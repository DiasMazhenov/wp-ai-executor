<?php

/** Runtime-backed Elementor capabilities shared by planning and compilation. */

defined( 'ABSPATH' ) || exit;

const WPAE_WIDGET_CAPABILITY_SCHEMA = 'wpae-widget-capability-v1';

function wpae_widget_capability_registry(): array {
	$registry = [
		'container' => [ 'available' => true, 'free_or_pro' => 'core', 'editable_content' => false, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'heading' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'text-editor' ],
		'text-editor' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'button' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'image' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => true, 'fallback_widget' => null ],
		'icon' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'icon-list' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'divider' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => false, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'accordion' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
	];
	if ( function_exists( 'apply_filters' ) ) {
		$registry = apply_filters( 'wpae_widget_capability_registry', $registry );
	}
	return is_array( $registry ) ? $registry : [];
}

function wpae_widget_compiler_supported_types(): array {
	return [ 'container', 'heading', 'text-editor', 'button', 'image', 'icon', 'icon-list', 'divider', 'accordion' ];
}

function wpae_widget_runtime_probe(): array {
	if ( ! class_exists( '\\Elementor\\Plugin' ) || ! method_exists( '\\Elementor\\Plugin', 'instance' ) ) {
		return [ 'state' => 'unavailable', 'reason' => 'elementor_runtime_missing' ];
	}
	try {
		$instance = \Elementor\Plugin::instance();
		$manager = is_object( $instance ) ? ( $instance->widgets_manager ?? null ) : null;
		if ( ! $manager || ! method_exists( $manager, 'get_widget_types' ) ) {
			return [ 'state' => 'unavailable', 'reason' => 'widget_manager_unavailable' ];
		}
		$types = $manager->get_widget_types();
		if ( ! is_array( $types ) ) {
			return [ 'state' => 'unavailable', 'reason' => 'widget_registry_invalid' ];
		}
		if ( function_exists( 'did_action' ) && did_action( 'elementor/widgets/register' ) < 1 ) {
			return [ 'state' => 'unavailable', 'reason' => 'widget_registration_not_ready' ];
		}
		return [ 'state' => 'confirmed', 'types' => $types ];
	} catch ( Throwable $error ) {
		return [ 'state' => 'error', 'reason' => 'widget_probe_failed' ];
	}
}

function wpae_widget_capability( string $widget_type ): array {
	$widget_type = sanitize_key( $widget_type );
	$registry = wpae_widget_capability_registry();
	$known = is_array( $registry[ $widget_type ] ?? null );
	$capability = $known ? $registry[ $widget_type ] : [
		'available' => false,
		'free_or_pro' => 'unknown',
		'editable_content' => false,
		'responsive_support' => false,
		'native_visual_controls' => false,
		'media_required' => false,
		'fallback_widget' => null,
	];
	$compiler_supported = in_array( $widget_type, wpae_widget_compiler_supported_types(), true );
	$static_allowed = $known && ! empty( $capability['available'] ) && $compiler_supported;
	$runtime = $widget_type === 'container'
		? [ 'state' => 'structural', 'reason' => 'elementor_container_element' ]
		: wpae_widget_runtime_probe();
	$runtime_has_type = $runtime['state'] === 'confirmed' && isset( $runtime['types'][ $widget_type ] );
	$available = $static_allowed && ( $widget_type === 'container' || $runtime_has_type );
	$reason = ! $known ? 'not_in_capability_registry'
		: ( ! $compiler_supported ? 'not_supported_by_compiler'
			: ( empty( $capability['available'] ) ? 'disabled_by_registry'
				: ( $runtime['state'] !== 'confirmed' ? (string) ( $runtime['reason'] ?? 'runtime_unavailable' )
					: ( $runtime_has_type ? 'runtime_confirmed' : 'runtime_widget_missing' ) ) ) );
	$capability['widget_type'] = $widget_type;
	$capability['schema'] = WPAE_WIDGET_CAPABILITY_SCHEMA;
	$capability['compiler_supported'] = $compiler_supported;
	$capability['static_policy'] = $known ? ( empty( $capability['available'] ) ? 'denied' : 'allowed' ) : 'unknown';
	$capability['runtime_state'] = $runtime['state'];
	$capability['runtime_result'] = $widget_type === 'container' ? 'structural' : ( $runtime['state'] === 'confirmed' ? ( $runtime_has_type ? 'present' : 'missing' ) : 'unknown' );
	$capability['available'] = $available;
	$capability['reason'] = $reason;
	return $capability;
}

function wpae_widget_capability_resolve( string $widget_type, array $context = [] ): array {
	$original = sanitize_key( $widget_type );
	$current = $original;
	$visited = [];
	$trace = [];
	for ( $depth = 0; $depth < 8; $depth++ ) {
		if ( isset( $visited[ $current ] ) ) {
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => 'fallback_cycle', 'trace' => $trace ];
		}
		$visited[ $current ] = true;
		$capability = wpae_widget_capability( $current );
		$trace[] = [ 'widget_type' => $current, 'available' => $capability['available'], 'runtime_result' => $capability['runtime_result'], 'reason' => $capability['reason'] ];
		if ( ! empty( $capability['available'] ) ) {
			return [ 'ok' => true, 'widget_type' => $current, 'from' => $original, 'downgraded' => $current !== $original, 'reason' => $current === $original ? 'runtime_confirmed' : 'safe_fallback', 'capability' => $capability, 'trace' => $trace ];
		}
		if ( $capability['runtime_state'] !== 'confirmed' && $current !== 'container' ) {
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => 'runtime_unverified', 'probe_reason' => $capability['reason'], 'trace' => $trace ];
		}
		$fallback = sanitize_key( (string) ( $capability['fallback_widget'] ?? '' ) );
		if ( $fallback === '' ) {
			$reason = $current !== $original ? 'fallback_unavailable' : ( $original === 'button' ? 'no_cta_preserving_fallback' : ( $original === 'image' ? 'no_media_preserving_fallback' : $capability['reason'] ) );
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => $reason, 'trace' => $trace ];
		}
		if ( ! in_array( $fallback, wpae_widget_compiler_supported_types(), true ) ) {
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => 'fallback_not_supported_by_compiler', 'fallback' => $fallback, 'trace' => $trace ];
		}
		if ( isset( $visited[ $fallback ] ) ) {
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => 'fallback_cycle', 'trace' => $trace ];
		}
		if ( $original !== 'heading' || $fallback !== 'text-editor' ) {
			$reason = $original === 'button' ? 'fallback_would_drop_cta_behavior' : ( $original === 'image' ? 'fallback_would_drop_media' : 'no_semantics_preserving_fallback' );
			return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => $reason, 'fallback' => $fallback, 'trace' => $trace ];
		}
		$current = $fallback;
	}
	return [ 'ok' => false, 'widget_type' => null, 'from' => $original, 'downgraded' => false, 'reason' => 'fallback_depth_exceeded', 'trace' => $trace ];
}

function wpae_widget_capability_report( array $requested ): array {
	$requested = array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested ) ) ) );
	$available = [];
	$unavailable = [];
	$unknown = [];
	$downgrades = [];
	$failures = [];
	$resolved = [];
	foreach ( $requested as $widget_type ) {
		$capability = wpae_widget_capability( $widget_type );
		$result = wpae_widget_capability_resolve( $widget_type );
		if ( ! empty( $capability['available'] ) ) {
			$available[] = $widget_type;
		}
		if ( ! empty( $result['ok'] ) ) {
			$resolved[] = [ 'from' => $widget_type, 'to' => $result['widget_type'], 'downgraded' => $result['downgraded'], 'runtime_result' => $capability['runtime_result'] ];
			if ( ! empty( $result['downgraded'] ) ) {
				$downgrades[] = $result;
			}
			continue;
		}
		$failure = [ 'from' => $widget_type, 'reason' => $result['reason'], 'runtime_result' => $capability['runtime_result'], 'probe_reason' => $result['probe_reason'] ?? null, 'trace' => $result['trace'] ?? [] ];
		$failures[] = $failure;
		if ( $capability['runtime_state'] === 'confirmed' || $capability['static_policy'] === 'denied' ) {
			$unavailable[] = $widget_type;
		} else {
			$unknown[] = $widget_type;
		}
	}
	return [
		'schema' => WPAE_WIDGET_CAPABILITY_SCHEMA,
		'ok' => empty( $failures ),
		'requested' => $requested,
		'available' => $available,
		'resolved' => $resolved,
		'unavailable' => array_values( array_unique( $unavailable ) ),
		'unknown' => array_values( array_unique( $unknown ) ),
		'downgrades' => $downgrades,
		'failures' => $failures,
	];
}
