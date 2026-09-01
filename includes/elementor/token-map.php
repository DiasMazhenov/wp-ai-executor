<?php

defined( 'ABSPATH' ) || exit;

function wpae_token_map_control( string $value ) {
    return wpae_css_native_control_value( $value );
}

function wpae_token_map_dimension( string $value ) {
    return wpae_css_native_dimension_value( $value );
}

function wpae_token_map_record( array &$report, string $element_id, string $path, string $role, $from, $to ): void {
    $report['mapped'][] = [
        'element_id' => $element_id,
        'path' => $path,
        'role' => $role,
        'from' => $from,
        'to' => $to,
    ];
    $report['native_paths'][ $path ] = true;
    $report['evidence'][] = [ 'element_id' => $element_id, 'path' => $path, 'source' => $from, 'role' => $role ];
    $fingerprint = $from === null ? '' : ( is_scalar( $from ) ? strtolower( trim( (string) $from ) ) : hash( 'sha256', (string) wp_json_encode( $from ) ) );
    if ( $fingerprint !== '' ) {
        $report['source_roles'][ $fingerprint ][ $role ] = true;
    }
}

function wpae_token_map_unset( array &$settings, string $key, string $element_id, string $role, array &$report ): void {
    if ( ! array_key_exists( $key, $settings ) ) {
        return;
    }
    $before = $settings[ $key ];
    unset( $settings[ $key ] );
    wpae_token_map_record( $report, $element_id, 'settings.' . $key, $role, $before, null );
}

function wpae_token_map_set( array &$settings, string $key, $value, string $element_id, string $role, array &$report ): void {
    $before = $settings[ $key ] ?? null;
    if ( $before === $value ) {
        return;
    }
    $settings[ $key ] = $value;
    wpae_token_map_record( $report, $element_id, 'settings.' . $key, $role, $before, $value );
}

function wpae_token_map_typography_role( string $widget_type, array $settings ): ?string {
    if ( $widget_type === 'heading' ) {
        $heading = (string) ( $settings['header_size'] ?? 'h2' );
        if ( in_array( $heading, [ 'h5', 'h6' ], true ) ) {
            return 'utility';
        }
        return in_array( $heading, [ 'h3', 'h4' ], true ) ? 'subheading' : 'display';
    }
    if ( in_array( $widget_type, [ 'text-editor', 'testimonial', 'icon-list' ], true ) ) {
        return 'body';
    }
    if ( in_array( $widget_type, [ 'button', 'form' ], true ) ) {
        return 'utility';
    }
    return null;
}

function wpae_token_map_apply_typography( array &$settings, string $element_id, string $role, array $tokens, array &$report ): void {
    $type = (array) ( $tokens['native_tokens']['typography'][ $role ] ?? [] );
    if ( empty( $type ) ) {
        $report['unmatched'][] = [ 'element_id' => $element_id, 'group' => 'typography', 'role' => $role, 'reason' => 'Target typography token is missing.' ];
        return;
    }

    wpae_token_map_set( $settings, 'typography_typography', 'custom', $element_id, 'typography.' . $role, $report );
    if ( (string) ( $type['font_family'] ?? '' ) !== 'inherit' ) {
        wpae_token_map_set( $settings, 'typography_font_family', (string) $type['font_family'], $element_id, 'typography.' . $role, $report );
    } else {
        wpae_token_map_unset( $settings, 'typography_font_family', $element_id, 'typography.' . $role . '.font_family', $report );
    }
    foreach ( [ 'desktop' => 'typography_font_size', 'tablet' => 'typography_font_size_tablet', 'mobile' => 'typography_font_size_mobile' ] as $device => $key ) {
        $value = wpae_token_map_control( (string) ( $type[ $device ] ?? '' ) );
        if ( $value !== null ) {
            wpae_token_map_set( $settings, $key, $value, $element_id, 'typography.' . $role . '.' . $device, $report );
        }
    }
    wpae_token_map_set( $settings, 'typography_font_weight', (string) ( $type['weight'] ?? '400' ), $element_id, 'typography.' . $role . '.weight', $report );
    $line_height = wpae_token_map_control( (string) ( $type['line_height'] ?? '' ) );
    if ( $line_height !== null ) {
        wpae_token_map_set( $settings, 'typography_line_height', $line_height, $element_id, 'typography.' . $role . '.line_height', $report );
    }
}

function wpae_token_map_color_role( string $key, string $el_type, string $widget_type, int $depth ): ?string {
    if ( in_array( $key, [ 'title_color', 'heading_color' ], true ) ) {
        return 'ink';
    }
    if ( in_array( $key, [ 'text_color', 'description_color' ], true ) ) {
        return 'muted';
    }
    if ( $key === 'button_text_color' ) {
        return 'surface';
    }
    if ( $key === 'icon_color' ) {
        return 'accent';
    }
    if ( in_array( $key, [ 'link_color', 'secondary_color', 'icon_secondary_color' ], true ) ) {
        return 'support';
    }
    if ( in_array( $key, [ 'button_background_color', 'background_overlay_color' ], true ) ) {
        return $widget_type === 'button' ? 'accent' : ( $depth === 0 ? 'paper' : 'surface' );
    }
    if ( preg_match( '/(?:accent|active|hover|selected|primary)_color$/', $key ) ) {
        return 'accent';
    }
    if ( $key === 'border_color' ) {
        return 'muted';
    }
    if ( in_array( $key, [ 'background_color', 'background_color_b' ], true ) ) {
        if ( $widget_type === 'button' ) {
            return 'accent';
        }
        return $el_type === 'container' && $depth === 0 ? 'paper' : 'surface';
    }
    return null;
}

function wpae_token_map_apply_elements( array $elements, array $tokens, array &$report, int $depth = 0, bool $preserve_library_design = false ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $element_id = sanitize_key( (string) ( $element['id'] ?? 'unknown-' . $index ) );
        $el_type = (string) ( $element['elType'] ?? '' );
        $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        $is_preserved_library = $preserve_library_design || ( $depth === 0 && is_array( $classes ) && in_array( 'wpae-preserve-library-design', $classes, true ) );
        if ( $is_preserved_library ) {
            $report['protected_skips'][] = [ 'element_id' => $element_id, 'reason' => 'Trusted library design is preserved without remapping source tokens.' ];
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $element['elements'] = wpae_token_map_apply_elements( $element['elements'], $tokens, $report, $depth + 1, true );
            }
            $elements[ $index ] = $element;
            continue;
        }
        $is_generated_badge = $el_type === 'container' && is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true );
        $is_bento_grid = $el_type === 'container' && is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
        $is_generated_content_shell = $el_type === 'container' && is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true );
        $is_generated_cta_row = $el_type === 'container' && is_array( $classes ) && in_array( 'wpae-generated-cta-row', $classes, true );
        $preserve_transparent_background = $is_generated_badge || $is_bento_grid || $is_generated_content_shell || $is_generated_cta_row
            || ( $el_type === 'container' && $depth === 0 && (string) ( $settings['background_color'] ?? '' ) === 'transparent' );

        if ( $widget_type === 'html' ) {
            $report['protected_skips'][] = [ 'element_id' => $element_id, 'reason' => 'HTML enhancement zone is protected.' ];
            continue;
        }

        $type_role = wpae_token_map_typography_role( $widget_type, $settings );
        if ( $type_role !== null ) {
            wpae_token_map_apply_typography( $settings, $element_id, $type_role, $tokens, $report );
        }

        if ( $widget_type === 'button' ) {
            $palette = (array) ( $tokens['palette'] ?? [] );
            foreach ( [
                'button_background_color' => 'accent',
                'button_text_color' => 'surface',
            ] as $key => $role ) {
                $target = (string) ( $palette[ $role ] ?? '' );
                if ( $target !== '' ) {
                    wpae_token_map_set( $settings, $key, $target, $element_id, 'palette.' . $role, $report );
                }
            }
        }

        foreach ( array_keys( $settings ) as $key ) {
            if ( $preserve_transparent_background && in_array( (string) $key, [ 'background_color', 'background_color_b' ], true ) ) {
                continue;
            }
            $role = wpae_token_map_color_role( (string) $key, $el_type, $widget_type, $depth );
            if ( $role === null ) {
                if ( preg_match( '/_color(?:_[ab])?$/', (string) $key ) && is_scalar( $settings[ $key ] ) ) {
                    $report['unmatched'][] = [
                        'element_id' => $element_id,
                        'path' => 'settings.' . $key,
                        'source' => $settings[ $key ],
                        'reason' => 'No deterministic semantic role exists for this native color setting.',
                    ];
                }
                continue;
            }
            $target = (string) ( $tokens['palette'][ $role ] ?? '' );
            if ( $target === '' ) {
                $report['unmatched'][] = [ 'element_id' => $element_id, 'path' => 'settings.' . $key, 'role' => 'palette.' . $role, 'reason' => 'Target palette token is missing.' ];
                continue;
            }
            wpae_token_map_set( $settings, (string) $key, $target, $element_id, 'palette.' . $role, $report );
        }

        if ( $el_type === 'container' ) {
            $spacing = (array) ( $tokens['native_tokens']['spacing'] ?? [] );
            if ( ! $is_generated_badge && ! $is_bento_grid && ! $is_generated_content_shell ) {
                $padding = wpae_token_map_dimension( (string) ( $spacing[ $depth === 0 ? 'section_desktop' : 'component' ] ?? '' ) );
                if ( $padding !== null ) {
                    wpae_token_map_set( $settings, 'padding', $padding, $element_id, $depth === 0 ? 'spacing.section' : 'spacing.component', $report );
                }
                if ( $depth === 0 ) {
                    $mobile_padding = wpae_token_map_dimension( (string) ( $spacing['section_mobile'] ?? '' ) );
                    if ( $mobile_padding !== null ) {
                        wpae_token_map_set( $settings, 'padding_mobile', $mobile_padding, $element_id, 'spacing.section.mobile', $report );
                    }
                }
            }
            $gap = wpae_token_map_control( (string) ( $spacing['gap'] ?? '' ) );
            if ( $gap !== null ) {
                $gap_value = [
                    'column' => (string) ( $gap['size'] ?? '' ),
                    'row' => (string) ( $gap['size'] ?? '' ),
                    'isLinked' => true,
                    'unit' => (string) ( $gap['unit'] ?? 'rem' ),
                    'size' => (string) ( $gap['size'] ?? '' ),
                ];
                $gap_key = ( array_key_exists( 'flex_gap', $settings ) || array_key_exists( 'flex_direction', $settings ) || array_key_exists( 'flex_wrap', $settings ) ) ? 'flex_gap' : 'gap';
                wpae_token_map_set( $settings, $gap_key, $gap_key === 'flex_gap' ? $gap_value : $gap, $element_id, 'spacing.gap', $report );
            }
            if ( ! $is_generated_badge && ! $is_bento_grid && ! $is_generated_content_shell ) {
                $radius = wpae_token_map_dimension( (string) ( $tokens['native_tokens']['radii']['card'] ?? '' ) );
                if ( isset( $settings['border_radius'] ) && $radius !== null ) {
                    wpae_token_map_set( $settings, 'border_radius', $radius, $element_id, 'radii.card', $report );
                }
            }
        } elseif ( $widget_type === 'button' ) {
            $radius = wpae_token_map_dimension( (string) ( $tokens['native_tokens']['radii']['button'] ?? '' ) );
            if ( $radius !== null ) {
                wpae_token_map_set( $settings, 'border_radius', $radius, $element_id, 'radii.button', $report );
            }
        }

        $element['settings'] = $settings;
        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_token_map_apply_elements( $element['elements'], $tokens, $report, $depth + 1 );
        }
        $elements[ $index ] = $element;
    }
    return $elements;
}

function wpae_apply_design_token_map( array $elements ): array {
    $report = [
        'mapped' => [],
        'unmatched' => [],
        'collisions' => [],
        'evidence' => [],
        'protected_skips' => [],
        'native_paths' => [],
        'source_roles' => [],
    ];
    $elements = wpae_token_map_apply_elements( $elements, wpae_get_project_design_tokens(), $report );

    foreach ( $report['source_roles'] as $fingerprint => $roles ) {
        $roles = array_keys( $roles );
        if ( count( $roles ) > 1 ) {
            $report['collisions'][] = [ 'source_fingerprint' => $fingerprint, 'roles' => $roles ];
        }
    }
    unset( $report['source_roles'] );
    $report['native_paths'] = array_keys( $report['native_paths'] );
    $report['summary'] = [
        'mapped' => count( $report['mapped'] ),
        'unmatched' => count( $report['unmatched'] ),
        'collisions' => count( $report['collisions'] ),
        'protected_skips' => count( $report['protected_skips'] ),
    ];

    return [ 'data' => $elements, 'report' => $report ];
}
