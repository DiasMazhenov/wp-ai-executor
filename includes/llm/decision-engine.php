<?php

/**
 * Typed, bounded design decisions for WP AI Executor.
 *
 * The engine never returns Elementor JSON. It returns a small plan which is
 * compiled into the existing native fallback/write path by llm.php.
 */

defined( 'ABSPATH' ) || exit;

const WPAE_LLM_DESIGN_ENGINE_SCHEMA = 'wpae-edde-plan-v1';
const WPAE_LLM_DESIGN_ENGINE_STATE_SCHEMA = 'wpae-edde-state-v1';

function wpae_llm_design_engine_mode(): string {
	$settings = function_exists( 'wpae_llm_get_settings' ) ? wpae_llm_get_settings() : [];
	$mode = sanitize_key( (string) ( $settings['design_engine_mode'] ?? 'off' ) );
	return in_array( $mode, [ 'off', 'shadow', 'active' ], true ) ? $mode : 'off';
}

function wpae_llm_design_engine_schema(): array {
	return [
		'schema' => [ WPAE_LLM_DESIGN_ENGINE_SCHEMA ],
		'archetype' => [ 'hero' ],
		'composition' => [ 'split_60_40', 'split_50_50', 'split_40_60', 'stacked_left' ],
		'content_alignment' => [ 'left', 'center', 'right' ],
		'vertical_alignment' => [ 'start', 'center' ],
		'spacing_rhythm' => [ 'compact', 'balanced', 'spacious' ],
		'surface' => [ 'minimal', 'soft_panel', 'outlined' ],
		'typography' => [ 'display', 'neutral' ],
		'cta_hierarchy' => [ 'single_primary', 'primary_secondary' ],
		'responsive_strategy' => [ 'copy_first_stack' ],
	];
}

function wpae_llm_design_engine_explicit_constraints( string $message ): array {
	$message = sanitize_textarea_field( $message );
	$constraints = [];
	if ( preg_match( '~60\s*[/\\:]\s*40~iu', $message ) ) {
		$constraints['composition'] = 'split_60_40';
	} elseif ( preg_match( '~40\s*[/\\:]\s*60~iu', $message ) ) {
		$constraints['composition'] = 'split_40_60';
	} elseif ( preg_match( '~50\s*[/\\:]\s*50~iu', $message ) ) {
		$constraints['composition'] = 'split_50_50';
	} elseif ( preg_match( '/\b(?:stack|stacked|vertical|вертикаль\w*|стек)\b/iu', $message ) ) {
		$constraints['composition'] = 'stacked_left';
	}

	if ( preg_match( '/(?:текст\w*|контент\w*|выравнив\w*|align\w*)[^,.;\n]{0,24}\b(?:справа|right)\b/iu', $message ) || preg_match( '/\b(?:по\s+правому\s+краю|right[- ]aligned)\b/iu', $message ) ) {
		$constraints['content_alignment'] = 'right';
	} elseif ( preg_match( '/\b(?:по\s+центру|выровн\w*\s+по\s+центру|center)\b/iu', $message ) ) {
		$constraints['content_alignment'] = 'center';
	} elseif ( preg_match( '/(?:текст\w*|контент\w*|выравнив\w*|align\w*)[^,.;\n]{0,24}\b(?:слева|left)\b/iu', $message ) || preg_match( '/\b(?:по\s+левому\s+краю|left[- ]aligned)\b/iu', $message ) ) {
		$constraints['content_alignment'] = 'left';
	}
	if ( preg_match( '/\b(?:компакт\w*|плотн\w*|compact)\b/iu', $message ) ) {
		$constraints['spacing_rhythm'] = 'compact';
	} elseif ( preg_match( '/\b(?:простор\w*|воздух\w*|spacious)\b/iu', $message ) ) {
		$constraints['spacing_rhythm'] = 'spacious';
	}
	if ( preg_match( '/\b(?:обвод\w*|outline\w*|outlined)\b/iu', $message ) ) {
		$constraints['surface'] = 'outlined';
	} elseif ( preg_match( '/\b(?:панел\w*|panel\w*|soft\s+panel)\b/iu', $message ) ) {
		$constraints['surface'] = 'soft_panel';
	} elseif ( preg_match( '/\b(?:минимал\w*|minimal)\b/iu', $message ) ) {
		$constraints['surface'] = 'minimal';
	}
	if ( preg_match( '/\b(?:крупн\w*|display|выразительн\w*)\b/iu', $message ) ) {
		$constraints['typography'] = 'display';
	} elseif ( preg_match( '/\b(?:нейтрал\w*|neutral)\b/iu', $message ) ) {
		$constraints['typography'] = 'neutral';
	}
	if ( preg_match( '/\b(?:две|двух|втор\w*|secondary|primary_secondary)\s+(?:кноп\w*|cta)|\bprimary_secondary\b/iu', $message ) ) {
		$constraints['cta_hierarchy'] = 'primary_secondary';
	} elseif ( preg_match( '/\b(?:одна|одну|single_primary)\s+(?:кноп\w*|cta)|\bsingle_primary\b/iu', $message ) ) {
		$constraints['cta_hierarchy'] = 'single_primary';
	}
	$constraints['responsive_strategy'] = 'copy_first_stack';
	return $constraints;
}

function wpae_llm_design_engine_build_state( string $message, string $archetype, array $content_plan, array $editor_context = [], array $runtime = [] ): array {
	$tokens = function_exists( 'wpae_get_project_design_tokens' ) ? wpae_get_project_design_tokens() : [];
	$design_system = [
		'id' => function_exists( 'wpae_get_design_system_id' ) ? sanitize_key( (string) wpae_get_design_system_id() ) : '',
		'source' => 'project-native-tokens',
		'palette_keys' => is_array( $tokens['palette'] ?? null ) ? array_values( array_map( 'sanitize_key', array_keys( $tokens['palette'] ) ) ) : [],
	];
	return [
		'schema' => WPAE_LLM_DESIGN_ENGINE_STATE_SCHEMA,
		'archetype' => sanitize_key( $archetype ),
		'content_plan' => array_intersect_key( $content_plan, array_flip( [ 'archetype', 'content_slots', 'required_slots', 'cta_required', 'explicit_cta', 'content_pairs' ] ) ),
		'constraints' => wpae_llm_design_engine_explicit_constraints( $message ),
		'design_system' => $design_system,
		'editor' => [
			'post_id_present' => absint( $editor_context['post_id'] ?? 0 ) > 0,
			'selected_element_count' => is_array( $editor_context['selected_elements'] ?? null ) ? count( $editor_context['selected_elements'] ) : 0,
		],
		'provider' => [
			'name' => sanitize_key( (string) ( $runtime['provider'] ?? '' ) ),
			'model' => sanitize_text_field( (string) ( $runtime['model'] ?? '' ) ),
		],
	];
}

function wpae_llm_design_engine_prompt( array $state ): string {
	$schema = wpae_llm_design_engine_schema();
	return 'Ты принимаешь bounded design decision для hero-блока WordPress/Elementor. Верни только один JSON-объект, без markdown, Elementor JSON, CSS, цветов, confidence или пояснений. Выбирай только значения из enum schema. Уважай constraints из state; они имеют приоритет. responsive_strategy всегда copy_first_stack. Schema: ' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '. State: ' . wp_json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function wpae_llm_design_engine_decode_plan( string $reply, array $constraints = [] ): array {
	$reply = trim( $reply );
	if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $reply, $match ) ) {
		$reply = trim( (string) $match[1] );
	}
	$candidate = json_decode( $reply, true );
	if ( is_string( $candidate ) ) {
		$candidate = json_decode( $candidate, true );
	}
	if ( ! is_array( $candidate ) ) {
		return [ 'ok' => false, 'errors' => [ 'json' ] ];
	}
	$schema = wpae_llm_design_engine_schema();
	$allowed_keys = array_keys( $schema );
	$unknown = array_values( array_diff( array_keys( $candidate ), $allowed_keys ) );
	if ( ! empty( $unknown ) ) {
		return [ 'ok' => false, 'errors' => [ 'unknown_fields:' . implode( ',', array_map( 'sanitize_key', $unknown ) ) ] ];
	}
	$plan = [];
	foreach ( $allowed_keys as $key ) {
		if ( ! array_key_exists( $key, $candidate ) || ! is_string( $candidate[ $key ] ) ) {
			return [ 'ok' => false, 'errors' => [ 'missing_or_invalid:' . $key ] ];
		}
		$value = sanitize_key( $candidate[ $key ] );
		if ( ! in_array( $value, $schema[ $key ], true ) ) {
			return [ 'ok' => false, 'errors' => [ 'enum:' . $key ] ];
		}
		$plan[ $key ] = $value;
	}
	if ( $plan['archetype'] !== 'hero' || $plan['schema'] !== WPAE_LLM_DESIGN_ENGINE_SCHEMA ) {
		return [ 'ok' => false, 'errors' => [ 'schema_or_archetype' ] ];
	}
	$overrides = [];
	foreach ( $constraints as $key => $value ) {
		if ( isset( $schema[ $key ] ) && in_array( $value, $schema[ $key ], true ) ) {
			if ( ( $plan[ $key ] ?? null ) !== $value ) {
				$overrides[ $key ] = [ 'from' => $plan[ $key ] ?? null, 'to' => $value ];
			}
			$plan[ $key ] = $value;
		}
	}
	return [ 'ok' => true, 'plan' => $plan, 'explicit_overrides' => $overrides ];
}

function wpae_llm_design_engine_decide( string $message, string $archetype, array $content_plan, array $editor_context = [], array $runtime = [] ): array {
	$mode = wpae_llm_design_engine_mode();
	$state = wpae_llm_design_engine_build_state( $message, $archetype, $content_plan, $editor_context, $runtime );
	$trace = [
		'schema' => WPAE_LLM_DESIGN_ENGINE_SCHEMA,
		'mode' => $mode,
		'archetype' => sanitize_key( $archetype ),
		'provider' => sanitize_key( (string) ( $runtime['provider'] ?? '' ) ),
		'model' => sanitize_text_field( (string) ( $runtime['model'] ?? '' ) ),
		'calls' => 0,
		'max_calls' => 2,
		'status' => 'skipped',
	];
	if ( $mode === 'off' ) {
		$trace['reason'] = 'disabled';
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	if ( $archetype !== 'hero' ) {
		$trace['reason'] = 'archetype_out_of_scope';
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	if ( ! is_array( $runtime ) || empty( $runtime['api_key'] ) || empty( $runtime['base_url'] ) ) {
		$trace['reason'] = 'runtime_unavailable';
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	$url = untrailingslashit( (string) $runtime['base_url'] ) . '/chat/completions';
	$headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . (string) $runtime['api_key'] ];
	if ( ( $runtime['provider'] ?? '' ) === 'openrouter' ) {
		$headers['HTTP-Referer'] = home_url( '/' );
		$headers['X-Title'] = get_bloginfo( 'name' );
	}
	$request_body = [
		'model' => (string) $runtime['model'],
		'messages' => [
			[ 'role' => 'system', 'content' => wpae_llm_design_engine_prompt( $state ) ],
			[ 'role' => 'user', 'content' => sanitize_textarea_field( $message ) ],
		],
		'temperature' => 0,
		'max_completion_tokens' => 700,
		'response_format' => [ 'type' => 'json_object' ],
	];
	if ( ( $runtime['provider'] ?? '' ) === 'openrouter' ) {
		$request_body['provider'] = [ 'require_parameters' => true ];
	}
	$remote_args = [
		'timeout' => 25,
		'redirection' => 2,
		'limit_response_size' => 32768,
		'headers' => $headers,
		'body' => wp_json_encode( $request_body ),
	];
	$trace['calls'] = 1;
	$deadline = microtime( true ) + 25;
	$response = wpae_llm_provider_request( $url, $remote_args, $request_body, true, (string) $runtime['provider'], $deadline );
	if ( is_wp_error( $response ) ) {
		$trace['status'] = 'transport_error';
		$trace['reason'] = wpae_llm_diagnostic_text( $response->get_error_message() );
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	$status = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$trace['http_status'] = $status;
	if ( $status < 200 || $status >= 300 ) {
		$trace['status'] = 'http_error';
		$trace['reason'] = 'HTTP ' . (int) $status;
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	$reply = function_exists( 'wpae_llm_extract_response_text' ) ? wpae_llm_extract_response_text( is_array( $body ) ? $body : [] ) : '';
	$decoded = wpae_llm_design_engine_decode_plan( $reply, $state['constraints'] );
	if ( empty( $decoded['ok'] ) ) {
		$trace['status'] = 'invalid_plan';
		$trace['reason'] = implode( ',', array_map( 'sanitize_key', (array) ( $decoded['errors'] ?? [] ) ) );
		return [ 'ok' => false, 'trace' => $trace, 'constraints' => $state['constraints'] ];
	}
	$trace['status'] = 'ok';
	$trace['plan'] = $decoded['plan'];
	$trace['explicit_overrides'] = $decoded['explicit_overrides'];
	return [ 'ok' => true, 'plan' => $decoded['plan'], 'constraints' => $state['constraints'], 'trace' => $trace ];
}

function wpae_llm_design_engine_set_width( array &$settings, int $size ): void {
	$settings['width'] = [ 'unit' => '%', 'size' => $size, 'sizes' => [] ];
	$settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
	$settings['_element_custom_width'] = [ 'unit' => '%', 'size' => $size, 'sizes' => [] ];
	$settings['_element_custom_width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
}

function wpae_llm_design_engine_compile_hero( array $action, array $plan, string $message = '' ): array {
	$composition = sanitize_key( (string) ( $plan['composition'] ?? 'split_60_40' ) );
	$widths = [
		'split_60_40' => [ 60, 40 ],
		'split_50_50' => [ 50, 50 ],
		'split_40_60' => [ 40, 60 ],
		'stacked_left' => [ 100, 100 ],
	];
	[ $copy_width, $visual_width ] = $widths[ $composition ] ?? $widths['split_60_40'];
	$spacing = [ 'compact' => [ 1.25, 1 ], 'balanced' => [ 3, 1.5 ], 'spacious' => [ 4.5, 2 ] ];
	[ $desktop_gap, $mobile_gap ] = $spacing[ sanitize_key( (string) ( $plan['spacing_rhythm'] ?? 'balanced' ) ) ] ?? $spacing['balanced'];
	$alignment = sanitize_key( (string) ( $plan['content_alignment'] ?? 'left' ) );
	$flex_alignment = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $alignment ] ?? 'flex-start';
	$vertical = sanitize_key( (string) ( $plan['vertical_alignment'] ?? 'start' ) );
	$surface = sanitize_key( (string) ( $plan['surface'] ?? 'soft_panel' ) );
	$typography = sanitize_key( (string) ( $plan['typography'] ?? 'display' ) );
	$cta_hierarchy = sanitize_key( (string) ( $plan['cta_hierarchy'] ?? 'single_primary' ) );
	$surface_colors = [ 'minimal' => [ 'classic', 'transparent' ], 'soft_panel' => [ 'classic', '#e7c7b7' ], 'outlined' => [ 'classic', 'transparent' ] ];
	[ $background_background, $background_color ] = $surface_colors[ $surface ] ?? $surface_colors['soft_panel'];
	$explicit_background_color = '';
	if ( preg_match( '/(?:фон|background(?:-color)?|surface)[^#\n]{0,24}(#[0-9a-f]{6}(?:[0-9a-f]{2})?)/iu', sanitize_textarea_field( $message ), $color_match ) ) {
		$explicit_background_color = strtolower( (string) $color_match[1] );
	}
	$stats = [ 'nodes_updated' => 0, 'shell_found' => false, 'copy_found' => false, 'visual_found' => false, 'buttons_styled' => 0 ];
	$button_index = 0;
	$apply = static function ( array &$nodes ) use ( &$apply, &$stats, &$button_index, $copy_width, $visual_width, $composition, $desktop_gap, $mobile_gap, $flex_alignment, $vertical, $surface, $background_background, $background_color, $explicit_background_color, $typography, $cta_hierarchy ): void {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
			$classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
			$classes = is_array( $classes ) ? $classes : [];
			$id = sanitize_key( (string) ( $node['id'] ?? '' ) );
			$is_shell = in_array( 'wpae-hero-content-shell', $classes, true ) || $id === 'llm-hero-content-shell';
			$is_copy = in_array( 'wpae-hero-copy-column', $classes, true ) || $id === 'llm-hero-copy-column';
			$is_visual = in_array( 'wpae-hero-visual-panel', $classes, true ) || $id === 'llm-hero-visual-panel';
			$is_explicit_root = $explicit_background_color !== '' && ( $id === 'llm-fallback' || $id === 'wpae-generated-hero' );
			if ( $is_explicit_root ) {
				$settings['background_background'] = 'classic';
				$settings['background_color'] = $explicit_background_color;
			}
			if ( $is_shell ) {
				$settings['flex_direction'] = $composition === 'stacked_left' ? 'column' : 'row';
				$settings['flex_direction_mobile'] = 'column';
				$settings['flex_gap'] = [ 'column' => (string) $desktop_gap, 'row' => (string) $desktop_gap, 'isLinked' => true, 'unit' => 'rem', 'size' => $desktop_gap ];
				$settings['flex_gap_mobile'] = [ 'column' => (string) $mobile_gap, 'row' => (string) $mobile_gap, 'isLinked' => true, 'unit' => 'rem', 'size' => $mobile_gap ];
				$settings['flex_align_items'] = $vertical === 'center' ? 'center' : 'stretch';
				$settings['flex_justify_content'] = $vertical === 'center' ? 'center' : 'flex-start';
				$stats['shell_found'] = true;
			} elseif ( $is_copy ) {
				wpae_llm_design_engine_set_width( $settings, $copy_width );
				$settings['flex_align_items'] = $flex_alignment;
				$settings['flex_align_items_mobile'] = 'stretch';
				$stats['copy_found'] = true;
			} elseif ( $is_visual ) {
				wpae_llm_design_engine_set_width( $settings, $visual_width );
				$settings['background_background'] = $background_background;
				$settings['background_color'] = $background_color;
				$settings['border_border'] = $surface === 'outlined' ? 'solid' : 'none';
				if ( $surface === 'outlined' ) {
					$settings['border_color'] = '#d1d5db';
					$settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
				}
				$settings['flex_align_items'] = $flex_alignment;
				$stats['visual_found'] = true;
			}
			if ( ( $node['elType'] ?? '' ) === 'widget' ) {
				$widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
				if ( $widget_type === 'heading' ) {
					$settings['typography_typography'] = 'custom';
					if ( $typography === 'neutral' ) {
						$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 2.75 ];
						$settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 2.25 ];
						$settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1.9 ];
					} elseif ( ( $settings['header_size'] ?? '' ) === 'h1' ) {
						$settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 3.8 ];
						$settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 3.1 ];
						$settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 2.2 ];
					}
				}
				if ( $widget_type === 'button' ) {
					$button_index++;
					$is_secondary = $cta_hierarchy === 'primary_secondary' && $button_index > 1;
					if ( $is_secondary ) {
						$settings['background_color'] = 'transparent';
						$settings['button_background_hover_color'] = '#f4e6df';
						$settings['button_text_color'] = '#a84c36';
						$settings['button_hover_text_color'] = '#a84c36';
					}
					$stats['buttons_styled']++;
				}
			}
			if ( $is_shell || $is_copy || $is_visual || $is_explicit_root || ( $node['elType'] ?? '' ) === 'widget' ) {
				$node['settings'] = $settings;
				$stats['nodes_updated']++;
			}
			if ( is_array( $node['elements'] ?? null ) ) {
				$apply( $node['elements'] );
			}
		}
		unset( $node );
	};
	$elements = is_array( $action['elements'] ?? null ) ? $action['elements'] : [];
	$apply( $elements );
	$action['elements'] = $elements;
	$action['_wpae_edde'] = [ 'schema' => WPAE_LLM_DESIGN_ENGINE_SCHEMA, 'plan' => $plan, 'compile' => $stats ];
	return $action;
}
