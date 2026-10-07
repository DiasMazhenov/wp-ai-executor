<?php
/** Versioned, control-aware native serialization rules shared by compiler and verifier. */
defined( 'ABSPATH' ) || exit;

const WPAE_NATIVE_ROUNDTRIP_VERSION = 'wpae-native-roundtrip-v2';

/** Existing v1 contracts remain readable while new freezes record v2. */
function wpae_native_roundtrip_supported_version( $version ): bool {
	return in_array( $version, [ null, 'wpae-native-roundtrip-v1', WPAE_NATIVE_ROUNDTRIP_VERSION ], true );
}

/** Resolve Elementor Global Color IDs from the active kit without guessing from labels. */
function wpae_native_roundtrip_elementor_global_colors( ?array $kit_settings = null ): array {
	if ( $kit_settings === null ) {
		$kit_id = function_exists( 'get_option' ) ? absint( get_option( 'elementor_active_kit', 0 ) ) : 0;
		$kit_settings = $kit_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : [];
	}
	$kit_settings = is_array( $kit_settings ) ? $kit_settings : [];
	$colors = [];
	foreach ( [ 'system_colors', 'custom_colors' ] as $group ) {
		foreach ( (array) ( $kit_settings[$group] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) { continue; }
			$id = sanitize_key( (string) ( $entry['_id'] ?? $entry['id'] ?? '' ) );
			$color = strtolower( trim( (string) ( $entry['color'] ?? '' ) ) );
			if ( $id !== '' && preg_match( '/^#[0-9a-f]{6}$/', $color ) ) { $colors[$id] = $color; }
		}
	}
	return $colors;
}

/**
 * Elementor can serialize an inherited container background as a Global Color reference.
 * Project only that exact, read-only serialization when it resolves to the frozen Plan
 * page background. A changed global value or any other global control remains a conflict.
 */
function wpae_native_roundtrip_project_plan_background_global( array $expected_settings, array $actual_settings, array $roundtrip_context, ?array &$changes = null, string $node_type = '' ): bool {
	if ( $changes === null ) { $changes = []; }
	if ( $node_type !== 'container' || ! wpae_native_roundtrip_supported_version( $roundtrip_context['version'] ?? null ) || empty( $roundtrip_context['version'] ) ) { return false; }
	if ( ! array_key_exists( 'background_color', $expected_settings ) || $expected_settings['background_color'] !== '' || ( $actual_settings['background_color'] ?? null ) !== '' ) { return false; }
	$globals = $actual_settings['__globals__'] ?? null;
	if ( ! is_array( $globals ) || array_keys( $globals ) !== [ 'background_color' ] ) { return false; }
	$reference = $globals['background_color'];
	if ( ! is_string( $reference ) || ! preg_match( '#^globals/colors\?id=([a-z0-9_-]+)$#i', $reference, $match ) ) { return false; }
	$plan_background = strtolower( trim( (string) ( $roundtrip_context['page_background'] ?? '' ) ) );
	if ( ! preg_match( '/^#[0-9a-f]{6}$/', $plan_background ) ) { return false; }
	$colors = is_array( $roundtrip_context['global_colors'] ?? null ) ? $roundtrip_context['global_colors'] : [];
	$resolved = strtolower( trim( (string) ( $colors[ sanitize_key( $match[1] ) ] ?? '' ) ) );
	if ( ! preg_match( '/^#[0-9a-f]{6}$/', $resolved ) || ! hash_equals( $plan_background, $resolved ) ) { return false; }
	$changes[] = [
		'control' => '__globals__.background_color',
		'kind' => 'plan_bound_elementor_global_color_reference',
		'global_reference_sha256' => hash( 'sha256', $reference ),
		'resolved_color_sha256' => hash( 'sha256', $resolved ),
		'plan_role' => 'color.page_bg',
	];
	return true;
}

/** Elementor Accordion repeater IDs are technical identifiers, not authored content. */
function wpae_native_roundtrip_is_elementor_repeater_id( $value ): bool {
	return is_string( $value ) && preg_match( '/^[A-Za-z0-9_-]{7}$/', $value ) === 1;
}

/** Only verified single-line plain text is wrapped; authored HTML remains untouched. */
function wpae_native_roundtrip_plain_text_paragraph( $value ): ?string {
	if ( ! is_string( $value ) || $value === '' || trim( $value ) === '' || strpbrk( $value, "<>\r\n" ) !== false ) {
		return null;
	}
	return '<p>' . $value . '</p>';
}

/** Apply the native editor's confirmed plain-text WYSIWYG representation before freeze. */
function wpae_native_roundtrip_compile_tree( array $elements ): array {
	$decisions = [];
	$walk = static function ( array $nodes, string $path = '$' ) use ( &$walk, &$decisions ): array {
		foreach ( $nodes as $index => &$node ) {
			if ( ! is_array( $node ) ) { continue; }
			$node_path = $path . '[' . (int) $index . ']';
			if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'accordion' ) {
				$tabs = (array) ( $node['settings']['tabs'] ?? [] );
				foreach ( $tabs as $tab_index => &$tab ) {
					if ( ! is_array( $tab ) || ! array_key_exists( 'tab_content', $tab ) ) { continue; }
					$source = $tab['tab_content'];
					$native = wpae_native_roundtrip_plain_text_paragraph( $source );
					if ( $native === null ) { continue; }
					$tab['tab_content'] = $native;
					$decisions[] = [
						'control' => $node_path . '.settings.tabs[' . (int) $tab_index . '].tab_content',
						'kind' => 'plain_text_to_single_paragraph',
						'source_sha256' => hash( 'sha256', $source ),
						'native_sha256' => hash( 'sha256', $native ),
						'exact_copy_preserved' => true,
					];
				}
				unset( $tab );
				$node['settings']['tabs'] = $tabs;
			}
			if ( is_array( $node['elements'] ?? null ) ) { $node['elements'] = $walk( $node['elements'], $node_path . '.elements' ); }
		}
		unset( $node );
		return $nodes;
	};
	return [
		'elements' => $walk( $elements ),
		'diagnostics' => [
			'version' => WPAE_NATIVE_ROUNDTRIP_VERSION,
			'control_aware' => true,
			'authored_copy' => 'BriefIR exact_text retained; native representation freezes before accepted signature',
			'repeater_order_and_count' => 'authored and strictly compared',
			'repeater_ids' => 'technical-only; seven-character Elementor IDs may be projected to the accepted IDs',
			'allowed_legacy_serialization' => 'one exact <p> wrapper around single-line plain Accordion answer text; a plan-bound Elementor global page-background reference',
			'global_background_reference' => 'single background_color reference on an inherited container only when its active-kit value equals the frozen color.page_bg',
			'registered_platform_defaults' => [],
			'decisions' => $decisions,
		],
	];
}

/**
 * Project one Accordion tabs control into the accepted representation.
 * Only the exact legacy WYSIWYG paragraph wrapper and valid technical repeater IDs are canonicalized.
 */
function wpae_native_roundtrip_project_accordion_tabs( $expected, $actual, ?array &$mismatch = null, ?array &$changes = null, ?string $adapter_version = null ): ?array {
	$changes = [];
	if ( ! wpae_native_roundtrip_supported_version( $adapter_version ) ) {
		$mismatch = [ 'control' => 'tabs', 'reason' => 'unknown_native_roundtrip_version' ];
		return null;
	}
	if ( ! is_array( $expected ) || ! is_array( $actual ) || array_values( $expected ) !== $expected || array_values( $actual ) !== $actual || count( $expected ) !== count( $actual ) ) {
		$mismatch = [ 'reason' => 'accordion_repeater_count_or_shape' ];
		return null;
	}
	$projected = [];
	$actual_ids = [];
	foreach ( $expected as $index => $expected_tab ) {
		$actual_tab = $actual[ $index ] ?? null;
		if ( ! is_array( $expected_tab ) || ! is_array( $actual_tab ) ) {
			$mismatch = [ 'control' => 'tabs', 'reason' => 'accordion_repeater_item_shape', 'item_index' => $index ];
			return null;
		}
		$expected_keys = array_keys( $expected_tab );
		$actual_keys = array_keys( $actual_tab );
		sort( $expected_keys ); sort( $actual_keys );
		if ( $expected_keys !== $actual_keys ) {
			$mismatch = [ 'control' => 'tabs', 'reason' => 'accordion_repeater_fields_changed', 'item_index' => $index ];
			return null;
		}
		$expected_id = $expected_tab['_id'] ?? null;
		$actual_id = $actual_tab['_id'] ?? null;
		if ( wpae_native_roundtrip_is_elementor_repeater_id( $actual_id ) ) {
			if ( isset( $actual_ids[ $actual_id ] ) ) {
				$mismatch = [ 'control' => 'tabs._id', 'reason' => 'invalid_or_duplicate_technical_repeater_id', 'item_index' => $index ];
				return null;
			}
			$actual_ids[ $actual_id ] = true;
		}
		if ( $expected_id !== $actual_id ) {
			if ( ! wpae_native_roundtrip_is_elementor_repeater_id( $expected_id ) || ! wpae_native_roundtrip_is_elementor_repeater_id( $actual_id ) ) {
				$mismatch = [ 'control' => 'tabs._id', 'reason' => 'invalid_or_duplicate_technical_repeater_id', 'item_index' => $index ];
				return null;
			}
			$changes[] = [ 'control' => 'tabs[' . $index . ']._id', 'kind' => 'technical_repeater_id', 'exact' => true ];
		}
		foreach ( $expected_tab as $key => $expected_value ) {
			$actual_value = $actual_tab[ $key ] ?? null;
			if ( $key === '_id' ) { continue; }
			if ( $key === 'tab_content' && $expected_value !== $actual_value ) {
				$paragraph = wpae_native_roundtrip_plain_text_paragraph( $expected_value );
				if ( $paragraph === null || $actual_value !== $paragraph ) {
					$mismatch = [ 'control' => 'tabs.tab_content', 'reason' => 'authored_control_changed', 'item_index' => $index ];
					return null;
				}
				$actual_tab[ $key ] = $expected_value;
				$changes[] = [ 'control' => 'tabs[' . $index . '].tab_content', 'kind' => 'legacy_single_paragraph_serialization', 'source_sha256' => hash( 'sha256', $expected_value ), 'native_sha256' => hash( 'sha256', $actual_value ), 'exact_copy_preserved' => true ];
				continue;
			}
			if ( $expected_value !== $actual_value ) {
				$mismatch = [ 'control' => 'tabs.' . sanitize_key( (string) $key ), 'reason' => 'authored_control_changed', 'item_index' => $index ];
				return null;
			}
		}
		$actual_tab['_id'] = $expected_tab['_id'] ?? null;
		$projected[] = $actual_tab;
	}
	return $projected;
}
