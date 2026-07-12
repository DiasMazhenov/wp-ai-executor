<?php

defined( 'ABSPATH' ) || exit;

function wpae_validate_elementor_data_string( string $raw_data ): array {
    $data = json_decode( $raw_data, true );
    if ( ! is_array( $data ) ) {
        return [ 'Elementor _elementor_data must be valid JSON array data.' ];
    }

    $errors = [];
    wpae_validate_elementor_elements_recursive( $data, 'root', $errors, wpae_get_enforceable_skill_rules() );
    return $errors;
}

function wpae_html_has_blocking_heading_typography_override( string $html ): bool {
    return (bool) preg_match(
        '/\.elementor-heading-title[^{}]*\{[^{}]*\b(?:font-family|font-size|font-weight|font-style|line-height|letter-spacing|word-spacing|text-transform|text-decoration)\s*:[^;{}]*!important/i',
        $html
    );
}

function wpae_native_css_property_names(): array {
    return [
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'line-height',
        'letter-spacing',
        'word-spacing',
        'text-transform',
        'text-decoration',
        'color',
        'background',
        'background-color',
        'border',
        'border-color',
        'border-radius',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'min-height',
        'width',
        'max-width',
        'display',
        'flex-direction',
        'justify-content',
        'align-items',
        'gap',
        'row-gap',
        'column-gap',
        'flex-wrap',
        'z-index',
        'position',
    ];
}

function wpae_html_script_injects_native_css( string $html ): array {
    if ( $html === '' || stripos( $html, '<script' ) === false ) {
        return [];
    }

    $has_style_injection = preg_match( '/createElement\s*\(\s*[\'"]style[\'"]\s*\)/i', $html )
        || preg_match( '/insertAdjacentHTML\s*\([^)]*<style/i', $html )
        || preg_match( '/\bappend(?:Child)?\s*\([^)]*<style/i', $html );

    if ( ! $has_style_injection ) {
        return [];
    }

    // Inspect static CSS assigned to a style node, never the full JavaScript source.
    preg_match_all( '/(?:textContent|innerHTML|innerText)\s*=\s*(`(?:\\\\.|[^`])*`|\'(?:\\\\.|[^\'])*\'|"(?:\\\\.|[^"])*")/s', $html, $matches );
    $sources = (array) ( $matches[1] ?? [] );
    if ( empty( $sources ) ) {
        return [];
    }

    $blocked = [];
    foreach ( $sources as $source ) {
        $source = (string) $source;
        if ( strlen( $source ) < 2 ) {
            continue;
        }
        $css = substr( $source, 1, -1 );
        if ( $source[0] !== '`' ) {
            $css = stripcslashes( $css );
        }

        $css = preg_replace( '/\/\*.*?\*\//s', '', $css );
        $css = preg_replace( '/@import\s+[^;]+;/i', '', $css );
        $css = preg_replace( '/:root\s*\{[^{}]*\}/si', '', $css );
        $css = preg_replace( '/@(media|supports|container|keyframes)\b[^{}]*\{(?:[^{}]|\{[^{}]*\})*\}/si', '', $css );

        foreach ( wpae_native_css_property_names() as $property ) {
            $pattern = '/(?:^|[;{])\s*' . preg_quote( $property, '/' ) . '\s*:/i';
            if ( preg_match( $pattern, $css ) ) {
                $blocked[] = $property;
            }
        }
    }

    return array_values( array_unique( $blocked ) );
}

function wpae_validate_elementor_elements_recursive( array $elements, string $path, array &$errors, array $skill_rules = [] ): void {
    $allowed_widget_types = [];
    foreach ( $skill_rules as $rule ) {
        if ( ( $rule['type'] ?? '' ) === 'allow_widget_type' && ! empty( $rule['value'] ) ) {
            $allowed_widget_types[] = (string) $rule['value'];
        }
    }
    $allowed_widget_types = array_values( array_unique( $allowed_widget_types ) );

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            $errors[] = "{$path}.{$index}: element must be an object/array.";
            continue;
        }

        $element_path = $path . '.' . ( $element['id'] ?? $index );
        $el_type = $element['elType'] ?? null;

        if ( $el_type === 'section' || $el_type === 'column' ) {
            $errors[] = "{$element_path}: legacy Elementor elType={$el_type} is forbidden; use elType=container.";
        }

        if ( array_key_exists( 'widget_type', $element ) ) {
            $errors[] = "{$element_path}: widget_type is forbidden; use camelCase widgetType.";
        }

        if ( $el_type === 'widget' && empty( $element['widgetType'] ) ) {
            $errors[] = "{$element_path}: widget element must have non-empty camelCase widgetType.";
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        if ( (string) ( $element['widgetType'] ?? '' ) === 'html' ) {
            $html = (string) ( $settings['html'] ?? $settings['content'] ?? $settings['code'] ?? '' );
            if ( $html !== '' && wpae_html_has_blocking_heading_typography_override( $html ) ) {
                $errors[] = "{$element_path}: HTML widget typography !important overrides native heading controls; use /elementor/resolve-typography-overrides.";
            }
            $script_injected_native_css = wpae_html_script_injects_native_css( $html );
            if ( ! empty( $script_injected_native_css ) ) {
                $errors[] = "{$element_path}: html_widget_script_injected_css blocks Elementor editability for native properties (" . implode( ', ', array_slice( $script_injected_native_css, 0, 10 ) ) . '). Move mapped properties into native widget/container settings and keep script-injected CSS only for non-native enhancements.';
            }
        }

        foreach ( $skill_rules as $rule ) {
            $rule_type = $rule['type'] ?? '';
            $rule_value = $rule['value'] ?? '';
            $rule_target = $rule['target'] ?? '';
            $skill_id = $rule['skill_id'] ?? 'unknown';
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

            if ( $rule_type === 'forbid_elementor_eltype' && $el_type === $rule_value ) {
                $errors[] = "{$element_path}: elType={$el_type} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_key' && $el_type === 'widget' && array_key_exists( $rule_value, $element ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is forbidden by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_widget_key' && $el_type === 'widget' && empty( $element[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: widget key {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_widget_type' && $el_type === 'widget' && $widget_type === $rule_value ) {
                $errors[] = "{$element_path}: widgetType={$widget_type} is forbidden by skill {$skill_id}.";
            }

            if (
                $rule_type === 'require_widget_setting' &&
                $el_type === 'widget' &&
                ( $rule_target === '' || $rule_target === $widget_type ) &&
                empty( $settings[ $rule_value ] )
            ) {
                $errors[] = "{$element_path}: widget setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'require_container_setting' && $el_type === 'container' && empty( $settings[ $rule_value ] ) ) {
                $errors[] = "{$element_path}: container setting {$rule_value} is required by skill {$skill_id}.";
            }

            if ( $rule_type === 'forbid_html_pattern' && $el_type === 'widget' && $widget_type === 'html' ) {
                $html = (string) ( $settings['html'] ?? $settings['editor'] ?? '' );

                if ( stripos( $html, $rule_value ) !== false ) {
                    $errors[] = "{$element_path}: HTML widget content matches forbidden pattern from skill {$skill_id}.";
                }
            }
        }

        if ( $el_type === 'widget' && ! empty( $allowed_widget_types ) && ! in_array( (string) ( $element['widgetType'] ?? '' ), $allowed_widget_types, true ) ) {
            $errors[] = "{$element_path}: widgetType=" . (string) ( $element['widgetType'] ?? '' ) . ' is not in the enabled skills allowlist.';
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            wpae_validate_elementor_elements_recursive( $element['elements'], $element_path, $errors, $skill_rules );
        }
    }
}
