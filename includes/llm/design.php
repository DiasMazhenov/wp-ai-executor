<?php
/**
 * Preserve an accepted provider composition while filling native responsive defaults.
 */

defined( 'ABSPATH' ) || exit;

final class WPAE_LLM_Design {
    public static function prompt(): string {
        return ' Создай законченный дизайн по содержанию: ровно один корневой Flexbox-контейнер, '
            . 'столько вложенных контейнеров и native widgets, сколько нужно для всех частей запроса (обычно 8–30, максимум 80 элементов). '
            . 'Перед JSON продумай визуальную идею, иерархию текста, композицию, палитру и поведение на телефоне; рассуждения не выводи. '
            . 'Не своди любой блок к одинаковой сетке карточек. Для hero допустима выразительная асимметрия текста и визуальной зоны; '
            . 'для тарифов и команды — ясные повторяющиеся группы; для процесса — последовательность связанных этапов. '
            . 'Сохраняй явно заданные тексты, ссылки, цвета и стиль. Не выдумывай отзывы, клиентов, числа и достижения. '
            . 'Палитра, шрифты и визуальные предпочтения из guide служат исходными значениями; явно заданное пользователем оформление имеет приоритет над ними. '
            . 'Все тексты, кнопки, изображения и оформление должны редактироваться штатными контролами Elementor. '
            . 'Не используй html/shortcode для основной композиции. Контейнер: elType=container, settings.container_type=flex; '
            . 'виджет: elType=widget, ключ widgetType (значения heading, text-editor, button и другие native имена), settings и elements. '
            . 'У вложенных контейнеров content_width=full. Направление: flex_direction; выравнивание: flex_align_items и flex_justify_content. '
            . 'Интервал: flex_gap={unit:"rem",row:"1.5",column:"1.5",isLinked:true}; '
            . 'размер: width={unit:"%",size:48}; отступы: padding={unit:"rem",top:"2",right:"2",bottom:"2",left:"2",isLinked:true}. '
            . 'Учитывай gap в сумме ширин соседних колонок. Задай flex_direction_mobile и width_mobile для колонок, '
            . 'padding_mobile, typography_font_size_tablet/mobile. На телефоне текст и CTA должны оставаться читаемыми, колонки складываться без overflow. '
            . 'Задай native фон, контрастные цвета текста и типографику. Не используй фиксированную высоту для текста; min_height допустим для hero и визуальных зон. '
            . 'Для заданной типографики включай typography_typography=custom; для фона background_background=classic, для рамки border_border=solid. '
            . 'Никаких пустых каркасов, случайных stock-фото, гигантских пробелов и одинакового оформления всех секций.';
    }

    public static function is_complete( array $elements ): bool {
        $count = 0;
        $walk = static function ( array $nodes, int $depth ) use ( &$walk, &$count ): int {
            if ( $depth > 12 || $nodes === [] ) {
                return -1;
            }
            $widgets = 0;
            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) || ++$count > 80 ) {
                    return -1;
                }
                if ( ( $node['elType'] ?? '' ) === 'container' ) {
                    $children = $walk( is_array( $node['elements'] ?? null ) ? $node['elements'] : [], $depth + 1 );
                    if ( $children < 1 ) {
                        return -1;
                    }
                    $widgets += $children;
                    continue;
                }
                $type = $node['widgetType'] ?? '';
                if ( ( $node['elType'] ?? '' ) !== 'widget' || ! is_string( $type ) || $type === '' ) {
                    return -1;
                }
                $content_keys = [ 'heading' => 'title', 'text-editor' => 'editor', 'button' => 'text' ];
                if ( isset( $content_keys[ $type ] ) ) {
                    $content = $node['settings'][ $content_keys[ $type ] ] ?? '';
                    if ( ! is_string( $content ) || trim( wp_strip_all_tags( $content ) ) === '' ) {
                        return -1;
                    }
                }
                $widgets++;
            }
            return $widgets;
        };
        return $walk( $elements, 0 ) > 0;
    }

    public static function normalize( array $elements, int &$changed = 0 ): array {
        $normalized = wpae_elementor_normalize_data( $elements );
        $changed += array_sum( $normalized['report']['counts'] );
        return self::responsive( $normalized['data'], $changed );
    }

    private static function responsive( array $elements, int &$changed, int $depth = 0 ): array {
        foreach ( $elements as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $before = $settings;
            // Elementor group controls ignore manual values until their group is enabled.
            foreach ( $settings as $key => $value ) {
                if ( preg_match( '/^(.+typography|typography)_(?:font_size|font_family|font_weight|line_height|letter_spacing)(?:_(?:tablet|mobile|laptop|widescreen|tablet_extra|mobile_extra))?$/', (string) $key, $match ) && $value !== '' && $value !== [] ) {
                    $control = $match[1] . '_typography';
                    if ( empty( $settings[ $control ] ) ) {
                        $settings[ $control ] = 'custom';
                    }
                }
            }
            if ( ( $element['elType'] ?? '' ) === 'container' ) {
                $direction = (string) ( $settings['flex_direction'] ?? 'column' );
                $row = in_array( $direction, [ 'row', 'row-reverse' ], true );
                if ( ! isset( $settings['flex_direction_mobile'] ) || $settings['flex_direction_mobile'] === '' ) {
                    $settings['flex_direction_mobile'] = 'column';
                }
                // A desktop column width remains narrow after its parent stacks.
                $mobile_stack = in_array( $settings['flex_direction_mobile'], [ 'column', 'column-reverse' ], true );
                if ( $row && $mobile_stack ) {
                    foreach ( $element['elements'] as &$child ) {
                        if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'container' ) {
                            continue;
                        }
                        if ( empty( $child['settings']['width_mobile'] ) ) {
                            $child['settings']['width_mobile'] = [ 'unit' => '%', 'size' => 100 ];
                            $changed++;
                        }
                    }
                    unset( $child );
                }
                if ( $depth === 0 && ! isset( $settings['padding_mobile'] ) ) {
                    $settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '2', 'right' => '1.25', 'bottom' => '2', 'left' => '1.25', 'isLinked' => false ];
                }
            }
            // Fill missing overrides only. Never turn the provider's display
            // heading into the generic fallback's 2.5rem card heading.
            if ( ( $element['widgetType'] ?? '' ) === 'heading' ) {
                $size = $settings['typography_font_size'] ?? null;
                if ( is_array( $size ) && is_numeric( $size['size'] ?? null ) && in_array( $size['unit'] ?? '', [ 'rem', 'px' ], true ) ) {
                    $scale = $size['unit'] === 'px' ? 16 : 1;
                    foreach ( [ 'tablet' => 3.5, 'mobile' => 2.5 ] as $device => $cap ) {
                        $key = 'typography_font_size_' . $device;
                        if ( ! isset( $settings[ $key ] ) ) {
                            $settings[ $key ] = [ 'unit' => $size['unit'], 'size' => min( (float) $size['size'], $cap * $scale ) ];
                        }
                    }
                }
            }
            if ( $before !== $settings ) {
                $changed++;
            }
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $element['elements'] = self::responsive( $element['elements'], $changed, $depth + 1 );
            }
        }
        unset( $element );
        return $elements;
    }
}
