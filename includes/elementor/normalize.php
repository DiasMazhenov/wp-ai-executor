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

            if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                $element['elements'] = [];
                wpae_elementor_normalize_add_change( $report, 'filled_elements', $element_path, 'Filled missing widget elements array.' );
            }
        } else {
            $element['elType'] = 'container';

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
                'content_width' => 'boxed',
                'flex_direction' => 'column',
                'background_background' => 'classic',
                'background_color' => '#ffffff',
            ] as $setting_key => $setting_value ) {
                if ( empty( $element['settings'][ $setting_key ] ) ) {
                    $element['settings'][ $setting_key ] = $setting_value;
                    wpae_elementor_normalize_add_change( $report, 'filled_container_setting', $element_path, 'Filled safe baseline container setting.', [ 'setting' => $setting_key, 'value' => $setting_value ] );
                }
            }

            if ( ! isset( $element['settings']['gap'] ) ) {
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

