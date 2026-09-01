<?php

defined( 'ABSPATH' ) || exit;

function wpae_append_css_classes( $existing, array $classes ): string {
    $current = preg_split( '/\s+/', trim( is_scalar( $existing ) ? (string) $existing : '' ) );
    $current = is_array( $current ) ? array_filter( $current ) : [];

    foreach ( $classes as $class ) {
        $class = sanitize_html_class( (string) $class );
        if ( $class !== '' && ! in_array( $class, $current, true ) ) {
            $current[] = $class;
        }
    }

    return trim( implode( ' ', array_values( array_unique( $current ) ) ) );
}

function wpae_migrate_design_system_css_classes( $existing, array $required_classes ): array {
    $current = preg_split( '/\s+/', trim( is_scalar( $existing ) ? (string) $existing : '' ) );
    $current = is_array( $current ) ? array_values( array_filter( $current ) ) : [];
    $removed = [];
    $kept = [];

    foreach ( $current as $class ) {
        $class = sanitize_html_class( (string) $class );
        if ( $class === '' ) {
            continue;
        }

        if ( strpos( $class, 'wpae-system-' ) === 0 && ! in_array( $class, $required_classes, true ) ) {
            $removed[] = $class;
            continue;
        }

        $kept[] = $class;
    }

    $added = [];
    foreach ( $required_classes as $required_class ) {
        $required_class = sanitize_html_class( (string) $required_class );
        if ( $required_class !== '' && ! in_array( $required_class, $kept, true ) ) {
            $kept[] = $required_class;
            $added[] = $required_class;
        }
    }

    return [
        'classes' => trim( implode( ' ', array_values( array_unique( $kept ) ) ) ),
        'added' => array_values( array_unique( $added ) ),
        'removed' => array_values( array_unique( $removed ) ),
    ];
}

function wpae_elementor_default_id( string $path, array $element ): string {
    return substr( md5( $path . '|' . wp_json_encode( $element ) ), 0, 7 );
}

function wpae_elementor_normalize_add_change( array &$report, string $type, string $path, string $message, array $details = [] ): void {
    if ( ! isset( $report['counts'][ $type ] ) ) {
        $report['counts'][ $type ] = 0;
    }

    $report['counts'][ $type ]++;

    if ( count( $report['changes'] ) >= 200 ) {
        return;
    }

    $report['changes'][] = [
        'type' => $type,
        'path' => $path,
        'message' => $message,
        'details' => $details,
    ];
}

function wpae_elementor_infer_widget_type( array $element, array $settings ): string {
    if ( ! empty( $element['widgetType'] ) ) {
        return sanitize_key( (string) $element['widgetType'] );
    }

    if ( ! empty( $element['widget_type'] ) ) {
        return sanitize_key( (string) $element['widget_type'] );
    }

    if ( isset( $settings['html'] ) ) {
        return 'html';
    }

    if ( isset( $settings['title'] ) ) {
        return 'heading';
    }

    if ( isset( $settings['text'] ) || isset( $settings['link'] ) ) {
        return 'button';
    }

    return 'text-editor';
}

function wpae_normalize_native_widget_content( array &$settings, string $widget_type, array &$report, string $element_path ): void {
    $aliases = [
        'heading' => [ 'title' => [ 'text', 'content' ] ],
        'text-editor' => [ 'editor' => [ 'text', 'content' ] ],
        'button' => [ 'text' => [ 'title', 'content' ] ],
    ];
    foreach ( (array) ( $aliases[ $widget_type ] ?? [] ) as $target => $sources ) {
        if ( trim( (string) ( $settings[ $target ] ?? '' ) ) !== '' ) {
            continue;
        }
        foreach ( $sources as $source ) {
            if ( ! is_scalar( $settings[ $source ] ?? null ) || trim( (string) $settings[ $source ] ) === '' ) {
                continue;
            }
            $settings[ $target ] = (string) $settings[ $source ];
            wpae_elementor_normalize_add_change(
                $report,
                'mapped_native_widget_content',
                $element_path,
                'Mapped a compatible content alias to the native Elementor widget setting.',
                [ 'widgetType' => $widget_type, 'from' => $source, 'to' => $target ]
            );
            break;
        }
    }
}

function wpae_normalize_known_third_party_widget( array &$element, array &$report, string $element_path ): void {
    $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
    if ( $widget_type !== 'jkit_heading' ) {
        return;
    }

    $source = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
    $title = trim( implode( ' ', array_filter( [
        (string) ( $source['sg_title_before'] ?? '' ),
        (string) ( $source['sg_title_focused'] ?? '' ),
        (string) ( $source['sg_title_after'] ?? '' ),
    ], static fn( string $part ): bool => trim( $part ) !== '' ) ) );
    if ( $title === '' ) {
        $title = trim( (string) ( $source['sg_title_text'] ?? '' ) );
    }
    if ( $title === '' ) {
        return;
    }

    $header_size = strtolower( (string) ( $source['sg_title_html_tag'] ?? 'h2' ) );
    if ( ! in_array( $header_size, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
        $header_size = 'h2';
    }

    $native = [
        'title' => preg_replace( '/\s+/u', ' ', $title ),
        'header_size' => $header_size,
    ];
    $setting_aliases = [
        'st_title_typography_content_typography_font_family' => 'typography_font_family',
        'st_title_typography_content_typography_font_size' => 'typography_font_size',
        'st_title_typography_content_typography_font_size_tablet' => 'typography_font_size_tablet',
        'st_title_typography_content_typography_font_size_mobile' => 'typography_font_size_mobile',
        'st_title_typography_content_typography_font_weight' => 'typography_font_weight',
        'st_title_typography_content_typography_line_height' => 'typography_line_height',
        'st_title_typography_content_typography_text_transform' => 'typography_text_transform',
        'st_title_typography_content_typography_typography' => 'typography_typography',
        'st_general_alignment_responsive' => 'align',
        'st_general_alignment_responsive_mobile' => 'align_mobile',
        'st_title_color_responsive' => 'title_color',
    ];
    foreach ( $setting_aliases as $source_key => $native_key ) {
        if ( array_key_exists( $source_key, $source ) && ( is_scalar( $source[ $source_key ] ) || is_array( $source[ $source_key ] ) ) ) {
            $native[ $native_key ] = is_array( $source[ $source_key ] ) ? $source[ $source_key ] : (string) $source[ $source_key ];
        }
    }
    foreach ( [ '_element_width', '_element_custom_width', '_element_custom_width_tablet', '_element_custom_width_mobile', '_margin', '_padding', '_animation', 'animation_duration', '__globals__' ] as $key ) {
        if ( array_key_exists( $key, $source ) ) {
            $native[ $key ] = $source[ $key ];
        }
    }

    $element['widgetType'] = 'heading';
    $element['settings'] = $native;
    wpae_elementor_normalize_add_change(
        $report,
        'converted_known_third_party_widget',
        $element_path,
        'Converted a known third-party heading widget to a native Elementor heading while preserving its content and key visual settings.',
        [ 'from' => $widget_type, 'to' => 'heading' ]
    );
}

function wpae_elementor_normalize_flex_settings( array &$settings, array &$report, string $element_path ): void {
    $container_type = sanitize_key( (string) ( $settings['container_type'] ?? '' ) );
    $was_grid = $container_type === 'grid';
    $grid_gaps = is_array( $settings['grid_gaps'] ?? null ) ? $settings['grid_gaps'] : [];
    $grid_gaps_mobile = is_array( $settings['grid_gaps_mobile'] ?? null ) ? $settings['grid_gaps_mobile'] : [];
    $grid_gaps_tablet = is_array( $settings['grid_gaps_tablet'] ?? null ) ? $settings['grid_gaps_tablet'] : [];

    if ( $container_type !== 'flex' ) {
        $settings['container_type'] = 'flex';
        wpae_elementor_normalize_add_change(
            $report,
            'converted_container_type',
            $element_path,
            'Converted the Elementor layout container to Flexbox.',
            [ 'from' => $container_type !== '' ? $container_type : 'unspecified', 'to' => 'flex' ]
        );
    }

    if ( $was_grid ) {
        if ( (string) ( $settings['flex_direction'] ?? '' ) === '' || (string) ( $settings['flex_direction'] ?? '' ) === 'column' ) {
            $settings['flex_direction'] = 'row';
        }
        if ( (string) ( $settings['flex_wrap'] ?? '' ) === '' || (string) ( $settings['flex_wrap'] ?? '' ) === 'nowrap' ) {
            $settings['flex_wrap'] = 'wrap';
        }
        if ( ! array_key_exists( 'flex_justify_content', $settings ) && is_scalar( $settings['grid_justify_content'] ?? null ) ) {
            $settings['flex_justify_content'] = sanitize_key( (string) $settings['grid_justify_content'] );
        }
        if ( ! array_key_exists( 'flex_align_items', $settings ) && is_scalar( $settings['grid_align_items'] ?? null ) ) {
            $settings['flex_align_items'] = sanitize_key( (string) $settings['grid_align_items'] );
        }
    }

    $map_grid_gap = static function ( array $grid_gap ): ?array {
        $unit = sanitize_key( (string) ( $grid_gap['unit'] ?? '' ) );
        $column = $grid_gap['column'] ?? null;
        $row = $grid_gap['row'] ?? null;
        if ( $unit === '' || ! is_scalar( $column ) || ! is_scalar( $row ) || ! is_numeric( $column ) || ! is_numeric( $row ) ) {
            return null;
        }
        $column = (string) $column;
        $row = (string) $row;
        return [
            'column' => $column,
            'row' => $row,
            'isLinked' => $column === $row,
            'unit' => $unit,
            'size' => $column === $row ? $column : '',
        ];
    };
    if ( $was_grid && ! empty( $grid_gaps ) ) {
        $mapped_gap = $map_grid_gap( $grid_gaps );
        if ( is_array( $mapped_gap ) ) {
            $settings['flex_gap'] = $mapped_gap;
        }
    }
    foreach ( [ 'mobile' => $grid_gaps_mobile, 'tablet' => $grid_gaps_tablet ] as $device => $grid_gap ) {
        if ( empty( $grid_gap ) ) {
            continue;
        }
        $mapped_gap = $map_grid_gap( $grid_gap );
        if ( is_array( $mapped_gap ) ) {
            $settings[ 'flex_gap_' . $device ] = $mapped_gap;
        }
    }

    $aliases = [
        'justify_content' => 'flex_justify_content',
        'align_items' => 'flex_align_items',
        'justify_content_tablet' => 'flex_justify_content_tablet',
        'justify_content_mobile' => 'flex_justify_content_mobile',
        'align_items_tablet' => 'flex_align_items_tablet',
        'align_items_mobile' => 'flex_align_items_mobile',
    ];

    foreach ( $aliases as $legacy_key => $modern_key ) {
        if ( ! array_key_exists( $modern_key, $settings ) && array_key_exists( $legacy_key, $settings ) && is_scalar( $settings[ $legacy_key ] ) ) {
            $settings[ $modern_key ] = sanitize_key( (string) $settings[ $legacy_key ] );
            wpae_elementor_normalize_add_change(
                $report,
                'migrated_flex_setting',
                $element_path,
                'Migrated a legacy container alignment setting to the current Elementor Flexbox control.',
                [ 'from' => $legacy_key, 'to' => $modern_key ]
            );
        }
    }

    foreach ( [ 'gap' => 'flex_gap', 'gap_mobile' => 'flex_gap_mobile' ] as $legacy_key => $modern_key ) {
        if ( array_key_exists( $modern_key, $settings ) || ! is_array( $settings[ $legacy_key ] ?? null ) ) {
            continue;
        }

        $unit = sanitize_key( (string) ( $settings[ $legacy_key ]['unit'] ?? '' ) );
        $size = $settings[ $legacy_key ]['size'] ?? null;
        if ( $unit === '' || ! is_scalar( $size ) || ! is_numeric( $size ) ) {
            continue;
        }

        $size = (string) $size;
        $settings[ $modern_key ] = [
            'column' => $size,
            'row' => $size,
            'isLinked' => true,
            'unit' => $unit,
            'size' => $size,
        ];
        wpae_elementor_normalize_add_change(
            $report,
            'migrated_flex_setting',
            $element_path,
            'Migrated a legacy container gap setting to the current Elementor Flexbox control.',
            [ 'from' => $legacy_key, 'to' => $modern_key ]
        );
    }

    foreach ( array_keys( $settings ) as $setting_key ) {
        if ( ! preg_match( '/^_?grid(?:_|$)/', (string) $setting_key ) ) {
            continue;
        }
        unset( $settings[ $setting_key ] );
        wpae_elementor_normalize_add_change(
            $report,
            'removed_grid_setting',
            $element_path,
            'Removed a grid-only setting after converting the container to Flexbox.',
            [ 'setting' => $setting_key ]
        );
    }
}

function wpae_elementor_normalize_elements( array $elements, array &$report, string $path = 'root' ): array {
    $normalized = [];

    foreach ( $elements as $index => $element ) {
        $element_path = $path . '.' . $index;

        if ( ! is_array( $element ) ) {
            wpae_elementor_normalize_add_change( $report, 'removed_non_object_element', $element_path, 'Removed non-object Elementor element.' );
            continue;
        }

        if ( empty( $element['id'] ) || ! is_string( $element['id'] ) ) {
            $element['id'] = wpae_elementor_default_id( $element_path, $element );
            wpae_elementor_normalize_add_change( $report, 'filled_missing_id', $element_path, 'Filled missing Elementor element id.', [ 'id' => $element['id'] ] );
        }

        $element_path = $path . '.' . $element['id'];
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
            $element['settings'] = [];
            $settings = [];
            wpae_elementor_normalize_add_change( $report, 'filled_settings', $element_path, 'Filled missing settings array.' );
        }

        $el_type = (string) ( $element['elType'] ?? '' );
        if ( $el_type === 'section' || $el_type === 'column' ) {
            $legacy_el_type = $el_type;
            $element['elType'] = 'container';
            $el_type = 'container';
            wpae_elementor_normalize_add_change( $report, 'converted_legacy_eltype', $element_path, 'Converted legacy Elementor layout node to Flexbox Container.', [ 'from' => $legacy_el_type, 'to' => 'container' ] );
        }

        if ( $el_type === '' ) {
            $el_type = ( isset( $element['widgetType'] ) || isset( $element['widget_type'] ) ) ? 'widget' : 'container';
            $element['elType'] = $el_type;
            wpae_elementor_normalize_add_change( $report, 'inferred_eltype', $element_path, 'Inferred missing elType.', [ 'elType' => $el_type ] );
        }

        if ( $el_type === 'widget' ) {
            if ( array_key_exists( 'widget_type', $element ) ) {
                if ( empty( $element['widgetType'] ) ) {
                    $element['widgetType'] = sanitize_key( (string) $element['widget_type'] );
                    wpae_elementor_normalize_add_change( $report, 'converted_widget_type_key', $element_path, 'Converted widget_type to camelCase widgetType.', [ 'widgetType' => $element['widgetType'] ] );
                }

                unset( $element['widget_type'] );
                wpae_elementor_normalize_add_change( $report, 'removed_widget_type_key', $element_path, 'Removed forbidden snake-case widget_type key.' );
            }

            if ( empty( $element['widgetType'] ) ) {
                $element['widgetType'] = wpae_elementor_infer_widget_type( $element, $settings );
                wpae_elementor_normalize_add_change( $report, 'inferred_widget_type', $element_path, 'Filled missing widgetType with best-effort native widget type.', [ 'widgetType' => $element['widgetType'] ] );
            }

            wpae_normalize_known_third_party_widget( $element, $report, $element_path );

            if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                $element['elements'] = [];
                wpae_elementor_normalize_add_change( $report, 'filled_elements', $element_path, 'Filled missing widget elements array.' );
            }
            foreach ( array_keys( $element['settings'] ) as $setting_key ) {
                if ( ! preg_match( '/^_?grid(?:_|$)/', (string) $setting_key ) ) {
                    continue;
                }
                unset( $element['settings'][ $setting_key ] );
                wpae_elementor_normalize_add_change(
                    $report,
                    'removed_grid_setting',
                    $element_path,
                    'Removed a grid-only widget placement setting for Flexbox compatibility.',
                    [ 'setting' => $setting_key ]
                );
            }
            wpae_normalize_native_widget_content( $element['settings'], sanitize_key( (string) $element['widgetType'] ), $report, $element_path );
            if ( ! empty( $element['elements'] ) ) {
                $element['elements'] = wpae_elementor_normalize_elements( $element['elements'], $report, $element_path );
            }
        } else {
            $element['elType'] = 'container';

            wpae_elementor_normalize_flex_settings( $element['settings'], $report, $element_path );

            if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                $element['elements'] = [];
                wpae_elementor_normalize_add_change( $report, 'filled_elements', $element_path, 'Filled missing container elements array.' );
            }

            if ( $path === 'root' ) {
                $required_classes = array_merge( wpae_get_design_system_required_classes(), [ 'wpae-block' ] );
                $before_classes = (string) ( $element['settings']['_css_classes'] ?? '' );
                $class_migration = wpae_migrate_design_system_css_classes( $before_classes, $required_classes );
                $element['settings']['_css_classes'] = $class_migration['classes'];
                $element['settings']['_wpae_design_system_id'] = wpae_get_design_system_id();

                if ( $element['settings']['_css_classes'] !== $before_classes ) {
                    $change_type = ! empty( $class_migration['removed'] ) ? 'migrated_design_system_marker' : 'filled_design_system_marker';
                    wpae_elementor_normalize_add_change(
                        $report,
                        $change_type,
                        $element_path,
                        ! empty( $class_migration['removed'] )
                            ? 'Migrated top-level container to the current design-system marker classes.'
                            : 'Added required design-system marker classes to top-level container.',
                        [
                            'required_classes' => $required_classes,
                            'added' => $class_migration['added'],
                            'removed' => $class_migration['removed'],
                        ]
                    );
                }
            }

            foreach ( [
                'container_type' => 'flex',
                'content_width' => 'boxed',
                'flex_direction' => 'column',
                'background_background' => 'classic',
                'background_color' => 'transparent',
            ] as $setting_key => $setting_value ) {
                if ( empty( $element['settings'][ $setting_key ] ) ) {
                    $element['settings'][ $setting_key ] = $setting_value;
                    wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container setting.', [ 'setting' => $setting_key, 'value' => $setting_value ] );
                }
            }

            if ( ! isset( $element['settings']['gap'] ) && ! isset( $element['settings']['flex_gap'] ) ) {
                $element['settings']['gap'] = [
                    'unit' => 'rem',
                    'size' => 1.5,
                    'sizes' => [],
                ];
                wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container gap.', [ 'setting' => 'gap' ] );
            }

            if ( ! isset( $element['settings']['padding'] ) ) {
                $element['settings']['padding'] = [
                    'unit' => 'rem',
                    'top' => '1.5',
                    'right' => '1.5',
                    'bottom' => '1.5',
                    'left' => '1.5',
                    'isLinked' => true,
                ];
                wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container padding.', [ 'setting' => 'padding' ] );
            }

            $element['elements'] = wpae_elementor_normalize_elements( $element['elements'], $report, $element_path );
        }

        $normalized[] = $element;
    }

    return $normalized;
}

function wpae_elementor_normalize_data( array $elementor_data ): array {
    $report = [
        'counts' => [],
        'changes' => [],
    ];

    $normalized = wpae_elementor_normalize_elements( $elementor_data, $report );
    ksort( $report['counts'] );

    return [
        'data' => $normalized,
        'report' => $report,
    ];
}
