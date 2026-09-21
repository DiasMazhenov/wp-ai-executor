<?php

/**
 * One capability source for planning, compiling and preflight. Unknown
 * widgets are downgraded to an explicit native fallback instead of emitted.
 */

defined( 'ABSPATH' ) || exit;

const WPAE_WIDGET_CAPABILITY_SCHEMA = 'wpae-widget-capability-v1';

function wpae_widget_capability_registry(): array {
	$registry = [
		'container' => [ 'available' => true, 'free_or_pro' => 'core', 'editable_content' => false, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => null ],
		'heading' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'text-editor' ],
		'text-editor' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'heading' ],
		'button' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'text-editor' ],
		'image' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => true, 'fallback_widget' => 'container' ],
		'icon' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'heading' ],
		'icon-list' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'text-editor' ],
		'divider' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => false, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'container' ],
		'accordion' => [ 'available' => true, 'free_or_pro' => 'free', 'editable_content' => true, 'responsive_support' => true, 'native_visual_controls' => true, 'media_required' => false, 'fallback_widget' => 'icon-list' ],
	];
	if ( function_exists( 'apply_filters' ) ) {
		$registry = apply_filters( 'wpae_widget_capability_registry', $registry );
	}
	return is_array( $registry ) ? $registry : [];
}

function wpae_widget_capability( string $widget_type ): array {
	$widget_type = sanitize_key( $widget_type );
	$registry = wpae_widget_capability_registry();
	$capability = is_array( $registry[ $widget_type ] ?? null ) ? $registry[ $widget_type ] : [
		'available' => false,
		'free_or_pro' => 'unknown',
		'editable_content' => false,
		'responsive_support' => false,
		'native_visual_controls' => false,
		'media_required' => false,
		'fallback_widget' => 'text-editor',
	];
	if ( class_exists( '\Elementor\Plugin' ) && method_exists( '\Elementor\Plugin', 'instance' ) ) {
		try {
			$instance = \Elementor\Plugin::instance();
			$manager = $instance->widgets_manager ?? null;
			if ( $manager && method_exists( $manager, 'get_widget_types' ) ) {
				$types = $manager->get_widget_types();
				$capability['available'] = isset( $types[ $widget_type ] ) || (bool) $capability['available'];
			}
		} catch ( Throwable $error ) {
			$capability['runtime_error'] = 'elementor_capability_probe_failed';
		}
	}
	$capability['widget_type'] = $widget_type;
	$capability['schema'] = WPAE_WIDGET_CAPABILITY_SCHEMA;
	return $capability;
}

function wpae_widget_capability_report( array $requested ): array {
	$requested = array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested ) ) ) );
	$available = [];
	$unavailable = [];
	$downgrades = [];
	foreach ( $requested as $widget_type ) {
		$capability = wpae_widget_capability( $widget_type );
		if ( ! empty( $capability['available'] ) ) {
			$available[] = $widget_type;
			continue;
		}
		$unavailable[] = $widget_type;
		$fallback = sanitize_key( (string) ( $capability['fallback_widget'] ?? 'text-editor' ) );
		$downgrades[] = [ 'from' => $widget_type, 'to' => $fallback, 'reason' => 'widget_unavailable', 'capability' => $capability ];
	}
	return [
		'schema' => WPAE_WIDGET_CAPABILITY_SCHEMA,
		'ok' => empty( $unavailable ),
		'requested' => $requested,
		'available' => $available,
		'unavailable' => $unavailable,
		'downgrades' => $downgrades,
	];
}

function wpae_widget_capability_resolve( string $widget_type ): array {
	$capability = wpae_widget_capability( $widget_type );
	if ( ! empty( $capability['available'] ) ) {
		return [ 'widget_type' => sanitize_key( $widget_type ), 'downgraded' => false, 'capability' => $capability ];
	}
	$fallback = sanitize_key( (string) ( $capability['fallback_widget'] ?? 'text-editor' ) );
	return [ 'widget_type' => $fallback, 'downgraded' => true, 'from' => sanitize_key( $widget_type ), 'reason' => 'widget_unavailable', 'capability' => $capability ];
}
