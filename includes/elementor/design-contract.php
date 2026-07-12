<?php

defined( 'ABSPATH' ) || exit;

function wpae_collect_design_system_stats( array $elements, array &$stats, int $depth = 0 ): void {
    $tokens = wpae_get_project_design_tokens();
    $palette = array_map( 'strtolower', array_values( (array) ( $tokens['palette'] ?? [] ) ) );
    $required_classes = wpae_get_design_system_required_classes( $tokens );

    if ( ! isset( $stats['design_system_marked_top_level_containers'] ) ) {
        $stats['design_system_marked_top_level_containers'] = 0;
        $stats['mismatched_design_system_top_level_containers'] = 0;
        $stats['mismatched_design_system_classes'] = [];
        $stats['top_level_containers'] = 0;
        $stats['token_color_hits'] = 0;
        $stats['off_palette_color_count'] = 0;
        $stats['off_palette_colors'] = [];
    }

    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $el_type = (string) ( $element['elType'] ?? '' );

        if ( $depth === 0 && $el_type === 'container' ) {
            $stats['top_level_containers']++;
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $classes = is_array( $classes ) ? $classes : [];
            $has_all = true;
            foreach ( $required_classes as $required_class ) {
                if ( ! in_array( $required_class, $classes, true ) ) {
                    $has_all = false;
                    break;
                }
            }
            if ( $has_all ) {
                $stats['design_system_marked_top_level_containers']++;
            }

            foreach ( $classes as $class ) {
                if ( strpos( (string) $class, 'wpae-system-' ) === 0 && ! in_array( $class, $required_classes, true ) ) {
                    $stats['mismatched_design_system_top_level_containers']++;
                    $stats['mismatched_design_system_classes'][ (string) $class ] = true;
                }
            }
        }

        foreach ( $settings as $setting_value ) {
            if ( ! is_string( $setting_value ) ) {
                continue;
            }
            if ( preg_match_all( '/#[0-9a-f]{3,8}\b/i', $setting_value, $matches ) ) {
                foreach ( $matches[0] as $color ) {
                    $color = strtolower( $color );
                    if ( in_array( $color, $palette, true ) ) {
                        $stats['token_color_hits']++;
                    } else {
                        $stats['off_palette_colors'][ $color ] = true;
                    }
                }
            }
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_collect_design_system_stats( $element['elements'], $stats, $depth + 1 );
        }
    }

    $stats['off_palette_color_count'] = count( (array) ( $stats['off_palette_colors'] ?? [] ) );
}

function wpae_finalize_design_system_stats( array &$stats ): void {
    if ( isset( $stats['off_palette_colors'] ) && is_array( $stats['off_palette_colors'] ) ) {
        $stats['off_palette_colors'] = array_keys( $stats['off_palette_colors'] );
    }

    if ( isset( $stats['mismatched_design_system_classes'] ) && is_array( $stats['mismatched_design_system_classes'] ) ) {
        $stats['mismatched_design_system_classes'] = array_keys( $stats['mismatched_design_system_classes'] );
    }
}

function wpae_validate_design_system_contract( array $elementor_data ): array {
    $stats = [];
    wpae_collect_design_system_stats( $elementor_data, $stats );
    wpae_finalize_design_system_stats( $stats );

    $errors = [];
    $top_level = (int) ( $stats['top_level_containers'] ?? 0 );
    $marked = (int) ( $stats['design_system_marked_top_level_containers'] ?? 0 );

    if ( $top_level <= 0 ) {
        $errors[] = 'Design system contract requires at least one top-level Flexbox Container.';
    } elseif ( $marked < $top_level ) {
        $required = implode( ' ', wpae_get_design_system_required_classes() );
        $mismatched = (array) ( $stats['mismatched_design_system_classes'] ?? [] );
        if ( ! empty( $mismatched ) ) {
            $errors[] = 'This page uses old design-system marker classes (' . implode( ', ', $mismatched ) . '). Run /elementor/normalize to migrate top-level containers to the current required_root_classes before /elementor/update dry_run: ' . $required . '.';
        } else {
            $errors[] = 'Every new page/block top-level container must include design-system classes in settings._css_classes: ' . $required . '. Run /elementor/normalize to add them before /elementor/update dry_run.';
        }
    }

    if ( (int) ( $stats['token_color_hits'] ?? 0 ) <= 0 ) {
        $errors[] = 'Design system contract requires using project palette tokens in native Elementor color/background settings.';
    }

    return [
        'ok' => empty( $errors ),
        'errors' => $errors,
        'stats' => $stats,
        'design_system' => wpae_build_project_design_system(),
    ];
}
