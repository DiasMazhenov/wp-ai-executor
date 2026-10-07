<?php

/** Resolve semantic design references without making the model choose raw CSS values. */

defined( 'ABSPATH' ) || exit;

const WPAE_DESIGN_TOKEN_SCHEMA = 'wpae-design-token-ref-v1';

function wpae_design_token_defaults(): array {
	return [
		'color.page_bg' => '#f6f0e6',
		'color.surface' => '#ffffff',
		'color.text' => '#111827',
		'color.muted' => '#4b5563',
		'color.primary' => '#4460EC',
		'color.border' => '#d1d5db',
		'color.focus' => '#2563eb',
		'color.hover' => '#2563eb',
		'type.display' => [ 'font_family' => 'inherit', 'desktop' => '3.5rem', 'tablet' => '2.75rem', 'mobile' => '2.25rem', 'weight' => '700', 'line_height' => '1.05' ],
		'type.section_title' => [ 'font_family' => 'inherit', 'desktop' => '2.5rem', 'tablet' => '2rem', 'mobile' => '1.75rem', 'weight' => '700', 'line_height' => '1.15' ],
		'type.body' => [ 'font_family' => 'inherit', 'desktop' => '1rem', 'tablet' => '1rem', 'mobile' => '1rem', 'weight' => '400', 'line_height' => '1.6' ],
		'space.section' => '4.5rem',
		'space.component' => '1.5rem',
		'radius.card' => '0.5rem',
	];
}

function wpae_design_token_ref( string $token ): string {
	$token = trim( strtolower( $token ) );
	return preg_replace( '/[^a-z0-9._-]/', '', $token ) ?? '';
}

function wpae_design_token_value( string $token, array $tokens = [], ?array &$report = null ) {
	$token = wpae_design_token_ref( $token );
	$defaults = wpae_design_token_defaults();
	$parts = explode( '.', $token, 3 );
	$value = null;
	$source = 'safe_default';
	$project_paths = [
		'color.page_bg' => [ 'palette', 'paper' ],
		'color.surface' => [ 'palette', 'surface' ],
		'color.text' => [ 'palette', 'ink' ],
		'color.muted' => [ 'palette', 'muted' ],
		'color.primary' => [ 'palette', 'accent' ],
		'color.border' => [ 'palette', 'border' ],
		'color.focus' => [ 'palette', 'support' ],
		'color.hover' => [ 'palette', 'support' ],
		'type.display' => [ 'native_tokens', 'typography', 'display' ],
		'type.section_title' => [ 'native_tokens', 'typography', 'subheading' ],
		'type.body' => [ 'native_tokens', 'typography', 'body' ],
		'space.section' => [ 'native_tokens', 'spacing', 'section_desktop' ],
		'space.component' => [ 'native_tokens', 'spacing', 'component' ],
		'radius.card' => [ 'native_tokens', 'radii', 'card' ],
	];
	$path = $project_paths[ $token ] ?? null;
	if ( is_array( $path ) ) {
		$cursor = $tokens;
		foreach ( $path as $segment ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $segment, $cursor ) ) {
				$cursor = null;
				break;
			}
			$cursor = $cursor[ $segment ];
		}
		if ( ( is_scalar( $cursor ) && (string) $cursor !== '' ) || ( is_array( $cursor ) && ! empty( $cursor ) ) ) {
			$value = $cursor;
			$source = 'project';
			// wpae_get_project_design_tokens() fills omitted settings with bundled
			// defaults. Keep their value, but do not claim the site owner supplied it.
			if ( isset( $tokens['design_prohibitions'], $tokens['button_style'] ) && function_exists( 'get_option' ) ) {
				$stored = get_option( 'wp_ai_executor_design_tokens', [] );
				$stored_cursor = is_array( $stored ) ? $stored : [];
				foreach ( $path as $segment ) {
					if ( ! is_array( $stored_cursor ) || ! array_key_exists( $segment, $stored_cursor ) ) {
						$stored_cursor = null;
						break;
					}
					$stored_cursor = $stored_cursor[ $segment ];
				}
				if ( $stored_cursor === null ) {
					$source = 'safe_default';
				}
			}
		}
	}
	if ( array_key_exists( $token, $tokens ) ) { $value = $tokens[ $token ]; $source = 'resolved_plan'; }
	if ( $value === null && isset( $defaults[ $token ] ) ) {
		$value = $defaults[ $token ];
		if ( is_array( $report ) ) {
			$report['fallbacks'][] = [ 'token' => $token, 'value' => $value, 'reason' => 'missing_project_token' ];
		}
	}
	if ( $value === null ) {
		if ( is_array( $report ) ) {
			$report['missing'][] = $token;
		}
		return null;
	}
	if ( is_array( $report ) ) {
		if ( $source === 'safe_default' && isset( $path ) && ! in_array( $token, array_column( (array) ( $report['fallbacks'] ?? [] ), 'token' ), true ) ) {
			$report['fallbacks'][] = [ 'token' => $token, 'value' => $value, 'reason' => 'missing_project_token' ];
		}
		$report['resolved'][] = [ 'token' => $token, 'value' => $value, 'source' => $source ];
	}
	return $value;
}

/**
 * Resolve only color values with an inspectable owner. Saved WPAE values that
 * still equal the bundled palette are ambiguous, so Elementor Global Colors
 * or inherited theme colors take precedence over them.
 */
function wpae_design_palette_resolve_sources( array $stored, array $kit_settings = [], string $theme_background = '' ): array {
	$default_project = function_exists( 'wpae_project_design_token_defaults' ) ? wpae_project_design_token_defaults() : [ 'palette' => [] ];
	$project_roles = [
		'color.page_bg' => 'paper', 'color.surface' => 'surface', 'color.text' => 'ink',
		'color.muted' => 'muted', 'color.primary' => 'accent', 'color.border' => 'border',
		'color.focus' => 'support', 'color.hover' => 'support',
	];
	$values = [];
	$sources = [];
	$unconfirmed = [];
	$normalize = static function ( $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( '/^#[0-9a-f]{6}(?:[0-9a-f]{2})?$/', $value ) ? $value : '';
	};
	$global_colors = [];
	// Elementor seeds these four IDs with stock values. Their presence in the
	// active kit is not evidence that the site owner selected them as brand
	// colors, so do not let them satisfy semantic roles until customized.
	$elementor_seed_colors = [ 'primary' => '#6ec1e4', 'secondary' => '#54595f', 'text' => '#7a7a7a', 'accent' => '#61ce70' ];
	foreach ( (array) ( $kit_settings['system_colors'] ?? [] ) as $entry ) {
		if ( ! is_array( $entry ) ) { continue; }
		$id = sanitize_key( (string) ( $entry['_id'] ?? $entry['id'] ?? '' ) );
		$title = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', ' ', (string) ( $entry['title'] ?? '' ) ) ?? '' ) );
		$color = $normalize( $entry['color'] ?? '' );
		if ( isset( $elementor_seed_colors[$id] ) && $color === $elementor_seed_colors[$id] ) {
			$unconfirmed['elementor_system_color.' . $id] = 'matches_elementor_stock_default';
			continue;
		}
		if ( $id !== '' && $color !== '' ) { $global_colors[$id] = $color; }
		if ( $title !== '' && $color !== '' ) { $global_colors['title:' . $title] = $color; }
	}
	// Custom colors are explicit owner-authored values, even when their ID or
	// title happens to match a system role.
	foreach ( (array) ( $kit_settings['custom_colors'] ?? [] ) as $entry ) {
		if ( ! is_array( $entry ) ) { continue; }
		$id = sanitize_key( (string) ( $entry['_id'] ?? $entry['id'] ?? '' ) );
		$title = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', ' ', (string) ( $entry['title'] ?? '' ) ) ?? '' ) );
		$color = $normalize( $entry['color'] ?? '' );
		if ( $id !== '' && $color !== '' ) { $global_colors[$id] = $color; }
		if ( $title !== '' && $color !== '' ) { $global_colors['title:' . $title] = $color; }
	}
	$resolve_kit_color = static function ( $value ) use ( $normalize, &$global_colors ): string {
		$hex = $normalize( $value );
		if ( $hex !== '' ) { return $hex; }
		if ( preg_match( '/(?:^|[?&])id=([a-z0-9_-]+)/i', (string) $value, $matches ) ) {
			return (string) ( $global_colors[sanitize_key( $matches[1] )] ?? '' );
		}
		return '';
	};
	$title_roles = [
		'color.page_bg' => [ 'page background', 'page bg', 'canvas', 'background' ],
		'color.surface' => [ 'surface', 'card surface', 'card background', 'surface background' ],
		'color.text' => [ 'text', 'body text', 'foreground' ],
		'color.muted' => [ 'muted', 'muted text', 'secondary text' ],
		'color.primary' => [ 'primary' ],
		'color.border' => [ 'border', 'outline' ],
		'color.focus' => [ 'focus', 'focus ring' ],
		'color.hover' => [ 'hover', 'button hover', 'hover background' ],
	];
	$kit_roles = [];
	foreach ( $title_roles as $role => $titles ) {
		foreach ( $titles as $title ) {
			if ( isset( $global_colors['title:' . $title] ) ) { $kit_roles[$role] = $global_colors['title:' . $title]; break; }
		}
	}
	foreach ( [ 'primary' => 'color.primary', 'text' => 'color.text', 'secondary' => 'color.muted' ] as $id => $role ) {
		if ( isset( $global_colors[$id] ) && ! isset( $kit_roles[$role] ) ) { $kit_roles[$role] = $global_colors[$id]; }
	}
	$kit_background = '';
	foreach ( [ 'body_background_color', 'page_background_color', 'background_color' ] as $key ) {
		$kit_background = $resolve_kit_color( $kit_settings[$key] ?? '' );
		if ( $kit_background !== '' ) { break; }
	}
	if ( $kit_background !== '' && ! isset( $kit_roles['color.page_bg'] ) ) { $kit_roles['color.page_bg'] = $kit_background; }
	$button_hover = $resolve_kit_color( $kit_settings['button_background_hover_color'] ?? $kit_settings['button_hover_background_color'] ?? '' );
	if ( $button_hover !== '' ) { $kit_roles['color.hover'] = $button_hover; }
	foreach ( $project_roles as $role => $project_key ) {
		$value = $normalize( $stored['palette'][$project_key] ?? '' );
		$default = $normalize( $default_project['palette'][$project_key] ?? '' );
		if ( $value === '' ) { continue; }
		if ( $default !== '' && $value === $default ) {
			$unconfirmed[$role] = 'saved_project_value_matches_bundle_default';
			continue;
		}
		$values[$role] = $value;
		$sources[$role] = 'confirmed_wpae_project_option:palette.' . $project_key;
	}
	foreach ( $kit_roles as $role => $value ) {
		if ( ! isset( $values[$role] ) ) { $values[$role] = $value; $sources[$role] = 'elementor_global_color'; }
	}
	if ( ! isset( $values['color.page_bg'] ) && $theme_background !== '' ) {
		$theme_background = $resolve_kit_color( $theme_background );
		if ( $theme_background !== '' ) { $values['color.page_bg'] = $theme_background; $sources['color.page_bg'] = 'confirmed_theme_global_background'; }
	}
	if ( isset( $values['color.page_bg'] ) && ! isset( $values['color.surface'] ) ) {
		$values['color.surface'] = $values['color.page_bg'];
		$sources['color.surface'] = 'semantic_alias:color.page_bg';
	}
	if ( isset( $global_colors['secondary'] ) && ! isset( $values['color.muted'] ) ) {
		$values['color.muted'] = $global_colors['secondary'];
		$sources['color.muted'] = 'semantic_alias:elementor_global_color.secondary';
	}
	if ( isset( $values['color.text'] ) && ! isset( $values['color.muted'] ) ) {
		$values['color.muted'] = $values['color.text'];
		$sources['color.muted'] = 'semantic_alias:color.text';
	}
	if ( isset( $values['color.primary'] ) && ! isset( $values['color.focus'] ) ) {
		$values['color.focus'] = $values['color.primary'];
		$sources['color.focus'] = 'contrast_safe_semantic_alias:color.primary';
	}
	if ( isset( $values['color.primary'] ) && ! isset( $values['color.hover'] ) ) {
		$values['color.hover'] = $values['color.primary'];
		$sources['color.hover'] = 'semantic_alias:color.primary';
	}
	if ( ! isset( $values['color.border'] ) && isset( $values['color.page_bg'] ) ) {
		$values['color.border'] = 'transparent';
		$sources['color.border'] = 'no_confirmed_border_role_transparent';
	} elseif ( ! isset( $values['color.border'] ) ) {
		$unconfirmed['color.border'] = 'transparent_border_requires_confirmed_surface_context';
	}
	$required = [ 'color.page_bg', 'color.surface', 'color.text', 'color.muted', 'color.primary', 'color.focus', 'color.hover' ];
	$missing = array_values( array_filter( $required, static fn( $role ): bool => ! isset( $values[$role] ) ) );
	return [ 'values' => $values, 'sources' => $sources, 'missing' => $missing, 'unconfirmed' => $unconfirmed, 'global_color_ids' => array_values( array_filter( array_keys( $global_colors ), static fn( $key ): bool => ! str_starts_with( $key, 'title:' ) ) ) ];
}

/** Read the active Elementor kit and raw WPAE option without treating UI-filled defaults as brand input. */
function wpae_design_palette_runtime_sources(): array {
	$stored = function_exists( 'get_option' ) ? get_option( 'wp_ai_executor_design_tokens', [] ) : [];
	$kit_id = function_exists( 'get_option' ) ? absint( get_option( 'elementor_active_kit', 0 ) ) : 0;
	$kit_settings = $kit_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : [];
	$kit_settings = is_array( $kit_settings ) ? $kit_settings : [];
	$theme_background = '';
	if ( function_exists( 'wp_get_global_styles' ) ) {
		$global = wp_get_global_styles( [ 'color', 'background' ] );
		if ( is_string( $global ) ) { $theme_background = $global; }
		elseif ( is_array( $global ) ) {
			$background = $global['color']['background'] ?? $global['background']['color'] ?? '';
			if ( is_string( $background ) ) { $theme_background = $background; }
		}
	}
	if ( $theme_background === '' && function_exists( 'get_theme_mod' ) ) {
		$theme_background = (string) get_theme_mod( 'background_color', '' );
		if ( preg_match( '/^[0-9a-f]{6}$/i', $theme_background ) ) { $theme_background = '#' . $theme_background; }
	}
	return wpae_design_palette_resolve_sources( is_array( $stored ) ? $stored : [], $kit_settings, $theme_background );
}

function wpae_design_token_precedence( array $explicit = [], array $reference = [], array $page = [], array $project = [], array $defaults = [] ): array {
	$defaults = $defaults ?: wpae_design_token_defaults();
	return array_replace( $defaults, $project, $page, $reference, $explicit );
}

function wpae_design_token_validate_refs( array $refs, array $tokens = [] ): array {
	$report = [ 'ok' => true, 'resolved' => [], 'missing' => [], 'fallbacks' => [], 'collisions' => [] ];
	$seen = [];
	foreach ( $refs as $ref ) {
		$ref = wpae_design_token_ref( (string) $ref );
		if ( $ref === '' ) {
			continue;
		}
		if ( isset( $seen[ $ref ] ) ) {
			$report['collisions'][] = $ref;
		}
		$seen[ $ref ] = true;
		wpae_design_token_value( $ref, $tokens, $report );
	}
	$report['missing'] = array_values( array_unique( $report['missing'] ) );
	$report['collisions'] = array_values( array_unique( $report['collisions'] ) );
	$report['ok'] = empty( $report['missing'] );
	return $report;
}

function wpae_design_hex_luminance( string $color ): ?float {
	$color = ltrim( trim( $color ), '#' );
	if ( strlen( $color ) === 8 ) {
		$color = substr( $color, 0, 6 );
	}
	if ( strlen( $color ) !== 6 || ! ctype_xdigit( $color ) ) {
		return null;
	}
	$channels = [];
	foreach ( str_split( $color, 2 ) as $channel ) {
		$value = hexdec( $channel ) / 255;
		$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}
	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

function wpae_design_token_validate_contrast( array $tokens = [], array $context = [] ): array {
	$report = [ 'ok' => true, 'pairs' => [], 'errors' => [] ];
	$pairs = [
		[ 'foreground' => 'color.text', 'background' => 'color.page_bg', 'minimum' => 4.5, 'font_size_px' => 16 ],
		[ 'foreground' => 'color.muted', 'background' => 'color.page_bg', 'minimum' => 4.5, 'font_size_px' => 16 ],
		[ 'foreground' => 'color.surface', 'background' => 'color.primary', 'minimum' => 3.0, 'font_size_px' => 16, 'ui_object' => true ],
		[ 'foreground' => 'color.surface', 'background' => 'color.hover', 'minimum' => 3.0, 'font_size_px' => 16, 'ui_object' => true ],
		[ 'foreground' => 'color.focus', 'background' => 'color.page_bg', 'minimum' => 3.0, 'font_size_px' => 16, 'ui_object' => true ],
	];
	foreach ( $pairs as $pair ) {
		$font_size_px = is_numeric( $context[ $pair['foreground'] . '.font_size_px' ] ?? null ) ? (float) $context[ $pair['foreground'] . '.font_size_px' ] : (float) $pair['font_size_px'];
		$font_weight = is_numeric( $context[ $pair['foreground'] . '.font_weight' ] ?? null ) ? (int) $context[ $pair['foreground'] . '.font_weight' ] : 400;
		$is_large_text = $font_size_px >= 24 || ( $font_size_px >= 18.66 && $font_weight >= 700 );
		$minimum = ! empty( $pair['ui_object'] ) || $is_large_text ? 3.0 : (float) $pair['minimum'];
		$foreground = wpae_design_token_value( $pair['foreground'], $tokens );
		$background = wpae_design_token_value( $pair['background'], $tokens );
		$foreground_luminance = is_string( $foreground ) ? wpae_design_hex_luminance( $foreground ) : null;
		$background_luminance = is_string( $background ) ? wpae_design_hex_luminance( $background ) : null;
		$ratio = null;
		if ( $foreground_luminance !== null && $background_luminance !== null ) {
			$ratio = ( max( $foreground_luminance, $background_luminance ) + 0.05 ) / ( min( $foreground_luminance, $background_luminance ) + 0.05 );
		}
		$entry = [ 'foreground' => $pair['foreground'], 'background' => $pair['background'], 'ratio' => $ratio, 'minimum' => $minimum, 'font_size_px' => $font_size_px, 'font_weight' => $font_weight, 'large_text' => $is_large_text, 'ui_object' => ! empty( $pair['ui_object'] ) ];
		$report['pairs'][] = $entry;
		if ( $ratio === null || $ratio + 0.0001 < $minimum ) {
			$report['errors'][] = $pair['foreground'] . '_on_' . $pair['background'];
		}
	}
	$report['ok'] = empty( $report['errors'] );
	return $report;
}
