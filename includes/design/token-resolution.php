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
		'type.display' => [ 'font_family' => 'inherit', 'desktop' => '3.5rem', 'tablet' => '2.75rem', 'mobile' => '2.25rem', 'weight' => '700', 'line_height' => '1.05' ],
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
		'type.display' => [ 'native_tokens', 'typography', 'display' ],
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
		}
	}
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
		$report['resolved'][] = [ 'token' => $token, 'value' => $value, 'source' => $source ];
	}
	return $value;
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
