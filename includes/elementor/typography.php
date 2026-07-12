<?php

defined( 'ABSPATH' ) || exit;

function wpae_get_typography_unlock_keys(): array {
    return [
        'typography_typography',
        'typography_font_family',
        'typography_font_size',
        'typography_font_size_tablet',
        'typography_font_size_mobile',
        'typography_font_weight',
        'typography_text_transform',
        'typography_font_style',
        'typography_text_decoration',
        'typography_line_height',
        'typography_line_height_tablet',
        'typography_line_height_mobile',
        'typography_letter_spacing',
        'typography_letter_spacing_tablet',
        'typography_letter_spacing_mobile',
        'typography_word_spacing',
        'typography_word_spacing_tablet',
        'typography_word_spacing_mobile',
    ];
}

function wpae_clean_typography_globals( array &$settings, array $keys ): array {
    $removed = [];
    if ( empty( $settings['__globals__'] ) || ! is_array( $settings['__globals__'] ) ) {
        return $removed;
    }

    foreach ( $keys as $key ) {
        if ( array_key_exists( $key, $settings['__globals__'] ) ) {
            $removed[] = '__globals__.' . $key;
            unset( $settings['__globals__'][ $key ] );
        }
    }

    if ( empty( $settings['__globals__'] ) ) {
        unset( $settings['__globals__'] );
    }

    return $removed;
}

function wpae_elementor_typography_unlock_elements( array $elements, array $options, array &$report, string $path = 'root' ): array {
    $keys = (array) ( $options['keys'] ?? wpae_get_typography_unlock_keys() );
    $widget_types = (array) ( $options['widget_types'] ?? [ 'heading', 'text-editor', 'button', 'icon-list' ] );
    $preserve_ids = array_fill_keys( (array) ( $options['preserve_ids'] ?? [] ), true );
    $preserve_classes = (array) ( $options['preserve_classes'] ?? [] );

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_id = (string) ( $element['id'] ?? $index );
        $element_path = $path . '.' . $element_id;
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $is_target_widget = ( (string) ( $element['elType'] ?? '' ) === 'widget' && in_array( $widget_type, $widget_types, true ) );
        $is_preserved = isset( $preserve_ids[ $element_id ] );

        if ( ! $is_preserved && ! empty( $preserve_classes ) ) {
            $class_string = (string) ( $settings['_css_classes'] ?? '' );
            foreach ( $preserve_classes as $preserve_class ) {
                if ( $preserve_class !== '' && preg_match( '/(^|\s)' . preg_quote( (string) $preserve_class, '/' ) . '(\s|$)/', $class_string ) ) {
                    $is_preserved = true;
                    break;
                }
            }
        }

        if ( $is_target_widget && ! $is_preserved ) {
            $removed_keys = [];
            foreach ( $keys as $key ) {
                if ( array_key_exists( $key, $settings ) ) {
                    unset( $settings[ $key ] );
                    $removed_keys[] = $key;
                }
            }
            $removed_keys = array_merge( $removed_keys, wpae_clean_typography_globals( $settings, $keys ) );

            if ( ! empty( $removed_keys ) ) {
                $report['changed'] = true;
                $report['widgets_changed']++;
                foreach ( $removed_keys as $removed_key ) {
                    $report['removed_settings'][ $removed_key ] = ( $report['removed_settings'][ $removed_key ] ?? 0 ) + 1;
                }
                if ( count( $report['changes'] ) < 200 ) {
                    $report['changes'][] = [
                        'path' => $element_path,
                        'id' => $element_id,
                        'widgetType' => $widget_type,
                        'removed' => $removed_keys,
                    ];
                }
            }
        }

        $element['settings'] = $settings;
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_elementor_typography_unlock_elements( $element['elements'], $options, $report, $element_path );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_typography_unlock( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
    }

    $preserve_ids = $request->get_param( 'preserve_ids' );
    $preserve_classes = $request->get_param( 'preserve_classes' );
    $widget_types = $request->get_param( 'widget_types' );
    $mode = sanitize_key( (string) ( $request->get_param( 'mode' ) ?: 'typography' ) );
    $keys = $mode === 'font_family'
        ? [ 'typography_font_family' ]
        : wpae_get_typography_unlock_keys();

    $report = [
        'changed' => false,
        'widgets_changed' => 0,
        'removed_settings' => [],
        'changes' => [],
        'mode' => $mode,
    ];
    $updated_data = wpae_elementor_typography_unlock_elements( $elementor_data, [
        'keys' => $keys,
        'preserve_ids' => is_array( $preserve_ids ) ? array_map( 'sanitize_key', $preserve_ids ) : [],
        'preserve_classes' => is_array( $preserve_classes ) ? array_map( 'sanitize_html_class', $preserve_classes ) : [],
        'widget_types' => is_array( $widget_types ) ? array_map( 'sanitize_key', $widget_types ) : [ 'heading', 'text-editor', 'button', 'icon-list' ],
    ], $report );

    $validation_errors = wpae_validate_elementor_data_array( $updated_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Typography unlock produced invalid Elementor data.',
            'details' => [ 'errors' => $validation_errors ],
            'report' => $report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'report' => $report,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_typography_unlock:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $updated_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'report' => $report,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'report' => $report,
        'quality_summary' => wpae_build_after_save_quality_summary( $post_id, $updated_data, [] ),
    ], 200 );
}

function wpae_sanitize_typography_override_patches( $raw_patches ): array {
    if ( ! is_array( $raw_patches ) ) {
        return [];
    }

    $allowed_keys = [
        'typography_typography',
        'typography_font_family',
        'typography_font_weight',
        'typography_font_style',
        'typography_text_transform',
        'typography_text_decoration',
        'typography_letter_spacing',
        'typography_word_spacing',
        'typography_line_height',
        'typography_font_size',
        'typography_font_size_tablet',
        'typography_font_size_mobile',
    ];
    $patches = [];

    foreach ( $raw_patches as $element_id => $settings ) {
        $element_id = sanitize_key( (string) $element_id );
        if ( $element_id === '' || ! is_array( $settings ) ) {
            continue;
        }

        foreach ( $settings as $key => $value ) {
            if ( ! in_array( $key, $allowed_keys, true ) || ! is_scalar( $value ) ) {
                continue;
            }

            $patches[ $element_id ][ $key ] = sanitize_text_field( (string) $value );
        }
    }

    return $patches;
}

function wpae_relax_heading_typography_important( string $content, array &$report ): string {
    $result = preg_replace_callback( '/([^{}]+)\{([^{}]*)\}/s', function ( array $match ) use ( &$report ): string {
        $selector = $match[1];
        if ( stripos( $selector, '.elementor-heading-title' ) === false ) {
            return $match[0];
        }

        $changed = false;
        $declarations = preg_replace_callback(
            '/\b(font-family|font-size|font-weight|font-style|line-height|letter-spacing|word-spacing|text-transform|text-decoration)\s*:\s*([^;{}]*?)\s*!important(\s*;?)/i',
            function ( array $declaration ) use ( &$report, &$changed ): string {
                $changed = true;
                $report['html_typography_important_removed']++;
                return $declaration[1] . ': ' . trim( $declaration[2] ) . $declaration[3];
            },
            $match[2]
        );

        if ( $changed ) {
            $report['html_rules_relaxed']++;
        }

        return $selector . '{' . $declarations . '}';
    }, $content );

    return is_string( $result ) ? $result : $content;
}

function wpae_resolve_heading_typography_overrides( array $elements, array $patches, array &$report, string $path = 'root' ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $element_id = sanitize_key( (string) ( $element['id'] ?? $index ) );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );

        if ( $widget_type === 'heading' && isset( $patches[ $element_id ] ) ) {
            $changed_keys = [];
            foreach ( $patches[ $element_id ] as $key => $value ) {
                if ( ! array_key_exists( $key, $settings ) || $settings[ $key ] !== $value ) {
                    $settings[ $key ] = $value;
                    $changed_keys[] = $key;
                }
            }

            if ( isset( $settings['typography_font_family'] ) && empty( $settings['typography_typography'] ) ) {
                $settings['typography_typography'] = 'custom';
                $changed_keys[] = 'typography_typography';
            }

            $report['patched_ids'][ $element_id ] = true;
            if ( ! empty( $changed_keys ) ) {
                $report['native_widgets_changed']++;
                $report['native_changes'][] = [
                    'path' => $path . '.' . $element_id,
                    'id' => $element_id,
                    'changed' => $changed_keys,
                ];
            }
        }

        if ( $widget_type === 'html' ) {
            foreach ( [ 'html', 'content', 'code' ] as $content_key ) {
                if ( ! is_string( $settings[ $content_key ] ?? null ) || $settings[ $content_key ] === '' ) {
                    continue;
                }

                $before = $settings[ $content_key ];
                $settings[ $content_key ] = wpae_relax_heading_typography_important( $before, $report );
                if ( $settings[ $content_key ] !== $before ) {
                    $report['html_widgets_changed']++;
                    $report['html_widget_ids'][] = $element_id;
                }
                break;
            }
        }

        $element['settings'] = $settings;
        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = wpae_resolve_heading_typography_overrides( $element['elements'], $patches, $report, $path . '.' . $element_id );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_elementor_resolve_typography_overrides( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $dry_run = (bool) $request->get_param( 'dry_run' );
    $template = sanitize_key( (string) ( $request->get_param( 'template' ) ?: 'elementor_canvas' ) );
    $patches = wpae_sanitize_typography_override_patches( $request->get_param( 'native_typography_patches' ) );

    if ( $post_id <= 0 || get_post( $post_id ) === null ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'A valid post_id is required.' ], 400 );
    }

    $raw_data = (string) get_post_meta( $post_id, '_elementor_data', true );
    $elementor_data = json_decode( $raw_data, true );
    if ( ! is_array( $elementor_data ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => '_elementor_data is not valid JSON array data.', 'post_id' => $post_id ], 422 );
    }

    $report = [
        'native_widgets_changed' => 0,
        'native_changes' => [],
        'patched_ids' => [],
        'html_widgets_changed' => 0,
        'html_widget_ids' => [],
        'html_rules_relaxed' => 0,
        'html_typography_important_removed' => 0,
    ];
    $updated_data = wpae_resolve_heading_typography_overrides( $elementor_data, $patches, $report );
    $report['patched_ids'] = array_values( array_keys( $report['patched_ids'] ) );
    $report['html_widget_ids'] = array_values( array_unique( $report['html_widget_ids'] ) );
    $report['missing_patch_ids'] = array_values( array_diff( array_keys( $patches ), $report['patched_ids'] ) );
    $report['changed'] = $report['native_widgets_changed'] > 0 || $report['html_widgets_changed'] > 0;

    $validation_errors = wpae_validate_elementor_data_array( $updated_data );
    if ( ! empty( $validation_errors ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => 'Typography override migration produced invalid Elementor data.',
            'details' => [ 'errors' => $validation_errors ],
            'report' => $report,
        ], 422 );
    }

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'ok' => true,
            'dry_run' => true,
            'post_id' => $post_id,
            'report' => $report,
        ], 200 );
    }

    $rollback_snapshot = wpae_create_rollback_snapshot( 'elementor_resolve_typography_overrides:' . $post_id, [ $post_id ] );
    $saved = wpae_save_elementor_page_data( $post_id, $updated_data, $template );
    if ( is_wp_error( $saved ) ) {
        return new WP_REST_Response( [
            'ok' => false,
            'error' => $saved->get_error_message(),
            'details' => $saved->get_error_data(),
            'report' => $report,
        ], $saved->get_error_code() === 'wpae_invalid_elementor_data' ? 422 : 400 );
    }

    return new WP_REST_Response( [
        'ok' => true,
        'post_id' => $post_id,
        'url' => get_permalink( $post_id ),
        'rollback_snapshot_id' => $rollback_snapshot['id'] ?? null,
        'rollback_expires_at' => $rollback_snapshot['expires_at'] ?? null,
        'report' => $report,
        'quality_summary' => wpae_build_after_save_quality_summary( $post_id, $updated_data, [] ),
    ], 200 );
}

