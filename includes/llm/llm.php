<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/transport.php';

function wpae_llm_is_instruction_only_brief( string $message ): bool {
	$message = trim( $message );
	if ( $message === '' || preg_match( '/[«"\x{201c}\x{201d}:;]|\r?\n/u', $message ) ) {
		return false;
	}

	return (bool) preg_match( '/^\s*(?:сделай|создай|добавь|собери|сверстай|сформируй|покажи|нарисуй)\b\s+.+$/iu', $message );
}

function wpae_llm_request_intent_head( string $message ): string {
	$parts = preg_split( '/\r?\n+|:|(?<=[.!?])\s+|(?=\s+[—-]\s+)/u', trim( $message ), 2, PREG_SPLIT_NO_EMPTY );
	return trim( (string) ( $parts[0] ?? '' ) );
}

function wpae_llm_is_process_request( string $message, string $archetype = '' ): bool {
	$intent_head = wpae_llm_request_intent_head( $message );
	if ( $intent_head === '' ) {
		return $archetype === 'process';
	}

	return (bool) preg_match( '/\b(процесс\w*|этап\w*|шаг\w*|таймлайн\w*|process|steps?|timeline)\b/iu', $intent_head );
}

function wpae_llm_content_units( string $message ): array {
	if ( wpae_llm_is_instruction_only_brief( $message ) ) {
		return [];
	}
	$segments = preg_split( '/(?:\r?\n+|(?<=[.!?])\s+)/u', trim( $message ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    $units = [];
    foreach ( $segments as $segment ) {
        $unit = trim( preg_replace( '/^[\s]+|[\s.!?]+$/u', '', sanitize_text_field( (string) $segment ) ) );
        if ( strlen( $unit ) >= 8 ) {
            $units[] = $unit;
        }
    }
    return $units;
}

function wpae_llm_is_cta_copy( string $value ): bool {
    $value = trim( sanitize_text_field( $value ) );
    return (bool) preg_match( '/^(?:(?:кнопка|cta|button)\s*[:\-]\s*)?(?:обсудить|обсудите|получить|получите|узнать|узнайте|заказать|закажите|оформить|оформите|купить|купите|начать|начните|выбрать|выберите|написать|напишите|связаться|свяжитесь|оставить\s+заявк\w*|оставьте\s+заявк\w*|смотреть|смотрите|записаться|запишитесь|забронировать|забронируйте|регистрац\w*)\b/iu', $value );
}

function wpae_llm_is_content_composition_request( string $message ): bool {
    $message = trim( $message );
    if ( strlen( $message ) < 40 || preg_match( '/[?؟]\s*$/u', $message ) ) {
        return false;
    }

    if ( count( wpae_llm_extract_labeled_content( $message ) ) >= 2 ) {
        return true;
    }

    $sentences = wpae_llm_content_units( $message );
    $has_cta = false;
    foreach ( $sentences as $sentence ) {
        if ( wpae_llm_is_cta_copy( $sentence ) ) {
            $has_cta = true;
            break;
        }
    }
    $has_content_signal = (bool) preg_match( '/[«»"]|₸|\$|€|₽|\b(дизайн|сайт|страниц|проект|бизнес|клиент|услуг|продукт|команд|запуск|результат|коллекц\w*|доставк\w*|специалист\w*|заказ\w*|выбор\w*|помощ\w*|стоим\w*|тариф\w*|отзыв\w*|этап\w*|шаг\w*)\b/iu', $message );

    // Content-only briefs can be neutral copy without domain keywords or labels.
    $has_brief_shape = count( $sentences ) >= 3 && strlen( implode( ' ', $sentences ) ) >= 80;
    return count( $sentences ) >= 3 && ( $has_cta || $has_content_signal || $has_brief_shape );
}

function wpae_llm_is_action_request( string $message ): bool {
    // Long content-only briefs may start with an interrogative word without
    // being a question, for example "Что получают наши клиенты.".
    if (
        preg_match( '/^\s*(как|что|почему|зачем|объясни|подскажи)\b/ui', $message )
        && ( preg_match( '/[?؟]\s*$/u', $message ) || strlen( trim( $message ) ) < 80 )
    ) {
        return false;
    }
    return (bool) preg_match( '/\b(сделай|создай|добавь|собери|сверстай|измени|поменяй|исправь|поставь|замени|скругл\w*|закругл\w*|округл\w*|радиус\w*|верст|hero|хиро|лендинг)\b/ui', $message ) || wpae_llm_is_content_composition_request( $message );
}

function wpae_llm_is_targeted_edit_request( string $message ): bool {
    if ( ! preg_match( '/\b(измени|поменяй|поставь|сделай|увеличь|уменьши|замени|настрой|улучши|обнови|оформи|перестрой|скругл\w*|закругл\w*|округл\w*)\b/iu', $message ) ) {
        return false;
    }

    $property_signal = (bool) preg_match( '/\b(шрифт|типограф|размер|кегл|цвет|фон|отступ|padding|margin|радиус\w*|скругл\w*|угл\w*|высот|ширин|выравнив|интервал|текст|заголов|кнопк|иконк)/iu', $message );
    $selection_signal = (bool) preg_match( '/\b(этот|эту|этого|выбран\w*|выделен\w*|текущ\w*|внутри|содержим|дочерн\w*)/iu', $message );
    $insert_signal = (bool) preg_match( '/\b(добавь|создай|собери|вставь|новый|новую|новое)\b/iu', $message );
    if ( $insert_signal ) {
        return false;
    }
	return $selection_signal || $property_signal;
}

function wpae_llm_build_vision_feedback_prompt( string $original_brief, string $findings, bool $regenerate = true ): string {
	$original_brief = trim( sanitize_textarea_field( substr( $original_brief, 0, 4000 ) ) );
	$findings = trim( sanitize_textarea_field( substr( $findings, 0, 3600 ) ) );
	if ( $findings === '' ) {
		return '';
	}

	$scope = $regenerate
		? 'полностью пересобери один полноценный блок'
		: 'исправь только выбранное дерево';
	return "Дополнительный промт контроля качества от AI Vision. Это визуальный feedback к исходному заданию, а не новый пользовательский запрос и не контент для публикации. Исходное задание должно остаться главным: «{$original_brief}». Отчет Vision: {$findings}. {$scope}, сохрани смысл исходного задания, исправь каждое критическое и существенное замечание, проверь композицию, иерархию, отступы, переполнение, контраст и соответствие запрошенному типу блока. Не вставляй текст отчета Vision в дизайн и не расширяй область изменения.";
}

function wpae_llm_is_border_radius_request( string $message ): bool {
    return (bool) preg_match( '/\b(скругл\w*|закругл\w*|округл\w*|радиус\w*|угл\w*)\b/iu', $message );
}

function wpae_llm_border_radius_patch_value( string $message ): array {
    $remove_radius = (bool) preg_match( '/\b(убери|сними|убрать|снять|прям\w*|без)\b.*\b(скругл\w*|радиус\w*|угл\w*)\b/iu', $message );
    $size = $remove_radius ? 0 : 1;

    return [
        'unit' => 'rem',
        'top' => (string) $size,
        'right' => (string) $size,
        'bottom' => (string) $size,
        'left' => (string) $size,
        'size' => $size,
        'isLinked' => true,
    ];
}

function wpae_llm_ensure_targeted_border_radius_patch( array $action, string $message, int $post_id, array $selected_elements ): array {
    if ( ! wpae_llm_is_border_radius_request( $message ) ) {
        return $action;
    }

    $selected_ids = [];
    foreach ( array_slice( $selected_elements, 0, 8 ) as $element ) {
        $id = is_array( $element ) ? sanitize_key( (string) ( $element['id'] ?? $element['element_id'] ?? '' ) ) : sanitize_key( (string) $element );
        if ( $id !== '' ) {
            $selected_ids[ $id ] = true;
        }
    }
    $selected_ids = array_keys( $selected_ids );
    if ( empty( $selected_ids ) ) {
        return $action;
    }

    $selected_id_map = array_fill_keys( $selected_ids, true );
    $radius_patches = [];
    $other_patches = [];
    foreach ( is_array( $action['patches'] ?? null ) ? $action['patches'] : [] as $patch ) {
        if ( ! is_array( $patch ) ) {
            continue;
        }
        $patch_id = sanitize_key( (string) ( $patch['element_id'] ?? $patch['id'] ?? '' ) );
        $patch_path = (string) ( $patch['path'] ?? '' );
        if ( isset( $selected_id_map[ $patch_id ] ) && ( $patch_path === 'settings.border_radius' || strpos( $patch_path, 'settings.border_radius.' ) === 0 ) ) {
            continue;
        }
        $other_patches[] = $patch;
    }
    foreach ( $selected_ids as $selected_id ) {
        $radius_patches[ $selected_id ] = [
            'element_id' => $selected_id,
            'path' => 'settings.border_radius',
            'op' => 'set',
            'value' => wpae_llm_border_radius_patch_value( $message ),
        ];
    }

    $radius_patches = array_values( $radius_patches );
    $action['action'] = 'patch_elements';
    $action['post_id'] = $post_id;
    $action['patches'] = array_merge( array_slice( $other_patches, 0, max( 0, 12 - count( $radius_patches ) ) ), $radius_patches );
    $action['_wpae_diagnostics'] = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
    $action['_wpae_diagnostics']['deterministic_border_radius_patch'] = true;
    return $action;
}

function wpae_llm_sanitize_editor_context_value( $value, int $depth = 0 ) {
    if ( $depth >= 8 ) {
        return '[truncated]';
    }
    if ( is_array( $value ) ) {
        $result = [];
        $count = 0;
        foreach ( $value as $key => $item ) {
            if ( ++$count > 80 ) {
                break;
            }
            $normalized_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
            if ( ! is_int( $key ) && $normalized_key === '' ) {
                continue;
            }
            if ( ! is_int( $key ) && in_array( strtolower( $normalized_key ), [ 'html', 'custom_css', 'code', 'script', 'api_key', 'token', 'nonce', 'password' ], true ) ) {
                continue;
            }
            $result[ $normalized_key ] = wpae_llm_sanitize_editor_context_value( $item, $depth + 1 );
        }
        return $result;
    }
    if ( is_string( $value ) ) {
        return sanitize_textarea_field( substr( $value, 0, 1600 ) );
    }
    if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || $value === null ) {
        return $value;
    }
    return sanitize_text_field( substr( (string) $value, 0, 1600 ) );
}

function wpae_llm_sanitize_selected_element_snapshot( array $element, int $depth = 0 ): array {
    $snapshot = [
        'id' => sanitize_key( substr( (string) ( $element['id'] ?? '' ), 0, 64 ) ),
        'elType' => sanitize_key( substr( (string) ( $element['elType'] ?? '' ), 0, 32 ) ),
        'widgetType' => sanitize_key( substr( (string) ( $element['widgetType'] ?? '' ), 0, 64 ) ),
        'settings' => wpae_llm_sanitize_editor_context_value( is_array( $element['settings'] ?? null ) ? $element['settings'] : [], $depth + 1 ),
        'elements' => [],
    ];
    if ( isset( $element['visible_text'] ) ) {
        $visible_text = sanitize_textarea_field( substr( (string) $element['visible_text'], 0, 240 ) );
        if ( $visible_text !== '' ) {
            $snapshot['visible_text'] = $visible_text;
        }
    }
    if ( $depth >= 8 || ! is_array( $element['elements'] ?? null ) ) {
        return $snapshot;
    }
    foreach ( array_slice( $element['elements'], 0, 40 ) as $child ) {
        if ( is_array( $child ) ) {
            $snapshot['elements'][] = wpae_llm_sanitize_selected_element_snapshot( $child, $depth + 1 );
        }
    }
    return $snapshot;
}

function wpae_llm_collect_selected_scope_ids( array $elements, array $selected_ids ): array {
    $roots = array_fill_keys( array_values( array_filter( array_map( 'sanitize_key', $selected_ids ) ) ), true );
    $allowed = [];
    $walk = static function ( array $nodes, bool $inside_scope = false ) use ( &$walk, &$allowed, $roots ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $id = sanitize_key( (string) ( $node['id'] ?? '' ) );
            $in_scope = $inside_scope || ( $id !== '' && isset( $roots[ $id ] ) );
            if ( $in_scope && $id !== '' ) {
                $allowed[ $id ] = true;
            }
            if ( is_array( $node['elements'] ?? null ) ) {
                $walk( $node['elements'], $in_scope );
            }
        }
    };
    $walk( $elements );
    return array_keys( $allowed );
}

function wpae_llm_is_list( array $value ): bool {
    $index = 0;
    foreach ( array_keys( $value ) as $key ) {
        if ( $key !== $index++ ) {
            return false;
        }
    }
    return true;
}

function wpae_llm_count_widgets( array $elements ): int {
    $count = 0;
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( (string) ( $element['elType'] ?? '' ) === 'widget' ) {
            $count++;
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            $count += wpae_llm_count_widgets( $element['elements'] );
        }
    }
    return $count;
}

function wpae_llm_new_operation_id(): string {
    return 'wpae-' . gmdate( 'YmdHis' ) . '-' . substr( wp_generate_uuid4(), 0, 8 );
}

function wpae_llm_validate_action_shape( array $action, int $post_id ): array {
    $elements = $action['elements'] ?? null;
    $errors = [];
    if ( (string) ( $action['action'] ?? '' ) !== 'insert_elements' ) {
        $errors[] = 'action must be insert_elements.';
    }
    if ( absint( $action['post_id'] ?? 0 ) !== $post_id ) {
        $errors[] = 'post_id must match the current Elementor page.';
    }
    if ( ! is_array( $elements ) || count( $elements ) !== 1 ) {
        $errors[] = 'elements must contain exactly one root container.';
    } elseif ( (string) ( $elements[0]['elType'] ?? '' ) !== 'container' ) {
        $errors[] = 'the root element must be an Elementor container.';
    } elseif ( empty( $elements[0]['elements'] ) || ! is_array( $elements[0]['elements'] ) ) {
        $errors[] = 'the root container must contain native widgets.';
    }
    return [ 'ok' => empty( $errors ), 'errors' => $errors ];
}

function wpae_llm_action_diff( array $before, array $inserted, array $after ): array {
    $ids = [];
    $top_level_ids = static function ( array $elements ): array {
        $ids = [];
        foreach ( $elements as $element ) {
            if ( is_array( $element ) && ! empty( $element['id'] ) ) {
                $ids[] = sanitize_key( (string) $element['id'] );
            }
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    };
    foreach ( $inserted as $element ) {
        if ( is_array( $element ) && ! empty( $element['id'] ) ) {
            $ids[] = sanitize_key( (string) $element['id'] );
        }
    }
    return [
        'before_top_level_count' => count( $before ),
        'inserted_top_level_count' => count( $inserted ),
        'after_top_level_count' => count( $after ),
        'before_top_level_ids' => $top_level_ids( $before ),
        'after_top_level_ids' => $top_level_ids( $after ),
        'inserted_ids' => array_values( array_unique( $ids ) ),
        'changed' => count( $after ) !== count( $before ),
    ];
}

function wpae_llm_execute_patch_action( array $action, int $post_id, array $selected_ids = [] ): array {
    $operation_id = wpae_llm_new_operation_id();
    $patches = is_array( $action['patches'] ?? null ) ? array_slice( $action['patches'], 0, 12 ) : [];
    $selected_ids = array_values( array_filter( array_map( 'sanitize_key', $selected_ids ) ) );
    $patch_ids = array_values( array_filter( array_map( static fn( $patch ) => is_array( $patch ) ? sanitize_key( (string) ( $patch['element_id'] ?? $patch['id'] ?? '' ) ) : '', $patches ) ) );
    $existing = wpae_get_elementor_data_for_post( $post_id );
    if ( is_wp_error( $existing ) ) {
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Не удалось прочитать текущую структуру Elementor для точечной правки.', 'status' => 422, 'details' => [ 'error' => $existing->get_error_message() ] ];
    }
    $scope_ids = wpae_llm_collect_selected_scope_ids( $existing, $selected_ids );
    $out_of_scope_ids = array_values( array_diff( $patch_ids, $scope_ids ) );
    if ( (string) ( $action['action'] ?? '' ) !== 'patch_elements' || absint( $action['post_id'] ?? 0 ) !== $post_id || empty( $patches ) || empty( $selected_ids ) || empty( $scope_ids ) || count( $patch_ids ) !== count( $patches ) || ! empty( $out_of_scope_ids ) ) {
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Patch-команда не соответствует текущему Elementor элементу или странице.' ];
    }
    $request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/patch' );
    $request->set_param( 'post_id', $post_id );
    $request->set_param( 'patches', $patches );
    $request->set_param( 'dry_run', true );
    $preview = wpae_elementor_patch( $request );
    $preview_data = $preview instanceof WP_REST_Response ? $preview->get_data() : [];
    $preview_status = $preview instanceof WP_REST_Response ? $preview->get_status() : 500;
    $steps = [ [ 'id' => 'preview', 'status' => $preview_status >= 200 && $preview_status < 300 && ! empty( $preview_data['ok'] ) ? 'ok' : 'failed', 'message' => 'Patch preview и preflight проверены до записи.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $preview_status, 'patch_count' => count( $patches ), 'selected_scope_count' => count( $scope_ids ) ] ] ];
    if ( $preview_status < 200 || $preview_status >= 300 || empty( $preview_data['ok'] ) ) {
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Patch preview отклонён до записи.', 'status' => $preview_status, 'details' => $preview_data, 'steps' => $steps ];
    }
    $request->set_param( 'dry_run', false );
    $result = wpae_elementor_patch( $request );
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $status = $result instanceof WP_REST_Response ? $result->get_status() : 500;
    if ( $status < 200 || $status >= 300 || empty( $data['ok'] ) ) {
        $steps[] = [ 'id' => 'elementor_patch', 'status' => 'failed', 'message' => 'Patch не сохранён и был остановлен проверками.', 'details' => [ 'http_status' => $status, 'error' => $data['error'] ?? '', 'selected_scope_count' => count( $scope_ids ) ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor patch отклонён проверкой.', 'status' => $status, 'details' => $data, 'steps' => $steps ];
    }
    $changes = (array) ( $data['patch_report']['changes'] ?? [] );
    $changed_ids = array_values( array_unique( array_map( static fn( $item ) => sanitize_key( (string) ( $item['element_id'] ?? '' ) ), $changes ) ) );
    $steps[] = [ 'id' => 'elementor_patch', 'status' => 'ok', 'message' => 'Точечные native-свойства изменены через Elementor patch.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'changes' => $changes, 'changed_ids' => $changed_ids, 'selected_scope_count' => count( $scope_ids ) ] ];
    return [
        'ok' => true,
        'operation_id' => $operation_id,
        'action' => 'patch_elements',
        'post_id' => $post_id,
        'changed_count' => count( $changes ),
        'patch_report' => $data['patch_report'] ?? [],
        'rollback_snapshot_id' => $data['rollback_snapshot_id'] ?? null,
        'rollback_expires_at' => $data['rollback_expires_at'] ?? null,
        'editor_sync' => [
            'mode' => 'patch',
            'patches' => array_values( $patches ),
            'changed_ids' => $changed_ids,
            'target_element_ids' => $changed_ids,
            'selected_scope_ids' => array_values( array_unique( $scope_ids ) ),
            'selected_scope_count' => count( $scope_ids ),
        ],
        'steps' => $steps,
    ];
}

// Single registry keeps classification and post-write semantic checks in sync.
function wpae_llm_content_archetype_catalog(): array {
    return [
        'mega_menu' => [
            'headline' => '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu',
            'body' => '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu',
            'semantic' => '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu',
        ],
        'carousel' => [
            'headline' => '/\b(карусел\w*|слайдер\w*|carousel|slider|логотип\w*)\b/iu',
            'body' => '/\b(карусел\w*|слайдер\w*|carousel|slider|логотип\w*|партн[её]р\w*)\b/iu',
            'semantic' => '/\b(карусел\w*|слайдер\w*|carousel|slider|логотип\w*)\b/iu',
        ],
        'hero' => [
            'headline' => '/\b(первый\s+экран|hero|хиро|обложк\w*|главн\w*|home)\b/iu',
            'body' => '/\b(первый\s+экран|hero|хиро|обложк\w*|главн\w*|home)\b/iu',
            'semantic' => '/\b(первый\s+экран|hero|хиро|обложк\w*)\b/iu',
        ],
        'faq' => [
            'headline' => '/\b(faq|вопрос\w*|ответ\w*|аккордеон)\b/iu',
            'body' => '/\b(faq|аккордеон)\b|\?/u',
            'semantic' => '/\b(faq|частые\s+вопрос\w*|вопрос\w*\s+и\s+ответ\w*|аккордеон)\b/iu',
        ],
        'pricing' => [
            'headline' => '/\b(тариф\w*|цен\w*|стоимост\w*|пакет\w*|формат\w*|вариант\w*|pricing)\b/iu',
            'body' => '/₸|\$|€|₽|\b(тариф\w*|цен\w*|стоимост\w*|пакет\w*|формат\w*|вариант\w*|pricing)\b/iu',
            'semantic' => '/\b(тариф\w*|цен\w*|стоимост\w*|пакет\w*|формат\w*|вариант\w*|pricing)\b/iu',
        ],
        'testimonials' => [
            'headline' => '/\b(отзыв\w*|рекомендац\w*|testimonial\w*)\b/iu',
            'body' => '/\b(отзыв\w*|рекомендац\w*|testimonial\w*)\b|[«"]/u',
            'semantic' => '/\b(отзыв\w*|рекомендац\w*|testimonial\w*|мнение\w*\s+клиент\w*)\b/iu',
        ],
        'benefits' => [
            'headline' => '/\b(преимуществ\w*|выгод\w*|features?|benefits?)\b/iu',
            'body' => '/\b(преимуществ\w*|выгод\w*|features?|benefits?)\b/iu',
            'semantic' => '/\b(преимуществ\w*|выгод\w*|features?|benefits?|почему\s+мы)\b/iu',
        ],
		'process' => [
			'headline' => '/\b(процесс\w*|этап\w*|шаг\w*|таймлайн\w*|process|steps?|timeline)\b/iu',
			'body' => '/\b(процесс\w*|этап\w*|шаг\w*|таймлайн\w*|process|steps?|timeline|сначала|затем|проверяем|запускаем|переда[её]м)\b/iu',
			'semantic' => '/\b(процесс\w*|этап\w*|шаг\w*|таймлайн\w*|process|steps?|timeline)\b/iu',
        ],
        'about' => [
            'headline' => '/\b(о\s+компани\w*|о\s+нас|кто\s+мы|about)\b/iu',
            'body' => '/\b(о\s+компани\w*|о\s+нас|кто\s+мы|about)\b/iu',
            'semantic' => '/\b(о\s+компани\w*|о\s+нас|кто\s+мы|about)\b/iu',
        ],
        'team' => [
            'headline' => '/^\s*(?:наша\s+)?команд\w*\b/iu',
            'body' => '/\b(наша\s+команд\w*|сотрудник\w*|специалист\w*\s*,?\s+котор\w*|коллег\w*)\b/iu',
            'semantic' => '/\b(наша\s+команд\w*|сотрудник\w*|специалист\w*\s*,?\s+котор\w*|коллег\w*)\b/iu',
        ],
        'portfolio' => [
            'headline' => '/\b(портфолио|кейс\w*|работ\w*|portfolio|project)\b/iu',
            'body' => '/\b(портфолио|кейс\w*|работ\w*|portfolio|project)\b/iu',
            'semantic' => '/\b(портфолио|кейс\w*|наши\s+проект\w*|portfolio|case\s+stud(?:y|ies))\b/iu',
        ],
        'cta' => [
            'headline' => '/\b(cta|заявк\w*|контакт\w*|призыв)\b/iu',
            'body' => '/\b(cta|заявк\w*|связ\w*|контакт\w*|обсудить|призыв)\b/iu',
            'semantic' => '',
        ],
    ];
}

function wpae_llm_content_archetype_markers(): array {
    $markers = [];
    foreach ( wpae_llm_content_archetype_catalog() as $archetype => $definition ) {
        if ( ! empty( $definition['semantic'] ) ) {
            $markers[ $archetype ] = $definition['semantic'];
        }
    }
    return $markers;
}

function wpae_llm_content_archetype_scores( string $message, array $labeled_pairs = [] ): array {
    $message = trim( $message );
    $headline = trim( (string) ( preg_split( '/\r?\n+|(?<=[.!?])\s+/u', $message, -1, PREG_SPLIT_NO_EMPTY )[0] ?? '' ) );
    $signal_text = wpae_llm_normalize_content_text( $message );
    foreach ( $labeled_pairs as $pair ) {
        $label = wpae_llm_normalize_content_text( (string) ( $pair['label'] ?? '' ) );
        if ( $label !== '' ) {
            $signal_text = trim( (string) preg_replace( '/' . preg_quote( $label, '/' ) . '/iu', ' ', $signal_text ) );
        }
    }
    $headline = wpae_llm_normalize_content_text( $headline );
    $catalog = wpae_llm_content_archetype_catalog();
    $scores = array_fill_keys( array_keys( $catalog ), 0 );
    foreach ( $catalog as $archetype => $definition ) {
        if ( preg_match( $definition['headline'], $headline ) ) {
            $scores[ $archetype ] += 100;
        }
        if ( preg_match( $definition['body'], $signal_text ) ) {
            $scores[ $archetype ] += 20;
        }
    }
    return $scores;
}

function wpae_llm_detect_block_archetype( string $message ): string {
    $labeled_pairs = wpae_llm_extract_labeled_content( $message );
    $scores = wpae_llm_content_archetype_scores( $message, $labeled_pairs );
    if ( ! wpae_llm_is_process_request( $message ) ) {
        $scores['process'] = 0;
    }
    $scored_archetype = '';
    $scored_value = 0;
    foreach ( $scores as $archetype => $score ) {
        if ( $score > $scored_value ) {
            $scored_archetype = $archetype;
            $scored_value = $score;
        }
    }
    if ( $scored_archetype !== '' && $scored_value > 0 ) {
        return $scored_archetype;
    }
    if ( wpae_llm_is_content_composition_request( $message ) ) {
        $normalized = wpae_llm_normalize_content_text( $message );
        $labeled_text = wpae_llm_normalize_content_text( implode( ' ', array_map( static fn( $pair ): string => (string) ( $pair['label'] ?? '' ), $labeled_pairs ) ) );
        if ( preg_match( '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu', $message ) ) {
            return 'mega_menu';
        }
        if ( preg_match( '/\b(карусел\w*|слайдер\w*|carousel|slider|логотип\w*)\b/iu', $message ) ) {
            return 'carousel';
        }
        if ( preg_match( '/₸|\$|€|₽|\b(цена|стоимост|тариф|пакет|от\s+\d+)/iu', $message ) ) {
            return 'pricing';
        }
        if ( preg_match( '/(?:^|[.!?\n]\s*)(?:наша\s+)?команд\w*\b/iu', trim( $message ) ) ) {
            return 'team';
        }
        if ( preg_match( '/\b(первый экран|hero|хиро|обложк|главн\w*|home)\b|школ\w*\s+публичн\w*\s+выступлен\w*/iu', $message ) ) {
            return 'hero';
        }
        if (
            preg_match( '/\b(запишитесь|записаться|оставьте\s+заявк\w*|начните|попробуйте|забронируйте|забронировать)\b/iu', $message )
            && preg_match( '/\b(школ\w*|курс\w*|обучен\w*|заняти\w*|практик\w*|тренер\w*|презентац\w*|выступлен\w*|руководител\w*|топ[- ]менеджер\w*)\b/iu', $normalized )
        ) {
            return 'hero';
        }
        if (
            count( $labeled_pairs ) < 2
            && preg_match( '/\b(запишитесь|записаться|оставьте\s+заявк\w*|начните|попробуйте|забронируйте|забронировать)\b/iu', $message )
            && preg_match( '/\b(для\s+\p{L}+|школ\w*|курс\w*|обучен\w*|заняти\w*|практик\w*|тренер\w*|результат\w*)\b/iu', $message )
        ) {
            return 'hero';
        }
        if ( preg_match( '/\bпартн[её]р\w*\b/iu', $message ) ) {
            return 'carousel';
        }
        if ( preg_match( '/\?|؟/u', $message ) ) {
            return 'faq';
        }
        if ( preg_match( '/\b(отзыв\w*|рекомендац\w*|понравил\w*|получил\w*)\b/iu', $normalized ) ) {
            return 'testimonials';
        }
        if ( preg_match( '/[«"]/u', $message ) ) {
            return 'testimonials';
        }
        if ( preg_match( '/(?:^|[.!?\n]\s*)(?:преимуществ\w*|выгод\w*|почему\s+мы|features?|benefits?)\b/iu', trim( $message ) ) ) {
            return 'benefits';
        }
		if ( wpae_llm_is_process_request( $message ) ) {
            return 'process';
        }
        if (
            preg_match( '/\b(о\s+компани\w*|о\s+нас|кто\s+мы|about|о\s+(?:нашей\s+)?студи\w*)\b/iu', $normalized )
            || ( preg_match( '/\bстуди\w*\b/iu', $normalized ) && preg_match( '/\b(услуг\w*|компани\w*|простым\s+язык\w*|довер\w*|прозрач\w*)\b/iu', $normalized ) )
        ) {
            return 'about';
        }
        if (
            preg_match( '/\b(?:наша|о\s+нашей|про\s+нашу|в\s+нашей)\s+команд\w*\b/iu', $normalized )
            || preg_match( '/\bкоманд\w*\s*:/iu', $normalized )
            || ( count( $labeled_pairs ) >= 2 && preg_match( '/\b(сотрудник\w*|специалист\w*|коллег\w*)\b/iu', $normalized ) )
        ) {
            return 'team';
        }
        if ( preg_match( '/\b(курс\w*|обучен\w*|заняти\w*|программ\w*)\b/iu', $normalized ) ) {
            return 'benefits';
        }
        if ( preg_match( '/\b(блог\w*|стать\w*|article\w*)\b/iu', $normalized ) ) {
            return 'portfolio';
        }
        if ( count( $labeled_pairs ) >= 2 && preg_match( '/\b(стратег\w*|редактируем\w*|мобильн\w*|преимуществ\w*|выгод\w*|опор\w*|понятн\w*|удобн\w*)\b/iu', $labeled_text ) ) {
            return 'benefits';
        }
        if ( count( $labeled_pairs ) >= 3 && preg_match( '/(?:кейс\w*|портфолио|проект\w*|бренд\w*|сайт\w*|сервис\w*|редизайн\w*|продукт\w*|магазин\w*)/iu', $labeled_text . ' ' . $normalized ) ) {
            return 'portfolio';
        }
        if ( preg_match( '/\b(быстрый старт|понятная структура|поддержка после запуска|преимуществ|выгод)\b/iu', $normalized ) ) {
            return 'benefits';
        }
        if ( preg_match( '/(?:кейс\w*|портфолио|работ\w*|рост\w*|вырос\w*|увелич\w*|сократ\w*|результат\w*|проект\w*)/iu', $normalized ) ) {
            return 'portfolio';
        }
        if ( preg_match( '/(?:заявк\w*|связ\w*|контакт\w*|обсудить|призыв\w*)/iu', $normalized ) ) {
            return 'cta';
        }
        return 'benefits';
    }

    $patterns = [
        'mega_menu' => '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu',
        'carousel' => '/\b(карусел\w*|слайдер\w*|carousel|slider|логотип\w*)\b/iu',
        'hero' => '/\b(hero|хиро|первый экран|обложк|главн\w*|home)\b|школ\w*\s+публичн\w*\s+выступлен\w*/iu',
        'benefits' => '/\b(преимуществ|benefit|features?|выгод|почему мы)/iu',
        'pricing' => '/\b(тариф|цен|пакет|pricing|стоимост)/iu',
        'about' => '/\b(о\s+компани\w*|о\s+нас|кто\s+мы|about|о\s+(?:нашей\s+)?студи\w*)/iu',
        'team' => '/\b(команд\w*|сотрудник\w*|специалист\w*|коллег\w*)/iu',
        'testimonials' => '/\b(отзыв|testimonial|клиентск|рекомендац)/iu',
        'faq' => '/\b(faq|вопрос|ответ|аккордеон)/iu',
        'process' => '/\b(процесс|этап|шаг|событи\w*|мероприяти\w*|event\w*|process|steps?)/iu',
        'cta' => '/\b(cta|заявк|связ|контакт|призыв)/iu',
        'portfolio' => '/\b(портфолио|кейс|работ|portfolio|project)/iu',
    ];
    foreach ( $patterns as $archetype => $pattern ) {
        if ( preg_match( $pattern, $message ) ) {
            return $archetype;
        }
    }
    return 'custom';
}

function wpae_llm_block_archetype_hint( string $message ): string {
    $archetype = wpae_llm_detect_block_archetype( $message );
    $labels = [
        'mega_menu' => [ 'мега меню/навигация', 'image, mega-menu и button в сохраненной grid-композиции' ],
        'carousel' => [ 'карусель/логотипы', 'image-carousel с реальными изображениями и responsive-настройками' ],
        'hero' => [ 'hero/первый экран', 'heading, text-editor, button и image при необходимости' ],
        'benefits' => [ 'преимущества/features', 'heading, icon-list и text-editor или button' ],
        'pricing' => [ 'тарифы/pricing', 'heading, price-list или заполненные native heading/text-editor/button' ],
        'team' => [ 'команда/team', 'heading, image, icon и повторяющиеся карточки' ],
        'about' => [ 'о компании/about', 'heading, image, icon-list и counter при необходимости' ],
        'testimonials' => [ 'отзывы/testimonials', 'heading, quote/proof widgets и повторяющиеся native items' ],
        'faq' => [ 'FAQ', 'heading и accordion с заполненными вопросами и ответами' ],
		'process' => [ 'процесс/этапы/таймлайн', 'heading, numbered Flex cards, icon-list или text-editor и divider' ],
        'cta' => [ 'CTA/контакт', 'heading, text-editor и button' ],
        'portfolio' => [ 'портфолио/кейсы', 'heading, image и text-editor или button' ],
    ];
    if ( isset( $labels[ $archetype ] ) ) {
        return ' Сначала классифицируй запрос как блок «' . $labels[ $archetype ][0] . '» и собери соответствующую композицию. Предпочтительные native widgets: ' . $labels[ $archetype ][1] . '. Не повторяй hero/benefits-шаблон, если запрос относится к другому типу.';
    }
    return ' Сначала определи тип блока по смыслу запроса и выбери подходящие native widgets из доступных Elementor. Не своди каждый блок к одному и тому же hero/benefits-шаблону; содержание и композиция должны соответствовать задаче пользователя.';
}

function wpae_llm_content_plan( string $message, string $archetype = '' ): array {
    $archetype = $archetype !== '' ? sanitize_key( $archetype ) : wpae_llm_detect_block_archetype( $message );
    $units = array_values( array_filter( array_map( static fn( $unit ): string => trim( sanitize_text_field( (string) $unit ) ), wpae_llm_content_units( $message ) ) ) );
    $pairs = array_slice( array_map( static function ( $pair ): array {
        return [
            'label' => trim( sanitize_text_field( (string) ( $pair['label'] ?? '' ) ) ),
            'content' => trim( sanitize_text_field( (string) ( $pair['content'] ?? '' ) ) ),
        ];
    }, wpae_llm_extract_labeled_content( $message ) ), 0, 8 );
    $content_pairs = $pairs;
    $archetype_scores = wpae_llm_content_archetype_scores( $message, $pairs );
    if ( $archetype === 'faq' && count( $content_pairs ) < 2 ) {
        $content_pairs = array_slice( array_map( static function ( $pair ): array {
            return [
                'label' => trim( sanitize_text_field( (string) ( $pair['label'] ?? '' ) ) ),
                'content' => trim( sanitize_text_field( (string) ( $pair['content'] ?? '' ) ) ),
            ];
        }, wpae_llm_extract_faq_content( $message ) ), 0, 8 );
    }
    $ctas = [];
    foreach ( array_merge( $units, array_column( $pairs, 'content' ) ) as $value ) {
        if ( wpae_llm_is_cta_copy( (string) $value ) ) {
            $ctas[] = wpae_llm_compact_cta_text( (string) $value );
        }
    }
    $ctas = array_values( array_unique( array_filter( $ctas ) ) );
    $allowed_widgets = [
        'hero' => [ 'heading', 'text-editor', 'button', 'image' ],
        'benefits' => [ 'heading', 'text-editor', 'icon-list', 'icon' ],
        'pricing' => [ 'heading', 'text-editor', 'price-list', 'button' ],
        'testimonials' => [ 'heading', 'text-editor', 'image', 'testimonial' ],
        'team' => [ 'heading', 'text-editor', 'image', 'icon' ],
        'about' => [ 'heading', 'text-editor', 'image', 'icon-list', 'counter' ],
        'faq' => [ 'heading', 'accordion', 'text-editor' ],
        'process' => [ 'heading', 'text-editor', 'icon-list', 'divider' ],
        'portfolio' => [ 'heading', 'text-editor', 'image', 'button' ],
        'carousel' => [ 'heading', 'text-editor', 'image-carousel' ],
        'mega_menu' => [ 'image', 'mega-menu', 'button' ],
        'cta' => [ 'heading', 'text-editor', 'button' ],
    ];
    return [
        'schema' => 'wpae-content-plan-v1',
        'archetype' => $archetype,
        'archetype_scores' => $archetype_scores,
        'title' => (string) ( $units[0] ?? '' ),
        'content_units' => array_slice( $units, 0, 8 ),
        'labeled_pairs' => $pairs,
        'content_pairs' => $content_pairs,
        'repeatable_units' => count( $content_pairs ) >= 2 ? count( $content_pairs ) : max( 0, count( $units ) - 1 ),
        'explicit_cta' => $ctas,
        'cta_required' => ! empty( $ctas ),
        'requires_media' => (bool) preg_match( '/\b(фото|изображен\w*|портрет\w*|картин\w*|медиа|image|photo|portrait|background)\b/iu', $message ),
        'allowed_widgets' => $allowed_widgets[ $archetype ] ?? [ 'heading', 'text-editor', 'image', 'button' ],
        'forbidden_widgets' => [ 'icon-box' ],
    ];
}

function wpae_llm_content_plan_audit( array $plan, array $elements ): array {
    $counts = [];
    $button_texts = [];
    $repeatable_container_count = 0;
    $accordion_item_count = 0;
    $repeatable_archetypes = [ 'benefits', 'pricing', 'testimonials', 'team', 'process', 'portfolio' ];
    $walk = static function ( array $nodes ) use ( &$walk, &$counts, &$button_texts ): void {
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( ( $element['elType'] ?? '' ) === 'widget' ) {
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                $counts[ $widget_type ] = (int) ( $counts[ $widget_type ] ?? 0 ) + 1;
                if ( $widget_type === 'button' ) {
                    $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                    $button_texts[] = trim( sanitize_text_field( (string) ( $settings['text'] ?? '' ) ) );
                }
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
    };
    $walk( $elements );
    $count_repeatable_containers = static function ( array $nodes, int $depth = 0 ) use ( &$count_repeatable_containers, &$repeatable_container_count, &$accordion_item_count ): void {
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
            if ( ( $element['elType'] ?? '' ) === 'container' && $depth > 0 ) {
                $child_containers = array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' );
                $meaningful_widgets = 0;
                foreach ( $children as $child ) {
                    if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' ) {
                        continue;
                    }
                    $widget_type = sanitize_key( (string) ( $child['widgetType'] ?? '' ) );
                    $settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
                    $has_copy = false;
                    foreach ( [ 'title', 'title_text', 'editor', 'text', 'testimonial_name', 'testimonial_content', 'description_text' ] as $key ) {
                        if ( trim( wp_strip_all_tags( (string) ( $settings[ $key ] ?? '' ) ) ) !== '' ) {
                            $has_copy = true;
                            break;
                        }
                    }
                    if ( $has_copy || in_array( $widget_type, [ 'image', 'image-box', 'image-carousel', 'icon', 'icon-list', 'price-list', 'testimonial', 'accordion' ], true ) ) {
                        $meaningful_widgets++;
                    }
                    if ( $widget_type === 'accordion' ) {
                        $accordion_item_count += count( (array) ( $settings['tabs'] ?? [] ) );
                    }
                }
                if ( empty( $child_containers ) && $meaningful_widgets > 0 ) {
                    $repeatable_container_count++;
                }
            }
            if ( ! empty( $children ) ) {
                $count_repeatable_containers( $children, $depth + 1 );
            }
        }
    };
    $count_repeatable_containers( $elements );
    $forbidden_found = array_values( array_filter( (array) ( $plan['forbidden_widgets'] ?? [] ), static fn( $type ): bool => ! empty( $counts[ sanitize_key( (string) $type ) ] ) ) );
    $required_media = ! empty( $plan['requires_media'] );
    $media_count = (int) ( $counts['image'] ?? 0 ) + (int) ( $counts['image-carousel'] ?? 0 );
    $explicit_ctas = array_values( array_filter( array_map( 'strval', (array) ( $plan['explicit_cta'] ?? [] ) ) ) );
    $matching_cta_count = 0;
    foreach ( $button_texts as $button_text ) {
        $normalized_button = wpae_llm_normalize_content_text( $button_text );
        foreach ( $explicit_ctas as $explicit_cta ) {
            $normalized_cta = wpae_llm_normalize_content_text( $explicit_cta );
            if ( $normalized_cta !== '' && ( $normalized_button === $normalized_cta || strpos( $normalized_button, $normalized_cta ) !== false || strpos( $normalized_cta, $normalized_button ) !== false ) ) {
                $matching_cta_count++;
                break;
            }
        }
    }
    $cta_required = ! empty( $plan['cta_required'] );
    $cta_failure = $cta_required && $matching_cta_count < 1;
    $unrequested_button_count = ! $cta_required ? count( array_filter( $button_texts ) ) : 0;
    $failures = $forbidden_found;
    if ( $required_media && $media_count < 1 ) {
        $failures[] = 'requested media is missing';
    }
    if ( $cta_failure ) {
        $failures[] = 'explicit CTA is missing from a native button';
    }
    if ( $unrequested_button_count > 0 ) {
        $failures[] = 'an unrequested CTA/button was added';
    }
    $archetype = sanitize_key( (string) ( $plan['archetype'] ?? '' ) );
    $repeatable_units = absint( $plan['repeatable_units'] ?? 0 );
    if ( in_array( $archetype, $repeatable_archetypes, true ) && $repeatable_units >= 2 ) {
        $required_repeatable_count = min( 8, max( 2, $repeatable_units ) );
        if ( $repeatable_container_count < $required_repeatable_count ) {
            $failures[] = 'repeatable content units are not separated into distinct containers';
        }
    }
    if ( $archetype === 'faq' && $repeatable_units >= 2 ) {
        $required_faq_count = min( 8, max( 2, $repeatable_units ) );
        if ( max( $repeatable_container_count, $accordion_item_count ) < $required_faq_count ) {
            $failures[] = 'FAQ questions and answers are not separated into distinct units';
        }
    }
    $semantic_conflicts = [];
    $plan_copy = wpae_llm_normalize_content_text( implode( ' ', array_merge( (array) ( $plan['content_units'] ?? [] ), array_map( static fn( $pair ): string => implode( ' ', (array) $pair ), (array) ( $plan['content_pairs'] ?? [] ) ) ) ) );
    $tree_copy = wpae_llm_normalize_content_text( wpae_llm_collect_action_content( $elements ) );
    // Instruction-only briefs authorize the selected scaffold; audit explicit copy only.
    $semantic_scope = $plan_copy !== '' ? 'explicit_content' : 'scaffold_only';
    if ( $semantic_scope === 'explicit_content' ) {
        foreach ( wpae_llm_content_archetype_markers() as $candidate_archetype => $marker ) {
            $plan_has_marker = (bool) preg_match( $marker, $plan_copy );
            if ( ! $plan_has_marker && preg_match( $marker, $tree_copy ) && $candidate_archetype !== $archetype ) {
                $semantic_conflicts[] = 'unrequested ' . $candidate_archetype . ' semantics appear in ' . $archetype . ' composition';
                $failures[] = $archetype . ' composition contains unrelated ' . $candidate_archetype . ' semantics';
            }
        }
    }
    return [
        'ok' => empty( $failures ),
        'archetype' => (string) ( $plan['archetype'] ?? '' ),
        'widget_counts' => $counts,
        'forbidden_widgets' => $forbidden_found,
        'media_required' => $required_media,
        'media_count' => $media_count,
        'cta_required' => $cta_required,
        'explicit_cta_count' => count( (array) ( $plan['explicit_cta'] ?? [] ) ),
        'button_count' => count( $button_texts ),
        'matching_cta_count' => $matching_cta_count,
        'unrequested_button_count' => $unrequested_button_count,
        'repeatable_container_count' => $repeatable_container_count,
        'accordion_item_count' => $accordion_item_count,
        'repeatable_units' => $repeatable_units,
        'semantic_scope' => $semantic_scope,
        'semantic_conflicts' => $semantic_conflicts,
        'failures' => array_values( array_unique( $failures ) ),
    ];
}

function wpae_llm_template_fingerprint( array $elements ): array {
    $stats = [
        'root_count' => 0,
        'container_count' => 0,
        'flex_container_count' => 0,
        'widget_count' => 0,
        'legacy_count' => 0,
        'max_depth' => 0,
        'widget_types' => [],
        'directions' => [],
        'has_heading' => false,
        'has_image' => false,
        'has_badge' => false,
        'has_bento_grid' => false,
    ];
    $walk = static function ( array $nodes, int $depth = 0 ) use ( &$walk, &$stats ): void {
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $element_type = (string) ( $element['elType'] ?? '' );
            $stats['max_depth'] = max( $stats['max_depth'], $depth );
            if ( in_array( $element_type, [ 'section', 'column' ], true ) ) {
                $stats['legacy_count']++;
            }
            if ( $element_type === 'container' ) {
                $stats['container_count']++;
                if ( sanitize_key( (string) ( $element['settings']['container_type'] ?? '' ) ) === 'flex' ) {
                    $stats['flex_container_count']++;
                }
                $direction = sanitize_key( (string) ( $element['settings']['flex_direction'] ?? '' ) );
                if ( $direction !== '' ) {
                    $stats['directions'][ $direction ] = (int) ( $stats['directions'][ $direction ] ?? 0 ) + 1;
                }
                $classes = preg_split( '/\s+/', trim( (string) ( $element['settings']['_css_classes'] ?? '' ) ) );
                if ( is_array( $classes ) ) {
                    $stats['has_badge'] = $stats['has_badge'] || in_array( 'wpae-generated-badge', $classes, true ) || in_array( 'wpae-generated-badge-label', $classes, true );
                    $stats['has_bento_grid'] = $stats['has_bento_grid'] || in_array( 'wpae-bento-grid', $classes, true );
                }
            } elseif ( $element_type === 'widget' ) {
                $stats['widget_count']++;
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                $stats['widget_types'][ $widget_type ] = (int) ( $stats['widget_types'][ $widget_type ] ?? 0 ) + 1;
                $stats['has_heading'] = $stats['has_heading'] || $widget_type === 'heading';
                $stats['has_image'] = $stats['has_image'] || in_array( $widget_type, [ 'image', 'image-box', 'image-carousel' ], true );
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'], $depth + 1 );
            }
        }
    };
    $stats['root_count'] = count( array_filter( $elements, static fn( $element ): bool => is_array( $element ) && ( $element['elType'] ?? '' ) === 'container' ) );
    $walk( $elements );
    ksort( $stats['widget_types'] );
    ksort( $stats['directions'] );
    $stats['schema'] = 'wpae-template-fingerprint-v1';
    $stats['signature'] = implode( '|', [
        $stats['root_count'],
        $stats['container_count'],
        $stats['widget_count'],
        $stats['legacy_count'],
        $stats['max_depth'],
        $stats['has_heading'] ? 'heading' : 'no-heading',
        $stats['has_image'] ? 'media' : 'no-media',
        $stats['has_bento_grid'] ? 'bento' : 'single',
    ] );
    return $stats;
}

function wpae_llm_compare_template_fingerprint( array $expected, array $actual, string $archetype = '' ): array {
    $failures = [];
    if ( (int) ( $actual['root_count'] ?? 0 ) !== 1 ) {
        $failures[] = 'result must contain exactly one root container';
    }
    if ( (int) ( $actual['legacy_count'] ?? 0 ) > 0 ) {
        $failures[] = 'legacy section/column nodes remain';
    }
    if ( (int) ( $actual['flex_container_count'] ?? 0 ) < 1 ) {
        $failures[] = 'result has no native Flexbox container';
    }
    if ( ! empty( $expected['has_heading'] ) && empty( $actual['has_heading'] ) ) {
        $failures[] = 'source heading structure was lost';
    }
    if ( ! empty( $expected['has_image'] ) && empty( $actual['has_image'] ) ) {
        $failures[] = 'source media structure was lost';
    }
    if ( ! empty( $expected['has_bento_grid'] ) && empty( $actual['has_bento_grid'] ) ) {
        $failures[] = 'source repeatable grid structure was lost';
    }
    if ( empty( $actual['has_badge'] ) ) {
        $failures[] = 'required generated badge is missing';
    }
    if ( $archetype === 'team' && ! empty( $actual['widget_types']['icon-box'] ) ) {
        $failures[] = 'team result contains forbidden icon-box widgets';
    }
    return [
        'ok' => empty( $failures ),
        'schema' => 'wpae-template-fidelity-v1',
        'archetype' => $archetype,
        'failures' => $failures,
        'expected_signature' => (string) ( $expected['signature'] ?? '' ),
        'actual_signature' => (string) ( $actual['signature'] ?? '' ),
    ];
}

function wpae_llm_normalize_content_text( $value ): string {
    $value = wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    $value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
    return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

function wpae_llm_extract_requested_content( string $message ): array {
    $matches = [];
    $navigation_request = false;
    foreach ( [ '/«([^»]{2,240})»/u', '/"([^"\\n]{2,240})"/u' ] as $pattern ) {
        if ( preg_match_all( $pattern, $message, $found ) ) {
            $matches = array_merge( $matches, $found[1] );
        }
    }
    $labeled_pairs = wpae_llm_extract_labeled_content( $message );
    $structured_pairs = $labeled_pairs;
    if ( preg_match( '/\b(мега[\s-]*меню|mega[\s-]*menu|навигац\w*|шапк\w*|header)\b/iu', $message ) ) {
        $navigation_request = true;
        $navigation = wpae_llm_extract_navigation_content( $message );
        foreach ( (array) ( $navigation['items'] ?? [] ) as $item ) {
            $matches[] = $item;
        }
        if ( ! empty( $navigation['cta'] ) ) {
            $matches[] = $navigation['cta'];
        }
    }
    if ( count( $structured_pairs ) < 2 && preg_match( '/\?|؟/u', $message ) ) {
        $structured_pairs = wpae_llm_extract_faq_content( $message );
    }
    foreach ( $structured_pairs as $pair ) {
        $matches[] = $pair['label'];
        $matches[] = $pair['content'];
    }
    if ( ! empty( $structured_pairs ) ) {
        foreach ( wpae_llm_content_units( $message ) as $unit ) {
            if ( wpae_llm_is_cta_copy( $unit ) ) {
                $matches[] = function_exists( 'wpae_llm_compact_cta_text' ) ? wpae_llm_compact_cta_text( $unit ) : $unit;
            }
        }
    }
    if ( empty( $matches ) && ! $navigation_request ) {
        $matches = wpae_llm_content_units( $message );
    }
    $content = [];
    foreach ( $matches as $value ) {
        $value = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $value ) ) );
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
        if ( $value !== '' && $length >= 3 ) {
            $content[ wpae_llm_normalize_content_text( $value ) ] = $value;
        }
    }
    return array_values( $content );
}

function wpae_llm_extract_navigation_content( string $message ): array {
    $raw_message = trim( $message );
    $has_explicit_list = (bool) preg_match( '/^\s*(?:добавь|добавить|создай|создать|сделай|сформируй)\b[^:]{0,160}:\s*/iu', $raw_message );
    $message = trim( (string) preg_replace( '/^\s*(?:добавь|добавить|создай|создать|сделай|сформируй)\b[^:]{0,160}:\s*/iu', '', $raw_message ) );
    $cta = '';
    if ( preg_match( '/(?:кнопка|cta|button)\s*:\s*([^.;\n]+)/iu', $message, $match ) ) {
        $cta = trim( sanitize_text_field( (string) $match[1] ) );
        $message = trim( (string) preg_replace( '/(?:кнопка|cta|button)\s*:\s*[^.;\n]+/iu', '', $message ) );
    }

    $items = [];
    foreach ( preg_split( '/(?:\r?\n+|[,;|]+)/u', $message, -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $segment ) {
        $item = trim( sanitize_text_field( (string) $segment ), " \t\n\r\0\x0B.,:;|\"«»" );
        if ( $item === '' || preg_match( '/^(?:добавь|добавить|создай|создать|сделай|сформируй|меню|навигац\w*)\b/iu', $item ) || ( ! $has_explicit_list && preg_match( '/^(?:мега[\s-]*меню|mega[\s-]*menu)\b/iu', $item ) ) ) {
            continue;
        }
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $item ) : strlen( $item );
        if ( $length >= 2 && $length <= 60 ) {
            $items[ wpae_llm_normalize_content_text( $item ) ] = $item;
        }
    }

    return [ 'items' => array_slice( array_values( $items ), 0, 8 ), 'cta' => $cta ];
}

function wpae_llm_extract_pricing_content( string $message ): array {
    if ( ! preg_match( '/₸|\$|€|₽|\b(тариф|стоимост|пакет|pricing)\b/iu', $message ) ) {
        return [];
    }
    $pairs = [];
    // Content-only briefs often put all tiers into one paragraph; parse each
    // label/amount boundary before the generic sentence splitter sees it.
    $pricing_pattern = '/(?:^|[.!?;\n]\s*|:\s+)([^—–:\n.;]{2,80}?)\s*[—–-]\s*(\d[\d\s]*(?:₸|\$|€|₽)?)(?:\s*[,;:]\s*([^.!?\n;]+?))?(?=(?:[.!?;]\s+|$)[^—–:\n.;]{2,80}?\s*[—–-]\s*\d|[.!?;]\s*$|$)/u';
    if ( preg_match_all( $pricing_pattern, trim( $message ), $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $label = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
            $price = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
            $description = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
            if ( $label === '' || $price === '' ) {
                continue;
            }
            $pairs[] = [
                'label' => $label,
                'content' => $price . ( $description !== '' ? ', ' . $description : '' ),
            ];
        }
    }

    // Keep line-based support for legacy prompts with a bare amount and no
    // punctuation after the label.
    if ( count( $pairs ) < 2 ) {
        foreach ( preg_split( '/\r?\n+/u', trim( $message ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $line ) {
            if ( ! preg_match( '/^\s*([^—–:\n]{2,80}?)\s*[—–-]\s*(\d[\d\s]*(?:₸|\$|€|₽)?[^.\n]*)(?:\.\s*(.*?))?\s*$/u', trim( (string) $line ), $match ) ) {
                continue;
            }
            $label = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
            $price = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
            $description = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
            if ( $label === '' || $price === '' ) {
                continue;
            }
            $pairs[] = [
                'label' => $label,
                'content' => $price . ( $description !== '' ? '. ' . $description : '' ),
            ];
        }
    }
    return array_slice( $pairs, 0, 8 );
}

function wpae_llm_extract_labeled_content( string $message ): array {
    $pricing_pairs = wpae_llm_extract_pricing_content( $message );
    if ( count( $pricing_pairs ) >= 2 ) {
        return $pricing_pairs;
    }
    $content_message = preg_replace( '/^\s*(?:добавь|добавить|создай|создать|сделай|сформируй)\b[^:]{0,160}:\s*/iu', '', trim( $message ) );
    if ( ! is_string( $content_message ) || $content_message === '' ) {
        $content_message = $message;
    }
    $dash_separator = '(?:\s*[—–]\s*|\s+-\s+)';
    if (
        preg_match( '/(?:«[^»]{2,80}»|"[^"\n]{2,80}")' . $dash_separator . '/u', $content_message )
        && preg_match_all( '/(?:«([^»]{2,80})»|"([^"\n]{2,80})")' . $dash_separator . '(.*?)(?=;\s*(?:«|\")|(?:\.\s+)(?=(?:К\s+каждому|В\s+конце|Добавь|Добавить)\b)|$)/us', $content_message, $quoted_label_matches, PREG_SET_ORDER )
    ) {
        $quoted_label_pairs = [];
        foreach ( $quoted_label_matches as $match ) {
            $label = trim( sanitize_text_field( (string) ( $match[1] !== '' ? $match[1] : ( $match[2] ?? '' ) ) ) );
            $content = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
            if ( $label !== '' && $content !== '' ) {
                $quoted_label_pairs[] = [ 'label' => $label, 'content' => $content ];
            }
        }
        if ( count( $quoted_label_pairs ) >= 2 ) {
            return $quoted_label_pairs;
        }
    }
    $quoted_pairs = [];
    if ( preg_match_all( '/(?:^|(?<=[.!?»;])\s+)([^,.\n—–-]{2,80})\s*,\s*([^—–,:;]{2,120}?)' . $dash_separator . '(«[^»]{2,240}»|"[^"\n]{2,240}")/u', $content_message, $quoted_matches, PREG_SET_ORDER ) ) {
        foreach ( $quoted_matches as $match ) {
            $name = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
            $company = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
            $quote = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
            if ( $name !== '' && $company !== '' && $quote !== '' ) {
                $quoted_pairs[] = [ 'label' => $name . ', ' . $company, 'content' => $quote ];
            }
        }
    }
    if ( count( $quoted_pairs ) >= 2 ) {
        return $quoted_pairs;
    }

    $reverse_quoted_pairs = [];
    if ( preg_match_all( '/(?:«([^»]{2,240})»|"([^"\n]{2,240})")\s*[.!?]?\s*([^,.;\n—–-]{2,80})\s*,\s*([^.!?;\n—–-]{2,120})(?=\s*[.!?;]|$)/u', $content_message, $reverse_quoted_matches, PREG_SET_ORDER ) ) {
        foreach ( $reverse_quoted_matches as $match ) {
            $quote = trim( (string) ( $match[1] !== '' ? '«' . $match[1] . '»' : '"' . ( $match[2] ?? '' ) . '"' ) );
            $name = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
            $company = trim( sanitize_text_field( (string) ( $match[4] ?? '' ) ) );
            if ( $name !== '' && $company !== '' && $quote !== '' ) {
                $reverse_quoted_pairs[] = [ 'label' => $name . ', ' . $company, 'content' => $quote ];
            }
        }
    }
    if ( count( $reverse_quoted_pairs ) >= 2 ) {
        return $reverse_quoted_pairs;
    }

    $dash_pairs = [];
    $dash_segments = preg_split( '/(?:\r?\n+|(?<=[.!?])\s+|;\s*)/u', trim( $content_message ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    foreach ( $dash_segments as $segment ) {
        $segment = trim( (string) $segment );
        if ( ! preg_match( '/^\s*([^—–,:;]{2,80}?)' . $dash_separator . '(.{3,320}?)\s*[.!?]?\s*$/u', $segment, $match ) ) {
            continue;
        }
        $label = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
        $content = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
        if ( $label !== '' && $content !== '' ) {
            $dash_pairs[] = [ 'label' => $label, 'content' => $content ];
        }
    }
    if ( count( $dash_pairs ) >= 2 ) {
        return $dash_pairs;
    }

    $natural_pairs = [];
    $natural_segments = preg_split( '/(?:\r?\n+|;\s*|(?<=[.!?])\s+)/u', trim( $content_message ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    foreach ( $natural_segments as $segment ) {
        $segment = trim( (string) $segment );
        if ( ! preg_match( '/(?:^|[.!?]\s+)([^,.;—–-]{2,80})\s*,\s*([^—–,:;]{2,120}?)' . $dash_separator . '(.{3,320})$/u', $segment, $match ) ) {
            continue;
        }
        $name = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
        $company = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
        $content = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
        if ( $name !== '' && $company !== '' && $content !== '' ) {
            $natural_pairs[] = [ 'label' => $name . ', ' . $company, 'content' => $content ];
        }
    }
    if ( count( $natural_pairs ) >= 2 ) {
        return $natural_pairs;
    }

    $pairs = [];
    $segments = preg_split( '/(?:\r?\n+|(?<=[.!?])\s+(?=[^—–-]))/u', $content_message ) ?: [];
    foreach ( $segments as $segment ) {
        $segment = trim( (string) $segment );
        $colon_position = strpos( $segment, ':' );
        if (
            $colon_position !== false
            && preg_match( '/^\s*[^—–,:-]{2,80}?' . $dash_separator . '/u', substr( $segment, $colon_position + 1 ) )
        ) {
            $segment = trim( substr( $segment, $colon_position + 1 ) );
        }

        $matched_pairs = [];
        if ( preg_match_all( '/(?:^|[,;]\s*)([^—–,:-]{2,80}?)' . $dash_separator . '(.{3,240}?)(?=(?:[,;]\s*[^—–,:-]{2,80}?' . $dash_separator . ')|$)/u', $segment, $matches, PREG_SET_ORDER ) ) {
            $matched_pairs = $matches;
        }
        if ( empty( $matched_pairs ) && preg_match( '/^\s*([^—–-]{2,80}?)' . $dash_separator . '(.{3,240})\s*$/u', $segment, $match ) ) {
            $matched_pairs = [ $match ];
        }
        foreach ( $matched_pairs as $match ) {
            $label = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
            if ( strpos( $label, ':' ) !== false ) {
                $parts = preg_split( '/:\s*/u', $label );
                $label = trim( (string) end( $parts ) );
            }
            $content = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
            if ( $label !== '' && $content !== '' ) {
                $pairs[] = [ 'label' => $label, 'content' => $content ];
            }
        }
    }
    return $pairs;
}

function wpae_llm_extract_faq_content( string $message ): array {
    $message = trim( sanitize_text_field( $message ) );
    $message = preg_replace( '/^\s*(?:добавь|добавить|создай|создать|сделай|сформируй)\b[^:]{0,160}:\s*/iu', '', $message );
    $message = preg_replace( '/^\s*(?:faq|частые вопросы|вопросы)\s*:\s*/iu', '', $message );
    $units = preg_split( '/(?:\r?\n+|(?<=[.!?؟])\s+)/u', trim( (string) $message ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    $pairs = [];
    for ( $index = 0, $count = count( $units ); $index < $count; $index++ ) {
        $question = trim( (string) $units[ $index ] );
        if ( ! preg_match( '/[?؟]\s*$/u', $question ) ) {
            continue;
        }
        $label = trim( preg_replace( '/[?؟\s]+$/u', '', $question ) );
        $answer_parts = [];
        for ( $answer_index = $index + 1; $answer_index < $count; $answer_index++ ) {
            $candidate = trim( (string) $units[ $answer_index ] );
            if ( preg_match( '/[?؟]\s*$/u', $candidate ) ) {
                break;
            }
            $answer_parts[] = $candidate;
        }
        $content = trim( preg_replace( '/[.!?؟\s]+$/u', '', implode( ' ', $answer_parts ) ) );
        $label = trim( sanitize_text_field( $label ) );
        $content = trim( sanitize_text_field( $content ) );
        if ( $label !== '' && $content !== '' ) {
            $pairs[] = [ 'label' => $label, 'content' => $content ];
        }
        $index += count( $answer_parts );
    }
    return array_slice( $pairs, 0, 12 );
}

function wpae_llm_collect_action_content( array $elements ): string {
    $content = [];
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        foreach ( [ 'title', 'title_text', 'description_text', 'editor', 'text', 'tab_title', 'tab_content', 'item_title' ] as $key ) {
            if ( is_scalar( $settings[ $key ] ?? null ) ) {
                $content[] = (string) $settings[ $key ];
            }
            if ( is_scalar( $element[ $key ] ?? null ) ) {
                $content[] = (string) $element[ $key ];
            }
        }
        if ( is_array( $settings['tabs'] ?? null ) ) {
            $content[] = wpae_llm_collect_action_content( $settings['tabs'] );
        }
        if ( is_array( $settings['menu_items'] ?? null ) ) {
            $content[] = wpae_llm_collect_action_content( $settings['menu_items'] );
        }
        foreach ( [ 'tabs', 'elements', 'menu_items' ] as $child_key ) {
            if ( is_array( $element[ $child_key ] ?? null ) ) {
                $content[] = wpae_llm_collect_action_content( $element[ $child_key ] );
            }
        }
    }
    return implode( ' ', $content );
}

function wpae_llm_content_fidelity( string $message, array $elements ): array {
    $requested = wpae_llm_extract_requested_content( $message );
    $haystack = wpae_llm_normalize_content_text( wpae_llm_collect_action_content( $elements ) );
    $missing = [];
    foreach ( $requested as $value ) {
        if ( strpos( $haystack, wpae_llm_normalize_content_text( $value ) ) === false ) {
            $missing[] = $value;
        }
    }
    return [
        'requested_count' => count( $requested ),
        'matched_count' => count( $requested ) - count( $missing ),
        'missing' => array_slice( $missing, 0, 12 ),
        'ok' => empty( $missing ),
    ];
}

function wpae_llm_compact_cta_text( string $value ): string {
    $value = trim( sanitize_text_field( $value ) );
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    if ( $length <= 64 ) {
        return $value;
    }

    $labels = [
        'обсуд' => 'Обсудить проект',
        'получ' => 'Получить расчёт',
        'узна' => 'Узнать больше',
        'заказ' => 'Заказать',
        'оформ' => 'Оформить заказ',
        'куп' => 'Купить',
        'нач' => 'Начать',
        'выбр' => 'Выбрать формат',
        'напис' => 'Написать нам',
        'связ' => 'Связаться',
        'остав' => 'Оставить заявку',
        'смотр' => 'Смотреть кейсы',
        'запиш' => 'Записаться',
        'заброн' => 'Забронировать',
        'регистрац' => 'Регистрация',
    ];
    foreach ( $labels as $needle => $label ) {
        if ( preg_match( '/\b' . preg_quote( $needle, '/' ) . '/iu', $value ) ) {
            return $label;
        }
    }

    return $value;
}

function wpae_llm_clear_unrequested_library_copy( array &$elements, string $message, int &$changed ): void {
    $requested = array_values( array_unique( array_merge( wpae_llm_extract_requested_content( $message ), wpae_llm_content_units( $message ) ) ) );
    if ( empty( $requested ) ) {
        return;
    }

    $requested_text = wpae_llm_normalize_content_text( implode( ' ', $requested ) );
    $title = trim( (string) ( wpae_llm_content_units( $message )[0] ?? ( $requested[0] ?? '' ) ) );
    $cta = '';
    foreach ( $requested as $value ) {
        if ( wpae_llm_is_cta_copy( (string) $value ) ) {
            $cta = wpae_llm_compact_cta_text( $value );
            break;
        }
    }
    $title_set = false;
    $cta_set = false;
    $matches_request = static function ( $value ) use ( $requested_text ): bool {
        $value = trim( wp_strip_all_tags( (string) $value ) );
        $normalized = wpae_llm_normalize_content_text( $value );
        return $normalized !== '' && strpos( $requested_text, $normalized ) !== false;
    };
    $widget_has_requested_copy = static function ( array $settings, string $widget_type ) use ( $matches_request ): bool {
        $keys = [
            'heading' => [ 'title' ],
            'text-editor' => [ 'editor' ],
            'button' => [ 'text' ],
            'icon-box' => [ 'title_text', 'description_text' ],
            'testimonial' => [ 'testimonial_name', 'testimonial_content', 'description_text' ],
            'counter' => [ 'ending_number', 'title' ],
            'call-to-action' => [ 'title', 'description', 'button_text' ],
        ];
        foreach ( $keys[ $widget_type ] ?? [] as $key ) {
            if ( $matches_request( $settings[ $key ] ?? '' ) ) {
                return true;
            }
        }
        return false;
    };
    $copy_widgets = [ 'heading', 'text-editor', 'button', 'icon-box', 'image-box', 'testimonial', 'counter', 'icon-list', 'call-to-action' ];
    $walk = static function ( array &$nodes ) use ( &$walk, $title, $cta, &$title_set, &$cta_set, $matches_request, $widget_has_requested_copy, $copy_widgets, &$changed ): void {
        $kept = [];
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
            if ( ( $element['elType'] ?? '' ) === 'widget' && in_array( $widget_type, $copy_widgets, true ) ) {
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $keep = false;
                if ( $widget_type === 'heading' ) {
                    $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                    if ( ! $title_set && $title !== '' && ( ! is_array( $classes ) || ! in_array( 'wpae-generated-badge-label', $classes, true ) ) ) {
                        $settings['title'] = $title;
                        $title_set = true;
                        $keep = true;
                        $changed++;
                    } else {
                        $keep = $widget_has_requested_copy( $settings, $widget_type );
                    }
                } elseif ( $widget_type === 'button' && $cta !== '' && ! $cta_set ) {
                    $settings['text'] = $cta;
                    $cta_set = true;
                    $keep = true;
                    $changed++;
                } elseif ( $widget_type === 'button' ) {
                    // A card description can contain an action verb without being a CTA.
                    $keep = false;
                } elseif ( $widget_type === 'icon-list' ) {
                    $items = is_array( $settings['icon_list'] ?? null ) ? $settings['icon_list'] : [];
                    $items = array_values( array_filter( $items, static fn( $item ): bool => is_array( $item ) && $matches_request( $item['text'] ?? '' ) ) );
                    if ( ! empty( $items ) ) {
                        $settings['icon_list'] = $items;
                        $keep = true;
                    }
                } else {
                    $keep = $widget_has_requested_copy( $settings, $widget_type );
                }
                if ( $keep ) {
                    $element['settings'] = $settings;
                    $kept[] = $element;
                } else {
                    $changed++;
                }
                continue;
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
            $kept[] = $element;
        }
        $nodes = $kept;
    };
    $walk( $elements );
}

function wpae_llm_apply_fallback_content( array &$elements, array &$missing, string $archetype, int &$changed, int $depth = 0 ): void {
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $repeatable_archetypes = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ];
        $is_repeatable_shell = in_array( $archetype, $repeatable_archetypes, true ) && $depth === 1;
        if ( ! $is_repeatable_shell && ! empty( $missing ) && in_array( $widget_type, [ 'heading', 'icon-box', 'text-editor', 'button' ], true ) ) {
            $value_index = 0;
            if ( $archetype === 'pricing' ) {
                $is_numeric = static fn( $value ): bool => (bool) preg_match( '/\d|₸|\$|€|₽/u', (string) $value );
                $wanted_numeric = $widget_type === 'text-editor';
                foreach ( $missing as $index => $value ) {
                    if ( $is_numeric( $value ) === $wanted_numeric ) {
                        $value_index = $index;
                        break;
                    }
                }
            } elseif ( $archetype === 'testimonials' && $widget_type === 'heading' ) {
                $value_index = count( $missing ) - 1;
            }
            $key = $widget_type === 'heading' ? 'title' : ( $widget_type === 'icon-box' ? 'title_text' : ( $widget_type === 'button' ? 'text' : 'editor' ) );
            $settings[ $key ] = $missing[ $value_index ];
            array_splice( $missing, $value_index, 1 );
            $changed++;
        }
        $element['settings'] = $settings;
        foreach ( [ 'elements' ] as $child_key ) {
            if ( is_array( $element[ $child_key ] ?? null ) ) {
                wpae_llm_apply_fallback_content( $element[ $child_key ], $missing, $archetype, $changed, $depth + 1 );
            }
        }
        if ( $depth === 0 && ( $element['elType'] ?? '' ) === 'container' && ! empty( $missing ) ) {
            if ( $archetype === 'faq' ) {
                continue;
            }
            foreach ( array_values( $missing ) as $index => $value ) {
                $element['elements'][] = [
                    'id' => 'llm-fallback-copy-' . (string) ( $index + 1 ),
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => [ 'editor' => $value ],
                    'elements' => [],
                ];
                $changed++;
            }
            $missing = [];
        }
    }
    unset( $element );
}

function wpae_llm_remove_unrequested_buttons( array &$elements, string $message, int &$changed ): void {
    $has_requested_cta = false;
    foreach ( wpae_llm_extract_requested_content( $message ) as $value ) {
        if ( wpae_llm_is_cta_copy( (string) $value ) ) {
            $has_requested_cta = true;
            break;
        }
    }
    if ( $has_requested_cta ) {
        return;
    }
    $walk = static function ( array &$nodes ) use ( &$walk, &$changed ): void {
        $kept = [];
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( ( $element['elType'] ?? '' ) === 'widget' && sanitize_key( (string) ( $element['widgetType'] ?? '' ) ) === 'button' ) {
                $changed++;
                continue;
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
            $kept[] = $element;
        }
        $nodes = $kept;
    };
    $walk( $elements );
}

function wpae_llm_apply_fallback_archetype_content( array &$elements, string $message, string $archetype, int &$changed ): void {
    if ( ! in_array( $archetype, [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ], true ) ) {
        return;
    }
    if ( $archetype === 'process' ) {
        $heading = '';
        if ( preg_match( '/(?:заголовок|название)\s*:\s*[«"]([^»"\n]{2,240})[»"]/iu', $message, $match ) ) {
            $heading = trim( sanitize_text_field( (string) $match[1] ) );
        }
        if ( $heading !== '' ) {
            foreach ( $elements as &$root ) {
                if ( ! is_array( $root ) || ! is_array( $root['elements'] ?? null ) ) {
                    continue;
                }
                foreach ( $root['elements'] as &$child ) {
                    if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' && ( $child['widgetType'] ?? '' ) === 'heading' ) {
                        $child['settings'] = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
                        $child['settings']['title'] = $heading;
                        $changed++;
                        break 2;
                    }
                }
                unset( $child );
            }
            unset( $root );
        }
        return;
    }
    $pairs = wpae_llm_extract_labeled_content( $message );
    if ( count( $pairs ) < 2 ) {
        return;
    }
    $heading = '';
    if ( preg_match( '/(?:заголовок|название)\s*:\s*[«"]([^»"\n]{2,240})[»"]/iu', $message, $match ) ) {
        $heading = trim( sanitize_text_field( (string) $match[1] ) );
    }
    $cta = '';
    if ( preg_match( '/(?:кнопка|cta)\s*:\s*([^\.\n]{3,120})/iu', $message, $match ) ) {
        $cta = trim( sanitize_text_field( (string) $match[1] ) );
    }
    foreach ( wpae_llm_extract_requested_content( $message ) as $value ) {
        if ( $cta === '' && preg_match( '/получить|расч[её]т|обсудить|задать|узнать|смотреть/iu', $value ) ) {
            $cta = $value;
            break;
        }
    }
    foreach ( $elements as &$root ) {
        if ( ! is_array( $root ) || ! is_array( $root['elements'] ?? null ) ) {
            continue;
        }
        foreach ( $root['elements'] as &$child ) {
            if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'container' || ! is_array( $child['elements'] ?? null ) ) {
                continue;
            }
            $cards = array_values( array_filter( $child['elements'], static fn( $item ) => is_array( $item ) && ( $item['elType'] ?? '' ) === 'container' ) );
            if ( count( $cards ) < 2 ) {
                continue;
            }
            if ( $archetype === 'pricing' && ! empty( array_filter(
                $cards,
                static function ( $card ): bool {
                    $classes = preg_split( '/\s+/', trim( (string) ( $card['settings']['_css_classes'] ?? '' ) ) );
                    return is_array( $classes ) && in_array( 'wpae-pricing-card', $classes, true );
                }
            ) ) ) {
                continue;
            }
            $target_card_count = min( 12, max( 2, count( $pairs ) ) );
            while ( count( $cards ) < $target_card_count ) {
                $template = $cards[ count( $cards ) - 1 ];
                $new_card_index = count( $cards ) + 1;
                $template['id'] = 'llm-' . $archetype . '-' . (string) $new_card_index;
                foreach ( (array) ( $template['elements'] ?? [] ) as $widget_index => $widget ) {
                    if ( is_array( $widget ) ) {
                        $template['elements'][ $widget_index ]['id'] = $template['id'] . '-' . (string) ( $widget_index + 1 );
                    }
                }
                $child['elements'][] = $template;
                $cards[] = $template;
            }
            if ( count( $cards ) > $target_card_count ) {
                $trimmed = [];
                $card_count = 0;
                foreach ( $child['elements'] as $item ) {
                    if ( is_array( $item ) && ( $item['elType'] ?? '' ) === 'container' ) {
                        if ( $card_count >= $target_card_count ) {
                            continue;
                        }
                        $card_count++;
                    }
                    $trimmed[] = $item;
                }
                $child['elements'] = $trimmed;
            }
            foreach ( $child['elements'] as &$card ) {
                if ( ! is_array( $card ) || ( $card['elType'] ?? '' ) !== 'container' || ! isset( $pairs[0] ) ) {
                    continue;
                }
                $pair = array_shift( $pairs );
                $title_set = false;
                $copy_set = false;
                $card_elements = is_array( $card['elements'] ?? null ) ? $card['elements'] : [];
                foreach ( $card_elements as &$widget ) {
                    if ( ! is_array( $widget ) ) {
                        continue;
                    }
                    $widget_type = (string) ( $widget['widgetType'] ?? '' );
                    $widget['settings'] = is_array( $widget['settings'] ?? null ) ? $widget['settings'] : [];
                    if ( $widget_type === 'heading' && ! $title_set ) {
                        $widget['settings']['title'] = $pair['label'];
                        $title_set = true;
                        $changed++;
                    } elseif ( $widget_type === 'icon-box' ) {
                        if ( ! $title_set ) {
                            $widget['settings']['title_text'] = $pair['label'];
                            $title_set = true;
                            $changed++;
                        }
                        if ( ! $copy_set ) {
                            $widget['settings']['description_text'] = $pair['content'];
                            $copy_set = true;
                            $changed++;
                        }
                    } elseif ( in_array( $widget_type, [ 'text-editor', 'testimonial' ], true ) && ! $copy_set ) {
                        $widget['settings'][ $widget_type === 'testimonial' ? 'testimonial_content' : 'editor' ] = $pair['content'];
                        $copy_set = true;
                        $changed++;
                    } elseif ( $widget_type === 'button' && $cta !== '' ) {
                        $widget['settings']['text'] = $cta;
                        $changed++;
                    }
                }
                unset( $widget );
                $card['elements'] = $card_elements;
            }
            unset( $card );
        }
        unset( $child );
        if ( $heading !== '' && isset( $root['elements'][0]['widgetType'] ) && $root['elements'][0]['widgetType'] === 'heading' ) {
            $root['elements'][0]['settings'] = is_array( $root['elements'][0]['settings'] ?? null ) ? $root['elements'][0]['settings'] : [];
            $root['elements'][0]['settings']['title'] = $heading;
            $changed++;
        }
    }
    unset( $root );
    wpae_llm_apply_fallback_cta( $elements, $cta, $changed );
}

function wpae_llm_apply_library_pair_to_widgets( array &$elements, array $pair, int &$changed, string $archetype = '', bool $content_already_set = false ): bool {
    $title = trim( (string) ( $pair['label'] ?? '' ) );
    $content = trim( (string) ( $pair['content'] ?? '' ) );
    $title_set = false;
    $content_set = false;
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( ( $element['elType'] ?? '' ) === 'widget' ) {
            $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $title_set_on_current_widget = false;
            if ( $title !== '' && ! $title_set && $widget_type === 'heading' ) {
                $settings['title'] = $title;
                $title_set = true;
                $title_set_on_current_widget = true;
                $changed++;
            } elseif ( $title !== '' && ! $title_set && $widget_type === 'icon-box' ) {
                $settings['title_text'] = $title;
                $title_set = true;
                $title_set_on_current_widget = true;
                $changed++;
            } elseif ( $title !== '' && ! $title_set && $widget_type === 'testimonial' ) {
                $settings['testimonial_name'] = $title;
                $title_set = true;
                $title_set_on_current_widget = true;
                $changed++;
            }
            if ( $archetype === 'testimonials' && $title_set_on_current_widget && $content_set && trim( (string) ( $settings['description_text'] ?? '' ) ) !== '' ) {
                $settings['description_text'] = '';
                $changed++;
            }
            if ( $content !== '' && ! $content_set && $widget_type === 'icon-box' ) {
                if ( $archetype === 'testimonials' && $content_already_set ) {
                    if ( trim( (string) ( $settings['description_text'] ?? '' ) ) !== '' ) {
                        $settings['description_text'] = '';
                        $changed++;
                    }
                    $content_set = true;
                } else {
                    $settings['description_text'] = $content;
                    $content_set = true;
                    $changed++;
                }
            } elseif ( $content !== '' && ! $content_set && $widget_type === 'testimonial' ) {
                $settings['testimonial_content'] = $content;
                $content_set = true;
                $changed++;
            } elseif ( $content !== '' && ! $content_set && $widget_type === 'text-editor' ) {
                $settings['editor'] = $content;
                $content_set = true;
                $changed++;
            } elseif ( $content !== '' && ! $content_set && $widget_type === 'heading' && $title_set && ! $title_set_on_current_widget ) {
                $settings['title'] = $content;
                $content_set = true;
                $changed++;
            }
            $element['settings'] = $settings;
        }
        if ( ( $title_set && ( $content_set || $content === '' ) ) ) {
            return true;
        }
        if ( is_array( $element['elements'] ?? null ) && wpae_llm_apply_library_pair_to_widgets( $element['elements'], $pair, $changed, $archetype, $content_set || $content_already_set ) ) {
            return true;
        }
    }
    unset( $element );
    return $title_set && ( $content_set || $content === '' );
}

function wpae_llm_apply_library_narrative_content( array &$elements, array $requested, int &$changed, bool $clear_unrequested_copy = false ): bool {
    if ( empty( $requested ) ) {
        return false;
    }

    $title = trim( (string) array_shift( $requested ) );
    $cta = '';
    for ( $index = count( $requested ) - 1; $index >= 0; $index-- ) {
        if ( wpae_llm_is_cta_copy( (string) $requested[ $index ] ) ) {
            $cta = trim( (string) $requested[ $index ] );
            array_splice( $requested, $index, 1 );
            break;
        }
    }
    $body = trim( implode( ' ', array_filter( array_map( 'strval', $requested ) ) ) );
    $numeric_copy = '';
    if ( preg_match( '/\b\d+\s*(?:недел(?:я|и|ь)|месяц(?:а|ев)?|заняти(?:е|я|й)|лет|года?|%)/iu', $body, $numeric_match ) ) {
        $numeric_copy = trim( (string) $numeric_match[0] );
    }
    $audience_copy = '';
    if ( preg_match( '/\bдля\s+[^,.!?]+/iu', $title, $audience_match ) ) {
        $audience_copy = trim( (string) $audience_match[0] );
    }
    $first_body_unit = (string) ( wpae_llm_content_units( $body )[0] ?? $body );
    $first_body_unit = trim( (string) ( preg_split( '/\s+(?=(?:практика|разбор|прогресс|срок(?:и)?|фокус|результат)\b)/iu', $first_body_unit, 2 )[0] ?? $first_body_unit ) );
    $first_body_words = preg_split( '/\s+/u', $first_body_unit, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    if ( count( $first_body_words ) > 6 ) {
        $first_body_unit = implode( ' ', array_slice( $first_body_words, 0, 6 ) );
    }
    $normalized_requested_copy = wpae_llm_normalize_content_text( $title . ' ' . $body );
    $is_unique_slot_copy = static function ( string $value ) use ( $normalized_requested_copy ): bool {
        $normalized_slot = wpae_llm_normalize_content_text( $value );
        return $normalized_slot !== '' && strpos( $normalized_requested_copy, $normalized_slot ) === false;
    };
    $title_set = false;
    $body_set = false;
    $cta_set = false;
    $numeric_set = false;
    $audience_set = false;
    $confidence_set = false;
    $target_widget_ids = [];
    $walk = static function ( array &$nodes ) use ( &$walk, $title, $body, $cta, $clear_unrequested_copy, $numeric_copy, $audience_copy, $first_body_unit, $is_unique_slot_copy, &$title_set, &$body_set, &$cta_set, &$numeric_set, &$audience_set, &$confidence_set, &$target_widget_ids, &$changed ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $source_text = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? $settings['text'] ?? '' ) ) );
            if ( $widget_type === 'heading' && ! $title_set ) {
                $settings['title'] = $title;
                if ( $clear_unrequested_copy ) {
                    $title_length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
                    $heading_desktop_size = $title_length > 48 ? 2.9 : 3.3;
                    $heading_tablet_size = $title_length > 48 ? 2.4 : 2.7;
                    $heading_mobile_size = $title_length > 48 ? 1.95 : 2.1;
                    $settings['typography_typography'] = 'custom';
                    $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => $heading_desktop_size ];
                    $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => $heading_tablet_size ];
                    $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => $heading_mobile_size ];
                    $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.05 ];
                    $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.05 ];
                    $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.1 ];
                }
                $title_set = true;
                $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                $changed++;
            } elseif ( $widget_type === 'heading' && $clear_unrequested_copy ) {
                $is_numeric_slot = preg_match( '/50\+|week|недел|month|месяц/i', $source_text ) && $numeric_copy !== '' && ! $numeric_set && $is_unique_slot_copy( $numeric_copy );
                $is_audience_slot = preg_match( '/coach|speaker|trainer|руковод/i', $source_text ) && $audience_copy !== '' && ! $audience_set && $is_unique_slot_copy( $audience_copy );
                if ( $is_numeric_slot ) {
                    $settings['title'] = $numeric_copy;
                    $numeric_set = true;
                    $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                    $changed++;
                } elseif ( $is_audience_slot ) {
                    $settings['title'] = $audience_copy;
                    $audience_set = true;
                    $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                    $changed++;
                }
            } elseif ( $widget_type === 'text-editor' && $body !== '' && ! $body_set ) {
                $settings['editor'] = $body;
                if ( $clear_unrequested_copy ) {
                    $settings['typography_typography'] = 'custom';
                    $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.15 ];
                    $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.05 ];
                    $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 0.95 ];
                    $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.5 ];
                    $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.5 ];
                    $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.45 ];
                    if ( is_array( $settings['_margin'] ?? null ) ) {
                        $settings['_margin']['bottom'] = '0';
                    }
                }
                $body_set = true;
                $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                $changed++;
            } elseif ( $widget_type === 'button' && $cta !== '' && ! $cta_set ) {
                $settings['text'] = $cta;
                $cta_set = true;
                $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                $changed++;
            } elseif ( $widget_type === 'icon-list' && $clear_unrequested_copy ) {
                $items = is_array( $settings['icon_list'] ?? null ) ? $settings['icon_list'] : [];
                $adapted_items = [];
                foreach ( $items as &$item ) {
                    if ( is_array( $item ) ) {
                        $source_item_text = trim( (string) ( $item['text'] ?? '' ) );
                        if ( preg_match( '/week|недел|program|программ/i', $source_item_text ) && $numeric_copy !== '' && ! $numeric_set && $is_unique_slot_copy( $numeric_copy ) ) {
                            $item['text'] = $numeric_copy;
                            $numeric_set = true;
                            $adapted_items[] = $item;
                        } elseif ( preg_match( '/confidence|уверен/i', $source_item_text ) && $first_body_unit !== '' && ! $confidence_set && $is_unique_slot_copy( $first_body_unit ) ) {
                            $item['text'] = $first_body_unit;
                            $confidence_set = true;
                            $adapted_items[] = $item;
                        }
                    }
                }
                unset( $item );
                if ( ! empty( $adapted_items ) ) {
                    $settings['icon_list'] = $adapted_items;
                    $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                    $changed++;
                }
            } elseif ( $widget_type === 'icon-box' && $clear_unrequested_copy ) {
                if ( ! $body_set && $body !== '' ) {
                    $settings['title_text'] = $title;
                    $settings['description_text'] = $body;
                    $body_set = true;
                    $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] = true;
                    $changed++;
                }
            }
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );

    if ( $clear_unrequested_copy ) {
        $copy_widget_types = [ 'heading', 'text-editor', 'button', 'icon-box', 'image-box', 'testimonial', 'counter', 'icon-list', 'call-to-action' ];
        $prune = static function ( array &$nodes ) use ( &$prune, $copy_widget_types, $target_widget_ids, &$changed ): void {
            $kept = [];
            foreach ( $nodes as $element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                $is_copy_widget = ( $element['elType'] ?? '' ) === 'widget' && in_array( $widget_type, $copy_widget_types, true );
                $is_target = isset( $target_widget_ids[ (string) ( $element['id'] ?? '' ) ] );
                if ( $is_copy_widget && ! $is_target ) {
                    $changed++;
                    continue;
                }
                if ( is_array( $element['elements'] ?? null ) ) {
                    $prune( $element['elements'] );
                }
                $kept[] = $element;
            }
            $nodes = $kept;
        };
        $prune( $elements );
    }

    if ( $body !== '' && ! $body_set ) {
        foreach ( $elements as &$root ) {
            if ( ! is_array( $root ) || ( $root['elType'] ?? '' ) !== 'container' ) {
                continue;
            }
            $root['elements'] = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
            $root['elements'][] = [
                'id' => 'wpae-library-copy-' . substr( md5( $body ), 0, 7 ),
                'elType' => 'widget',
                'widgetType' => 'text-editor',
                'settings' => [ 'editor' => $body ],
                'elements' => [],
            ];
            $body_set = true;
            $changed++;
            break;
        }
        unset( $root );
    }

    return $title_set && ( $body === '' || $body_set ) && ( $cta === '' || $cta_set );
}

function wpae_llm_extract_team_content( string $message ): array {
    $content_message = preg_replace( '/^\s*(?:добавь|добавить|создай|создать|сделай|сформируй)\b[^:]{0,160}:\s*/iu', '', trim( $message ) );
    if ( ! is_string( $content_message ) || $content_message === '' ) {
        $content_message = $message;
    }

    $pairs = [];
    $segments = preg_split( '/(?<=\.)\s+(?=[\p{Lu}][^,.;\n]{2,80},\s*[^.!?;,\n]{2,80}\.)/u', $content_message, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
    foreach ( $segments as $segment ) {
        if ( ! preg_match( '/^\s*(?!наша\s+команд\w*\b)([^,.;\n]{2,80}),\s*([^.!?;,\n]{2,80})\.\s*(.+?)\s*$/us', trim( (string) $segment ), $match ) ) {
            continue;
        }
        $name = trim( sanitize_text_field( (string) ( $match[1] ?? '' ) ) );
        $role = trim( sanitize_text_field( (string) ( $match[2] ?? '' ) ) );
        $description = trim( sanitize_text_field( (string) ( $match[3] ?? '' ) ) );
        if ( $name !== '' && $role !== '' && $description !== '' ) {
            $pairs[] = [
                'label' => $name . ', ' . $role,
                'content' => $description,
            ];
        }
    }

    return $pairs;
}

function wpae_llm_apply_library_image_box_content( array &$elements, string $message, int &$changed ): bool {
    $units = wpae_llm_content_units( $message );
    if ( count( $units ) < 3 ) {
        return false;
    }
    $heading = trim( sanitize_text_field( (string) array_shift( $units ) ) );
    $cards = array_values( array_filter( array_map( static fn( $unit ): string => trim( sanitize_text_field( (string) $unit ) ), $units ) ) );
    if ( $heading === '' || count( $cards ) < 2 ) {
        return false;
    }

    $short_title = static function ( string $content ): string {
        if ( preg_match( '/^(?:сайт|онлайн[- ]школа|приложение|платформа|сервис|магазин|мобильн\w+)[^,.!?]*/iu', $content, $match ) ) {
            return trim( sanitize_text_field( (string) $match[0] ) );
        }
        $words = preg_split( '/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
        return trim( implode( ' ', array_slice( $words, 0, 5 ) ) );
    };
    $set_card_content = static function ( array &$nodes, string $title, string $content ) use ( &$set_card_content, &$changed ): bool {
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            if ( ( $node['elType'] ?? '' ) === 'widget' ) {
                $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
                if ( $widget_type === 'icon-box' ) {
                    if ( (string) ( $settings['title_text'] ?? '' ) !== $title ) {
                        $settings['title_text'] = $title;
                        $changed++;
                    }
                    if ( (string) ( $settings['description_text'] ?? '' ) !== $content ) {
                        $settings['description_text'] = $content;
                        $changed++;
                    }
                    $node['settings'] = $settings;
                    return true;
                }
                if ( $widget_type === 'heading' && trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) ) !== '' ) {
                    if ( (string) $settings['title'] !== $title ) {
                        $settings['title'] = $title;
                        $changed++;
                    }
                    $node['settings'] = $settings;
                    return true;
                }
            }
            if ( is_array( $node['elements'] ?? null ) && $set_card_content( $node['elements'], $title, $content ) ) {
                return true;
            }
        }
        unset( $node );
        return false;
    };
    $make_intro_heading = static function ( string $title ): array {
        return [
            'id' => 'wpae-image-box-heading-' . substr( md5( $title ), 0, 7 ),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => [
                'title' => $title,
                'header_size' => 'h2',
                'align' => 'center',
                'typography_typography' => 'custom',
                'typography_font_size' => [ 'unit' => 'rem', 'size' => 2.5 ],
                'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 2.1 ],
                'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1.75 ],
                'typography_line_height' => [ 'unit' => 'em', 'size' => 1.1 ],
                'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.15 ],
            ],
            'elements' => [],
        ];
    };
    $adapt = static function ( array &$nodes ) use ( &$adapt, $cards, $short_title, $set_card_content, $make_intro_heading, $heading, &$changed ): bool {
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) || ! is_array( $node['elements'] ?? null ) ) {
                continue;
            }
            foreach ( $node['elements'] as $child_index => $child ) {
                if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' || ! in_array( sanitize_key( (string) ( $child['widgetType'] ?? '' ) ), [ 'nested-carousel', 'n-carousel' ], true ) ) {
                    continue;
                }
                $slides = is_array( $child['elements'] ?? null ) ? $child['elements'] : [];
                $slide_indexes = [];
                foreach ( $slides as $slide_index => $slide ) {
                    if ( is_array( $slide ) && ( $slide['elType'] ?? '' ) === 'container' ) {
                        $slide_indexes[] = $slide_index;
                    }
                }
                if ( count( $slide_indexes ) < 2 ) {
                    continue;
                }
                $target_count = min( count( $slide_indexes ), count( $cards ) );
                $next_slides = [];
                foreach ( array_slice( $slide_indexes, 0, $target_count ) as $position => $slide_index ) {
                    $slide = $slides[ $slide_index ];
                    $card_content = $cards[ $position ];
                    $set_card_content( $slide['elements'], $short_title( $card_content ), $card_content );
                    $next_slides[] = $slide;
                }
                if ( count( $next_slides ) < count( $slide_indexes ) ) {
                    $changed += count( $slide_indexes ) - count( $next_slides );
                }
                $child['elements'] = $next_slides;
                $node['elements'][ $child_index ] = $child;
                array_splice( $node['elements'], (int) $child_index, 0, [ $make_intro_heading( $heading ) ] );
                $changed++;
                return true;
            }
            if ( $adapt( $node['elements'] ) ) {
                return true;
            }
        }
        unset( $node );
        return false;
    };

    return $adapt( $elements );
}

function wpae_llm_mark_preserved_library_design( array $elements ): array {
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) || ( $element['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $settings['_css_classes'] = function_exists( 'wpae_append_css_classes' )
            ? wpae_append_css_classes( $settings['_css_classes'] ?? '', [ 'wpae-preserve-library-design' ] )
            : trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-preserve-library-design' );
        $element['settings'] = $settings;
    }
    unset( $element );
    return $elements;
}

function wpae_llm_materialize_preserved_library_colors( array $elements, int &$changed = 0 ): array {
    // Vocario templates reference custom Elementor colors that are not present on the target site.
    $known_colors = [
        '1edeba7' => '#4460EC',
        '6a41369' => '#FFFFFF',
        '81bea52' => '#000000',
        'ce10ccb' => '#04103A',
        'c81787f' => '#F0F0F0',
    ];
    $walk = static function ( array &$nodes ) use ( &$walk, $known_colors, &$changed ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $globals = is_array( $settings['__globals__'] ?? null ) ? $settings['__globals__'] : [];
            foreach ( $globals as $key => $reference ) {
                if ( ! is_string( $reference ) || ! preg_match( '#^globals/colors\?id=([a-z0-9]+)$#i', $reference, $match ) ) {
                    continue;
                }
                $color = $known_colors[ strtolower( (string) $match[1] ) ] ?? '';
                if ( $color === '' || (string) ( $settings[ $key ] ?? '' ) === $color ) {
                    continue;
                }
                $settings[ $key ] = $color;
                unset( $settings['__globals__'][ $key ] );
                $changed++;
            }
            if ( empty( $settings['__globals__'] ) ) {
                unset( $settings['__globals__'] );
            }
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_normalize_preserved_library_typography( array $elements, int &$changed = 0 ): array {
    $read_rem_size = static function ( $value ): ?float {
        if ( ! is_array( $value ) || ! is_numeric( $value['size'] ?? null ) ) {
            return null;
        }
        $size = (float) $value['size'];
        $unit = strtolower( (string) ( $value['unit'] ?? '' ) );
        if ( $unit === 'px' ) {
            return $size / 16;
        }
        if ( in_array( $unit, [ 'em', 'rem' ], true ) ) {
            return $size;
        }
        return null;
    };
    $set_responsive_heading_size = static function ( array &$settings, bool $long_heading ): void {
        $sizes = $long_heading ? [ 3.1, 2.6, 2.1 ] : [ 4.2, 3.4, 2.6 ];
        $settings['typography_typography'] = 'custom';
        $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => $sizes[0] ];
        $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => $sizes[1] ];
        $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => $sizes[2] ];
        $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.05 ];
        $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.08 ];
        $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.12 ];
    };
    $clear_negative_vertical_margin = static function ( array &$settings ): bool {
        $did_change = false;
        foreach ( [ 'margin', 'margin_tablet', 'margin_mobile', '_margin', '_margin_tablet', '_margin_mobile' ] as $key ) {
            if ( ! is_array( $settings[ $key ] ?? null ) ) {
                continue;
            }
            foreach ( [ 'top', 'bottom' ] as $side ) {
                if ( is_numeric( $settings[ $key ][ $side ] ?? null ) && (float) $settings[ $key ][ $side ] < 0 ) {
                    $settings[ $key ][ $side ] = '0';
                    $did_change = true;
                }
            }
        }
        return $did_change;
    };
    $walk = static function ( array &$nodes ) use ( &$walk, &$changed, $read_rem_size, $set_responsive_heading_size, $clear_negative_vertical_margin ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( ( $element['elType'] ?? '' ) === 'widget' ) {
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $text_key = $widget_type === 'text-editor' ? 'editor' : 'title';
                $text = trim( wp_strip_all_tags( (string) ( $settings[ $text_key ] ?? '' ) ) );
                $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
                if ( $widget_type === 'heading' ) {
                    $size = $read_rem_size( $settings['typography_font_size'] ?? null );
                    $unit = strtolower( (string) ( $settings['typography_font_size']['unit'] ?? '' ) );
                    $is_extreme = $size !== null && ( $size > 5.5 || ( $unit === 'em' && $size > 4.5 ) );
                    $is_long_heading = $length > 44;
                    if ( $is_extreme || ( $is_long_heading && $size !== null && $size > 3.8 ) ) {
                        $set_responsive_heading_size( $settings, $is_long_heading );
                        $changed++;
                    }
                    if ( $clear_negative_vertical_margin( $settings ) ) {
                        $changed++;
                    }
                } elseif ( $widget_type === 'text-editor' ) {
                    $size = $read_rem_size( $settings['typography_font_size'] ?? null );
                    if ( $size !== null && $size > 2.25 ) {
                        $settings['typography_typography'] = 'custom';
                        $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.15 ];
                        $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.05 ];
                        $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 0.95 ];
                        $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.5 ];
                        $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.5 ];
                        $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.45 ];
                        $changed++;
                    }
                    if ( $clear_negative_vertical_margin( $settings ) ) {
                        $changed++;
                    }
                }
                $element['settings'] = $settings;
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_sync_native_photo_overlay_settings( array &$settings, bool $has_background_image, bool $has_light_text ): bool {
    $before = wp_json_encode( $settings );
    if ( $has_background_image && $has_light_text ) {
        $settings['background_overlay_background'] = 'classic';
        $settings['background_overlay_color'] = '#000000';
        $settings['background_overlay_opacity'] = [ 'unit' => 'px', 'size' => 0.42, 'sizes' => [] ];
    } else {
        foreach ( [
            'background_overlay_background', 'background_overlay_color', 'background_overlay_color_stop',
            'background_overlay_color_stop_tablet', 'background_overlay_color_stop_mobile',
            'background_overlay_color_b', 'background_overlay_color_b_stop',
            'background_overlay_color_b_stop_tablet', 'background_overlay_color_b_stop_mobile',
            'background_overlay_gradient_type', 'background_overlay_gradient_angle',
            'background_overlay_gradient_angle_tablet', 'background_overlay_gradient_angle_mobile',
            'background_overlay_position', 'background_overlay_position_tablet',
            'background_overlay_position_mobile', 'background_overlay_opacity', 'background_overlay_opacity_b',
        ] as $overlay_key ) {
            unset( $settings[ $overlay_key ] );
        }
    }
    return $before !== wp_json_encode( $settings );
}

function wpae_llm_normalize_preserved_library_visual_state( array $elements, int &$changed = 0 ): array {
    $has_background_image = static function ( array $settings ): bool {
        foreach ( [ 'background_image', 'background_overlay_image', 'background_hover_image' ] as $key ) {
            $image = $settings[ $key ] ?? null;
            if ( is_array( $image ) && trim( (string) ( $image['url'] ?? '' ) ) !== '' ) {
                return true;
            }
            if ( is_string( $image ) && trim( $image ) !== '' ) {
                return true;
            }
        }
        return false;
    };
    $is_white = static function ( string $color ): bool {
        return in_array( strtolower( trim( $color ) ), [ '#fff', '#ffffff', 'white', 'rgb(255, 255, 255)', 'rgba(255, 255, 255, 1)' ], true );
    };
    $has_light_text = static function ( array $nodes ) use ( &$has_light_text, $is_white ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            foreach ( [ 'title_color', 'text_color', 'button_text_color', 'icon_color' ] as $key ) {
                if ( $is_white( trim( (string) ( $settings[ $key ] ?? '' ) ) ) ) {
                    return true;
                }
            }
            if ( $has_light_text( (array) ( $node['elements'] ?? [] ) ) ) {
                return true;
            }
        }
        return false;
    };
    $has_card_surface = static function ( array $settings ): bool {
        if ( trim( (string) ( $settings['border_border'] ?? '' ) ) !== '' ) {
            return true;
        }
        foreach ( [ 'border_radius', 'box_shadow' ] as $key ) {
            if ( ! is_array( $settings[ $key ] ?? null ) ) {
                continue;
            }
            foreach ( [ 'size', 'top', 'right', 'bottom', 'left', 'blur', 'spread' ] as $dimension ) {
                if ( is_numeric( $settings[ $key ][ $dimension ] ?? null ) && (float) $settings[ $key ][ $dimension ] > 0 ) {
                    return true;
                }
            }
        }
        return false;
    };
    $walk = static function ( array &$nodes ) use ( &$walk, &$changed, $has_background_image, $has_card_surface, $is_white, $has_light_text ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $before = wp_json_encode( $settings );
            foreach ( [ '_animation', 'animation', 'animation_duration', 'animation_delay', 'entrance_animation', 'entrance_animation_duration' ] as $key ) {
                unset( $settings[ $key ] );
            }
            if ( ( $element['elType'] ?? '' ) === 'container' ) {
                $color = trim( (string) ( $settings['background_color'] ?? '' ) );
                if ( $color !== '' && $is_white( $color ) && ( $has_background_image( $settings ) || ! $has_card_surface( $settings ) ) ) {
                    $settings['background_color'] = 'transparent';
                }
                wpae_llm_sync_native_photo_overlay_settings( $settings, $has_background_image( $settings ), $has_light_text( (array) ( $element['elements'] ?? [] ) ) );
            }
            if ( $before !== wp_json_encode( $settings ) ) {
                $changed++;
            }
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_sanitize_background_image_urls( $urls ): array {
    if ( ! is_array( $urls ) ) {
        return [];
    }

    $sanitized = [];
    foreach ( array_slice( $urls, 0, 48 ) as $url ) {
        $url = esc_url_raw( (string) $url );
        if ( $url !== '' && preg_match( '#^https?://#i', $url ) ) {
            $sanitized[] = $url;
        }
    }

    return array_values( array_unique( $sanitized ) );
}

function wpae_llm_collect_background_image_urls( array $nodes, array &$urls = [] ): array {
    foreach ( $nodes as $node ) {
        if ( ! is_array( $node ) ) {
            continue;
        }
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
        foreach ( [ 'background_image', 'background_overlay_image', 'background_hover_image' ] as $key ) {
            $image = $settings[ $key ] ?? null;
            $url = is_array( $image ) ? trim( (string) ( $image['url'] ?? '' ) ) : trim( (string) $image );
            if ( $url !== '' ) {
                $urls[] = $url;
            }
        }
        if ( is_array( $node['elements'] ?? null ) ) {
            wpae_llm_collect_background_image_urls( $node['elements'], $urls );
        }
    }

    return wpae_llm_sanitize_background_image_urls( $urls );
}

function wpae_llm_normalize_hero_composition( array $elements, int &$changed = 0, string $message = '', bool $clean_trusted_source = false, int $image_variant = -1, array $used_image_urls = [] ): array {
    $to_alignment = static function ( $value ): ?string {
        $value = strtolower( trim( (string) $value ) );
        if ( in_array( $value, [ 'left', 'center', 'right' ], true ) ) {
            return $value;
        }
        return [ 'flex-start' => 'left', 'flex-end' => 'right' ][ $value ] ?? null;
    };
    $to_flex_alignment = static function ( string $alignment ): string {
        return [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $alignment ] ?? 'flex-start';
    };
    $is_white = static function ( string $color ): bool {
        return in_array( strtolower( trim( $color ) ), [ '#fff', '#ffffff', 'white', 'rgb(255, 255, 255)', 'rgba(255, 255, 255, 1)' ], true );
    };
    $has_light_text = static function ( array $nodes ) use ( &$has_light_text, $is_white ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            foreach ( [ 'title_color', 'text_color', 'button_text_color', 'icon_color' ] as $key ) {
                if ( $is_white( trim( (string) ( $settings[ $key ] ?? '' ) ) ) ) {
                    return true;
                }
            }
            if ( $has_light_text( (array) ( $node['elements'] ?? [] ) ) ) {
                return true;
            }
        }
        return false;
    };
    $has_background_image = static function ( array $settings ): bool {
        foreach ( [ 'background_image', 'background_overlay_image', 'background_hover_image' ] as $key ) {
            $image = $settings[ $key ] ?? null;
            if ( is_array( $image ) && trim( (string) ( $image['url'] ?? '' ) ) !== '' ) {
                return true;
            }
            if ( is_string( $image ) && trim( $image ) !== '' ) {
                return true;
            }
        }
        return false;
    };
    $has_text_content = static function ( array $nodes ) use ( &$has_text_content ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( ( $node['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $node['widgetType'] ?? '' ) ), [ 'heading', 'text-editor', 'button', 'icon-box', 'icon-list' ], true ) ) {
                return true;
            }
            if ( $has_text_content( (array) ( $node['elements'] ?? [] ) ) ) {
                return true;
            }
        }
        return false;
    };
    $has_media = static function ( array $nodes ) use ( &$has_media, $has_background_image ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            if ( ( $node['elType'] ?? '' ) === 'container' && $has_background_image( $settings ) ) {
                return true;
            }
            if ( ( $node['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $node['widgetType'] ?? '' ) ), [ 'image', 'video', 'video-playlist' ], true ) ) {
                return true;
            }
            if ( $has_media( (array) ( $node['elements'] ?? [] ) ) ) {
                return true;
            }
        }
        return false;
    };
    $find_text_alignment = static function ( array $nodes ) use ( &$find_text_alignment, $to_alignment ): ?string {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true ) ) {
                continue;
            }
            $element_type = (string) ( $node['elType'] ?? '' );
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            if ( $element_type === 'widget' && in_array( $widget_type, [ 'heading', 'text-editor', 'button', 'icon-box', 'icon-list' ], true ) ) {
                $widget_alignment = $to_alignment( $settings['align'] ?? '' );
                if ( $widget_alignment !== null ) {
                    return $widget_alignment;
                }
            }
            $nested_alignment = $find_text_alignment( (array) ( $node['elements'] ?? [] ) );
            if ( $nested_alignment !== null ) {
                return $nested_alignment;
            }
        }
        return null;
    };
    $find_alignment = static function ( array $nodes ) use ( &$find_alignment, $find_text_alignment, $to_alignment ): ?string {
        $text_alignment = $find_text_alignment( $nodes );
        if ( $text_alignment !== null ) {
            return $text_alignment;
        }
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true ) ) {
                continue;
            }
            $element_type = (string) ( $node['elType'] ?? '' );
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            if ( $element_type === 'container' && strtolower( (string) ( $settings['flex_direction'] ?? 'column' ) ) === 'column' ) {
                $container_alignment = $to_alignment( $settings['flex_align_items'] ?? '' );
                if ( $container_alignment !== null ) {
                    return $container_alignment;
                }
            }
            if ( $element_type === 'widget' && in_array( $widget_type, [ 'heading', 'text-editor', 'button', 'icon', 'icon-box', 'icon-list', 'image' ], true ) ) {
                $widget_alignment = $to_alignment( $settings['align'] ?? '' );
                if ( $widget_alignment !== null ) {
                    return $widget_alignment;
                }
            }
            $nested_alignment = $find_alignment( (array) ( $node['elements'] ?? [] ) );
            if ( $nested_alignment !== null ) {
                return $nested_alignment;
            }
        }
        return null;
    };
    $apply = static function ( array &$nodes, string $alignment, bool $inside_content = false, bool $photo_backed = false ) use ( &$apply, $to_flex_alignment, $has_text_content, $has_media, $has_background_image, $has_light_text ): void {
        $flex_alignment = $to_flex_alignment( $alignment );
        $align_self = $alignment === 'center' ? 'center' : ( $alignment === 'right' ? 'flex-end' : 'flex-start' );
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $element_type = (string) ( $node['elType'] ?? '' );
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $is_badge = $element_type === 'container' && is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true );
            $is_content_container = $inside_content;
            $is_photo_container = false;
            if ( $element_type === 'container' && ! $is_badge ) {
                $children = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];
                $is_photo_container = $has_background_image( $settings );
                wpae_llm_sync_native_photo_overlay_settings( $settings, $is_photo_container, $has_light_text( $children ) );
                $child_containers = count( array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) );
                $split_layout = ! $inside_content && $has_text_content( $children ) && $child_containers >= 2 && $has_media( $children );
                if ( ! $inside_content && ! $split_layout && $has_text_content( $children ) ) {
                    $is_content_container = true;
                }
                if ( $is_content_container ) {
                    $settings['flex_align_items'] = $flex_alignment;
                    $settings['flex_align_items_tablet'] = $flex_alignment;
                    $settings['flex_align_items_mobile'] = $flex_alignment;
                }
            }
            if ( $is_badge ) {
                $settings['align_self'] = $align_self;
                $settings['align_self_tablet'] = $align_self;
                $settings['align_self_mobile'] = $align_self;
                $settings['flex_align_items'] = 'center';
            } elseif ( $element_type === 'widget' && ( $inside_content || $is_content_container ) && in_array( $widget_type, [ 'heading', 'text-editor', 'button', 'icon', 'icon-box', 'icon-list', 'image' ], true ) ) {
                $settings['align'] = $alignment;
                $settings['align_tablet'] = $alignment;
                $settings['align_mobile'] = $alignment;
                $settings['align_self'] = $align_self;
                $settings['align_self_tablet'] = $align_self;
                $settings['align_self_mobile'] = $align_self;
                if ( $photo_backed ) {
                    if ( $widget_type === 'heading' ) {
                        $settings['title_color'] = '#ffffff';
                    } elseif ( $widget_type === 'text-editor' ) {
                        $settings['text_color'] = '#ffffff';
                    } elseif ( $widget_type === 'button' ) {
                        $settings['button_text_color'] = '#ffffff';
                        $settings['button_hover_text_color'] = '#ffffff';
                    } elseif ( $widget_type === 'icon' ) {
                        $settings['primary_color'] = '#ffffff';
                    } elseif ( $widget_type === 'icon-box' ) {
                        $settings['title_color'] = '#ffffff';
                        $settings['description_color'] = '#ffffff';
                    } elseif ( $widget_type === 'icon-list' ) {
                        $settings['icon_color'] = '#ffffff';
                        $settings['text_color'] = '#ffffff';
                    }
                }
            }
            $node['settings'] = $settings;
            $next_inside_content = $inside_content || $is_content_container;
            if ( is_array( $node['elements'] ?? null ) ) {
                $apply( $node['elements'], $alignment, $next_inside_content, $photo_backed || $is_photo_container );
            }
        }
        unset( $node );
    };

    $content_units = wpae_llm_content_units( $message );
    $trusted_hero_images = [
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/side-view-of-caucasian-businessman-standing-and-gi-2026-03-25-05-35-12-utc2.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/business-executive-giving-a-speech-at-conference-c-2026-03-26-11-49-31-utc-200kb.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/man-giving-presentation-to-an-audience-indoors-2026-03-10-03-17-22-utc-1.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/woman-speaking-at-event-with-audience-members-2026-03-26-22-59-42-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/confident-professional-woman-speaking-into-microph-2026-05-18-21-28-38-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/smiling-woman-speaking-at-corporate-event-2026-01-11-11-10-44-utc-200kb.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/businessman-holding-microphone-at-business-seminar-2026-01-09-08-36-06-utc-200kb.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/business-presentation-of-marketing-plan-and-data-a-2026-03-15-00-39-37-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/woman-in-lab-coat-speaking-at-conference-2026-01-23-00-14-47-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/happy-entrepreneur-communicating-with-coworkers-wh-2026-07-14-20-39-23-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/confident-businesswoman-standing-in-modern-office-2026-04-14-19-34-06-utc.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/cropped-view-of-journalists-holding-microphones-ne-2026-03-25-10-30-11-utc-200kb.jpg',
        'https://templatekit.kitprostudio.com/vocario/wp-content/uploads/sites/141/2026/07/asian-businesswoman-presenting-financial-data-to-o-2026-01-09-12-00-44-utc-200kb.jpg',
    ];
    $used_image_urls = array_values( array_unique( array_filter( array_map( 'trim', $used_image_urls ) ) ) );
    $clean_hero = static function ( array $root, array $units, int &$changed ) use ( $has_background_image, $clean_trusted_source, $trusted_hero_images, $image_variant, $message, $used_image_urls ): array {
        if ( count( $units ) < 2 ) {
            return $root;
        }

        $title = (string) array_shift( $units );
        $cta = '';
        $body = [];
        foreach ( $units as $unit ) {
            if ( wpae_llm_is_cta_copy( $unit ) ) {
                $cta = $unit;
                continue;
            }
            $body[] = $unit;
        }

        $root_settings = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
        $has_photo = $has_background_image( $root_settings );
        if ( $has_photo ) {
            if ( $clean_trusted_source && count( $trusted_hero_images ) > 1 ) {
                $background_image = is_array( $root_settings['background_image'] ?? null ) ? $root_settings['background_image'] : [];
                $current_image_url = trim( (string) ( $background_image['url'] ?? '' ) );
                $image_index = abs( $image_variant >= 0 ? $image_variant : hexdec( substr( md5( $message ), 0, 6 ) ) ) % count( $trusted_hero_images );
                $next_image_url = $trusted_hero_images[ $image_index ];
                for ( $offset = 0; $offset < count( $trusted_hero_images ); $offset++ ) {
                    $candidate_image_url = $trusted_hero_images[ ( $image_index + $offset ) % count( $trusted_hero_images ) ];
                    if ( $candidate_image_url !== $current_image_url && ! in_array( $candidate_image_url, $used_image_urls, true ) ) {
                        $next_image_url = $candidate_image_url;
                        break;
                    }
                }
                if ( $next_image_url === $current_image_url ) {
                    $next_image_url = $trusted_hero_images[ ( $image_index + 1 ) % count( $trusted_hero_images ) ];
                }
                $background_image['url'] = $next_image_url;
                $background_image['id'] = 0;
                $background_image['source'] = 'url';
                $root_settings['background_image'] = $background_image;
                $changed++;
            }
            $root_settings['_css_classes'] = function_exists( 'wpae_append_css_classes' )
                ? wpae_append_css_classes( $root_settings['_css_classes'] ?? '', [ 'wpae-photo-hero' ] )
                : trim( (string) ( $root_settings['_css_classes'] ?? '' ) . ' wpae-photo-hero' );
        }
        foreach ( [
            'width', 'width_tablet', 'width_mobile', '_element_width', '_element_width_tablet',
            '_element_width_mobile', '_element_custom_width', '_element_custom_width_tablet',
            '_element_custom_width_mobile', 'min_height_tablet', 'min_height_mobile', 'height',
            'height_tablet', 'height_mobile', 'background_overlay_image', 'background_overlay_color_b',
            'background_overlay_color_b_stop', 'background_overlay_opacity_b', 'background_hover_background',
            'background_hover_color', 'background_hover_image', 'background_video_fallback',
            'background_slideshow_gallery', 'background_overlay_video_fallback',
            'background_overlay_slideshow_gallery', 'grid_columns_grid', 'grid_columns_grid_tablet',
            'grid_columns_grid_mobile', 'grid_rows_grid', 'grid_gaps', 'grid_align_items',
        ] as $key ) {
            unset( $root_settings[ $key ] );
        }
        $root_settings['container_type'] = 'flex';
        $root_settings['content_width'] = 'full';
        $root_settings['background_background'] = 'classic';
        $root_settings['background_color'] = 'transparent';
        $root_settings['flex_direction'] = 'column';
        $root_settings['flex_direction_tablet'] = 'column';
        $root_settings['flex_direction_mobile'] = 'column';
        $root_settings['flex_wrap'] = 'nowrap';
        $root_settings['flex_wrap_tablet'] = 'nowrap';
        $root_settings['flex_wrap_mobile'] = 'nowrap';
        $root_settings['flex_justify_content'] = 'center';
        $root_settings['flex_align_items'] = 'stretch';
        $root_settings['flex_align_items_tablet'] = 'stretch';
        $root_settings['flex_align_items_mobile'] = 'stretch';
        $root_settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
        $root_settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
        $root_settings['padding'] = [ 'unit' => 'rem', 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'isLinked' => false ];
        $root_settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '2.5', 'right' => '1.25', 'bottom' => '2.5', 'left' => '1.25', 'isLinked' => false ];
        $root_settings['min_height'] = [ 'unit' => 'vh', 'size' => 72, 'sizes' => [] ];
        $root_settings['min_height_mobile'] = [ 'unit' => 'vh', 'size' => 78, 'sizes' => [] ];
        if ( $has_photo ) {
            $root_settings['background_overlay_background'] = 'classic';
            $root_settings['background_overlay_color'] = '#000000';
            $root_settings['background_overlay_opacity'] = [ 'unit' => 'px', 'size' => 0.42, 'sizes' => [] ];
        }

        $root_id = (string) ( $root['id'] ?? 'wpae-hero' );
        $badge = wpae_llm_badge_widget( $root_id . '-hero-badge', 'hero' );
        $text_color = $has_photo ? '#ffffff' : '#111827';
        $widget = static function ( string $id, string $type, array $settings ): array {
            return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
        };
        $shell_settings = [
            '_css_classes' => 'wpae-generated-content-shell wpae-hero-content-shell',
            'content_width' => 'full',
            'flex_direction' => 'column',
            'flex_direction_mobile' => 'column',
            'flex_wrap' => 'nowrap',
            'flex_justify_content' => 'center',
            'flex_align_items' => 'flex-start',
            'flex_align_items_mobile' => 'flex-start',
            'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ],
            'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ],
            'background_background' => 'classic',
            'background_color' => 'transparent',
            'width' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
            'width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
            '_element_width' => 'initial',
            '_element_width_mobile' => 'initial',
            '_element_custom_width' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
            '_element_custom_width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
            'custom_css' => 'selector { width: min(100%, 54rem); max-width: 100%; align-self: flex-start; } @media (max-width: 767px) { selector { width: 100%; } }',
        ];
        $heading_settings = [
            'title' => $title,
            'header_size' => 'h1',
            'align' => 'left',
            'align_mobile' => 'left',
            'title_color' => $text_color,
            'typography_typography' => 'custom',
            'typography_font_size' => [ 'unit' => 'rem', 'size' => 3.8 ],
            'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 3.1 ],
            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 2.2 ],
            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.05 ],
            'typography_line_height_tablet' => [ 'unit' => 'em', 'size' => 1.08 ],
            'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.1 ],
            'typography_font_weight' => '700',
            'custom_css' => 'selector .elementor-heading-title { text-wrap: balance; }',
        ];
        if ( $has_photo ) {
            $heading_settings['_css_classes'] = 'wpae-photo-hero-text';
        }
        $shell_elements = [ $widget( $root_id . '-hero-heading', 'heading', $heading_settings ) ];
        if ( ! empty( $body ) ) {
            $body_text = implode( '. ', $body );
            if ( $body_text !== '' && ! preg_match( '/[.!?]$/u', $body_text ) ) {
                $body_text .= '.';
            }
            $shell_elements[] = $widget( $root_id . '-hero-copy', 'text-editor', [
                'editor' => $body_text,
                'align' => 'left',
                'align_mobile' => 'left',
                'text_color' => $text_color,
                'typography_typography' => 'custom',
                'typography_font_size' => [ 'unit' => 'rem', 'size' => 1.15 ],
                'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1 ],
                'typography_line_height' => [ 'unit' => 'em', 'size' => 1.5 ],
            ] );
            if ( $has_photo ) {
                $shell_elements[ count( $shell_elements ) - 1 ]['settings']['_css_classes'] = 'wpae-photo-hero-text';
            }
        }
        if ( $cta !== '' ) {
            $button_settings = [
                'text' => $cta,
                'align' => 'left',
                'align_mobile' => 'left',
                'link' => [ 'url' => '#contact' ],
                'button_text_color' => '#ffffff',
                'button_hover_text_color' => '#ffffff',
                'background_color' => '',
            ];
            wpae_llm_normalize_generated_button_settings( $button_settings );
            $button_settings['button_text_color'] = '#ffffff';
            $button_settings['button_hover_text_color'] = '#ffffff';
            $shell_elements[] = $widget( $root_id . '-hero-cta', 'button', $button_settings );
            if ( $has_photo ) {
                $shell_elements[ count( $shell_elements ) - 1 ]['settings']['_css_classes'] = 'wpae-photo-hero-text';
            }
        }

        $root['settings'] = $root_settings;
        $root['elements'] = [
            $badge,
            [ 'id' => $root_id . '-hero-content-shell', 'elType' => 'container', 'settings' => $shell_settings, 'elements' => $shell_elements ],
        ];
        $changed++;
        return $root;
    };

    foreach ( $elements as $index => $root ) {
        if ( ! is_array( $root ) || ( $root['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        if ( $clean_trusted_source && count( $content_units ) >= 2 ) {
            $elements[ $index ] = $clean_hero( $root, $content_units, $changed );
            $root = $elements[ $index ];
        }
        $root_children = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
        $content_shell_index = null;
        $root_badges = [];
        foreach ( $root_children as $child_index => $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
            $child_classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $child_classes ) && in_array( 'wpae-generated-badge', $child_classes, true ) ) {
                $root_badges[] = $child_index;
            }
            if ( is_array( $child_classes ) && in_array( 'wpae-generated-content-shell', $child_classes, true ) ) {
                $content_shell_index = $child_index;
            }
        }
        if ( $content_shell_index !== null && ! empty( $root_badges ) ) {
            $shell = is_array( $root_children[ $content_shell_index ] ?? null ) ? $root_children[ $content_shell_index ] : [];
            $shell_children = is_array( $shell['elements'] ?? null ) ? $shell['elements'] : [];
            $badge = $root_children[ $root_badges[0] ] ?? null;
            $shell_badge = null;
            $shell_without_badges = [];
            foreach ( $shell_children as $shell_child ) {
                if ( ! is_array( $shell_child ) ) {
                    $shell_without_badges[] = $shell_child;
                    continue;
                }
                $shell_child_settings = is_array( $shell_child['settings'] ?? null ) ? $shell_child['settings'] : [];
                $shell_child_classes = preg_split( '/\s+/', trim( (string) ( $shell_child_settings['_css_classes'] ?? '' ) ) );
                if ( is_array( $shell_child_classes ) && in_array( 'wpae-generated-badge', $shell_child_classes, true ) ) {
                    $shell_badge = $shell_badge ?? $shell_child;
                    continue;
                }
                $shell_without_badges[] = $shell_child;
            }
            if ( $shell_badge === null && is_array( $badge ) ) {
                $shell_badge = $badge;
            }
            if ( is_array( $shell_badge ) ) {
                $heading_index = count( $shell_without_badges );
                foreach ( $shell_without_badges as $shell_child_index => $shell_child ) {
                    if ( is_array( $shell_child ) && ( $shell_child['elType'] ?? '' ) === 'widget' && sanitize_key( (string) ( $shell_child['widgetType'] ?? '' ) ) === 'heading' ) {
                        $heading_index = $shell_child_index;
                        break;
                    }
                }
                array_splice( $shell_without_badges, $heading_index, 0, [ $shell_badge ] );
                $shell['elements'] = $shell_without_badges;
                $new_root_children = [];
                foreach ( $root_children as $child_index => $child ) {
                    if ( in_array( $child_index, $root_badges, true ) ) {
                        continue;
                    }
                    $new_root_children[] = $child_index === $content_shell_index ? $shell : $child;
                }
                $root['elements'] = $new_root_children;
            }
        }
        $mode = $find_alignment( [ $root ] ) ?? 'left';
        $root_settings = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
        if ( $has_background_image( $root_settings ) && $has_light_text( (array) ( $root['elements'] ?? [] ) ) ) {
            $root_settings['background_overlay_background'] = 'classic';
            $root_settings['background_overlay_color'] = '#000000';
            $root_settings['background_overlay_opacity'] = [ 'unit' => 'px', 'size' => 0.42, 'sizes' => [] ];
            $root['settings'] = $root_settings;
        }
        $root_nodes = [ $root ];
        $apply( $root_nodes, $mode );
        if ( wp_json_encode( $root ) !== wp_json_encode( $root_nodes[0] ) ) {
            $changed++;
        }
        $elements[ $index ] = $root_nodes[0];
    }

    return $elements;
}

function wpae_llm_normalize_preserved_library_geometry( array $elements, int &$changed = 0 ): array {
    $read_percent_width = static function ( array $settings ): ?float {
        foreach ( [ 'width', '_element_custom_width' ] as $key ) {
            $width = $settings[ $key ] ?? null;
            if ( ! is_array( $width ) || strtolower( (string) ( $width['unit'] ?? '' ) ) !== '%' || ! is_numeric( $width['size'] ?? null ) ) {
                continue;
            }
            return max( 0, min( 100, (float) $width['size'] ) );
        }
        return null;
    };
    $has_gap = static function ( array $settings ): bool {
        foreach ( [ 'flex_gap', 'flex_gap_tablet', 'flex_gap_mobile' ] as $key ) {
            $gap = $settings[ $key ] ?? null;
            if ( ! is_array( $gap ) ) {
                continue;
            }
            foreach ( [ 'column', 'row', 'size' ] as $side ) {
                if ( is_numeric( $gap[ $side ] ?? null ) && (float) $gap[ $side ] > 0 ) {
                    return true;
                }
            }
        }
        return false;
    };
    $clear_negative_margin = static function ( array &$settings ): bool {
        $changed_margin = false;
        foreach ( [ 'margin', 'margin_tablet', 'margin_mobile', '_margin', '_margin_tablet', '_margin_mobile' ] as $key ) {
            if ( ! is_array( $settings[ $key ] ?? null ) ) {
                continue;
            }
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                if ( is_numeric( $settings[ $key ][ $side ] ?? null ) && (float) $settings[ $key ][ $side ] < 0 ) {
                    $settings[ $key ][ $side ] = '0';
                    $changed_margin = true;
                }
            }
        }
        return $changed_margin;
    };
    $walk = static function ( array &$nodes ) use ( &$walk, &$changed, $read_percent_width, $has_gap, $clear_negative_margin ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            if ( $clear_negative_margin( $settings ) ) {
                $changed++;
            }
            if ( ( $element['elType'] ?? '' ) === 'container' ) {
                $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
                $child_indexes = [];
                foreach ( $children as $child_index => $child ) {
                    if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                        $child_indexes[] = $child_index;
                    }
                }
                $direction = strtolower( (string) ( $settings['flex_direction'] ?? '' ) );
                if ( in_array( $direction, [ 'row', 'row-reverse' ], true ) && count( $child_indexes ) === 2 ) {
                    $widths = [];
                    foreach ( $child_indexes as $child_index ) {
                        $widths[] = $read_percent_width( is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [] );
                    }
                    $target_total = $has_gap( $settings ) ? 98.0 : 100.0;
                    if ( $widths[0] === null && $widths[1] === null ) {
                        $widths = [ $target_total / 2, $target_total / 2 ];
                    } elseif ( $widths[0] === null && $widths[1] !== null ) {
                        $widths[0] = max( 1, $target_total - $widths[1] );
                    } elseif ( $widths[1] === null && $widths[0] !== null ) {
                        $widths[1] = max( 1, $target_total - $widths[0] );
                    } elseif ( ( $widths[0] + $widths[1] ) > $target_total ) {
                        $scale = $target_total / max( 1, $widths[0] + $widths[1] );
                        $widths[0] *= $scale;
                        $widths[1] *= $scale;
                    }
                    $settings['flex_wrap'] = 'nowrap';
                    $settings['flex_wrap_tablet'] = 'wrap';
                    $settings['flex_wrap_mobile'] = 'wrap';
                    foreach ( $child_indexes as $width_index => $child_index ) {
                        $child_settings = is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [];
                        $before_child = wp_json_encode( $child_settings );
                        wpae_llm_set_variant_container_width( $child_settings, (float) $widths[ $width_index ] );
                        $child_settings['width_tablet'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
                        $child_settings['_element_width_tablet'] = 'initial';
                        $child_settings['_element_custom_width_tablet'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
                        $child_settings['flex_shrink'] = 1;
                        $child_settings['_flex_shrink'] = 1;
                        $child_settings['flex_shrink_tablet'] = 1;
                        $child_settings['flex_shrink_mobile'] = 1;
                        $children[ $child_index ]['settings'] = $child_settings;
                        if ( $before_child !== wp_json_encode( $child_settings ) ) {
                            $changed++;
                        }
                    }
                    $element['elements'] = $children;
                }
            }
            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_build_pricing_pair_layout( array $template_elements, array $pairs, int &$changed ): array {
    if ( count( $pairs ) < 2 ) {
        return [];
    }

    $root = null;
    foreach ( $template_elements as $element ) {
        if ( is_array( $element ) && ( $element['elType'] ?? '' ) === 'container' ) {
            $root = $element;
            break;
        }
    }
    if ( ! is_array( $root ) ) {
        $root = [ 'id' => 'wpae-pricing-root', 'elType' => 'container', 'settings' => [], 'elements' => [] ];
    }

    $widget = static function ( string $id, string $type, array $settings = [] ): array {
        return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
    };
    $cards = [];
    foreach ( array_slice( $pairs, 0, 8 ) as $index => $pair ) {
        $label = trim( sanitize_text_field( (string) ( $pair['label'] ?? '' ) ) );
        $content = trim( sanitize_text_field( (string) ( $pair['content'] ?? '' ) ) );
        if ( $label === '' || $content === '' ) {
            continue;
        }
        $price = $content;
        $description = '';
        if ( preg_match( '/^\s*(\d[\d\s]*(?:₸|\$|€|₽)?)\s*(?:[,.;:]\s*(.*))?$/u', $content, $match ) ) {
            $price = trim( (string) ( $match[1] ?? $content ) );
            $description = trim( (string) ( $match[2] ?? '' ) );
        }
        $card_id = 'wpae-pricing-card-' . (string) ( $index + 1 );
        $card_elements = [
            $widget( $card_id . '-label', 'heading', [
                'title' => $label,
                'header_size' => 'h4',
                '_css_classes' => 'wpae-pricing-tier-label',
                'typography_typography' => 'custom',
                'typography_font_size' => [ 'unit' => 'rem', 'size' => 1.25 ],
                'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 1.15 ],
                'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1.05 ],
                'typography_line_height' => [ 'unit' => 'em', 'size' => 1.2 ],
                'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.15 ],
                'typography_font_weight' => '700',
            ] ),
            $widget( $card_id . '-price', 'heading', [
                'title' => $price,
                'header_size' => 'h3',
                '_css_classes' => 'wpae-pricing-price',
                'typography_typography' => 'custom',
                'typography_font_size' => [ 'unit' => 'rem', 'size' => 2.25 ],
                'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 2 ],
                'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1.75 ],
                'typography_line_height' => [ 'unit' => 'em', 'size' => 1.05 ],
                'typography_line_height_tablet' => [ 'unit' => 'em', 'size' => 1.05 ],
                'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.1 ],
                'typography_font_weight' => '800',
            ] ),
        ];
        if ( $description !== '' ) {
            $card_elements[] = $widget( $card_id . '-description', 'text-editor', [
                'editor' => $description,
                'typography_typography' => 'custom',
                'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 1 ],
                'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 0.95 ],
                'typography_line_height' => [ 'unit' => 'em', 'size' => 1.45 ],
                'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.4 ],
                'text_color' => '#667085',
            ] );
        }
        $card = [
            'id' => $card_id,
            'elType' => 'container',
            'settings' => [
                '_css_classes' => 'wpae-pricing-card',
                'container_type' => 'flex',
                'content_width' => 'full',
                'flex_direction' => 'column',
                'flex_wrap' => 'nowrap',
                'flex_align_items' => 'flex-start',
                'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                'flex_gap_mobile' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                'background_background' => 'classic',
                'background_color' => '#ffffff',
                'border_border' => 'solid',
                'border_color' => '#e5e7eb',
                'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                'border_radius' => [ 'unit' => 'rem', 'size' => 0.75, 'isLinked' => true ],
                'padding' => [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ],
                'padding_mobile' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ],
            ],
            'elements' => $card_elements,
        ];
        wpae_llm_set_flexible_bento_container_width( $card['settings'], 31 );
        $cards[] = $card;
    }
    if ( count( $cards ) < 2 ) {
        return [];
    }

    $settings = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
    $settings['container_type'] = 'flex';
    $settings['content_width'] = 'full';
    $settings['flex_direction'] = 'column';
    $settings['flex_wrap'] = 'nowrap';
    $settings['flex_justify_content'] = 'flex-start';
    $settings['flex_align_items'] = 'stretch';
    $settings['flex_gap'] = [ 'column' => '1.5', 'row' => '1.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.5' ];
    $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
    $settings['padding'] = [ 'unit' => 'rem', 'top' => '2', 'right' => '1.5', 'bottom' => '2', 'left' => '1.5', 'isLinked' => false ];
    $settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1', 'bottom' => '1.5', 'left' => '1', 'isLinked' => false ];
    $settings['_css_classes'] = trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-pricing-composition' );
    $root['settings'] = $settings;
    $grid = wpae_llm_bento_grid( 'wpae-pricing-grid', $cards );
    $grid['settings']['container_type'] = 'flex';
    $root['elements'] = [
        wpae_llm_badge_widget( 'wpae-pricing-badge', 'pricing' ),
        $widget( 'wpae-pricing-heading', 'heading', [
            'title' => 'Тарифы',
            'header_size' => 'h2',
            'typography_typography' => 'custom',
            'typography_font_size' => [ 'unit' => 'rem', 'size' => 2.5 ],
            'typography_font_size_tablet' => [ 'unit' => 'rem', 'size' => 2.1 ],
            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1.75 ],
            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.1 ],
            'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.15 ],
            'typography_font_weight' => '700',
        ] ),
        $grid,
    ];
    $changed++;
    return [ $root ];
}

function wpae_llm_apply_library_template( array $template_elements, string $message, string $archetype, int &$changed, bool $clear_unrequested_copy = false ): array {
    $applied = false;
    $has_content_widget = static function ( array $elements ) use ( &$has_content_widget ): bool {
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( ( $element['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $element['widgetType'] ?? '' ) ), [ 'icon-box', 'testimonial', 'text-editor' ], true ) ) {
                return true;
            }
            if ( is_array( $element['elements'] ?? null ) && $has_content_widget( $element['elements'] ) ) {
                return true;
            }
        }
        return false;
    };
    $clone_with_ids = static function ( array $element, string $seed ) use ( &$clone_with_ids ): array {
        $old_id = (string) ( $element['id'] ?? 'element' );
        $element['id'] = substr( md5( $seed . '|' . $old_id ), 0, 7 );
        if ( is_array( $element['elements'] ?? null ) ) {
            $children = [];
            foreach ( $element['elements'] as $index => $child ) {
                $children[] = is_array( $child ) ? $clone_with_ids( $child, $seed . '|' . (string) $index ) : $child;
            }
            $element['elements'] = $children;
        }
        return $element;
    };
    if ( $archetype === 'carousel' && count( $template_elements ) === 1 ) {
        $carousel = is_array( $template_elements[0] ) ? $clone_with_ids( $template_elements[0], 'library-carousel' ) : [];
        if ( (string) ( $carousel['elType'] ?? '' ) === 'widget' && sanitize_key( (string) ( $carousel['widgetType'] ?? '' ) ) === 'image-carousel' ) {
            $settings = is_array( $carousel['settings'] ?? null ) ? $carousel['settings'] : [];
            $slides = [];
            foreach ( (array) ( $settings['carousel'] ?? [] ) as $slide ) {
                if ( ! is_array( $slide ) ) {
                    continue;
                }
                $url = esc_url_raw( (string) ( $slide['url'] ?? '' ) );
                $attachment_id = absint( $slide['id'] ?? 0 );
                if ( $url === '' && $attachment_id < 1 ) {
                    continue;
                }
                $slide['url'] = $url;
                $slides[] = $slide;
            }
            if ( count( $slides ) >= 2 ) {
                $settings['carousel'] = $slides;
                $settings['slides_to_show'] = '1';
                $settings['slides_to_show_tablet'] = '3';
                $settings['slides_to_show_mobile'] = '2';
                $settings['navigation'] = (string) ( $settings['navigation'] ?? 'none' );
                $settings['autoplay'] = (string) ( $settings['autoplay'] ?? 'yes' );
                $settings['infinite'] = (string) ( $settings['infinite'] ?? 'yes' );
                $settings['_css_classes'] = wpae_append_css_classes( $settings['_css_classes'] ?? '', [ 'wpae-library-carousel' ] );
                $carousel['settings'] = $settings;
                $wrapper_id = 'library-carousel-' . substr( md5( (string) ( $carousel['id'] ?? 'carousel' ) ), 0, 7 );
                $requested_content = trim( sanitize_text_field( $message ) );
                $heading_text = 'Партнёры проекта';
                if ( preg_match( '/^\s*([^:]{3,80}):/u', $requested_content, $heading_match ) ) {
                    $heading_text = trim( sanitize_text_field( (string) ( $heading_match[1] ?? '' ) ) );
                }
                $partner_names = [];
                if ( preg_match( '/(?:партн[её]ры|логотипы)\s*:\s*([^.!?]+)/iu', $requested_content, $partner_match ) ) {
                    $partner_text = trim( (string) ( $partner_match[1] ?? '' ) );
                    $partner_text = preg_replace( '/\s+и\s+/iu', ', ', $partner_text );
                    foreach ( preg_split( '/,\s*/u', (string) $partner_text, -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $partner_name ) {
                        $partner_name = trim( sanitize_text_field( (string) $partner_name ) );
                        if ( $partner_name !== '' ) {
                            $partner_names[ wpae_llm_normalize_content_text( $partner_name ) ] = $partner_name;
                        }
                    }
                }
                $content_elements = [
                    [
                        'id' => 'library-carousel-heading',
                        'elType' => 'widget',
                        'widgetType' => 'heading',
                        'settings' => [ 'title' => $heading_text, 'header_size' => 'h2' ],
                        'elements' => [],
                    ],
                    [
                        'id' => 'library-carousel-copy',
                        'elType' => 'widget',
                        'widgetType' => 'text-editor',
                        'settings' => [ 'editor' => $requested_content ],
                        'elements' => [],
                    ],
                ];
                if ( count( $partner_names ) >= 2 ) {
                    $partner_labels = [];
                    foreach ( array_slice( array_values( $partner_names ), 0, 8 ) as $partner_index => $partner_name ) {
                        $partner_labels[] = [
                            'id' => 'library-carousel-partner-' . (string) ( $partner_index + 1 ),
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => [
                                'title' => $partner_name,
                                'header_size' => 'h5',
                                '_css_classes' => 'wpae-carousel-partner-label',
                                'background_background' => 'classic',
                                'background_color' => '#f7f7f5',
                                'border_border' => 'solid',
                                'border_color' => '#6b7280',
                                'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                                'border_radius' => [ 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'size' => 999, 'isLinked' => true ],
                                'padding' => [ 'unit' => 'rem', 'top' => '0.55', 'right' => '1', 'bottom' => '0.55', 'left' => '1', 'isLinked' => false ],
                                'margin' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                            ],
                            'elements' => [],
                        ];
                    }
                    $content_elements[] = [
                        'id' => 'library-carousel-partners',
                        'elType' => 'container',
                        'settings' => [
                            '_css_classes' => 'wpae-carousel-partner-rail',
                            'container_type' => 'flex',
                            'content_width' => 'full',
                            'flex_direction' => 'row',
                            'flex_wrap' => 'wrap',
                            'flex_justify_content' => 'flex-start',
                            'flex_align_items' => 'center',
                            'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                            'flex_gap_mobile' => [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ],
                            'background_background' => 'classic',
                            'background_color' => 'transparent',
                            'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                            'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                        ],
                        'elements' => $partner_labels,
                    ];
                }
                $template_elements = [
                    [
                        'id' => $wrapper_id,
                        'elType' => 'container',
                        'settings' => [
                            '_css_classes' => 'wpae-generated-carousel',
                            'container_type' => 'flex',
                            'content_width' => 'full',
                            'flex_direction' => 'column',
                            'flex_wrap' => 'nowrap',
                            'flex_justify_content' => 'flex-start',
                            'flex_align_items' => 'stretch',
                            'flex_gap' => [ 'column' => '1.5', 'row' => '1.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.5' ],
                            'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ],
                            'background_background' => 'classic',
                            'background_color' => 'transparent',
                            'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                            'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                        ],
                        'elements' => array_merge( $content_elements, [ $carousel ] ),
                    ],
                ];
                $changed++;
                return $template_elements;
            }
        }
    }
    if ( count( $template_elements ) > 1 ) {
        $root_patterns = [
            'hero' => '/hero|banner|header|первый|обложк/iu',
            'benefits' => '/benefit|feature|why\s+choose(?:\s+us)?|почему|опор|преимуществ|выгод/iu',
            'pricing' => '/pricing|price|тариф|стоимост|пакет/iu',
            'testimonials' => '/testimonial|review|отзыв|клиент/iu',
            'team' => '/team|команд|сотрудник|специалист/iu',
            'about' => '/about|о\s+нас|о\s+компани/iu',
            'faq' => '/faq|question|вопрос|ответ/iu',
            'process' => '/process|step|этап|шаг/iu',
            'cta' => '/cta|contact|контакт|заявк/iu',
            'portfolio' => '/portfolio|case|project|кейс|проект/iu',
        ];
        $pattern = $root_patterns[ $archetype ] ?? '';
        $best_index = 0;
        $best_score = -1;
        foreach ( $template_elements as $index => $root ) {
            if ( ! is_array( $root ) ) {
                continue;
            }
            $title = (string) ( $root['settings']['_title'] ?? '' );
            $score = $pattern !== '' && preg_match( $pattern, $title ) ? 30 : 0;
            if ( $pattern !== '' && preg_match( $pattern, wpae_llm_collect_action_content( [ $root ] ) ) ) {
                $score += 5;
            }
            if ( wpae_llm_count_widgets( [ $root ] ) >= 3 ) {
                $score++;
            }
            if ( $score > $best_score ) {
                $best_index = (int) $index;
                $best_score = $score;
            }
        }
        // Reject a multi-section kit when none of its roots names the requested archetype.
        if ( $best_score < 30 ) {
            return [];
        }
        $template_elements = [ $template_elements[ $best_index ] ];
    }
    if ( $archetype === 'mega_menu' ) {
        $navigation = wpae_llm_extract_navigation_content( $message );
        $navigation_items = (array) ( $navigation['items'] ?? [] );
        $navigation_cta = trim( (string) ( $navigation['cta'] ?? '' ) );
        $template_elements = array_map( static fn( $element ): array => is_array( $element ) ? $clone_with_ids( $element, 'library-mega-menu' ) : [], $template_elements );
        $apply_navigation = static function ( array &$elements ) use ( &$apply_navigation, $navigation_items, $navigation_cta, &$changed ): void {
            foreach ( $elements as &$element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                if ( $widget_type === 'mega-menu' && ! empty( $navigation_items ) ) {
                    $menu_items = is_array( $settings['menu_items'] ?? null ) ? $settings['menu_items'] : [];
                    $next_items = [];
                    foreach ( $navigation_items as $index => $item_title ) {
                        $menu_item = is_array( $menu_items[ $index ] ?? null ) ? $menu_items[ $index ] : [ '_id' => substr( md5( 'library-mega-menu-item-' . (string) $index ), 0, 7 ) ];
                        $menu_item['item_title'] = $item_title;
                        if ( $index === 1 ) {
                            $menu_item['item_dropdown_content'] = 'yes';
                        }
                        $next_items[] = $menu_item;
                    }
                    if ( wp_json_encode( $menu_items ) !== wp_json_encode( $next_items ) ) {
                        $settings['menu_items'] = $next_items;
                        $changed++;
                    }
                } elseif ( $widget_type === 'button' && $navigation_cta !== '' && (string) ( $settings['text'] ?? '' ) !== $navigation_cta ) {
                    $settings['text'] = $navigation_cta;
                    $changed++;
                }
                $element['settings'] = $settings;
                if ( is_array( $element['elements'] ?? null ) ) {
                    $apply_navigation( $element['elements'] );
                }
            }
            unset( $element );
        };
        $apply_navigation( $template_elements );
        wpae_llm_clear_unrequested_library_copy( $template_elements, $message, $changed );
        return $template_elements;
    }
    if ( in_array( $archetype, [ 'portfolio', 'image-box' ], true ) && wpae_llm_apply_library_image_box_content( $template_elements, $message, $changed ) ) {
        wpae_llm_clear_unrequested_library_copy( $template_elements, $message, $changed );
        return $template_elements;
    }
    $pairs = wpae_llm_extract_labeled_content( $message );
    if ( $archetype === 'pricing' && count( $pairs ) >= 2 ) {
        $pricing_layout = wpae_llm_build_pricing_pair_layout( $template_elements, $pairs, $changed );
        if ( ! empty( $pricing_layout ) ) {
            return $pricing_layout;
        }
    }
    if ( $archetype === 'team' && count( $pairs ) < 2 ) {
        $team_pairs = wpae_llm_extract_team_content( $message );
        if ( count( $team_pairs ) >= 2 ) {
            $pairs = $team_pairs;
        }
    }
    if ( count( $pairs ) < 2 ) {
        $missing = wpae_llm_extract_requested_content( $message );
        if ( empty( $missing ) ) {
            return [];
        }
        if ( wpae_llm_apply_library_narrative_content( $template_elements, $missing, $changed, true ) ) {
            wpae_llm_clear_unrequested_library_copy( $template_elements, $message, $changed );
            return $template_elements;
        }
        wpae_llm_apply_fallback_content( $template_elements, $missing, $archetype, $changed );
        if ( empty( $missing ) ) {
            wpae_llm_clear_unrequested_library_copy( $template_elements, $message, $changed );
        }
        return empty( $missing ) ? $template_elements : [];
    }
    $walk = static function ( array &$elements ) use ( &$walk, &$pairs, &$applied, &$changed, &$has_content_widget, $archetype, $clone_with_ids ): void {
        if ( $applied ) {
            return;
        }
        foreach ( $elements as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
            $card_indexes = [];
            foreach ( $children as $index => $child ) {
                if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' && $has_content_widget( is_array( $child['elements'] ?? null ) ? $child['elements'] : [] ) ) {
                    $card_indexes[] = $index;
                }
            }
            if ( count( $card_indexes ) >= 2 ) {
                $target_count = max( 2, count( $pairs ) );
                if ( count( $card_indexes ) < $target_count ) {
                    $source_index = (int) $card_indexes[ count( $card_indexes ) - 1 ];
                    $insert_at = $source_index + 1;
                    $source_card = $children[ $source_index ];
                    while ( count( $card_indexes ) < $target_count ) {
                        $clone_position = count( $card_indexes ) + 1;
                        array_splice( $children, $insert_at, 0, [ $clone_with_ids( $source_card, 'library-' . $archetype . '-' . (string) $clone_position ) ] );
                        $card_indexes[] = $insert_at;
                        $insert_at++;
                        $changed++;
                    }
                }
                $next_children = [];
                $card_position = 0;
                $group_applied = 0;
                foreach ( $children as $index => $child ) {
                    if ( ! in_array( $index, $card_indexes, true ) ) {
                        $next_children[] = $child;
                        continue;
                    }
                    if ( $card_position >= $target_count ) {
                        $changed++;
                        continue;
                    }
                    $pair = $pairs[ $card_position ] ?? null;
                    $child_elements = is_array( $child['elements'] ?? null ) ? $child['elements'] : [];
                    if ( is_array( $pair ) && wpae_llm_apply_library_pair_to_widgets( $child_elements, $pair, $changed, $archetype ) ) {
                        $child['elements'] = $child_elements;
                        $group_applied++;
                    }
                    $next_children[] = $child;
                    $card_position++;
                }
                if ( $group_applied > 0 ) {
                    $element['elements'] = $next_children;
                    $applied = true;
                    return;
                }
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
                if ( $applied ) {
                    return;
                }
            }
        }
        unset( $element );
    };
    $walk( $template_elements );
    if ( ! $applied ) {
        $has_layout_content_widget = static function ( array $elements ) use ( &$has_layout_content_widget ): bool {
            foreach ( $elements as $element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                if ( ( $element['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $element['widgetType'] ?? '' ) ), [ 'heading', 'icon-box', 'icon-list', 'counter', 'testimonial', 'text-editor' ], true ) ) {
                    return true;
                }
                if ( is_array( $element['elements'] ?? null ) && $has_layout_content_widget( $element['elements'] ) ) {
                    return true;
                }
            }
            return false;
        };
        $layout_walk = static function ( array &$elements ) use ( &$layout_walk, &$pairs, &$applied, &$changed, &$has_layout_content_widget, $archetype, $clone_with_ids ): void {
            if ( $applied ) {
                return;
            }
            foreach ( $elements as &$element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
                $zone_indexes = [];
                foreach ( $children as $index => $child ) {
                    if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' && $has_layout_content_widget( (array) ( $child['elements'] ?? [] ) ) ) {
                        $zone_indexes[] = $index;
                    }
                }
                if ( count( $zone_indexes ) >= 2 ) {
                    $target_count = max( 2, count( $pairs ) );
                    if ( count( $zone_indexes ) < $target_count ) {
                        $source_index = (int) $zone_indexes[ count( $zone_indexes ) - 1 ];
                        $insert_at = $source_index + 1;
                        $source_zone = $children[ $source_index ];
                        while ( count( $zone_indexes ) < $target_count ) {
                            $clone_position = count( $zone_indexes ) + 1;
                            array_splice( $children, $insert_at, 0, [ $clone_with_ids( $source_zone, 'library-' . $archetype . '-zone-' . (string) $clone_position ) ] );
                            $zone_indexes[] = $insert_at;
                            $insert_at++;
                            $changed++;
                        }
                    }
                    $zone_position = 0;
                    $group_applied = 0;
                    foreach ( $zone_indexes as $index ) {
                        if ( $zone_position >= $target_count ) {
                            break;
                        }
                        $pair = $pairs[ $zone_position ] ?? null;
                        $child_elements = is_array( $children[ $index ]['elements'] ?? null ) ? $children[ $index ]['elements'] : [];
                        if ( is_array( $pair ) && wpae_llm_apply_library_pair_to_widgets( $child_elements, $pair, $changed, $archetype ) ) {
                            $children[ $index ]['elements'] = $child_elements;
                            $group_applied++;
                        }
                        $zone_position++;
                    }
                    if ( $group_applied >= 2 ) {
                        $element['elements'] = $children;
                        $applied = true;
                        return;
                    }
                }
                if ( is_array( $element['elements'] ?? null ) ) {
                    $layout_walk( $element['elements'] );
                    if ( $applied ) {
                        return;
                    }
                }
            }
            unset( $element );
        };
        $layout_walk( $template_elements );
    }
    if ( ! $applied ) {
        return [];
    }

    $missing = (array) ( wpae_llm_content_fidelity( $message, $template_elements )['missing'] ?? [] );
    if ( ! empty( $missing ) ) {
        wpae_llm_apply_fallback_content( $template_elements, $missing, $archetype, $changed );
    }
    wpae_llm_clear_unrequested_library_copy( $template_elements, $message, $changed );
    if ( $archetype === 'process' ) {
        $process_heading_count = 0;
        $process_media_count = 0;
        $collect_process_quality = static function ( array $nodes ) use ( &$collect_process_quality, &$process_heading_count, &$process_media_count ): void {
            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) ) {
                    continue;
                }
                if ( ( $node['elType'] ?? '' ) === 'widget' ) {
                    $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
                    $node_settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
                    if ( $widget_type === 'heading' && trim( wp_strip_all_tags( (string) ( $node_settings['title'] ?? '' ) ) ) !== '' ) {
                        $process_heading_count++;
                    }
                    if ( in_array( $widget_type, [ 'image', 'image-carousel', 'gallery' ], true ) ) {
                        $process_media_count++;
                    }
                }
                if ( is_array( $node['elements'] ?? null ) ) {
                    $collect_process_quality( $node['elements'] );
                }
            }
        };
        $collect_process_quality( $template_elements );
        $required_process_headings = max( 2, min( 4, count( $pairs ) ) );
        if ( $process_media_count > 0 || $process_heading_count < $required_process_headings ) {
            return [];
        }
    }
    return $template_elements;
}

function wpae_llm_invalidate_render_cache( array &$elements, int &$changed ): void {
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        foreach ( [ 'htmlCache', 'html_cache' ] as $cache_key ) {
            if ( array_key_exists( $cache_key, $element ) && $element[ $cache_key ] !== null ) {
                $element[ $cache_key ] = null;
                $changed++;
            }
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_llm_invalidate_render_cache( $element['elements'], $changed );
        }
    }
    unset( $element );
}

function wpae_llm_apply_fallback_cta( array &$elements, string $cta, int &$changed ): void {
    if ( $cta === '' ) {
        return;
    }
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( ( $element['elType'] ?? '' ) === 'widget' && ( $element['widgetType'] ?? '' ) === 'button' ) {
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            if ( (string) ( $settings['text'] ?? '' ) !== $cta ) {
                $settings['text'] = $cta;
                $element['settings'] = $settings;
                $changed++;
            }
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_llm_apply_fallback_cta( $element['elements'], $cta, $changed );
        }
    }
    unset( $element );
}

function wpae_llm_apply_fallback_faq_content( array &$elements, string $message, int &$changed ): void {
    $pairs = wpae_llm_extract_faq_content( $message );
    if ( empty( $pairs ) ) {
        $pairs = wpae_llm_extract_labeled_content( $message );
    }
    if ( empty( $pairs ) ) {
        return;
    }
    foreach ( $elements as &$root ) {
        if ( ! is_array( $root ) || ! is_array( $root['elements'] ?? null ) ) {
            continue;
        }
        foreach ( $root['elements'] as &$element ) {
            if ( ! is_array( $element ) || ( $element['widgetType'] ?? '' ) !== 'accordion' ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $settings['tabs'] = [];
            foreach ( array_slice( $pairs, 0, 6 ) as $pair ) {
                $settings['tabs'][] = [ 'tab_title' => $pair['label'], 'tab_content' => $pair['content'] ];
            }
            $element['settings'] = $settings;
            $changed += count( $settings['tabs'] );
        }
        unset( $element );
    }
    unset( $root );
}

function wpae_llm_bento_card( string $id, array $elements ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            'content_width' => 'full',
            'flex_direction' => 'column',
            'background_background' => 'classic',
            'background_color' => '#ffffff',
            'border_border' => 'solid',
            'border_color' => '#e5e7eb',
            'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
            'border_radius' => [ 'unit' => 'rem', 'size' => 0.75, 'isLinked' => true ],
            'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
            'padding' => [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ],
            'padding_mobile' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ],
        ],
        'elements' => $elements,
    ];
}

function wpae_llm_bento_grid( string $id, array $elements ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            'content_width' => 'full',
            'flex_direction' => 'row',
            'flex_wrap' => 'wrap',
            'flex_justify_content' => 'space-between',
            'flex_align_items' => 'stretch',
            'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ],
            'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ],
            '_css_classes' => 'wpae-bento-grid',
            'background_background' => 'classic',
            'background_color' => 'transparent',
            'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
            'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
        ],
        'elements' => $elements,
    ];
}

function wpae_llm_process_timeline_steps( string $message ): array {
    $pairs = array_slice( wpae_llm_extract_labeled_content( $message ), 0, 6 );
    if ( count( $pairs ) >= 2 ) {
        return $pairs;
    }

    return [
        [ 'label' => 'Бриф', 'content' => 'Фиксируем цель, аудиторию и главное действие страницы.' ],
        [ 'label' => 'Структура', 'content' => 'Собираем смысловой маршрут и расставляем доказательства.' ],
        [ 'label' => 'Сборка', 'content' => 'Создаем блоки из native Elementor-элементов и задаем адаптив.' ],
        [ 'label' => 'Проверка', 'content' => 'Проверяем визуальный результат, редактируемость и следующий шаг.' ],
    ];
}

function wpae_llm_process_timeline_layout( string $message ): string {
	if ( preg_match( '/горизонталь\w*|по\s+горизонтал\w*|horizontal/iu', $message ) ) {
		return 'horizontal';
	}
	if ( preg_match( '/центр\w*|чередующ\w*|alternat\w*|center\w*/iu', $message ) ) {
		return 'alternating';
	}
	return 'left';
}

function wpae_llm_build_process_timeline( array $steps, string $id = 'wpae-process-timeline', ?string $layout = 'left' ): array {
	$layout = in_array( $layout, [ 'left', 'alternating', 'horizontal' ], true ) ? $layout : 'left';
    $widget = static function ( string $widget_id, string $type, array $settings = [] ): array {
        return [ 'id' => $widget_id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
    };
    $timeline_steps = [];
    $step_count = count( $steps );
    foreach ( array_values( $steps ) as $index => $step ) {
        $step_number = $index + 1;
        $step_label = trim( sanitize_text_field( (string) ( $step['label'] ?? '' ) ) );
        $step_copy = trim( sanitize_text_field( (string) ( $step['content'] ?? '' ) ) );
        if ( $step_label === '' ) {
            $step_label = 'Шаг ' . (string) $step_number;
        }
        if ( $step_copy === '' ) {
            $step_copy = 'Понятный следующий шаг без лишней сложности.';
        }

        $marker_elements = [
            [
                'id' => $id . '-marker-' . (string) $step_number,
                'elType' => 'container',
                'settings' => [
                    '_css_classes' => 'wpae-process-marker',
                    'container_type' => 'flex',
                    'content_width' => 'full',
                    'flex_direction' => 'row',
                    'flex_wrap' => 'nowrap',
                    'flex_justify_content' => 'center',
                    'flex_align_items' => 'center',
                    'background_background' => 'classic',
                    'background_color' => '#4460EC',
                    'border_radius' => [ 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'size' => 999, 'isLinked' => true ],
                    'width' => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                    'width_mobile' => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                    'min_height' => [ 'unit' => 'rem', 'size' => 3 ],
                    'min_height_mobile' => [ 'unit' => 'rem', 'size' => 2.5 ],
                    '_element_width' => 'initial',
                    '_element_custom_width' => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                    '_element_custom_width_mobile' => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                    '_flex_size' => 'custom',
                    '_flex_grow' => 0,
                    '_flex_shrink' => 0,
                ],
                'elements' => [
                    $widget( $id . '-marker-' . (string) $step_number . '-label', 'heading', [
                        'title' => sprintf( '%02d', $step_number ),
                        'header_size' => 'h5',
                        '_css_classes' => 'wpae-process-marker-label',
                        'title_color' => '#ffffff',
                        'align' => 'center',
                        'typography_typography' => 'custom',
                        'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                        'typography_font_weight' => '700',
                        'typography_line_height' => [ 'unit' => 'em', 'size' => 1 ],
                        'margin' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                    ] ),
                ],
            ],
        ];
        if ( $step_number < $step_count ) {
            $marker_elements[] = [
                'id' => $id . '-connector-' . (string) $step_number,
                'elType' => 'container',
                'settings' => [
                    '_css_classes' => 'wpae-process-connector',
                    'container_type' => 'flex',
                    'content_width' => 'full',
                    'flex_direction' => 'column',
                    'flex_wrap' => 'nowrap',
                    'background_background' => 'classic',
                    'background_color' => 'transparent',
                    'border_radius' => [ 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'size' => 999, 'isLinked' => true ],
                    'width' => [ 'unit' => 'px', 'size' => 2, 'sizes' => [] ],
                    'width_mobile' => [ 'unit' => 'px', 'size' => 2, 'sizes' => [] ],
                    'min_height' => [ 'unit' => 'rem', 'size' => 3.5 ],
                    '_element_width' => 'initial',
                    '_element_custom_width' => [ 'unit' => 'px', 'size' => 2, 'sizes' => [] ],
                    '_element_custom_width_mobile' => [ 'unit' => 'px', 'size' => 2, 'sizes' => [] ],
                    '_flex_size' => 'grow',
                    '_flex_grow' => 1,
                    '_flex_shrink' => 0,
                ],
                'elements' => [
                    $widget( $id . '-connector-line-' . (string) $step_number, 'html', [
                        'html' => '<span aria-hidden="true" style="display:block;width:2px;min-height:3.5rem;background:#b9c5e8;margin:0 auto;"></span>',
                    ] ),
                ],
            ];
        }

        $timeline_steps[] = [
            'id' => $id . '-step-' . (string) $step_number,
            'elType' => 'container',
            'settings' => [
                '_css_classes' => 'wpae-process-step',
                'container_type' => 'flex',
                'content_width' => 'full',
                'flex_direction' => 'row',
                'flex_direction_mobile' => 'row',
                'flex_wrap' => 'nowrap',
                'flex_wrap_mobile' => 'nowrap',
                'flex_align_items' => 'stretch',
                'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ],
                'flex_gap_mobile' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                'background_background' => 'classic',
                'background_color' => 'transparent',
                'border_border' => 'none',
                'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '1.25', 'left' => '0', 'isLinked' => false ],
                'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '1', 'left' => '0', 'isLinked' => false ],
				'_flex_size' => 'custom',
                '_flex_grow' => 0,
                '_flex_shrink' => 0,
            ],
            'elements' => [
                [
                    'id' => $id . '-rail-' . (string) $step_number,
                    'elType' => 'container',
                    'settings' => [
                        '_css_classes' => 'wpae-process-marker-column',
                        'container_type' => 'flex',
                        'content_width' => 'full',
                        'flex_direction' => 'column',
                        'flex_wrap' => 'nowrap',
                        'flex_align_items' => 'center',
                        'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                        'width' => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                        'width_mobile' => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                        '_element_width' => 'initial',
                        '_element_custom_width' => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                        '_element_custom_width_mobile' => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                        '_flex_size' => 'custom',
                        '_flex_grow' => 0,
                        '_flex_shrink' => 0,
                    ],
                    'elements' => $marker_elements,
                ],
                [
                    'id' => $id . '-content-' . (string) $step_number,
                    'elType' => 'container',
                    'settings' => [
                        '_css_classes' => 'wpae-process-content',
                        'container_type' => 'flex',
                        'content_width' => 'full',
                        'flex_direction' => 'column',
                        'flex_wrap' => 'nowrap',
                        'flex_align_items' => 'stretch',
                        'flex_gap' => [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ],
                        'flex_gap_mobile' => [ 'column' => '0.4', 'row' => '0.4', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.4' ],
                        'background_background' => 'classic',
                        'background_color' => '#ffffff',
                        'border_border' => 'solid',
                        'border_color' => '#dbe3f0',
                        'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                        'border_radius' => [ 'unit' => 'rem', 'size' => 0.75, 'isLinked' => true ],
                        'padding' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1.25', 'bottom' => '1.25', 'left' => '1.25', 'isLinked' => true ],
                        'padding_mobile' => [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1', 'left' => '0.75', 'isLinked' => true ],
						'_element_width' => 'initial',
						'_flex_size' => 'grow',
                        '_flex_grow' => 1,
                        '_flex_shrink' => 1,
                    ],
                    'elements' => [
                        $widget( $id . '-title-' . (string) $step_number, 'heading', [
                            'title' => $step_label,
                            'header_size' => 'h3',
                            'title_color' => '#111827',
                            'align' => 'left',
                            'typography_typography' => 'custom',
                            'typography_font_size' => [ 'unit' => 'rem', 'size' => 1.35 ],
                            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 1.1 ],
                            'typography_font_weight' => '700',
                            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.2 ],
                            'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.25 ],
                            'margin' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                        ] ),
                        $widget( $id . '-copy-' . (string) $step_number, 'text-editor', [
                            'editor' => $step_copy,
                            'text_color' => '#667085',
                            'align' => 'left',
                            'typography_typography' => 'custom',
                            'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 0.95 ],
                            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.5 ],
                            'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.45 ],
                            'margin' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                        ] ),
                    ],
                ],
            ],
        ];
    }

    $timeline = [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            '_css_classes' => 'wpae-process-timeline wpae-process-timeline-' . $layout,
            'container_type' => 'flex',
            'content_width' => 'full',
            'flex_direction' => 'column',
            'flex_direction_mobile' => 'column',
            'flex_wrap' => 'nowrap',
            'flex_wrap_mobile' => 'nowrap',
            'flex_align_items' => 'stretch',
            'flex_gap' => [ 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'rem', 'size' => '0' ],
            'flex_gap_mobile' => [ 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'rem', 'size' => '0' ],
            'background_background' => 'classic',
            'background_color' => 'transparent',
			'padding' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => false ],
			'padding_mobile' => [ 'unit' => 'rem', 'top' => '1', 'right' => '0.875', 'bottom' => '1.25', 'left' => '0.875', 'isLinked' => false ],
            '_flex_size' => 'custom',
            '_flex_grow' => 0,
            '_flex_shrink' => 0,
        ],
        'elements' => $timeline_steps,
    ];

	if ( $layout === 'alternating' ) {
		$spacer = static function ( string $spacer_id, string $label, string $align ) use ( $widget ): array {
			return [
				'id' => $spacer_id,
				'elType' => 'container',
				'settings' => [
					'_css_classes' => 'wpae-process-spacer',
					'container_type' => 'flex',
					'content_width' => 'full',
					'flex_direction' => 'column',
					'flex_wrap' => 'nowrap',
					'width' => [ 'unit' => '%', 'size' => 44, 'sizes' => [] ],
					'width_mobile' => [ 'unit' => '%', 'size' => 0, 'sizes' => [] ],
					'_element_width' => 'initial',
					'_element_custom_width' => [ 'unit' => '%', 'size' => 44, 'sizes' => [] ],
					'_element_custom_width_mobile' => [ 'unit' => '%', 'size' => 0, 'sizes' => [] ],
					'_flex_size' => 'custom',
					'_flex_grow' => 0,
					'_flex_shrink' => 0,
					'_flex_size_mobile' => 'custom',
					'_flex_grow_mobile' => 0,
					'_flex_shrink_mobile' => 0,
				],
				'elements' => [
					$widget( $spacer_id . '-label', 'heading', [
						'title' => $label,
						'header_size' => 'h6',
						'title_color' => '#8290a6',
						'align' => $align,
						'typography_typography' => 'custom',
						'typography_font_size' => [ 'unit' => 'rem', 'size' => 0.8 ],
						'typography_font_weight' => '700',
						'typography_letter_spacing' => [ 'unit' => 'px', 'size' => 1.2 ],
						'margin' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ],
					] ),
				],
			];
		};
		foreach ( $timeline['elements'] as $index => &$step ) {
			$step_settings = is_array( $step['settings'] ?? null ) ? $step['settings'] : [];
			$step_settings['flex_justify_content'] = 'center';
			$step_settings['flex_gap'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
			$step_settings['flex_gap_mobile'] = [ 'column' => '0.5', 'row' => '0.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.5' ];
			$step_settings['border_border'] = 'none';
			$step_settings['background_color'] = 'transparent';
			$step_settings['padding'] = [ 'unit' => 'rem', 'top' => '0.75', 'right' => '0', 'bottom' => '1.25', 'left' => '0', 'isLinked' => false ];
			$step_settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '0.5', 'right' => '0', 'bottom' => '1', 'left' => '0', 'isLinked' => false ];
			$step['settings'] = $step_settings;
			$rail = [];
			$content = [];
			foreach ( is_array( $step['elements'] ?? null ) ? $step['elements'] : [] as $step_child ) {
				$child_settings = is_array( $step_child['settings'] ?? null ) ? $step_child['settings'] : [];
				$child_classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
				if ( is_array( $child_classes ) && in_array( 'wpae-process-marker-column', $child_classes, true ) ) {
					$rail = $step_child;
				} elseif ( is_array( $child_classes ) && in_array( 'wpae-process-content', $child_classes, true ) ) {
					$content = $step_child;
				}
			}
			if ( ! $rail && is_array( $step['elements'][0] ?? null ) ) {
				$rail = $step['elements'][0];
			}
			if ( ! $content && is_array( $step['elements'][1] ?? null ) ) {
				$content = $step['elements'][1];
			}
			$content_settings = is_array( $content['settings'] ?? null ) ? $content['settings'] : [];
			wpae_llm_set_variant_container_width( $content_settings, 44 );
			$content_settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
			$content_settings['_element_custom_width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
			$content['settings'] = $content_settings;
			$rail_settings = is_array( $rail['settings'] ?? null ) ? $rail['settings'] : [];
			$rail_settings['width'] = [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ];
			$rail_settings['width_mobile'] = [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ];
			$rail_settings['_element_custom_width'] = [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ];
			$rail_settings['_element_custom_width_mobile'] = [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ];
			$rail['settings'] = $rail_settings;
			$step_label = sprintf( 'ЭТАП %02d', $index + 1 );
			$step['elements'] = $index % 2 === 0
				? [ $spacer( $id . '-spacer-' . (string) ( $index + 1 ), $step_label, 'right' ), $rail, $content ]
				: [ $content, $rail, $spacer( $id . '-spacer-' . (string) ( $index + 1 ), $step_label, 'left' ) ];
		}
		unset( $step );
	} elseif ( $layout === 'horizontal' ) {
		// Horizontal family: one shared marker rail above one row of content
		// cards, so connector lines run between the numbers outside the card
		// surfaces instead of stopping at every card padding edge.
		$timeline['settings']['flex_direction'] = 'column';
		$timeline['settings']['flex_direction_mobile'] = 'column';
		$timeline['settings']['flex_wrap'] = 'nowrap';
		$timeline['settings']['flex_wrap_mobile'] = 'nowrap';
		$timeline['settings']['flex_justify_content'] = 'flex-start';
		$timeline['settings']['flex_align_items'] = 'stretch';
		$timeline['settings']['flex_gap'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
		$timeline['settings']['flex_gap_mobile'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
		$step_width = max( 14, min( 22, 92 / max( 1, $step_count ) ) );
		$track_gap = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
		$rail_cells = [];
		$card_cells = [];
		foreach ( $timeline['elements'] as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			$rail_cell = null;
			$card_cell = null;
			foreach ( (array) ( $step['elements'] ?? [] ) as $step_child ) {
				if ( ! is_array( $step_child ) ) {
					continue;
				}
				$child_settings = is_array( $step_child['settings'] ?? null ) ? $step_child['settings'] : [];
				$child_classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
				if ( is_array( $child_classes ) && in_array( 'wpae-process-marker-column', $child_classes, true ) ) {
					$rail_cell = $step_child;
				} elseif ( is_array( $child_classes ) && in_array( 'wpae-process-content', $child_classes, true ) ) {
					$card_cell = $step_child;
				}
			}
			if ( $rail_cell === null && is_array( $step['elements'][0] ?? null ) ) {
				$rail_cell = $step['elements'][0];
			}
			if ( $card_cell === null && is_array( $step['elements'][1] ?? null ) ) {
				$card_cell = $step['elements'][1];
			}
			if ( is_array( $rail_cell ) ) {
				$rail_settings = is_array( $rail_cell['settings'] ?? null ) ? $rail_cell['settings'] : [];
				$rail_settings['flex_direction'] = 'row';
				$rail_settings['flex_direction_mobile'] = 'row';
				$rail_settings['flex_wrap'] = 'nowrap';
				$rail_settings['flex_wrap_mobile'] = 'nowrap';
				$rail_settings['flex_align_items'] = 'center';
				$rail_settings['flex_justify_content'] = 'flex-start';
				// Rail cells must share the cards' exact width budget so every
				// marker sits on top of its card instead of drifting across
				// the row; grow sizing broke that alignment (live feedback).
				wpae_llm_set_variant_container_width( $rail_settings, $step_width );
				$rail_settings['_flex_size'] = 'custom';
				$rail_settings['_flex_grow'] = 0;
				$rail_settings['_flex_shrink'] = 1;
				foreach ( (array) ( $rail_cell['elements'] ?? [] ) as &$rail_child ) {
					if ( ! is_array( $rail_child ) ) {
						continue;
					}
					$rail_child_settings = is_array( $rail_child['settings'] ?? null ) ? $rail_child['settings'] : [];
					$rail_child_classes = preg_split( '/\s+/', trim( (string) ( $rail_child_settings['_css_classes'] ?? '' ) ) );
					if ( is_array( $rail_child_classes ) && in_array( 'wpae-process-connector', $rail_child_classes, true ) ) {
						// Strip the inherited vertical 2px width so the
						// horizontal line can actually stretch between markers.
						foreach ( [ 'width', 'width_mobile', '_element_custom_width', '_element_custom_width_mobile' ] as $connector_width_key ) {
							unset( $rail_child_settings[ $connector_width_key ] );
						}
						$rail_child_settings['min_height'] = [ 'unit' => 'px', 'size' => 2 ];
						$rail_child_settings['min_height_mobile'] = [ 'unit' => 'px', 'size' => 2 ];
						$rail_child_settings['_flex_size'] = 'grow';
						$rail_child_settings['_flex_grow'] = 1;
						$rail_child_settings['_flex_shrink'] = 1;
						$rail_child_settings['flex_direction'] = 'row';
						$rail_child_settings['flex_direction_mobile'] = 'row';
						$rail_child_settings['flex_align_items'] = 'center';
						foreach ( (array) ( $rail_child['elements'] ?? [] ) as &$connector_element ) {
							if ( ! is_array( $connector_element ) || ( $connector_element['widgetType'] ?? '' ) !== 'html' ) {
								continue;
							}
							$connector_settings = is_array( $connector_element['settings'] ?? null ) ? $connector_element['settings'] : [];
							$connector_settings['html'] = '<span aria-hidden="true" style="display:block;width:100%;height:2px;min-height:0;background:#b9c5e8;margin:auto 0;"></span>';
							$connector_element['settings'] = $connector_settings;
						}
						unset( $connector_element );
						$rail_child['settings'] = $rail_child_settings;
					}
				}
				unset( $rail_child );
				$rail_cell['settings'] = $rail_settings;
				$rail_cells[] = $rail_cell;
			}
			if ( is_array( $card_cell ) ) {
				$card_settings = is_array( $card_cell['settings'] ?? null ) ? $card_cell['settings'] : [];
				$card_settings['background_background'] = 'classic';
				$card_settings['background_color'] = '#ffffff';
				$card_settings['border_border'] = 'solid';
				$card_settings['border_color'] = '#dbe3f0';
				$card_settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
				$card_settings['border_radius'] = [ 'unit' => 'rem', 'size' => 0.75, 'isLinked' => true ];
				$card_settings['padding'] = [ 'unit' => 'rem', 'top' => '1', 'right' => '0.75', 'bottom' => '1.25', 'left' => '0.75', 'isLinked' => true ];
				wpae_llm_set_variant_container_width( $card_settings, $step_width );
				// Same width budget as rail cells; no grow, so rows stay
				// geometrically identical and markers align to cards.
				$card_settings['_flex_size'] = 'custom';
				$card_settings['_flex_grow'] = 0;
				$card_settings['_flex_shrink'] = 1;
				$card_settings['_flex_shrink_mobile'] = 1;
				$card_cell['settings'] = $card_settings;
				$card_cells[] = $card_cell;
			}
		}
		$rail_row = [
			'id' => $id . '-rail',
			'elType' => 'container',
			'settings' => [
				'_css_classes' => 'wpae-process-rail',
				'container_type' => 'flex',
				'content_width' => 'full',
				'flex_direction' => 'row',
				'flex_direction_mobile' => 'row',
				'flex_wrap' => 'nowrap',
				'flex_wrap_mobile' => 'nowrap',
				'flex_justify_content' => 'flex-start',
				'flex_align_items' => 'center',
				'flex_gap' => $track_gap,
				'flex_gap_mobile' => $track_gap,
				'_element_width' => 'initial',
				'_flex_size' => 'grow',
				'_flex_grow' => 1,
				'_flex_shrink' => 1,
			],
			'elements' => $rail_cells,
		];
		$cards_row = [
			'id' => $id . '-cards',
			'elType' => 'container',
			'settings' => [
				'_css_classes' => 'wpae-process-cards',
				'container_type' => 'flex',
				'content_width' => 'full',
				'flex_direction' => 'row',
				'flex_direction_mobile' => 'column',
				'flex_wrap' => 'nowrap',
				'flex_wrap_mobile' => 'nowrap',
				'flex_justify_content' => 'flex-start',
				'flex_align_items' => 'stretch',
				'flex_gap' => $track_gap,
				'flex_gap_mobile' => $track_gap,
				'_element_width' => 'initial',
				'_flex_size' => 'grow',
				'_flex_grow' => 1,
				'_flex_shrink' => 1,
			],
			'elements' => $card_cells,
		];
		$timeline['elements'] = [
			[
				'id' => $id . '-track',
				'elType' => 'container',
				'settings' => [
					'_css_classes' => 'wpae-process-track wpae-process-track-horizontal',
					'container_type' => 'flex',
					'content_width' => 'full',
					'flex_direction' => 'column',
					'flex_direction_mobile' => 'column',
					'flex_wrap' => 'nowrap',
					'flex_wrap_mobile' => 'nowrap',
					'flex_justify_content' => 'flex-start',
					'flex_align_items' => 'stretch',
					'flex_gap' => $track_gap,
					'flex_gap_mobile' => $track_gap,
					'_element_width' => 'initial',
					'_flex_size' => 'grow',
					'_flex_grow' => 1,
					'_flex_shrink' => 1,
				],
				'elements' => [ $rail_row, $cards_row ],
			],
		];
	}

	return $timeline;
}

function wpae_llm_process_timeline_steps_from_elements( array $elements ): array {
    $read_step = static function ( array $step ) use ( &$read_step ): array {
        $title = '';
        $copy = '';
        $walk = static function ( array $nodes ) use ( &$walk, &$title, &$copy ): void {
            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) ) {
                    continue;
                }
                $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
                $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                if ( is_array( $classes ) && array_intersect( [ 'wpae-process-marker', 'wpae-process-connector', 'wpae-process-marker-column' ], $classes ) ) {
                    continue;
                }
                if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'heading' && $title === '' ) {
                    $title = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
                } elseif ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'text-editor' && $copy === '' ) {
                    $copy = trim( wp_strip_all_tags( (string) ( $settings['editor'] ?? '' ) ) );
                }
                if ( is_array( $node['elements'] ?? null ) && ( $title === '' || $copy === '' ) ) {
                    $walk( $node['elements'] );
                }
            }
        };
        $walk( [ $step ] );
        return [ 'label' => $title, 'content' => $copy ];
    };
    $found = [];
    $walk = static function ( array $nodes ) use ( &$walk, &$found, $read_step ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $is_generated_shell = is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true );
			if ( ( $node['elType'] ?? '' ) === 'container' && ! $is_generated_shell && is_array( $classes ) && ( in_array( 'wpae-process-timeline', $classes, true ) || in_array( 'wpae-bento-grid', $classes, true ) ) ) {
				foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
					if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
						$child_classes = preg_split( '/\s+/', trim( (string) ( $child['settings']['_css_classes'] ?? '' ) ) );
						if ( is_array( $child_classes ) && array_intersect( [ 'wpae-generated-badge', 'wpae-generated-content-shell' ], $child_classes ) ) {
							continue;
						}
						if ( is_array( $child_classes ) && in_array( 'wpae-process-track', $child_classes, true ) ) {
							// Horizontal tracks own a marker rail row plus a
							// cards row; vertical tracks own steps directly.
							foreach ( (array) ( $child['elements'] ?? [] ) as $track_child ) {
								if ( ! is_array( $track_child ) || ( $track_child['elType'] ?? '' ) !== 'container' ) {
									continue;
								}
								$track_child_classes = preg_split( '/\s+/', trim( (string) ( $track_child['settings']['_css_classes'] ?? '' ) ) );
								if ( is_array( $track_child_classes ) && array_intersect( [ 'wpae-process-rail' ], $track_child_classes ) ) {
									continue;
								}
								$step_containers = is_array( $track_child_classes ) && in_array( 'wpae-process-cards', $track_child_classes, true )
									? (array) ( $track_child['elements'] ?? [] )
									: [ $track_child ];
								foreach ( $step_containers as $track_step ) {
									if ( ! is_array( $track_step ) || ( $track_step['elType'] ?? '' ) !== 'container' ) {
										continue;
									}
									$track_step_data = $read_step( $track_step );
									if ( $track_step_data['label'] !== '' || $track_step_data['content'] !== '' ) {
										$found[] = $track_step_data;
									}
								}
							}
							continue;
						}
						$step = $read_step( $child );
                        if ( $step['label'] !== '' || $step['content'] !== '' ) {
                            $found[] = $step;
                        }
                    }
                }
                if ( count( $found ) >= 2 ) {
                    return;
                }
            }
            if ( is_array( $node['elements'] ?? null ) ) {
                $walk( $node['elements'] );
                if ( count( $found ) >= 2 ) {
                    return;
                }
            }
        }
    };
    $walk( $elements );
    return array_slice( $found, 0, 6 );
}

function wpae_llm_normalize_process_timeline( array $elements, string $message, int &$changed = 0 ): array {
    $message_steps = array_slice( wpae_llm_extract_labeled_content( $message ), 0, 6 );
	$layout = wpae_llm_process_timeline_layout( $message );
    $replace = static function ( array &$nodes ) use ( &$replace, $message_steps, &$changed ): bool {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( ( $element['elType'] ?? '' ) === 'container' && is_array( $classes ) && ( in_array( 'wpae-process-timeline', $classes, true ) || in_array( 'wpae-bento-grid', $classes, true ) ) ) {
                $steps = count( $message_steps ) >= 2 ? $message_steps : wpae_llm_process_timeline_steps_from_elements( [ $element ] );
                if ( count( $steps ) < 2 ) {
                    $steps = wpae_llm_process_timeline_steps( $message );
                }
                $element = wpae_llm_build_process_timeline( $steps, (string) ( $element['id'] ?? 'wpae-process-timeline' ), $layout );
                $changed++;
                return true;
            }
            if ( is_array( $element['elements'] ?? null ) && $replace( $element['elements'] ) ) {
                return true;
            }
        }
        unset( $element );
        return false;
    };
    if ( $replace( $elements ) ) {
        return $elements;
    }

    $steps = count( $message_steps ) >= 2 ? $message_steps : wpae_llm_process_timeline_steps( $message );
    $timeline = wpae_llm_build_process_timeline( $steps, 'wpae-process-timeline', $layout );
    foreach ( $elements as &$root ) {
        if ( ! is_array( $root ) || ( $root['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $root['elements'] = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
        $insert_at = array_key_last( $root['elements'] );
        foreach ( $root['elements'] as $index => $child ) {
            if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' && ( $child['widgetType'] ?? '' ) === 'button' ) {
                $insert_at = $index;
                break;
            }
        }
        if ( $insert_at === null ) {
            $root['elements'][] = $timeline;
        } else {
            array_splice( $root['elements'], (int) $insert_at, 0, [ $timeline ] );
        }
        $changed++;
        break;
    }
    unset( $root );
    return $elements;
}

function wpae_llm_enforce_process_timeline_contract( array $elements, string $message, int &$changed = 0 ): array {
    $layout = wpae_llm_process_timeline_layout( $message );
    $contains_process = static function ( array $nodes ) use ( &$contains_process ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && array_intersect( [ 'wpae-process-timeline', 'wpae-process-step', 'wpae-process-marker', 'wpae-process-content' ], $classes ) ) {
                return true;
            }
            if ( is_array( $node['elements'] ?? null ) && $contains_process( $node['elements'] ) ) {
                return true;
            }
        }
        return false;
    };
    foreach ( $elements as $index => $root ) {
        if ( ! is_array( $root ) || ( $root['elType'] ?? '' ) !== 'container' || ! $contains_process( [ $root ] ) ) {
            continue;
        }
        $steps = array_slice( wpae_llm_extract_labeled_content( $message ), 0, 6 );
        if ( count( $steps ) < 2 ) {
            $steps = wpae_llm_process_timeline_steps_from_elements( [ $root ] );
        }
        if ( count( $steps ) < 2 ) {
            $steps = wpae_llm_process_timeline_steps( $message );
        }
        $timeline = wpae_llm_build_process_timeline( $steps, (string) ( $root['id'] ?? 'wpae-process-timeline' ), $layout );
        $badge = null;
        foreach ( (array) ( $root['elements'] ?? [] ) as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            $child_classes = preg_split( '/\s+/', trim( (string) ( $child['settings']['_css_classes'] ?? '' ) ) );
            if ( is_array( $child_classes ) && in_array( 'wpae-generated-badge', $child_classes, true ) ) {
                $badge = $child;
                break;
            }
        }
        if ( $badge !== null ) {
            $timeline['elements'] = array_merge( [ $badge ], $timeline['elements'] );
        }
        $root_settings = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
        $root_classes = preg_split( '/\s+/', trim( (string) ( $root_settings['_css_classes'] ?? '' ) ) );
        $root_classes = is_array( $root_classes ) ? $root_classes : [];
        $root_classes = array_values( array_filter( $root_classes, static function ( $class ): bool {
            return $class !== 'wpae-bento-grid' && strpos( (string) $class, 'wpae-system-' ) !== 0 && $class !== 'wpae-ds';
        } ) );
        $required_classes = function_exists( 'wpae_get_design_system_required_classes' ) ? wpae_get_design_system_required_classes() : [ 'wpae-ds' ];
        $timeline_classes = preg_split( '/\s+/', trim( (string) ( $timeline['settings']['_css_classes'] ?? '' ) ) );
        $timeline['settings'] = array_merge( $root_settings, $timeline['settings'] );
        $timeline['settings']['_css_classes'] = implode( ' ', array_values( array_unique( array_merge( $root_classes, $required_classes, [ 'wpae-block' ], is_array( $timeline_classes ) ? $timeline_classes : [] ) ) ) );
        if ( isset( $root_settings['_wpae_design_system_id'] ) ) {
            $timeline['settings']['_wpae_design_system_id'] = $root_settings['_wpae_design_system_id'];
        } elseif ( function_exists( 'wpae_get_design_system_id' ) ) {
            $timeline['settings']['_wpae_design_system_id'] = wpae_get_design_system_id();
        }
        $elements[ $index ] = $timeline;
        $changed++;
    }
    return $elements;
}

function wpae_llm_normalize_generated_button_settings( array &$settings ): bool {
    $before = wp_json_encode( $settings );
    foreach ( [ 'width', 'width_tablet', 'width_mobile', 'min_width', 'max_width', '_element_width', '_element_width_tablet', '_element_width_mobile', '_element_custom_width', '_element_custom_width_tablet', '_element_custom_width_mobile' ] as $key ) {
        unset( $settings[ $key ] );
    }
    $settings['align'] = 'left';
    $settings['align_tablet'] = 'left';
    $settings['align_mobile'] = 'left';
    $settings['align_self'] = 'flex-start';
    $settings['align_self_mobile'] = 'stretch';
    $settings['typography_typography'] = 'custom';
    $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 0.95 ];
    $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 0.95 ];
    $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 0.9 ];
    $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.2 ];
    $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.2 ];
    $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.2 ];
    $settings['text_padding'] = [ 'unit' => 'rem', 'top' => '0.7', 'right' => '1.1', 'bottom' => '0.7', 'left' => '1.1', 'isLinked' => false ];
    $settings['border_radius'] = [ 'unit' => 'rem', 'top' => '0.75', 'right' => '0.75', 'bottom' => '0.75', 'left' => '0.75', 'isLinked' => true ];
    $custom_css = trim( (string) ( $settings['custom_css'] ?? '' ) );
    if ( strpos( $custom_css, 'white-space' ) === false || strpos( $custom_css, 'max-width' ) === false ) {
        $custom_css .= ( $custom_css !== '' ? "\n" : '' ) . 'selector .elementor-button { max-width: 100%; white-space: normal; box-sizing: border-box; }';
        $settings['custom_css'] = $custom_css;
    }
    return $before !== wp_json_encode( $settings );
}

function wpae_llm_wrap_generation_cta( array $elements, int &$changed ): array {
    if ( empty( $elements ) ) {
        return $elements;
    }
    $last_index = array_key_last( $elements );
    $last = $elements[ $last_index ] ?? null;
    if ( ! is_array( $last ) || ( $last['elType'] ?? '' ) !== 'widget' || ( $last['widgetType'] ?? '' ) !== 'button' ) {
        return $elements;
    }
    $last_settings = is_array( $last['settings'] ?? null ) ? $last['settings'] : [];
    $last_classes = preg_split( '/\s+/', trim( (string) ( $last_settings['_css_classes'] ?? '' ) ) );
    if ( is_array( $last_classes ) && in_array( 'wpae-generated-cta', $last_classes, true ) ) {
        return $elements;
    }
    $last_settings['_css_classes'] = function_exists( 'wpae_append_css_classes' )
        ? wpae_append_css_classes( $last_settings['_css_classes'] ?? '', [ 'wpae-generated-cta' ] )
        : trim( (string) ( $last_settings['_css_classes'] ?? '' ) . ' wpae-generated-cta' );
    wpae_llm_normalize_generated_button_settings( $last_settings );
    $last['settings'] = $last_settings;
    $elements[ $last_index ] = $last;
    $changed++;
    return $elements;
}

function wpae_llm_normalize_requested_cta( array $elements, string $message, int &$changed, bool $preserve_style = false ): array {
    $cta = '';
    foreach ( wpae_llm_extract_requested_content( $message ) as $value ) {
        if ( wpae_llm_is_cta_copy( (string) $value ) ) {
            $cta = wpae_llm_compact_cta_text( (string) $value );
            break;
        }
    }
    if ( $cta === '' ) {
        return $elements;
    }

    $normalized_cta = wpae_llm_normalize_content_text( $cta );
    $button_found = false;
    $replaced_text = false;
    $walk = static function ( array &$nodes ) use ( &$walk, $cta, $normalized_cta, $preserve_style, &$button_found, &$replaced_text, &$changed ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            if ( $widget_type === 'button' ) {
                $settings['text'] = $cta;
                if ( ! $preserve_style ) {
                    $settings['link'] = [ 'url' => '#contact' ];
                    $settings['_css_classes'] = function_exists( 'wpae_append_css_classes' )
                        ? wpae_append_css_classes( $settings['_css_classes'] ?? '', [ 'wpae-generated-cta' ] )
                        : trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-generated-cta' );
                    wpae_llm_normalize_generated_button_settings( $settings );
                }
                $element['settings'] = $settings;
                $button_found = true;
            } elseif ( $widget_type === 'text-editor' && ! $button_found ) {
                $editor_text = wpae_llm_normalize_content_text( $settings['editor'] ?? '' );
                if ( $editor_text === $normalized_cta ) {
                    $element['widgetType'] = 'button';
                    $settings = [
                        'text' => $cta,
                        'link' => [ 'url' => '#contact' ],
                        '_css_classes' => 'wpae-generated-cta',
                    ];
                    wpae_llm_normalize_generated_button_settings( $settings );
                    $element['settings'] = $settings;
                    $element['elements'] = [];
                    $button_found = true;
                    $replaced_text = true;
                    $changed++;
                }
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'] );
            }
        }
        unset( $element );
    };
    $walk( $elements );

    if ( $button_found ) {
        if ( $replaced_text ) {
            $changed++;
        }
        return $elements;
    }

    $button = [
        'id' => 'wpae-generated-cta-' . substr( md5( $normalized_cta ), 0, 7 ),
        'elType' => 'widget',
        'widgetType' => 'button',
        'settings' => [
            'text' => $cta,
            'link' => [ 'url' => '#contact' ],
            '_css_classes' => 'wpae-generated-cta',
        ],
        'elements' => [],
    ];
    $target = null;
    $find_shell = static function ( array &$nodes ) use ( &$find_shell, &$target ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( ( $element['elType'] ?? '' ) === 'container' && is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true ) ) {
                $target =& $element['elements'];
                return;
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $find_shell( $element['elements'] );
                if ( is_array( $target ) ) {
                    return;
                }
            }
        }
        unset( $element );
    };
    $find_shell( $elements );
    if ( ! is_array( $target ) ) {
        foreach ( $elements as &$root ) {
            if ( is_array( $root ) && ( $root['elType'] ?? '' ) === 'container' ) {
                $root['elements'] = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
                $target =& $root['elements'];
                break;
            }
        }
        unset( $root );
    }
    if ( is_array( $target ) ) {
        wpae_llm_normalize_generated_button_settings( $button['settings'] );
        $target[] = $button;
        $changed++;
    }
    unset( $target );
    return $elements;
}

function wpae_llm_generation_visual_grammar_hint(): string {
    return ' По умолчанию каждый новый блок обязан начинаться с одного компактного outlined native badge-контейнера с закругленным pill-радиусом и native heading-label внутри. Icon-box запрещен: заголовок карточки всегда остается обычным native heading, а иконка при необходимости выводится отдельным native icon. В testimonial-карточке имя/компания выводятся native heading, цитата — native text-editor, а quote/icon-иконка допускается только как декоративный маркер. В testimonial-карточке цитата является текстом и не должна превращаться в иконку. Контейнер bento-сетки карточек должен оставаться прозрачным, а фон разрешен только у самих карточек. Для process/timeline выбери из запрошенного семейства: односторонняя вертикальная последовательность слева, центральная чередующаяся вертикальная последовательность или горизонтальная последовательность; используй общий native Flex-контейнер, отдельные номер-маркеры, connector между шагами и обычные heading/text-editor в каждом шаге. На mobile любой вариант собирай в один читаемый stack; не превращай timeline в bento-сетку или 2x2-карточки. Hero — самостоятельная композиция; trusted-preservation применяется только к явно доверенному источнику. Внутри каждой текстовой колонки выбери одно выравнивание и примени его одновременно к badge, заголовку, тексту, иконкам и CTA; если выбран center, все элементы центрированы, если left, все элементы прижаты к left. Для hero с фото и светлым текстом используй черный полупрозрачный native Background Overlay. Это правило задается плагином и не требует технических указаний в пользовательском промте.';
}

function wpae_llm_fallback_variant( string $message ): int {
    return hexdec( substr( md5( wpae_llm_normalize_content_text( $message ) ), 0, 6 ) ) % wpae_llm_visual_variant_count();
}

function wpae_llm_visual_variant_count(): int {
    return 60;
}

function wpae_llm_fallback_theme( int $variant ): array {
    $themes = [
        [ 'root' => '#f7f7f5', 'cards' => [ '#ffffff' ], 'border' => '#d1d5db', 'radius' => 0.75, 'gap' => '1.5', 'padding' => [ 'top' => '2.5', 'right' => '1.5', 'bottom' => '2.5', 'left' => '1.5' ] ],
        [ 'root' => '#f4f7ff', 'cards' => [ '#ffffff' ], 'border' => '#4460EC', 'radius' => 1.25, 'gap' => '1.25', 'padding' => [ 'top' => '3', 'right' => '1.75', 'bottom' => '3', 'left' => '1.75' ] ],
        [ 'root' => '#ecfeff', 'cards' => [ '#f5fffe' ], 'border' => '#0f766e', 'radius' => 0.5, 'gap' => '1.75', 'padding' => [ 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2' ] ],
        [ 'root' => '#f8fafc', 'cards' => [ '#ffffff' ], 'border' => '#64748b', 'radius' => 1.5, 'gap' => '1', 'padding' => [ 'top' => '3.5', 'right' => '1.25', 'bottom' => '3.5', 'left' => '1.25' ] ],
        [ 'root' => '#fef2f2', 'cards' => [ '#fff8f9' ], 'border' => '#be123c', 'radius' => 0.25, 'gap' => '1.5', 'padding' => [ 'top' => '2.25', 'right' => '1.5', 'bottom' => '2.25', 'left' => '1.5' ] ],
        [ 'root' => '#f0fdf4', 'cards' => [ '#f7fff9' ], 'border' => '#15803d', 'radius' => 1, 'gap' => '1.25', 'padding' => [ 'top' => '2.75', 'right' => '1.75', 'bottom' => '2.75', 'left' => '1.75' ] ],
        [ 'root' => '#fffbeb', 'cards' => [ '#fffdf5' ], 'border' => '#a16207', 'radius' => 0.5, 'gap' => '1.5', 'padding' => [ 'top' => '2.25', 'right' => '2', 'bottom' => '2.25', 'left' => '2' ] ],
        [ 'root' => '#eff6ff', 'cards' => [ '#f8fbff' ], 'border' => '#1d4ed8', 'radius' => 1.25, 'gap' => '1.75', 'padding' => [ 'top' => '3', 'right' => '1.5', 'bottom' => '3', 'left' => '1.5' ] ],
        [ 'root' => '#f5f5f4', 'cards' => [ '#ffffff' ], 'border' => '#57534e', 'radius' => 0.25, 'gap' => '1', 'padding' => [ 'top' => '2.5', 'right' => '1.25', 'bottom' => '2.5', 'left' => '1.25' ] ],
        [ 'root' => '#f0fdfa', 'cards' => [ '#f5fffd' ], 'border' => '#115e59', 'radius' => 1.75, 'gap' => '1.25', 'padding' => [ 'top' => '3.25', 'right' => '1.75', 'bottom' => '3.25', 'left' => '1.75' ] ],
    ];
    return $themes[ abs( $variant ) % count( $themes ) ];
}

function wpae_llm_select_fallback_variant( array $elements, int $seed, array $candidate_elements = [], string $archetype = '' ): int {
    $used = [];
    $collect = static function ( array $nodes ) use ( &$collect, &$used ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            preg_match_all( '/(?:^|\s)wpae-fallback-variant-(\d+)(?:\s|$)/', (string) ( $settings['_css_classes'] ?? '' ), $matches );
            foreach ( (array) ( $matches[1] ?? [] ) as $match ) {
                $used[] = absint( $match );
            }
            if ( isset( $settings['_wpae_visual_variant'] ) && is_numeric( $settings['_wpae_visual_variant'] ) ) {
                $used[] = absint( $settings['_wpae_visual_variant'] );
            }
            if ( is_array( $node['elements'] ?? null ) ) {
                $collect( $node['elements'] );
            }
        }
    };
    $collect( $elements );
    $used = array_values( array_unique( $used ) );
    $used_signatures = wpae_llm_collect_visual_signatures( $elements );
    $used_layouts = wpae_llm_collect_visual_layouts( $elements );
    $variant_count = wpae_llm_visual_variant_count();
    $start = abs( $seed ) % $variant_count;
    $prefer_new_layout = count( $used_layouts ) < 6;
    for ( $offset = 0; $offset < $variant_count; $offset++ ) {
        $candidate = ( $start + $offset ) % $variant_count;
        if ( in_array( $candidate, $used, true ) ) {
            continue;
        }
        if ( ! empty( $candidate_elements ) ) {
            $candidate_data = wpae_llm_apply_fallback_variant( $candidate_elements, $archetype, $candidate );
            $candidate_layouts = wpae_llm_collect_visual_layouts( $candidate_data );
            if ( $prefer_new_layout && array_intersect( $candidate_layouts, $used_layouts ) ) {
                continue;
            }
            $candidate_signatures = wpae_llm_collect_visual_signatures( $candidate_data );
            if ( array_intersect( $candidate_signatures, $used_signatures ) ) {
                continue;
            }
        }
        return $candidate;
    }
    return $start;
}

function wpae_llm_set_variant_container_width( array &$settings, float $width ): void {
    $width = max( 0, min( 100, $width ) );
    foreach ( [ 'height', 'height_tablet', 'height_mobile', 'min_height', 'min_height_tablet', 'min_height_mobile', 'flex_basis', 'flex_basis_tablet', 'flex_basis_mobile' ] as $conflicting_key ) {
        unset( $settings[ $conflicting_key ] );
    }
    $settings['width'] = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
    $settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
    $settings['_element_width'] = 'initial';
    $settings['_element_custom_width'] = [ 'unit' => '%', 'size' => $width, 'sizes' => [] ];
    $settings['_element_width_mobile'] = 'initial';
    $settings['_element_custom_width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
    $settings['_flex_size'] = 'custom';
    $settings['_flex_grow'] = 0;
    $settings['_flex_shrink'] = 0;
    $settings['flex_grow'] = 0;
    $settings['flex_shrink'] = 0;
    $settings['align_self'] = 'stretch';
    $settings['_flex_size_mobile'] = 'custom';
    $settings['_flex_grow_mobile'] = 0;
    $settings['_flex_shrink_mobile'] = 0;
    $settings['flex_grow_mobile'] = 0;
    $settings['flex_shrink_mobile'] = 0;
    $settings['align_self_mobile'] = 'stretch';
}

function wpae_llm_set_flexible_bento_container_width( array &$settings, float $width ): void {
    wpae_llm_set_variant_container_width( $settings, $width );
    $settings['_flex_grow'] = 1;
    $settings['_flex_shrink'] = 1;
    $settings['flex_grow'] = 1;
    $settings['flex_shrink'] = 1;
    $settings['_flex_grow_mobile'] = 1;
    $settings['_flex_shrink_mobile'] = 1;
    $settings['flex_grow_mobile'] = 1;
    $settings['flex_shrink_mobile'] = 1;
}

function wpae_llm_variant_card_widths( int $variant, int $count ): array {
    $layout = intdiv( abs( $variant ) % wpae_llm_visual_variant_count(), 10 );
    if ( $count <= 0 ) {
        return [];
    }
    if ( $count === 1 ) {
        return [ 100 ];
    }

    if ( $count === 3 ) {
        return array_fill( 0, $count, 31 );
    }

    if ( $count === 4 ) {
        return array_fill( 0, $count, 48 );
    }

    // Keep rows balanced: five or six cards use three columns, while seven
    // or more use no more than four columns without leaving a narrow orphan.
    if ( in_array( $layout, [ 1, 3, 5 ], true ) && $count <= 4 ) {
        return array_fill( 0, $count, $count === 4 ? 48 : 31 );
    }

    $rows = (int) ceil( $count / 4 );
    $columns = (int) ceil( $count / max( 1, $rows ) );
    $width = $columns >= 4 ? 31 : ( $columns === 3 ? 31 : 48 );
    return array_fill( 0, $count, $width );
}

function wpae_llm_normalize_bento_grid( array &$element, int &$changed, string $archetype = '' ): void {
    if ( ( $element['elType'] ?? '' ) !== 'container' ) {
        return;
    }

    $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
    $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
    $grid_cards = [];
    foreach ( $children as $index => $child ) {
        if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
            $grid_cards[] = $index;
        }
    }
    if ( count( $grid_cards ) < 2 ) {
        return;
    }

    $before = wp_json_encode( [ $settings, $children ] );
    $settings['container_type'] = 'flex';
    $settings['flex_direction'] = 'row';
    $settings['flex_wrap'] = 'wrap';
    $settings['flex_justify_content'] = 'space-between';
    $settings['flex_align_items'] = $archetype === 'testimonials' ? 'flex-start' : 'stretch';
    if ( $archetype === 'testimonials' ) {
        $settings['flex_align_items_mobile'] = 'flex-start';
    }
    $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
    $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
    $settings['background_background'] = 'classic';
    $settings['background_color'] = 'transparent';
    $settings['_css_classes'] = function_exists( 'wpae_append_css_classes' ) ? wpae_append_css_classes( $settings['_css_classes'] ?? '', [ 'wpae-bento-grid' ] ) : trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-bento-grid' );
    foreach ( wpae_llm_variant_card_widths( 0, count( $grid_cards ) ) as $width_index => $width ) {
        $grid_index = $grid_cards[ $width_index ] ?? null;
        if ( $grid_index === null ) {
            continue;
        }
        $card_settings = is_array( $children[ $grid_index ]['settings'] ?? null ) ? $children[ $grid_index ]['settings'] : [];
        $card_settings['container_type'] = 'flex';
        $card_settings['flex_direction'] = 'column';
        $card_settings['flex_direction_mobile'] = 'column';
        $card_settings['flex_wrap'] = 'nowrap';
        $card_settings['flex_align_items'] = 'flex-start';
        $card_settings['flex_align_items_mobile'] = 'flex-start';
        wpae_llm_set_flexible_bento_container_width( $card_settings, (float) $width );
        $children[ $grid_index ]['settings'] = $card_settings;
    }
    $element['settings'] = $settings;
    $element['elements'] = $children;
    if ( $before !== wp_json_encode( [ $settings, $children ] ) ) {
        $changed++;
    }
}

function wpae_llm_normalize_bento_grids_recursive( array &$elements, int &$changed, string $archetype = '' ): void {
    if ( $archetype === 'process' ) {
        return;
    }
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( ( $element['elType'] ?? '' ) === 'container' ) {
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) ) {
                wpae_llm_normalize_bento_grid( $element, $changed, $archetype );
            }
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_llm_normalize_bento_grids_recursive( $element['elements'], $changed, $archetype );
        }
    }
    unset( $element );
}

function wpae_llm_enforce_flex_layout_contract( array $elements, string $archetype, int &$changed = 0 ): array {
    $repeatable = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio', 'team' ];
    $clear_negative_margins = static function ( array &$settings ): bool {
        $did_change = false;
        foreach ( [ 'margin', 'margin_tablet', 'margin_mobile', '_margin', '_margin_tablet', '_margin_mobile' ] as $key ) {
            if ( ! is_array( $settings[ $key ] ?? null ) ) {
                continue;
            }
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                if ( is_numeric( $settings[ $key ][ $side ] ?? null ) && (float) $settings[ $key ][ $side ] < 0 ) {
                    $settings[ $key ][ $side ] = '0';
                    $did_change = true;
                }
            }
        }
        return $did_change;
    };
    $walk = static function ( array &$nodes, int $depth = 0 ) use ( &$walk, &$changed, $archetype, $repeatable, $clear_negative_margins ): void {
        foreach ( $nodes as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( ( $element['elType'] ?? '' ) === 'container' ) {
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
                $before = wp_json_encode( [ $settings, $children ] );
                $settings['container_type'] = 'flex';
                foreach ( [ 'height', 'height_tablet', 'height_mobile', 'min_height', 'min_height_tablet', 'min_height_mobile', 'max_height', 'max_height_tablet', 'max_height_mobile', 'flex_basis', 'flex_basis_tablet', 'flex_basis_mobile' ] as $key ) {
                    unset( $settings[ $key ] );
                }
                $clear_negative_margins( $settings );
                $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                $classes = is_array( $classes ) ? array_values( array_filter( $classes ) ) : [];
                $is_bento = in_array( 'wpae-bento-grid', $classes, true );
                $is_process_marker = $archetype === 'process' && in_array( 'wpae-process-marker', $classes, true );
                $is_process_connector = $archetype === 'process' && in_array( 'wpae-process-connector', $classes, true );
                if ( $is_process_marker ) {
                    $settings['min_height'] = [ 'unit' => 'rem', 'size' => 3 ];
                    $settings['min_height_mobile'] = [ 'unit' => 'rem', 'size' => 2.5 ];
                } elseif ( $is_process_connector ) {
                    $settings['min_height'] = [ 'unit' => 'rem', 'size' => 2.5 ];
                }
                $child_containers = [];
                foreach ( $children as $child_index => $child ) {
                    if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                        $child_containers[] = $child_index;
                    }
                }
                $is_repeatable_group = in_array( $archetype, $repeatable, true ) && $archetype !== 'process' && count( $child_containers ) >= 2 && $depth > 0;
                if ( $is_bento || $is_repeatable_group ) {
                    $settings['flex_direction'] = 'row';
                    $settings['flex_direction_mobile'] = 'column';
                    $settings['flex_wrap'] = 'wrap';
                    $settings['flex_wrap_mobile'] = 'wrap';
                    $settings['flex_justify_content'] = 'space-between';
                    $settings['flex_align_items'] = 'stretch';
                    $settings['flex_align_items_mobile'] = 'stretch';
                    $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
                    $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
                    $settings['background_background'] = 'classic';
                    $settings['background_color'] = 'transparent';
                    if ( ! $is_bento ) {
                        $classes[] = 'wpae-bento-grid';
                    }
                    foreach ( wpae_llm_variant_card_widths( 0, count( $child_containers ) ) as $width_index => $width ) {
                        $child_index = $child_containers[ $width_index ] ?? null;
                        if ( $child_index === null ) {
                            continue;
                        }
                        $child_settings = is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [];
                        $child_settings['container_type'] = 'flex';
                        $child_settings['flex_direction'] = 'column';
                        $child_settings['flex_direction_mobile'] = 'column';
                        $child_settings['flex_wrap'] = 'nowrap';
                        $child_settings['flex_align_items'] = 'stretch';
                        $child_settings['flex_align_items_mobile'] = 'stretch';
                        wpae_llm_set_flexible_bento_container_width( $child_settings, (float) $width );
                        $children[ $child_index ]['settings'] = $child_settings;
                    }
                } elseif ( empty( $settings['flex_direction'] ) ) {
                    $settings['flex_direction'] = 'column';
                    $settings['flex_direction_mobile'] = 'column';
                    $settings['flex_wrap'] = 'nowrap';
                }
                if ( $depth === 0 ) {
                    $settings['background_background'] = 'classic';
                    $settings['background_color'] = 'transparent';
                }
                $settings['_css_classes'] = implode( ' ', array_values( array_unique( $classes ) ) );
                $element['settings'] = $settings;
                $element['elements'] = $children;
                if ( $before !== wp_json_encode( [ $settings, $children ] ) ) {
                    $changed++;
                }
            }
            if ( is_array( $element['elements'] ?? null ) ) {
                $walk( $element['elements'], $depth + 1 );
            }
        }
        unset( $element );
    };
    $walk( $elements );
    if ( $archetype === 'team' ) {
        $elements = wpae_llm_convert_icon_boxes_to_native_widgets( $elements, $changed );
    }
    return $elements;
}

function wpae_llm_repair_unbalanced_repeatable_layout( array $elements, string $message, string $archetype, int &$changed ): array {
    if ( ! in_array( $archetype, [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ], true ) || $archetype === 'process' ) {
        return $elements;
    }

    $section_title = trim( (string) ( wpae_llm_content_units( $message )[0] ?? '' ) );
    $normalized_section_title = wpae_llm_normalize_content_text( $section_title );
    $content_units = array_values( array_filter( array_slice( wpae_llm_content_units( $message ), 1 ), static fn( $unit ): bool => trim( (string) $unit ) !== '' ) );
    $leaf_cards = static function ( array $nodes ) use ( &$leaf_cards ): array {
        $cards = [];
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ( $node['elType'] ?? '' ) !== 'container' ) {
                continue;
            }
            $children = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];
            $child_containers = array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' );
            if ( empty( $child_containers ) && ! empty( $children ) ) {
                $has_card_widget = (bool) array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $child['widgetType'] ?? '' ) ), [ 'heading', 'text-editor', 'testimonial', 'image', 'icon' ], true ) );
                if ( $has_card_widget ) {
                    $cards[] = $node;
                }
                continue;
            }
            if ( ! empty( $child_containers ) ) {
                $cards = array_merge( $cards, $leaf_cards( $children ) );
            }
        }
        return $cards;
    };
    $card_widget_count = static function ( array $card ): int {
        return count( array_filter( (array) ( $card['elements'] ?? [] ), static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' ) );
    };
    $card_heading = static function ( array $card ): string {
        foreach ( (array) ( $card['elements'] ?? [] ) as $child ) {
            if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'widget' && ( $child['widgetType'] ?? '' ) === 'heading' ) {
                return wpae_llm_normalize_content_text( $child['settings']['title'] ?? '' );
            }
        }
        return '';
    };
    $repair = static function ( array &$nodes ) use ( &$repair, $leaf_cards, $card_widget_count, $card_heading, $normalized_section_title, &$changed ): bool {
        foreach ( $nodes as $index => &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            if ( ( $element['elType'] ?? '' ) === 'container' && is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) ) {
                $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
                $branches = array_values( array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) );
                $branch_sizes = array_map( static fn( $branch ): int => count( $leaf_cards( [ $branch ] ) ), $branches );
                $has_sparse_branch = count( $branch_sizes ) >= 2 && in_array( 1, $branch_sizes, true ) && max( $branch_sizes ) >= 3;
                if ( $has_sparse_branch ) {
                    $cards = $leaf_cards( $branches );
                    $cards = array_values( array_filter( $cards, static fn( $card ): bool => ! ( $card_widget_count( $card ) === 1 && $normalized_section_title !== '' && $card_heading( $card ) === $normalized_section_title ) ) );
                    if ( count( $cards ) >= 2 ) {
                        $settings['container_type'] = 'flex';
                        $settings['flex_direction'] = 'row';
                        $settings['flex_wrap'] = 'wrap';
                        $settings['flex_justify_content'] = 'space-between';
                        $settings['flex_align_items'] = 'stretch';
                        $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
                        $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
                        $settings['background_background'] = 'classic';
                        $settings['background_color'] = 'transparent';
                        foreach ( wpae_llm_variant_card_widths( 0, count( $cards ) ) as $card_index => $width ) {
                            $card_settings = is_array( $cards[ $card_index ]['settings'] ?? null ) ? $cards[ $card_index ]['settings'] : [];
                            wpae_llm_set_flexible_bento_container_width( $card_settings, (float) $width );
                            $cards[ $card_index ]['settings'] = $card_settings;
                        }
                        $element['settings'] = $settings;
                        $element['elements'] = $cards;
                        $nodes[ $index ] = $element;
                        $changed++;
                        return true;
                    }
                }
            }
            if ( is_array( $element['elements'] ?? null ) && $repair( $element['elements'] ) ) {
                return true;
            }
        }
        unset( $element );
        return false;
    };
    $repair( $elements );

    if ( $normalized_section_title !== '' ) {
        $set_section_heading = static function ( array &$nodes ) use ( &$set_section_heading, $section_title, &$changed ): bool {
            foreach ( $nodes as &$element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                if ( ( $element['elType'] ?? '' ) === 'container' && is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) ) {
                    continue;
                }
                if ( ( $element['elType'] ?? '' ) === 'widget' && ( $element['widgetType'] ?? '' ) === 'heading' && ( ! is_array( $classes ) || ! in_array( 'wpae-generated-badge-label', $classes, true ) ) ) {
                    $settings['title'] = $section_title;
                    $element['settings'] = $settings;
                    $changed++;
                    return true;
                }
                if ( is_array( $element['elements'] ?? null ) && $set_section_heading( $element['elements'] ) ) {
                    return true;
                }
            }
            unset( $element );
            return false;
        };
        $set_section_heading( $elements );
    }

    if ( $normalized_section_title !== '' && ! empty( $content_units ) ) {
        $repair_duplicate_card_heading = static function ( array &$nodes, bool $inside_grid = false ) use ( &$repair_duplicate_card_heading, $normalized_section_title, $content_units, &$changed ): void {
            foreach ( $nodes as &$element ) {
                if ( ! is_array( $element ) ) {
                    continue;
                }
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                $in_grid = $inside_grid || ( is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) );
                if ( $in_grid && ( $element['elType'] ?? '' ) === 'container' ) {
                    $heading_index = null;
                    $copy_index = null;
                    foreach ( (array) ( $element['elements'] ?? [] ) as $child_index => $child ) {
                        if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' ) {
                            continue;
                        }
                        $widget_type = sanitize_key( (string) ( $child['widgetType'] ?? '' ) );
                        $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
                        if ( $widget_type === 'heading' && $heading_index === null && wpae_llm_normalize_content_text( $child_settings['title'] ?? '' ) === $normalized_section_title ) {
                            $heading_index = $child_index;
                        } elseif ( $widget_type === 'text-editor' && $copy_index === null ) {
                            $copy_index = $child_index;
                        }
                    }
                    if ( $heading_index !== null && $copy_index !== null ) {
                        $heading_settings = is_array( $element['elements'][ $heading_index ]['settings'] ?? null ) ? $element['elements'][ $heading_index ]['settings'] : [];
                        $copy_settings = is_array( $element['elements'][ $copy_index ]['settings'] ?? null ) ? $element['elements'][ $copy_index ]['settings'] : [];
                        $current_copy = trim( sanitize_text_field( (string) ( $copy_settings['editor'] ?? '' ) ) );
                        if ( $current_copy !== '' ) {
                            $heading_settings['title'] = $current_copy;
                            $replacement_copy = '';
                            foreach ( $content_units as $unit ) {
                                $candidate = trim( sanitize_text_field( (string) $unit ) );
                                if ( $candidate !== '' && wpae_llm_normalize_content_text( $candidate ) !== wpae_llm_normalize_content_text( $current_copy ) && wpae_llm_normalize_content_text( $candidate ) !== $normalized_section_title ) {
                                    $replacement_copy = $candidate;
                                    break;
                                }
                            }
                            $copy_settings['editor'] = $replacement_copy !== '' ? $replacement_copy : $current_copy;
                            $element['elements'][ $heading_index ]['settings'] = $heading_settings;
                            $element['elements'][ $copy_index ]['settings'] = $copy_settings;
                            $changed++;
                        }
                    }
                }
                if ( is_array( $element['elements'] ?? null ) ) {
                    $repair_duplicate_card_heading( $element['elements'], $in_grid );
                }
            }
            unset( $element );
        };
        $repair_duplicate_card_heading( $elements );
    }

    return $elements;
}

function wpae_llm_visual_signature( array $root ): string {
    $containers = [];
    $walk = static function ( array $nodes, int $depth = 0 ) use ( &$walk, &$containers ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ( $node['elType'] ?? '' ) !== 'container' ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $children = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];
            $child_widths = [];
            foreach ( $children as $child ) {
                if ( ! is_array( $child ) ) {
                    continue;
                }
                $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
                $width = $child_settings['width'] ?? $child_settings['_element_custom_width'] ?? null;
                if ( is_array( $width ) ) {
                    $child_widths[] = [ 'unit' => (string) ( $width['unit'] ?? '' ), 'size' => (string) ( $width['size'] ?? '' ) ];
                } else {
                    $child_widths[] = (string) $width;
                }
            }
            $containers[] = [
                'depth' => $depth,
                'grid' => is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ),
                'background' => (string) ( $settings['background_color'] ?? '' ),
                'border' => (string) ( $settings['border_color'] ?? '' ),
                'radius' => $settings['border_radius'] ?? null,
                'direction' => (string) ( $settings['flex_direction'] ?? '' ),
                'wrap' => (string) ( $settings['flex_wrap'] ?? '' ),
                'justify' => (string) ( $settings['flex_justify_content'] ?? '' ),
                'align' => (string) ( $settings['flex_align_items'] ?? '' ),
                'gap' => $settings['flex_gap'] ?? $settings['gap'] ?? null,
                'padding' => $settings['padding'] ?? null,
                'child_widths' => $child_widths,
                'child_count' => count( $children ),
            ];
            if ( $children ) {
                $walk( $children, $depth + 1 );
            }
        }
    };
    $walk( [ $root ] );
    return hash( 'sha256', (string) wp_json_encode( $containers ) );
}

function wpae_llm_infer_visual_layout( array $root ): ?int {
    $root_settings = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
    if ( isset( $root_settings['_wpae_visual_layout'] ) && is_numeric( $root_settings['_wpae_visual_layout'] ) ) {
        return absint( $root_settings['_wpae_visual_layout'] ) % 6;
    }

    $containers = [];
    $walk = static function ( array $nodes ) use ( &$walk, &$containers ): void {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ( $node['elType'] ?? '' ) !== 'container' ) {
                continue;
            }
            $children = is_array( $node['elements'] ?? null ) ? $node['elements'] : [];
            $child_containers = array_values( array_filter( $children, static fn( $child ): bool => is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) );
            if ( count( $child_containers ) >= 2 ) {
                $widths = [];
                foreach ( $child_containers as $child ) {
                    $settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
                    $width = $settings['width']['size'] ?? $settings['_element_custom_width']['size'] ?? null;
                    $widths[] = is_numeric( $width ) ? (int) round( (float) $width ) : 0;
                }
                $containers[] = $widths;
                return;
            }
            if ( $children ) {
                $walk( $children );
            }
        }
    };
    $walk( [ $root ] );
    $widths = $containers[0] ?? [];
    if ( count( $widths ) >= 2 ) {
        $first = (int) ( $widths[0] ?? 0 );
        $rest = array_slice( $widths, 1 );
        $rest_unique = array_values( array_unique( $rest ) );
        if ( $first >= 95 && count( $rest_unique ) === 1 ) {
            return 4;
        }
        $last_width = (int) ( $widths[ count( $widths ) - 1 ] ?? 0 );
        $leading_widths = array_slice( $widths, 0, -1 );
        if ( count( $widths ) >= 4 && $last_width >= 95 && count( array_filter( $leading_widths, static fn( $width ): bool => $width >= 29 && $width <= 35 ) ) === count( $leading_widths ) ) {
            return 3;
        }
        if ( $first >= 45 && $first <= 55 && count( array_filter( $rest, static fn( $width ): bool => $width >= 20 && $width <= 28 ) ) === count( $rest ) ) {
            return 2;
        }
        if ( $first >= 20 && $first <= 28 && (int) ( $widths[1] ?? 0 ) >= 45 && (int) ( $widths[1] ?? 0 ) <= 55 ) {
            return 5;
        }
        if ( count( array_unique( $widths ) ) === 1 && $first >= 45 && $first <= 55 ) {
            return 1;
        }
        if ( count( array_unique( $widths ) ) === 1 && $first >= 20 && $first <= 28 ) {
            return 3;
        }
        if ( count( array_unique( $widths ) ) === 1 && $first >= 29 && $first <= 35 ) {
            return 0;
        }
    }

    return (string) ( $root_settings['flex_direction'] ?? '' ) === 'row' ? 1 : 0;
}

function wpae_llm_collect_visual_layouts( array $elements ): array {
    $layouts = [];
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) || ( $element['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $layout = wpae_llm_infer_visual_layout( $element );
        if ( $layout !== null ) {
            $layouts[] = $layout;
        }
    }
    return array_values( array_unique( $layouts ) );
}

function wpae_llm_collect_visual_signatures( array $elements ): array {
    $signatures = [];
    foreach ( $elements as $element ) {
        if ( is_array( $element ) && ( $element['elType'] ?? '' ) === 'container' ) {
            $signatures[] = wpae_llm_visual_signature( $element );
        }
    }
    return array_values( array_unique( $signatures ) );
}

function wpae_llm_apply_fallback_variant_recursive( array &$elements, string $archetype, array $theme, int $variant, int $depth, int &$card_index ): void {
    $repeatable = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ];
    $layout = intdiv( abs( $variant ) % wpae_llm_visual_variant_count(), 10 );
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( ( $element['elType'] ?? '' ) === 'container' ) {
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $is_grid = is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
            $process_layout = 'left';
            if ( is_array( $classes ) && in_array( 'wpae-process-timeline-horizontal', $classes, true ) ) {
                $process_layout = 'horizontal';
            } elseif ( is_array( $classes ) && in_array( 'wpae-process-timeline-alternating', $classes, true ) ) {
                $process_layout = 'alternating';
            }
            $is_process_visual = $archetype === 'process' && is_array( $classes ) && (bool) array_intersect( [ 'wpae-process-marker', 'wpae-process-marker-column', 'wpae-process-connector', 'wpae-process-content', 'wpae-process-spacer' ], $classes );
            $has_content_shell = false;
            if ( $depth === 0 ) {
                foreach ( $element['elements'] ?? [] as $top_level_child ) {
                    if ( ! is_array( $top_level_child ) ) {
                        continue;
                    }
                    $top_level_settings = is_array( $top_level_child['settings'] ?? null ) ? $top_level_child['settings'] : [];
                    $top_level_classes = preg_split( '/\s+/', trim( (string) ( $top_level_settings['_css_classes'] ?? '' ) ) );
                    if ( is_array( $top_level_classes ) && in_array( 'wpae-generated-content-shell', $top_level_classes, true ) ) {
                        $has_content_shell = true;
                        break;
                    }
                }
            }
            if ( $depth === 0 ) {
                $settings['background_background'] = 'classic';
                $settings['background_color'] = 'transparent';
                $settings['flex_gap'] = [ 'column' => $theme['gap'], 'row' => $theme['gap'], 'isLinked' => true, 'unit' => 'rem', 'size' => $theme['gap'] ];
                $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
                $settings['padding'] = array_merge( [ 'unit' => 'rem', 'isLinked' => false ], $theme['padding'] );
                $settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '2', 'right' => '1', 'bottom' => '2', 'left' => '1', 'isLinked' => true ];
                $settings['_css_classes'] = trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-fallback-variant-' . (string) $variant );
                $settings['_wpae_visual_variant'] = $variant;
                $settings['_wpae_visual_layout'] = $layout;
                $settings['flex_direction'] = $archetype === 'process' && $process_layout === 'horizontal' ? 'row' : ( $archetype === 'process' || $has_content_shell ? 'column' : ( in_array( $layout, [ 1, 5 ], true ) ? 'row' : 'column' ) );
                $settings['flex_wrap'] = $archetype === 'process' && $process_layout === 'horizontal' ? 'wrap' : ( $archetype === 'process' || $has_content_shell ? 'nowrap' : ( in_array( $layout, [ 1, 5 ], true ) ? 'wrap' : 'nowrap' ) );
                $settings['flex_justify_content'] = $archetype === 'process' && $process_layout === 'horizontal' ? 'space-between' : ( $archetype === 'process' || $has_content_shell ? 'flex-start' : ( $layout === 5 ? 'space-between' : ( $layout === 1 ? 'flex-start' : 'center' ) ) );
                $settings['flex_align_items'] = $archetype === 'process' || $has_content_shell ? 'stretch' : ( $layout === 1 ? 'stretch' : 'flex-start' );
                if ( $archetype === 'process' || $has_content_shell || in_array( $layout, [ 1, 5 ], true ) ) {
                    foreach ( $element['elements'] as &$top_child ) {
                        if ( ! is_array( $top_child ) ) {
                            continue;
                        }
                        $top_child_settings = is_array( $top_child['settings'] ?? null ) ? $top_child['settings'] : [];
                        $top_child_classes = preg_split( '/\s+/', trim( (string) ( $top_child_settings['_css_classes'] ?? '' ) ) );
                        $top_child_type = (string) ( $top_child['widgetType'] ?? '' );
                        if ( $archetype === 'process' ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, $process_layout === 'horizontal' ? 24 : 100 );
                        } elseif ( is_array( $top_child_classes ) && in_array( 'wpae-generated-badge', $top_child_classes, true ) ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, 100 );
                        } elseif ( is_array( $top_child_classes ) && in_array( 'wpae-generated-content-shell', $top_child_classes, true ) ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, 100 );
                        } elseif ( is_array( $top_child_classes ) && in_array( 'wpae-bento-grid', $top_child_classes, true ) ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, 100 );
                        } elseif ( $top_child_type === 'heading' ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, $layout === 5 ? 58 : 48 );
                        } elseif ( $top_child_type === 'text-editor' ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, $layout === 5 ? 38 : 48 );
                        } elseif ( $top_child_type === 'button' ) {
                            wpae_llm_set_variant_container_width( $top_child_settings, $layout === 5 ? 38 : 48 );
                        }
                        $top_child['settings'] = $top_child_settings;
                    }
                    unset( $top_child );
                }
            } elseif ( in_array( $archetype, $repeatable, true ) && $depth >= 2 && ! $is_grid && ! $is_process_visual ) {
                $card_index++;
                $settings['background_background'] = 'classic';
                $settings['background_color'] = $theme['cards'][ ( $card_index - 1 ) % count( $theme['cards'] ) ];
                $settings['border_border'] = 'solid';
                $settings['border_color'] = $theme['border'];
                $settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
                $settings['border_radius'] = [ 'unit' => 'rem', 'size' => $theme['radius'], 'isLinked' => true ];
            }
            if ( $is_grid ) {
                $settings['flex_direction'] = 'row';
                $settings['flex_wrap'] = 'wrap';
                $settings['flex_justify_content'] = in_array( $layout, [ 1, 3, 5 ], true ) ? 'flex-start' : 'space-between';
                $settings['flex_align_items'] = 'stretch';
                $grid_cards = [];
                foreach ( $element['elements'] as $grid_index => $grid_child ) {
                    if ( is_array( $grid_child ) && ( $grid_child['elType'] ?? '' ) === 'container' ) {
                        $grid_cards[] = $grid_index;
                    }
                }
                foreach ( wpae_llm_variant_card_widths( $variant, count( $grid_cards ) ) as $width_index => $width ) {
                    $grid_index = $grid_cards[ $width_index ] ?? null;
                    if ( $grid_index === null ) {
                        continue;
                    }
                    $card_settings = is_array( $element['elements'][ $grid_index ]['settings'] ?? null ) ? $element['elements'][ $grid_index ]['settings'] : [];
                    wpae_llm_set_flexible_bento_container_width( $card_settings, (float) $width );
                    $element['elements'][ $grid_index ]['settings'] = $card_settings;
                }
            }
            $element['settings'] = $settings;
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            wpae_llm_apply_fallback_variant_recursive( $element['elements'], $archetype, $theme, $variant, $depth + 1, $card_index );
        }
    }
    unset( $element );
}

function wpae_llm_apply_fallback_variant( array $elements, string $archetype, int $variant ): array {
    $card_index = 0;
    wpae_llm_apply_fallback_variant_recursive( $elements, $archetype, wpae_llm_fallback_theme( $variant ), $variant, 0, $card_index );
    return $elements;
}

function wpae_llm_badge_label( string $archetype ): string {
    $labels = [
        'mega_menu' => 'МЕГА МЕНЮ',
        'hero' => 'ПРЕДЛОЖЕНИЕ',
        'benefits' => 'ПРЕИМУЩЕСТВА',
        'pricing' => 'ФОРМАТЫ',
        'testimonials' => 'ОТЗЫВЫ',
        'faq' => 'ВОПРОСЫ',
        'process' => 'ПРОЦЕСС',
        'cta' => 'СЛЕДУЮЩИЙ ШАГ',
        'portfolio' => 'КЕЙСЫ',
        'team' => 'КОМАНДА',
        'about' => 'О КОМПАНИИ',
        'image-box' => 'ПРОЕКТЫ',
        'carousel' => 'ПАРТНЁРЫ',
    ];

    return $labels[ $archetype ] ?? 'НОВЫЙ БЛОК';
}

function wpae_llm_badge_widget( string $id, string $archetype ): array {
    return [
        'id' => $id,
        'elType' => 'container',
        'settings' => [
            '_css_classes' => 'wpae-generated-badge',
            'content_width' => 'full',
            'flex_direction' => 'row',
            'flex_wrap' => 'nowrap',
            'flex_justify_content' => 'center',
            'flex_align_items' => 'center',
            'background_background' => 'classic',
            'background_color' => '#ffffff',
            'border_border' => 'solid',
            'border_color' => '#1f2937',
            'border_width' => [ 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ],
            'border_radius' => [ 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'size' => 999, 'isLinked' => true ],
            'padding' => [ 'unit' => 'rem', 'top' => '0.5', 'right' => '1.75', 'bottom' => '0.5', 'left' => '1.75', 'isLinked' => false ],
            'align_self' => 'flex-start',
            '_element_width' => 'initial',
            '_flex_grow' => 0,
            '_flex_shrink' => 0,
            'custom_css' => 'selector { width: fit-content; max-width: 100%; align-self: flex-start; flex: 0 0 auto; }',
        ],
        'elements' => [
            [
                'id' => $id . '-label',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => [
                    'title' => wpae_llm_badge_label( $archetype ),
                    'header_size' => 'h6',
                    '_css_classes' => 'wpae-generated-badge-label',
                    'title_color' => '#111827',
                    'typography_font_size' => [ 'unit' => 'rem', 'size' => 1.125 ],
                    'typography_font_weight' => '600',
                    'typography_line_height' => [ 'unit' => 'em', 'size' => 1.1 ],
                    'typography_text_transform' => 'uppercase',
                    'align_self' => 'center',
                    '_element_width' => 'initial',
                    '_flex_grow' => 0,
                    '_flex_shrink' => 0,
                    'margin' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                ],
                'elements' => [],
            ],
        ],
    ];
}

function wpae_llm_remove_redundant_badge_label_content( array $elements, string $archetype, int &$changed = 0 ): array {
    $normalize_label = static function ( string $value ): string {
        $value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
        return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value ) );
    };
    $badge_key = $normalize_label( wpae_llm_badge_label( $archetype ) );
    $walk = static function ( array &$nodes, bool $inside_bento_grid = false ) use ( &$walk, $normalize_label, $badge_key, $archetype, &$changed ): bool {
        if ( $badge_key === '' ) {
            return false;
        }
        foreach ( $nodes as $index => &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $is_bento_grid = is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            if ( ! $inside_bento_grid && ( $node['elType'] ?? '' ) === 'widget' && in_array( $widget_type, [ 'heading', 'text-editor' ], true ) && ( ! is_array( $classes ) || ! in_array( 'wpae-generated-badge-label', $classes, true ) ) ) {
                $text_key = $widget_type === 'heading' ? 'title' : 'editor';
                $text_key_value = $normalize_label( wp_strip_all_tags( (string) ( $settings[ $text_key ] ?? '' ) ) );
                $text_without_prefix = preg_replace( '/^(?:наша|наш|наше|наши|новая|новые|избранные|главное)\s+/u', '', $text_key_value );
                $is_team_section_heading = $archetype === 'team' && $text_key_value === 'наша команда';
                if ( ! $is_team_section_heading && ( $text_key_value === $badge_key || $text_without_prefix === $badge_key ) ) {
                    array_splice( $nodes, $index, 1 );
                    $changed++;
                    return true;
                }
            }
            if ( is_array( $node['elements'] ?? null ) && $walk( $node['elements'], $inside_bento_grid || $is_bento_grid ) ) {
                return true;
            }
        }
        unset( $node );
        return false;
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_promote_team_section_heading( array $elements, int &$changed = 0 ): array {
    $contains_team_heading = static function ( array $nodes ) use ( &$contains_team_heading ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            $text_key = $widget_type === 'heading' ? 'title' : ( $widget_type === 'text-editor' ? 'editor' : '' );
            if ( ( $node['elType'] ?? '' ) === 'widget' && $text_key !== '' && wpae_llm_normalize_content_text( (string) ( $settings[ $text_key ] ?? '' ) ) === 'наша команда' ) {
                return true;
            }
            if ( is_array( $node['elements'] ?? null ) && $contains_team_heading( $node['elements'] ) ) {
                return true;
            }
        }
        return false;
    };
    $walk = static function ( array &$nodes, bool $inside_bento_grid = false ) use ( &$walk, &$changed, $contains_team_heading ): void {
        foreach ( $nodes as &$node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
            $is_bento_grid = is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
            $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
            if ( ! $inside_bento_grid && ( $node['elType'] ?? '' ) === 'widget' && in_array( $widget_type, [ 'heading', 'text-editor' ], true ) ) {
                $text_key = $widget_type === 'heading' ? 'title' : 'editor';
                $text = trim( wp_strip_all_tags( (string) ( $settings[ $text_key ] ?? '' ) ) );
                if ( wpae_llm_normalize_content_text( $text ) === 'наша команда' ) {
                    $before = wp_json_encode( $node );
                    $node['widgetType'] = 'heading';
                    $settings['title'] = $text !== '' ? $text : 'Наша команда';
                    $settings['header_size'] = 'h2';
                    $settings['title_color'] = '#111827';
                    $settings['typography_typography'] = 'custom';
                    $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 3.25 ];
                    $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 2.75 ];
                    $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 2.15 ];
                    $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.08 ];
                    $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => 1.1 ];
                    $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.12 ];
                    $settings['typography_font_weight'] = '700';
                    unset( $settings['editor'], $settings['text_color'], $settings['title_text'], $settings['description_text'] );
                    $node['settings'] = $settings;
                    if ( $before !== wp_json_encode( $node ) ) {
                        $changed++;
                    }
                }
            }
            if ( is_array( $node['elements'] ?? null ) ) {
                $walk( $node['elements'], $inside_bento_grid || $is_bento_grid );
                if ( ! $inside_bento_grid && ( $node['elType'] ?? '' ) === 'container' && sanitize_key( (string) ( $settings['flex_direction'] ?? '' ) ) === 'row' && $contains_team_heading( $node['elements'] ) ) {
                    foreach ( [ 'padding' => '24', 'padding_mobile' => '20' ] as $padding_key => $bottom ) {
                        if ( ! is_array( $settings[ $padding_key ] ?? null ) || (string) ( $settings[ $padding_key ]['bottom'] ?? '' ) === $bottom ) {
                            continue;
                        }
                        $settings[ $padding_key ]['bottom'] = $bottom;
                        $changed++;
                    }
                    $node['settings'] = $settings;
                }
            }
        }
        unset( $node );
    };
    $walk( $elements );
    return $elements;
}

function wpae_llm_enforce_preserved_library_badge( array $elements, string $archetype, int &$changed = 0 ): array {
    foreach ( $elements as $index => $root ) {
        if ( ! is_array( $root ) || ( $root['elType'] ?? '' ) !== 'container' ) {
            continue;
        }

        $root['settings'] = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
        $root['elements'] = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
        $original_settings = $root['settings'];
        $badge = null;
        $content_shell = null;
        $content_elements = [];

        foreach ( $root['elements'] as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true ) ) {
                if ( $badge === null ) {
                    $badge = wpae_llm_badge_widget( 'wpae-generated-badge', $archetype );
                } else {
                    $changed++;
                }
            } elseif ( is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true ) ) {
                if ( $content_shell === null ) {
                    $content_shell = $child;
                } else {
                    $content_shell['elements'] = array_merge( (array) ( $content_shell['elements'] ?? [] ), (array) ( $child['elements'] ?? [] ) );
                    $changed++;
                }
            } else {
                $content_elements[] = $child;
            }
        }

        $find_content_alignment = static function ( array $nodes ) use ( &$find_content_alignment ): string {
            foreach ( $nodes as $node ) {
                if ( ! is_array( $node ) ) {
                    continue;
                }
                if ( ( $node['elType'] ?? '' ) === 'widget' && in_array( sanitize_key( (string) ( $node['widgetType'] ?? '' ) ), [ 'heading', 'text-editor' ], true ) ) {
                    $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
                    $alignment = sanitize_key( (string) ( $settings['align'] ?? '' ) );
                    if ( in_array( $alignment, [ 'left', 'center', 'right' ], true ) ) {
                        return $alignment;
                    }
                }
                if ( is_array( $node['elements'] ?? null ) ) {
                    $alignment = $find_content_alignment( $node['elements'] );
                    if ( $alignment !== '' ) {
                        return $alignment;
                    }
                }
            }
            return '';
        };
        if ( $badge === null ) {
            $badge = wpae_llm_badge_widget( 'wpae-generated-badge', $archetype );
            $changed++;
        }
        $alignment_nodes = $content_elements;
        if ( is_array( $content_shell ) ) {
            $alignment_nodes = array_merge( (array) ( $content_shell['elements'] ?? [] ), $alignment_nodes );
        }
        $content_alignment = $find_content_alignment( $alignment_nodes );
        $badge_settings = is_array( $badge['settings'] ?? null ) ? $badge['settings'] : [];
        $badge_alignment = $content_alignment === 'center' ? 'center' : ( $content_alignment === 'right' ? 'flex-end' : 'flex-start' );
        $badge_settings['align_self'] = $badge_alignment;
        $badge_settings['custom_css'] = 'selector { width: fit-content; max-width: 100%; align-self: ' . $badge_alignment . '; flex: 0 0 auto; }';
        $badge['settings'] = $badge_settings;

        if ( $content_shell === null ) {
            $shell_settings = $original_settings;
            foreach ( [ 'background_background', 'background_color', 'background_color_b', 'background_image', 'background_overlay_background', 'background_overlay_color', 'background_overlay_color_b', 'background_overlay_opacity', 'background_position', 'background_repeat', 'background_size', 'background_hover_background', 'background_hover_color', 'background_hover_image', 'background_overlay_image', 'background_video_fallback', 'background_slideshow_gallery', 'background_overlay_video_fallback', 'background_overlay_slideshow_gallery' ] as $background_key ) {
                unset( $shell_settings[ $background_key ] );
            }
            $shell_settings['background_background'] = 'classic';
            $shell_settings['background_color'] = 'transparent';
            $shell_settings['_css_classes'] = function_exists( 'wpae_append_css_classes' )
                ? wpae_append_css_classes( $shell_settings['_css_classes'] ?? '', [ 'wpae-generated-content-shell' ] )
                : trim( (string) ( $shell_settings['_css_classes'] ?? '' ) . ' wpae-generated-content-shell' );
            $shell_settings['width'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
            $shell_settings['width_mobile'] = [ 'unit' => '%', 'size' => 100, 'sizes' => [] ];
            $content_shell = [
                'id' => (string) ( $root['id'] ?? 'wpae-preserved-library' ) . '-content-shell',
                'elType' => 'container',
                'settings' => $shell_settings,
                'elements' => $content_elements,
            ];
            $changed++;
        } elseif ( $content_elements ) {
            $content_shell['elements'] = array_merge( (array) ( $content_shell['elements'] ?? [] ), $content_elements );
            $changed++;
        }

        $root['settings']['container_type'] = 'flex';
        $root['settings']['content_width'] = 'full';
        $root['settings']['background_background'] = 'classic';
        $root['settings']['background_color'] = 'transparent';
        $root['settings']['flex_direction'] = 'column';
        $root['settings']['flex_direction_mobile'] = 'column';
        $root['settings']['flex_wrap'] = 'nowrap';
        $root['settings']['flex_justify_content'] = 'flex-start';
        $root['settings']['flex_align_items'] = 'stretch';
        $root['settings']['flex_gap'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
        $root['settings']['flex_gap_mobile'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
        $root['settings']['padding'] = [ 'unit' => 'rem', 'top' => '0', 'right' => $archetype === 'team' ? '1.5' : '0', 'bottom' => '0', 'left' => $archetype === 'team' ? '1.5' : '0', 'isLinked' => false ];
        $root['settings']['padding_mobile'] = [ 'unit' => 'rem', 'top' => '0', 'right' => $archetype === 'team' ? '1.25' : '0', 'bottom' => '0', 'left' => $archetype === 'team' ? '1.25' : '0', 'isLinked' => false ];
        $content_shell['elements'] = wpae_llm_convert_icon_boxes_to_native_widgets( (array) ( $content_shell['elements'] ?? [] ), $changed );
        if ( $archetype === 'team' ) {
            $content_shell['settings'] = is_array( $content_shell['settings'] ?? null ) ? $content_shell['settings'] : [];
            foreach ( [ 'padding' => '1.5', 'padding_mobile' => '1.25' ] as $padding_key => $top ) {
                if ( ! is_array( $content_shell['settings'][ $padding_key ] ?? null ) ) {
                    continue;
                }
                $content_shell['settings'][ $padding_key ]['top'] = $top;
                $changed++;
            }
            $content_shell['elements'] = wpae_llm_promote_team_section_heading( (array) ( $content_shell['elements'] ?? [] ), $changed );
        }
        $root['elements'] = [ $badge, $content_shell ];
        $root['elements'] = wpae_llm_remove_redundant_badge_label_content( $root['elements'], $archetype, $changed );
        $changed++;
        $elements[ $index ] = $root;
    }

    return $elements;
}

function wpae_llm_card_icon_widget( string $id, array $source = [] ): array {
    $source_settings = is_array( $source['settings'] ?? null ) ? $source['settings'] : [];
    $selected_icon = is_array( $source_settings['selected_icon'] ?? null ) ? $source_settings['selected_icon'] : [];
    if ( trim( (string) ( $selected_icon['value'] ?? '' ) ) === '' ) {
        $selected_icon = [ 'value' => 'fas fa-star', 'library' => 'fa-solid' ];
    }
    return [
        'id' => $id,
        'elType' => 'widget',
        'widgetType' => 'icon',
        'settings' => [
            'selected_icon' => $selected_icon,
            'primary_color' => (string) ( $source_settings['icon_color'] ?? '#1f2937' ),
            'size' => is_array( $source_settings['icon_size'] ?? null ) ? $source_settings['icon_size'] : [ 'unit' => 'rem', 'size' => 1.25 ],
            'align' => (string) ( $source_settings['align'] ?? 'left' ),
            'content_width' => 'full',
            '_css_classes' => 'wpae-card-icon',
        ],
        'elements' => [],
    ];
}

function wpae_llm_card_has_visual_icon( array $elements ): bool {
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) || ( $element['elType'] ?? '' ) !== 'widget' ) {
            continue;
        }
        if ( in_array( sanitize_key( (string) ( $element['widgetType'] ?? '' ) ), [ 'icon', 'image' ], true ) ) {
            return true;
        }
    }
    return false;
}

function wpae_llm_card_heading_widget( string $id, array $source ): array {
    $source_settings = is_array( $source['settings'] ?? null ) ? $source['settings'] : [];
    $title = trim( (string) ( $source_settings['title'] ?? '' ) );
    if ( $title === '' ) {
        return $source;
    }

    $title_size = strtolower( (string) ( $source_settings['header_size'] ?? 'h4' ) );
    if ( ! in_array( $title_size, [ 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
        $title_size = 'h4';
    }
    return [
        'id' => $id,
        'elType' => 'widget',
        'widgetType' => 'heading',
        'settings' => [
            'title' => $title,
            'header_size' => $title_size,
            'title_color' => (string) ( $source_settings['title_color'] ?? '#111827' ),
            'typography_typography' => 'custom',
            'typography_font_size' => [ 'unit' => 'rem', 'size' => 1.25 ],
            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.2 ],
            'typography_font_weight' => '600',
            'align' => 'left',
            '_css_classes' => 'wpae-card-heading',
        ],
        'elements' => [],
    ];
}

function wpae_llm_convert_icon_boxes_to_native_widgets( array $elements, int &$changed ): array {
    $normalized = [];
    $has_visual_sibling = wpae_llm_card_has_visual_icon( $elements );
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
        if ( ( $element['elType'] ?? '' ) === 'widget' && $widget_type === 'icon-box' ) {
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $title = trim( wp_strip_all_tags( (string) ( $settings['title_text'] ?? '' ) ) );
            $description = trim( wp_strip_all_tags( (string) ( $settings['description_text'] ?? '' ) ) );
            $replacement = [];
            if ( ! $has_visual_sibling && ! empty( $settings['selected_icon']['value'] ) ) {
                $replacement[] = wpae_llm_card_icon_widget( (string) ( $element['id'] ?? 'wpae-card' ) . '-icon', $element );
            }
            if ( $title !== '' ) {
                $replacement[] = wpae_llm_card_heading_widget( (string) ( $element['id'] ?? 'wpae-card' ) . '-heading', [ 'settings' => [ 'title' => $title, 'header_size' => $settings['title_size'] ?? 'h4', 'title_color' => $settings['title_color'] ?? '#111827' ] ] );
            }
            if ( $description !== '' ) {
                $replacement[] = [
                    'id' => (string) ( $element['id'] ?? 'wpae-card' ) . '-description',
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => [
                        'editor' => $description,
                        'text_color' => (string) ( $settings['description_color'] ?? '#6b7280' ),
                        'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                        'typography_line_height' => [ 'unit' => 'em', 'size' => 1.45 ],
                        '_css_classes' => 'wpae-card-description',
                    ],
                    'elements' => [],
                ];
            }
            if ( ! empty( $replacement ) ) {
                array_push( $normalized, ...$replacement );
                $changed++;
            }
            continue;
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_convert_icon_boxes_to_native_widgets( $element['elements'], $changed );
        }
        $normalized[] = $element;
    }
    return $normalized;
}

function wpae_llm_card_widget_text( array $element ): string {
    $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
    $widget_type = (string) ( $element['widgetType'] ?? '' );
    $key = $widget_type === 'heading' ? 'title' : ( $widget_type === 'icon-box' ? 'title_text' : ( $widget_type === 'text-editor' ? 'editor' : '' ) );
    if ( $key === '' || ! is_scalar( $settings[ $key ] ?? null ) ) {
        return '';
    }
    $text = wp_strip_all_tags( html_entity_decode( (string) $settings[ $key ], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

function wpae_llm_is_probable_card_heading( string $text ): bool {
    $text = trim( preg_replace( '/\s+/u', ' ', $text ) );
    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
    if ( $text === '' || $length > 72 ) {
        return false;
    }
    if ( preg_match( '/^\s*[«"“„]|[»"”]\s*$/u', $text ) || preg_match( '/[.!?…]\s*$/u', $text ) ) {
        return false;
    }
    return true;
}

function wpae_llm_normalize_card_heading_icons( array $elements, int $parent_depth = -1, int &$changed = 0, string $archetype = '', bool $inside_bento_grid = false ): array {
    $nested_container_count = count( array_filter( $elements, static fn( $item ) => is_array( $item ) && ( $item['elType'] ?? '' ) === 'container' ) );
    $is_card_contents = $inside_bento_grid && $parent_depth >= 1 && $nested_container_count === 0;

    if ( $is_card_contents && $archetype === 'testimonials' ) {
        for ( $index = 0, $element_count = count( $elements ); $index < $element_count; $index++ ) {
            $element = $elements[ $index ] ?? null;
            if ( ! is_array( $element ) || ( $element['elType'] ?? '' ) !== 'widget' ) {
                continue;
            }
            $widget_type = (string) ( $element['widgetType'] ?? '' );
            if ( $widget_type === 'icon-box' ) {
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $title = trim( wp_strip_all_tags( (string) ( $settings['title_text'] ?? '' ) ) );
                $description = trim( wp_strip_all_tags( (string) ( $settings['description_text'] ?? '' ) ) );
                if ( $title !== '' || $description !== '' ) {
                    $replacement = [];
                    if ( $title !== '' ) {
                        $author = $element;
                        $author['widgetType'] = 'heading';
                        $author['settings'] = [
                            'title' => $title,
                            'header_size' => 'h6',
                            'title_color' => (string) ( $settings['title_color'] ?? '#111827' ),
                            'typography_typography' => 'custom',
                            'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 0.95 ],
                            'typography_font_weight' => '600',
                            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.2 ],
                            'align' => 'left',
                            '_css_classes' => 'wpae-testimonial-author',
                        ];
                        $replacement[] = $author;
                    }
                    if ( $description !== '' ) {
                        $quote = $element;
                        $quote['id'] = (string) ( $element['id'] ?? 'wpae-testimonial' ) . '-quote';
                        $quote['widgetType'] = 'text-editor';
                        $quote['settings'] = [
                            'editor' => $description,
                            'text_color' => (string) ( $settings['description_color'] ?? '#6b7280' ),
                            'typography_typography' => 'custom',
                            'typography_font_size' => [ 'unit' => 'rem', 'size' => 1 ],
                            'typography_font_size_mobile' => [ 'unit' => 'rem', 'size' => 0.95 ],
                            'typography_line_height' => [ 'unit' => 'em', 'size' => 1.45 ],
                            'typography_line_height_mobile' => [ 'unit' => 'em', 'size' => 1.4 ],
                            'align' => 'left',
                            '_css_classes' => 'wpae-testimonial-quote',
                        ];
                        $replacement[] = $quote;
                    }
                    array_splice( $elements, $index, 1, $replacement );
                    $element_count += count( $replacement ) - 1;
                    $index += count( $replacement ) - 1;
                    $changed += count( $replacement );
                    continue;
                }
            }
            $text = wpae_llm_card_widget_text( $element );
            if ( in_array( $widget_type, [ 'heading', 'icon-box' ], true ) && ! wpae_llm_is_probable_card_heading( $text ) ) {
                $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $quote = $text;
                $description = trim( wp_strip_all_tags( (string) ( $settings['description_text'] ?? '' ) ) );
                if ( $description !== '' && $description !== $quote ) {
                    $quote .= ' ' . $description;
                }
                if ( $quote !== '' ) {
                    $element['widgetType'] = 'text-editor';
                    $element['settings'] = [ 'editor' => $quote ];
                    $elements[ $index ] = $element;
                    $changed++;
                }
            }
        }
    }

    $has_marked_card_heading = false;
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) || (string) ( $element['widgetType'] ?? '' ) !== 'icon-box' ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        if ( is_array( $classes ) && in_array( 'wpae-card-heading', $classes, true ) && wpae_llm_is_probable_card_heading( wpae_llm_card_widget_text( $element ) ) ) {
            $has_marked_card_heading = true;
            break;
        }
    }

    $candidate_index = null;
    if ( $is_card_contents && $archetype !== 'testimonials' && ! $has_marked_card_heading ) {
        foreach ( $elements as $index => $element ) {
            if ( is_array( $element ) && ( $element['elType'] ?? '' ) === 'widget' && ( $element['widgetType'] ?? '' ) === 'heading' && wpae_llm_is_probable_card_heading( wpae_llm_card_widget_text( $element ) ) ) {
                $candidate_index = $index;
                break;
            }
        }
        if ( $candidate_index === null && $archetype === 'testimonials' ) {
            foreach ( array_reverse( $elements, true ) as $index => $element ) {
                if ( is_array( $element ) && ( $element['elType'] ?? '' ) === 'widget' && ( $element['widgetType'] ?? '' ) === 'text-editor' && wpae_llm_is_probable_card_heading( wpae_llm_card_widget_text( $element ) ) ) {
                    $candidate_index = $index;
                    break;
                }
            }
        }
    }

    $card_heading_insertions = [];
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $element_type = (string) ( $element['elType'] ?? '' );
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $element_settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $element_classes = preg_split( '/\s+/', trim( (string) ( $element_settings['_css_classes'] ?? '' ) ) );
        if ( $element_type === 'container' && is_array( $element_classes ) && in_array( 'wpae-generated-badge', $element_classes, true ) ) {
            $elements[ $index ] = $element;
            continue;
        }
        if ( $index === $candidate_index ) {
            if ( $widget_type === 'text-editor' ) {
                $element['settings'] = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
                $element['settings']['title'] = wpae_llm_card_widget_text( $element );
            }
            $card_heading = wpae_llm_card_heading_widget( (string) ( $element['id'] ?? 'wpae-card-heading' ) . '-icon', $element );
            if ( $archetype === 'testimonials' ) {
                $card_heading['settings']['title_size'] = 'h6';
                $card_heading['settings']['title_typography_typography'] = 'custom';
                $card_heading['settings']['title_typography_font_size'] = [ 'unit' => 'rem', 'size' => 1 ];
                $card_heading['settings']['title_typography_font_weight'] = '600';
                $card_heading['settings']['title_typography_line_height'] = [ 'unit' => 'em', 'size' => 1.2 ];
            }
            if ( $archetype !== 'testimonials' && ! wpae_llm_card_has_visual_icon( $elements ) ) {
                // Defer insertion until the loop ends; mutating this array here skips the next card widget.
                $card_heading_insertions[ $index ] = [ wpae_llm_card_icon_widget( (string) ( $element['id'] ?? 'wpae-card-heading' ) . '-icon', $element ), $card_heading ];
                $changed += 2;
            } else {
                $elements[ $index ] = $card_heading;
                $changed++;
            }
            continue;
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            $element_depth = $element_type === 'container' ? $parent_depth + 1 : $parent_depth;
            $is_bento_container = false;
            if ( $element_type === 'container' ) {
                $is_bento_container = is_array( $element_classes ) && in_array( 'wpae-bento-grid', $element_classes, true );
            }
            $inside_card_collection = $inside_bento_grid || $is_bento_container;
            if ( $archetype === 'testimonials' && $element_type === 'widget' && in_array( $widget_type, [ 'nested-carousel', 'n-carousel' ], true ) ) {
                $inside_card_collection = true;
            }
            $element['elements'] = wpae_llm_normalize_card_heading_icons( $element['elements'], $element_depth, $changed, $archetype, $inside_card_collection );
        }
        $elements[ $index ] = $element;
    }

    if ( ! empty( $card_heading_insertions ) ) {
        $normalized_elements = [];
        foreach ( $elements as $index => $element ) {
            if ( isset( $card_heading_insertions[ $index ] ) ) {
                array_push( $normalized_elements, ...$card_heading_insertions[ $index ] );
                continue;
            }
            $normalized_elements[] = $element;
        }
        $elements = $normalized_elements;
    }

    return $elements;
}

function wpae_llm_apply_generation_visual_grammar( array $elements, string $archetype, int &$changed = 0 ): array {
    foreach ( $elements as $index => $root ) {
        if ( ! is_array( $root ) || (string) ( $root['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $root['settings'] = is_array( $root['settings'] ?? null ) ? $root['settings'] : [];
        $root['elements'] = is_array( $root['elements'] ?? null ) ? $root['elements'] : [];
        $original_settings = $root['settings'];
        if ( wpae_llm_normalize_generated_container_spacing( $root['settings'] ) ) {
            $changed++;
        }
        if ( $archetype === 'mega_menu' ) {
            $badge = wpae_llm_badge_widget( 'wpae-generated-badge', $archetype );
            $content_shell = [
                'id' => (string) ( $root['id'] ?? 'wpae-mega-menu' ) . '-content-shell',
                'elType' => 'container',
                'settings' => $original_settings,
                'elements' => wpae_llm_wrap_generation_cta( $root['elements'], $changed ),
            ];
            $root['settings']['container_type'] = 'flex';
            $root['settings']['content_width'] = 'full';
            $root['settings']['background_background'] = 'classic';
            $root['settings']['background_color'] = 'transparent';
            $root['settings']['flex_direction'] = 'column';
            $root['settings']['flex_direction_mobile'] = 'column';
            $root['settings']['flex_wrap'] = 'nowrap';
            $root['settings']['flex_justify_content'] = 'flex-start';
            $root['settings']['flex_align_items'] = 'stretch';
            $root['settings']['flex_gap'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
            $root['settings']['flex_gap_mobile'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
            $root['settings']['padding'] = [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
            $root['settings']['padding_mobile'] = [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
            foreach ( [ 'grid_columns_grid', 'grid_columns_grid_tablet', 'grid_columns_grid_mobile', 'grid_rows_grid', 'grid_gaps', 'grid_align_items' ] as $grid_key ) {
                unset( $root['settings'][ $grid_key ] );
            }
            $root['elements'] = [ $badge, $content_shell ];
            $changed++;
            $elements[ $index ] = $root;
            continue;
        }
        $root['settings']['background_background'] = 'classic';
        $root['settings']['background_color'] = 'transparent';
        $root_classes = preg_split( '/\s+/', trim( (string) ( $root['settings']['_css_classes'] ?? '' ) ) );
        $root_was_bento_grid = is_array( $root_classes ) && in_array( 'wpae-bento-grid', $root_classes, true );
        $badge = null;
        $content_shell = null;
        $content_elements = [];
        foreach ( $root['elements'] as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-generated-badge', $classes, true ) ) {
                $badge = wpae_llm_badge_widget( 'wpae-generated-badge', $archetype );
            } elseif ( is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true ) ) {
                $content_shell = $child;
            } else {
                $content_elements[] = $child;
            }
        }
        if ( ! $badge ) {
            $badge = wpae_llm_badge_widget( 'wpae-generated-badge', $archetype );
            $changed++;
        }
        $root['elements'] = $content_shell ? [ $badge, $content_shell ] : array_merge( [ $badge ], $content_elements );
        $root['elements'] = wpae_llm_normalize_card_heading_icons( $root['elements'], 0, $changed, $archetype );
        $root['elements'] = wpae_llm_convert_icon_boxes_to_native_widgets( $root['elements'], $changed );
        $badge = $root['elements'][0] ?? $badge;
        $content_shell = null;
        $content_elements = [];
        foreach ( array_slice( $root['elements'], 1 ) as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            $child_settings = is_array( $child['settings'] ?? null ) ? $child['settings'] : [];
            $classes = preg_split( '/\s+/', trim( (string) ( $child_settings['_css_classes'] ?? '' ) ) );
            if ( is_array( $classes ) && in_array( 'wpae-generated-content-shell', $classes, true ) ) {
                $content_shell = $child;
            } else {
                $content_elements[] = $child;
            }
        }
        if ( ! $content_shell ) {
            $original_gap = $original_settings['flex_gap'] ?? $original_settings['gap'] ?? [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
            $shell_card_count = 0;
            if ( $root_was_bento_grid ) {
                foreach ( $content_elements as $content_element ) {
                    if ( is_array( $content_element ) && ( $content_element['elType'] ?? '' ) === 'container' ) {
                        $shell_card_count++;
                    }
                }
            }
            $shell_classes = 'wpae-generated-content-shell';
            if ( $shell_card_count >= 2 ) {
                $shell_classes .= ' wpae-bento-grid';
            }
            $content_shell = [
                'id' => (string) ( $root['id'] ?? 'wpae-generated' ) . '-content-shell',
                'elType' => 'container',
                'settings' => [
                    '_css_classes' => $shell_classes,
                    'content_width' => 'full',
                    'flex_direction' => (string) ( $original_settings['flex_direction'] ?? 'column' ),
                    'flex_direction_mobile' => (string) ( $original_settings['flex_direction_mobile'] ?? 'column' ),
                    'flex_wrap' => (string) ( $original_settings['flex_wrap'] ?? 'nowrap' ),
                    'flex_wrap_mobile' => (string) ( $original_settings['flex_wrap_mobile'] ?? 'nowrap' ),
                    'flex_justify_content' => (string) ( $original_settings['flex_justify_content'] ?? 'flex-start' ),
                    'flex_align_items' => (string) ( $original_settings['flex_align_items'] ?? 'stretch' ),
                    'flex_align_items_mobile' => (string) ( $original_settings['flex_align_items_mobile'] ?? 'stretch' ),
                    'flex_gap' => $original_gap,
                    'flex_gap_mobile' => $original_settings['flex_gap_mobile'] ?? [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ],
                    'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                    'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
                    'background_background' => 'classic',
                    'background_color' => 'transparent',
                    'width' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    'width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    '_element_width' => 'initial',
                    '_element_custom_width' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    '_element_width_mobile' => 'initial',
                    '_element_custom_width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    '_flex_size' => 'custom',
                    '_flex_grow' => 0,
                    '_flex_shrink' => 0,
                    '_flex_size_mobile' => 'custom',
                    '_flex_grow_mobile' => 0,
                    '_flex_shrink_mobile' => 0,
                ],
                'elements' => $content_elements,
            ];
            $changed++;
        } else {
            $content_shell_settings = is_array( $content_shell['settings'] ?? null ) ? $content_shell['settings'] : [];
            $content_shell_settings['background_background'] = 'classic';
            $content_shell_settings['background_color'] = 'transparent';
            if ( $root_was_bento_grid ) {
                $shell_card_count = 0;
                foreach ( (array) ( $content_shell['elements'] ?? [] ) as $content_element ) {
                    if ( is_array( $content_element ) && ( $content_element['elType'] ?? '' ) === 'container' ) {
                        $shell_card_count++;
                    }
                }
                if ( $shell_card_count >= 2 ) {
                    $content_shell_settings['_css_classes'] = function_exists( 'wpae_append_css_classes' ) ? wpae_append_css_classes( $content_shell_settings['_css_classes'] ?? '', [ 'wpae-bento-grid' ] ) : trim( (string) ( $content_shell_settings['_css_classes'] ?? '' ) . ' wpae-bento-grid' );
                }
            }
            $content_shell['settings'] = $content_shell_settings;
            if ( $content_elements ) {
                $content_shell['elements'] = array_merge( is_array( $content_shell['elements'] ?? null ) ? $content_shell['elements'] : [], $content_elements );
            }
        }
        if ( $root_was_bento_grid ) {
            $root_classes = array_values( array_filter( (array) $root_classes, static fn( $class ) => $class !== 'wpae-bento-grid' ) );
            $root['settings']['_css_classes'] = trim( implode( ' ', $root_classes ) );
        }
        $content_shell['elements'] = wpae_llm_wrap_generation_cta( is_array( $content_shell['elements'] ?? null ) ? $content_shell['elements'] : [], $changed );
        if ( $archetype === 'team' ) {
            $content_shell['elements'] = wpae_llm_promote_team_section_heading( $content_shell['elements'], $changed );
        }
        $root['settings']['flex_direction'] = 'column';
        $root['settings']['flex_direction_mobile'] = 'column';
        $root['settings']['flex_wrap'] = 'nowrap';
        $root['settings']['flex_justify_content'] = 'flex-start';
        $root['settings']['flex_align_items'] = 'stretch';
        $root['settings']['flex_gap'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
        $root['settings']['flex_gap_mobile'] = [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ];
        $root['elements'] = [ $badge, $content_shell ];
        $root['elements'] = wpae_llm_remove_redundant_badge_label_content( $root['elements'], $archetype, $changed );
        $changed++;
        $elements[ $index ] = $root;
    }

    return $elements;
}

function wpae_llm_build_fallback_action( string $message, int $post_id ): array {
    $archetype = wpae_llm_detect_block_archetype( $message );
    $widget = static function ( string $id, string $type, array $settings = [] ): array {
        return [ 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
    };
    $gap = [ 'column' => '1.5', 'row' => '1.5', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.5' ];
    $padding = [ 'unit' => 'rem', 'top' => '2.5', 'right' => '1.5', 'bottom' => '2.5', 'left' => '1.5', 'isLinked' => false ];
    $card = static function ( string $id, array $children ) use ( $widget ): array {
        return wpae_llm_bento_card( $id, $children );
    };
    $grid = static function ( string $id, array $cards ): array {
        return wpae_llm_bento_grid( $id, $cards );
    };
    $elements = [
        $widget( 'llm-heading', 'heading', [ 'title' => 'Новый блок', 'header_size' => 'h2' ] ),
        $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Содержательный блок под вашу задачу.' ] ),
        $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
    ];
    if ( $archetype === 'benefits' ) {
        $content_units = array_values( array_filter(
            wpae_llm_extract_requested_content( $message ),
            static fn( $unit ): bool => ! wpae_llm_is_cta_copy( (string) $unit )
        ) );
        if ( count( $content_units ) >= 3 ) {
            $section_title = array_shift( $content_units );
            $benefit_cards = [];
            foreach ( array_slice( $content_units, 0, 6 ) as $index => $unit ) {
                $card_number = (string) ( $index + 1 );
                $benefit_cards[] = $card( 'llm-benefit-' . $card_number, [
                    wpae_llm_card_heading_widget(
                        'llm-benefit-' . $card_number . '-title',
                        $widget( 'llm-benefit-' . $card_number . '-source', 'heading', [ 'title' => $unit, 'header_size' => 'h4' ] )
                    ),
                ] );
            }
            $elements = [
                $widget( 'llm-heading', 'heading', [ 'title' => $section_title, 'header_size' => 'h2' ] ),
                $grid( 'llm-benefits-grid', $benefit_cards ),
            ];
        } else {
            $elements = [
                $widget( 'llm-heading', 'heading', [ 'title' => 'Преимущества для вашего проекта', 'header_size' => 'h2' ] ),
                $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Три опоры, которые делают страницу понятной, убедительной и готовой к заявке.' ] ),
                $grid( 'llm-benefits-grid', [
                    $card( 'llm-benefit-1', [ $widget( 'llm-benefit-1-title', 'heading', [ 'title' => 'Понятная структура', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-1-copy', 'text-editor', [ 'editor' => 'Посетитель быстро понимает предложение и следующий шаг.' ] ) ] ),
                    $card( 'llm-benefit-2', [ $widget( 'llm-benefit-2-title', 'heading', [ 'title' => 'Native Elementor', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-2-copy', 'text-editor', [ 'editor' => 'Контент и стили остаются редактируемыми в визуальном редакторе.' ] ) ] ),
                    $card( 'llm-benefit-3', [ $widget( 'llm-benefit-3-title', 'heading', [ 'title' => 'Готово к росту', 'header_size' => 'h4' ] ), $widget( 'llm-benefit-3-copy', 'text-editor', [ 'editor' => 'Компоненты и адаптивная сетка не рассыпаются на мобильных устройствах.' ] ) ] ),
                ] ),
                $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
            ];
        }
    } elseif ( $archetype === 'faq' ) {
        $faq_pairs = array_slice( wpae_llm_extract_faq_content( $message ), 0, 12 );
        if ( empty( $faq_pairs ) ) {
            $faq_pairs = [
                [ 'label' => 'Можно ли изменить блок позже?', 'content' => 'Да, все основные настройки остаются доступными в Elementor.' ],
                [ 'label' => 'Будет ли версия для мобильных?', 'content' => 'Да, композиция и spacing задаются с учетом mobile-first.' ],
            ];
        }
        $faq_cards = [];
        foreach ( $faq_pairs as $index => $pair ) {
            $question = trim( (string) ( $pair['label'] ?? '' ) );
            if ( $question !== '' && ! preg_match( '/[?؟]$/u', $question ) ) {
                $question .= '?';
            }
            $faq_card_id = 'llm-faq-' . (string) ( $index + 1 );
            $faq_heading = wpae_llm_card_heading_widget(
                $faq_card_id . '-title',
                $widget( $faq_card_id . '-source', 'heading', [ 'title' => $question, 'header_size' => 'h4' ] )
            );
            $faq_cards[] = $card( $faq_card_id, [
                $faq_heading,
                $widget( $faq_card_id . '-answer', 'text-editor', [ 'editor' => trim( (string) ( $pair['content'] ?? '' ) ) ] ),
            ] );
        }
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Частые вопросы', 'header_size' => 'h2' ] ),
            $grid( 'llm-faq-grid', $faq_cards ),
            $widget( 'llm-button', 'button', [ 'text' => 'Задать вопрос', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'process' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Как проходит работа', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Понятный маршрут от первой задачи до готовой страницы.' ] ),
            wpae_llm_build_process_timeline( wpae_llm_process_timeline_steps( $message ), 'wpae-process-timeline', wpae_llm_process_timeline_layout( $message ) ),
            $widget( 'llm-button', 'button', [ 'text' => 'Начать проект', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'pricing' ) {
        $pricing_pairs = array_slice( wpae_llm_extract_pricing_content( $message ), 0, 8 );
        $pricing_layout_changed = 0;
        $pricing_layout = wpae_llm_build_pricing_pair_layout( [], $pricing_pairs, $pricing_layout_changed );
        if ( ! empty( $pricing_layout[0]['elements'] ) ) {
            $elements = $pricing_layout[0]['elements'];
        } else {
            $elements = [
                $widget( 'llm-heading', 'heading', [ 'title' => 'Выберите формат работы', 'header_size' => 'h2' ] ),
                $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Соберите нужный объем работ и начните с понятного следующего шага.' ] ),
                $grid( 'llm-pricing-grid', [
                    $card( 'llm-pricing-1', [ $widget( 'llm-pricing-1-title', 'heading', [ 'title' => 'Лендинг', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-1-copy', 'text-editor', [ 'editor' => '<strong>Быстрый запуск</strong><br>Один экран или страница услуги с ясным оффером, структурой и CTA.' ] ), $widget( 'llm-pricing-1-button', 'button', [ 'text' => 'Обсудить формат', 'link' => [ 'url' => '#contact' ] ] ) ] ),
                    $card( 'llm-pricing-2', [ $widget( 'llm-pricing-2-title', 'heading', [ 'title' => 'Система страниц', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-2-copy', 'text-editor', [ 'editor' => '<strong>Масштабируемая структура</strong><br>Несколько связанных страниц, единая дизайн-система и путь клиента.' ] ), $widget( 'llm-pricing-2-button', 'button', [ 'text' => 'Получить расчет', 'link' => [ 'url' => '#contact' ] ] ) ] ),
                    $card( 'llm-pricing-3', [ $widget( 'llm-pricing-3-title', 'heading', [ 'title' => 'Поддержка', 'header_size' => 'h4' ] ), $widget( 'llm-pricing-3-copy', 'text-editor', [ 'editor' => '<strong>Развитие проекта</strong><br>Точечные улучшения, новые блоки и контроль качества после запуска.' ] ), $widget( 'llm-pricing-3-button', 'button', [ 'text' => 'Задать вопрос', 'link' => [ 'url' => '#contact' ] ] ) ] ),
                ] ),
            ];
        }
    } elseif ( $archetype === 'team' ) {
        $pairs = array_slice( wpae_llm_extract_labeled_content( $message ), 0, 4 );
        if ( count( $pairs ) < 2 ) {
            $pairs = [
                [ 'label' => 'Стратегия', 'content' => 'Помогаем определить цель и собрать ясный план.' ],
                [ 'label' => 'Дизайн', 'content' => 'Превращаем смысл проекта в понятную визуальную систему.' ],
            ];
        }
        $cards = [];
        foreach ( $pairs as $index => $pair ) {
            $number = (string) ( $index + 1 );
            $cards[] = $card( 'llm-team-' . $number, [
                $widget( 'llm-team-' . $number . '-title', 'heading', [ 'title' => (string) $pair['label'], 'header_size' => 'h4' ] ),
                $widget( 'llm-team-' . $number . '-copy', 'text-editor', [ 'editor' => (string) $pair['content'] ] ),
            ] );
        }
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Наша команда', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Специалисты, которые ведут проект от идеи до результата.' ] ),
            $grid( 'llm-team-grid', $cards ),
            $widget( 'llm-button', 'button', [ 'text' => 'Связаться', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'testimonials' ) {
        $card = static function ( string $id, string $quote, string $author ) use ( $widget ): array {
            return [
                'id' => $id,
                'elType' => 'container',
                'settings' => [
                    'content_width' => 'full',
                    'flex_direction' => 'column',
                    'background_background' => 'classic',
                    'background_color' => '#f7f7f5',
                    'border_border' => 'solid',
                    'border_color' => '#e5e7eb',
                    'border_width' => [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ],
                    'border_radius' => [ 'unit' => 'rem', 'size' => 1, 'isLinked' => true ],
                    'flex_gap' => [ 'column' => '0.75', 'row' => '0.75', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.75' ],
                    'padding' => [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ],
                    'padding_mobile' => [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ],
                    'width' => [ 'unit' => '%', 'size' => 31 ],
                    'width_mobile' => [ 'unit' => '%', 'size' => 100 ],
                    '_element_width' => 'initial',
                    '_element_custom_width' => [ 'unit' => '%', 'size' => 31, 'sizes' => [] ],
                    '_element_width_mobile' => 'initial',
                    '_element_custom_width_mobile' => [ 'unit' => '%', 'size' => 100, 'sizes' => [] ],
                    '_flex_size' => 'custom',
                    '_flex_grow' => 0,
                    '_flex_shrink' => 0,
                    '_flex_size_mobile' => 'custom',
                    '_flex_grow_mobile' => 0,
                    '_flex_shrink_mobile' => 0,
                ],
                'elements' => [
                    $widget( $id . '-quote', 'text-editor', [ 'editor' => $quote ] ),
                    $widget( $id . '-author', 'heading', [ 'title' => $author, 'header_size' => 'h5' ] ),
                ],
            ];
        };
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Что говорят клиенты', 'header_size' => 'h2' ] ),
            [ 'id' => 'llm-testimonial-grid', 'elType' => 'container', 'settings' => [ 'content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'stretch', 'flex_gap' => [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ], 'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ], 'padding' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ], 'padding_mobile' => [ 'unit' => 'rem', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ] ], 'elements' => [
                $card( 'llm-card-1', '«Стало сразу понятно, что мы продаем и куда вести клиента.»', 'Анна, студия брендинга' ),
                $card( 'llm-card-2', '«Получили аккуратную страницу, которую можем менять сами.»', 'Руслан, сервисный бизнес' ),
                $card( 'llm-card-3', '«Новая структура заметно упростила путь клиента к заявке.»', 'Марат, консалтинговая компания' ),
            ] ],
            $widget( 'llm-button', 'button', [ 'text' => 'Обсудить проект', 'link' => [ 'url' => '#contact' ] ] ),
        ];
    } elseif ( $archetype === 'portfolio' ) {
        $elements = [
            $widget( 'llm-heading', 'heading', [ 'title' => 'Избранные проекты', 'header_size' => 'h2' ] ),
            $widget( 'llm-copy', 'text-editor', [ 'editor' => 'Показываем задачу, решение и результат каждого проекта без лишнего шума.' ] ),
            $grid( 'llm-portfolio-grid', [
                $card( 'llm-portfolio-1', [ $widget( 'llm-portfolio-1-title', 'heading', [ 'title' => 'Сервисный бизнес', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-1-copy', 'text-editor', [ 'editor' => 'Страница услуги с понятным оффером и маршрутом к заявке.' ] ) ] ),
                $card( 'llm-portfolio-2', [ $widget( 'llm-portfolio-2-title', 'heading', [ 'title' => 'Запуск продукта', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-2-copy', 'text-editor', [ 'editor' => 'Собрали структуру, которая объясняет ценность продукта с первого экрана.' ] ) ] ),
                $card( 'llm-portfolio-3', [ $widget( 'llm-portfolio-3-title', 'heading', [ 'title' => 'Редизайн', 'header_size' => 'h4' ] ), $widget( 'llm-portfolio-3-copy', 'text-editor', [ 'editor' => 'Убрали лишнее, усилили доказательства и сделали систему редактируемой.' ] ) ] ),
            ] ),
            $widget( 'llm-button', 'button', [ 'text' => 'Смотреть кейсы', 'link' => [ 'url' => '#cases' ] ] ),
        ];
    } elseif ( $archetype === 'cta' ) {
        $units = array_values( array_filter( wpae_llm_content_units( $message ), static fn( $unit ): bool => ! wpae_llm_is_cta_copy( (string) $unit ) ) );
        $title = (string) ( $units[0] ?? 'Свяжитесь с нами' );
        $pairs = array_slice( wpae_llm_extract_labeled_content( $message ), 0, 4 );
        $cta = '';
        foreach ( wpae_llm_extract_requested_content( $message ) as $value ) {
            if ( wpae_llm_is_cta_copy( (string) $value ) ) {
                $cta = wpae_llm_compact_cta_text( (string) $value );
                break;
            }
        }
        $contact_elements = [];
        foreach ( $pairs as $index => $pair ) {
            $contact_elements[] = $card( 'llm-contact-' . (string) ( $index + 1 ), [
                $widget( 'llm-contact-' . (string) ( $index + 1 ) . '-title', 'heading', [ 'title' => (string) $pair['label'], 'header_size' => 'h4' ] ),
                $widget( 'llm-contact-' . (string) ( $index + 1 ) . '-copy', 'text-editor', [ 'editor' => (string) $pair['content'] ] ),
            ] );
        }
        $elements = [ $widget( 'llm-heading', 'heading', [ 'title' => $title, 'header_size' => 'h2' ] ) ];
        if ( count( $contact_elements ) >= 2 ) {
            $elements[] = $grid( 'llm-contact-grid', $contact_elements );
        } elseif ( count( $units ) > 1 ) {
            $elements[] = $widget( 'llm-copy', 'text-editor', [ 'editor' => implode( ' ', array_slice( $units, 1, 3 ) ) ] );
        }
        if ( $cta !== '' ) {
            $elements[] = $widget( 'llm-button', 'button', [ 'text' => $cta, 'link' => [ 'url' => '#contact' ] ] );
        }
    }
    return [
        'action' => 'insert_elements',
        'post_id' => $post_id,
        'position' => 'end',
        'fallback_archetype' => $archetype,
        'fallback_variant' => wpae_llm_fallback_variant( $message ),
        'elements' => [ [ 'id' => 'llm-fallback', 'elType' => 'container', 'settings' => [ 'content_width' => 'boxed', 'flex_direction' => 'column', 'background_background' => 'classic', 'background_color' => 'transparent', 'flex_gap' => $gap, 'flex_gap_mobile' => [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ], 'padding' => $padding, 'padding_mobile' => [ 'unit' => 'rem', 'top' => '2', 'right' => '1', 'bottom' => '2', 'left' => '1', 'isLinked' => true ] ], 'elements' => $elements ] ],
    ];
}

function wpae_llm_normalize_generated_container_spacing( array &$settings ): bool {
    $before = wp_json_encode( $settings );
    foreach ( [ 'padding', 'padding_tablet', 'padding_mobile', '_padding', '_padding_tablet', '_padding_mobile' ] as $key ) {
        if ( ! is_array( $settings[ $key ] ?? null ) || strtolower( (string) ( $settings[ $key ]['unit'] ?? '' ) ) !== 'px' ) {
            continue;
        }
        $spacing = $settings[ $key ];
        $vertical = array_filter( [ $spacing['top'] ?? null, $spacing['bottom'] ?? null ], 'is_numeric' );
        if ( empty( $vertical ) || max( array_map( 'floatval', $vertical ) ) <= 80 ) {
            continue;
        }
        $spacing['unit'] = 'rem';
        $spacing['isLinked'] = false;
        foreach ( [ 'top', 'bottom' ] as $side ) {
            if ( is_numeric( $spacing[ $side ] ?? null ) ) {
                $spacing[ $side ] = (string) min( 3, max( 0, round( (float) $spacing[ $side ] / 16, 3 ) ) );
            }
        }
        foreach ( [ 'right', 'left' ] as $side ) {
            if ( is_numeric( $spacing[ $side ] ?? null ) ) {
                $spacing[ $side ] = (string) min( 2.5, max( 0, round( (float) $spacing[ $side ] / 16, 3 ) ) );
            }
        }
        $settings[ $key ] = $spacing;
    }
    return $before !== wp_json_encode( $settings );
}

function wpae_llm_normalize_library_layout( array $elements, int &$changed = 0, string $archetype = '' ): array {
    $defaults = [
        'team' => [ 'title' => 'Наша команда', 'description' => 'Специалисты, которые ведут проект от идеи до результата.', 'cta' => 'Связаться', 'card' => 'Специалист' ],
        'testimonials' => [ 'title' => 'Что говорят клиенты', 'description' => 'Реальные результаты клиентов вместо общих обещаний.', 'cta' => 'Обсудить проект', 'card' => 'Отзыв' ],
        'about' => [ 'title' => 'О компании', 'description' => 'Работаем прозрачно, объясняем сложное и доводим до результата.', 'cta' => 'Обсудить проект', 'card' => 'Принцип' ],
        'portfolio' => [ 'title' => 'Избранные проекты', 'description' => 'Показываем задачу, решение и результат без лишнего шума.', 'cta' => 'Смотреть проекты', 'card' => 'Проект' ],
        'image-box' => [ 'title' => 'Избранные проекты', 'description' => 'Показываем задачу, решение и результат без лишнего шума.', 'cta' => 'Смотреть проекты', 'card' => 'Проект' ],
    ];
    $copyelement_defaults = $defaults[ $archetype ] ?? $defaults['portfolio'];
    $library_image_sets = [
        'testimonials' => [
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=900&q=80',
        ],
        'team' => [
            'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=900&q=80',
        ],
        'about' => [
            'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
        ],
        'portfolio' => [
            'https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1559028012-481c04fa702d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
        ],
        'image-box' => [
            'https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1559028012-481c04fa702d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
        ],
    ];
    $library_image_alt = [
        'testimonials' => 'Портрет клиента',
        'team' => 'Член команды',
        'about' => 'Команда проекта',
        'portfolio' => 'Избранный проект',
        'image-box' => 'Избранный проект',
    ];
    $is_library_image_placeholder = static function ( string $url ): bool {
        $normalized = strtolower( trim( $url ) );
        return $normalized === ''
            || strpos( $normalized, 'new-container-image-' ) !== false
            || strpos( $normalized, 'image-placeholder' ) !== false
            || strpos( $normalized, 'placeholder-image' ) !== false;
    };
    $next_library_image = static function ( int $index ) use ( $library_image_sets, $archetype ): string {
        $images = $library_image_sets[ $archetype ] ?? $library_image_sets['portfolio'];
        return (string) ( $images[ $index % count( $images ) ] ?? $images[0] );
    };
    $is_placeholder = static function ( $value ): bool {
        $normalized = wpae_llm_normalize_content_text( $value );
        return $normalized === 'sample subtitle'
            || $normalized === 'sample title'
            || $normalized === 'new block title'
            || $normalized === 'block title'
            || $normalized === 'section title'
            || $normalized === 'заголовок блока'
            || $normalized === 'текст заголовка'
            || $normalized === 'новый блок'
            || strpos( $normalized, 'quis autem' ) === 0
            || strpos( $normalized, 'amnis natus' ) === 0
            || strpos( $normalized, 'lorem ipsum' ) === 0
            || strpos( $normalized, 'lorem dolor' ) === 0
            || strpos( $normalized, 'sed ut unde omnis' ) === 0
            || strpos( $normalized, 'volur tatem accus' ) !== false;
    };
    $contains_card_signal = static function ( array $nodes ) use ( &$contains_card_signal, $archetype ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( ( $node['elType'] ?? '' ) === 'widget' ) {
                $widget_type = sanitize_key( (string) ( $node['widgetType'] ?? '' ) );
                if ( in_array( $widget_type, [ 'icon-box', 'testimonial' ], true ) || ( $archetype === 'about' && $widget_type === 'counter' ) ) {
                    return true;
                }
            }
            if ( is_array( $node['elements'] ?? null ) && $contains_card_signal( $node['elements'] ) ) {
                return true;
            }
        }
        return false;
    };
    $has_meaningful_descendant = static function ( array $nodes ) use ( &$has_meaningful_descendant ): bool {
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            if ( ( $node['elType'] ?? '' ) === 'widget' ) {
                return true;
            }
            $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
            foreach ( [ 'background_image', 'background_overlay_image', 'background_hover_image' ] as $key ) {
                if ( is_array( $settings[ $key ] ?? null ) && trim( (string) ( $settings[ $key ]['url'] ?? '' ) ) !== '' ) {
                    return true;
                }
            }
            if ( is_array( $node['elements'] ?? null ) && $has_meaningful_descendant( $node['elements'] ) ) {
                return true;
            }
        }
        return false;
    };
    $placeholder_heading_index = 0;
    $library_image_index = 0;
    $walk = static function ( array $nodes, int $depth = 0 ) use ( &$walk, &$changed, $archetype, $copyelement_defaults, $is_placeholder, $contains_card_signal, $has_meaningful_descendant, &$placeholder_heading_index, &$library_image_index, $is_library_image_placeholder, $next_library_image, $library_image_alt ): array {
        $normalized_nodes = [];
        foreach ( $nodes as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $element_type = (string) ( $element['elType'] ?? '' );
            if ( $element_type === 'widget' ) {
                $widget_type = sanitize_key( (string) ( $element['widgetType'] ?? '' ) );
                if ( in_array( $widget_type, [ 'image', 'image-box' ], true ) ) {
                    $image = is_array( $settings['image'] ?? null ) ? $settings['image'] : [];
                    $image_url = trim( (string) ( $image['url'] ?? $settings['image_url'] ?? '' ) );
                    $image_id = absint( $image['id'] ?? $settings['image_id'] ?? 0 );
                    if ( $is_library_image_placeholder( $image_url ) && $archetype !== '' ) {
                        $image['url'] = $next_library_image( $library_image_index++ );
                        $image['id'] = 0;
                        $image['source'] = 'url';
                        $image['alt'] = (string) ( $library_image_alt[ $archetype ] ?? 'Изображение блока' );
                        $settings['image'] = $image;
                        $changed++;
                    }
                    if ( $image_url !== '' && $image_id > 0 && function_exists( 'home_url' ) && function_exists( 'wp_parse_url' ) ) {
                        $site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
                        $image_host = strtolower( (string) wp_parse_url( $image_url, PHP_URL_HOST ) );
                        if ( $site_host !== '' && $image_host !== '' && $site_host !== $image_host ) {
                            $image['id'] = 0;
                            $image['source'] = 'url';
                            $settings['image'] = $image;
                            $changed++;
                        }
                    }
                }
                foreach ( [ '_element_custom_width', '_element_custom_width_tablet', '_element_custom_width_mobile' ] as $key ) {
                    if ( array_key_exists( $key, $settings ) ) {
                        unset( $settings[ $key ] );
                        $changed++;
                    }
                }
                foreach ( [ '_margin', '_margin_tablet', '_margin_mobile' ] as $key ) {
                    if ( ! is_array( $settings[ $key ] ?? null ) ) {
                        continue;
                    }
                    $negative_found = false;
                    foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                        if ( isset( $settings[ $key ][ $side ] ) && is_numeric( $settings[ $key ][ $side ] ) && (float) $settings[ $key ][ $side ] < 0 ) {
                            $settings[ $key ][ $side ] = '0';
                            $negative_found = true;
                        }
                    }
                    if ( $negative_found ) {
                        $changed++;
                    }
                }
                foreach ( [ 'animation', 'animation_duration', 'animation_delay' ] as $key ) {
                    if ( array_key_exists( $key, $settings ) ) {
                        unset( $settings[ $key ] );
                        $changed++;
                    }
                }

                $title = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
                $title_length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
                if ( $widget_type === 'heading' && strtolower( (string) ( $settings['header_size'] ?? '' ) ) === 'p' && $title_length > 80 ) {
                    $element['widgetType'] = 'text-editor';
                    $settings['editor'] = $title;
                    $settings['typography_typography'] = 'custom';
                    $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1.125 ];
                    $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.0625 ];
                    $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1 ];
                    $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.5 ];
                    if ( ! empty( $settings['title_color'] ) ) {
                        $settings['text_color'] = $settings['title_color'];
                    }
                    unset( $settings['title'], $settings['header_size'], $settings['title_color'] );
                    $widget_type = 'text-editor';
                    $changed++;
                }
                if ( $widget_type === 'heading' && $is_placeholder( $settings['title'] ?? '' ) ) {
                    $placeholder_text = wpae_llm_normalize_content_text( $settings['title'] ?? '' );
                    if ( $placeholder_heading_index === 0 && $placeholder_text === 'sample subtitle' ) {
                        $placeholder_heading_index++;
                        $changed++;
                        continue;
                    }
                    $replacement = $placeholder_heading_index <= 1 ? $copyelement_defaults['title'] : $copyelement_defaults['card'];
                    $settings['title'] = $replacement;
                    $placeholder_heading_index++;
                    $changed++;
                } elseif ( $widget_type === 'text-editor' && $is_placeholder( $settings['editor'] ?? '' ) ) {
                    $settings['editor'] = $copyelement_defaults['description'];
                    $changed++;
                } elseif ( $widget_type === 'icon-box' ) {
                    if ( $is_placeholder( $settings['title_text'] ?? '' ) ) {
                        $settings['title_text'] = $copyelement_defaults['card'];
                        $changed++;
                    }
                    if ( $is_placeholder( $settings['description_text'] ?? '' ) ) {
                        $settings['description_text'] = $copyelement_defaults['description'];
                        $changed++;
                    }
                } elseif ( $widget_type === 'button' ) {
                    $button_text = trim( wp_strip_all_tags( (string) ( $settings['text'] ?? '' ) ) );
                    if ( $button_text === '' ) {
                        continue;
                    }
                    if ( in_array( strtolower( $button_text ), [ 'learn more', 'read more', 'смотреть проекты', 'смотреть кейсы', 'view projects' ], true ) ) {
                        $settings['text'] = $copyelement_defaults['cta'];
                        $changed++;
                    }
                    if ( wpae_llm_normalize_generated_button_settings( $settings ) ) {
                        $changed++;
                    }
                }
            } elseif ( $element_type === 'container' ) {
                if ( wpae_llm_normalize_generated_container_spacing( $settings ) ) {
                    $changed++;
                }
                foreach ( [ 'height', 'height_tablet', 'height_mobile', 'min_height', 'min_height_tablet', 'min_height_mobile', 'max_height', 'max_height_tablet', 'max_height_mobile', 'flex_basis', 'flex_basis_tablet', 'flex_basis_mobile' ] as $key ) {
                    if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) && (string) ( $settings[ $key ]['size'] ?? '' ) !== '' ) {
                        unset( $settings[ $key ] );
                        $changed++;
                    }
                }
                foreach ( [ 'animation', 'animation_duration', 'animation_delay' ] as $key ) {
                    if ( array_key_exists( $key, $settings ) ) {
                        unset( $settings[ $key ] );
                        $changed++;
                    }
                }

                $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
                $child_containers = [];
                foreach ( $children as $child_index => $child ) {
                    if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                        $child_containers[] = $child_index;
                    }
                }
                $container_classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                $is_structural_container = count( $child_containers ) >= 2
                    || ( is_array( $container_classes ) && ( in_array( 'wpae-bento-grid', $container_classes, true ) || in_array( 'wpae-generated-content-shell', $container_classes, true ) ) );
                if ( $is_structural_container ) {
                    foreach ( [ 'padding', 'padding_tablet', 'padding_mobile' ] as $padding_key ) {
                        if ( ! is_array( $settings[ $padding_key ] ?? null ) ) {
                            continue;
                        }
                        $settings[ $padding_key ]['left'] = '0';
                        $settings[ $padding_key ]['right'] = '0';
                        $settings[ $padding_key ]['isLinked'] = false;
                    }
                }
                if ( $archetype === 'testimonials' && $depth >= 2 && empty( $child_containers ) && $contains_card_signal( $children ) ) {
                    $before_card = wp_json_encode( $settings );
                    $settings['container_type'] = 'flex';
                    $settings['flex_direction'] = 'column';
                    $settings['flex_wrap'] = 'nowrap';
                    $settings['flex_justify_content'] = 'flex-start';
                    $settings['flex_align_items'] = 'stretch';
                    $settings['flex_gap'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
                    $settings['flex_gap_mobile'] = [ 'column' => '0.875', 'row' => '0.875', 'isLinked' => true, 'unit' => 'rem', 'size' => '0.875' ];
                    $settings['background_background'] = 'classic';
                    $settings['background_color'] = '#ffffff';
                    $settings['border_border'] = 'solid';
                    $settings['border_color'] = '#d1d5db';
                    $settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
                    $settings['border_radius'] = [ 'unit' => 'rem', 'size' => 1, 'isLinked' => true ];
                    $settings['padding'] = [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.5', 'bottom' => '1.5', 'left' => '1.5', 'isLinked' => true ];
                    $settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1.25', 'bottom' => '1.25', 'left' => '1.25', 'isLinked' => true ];
                    if ( $before_card !== wp_json_encode( $settings ) ) {
                        $changed++;
                    }
                }
                $carousel_groups = [];
                foreach ( $children as $child_index => $child ) {
                    if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' || ! in_array( (string) ( $child['widgetType'] ?? '' ), [ 'nested-carousel', 'n-carousel' ], true ) || ! is_array( $child['elements'] ?? null ) ) {
                        continue;
                    }
                    $slide_indexes = [];
                    foreach ( $child['elements'] as $slide_index => $slide ) {
                        if ( is_array( $slide ) && ( $slide['elType'] ?? '' ) === 'container' && $contains_card_signal( (array) ( $slide['elements'] ?? [] ) ) ) {
                            $slide_indexes[] = $slide_index;
                        }
                    }
                    if ( count( $slide_indexes ) >= 2 ) {
                        $carousel_groups[ $child_index ] = $slide_indexes;
                    }
                }
                foreach ( $carousel_groups as $carousel_index => $slide_indexes ) {
                    $before_carousel = wp_json_encode( [ $settings, $children[ $carousel_index ] ] );
                    $settings['background_background'] = 'classic';
                    $settings['background_color'] = 'transparent';
                    $carousel_classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                    $carousel_classes = is_array( $carousel_classes ) ? $carousel_classes : [];
                    $carousel_classes[] = 'wpae-bento-grid';
                    $settings['_css_classes'] = implode( ' ', array_values( array_unique( array_filter( $carousel_classes ) ) ) );
                    foreach ( wpae_llm_variant_card_widths( 0, count( $slide_indexes ) ) as $width_index => $width ) {
                        $slide_index = $slide_indexes[ $width_index ] ?? null;
                        if ( $slide_index === null ) {
                            continue;
                        }
                        $slide_settings = is_array( $children[ $carousel_index ]['elements'][ $slide_index ]['settings'] ?? null ) ? $children[ $carousel_index ]['elements'][ $slide_index ]['settings'] : [];
                        $slide_settings['container_type'] = 'flex';
                        $slide_settings['flex_direction'] = 'column';
                        $slide_settings['background_background'] = 'classic';
                        $slide_settings['background_color'] = '#ffffff';
                        $slide_settings['border_border'] = 'solid';
                        $slide_settings['border_color'] = '#d1d5db';
                        $slide_settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
                        $slide_settings['border_radius'] = [ 'unit' => 'rem', 'size' => 1, 'isLinked' => true ];
                        $slide_settings['padding'] = [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ];
                        $slide_settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ];
                        wpae_llm_set_flexible_bento_container_width( $slide_settings, (float) $width );
                        $children[ $carousel_index ]['elements'][ $slide_index ]['settings'] = $slide_settings;
                    }
                    if ( $before_carousel !== wp_json_encode( [ $settings, $children[ $carousel_index ] ] ) ) {
                        $changed++;
                    }
                }
                if ( $depth > 0 && count( $child_containers ) >= 2 ) {
                    $is_card_group = true;
                    foreach ( $child_containers as $child_index ) {
                        if ( ! $contains_card_signal( (array) ( $children[ $child_index ]['elements'] ?? [] ) ) ) {
                            $is_card_group = false;
                            break;
                        }
                    }
                    $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
                    $classes = is_array( $classes ) ? array_values( array_filter( $classes, static fn( $class ) => $class !== '' && $class !== 'wpae-bento-grid' ) ) : [];
                    $before_group = wp_json_encode( [ $settings, $children ] );
                    $settings['container_type'] = 'flex';
                    $settings['background_background'] = 'classic';
                    $settings['background_color'] = 'transparent';
                    if ( $is_card_group ) {
                        $settings['flex_direction'] = 'row';
                        $settings['flex_wrap'] = 'wrap';
                        $settings['flex_justify_content'] = 'space-between';
                        $settings['flex_align_items'] = $archetype === 'testimonials' ? 'flex-start' : 'stretch';
                        if ( $archetype === 'testimonials' ) {
                            $settings['flex_align_items_mobile'] = 'flex-start';
                        }
                        $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
                        $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
                        $classes[] = 'wpae-bento-grid';
                        foreach ( wpae_llm_variant_card_widths( 0, count( $child_containers ) ) as $width_index => $width ) {
                            $child_index = $child_containers[ $width_index ] ?? null;
                            if ( $child_index === null ) {
                                continue;
                            }
                            $child_settings = is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [];
                            $child_settings['container_type'] = 'flex';
                            $child_settings['background_background'] = 'classic';
                            $child_settings['background_color'] = '#ffffff';
                            $child_settings['border_border'] = 'solid';
                            $child_settings['border_color'] = '#d1d5db';
                            $child_settings['border_width'] = [ 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ];
                            $child_settings['border_radius'] = [ 'unit' => 'rem', 'size' => 1, 'isLinked' => true ];
                            $child_settings['padding'] = [ 'unit' => 'rem', 'top' => '1.5', 'right' => '1.25', 'bottom' => '1.5', 'left' => '1.25', 'isLinked' => true ];
                            $child_settings['padding_mobile'] = [ 'unit' => 'rem', 'top' => '1.25', 'right' => '1', 'bottom' => '1.25', 'left' => '1', 'isLinked' => true ];
                            wpae_llm_set_flexible_bento_container_width( $child_settings, (float) $width );
                            $children[ $child_index ]['settings'] = $child_settings;
                        }
                    }
                    $settings['_css_classes'] = implode( ' ', array_values( array_unique( $classes ) ) );
                    if ( $before_group !== wp_json_encode( [ $settings, $children ] ) ) {
                        $changed++;
                    }
                    $element['elements'] = $children;
                }
            }

            $element['settings'] = $settings;
            if ( is_array( $element['elements'] ?? null ) ) {
                $element['elements'] = $walk( $element['elements'], $depth + 1 );
            }
            if ( $element_type === 'container' && $depth > 0 && ! $has_meaningful_descendant( [ $element ] ) ) {
                $changed++;
                continue;
            }
            $normalized_nodes[] = $element;
        }
        return $normalized_nodes;
    };

    return $walk( $elements );
}

function wpae_llm_normalize_generated_typography( array $elements, string $archetype, int $depth = 0, int &$changed = 0, bool $inside_bento_grid = false ): array {
    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $widget_type = (string) ( $element['widgetType'] ?? '' );
        $element_classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        $element_type = (string) ( $element['elType'] ?? '' );
        $is_bento_grid = $element_type === 'container'
            && is_array( $element_classes )
            && in_array( 'wpae-bento-grid', $element_classes, true );
        $is_testimonial_carousel = $archetype === 'testimonials'
            && $element_type === 'widget'
            && in_array( $widget_type, [ 'nested-carousel', 'n-carousel' ], true );
        $next_inside_bento_grid = $inside_bento_grid || $is_bento_grid || $is_testimonial_carousel;
        $is_pricing_price = $archetype === 'pricing'
            && $widget_type === 'heading'
            && is_array( $element_classes )
            && in_array( 'wpae-pricing-price', $element_classes, true );
        $before = wp_json_encode( $settings );

        if ( $widget_type === 'heading' && ( ! is_array( $element_classes ) || ! in_array( 'wpae-generated-badge-label', $element_classes, true ) ) ) {
            $is_marked_card_heading = is_array( $element_classes ) && in_array( 'wpae-card-heading', $element_classes, true );
            $is_card_heading = $is_marked_card_heading || $next_inside_bento_grid;
            $settings['header_size'] = $is_pricing_price ? 'h3' : ( $is_card_heading ? 'h4' : 'h2' );
            $settings['typography_typography'] = 'custom';
            $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => $is_pricing_price ? 2.25 : ( $is_card_heading ? 1.25 : 2.5 ) ];
            $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => $is_pricing_price ? 2 : ( $is_card_heading ? 1.15 : 2.1 ) ];
            $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => $is_pricing_price ? 1.75 : ( $is_card_heading ? 1.05 : 1.75 ) ];
            $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => $is_pricing_price ? 1.05 : ( $is_card_heading ? 1.2 : 1.1 ) ];
            $settings['typography_line_height_tablet'] = [ 'unit' => 'em', 'size' => $is_pricing_price ? 1.05 : ( $is_card_heading ? 1.2 : 1.15 ) ];
            $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => $is_pricing_price ? 1.1 : 1.15 ];
            $settings['typography_font_weight'] = $is_pricing_price ? '800' : '700';
        } elseif ( $widget_type === 'icon-box' && $next_inside_bento_grid ) {
            $settings['title_typography_typography'] = 'custom';
            $settings['title_typography_font_size'] = [ 'unit' => 'rem', 'size' => $archetype === 'testimonials' ? 1.05 : 1.1 ];
            $settings['title_typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1.05 ];
            $settings['title_typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 1 ];
            $settings['title_typography_font_weight'] = '600';
            $settings['title_typography_line_height'] = [ 'unit' => 'em', 'size' => 1.2 ];
            $settings['title_typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.2 ];
        } elseif ( $widget_type === 'text-editor' && $next_inside_bento_grid ) {
            $settings['typography_typography'] = 'custom';
            $settings['typography_font_size'] = [ 'unit' => 'rem', 'size' => 1 ];
            $settings['typography_font_size_tablet'] = [ 'unit' => 'rem', 'size' => 1 ];
            $settings['typography_font_size_mobile'] = [ 'unit' => 'rem', 'size' => 0.95 ];
            $settings['typography_line_height'] = [ 'unit' => 'em', 'size' => 1.45 ];
            $settings['typography_line_height_mobile'] = [ 'unit' => 'em', 'size' => 1.4 ];
        }

        if ( $widget_type === 'button' ) {
            wpae_llm_normalize_generated_button_settings( $settings );
        }
        if ( $before !== wp_json_encode( $settings ) ) {
            $changed++;
        }

        $element['settings'] = $settings;
        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_normalize_generated_typography( $element['elements'], $archetype, $depth + 1, $changed, $next_inside_bento_grid );
        }
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_llm_apply_bento_layout( array $elements, string $archetype, int &$changed ): array {
    $repeatable = [ 'benefits', 'pricing', 'testimonials', 'process', 'portfolio' ];
    if ( ! in_array( $archetype, $repeatable, true ) || $archetype === 'process' ) {
        return $elements;
    }

    foreach ( $elements as $index => $element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }

        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $children = is_array( $element['elements'] ?? null ) ? $element['elements'] : [];
        $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        $is_grid = is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true );
        $child_containers = [];
        foreach ( $children as $child_index => $child ) {
            if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                $child_containers[] = $child_index;
            }
        }

        if ( count( $children ) >= 3 && empty( $child_containers ) ) {
            $leading = [];
            $trailing = [];
            $repeatable_children = $children;
            if ( is_array( $repeatable_children[0] ?? null ) && ( $repeatable_children[0]['elType'] ?? '' ) === 'widget' && ( $repeatable_children[0]['widgetType'] ?? '' ) === 'heading' ) {
                $leading[] = array_shift( $repeatable_children );
            }
            if ( is_array( end( $repeatable_children ) ) && ( end( $repeatable_children )['elType'] ?? '' ) === 'widget' && ( end( $repeatable_children )['widgetType'] ?? '' ) === 'button' ) {
                $trailing[] = array_pop( $repeatable_children );
            }
            if ( count( $repeatable_children ) >= 2 && ( ! empty( $leading ) || ! empty( $trailing ) ) ) {
                $cards = [];
                foreach ( $repeatable_children as $child_index => $child ) {
                    $cards[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $child ] );
                    $changed++;
                }
                $children = array_merge( $leading, [ wpae_llm_bento_grid( 'wpae-bento-grid-' . (string) $index, $cards ) ], $trailing );
                $element['elements'] = $children;
            } elseif ( empty( $leading ) && empty( $trailing ) ) {
                $wrapped = [];
                foreach ( $children as $child_index => $child ) {
                    if ( ! is_array( $child ) || ( $child['elType'] ?? '' ) !== 'widget' ) {
                        $wrapped[] = $child;
                        continue;
                    }
                    $wrapped[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $child ] );
                    $changed++;
                }
                $children = $wrapped;
                $element['elements'] = $children;
            }
            $child_containers = [];
            foreach ( $children as $child_index => $child ) {
                if ( is_array( $child ) && ( $child['elType'] ?? '' ) === 'container' ) {
                    $child_containers[] = $child_index;
                }
            }
            $element['elements'] = $children;
        }

        $has_semantic_shell = ( is_array( $children[0] ?? null ) && ( $children[0]['elType'] ?? '' ) === 'widget' && ( $children[0]['widgetType'] ?? '' ) === 'heading' )
            || ( is_array( end( $children ) ) && ( end( $children )['elType'] ?? '' ) === 'widget' && ( end( $children )['widgetType'] ?? '' ) === 'button' );
        if ( count( $child_containers ) >= 2 && $has_semantic_shell ) {
            $first_card = (int) $child_containers[0];
            $last_card = (int) end( $child_containers );
            $leading = array_slice( $children, 0, $first_card );
            foreach ( array_slice( $children, $first_card + 1, max( 0, $last_card - $first_card - 1 ) ) as $between_index => $between ) {
                if ( ! in_array( $first_card + 1 + $between_index, $child_containers, true ) ) {
                    $leading[] = $between;
                }
            }
            $cards = [];
            foreach ( $child_containers as $child_index ) {
                $cards[] = wpae_llm_bento_card( 'wpae-bento-' . (string) $child_index, [ $children[ $child_index ] ] );
            }
            $trailing = array_slice( $children, $last_card + 1 );
            $children = array_merge( $leading, [ wpae_llm_bento_grid( 'wpae-bento-grid-' . (string) $index, $cards ) ], $trailing );
            $element['elements'] = $children;
            $child_containers = [ count( $leading ) ];
            $changed++;
        }
        if ( count( $child_containers ) >= 2 && ! $has_semantic_shell ) {
            $settings['flex_direction'] = 'row';
            $settings['flex_wrap'] = 'wrap';
            $settings['flex_justify_content'] = 'space-between';
            $settings['flex_align_items'] = 'stretch';
            $settings['flex_gap'] = [ 'column' => '1.25', 'row' => '1.25', 'isLinked' => true, 'unit' => 'rem', 'size' => '1.25' ];
            $settings['flex_gap_mobile'] = [ 'column' => '1', 'row' => '1', 'isLinked' => true, 'unit' => 'rem', 'size' => '1' ];
            $changed++;

            $count = count( $child_containers );
            foreach ( wpae_llm_variant_card_widths( 0, $count ) as $width_index => $width ) {
                $child_index = $child_containers[ $width_index ] ?? null;
                if ( $child_index === null ) {
                    continue;
                }
                $child_settings = is_array( $children[ $child_index ]['settings'] ?? null ) ? $children[ $child_index ]['settings'] : [];
                wpae_llm_set_flexible_bento_container_width( $child_settings, (float) $width );
                $children[ $child_index ]['settings'] = $child_settings;
            }
            $element['elements'] = $children;
        }

        if ( count( $child_containers ) >= 2 ) {
            $before_grid_settings = wp_json_encode( [ $settings['background_background'] ?? null, $settings['background_color'] ?? null, $settings['_css_classes'] ?? null ] );
            $settings['background_background'] = 'classic';
            $settings['background_color'] = 'transparent';
            $settings['_css_classes'] = function_exists( 'wpae_append_css_classes' ) ? wpae_append_css_classes( $settings['_css_classes'] ?? '', [ 'wpae-bento-grid' ] ) : trim( (string) ( $settings['_css_classes'] ?? '' ) . ' wpae-bento-grid' );
            if ( $before_grid_settings !== wp_json_encode( [ $settings['background_background'] ?? null, $settings['background_color'] ?? null, $settings['_css_classes'] ?? null ] ) ) {
                $changed++;
            }
        }

        if ( $is_grid ) {
            wpae_llm_normalize_bento_grid( $element, $changed, $archetype );
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : $settings;
        }

        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_apply_bento_layout( $element['elements'], $archetype, $changed );
        }
        $element['settings'] = $settings;
        $elements[ $index ] = $element;
    }

    return $elements;
}

function wpae_llm_normalize_process_card_heading( array &$elements, int $step, int &$changed ): bool {
    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        if ( ( $element['elType'] ?? '' ) === 'widget' && in_array( (string) ( $element['widgetType'] ?? '' ), [ 'heading', 'icon-box' ], true ) ) {
            $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
            $text_key = ( $element['widgetType'] ?? '' ) === 'icon-box' ? 'title_text' : 'title';
            $text = trim( (string) ( $settings[ $text_key ] ?? '' ) );
            if ( $text === '' ) {
                continue;
            }
            $text = preg_replace( '/^\s*\d{1,2}\s*\/\s*/u', '', $text );
            $normalized = sprintf( '%02d / %s', $step, trim( (string) $text ) );
            if ( $normalized !== (string) ( $settings[ $text_key ] ?? '' ) ) {
                $settings[ $text_key ] = $normalized;
                $element['settings'] = $settings;
                $changed++;
            }
            return true;
        }
        if ( is_array( $element['elements'] ?? null ) && wpae_llm_normalize_process_card_heading( $element['elements'], $step, $changed ) ) {
            return true;
        }
    }
    unset( $element );

    return false;
}

function wpae_llm_normalize_process_step_labels( array $elements, string $archetype, int &$changed ): array {
    if ( $archetype !== 'process' ) {
        return $elements;
    }

    foreach ( $elements as &$element ) {
        if ( ! is_array( $element ) ) {
            continue;
        }
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $classes = preg_split( '/\s+/', trim( (string) ( $settings['_css_classes'] ?? '' ) ) );
        if ( is_array( $classes ) && in_array( 'wpae-bento-grid', $classes, true ) ) {
            $step = 0;
            foreach ( (array) ( $element['elements'] ?? [] ) as &$card ) {
                if ( ! is_array( $card ) || ( $card['elType'] ?? '' ) !== 'container' ) {
                    continue;
                }
                $step++;
                if ( ! wpae_llm_normalize_process_card_heading( $card['elements'], $step, $changed ) ) {
                    $step--;
                }
            }
            unset( $card );
        }
        if ( is_array( $element['elements'] ?? null ) ) {
            $element['elements'] = wpae_llm_normalize_process_step_labels( $element['elements'], $archetype, $changed );
        }
    }
    unset( $element );

    return $elements;
}

function wpae_llm_decode_action( string $reply, int $post_id = 0 ): array {
    $candidate = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $reply ) );
    $decoded = json_decode( $candidate, true );
    if ( is_string( $decoded ) ) {
        $nested = json_decode( trim( $decoded ), true );
        if ( is_array( $nested ) ) {
            $decoded = $nested;
        }
    }
    if ( ! is_array( $decoded ) ) {
        $start = strpos( $candidate, '{' );
        $end = strrpos( $candidate, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $decoded = json_decode( substr( $candidate, $start, $end - $start + 1 ), true );
        }
    }
    if ( ! is_array( $decoded ) ) {
        return [
            '_wpae_diagnostics' => [
                'response_type' => 'text',
                'json_decoded' => false,
                'reply_preview' => sanitize_text_field( substr( wp_strip_all_tags( $reply ), 0, 600 ) ),
                'reply_length' => strlen( $reply ),
                'json_error' => sanitize_text_field( json_last_error_msg() ),
                'likely_truncated' => substr( rtrim( $candidate ), -1 ) !== '}',
            ],
        ];
    }
    foreach ( [ 'result', 'data', 'command' ] as $wrapper ) {
        if ( isset( $decoded[ $wrapper ] ) && is_array( $decoded[ $wrapper ] ) ) {
            $decoded = $decoded[ $wrapper ];
            break;
        }
    }
    if ( wpae_llm_is_list( $decoded ) ) {
        $decoded = [ 'action' => 'insert_elements', 'elements' => $decoded ];
    }
    $decoded['_wpae_diagnostics'] = [
        'response_type' => 'json_object',
        'json_decoded' => true,
        'response_keys' => array_values( array_map( 'sanitize_key', array_keys( $decoded ) ) ),
    ];
    if ( ( $decoded['action'] ?? $decoded['type'] ?? '' ) === 'insert_elements' ) {
        $decoded['action'] = 'insert_elements';
    }
    if ( $post_id > 0 && empty( $decoded['post_id'] ) ) {
        $decoded['post_id'] = $post_id;
    }
    return $decoded;
}

function wpae_llm_execute_action( array $action, int $post_id, string $archetype = '', int $variation_seed = -1, string $message = '' ): array {
    $operation_id = wpae_llm_new_operation_id();
    $received_action = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
    $received_post_id = absint( $action['post_id'] ?? 0 );
    $received_elements = $action['elements'] ?? [];
    $steps = [];
    if ( ( $action['action'] ?? '' ) !== 'insert_elements' || $post_id <= 0 || absint( $action['post_id'] ?? 0 ) !== $post_id ) {
        $steps[] = [
            'id' => 'command_validation',
            'status' => 'failed',
            'message' => 'Команда модели не прошла проверку типа или страницы.',
            'details' => [
                'received_action' => $received_action,
                'received_post_id' => $received_post_id,
                'expected_action' => 'insert_elements',
                'expected_post_id' => $post_id,
                'element_count' => is_array( $received_elements ) ? count( $received_elements ) : 0,
            ],
        ];
        return [
            'ok' => false,
            'operation_id' => $operation_id,
            'error' => 'Модель вернула неподдерживаемую Elementor-команду.',
            'received_action' => $received_action,
            'received_post_id' => $received_post_id,
            'expected_action' => 'insert_elements',
            'expected_post_id' => $post_id,
            'model_response' => $action['_wpae_diagnostics'] ?? [],
            'steps' => $steps,
        ];
    }
    if ( ! wpae_capability_enabled( 'elementor_writes' ) ) {
        $steps[] = [ 'id' => 'permissions', 'status' => 'failed', 'message' => 'Запись заблокирована настройками возможностей сайта.', 'details' => [ 'capability' => 'elementor_writes' ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Разрешение elementor_writes выключено владельцем сайта.', 'capability' => 'elementor_writes', 'steps' => $steps ];
    }
    if ( function_exists( 'current_user_can' ) && is_user_logged_in() && ! current_user_can( 'edit_post', $post_id ) ) {
        $steps[] = [ 'id' => 'permissions', 'status' => 'failed', 'message' => 'Текущий пользователь не может изменить эту страницу.', 'details' => [ 'post_id' => $post_id ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Нет разрешения на изменение этой страницы.', 'post_id' => $post_id, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'command_validation', 'status' => 'ok', 'message' => 'Команда insert_elements и целевая страница подтверждены.', 'details' => [ 'action' => $received_action, 'post_id' => $post_id ] ];

    $elements = $action['elements'] ?? [];
    if ( ! is_array( $elements ) || empty( $elements ) || count( $elements ) > 12 ) {
        $steps[] = [ 'id' => 'element_count', 'status' => 'failed', 'message' => 'Количество новых Elementor-элементов должно быть от 1 до 12.', 'details' => [ 'element_count' => is_array( $elements ) ? count( $elements ) : 0 ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать от 1 до 12 новых элементов.', 'steps' => $steps ];
    }
    $shape = wpae_llm_validate_action_shape( $action, $post_id );
    if ( empty( $shape['ok'] ) ) {
        $steps[] = [ 'id' => 'command_shape', 'status' => 'failed', 'message' => 'Структура команды не соответствует безопасному Elementor-контракту.', 'details' => [ 'errors' => $shape['errors'] ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать ровно один заполненный корневой Flexbox-контейнер.', 'details' => $shape, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'element_count', 'status' => 'ok', 'message' => 'Количество новых элементов прошло проверку.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $widget_count = wpae_llm_count_widgets( $elements );
    if ( $widget_count < 1 ) {
        $steps[] = [ 'id' => 'native_widgets', 'status' => 'failed', 'message' => 'Команда не содержит native Elementor widgets, поэтому результат был бы пустым.', 'details' => [ 'widget_count' => 0 ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Elementor-команда должна содержать хотя бы один native widget, иначе страница останется пустой.', 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'native_widgets', 'status' => 'ok', 'message' => 'В команде найден хотя бы один native Elementor widget.', 'details' => [ 'widget_count' => $widget_count ] ];
    $native_normalized = function_exists( 'wpae_elementor_normalize_data' );
    if ( $native_normalized ) {
        $elements = wpae_elementor_normalize_data( $elements )['data'];
    }
    $steps[] = [ 'id' => 'native_normalize', 'status' => $native_normalized ? 'ok' : 'skipped', 'message' => $native_normalized ? 'Структура виджетов нормализована под native Elementor и Flexbox.' : 'Нормализация Elementor недоступна и пропущена.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $preserved_library_design = false;
    foreach ( $elements as $element ) {
        if ( ! is_array( $element ) || ( $element['elType'] ?? '' ) !== 'container' ) {
            continue;
        }
        $classes = preg_split( '/\s+/', trim( (string) ( $element['settings']['_css_classes'] ?? '' ) ) );
        if ( is_array( $classes ) && in_array( 'wpae-preserve-library-design', $classes, true ) ) {
            $preserved_library_design = true;
            break;
        }
    }
    $design_mapped = function_exists( 'wpae_apply_design_token_map' );
    if ( $design_mapped ) {
        $elements = wpae_apply_design_token_map( $elements, $preserved_library_design )['data'];
    }
    $steps[] = [ 'id' => 'design_system', 'status' => $design_mapped ? 'ok' : 'skipped', 'message' => $design_mapped ? 'Применены совместимые native-токены активной дизайн-системы.' : 'Маппинг дизайн-системы недоступен и пропущен.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $existing = wpae_get_elementor_data_for_post( $post_id );
    $initial_page = false;
    if ( is_wp_error( $existing ) ) {
        $existing = [];
        $initial_page = true;
    }
    if ( ! empty( $existing ) && function_exists( 'wpae_elementor_normalize_data' ) ) {
        $existing = wpae_elementor_normalize_data( $existing )['data'];
    }
    $steps[] = [ 'id' => 'page_context', 'status' => 'ok', 'message' => $initial_page ? 'Страница пустая: разрешена безопасная инициализация Elementor.' : 'Текущая структура страницы прочитана.', 'details' => [ 'existing_element_count' => count( $existing ) ] ];
    $fallback_variant_applied = false;
    $fallback_variant = null;
    $variation_requested = ! $preserved_library_design && ( isset( $action['fallback_variant'] ) || $variation_seed >= 0 );
    if ( $variation_requested && function_exists( 'wpae_llm_apply_fallback_variant' ) ) {
        $variation_source = isset( $action['fallback_variant'] ) ? absint( $action['fallback_variant'] ) : $variation_seed;
        $variation_archetype = sanitize_key( (string) ( $action['fallback_archetype'] ?? $archetype ) );
        $fallback_variant = wpae_llm_select_fallback_variant( $existing, $variation_source + count( $existing ), $elements, $variation_archetype );
        $elements = wpae_llm_apply_fallback_variant( $elements, $variation_archetype, $fallback_variant );
        $fallback_variant_applied = true;
        $steps[] = [ 'id' => 'visual_variation', 'status' => 'ok', 'message' => 'Для нового блока выбрана новая композиция без повтора уже добавленных блоков.', 'details' => [ 'variant' => $fallback_variant, 'archetype' => $variation_archetype, 'available_variants' => wpae_llm_visual_variant_count(), 'layout' => intdiv( $fallback_variant, 10 ) ] ];
    }
	$process_request = wpae_llm_is_process_request( $message, $archetype );
    if ( $process_request && $message !== '' && function_exists( 'wpae_llm_enforce_process_timeline_contract' ) ) {
        $final_process_changed = 0;
        $elements = wpae_llm_enforce_process_timeline_contract( $elements, $message, $final_process_changed );
        if ( $final_process_changed > 0 ) {
            $steps[] = [ 'id' => 'process_timeline_final', 'status' => 'ok', 'message' => 'Финальная граница записи восстановила канонический native Flex timeline после вариаций.', 'details' => [ 'containers_rebuilt' => $final_process_changed, 'layout' => wpae_llm_process_timeline_layout( $message ) ] ];
        }
    }
    if ( function_exists( 'wpae_llm_normalize_bento_grids_recursive' ) ) {
        $final_bento_changed = 0;
        wpae_llm_normalize_bento_grids_recursive( $elements, $final_bento_changed, $process_request ? 'process' : $archetype );
        if ( $final_bento_changed > 0 ) {
            $steps[] = [ 'id' => 'bento_layout_final', 'status' => 'ok', 'message' => 'Финальная проверка выровняла bento-карточки после всех Elementor-нормализаторов.', 'details' => [ 'containers_updated' => $final_bento_changed, 'max_items_per_row' => 4 ] ];
        }
    }
    if ( function_exists( 'wpae_rekey_elementor_ids_recursive' ) ) {
        $elements = wpae_rekey_elementor_ids_recursive( $elements, 'llm-' . wp_generate_password( 10, false, false ) );
    }
    $steps[] = [ 'id' => 'element_ids', 'status' => 'ok', 'message' => 'Для новых элементов созданы уникальные Elementor ID.', 'details' => [ 'element_count' => count( $elements ) ] ];
    $position = sanitize_key( (string) ( $action['position'] ?? 'end' ) );
    $next = $position === 'start' ? array_merge( $elements, $existing ) : array_merge( $existing, $elements );
    $request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
    $request->set_param( 'post_id', $post_id );
    $request->set_param( 'elementor_data', $next );
    $request->set_param( 'template', 'elementor_canvas' );
    $request->set_param( '_wpae_allow_initial_data', true );
    $request->set_param( 'transaction_visual_regression', ! $initial_page );
    $preview_request = new WP_REST_Request( 'POST', '/ai-executor/v1/elementor/update' );
    $preview_request->set_param( 'post_id', $post_id );
    $preview_request->set_param( 'elementor_data', $next );
    $preview_request->set_param( 'template', 'elementor_canvas' );
    $preview_request->set_param( '_wpae_allow_initial_data', true );
    $preview_request->set_param( 'dry_run', true );
    $preview = wpae_elementor_update( $preview_request );
    $preview_data = $preview instanceof WP_REST_Response ? $preview->get_data() : [];
    $preview_status = $preview instanceof WP_REST_Response ? $preview->get_status() : 500;
    if ( $preview_status < 200 || $preview_status >= 300 || ! is_array( $preview_data ) || empty( $preview_data['ok'] ) ) {
        $steps[] = [ 'id' => 'preview', 'status' => 'failed', 'message' => 'Предпросмотр команды отклонён до записи.', 'details' => [ 'http_status' => $preview_status, 'error' => sanitize_text_field( (string) ( $preview_data['error'] ?? 'Preview failed.' ) ), 'details' => $preview_data['details'] ?? $preview_data['preflight'] ?? [] ] ];
        return [ 'ok' => false, 'operation_id' => $operation_id, 'error' => 'Предпросмотр Elementor отклонён до записи.', 'update_error' => sanitize_text_field( (string) ( $preview_data['error'] ?? '' ) ), 'status' => $preview_status, 'details' => $preview_data, 'steps' => $steps ];
    }
    $steps[] = [ 'id' => 'preview', 'status' => 'ok', 'message' => 'Предпросмотр команды и preflight прошли до записи.', 'details' => [ 'http_status' => $preview_status, 'post_id' => $post_id ] ];
    $result = wpae_elementor_update( $request );
    $data = $result instanceof WP_REST_Response ? $result->get_data() : [];
    $status = $result instanceof WP_REST_Response ? $result->get_status() : 500;
    if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['ok'] ) ) {
        $blocking_errors = [];
        $failed_checks = [];
        $failure_details = [];
        foreach ( [ $data['details']['errors'] ?? [], $data['details']['design_system']['errors'] ?? [], $data['preflight']['blocking_errors'] ?? [] ] as $errors ) {
            foreach ( (array) $errors as $error ) {
                if ( is_scalar( $error ) && trim( (string) $error ) !== '' ) {
                    $blocking_errors[] = sanitize_text_field( (string) $error );
                }
            }
        }
        foreach ( [ $data['details']['transaction']['checks'] ?? [], $data['transaction']['checks'] ?? [], $data['details']['checks'] ?? [] ] as $checks ) {
            foreach ( (array) $checks as $code => $check ) {
                if ( is_array( $check ) && empty( $check['ok'] ) ) {
                    $failed_checks[] = sanitize_key( (string) $code );
                    $failure_details[] = [
                        'code' => sanitize_key( (string) $code ),
                        'message' => sanitize_text_field( (string) ( $check['message'] ?? 'Проверка не пройдена.' ) ),
                    ];
                }
            }
        }
        $failed_checks = array_values( array_unique( $failed_checks ) );
        return [
            'ok' => false,
            'operation_id' => $operation_id,
            'error' => 'Elementor update отклонен существующей проверкой.',
            'update_error' => sanitize_text_field( (string) ( $data['error'] ?? '' ) ),
            'status' => $status,
            'blocking_errors' => array_values( array_unique( $blocking_errors ) ),
            'failed_checks' => $failed_checks,
            'failure_details' => $failure_details,
            'details' => is_array( $data ) ? $data : [],
            'steps' => array_merge( $steps, [ [ 'id' => 'elementor_update', 'status' => 'failed', 'message' => 'Elementor update остановлен проверкой; изменения откатились, если rollback был доступен.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'blocking_errors' => array_values( array_unique( $blocking_errors ) ), 'failed_checks' => $failed_checks, 'failure_details' => $failure_details ] ] ] ),
        ];
    }
    $diff = wpae_llm_action_diff( $existing, $elements, $next );
    $steps[] = [ 'id' => 'elementor_update', 'status' => 'ok', 'message' => 'Изменения сохранены через Elementor update.', 'details' => [ 'operation_id' => $operation_id, 'http_status' => $status, 'diff' => $diff ] ];
    $steps[] = [ 'id' => 'complete', 'status' => 'ok', 'message' => 'Задача выполнена, Elementor подтвердил запись.' ];
    return [
        'ok' => true,
        'operation_id' => $operation_id,
        'action' => 'insert_elements',
        'post_id' => $post_id,
        'inserted_count' => count( $elements ),
        'inserted_widget_count' => wpae_llm_count_widgets( $elements ),
        'fallback_variant' => $fallback_variant_applied ? $fallback_variant : null,
        'action_summary' => 'Добавлен блок ' . ( $action['position'] ?? 'end' ) . ': ' . count( $elements ) . ' контейнер.',
        'diff' => $diff,
        'editor_sync' => [
            'position' => $position,
            'elements' => $elements,
            'before_top_level_ids' => (array) ( $diff['before_top_level_ids'] ?? [] ),
        ],
        'quality_summary' => $data['quality_summary'] ?? null,
        'rollback_snapshot_id' => $data['rollback_snapshot_id'] ?? null,
        'steps' => $steps,
    ];
}

function wpae_llm_chat( WP_REST_Request $request ) {
    try {
        return wpae_llm_chat_request( $request );
    } catch ( Throwable $error ) {
        $provider = sanitize_key( (string) ( wpae_llm_get_stored_settings()['provider'] ?? 'unknown' ) );
        error_log( sprintf( '[WP AI Executor] LLM chat failed for provider %s: %s in %s:%d', $provider, $error->getMessage(), $error->getFile(), $error->getLine() ) );
        return new WP_Error( 'wpae_llm_internal_error', 'Внутренняя ошибка LLM-запроса. Подробности записаны в журнал WordPress.', [
            'status' => 500,
            'provider' => $provider,
            'details' => [
                'exception' => sanitize_text_field( $error->getMessage() ),
                'exception_type' => sanitize_text_field( get_class( $error ) ),
            ],
        ] );
    }
}

function wpae_llm_chat_request( WP_REST_Request $request ) {
    if ( ! wpae_llm_rate_limit_check() ) {
        return new WP_Error( 'wpae_llm_rate_limited', 'Лимит LLM-запросов исчерпан. Повторите позже.', [ 'status' => 429, 'window_seconds' => WPAE_LLM_CALL_WINDOW ] );
    }

    $runtime = wpae_llm_get_runtime_settings();
    if ( is_wp_error( $runtime ) ) {
        return $runtime;
    }
    $body_params = $request->get_json_params();
    $body_params = is_array( $body_params ) ? $body_params : [];
    $message_input = array_key_exists( 'message', $body_params ) ? $body_params['message'] : $request->get_param( 'message' );
    $message = sanitize_textarea_field( (string) $message_input );
    if ( $message === '' ) {
        return new WP_Error( 'wpae_llm_message_required', 'Поле message обязательно.', [ 'status' => 400 ] );
    }
    if ( strlen( $message ) > WPAE_LLM_MAX_MESSAGE_LENGTH ) {
        return new WP_Error( 'wpae_llm_message_too_long', 'Сообщение слишком длинное.', [ 'status' => 400, 'max_length' => WPAE_LLM_MAX_MESSAGE_LENGTH ] );
    }

    $action_request = wpae_llm_is_action_request( $message );
    $editor_context_input = array_key_exists( 'context', $body_params ) ? $body_params['context'] : $request->get_param( 'context' );
    $live_background_image_urls = is_array( $editor_context_input )
        ? wpae_llm_sanitize_background_image_urls( $editor_context_input['background_image_urls'] ?? [] )
        : [];
    $selected_element_count = is_array( $editor_context_input ) && is_array( $editor_context_input['selected_elements'] ?? null ) ? count( $editor_context_input['selected_elements'] ) : 0;
    $vision_repair = is_array( $editor_context_input ) && ! empty( $editor_context_input['vision_repair'] );
    $vision_regenerate = is_array( $editor_context_input ) && ! empty( $editor_context_input['vision_regenerate'] );
	$vision_findings = $vision_repair && is_array( $editor_context_input ) ? sanitize_textarea_field( substr( (string) ( $editor_context_input['vision_findings'] ?? '' ), 0, 3600 ) ) : '';
	$vision_feedback_prompt = $vision_repair ? wpae_llm_build_vision_feedback_prompt( $message, $vision_findings, $vision_regenerate ) : '';
    $targeted_edit = $action_request && $selected_element_count > 0 && ! $vision_regenerate && ( $vision_repair || wpae_llm_is_targeted_edit_request( $message ) );
    $action_archetype = $action_request ? wpae_llm_detect_block_archetype( $message ) : '';
    $content_plan = $action_request ? wpae_llm_content_plan( $message, $action_archetype ) : [];
    $library_retrieval = [
        'status' => 'skipped',
        'reason' => $targeted_edit
            ? 'Library retrieval is skipped for targeted edits.'
            : ( $vision_regenerate
                ? 'Library retrieval is available for full Vision regeneration.'
                : ( $vision_repair ? 'Library retrieval is skipped for selected-element Vision repair.' : 'Library retrieval is available for new block generation.' ) ),
        'available_count' => 0,
        'candidate_count' => 0,
        'candidates' => [],
        'selected' => null,
    ];
    $library_retrieval_enabled = $action_request && ! $targeted_edit && ( ! $vision_repair || $vision_regenerate ) && function_exists( 'wpae_block_library_retrieve_for_prompt' );
    if ( $library_retrieval_enabled ) {
        $library_retrieval = wpae_block_library_retrieve_for_prompt( $message, $action_archetype );
    }
    $system_prompt = 'Ты помогаешь работать с WordPress и Elementor. Не заявляй, что изменения выполнены, если не получил подтверждение API. Соблюдай native Elementor settings, Flexbox Containers, mobile-first и сохраняй существующие WebGL/GSAP/Three.js enhancement-зоны.';
    $guided_context = [];
    if ( $action_request ) {
        $guided_context = [
            'guide_version' => WPAE_GUIDE_VERSION,
            'plugin_version' => WPAE_VERSION,
            'agent_rules' => function_exists( 'wpae_agent_prompt' ) ? wpae_agent_prompt() : '',
            'custom_skills' => function_exists( 'wpae_get_enabled_skills_for_guide' ) ? wpae_get_enabled_skills_for_guide() : [],
            'capabilities' => function_exists( 'wpae_get_capabilities_payload' ) ? wpae_get_capabilities_payload() : [],
        ];
        $system_prompt .= "\nЭто guided-режим WP AI Executor. Перед выполнением обязательно применяй agent_rules, все custom_skills и capabilities из следующего контекста. Правила WP AI Executor имеют приоритет при конфликте:\n" . wp_json_encode( $guided_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( $library_retrieval['status'] === 'matched' && is_array( $library_retrieval['selected'] ?? null ) ) {
            $library_label = ! empty( $library_retrieval['selected']['trusted_bundled'] ) ? 'проверенный bundled-шаблон' : 'одобренный шаблон';
            $system_prompt .= "\nДля этого нового блока найден " . $library_label . " из private block library: «" . sanitize_text_field( (string) ( $library_retrieval['selected']['title'] ?? '' ) ) . '». Сервер адаптирует его композицию и применит только после проверки native-структуры и точного пользовательского контента. Не возвращай служебные инструкции или JSON библиотеки; сгенерируй контент по запросу пользователя.';
        }
    }
    if ( $action_request ) {
        $system_prompt .= $targeted_edit
            ? ' Это точечное изменение выбранного Elementor элемента или контейнера вместе со всем его дочерним деревом. Контекст редактора содержит полный снимок выбранного объекта, его settings и содержимое descendants. Верни только JSON по схеме: {"action":"patch_elements","post_id":number,"patches":[{"element_id":"selected-id-or-descendant-id","path":"settings.native_property","op":"set","value":...}]}. Меняй только явно запрошенные native properties, не трогай HTML/CSS/WebGL и не пересобирай страницу. Для контейнера можешь менять native settings любого элемента внутри его дерева, но не выходи за пределы выбранного дерева и не выдумывай element_id. Для текста используй текущий settings.title, settings.editor или settings.text; для стиля сохраняй совместимую форму текущего native setting.'
            : ' Ограничения компактности action-JSON: ровно 1 корневой контейнер и 3–5 вложенных виджетов; не дублируй значения Elementor по умолчанию и не добавляй необязательные настройки.';
		if ( $vision_repair ) {
			$system_prompt .= $vision_regenerate
				? ' Это автоматический repair-проход по замечаниям AI Vision. Перегенерируй один полноценный Elementor-блок заново по исходному запросу пользователя; не урезай композицию, не оставляй placeholder-тексты, исправь все findings и верни insert_elements. Не удаляй и не заменяй существующие блоки страницы: предыдущая неудачная версия уже откатена перед этой генерацией.'
				: ' Это автоматический repair-проход по замечаниям AI Vision. Исправь только существующие выбранные элементы по findings; сохрани корректный контент, но замени placeholder или контент, который Vision отметил как несоответствующий исходному запросу пользователя. Не добавляй, не удаляй и не заменяй блоки, не меняй HTML/CSS/WebGL. Используй только native settings properties, необходимые для устранения замечаний, и верни patch_elements.';
			if ( $vision_feedback_prompt !== '' ) {
				$system_prompt .= ' Отчет AI Vision будет передан ниже отдельным дополнительным промтом. Считай его визуальным feedback, а не заменой исходного задания и не публикуемым контентом.';
			}
		}
        $variation_seed = hexdec( substr( md5( $message . '|' . microtime( true ) ), 0, 6 ) ) % 100000;
        $system_prompt .= wpae_llm_block_archetype_hint( $message );
        $system_prompt .= "\nСемантический план контента (контракт для адаптации, не текст для вывода): " . wp_json_encode( $content_plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '. Не добавляй CTA, имена, цены или смысловые блоки, которых нет в плане; не меняй смысл пользовательского контента ради шаблона.';
        $system_prompt .= $targeted_edit ? ' Это запрос на выполнение точечной правки. Не пиши инструкцию и не объясняй ручные клики.' : ' Это запрос на выполнение работы. Не пиши инструкцию и не объясняй ручные клики. Верни только компактный JSON без markdown по схеме: {"action":"insert_elements","post_id":number,"position":"start|end","elements":[Elementor native Flexbox container/widget objects]}. Для этой задачи массив elements обязан содержать ровно один объект elType=container, все widget-объекты должны находиться только внутри его elements, а верхний уровень не должен содержать widget-объекты или дополнительные контейнеры. Используй 3–5 заполненных native widgets, выбранных по типу блока; heading, text-editor и button разрешены, но не обязательны, если более подходящий native widget поддерживается Elementor. Разрешена только вставка новых элементов с elType=container/widget, точным camelCase widgetType, native settings и elements arrays. Каждый container обязан содержать заполненные native widgets в своем дереве; не возвращай контейнеры без widgets. Для hero обязательно добавь полезный контент через native heading/text-editor/button widgets, а не только пустую структуру layout. Любой тип блока должен иметь сбалансированную композицию без пустых или чрезмерно широких зон и чрезмерно широких колонок: на desktop используй понятную композицию, на mobile собери ее в вертикальный stack; внешний контейнер и контейнер bento-сетки оставляй прозрачными, а фон и обводку используй у самих карточек; обеспечь контрастный текст, видимый CTA там, где он нужен, разумные min-height/spacing и responsive units rem/em/vh/% вместо огромных px-значений. Не допускай слитого текста, гигантских пустых промежутков и элементов, которые визуально существуют только как placeholder.' . wpae_llm_generation_visual_grammar_hint() . ' Не удаляй и не заменяй существующие элементы.';
        if ( ! $targeted_edit ) {
            $system_prompt .= ' Выбери для этого запуска новую композицию и не копируй предыдущие блоки: меняй ритм, соотношение зон, плотность и акцентную иерархию, сохраняя смысл и весь пользовательский контент. Внутренний номер варианта: ' . (string) $variation_seed . '.';
        }
        $system_prompt .= "\nАктивная дизайн-система: " . wp_json_encode( wpae_build_project_design_system(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $system_prompt .= $targeted_edit ? ' КРИТИЧЕСКОЕ ПРАВИЛО: ответом должен быть только JSON-объект patch_elements. Не возвращай URL, endpoint, пояснения или markdown.' : ' КРИТИЧЕСКОЕ ПРАВИЛО: ответом должен быть только сам JSON-объект команды insert_elements. Не возвращай URL, HTTP-запросы, названия endpoint, пояснения, markdown или текст вроде POST /wp-json/... .';
    }
    $messages = [ [
        'role' => 'system',
        'content' => $system_prompt,
    ] ];
    $history_input = array_key_exists( 'history', $body_params ) ? $body_params['history'] : $request->get_param( 'history' );
    foreach ( wpae_llm_clean_history( $history_input ) as $item ) {
        $messages[] = $item;
    }
	$messages[] = [ 'role' => 'user', 'content' => $message ];
	if ( $vision_feedback_prompt !== '' ) {
		$messages[] = [ 'role' => 'user', 'content' => $vision_feedback_prompt ];
	}

	$context = array_key_exists( 'context', $body_params ) ? $body_params['context'] : $request->get_param( 'context' );
    if ( is_array( $context ) ) {
        $selected_elements = [];
        foreach ( array_slice( is_array( $context['selected_elements'] ?? null ) ? $context['selected_elements'] : [], 0, 8 ) as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $selected_elements[] = wpae_llm_sanitize_selected_element_snapshot( $element );
        }
        $context = [
            'post_id' => absint( $context['post_id'] ?? 0 ),
            'selection_scope' => ! empty( $selected_elements ) ? 'selected_element_and_descendants' : 'page',
            'selected_elements' => $selected_elements,
            'background_image_urls' => $live_background_image_urls,
        ];
        $messages[0]['content'] .= "\nКонтекст редактора: " . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    $url = untrailingslashit( $runtime['base_url'] ) . '/chat/completions';
    $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $runtime['api_key'] ];
    if ( $runtime['provider'] === 'openrouter' ) {
        $headers['HTTP-Referer'] = home_url( '/' );
        $headers['X-Title'] = get_bloginfo( 'name' );
    }
    $request_body = [
        'model' => $runtime['model'],
        'messages' => $messages,
        'temperature' => 0.2,
        'max_completion_tokens' => $action_request ? 8000 : 400,
    ];
    if ( $action_request ) {
        $request_body['response_format'] = [ 'type' => 'json_object' ];
        if ( $runtime['provider'] === 'openrouter' ) {
            $request_body['provider'] = [ 'require_parameters' => true ];
        }
    }
    $remote_args = [
        // When a fallback model exists the primary attempt must leave room for
        // the bounded 30-second fallback inside the editor's 55-second abort,
        // otherwise the fallback window is never reached on hanging routes.
        'timeout' => wpae_llm_fallback_model( $runtime['model'] ) !== '' ? 20 : 45,
        'redirection' => 2,
        'limit_response_size' => WPAE_LLM_MAX_RESPONSE_BYTES,
        'headers' => $headers,
        'body' => wp_json_encode( $request_body ),
    ];
    $response = wpae_llm_provider_request( $url, $remote_args, $request_body, $action_request, $runtime['provider'] );
    if ( is_wp_error( $response ) ) {
        // Transport-level failure (timeout or HTTP layer). One opt-in fallback
        // model attempt keeps slow shared wildcard routes usable without the
        // heavy editor reload cycle.
        $fallback_model = wpae_llm_fallback_model( $runtime['model'] );
        if ( $fallback_model !== '' ) {
            $fallback_request_body = $request_body;
            $fallback_request_body['model'] = $fallback_model;
            $fallback_remote_args = $remote_args;
            $fallback_remote_args['timeout'] = 30;
            $fallback_response = wpae_llm_provider_request( $url, $fallback_remote_args, $fallback_request_body, $action_request, $runtime['provider'] );
            if ( ! is_wp_error( $fallback_response ) ) {
                $response = $fallback_response;
                $runtime['model'] = $fallback_model;
            }
        }
    }
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'wpae_llm_provider_request_failed', 'LLM-провайдер недоступен.', [ 'status' => 502, 'details' => sanitize_text_field( $response->get_error_message() ), 'provider' => $runtime['provider'] ] );
    }

    $status = wp_remote_retrieve_response_code( $response );
    $raw = wp_remote_retrieve_body( $response );
    $body = json_decode( $raw, true );
    $used_fallback_model = '';
    if ( $status < 200 || $status >= 300 ) {
        $provider_error = wpae_llm_provider_error_message( is_array( $body ) ? $body : [] ) ?: ( is_array( $body ) ? 'Провайдер вернул ошибку.' : 'Провайдер вернул некорректный ответ.' );
        if ( wpae_llm_provider_is_rate_limited( is_array( $body ) ? $body : [] ) ) {
            // Shared free pools ask for a delayed retry; expose a distinct code
            // and bounded retry_after so the client can wait instead of burning
            // both attempts and reloading the whole editor. Before giving up,
            // one opt-in fallback model gets a single immediate attempt.
            $fallback_model = wpae_llm_fallback_model( $runtime['model'] );
            if ( $fallback_model !== '' ) {
                $fallback_request_body = $request_body;
                $fallback_request_body['model'] = $fallback_model;
                $fallback_response = wpae_llm_provider_request( $url, $remote_args, $fallback_request_body, $action_request, $runtime['provider'] );
                if ( ! is_wp_error( $fallback_response ) ) {
                    $fallback_status = wp_remote_retrieve_response_code( $fallback_response );
                    if ( $fallback_status >= 200 && $fallback_status < 300 ) {
                        $response = $fallback_response;
                        $status = $fallback_status;
                        $raw = wp_remote_retrieve_body( $fallback_response );
                        $body = json_decode( $raw, true );
                        $used_fallback_model = $fallback_model;
                        $runtime['model'] = $fallback_model;
                    }
                }
            }
        }
    }
    if ( $status < 200 || $status >= 300 ) {
        $provider_error = wpae_llm_provider_error_message( is_array( $body ) ? $body : [] ) ?: ( is_array( $body ) ? 'Провайдер вернул ошибку.' : 'Провайдер вернул некорректный ответ.' );
        if ( wpae_llm_provider_is_rate_limited( is_array( $body ) ? $body : [] ) ) {
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            return new WP_Error( 'wpae_llm_provider_rate_limited', 'LLM-провайдер временно ограничен по лимиту (rate limit).', [ 'status' => 429, 'provider_status' => $status, 'provider_message' => sanitize_text_field( (string) $provider_error ), 'retry_after' => max( 15, min( 60, $retry_after > 0 ? $retry_after : 30 ) ), 'provider' => $runtime['provider'], 'fallback_model' => $used_fallback_model ] );
        }
        return new WP_Error( 'wpae_llm_provider_error', 'LLM-провайдер вернул ошибку.', [ 'status' => 502, 'provider_status' => $status, 'provider_message' => sanitize_text_field( (string) $provider_error ), 'provider' => $runtime['provider'] ] );
    }

    $reply = wpae_llm_extract_response_text( is_array( $body ) ? $body : [] );
    if ( $reply === '' ) {
        $diagnostics = wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] );
        $provider_message = $diagnostics['provider_message'] ?: ( $diagnostics['finish_reason'] === 'error' ? 'Провайдер завершил генерацию с ошибкой без дополнительного сообщения.' : '' );
        if ( $provider_message !== '' || $diagnostics['finish_reason'] === 'error' ) {
            return new WP_Error( 'wpae_llm_provider_error', 'LLM-провайдер вернул ошибку.', [
                'status' => 502,
                'provider_status' => $status,
                'provider_message' => $provider_message,
                'provider_error_code' => $diagnostics['provider_error_code'],
                'finish_reason' => $diagnostics['finish_reason'],
                'provider' => $runtime['provider'],
                'details' => $diagnostics,
            ] );
        }
        return new WP_Error( 'wpae_llm_empty_response', 'LLM-провайдер вернул пустой ответ. Проверьте модель и лимит токенов.', [
            'status' => 502,
            'provider' => $runtime['provider'],
            'details' => $diagnostics,
        ] );
    }
    if ( $action_request ) {
        $post_id = is_array( $context ?? null ) ? absint( $context['post_id'] ?? 0 ) : 0;
        $action = wpae_llm_decode_action( $reply, $post_id );
        $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
        $action_diagnostics['decoded_action'] = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
        $action_diagnostics['decoded_post_id'] = absint( $action['post_id'] ?? 0 );
        $action_diagnostics['decoded_element_count'] = is_array( $action['elements'] ?? null ) ? count( $action['elements'] ) : 0;
        $action_diagnostics['decoded_patch_count'] = is_array( $action['patches'] ?? null ) ? count( $action['patches'] ) : 0;
        if ( $targeted_edit ) {
            $selected_element_ids = array_values( array_filter( array_map( static fn( $item ) => is_array( $item ) ? sanitize_key( (string) ( $item['id'] ?? $item['element_id'] ?? '' ) ) : sanitize_key( (string) $item ), (array) ( $editor_context_input['selected_elements'] ?? [] ) ) ) );
            $action = wpae_llm_ensure_targeted_border_radius_patch( $action, $message, $post_id, (array) ( $editor_context_input['selected_elements'] ?? [] ) );
            $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : $action_diagnostics;
            $action_diagnostics['decoded_action'] = sanitize_key( (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' ) );
            $action_diagnostics['decoded_post_id'] = absint( $action['post_id'] ?? 0 );
            $action_diagnostics['decoded_patch_count'] = is_array( $action['patches'] ?? null ) ? count( $action['patches'] ) : 0;
            $patch_execution = wpae_llm_execute_patch_action( $action, $post_id, $selected_element_ids );
            $patch_execution['steps'] = array_merge(
                [ [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Загружены guide, skills и полное дерево выбранного Elementor элемента.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION, 'custom_skills_count' => count( $guided_context['custom_skills'] ?? [] ), 'selected_element_count' => $selected_element_count ] ] ],
                [ [ 'id' => 'command_decode', 'status' => ! empty( $action_diagnostics['json_decoded'] ) || ! empty( $action_diagnostics['deterministic_border_radius_patch'] ) ? 'ok' : 'failed', 'message' => ! empty( $action_diagnostics['json_decoded'] ) || ! empty( $action_diagnostics['deterministic_border_radius_patch'] ) ? 'Ответ разобран как patch-команда.' : 'Ответ не разобран как patch-команда.', 'details' => $action_diagnostics ] ],
                (array) ( $patch_execution['steps'] ?? [] )
            );
            if ( empty( $patch_execution['ok'] ) ) {
                return new WP_Error( 'wpae_llm_action_failed', 'LLM не выполнил точечную правку в Elementor.', [ 'status' => 422, 'details' => $patch_execution ] );
            }
            return new WP_REST_Response( [ 'ok' => true, 'message' => 'Точечная правка выполнена через Elementor. Изменено свойств: ' . (int) $patch_execution['changed_count'] . '.', 'operation_id' => $patch_execution['operation_id'] ?? null, 'action' => $patch_execution['action'], 'write' => $patch_execution, 'steps' => $patch_execution['steps'], 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
        }
        $action_repair = false;
        $action_fallback = false;
        $decoded_action = (string) ( $action['action'] ?? $action['type'] ?? $action['command'] ?? '' );
        $decoded_elements = is_array( $action['elements'] ?? null ) ? $action['elements'] : [];
        $decoded_widget_count = wpae_llm_count_widgets( $decoded_elements );
        $decoded_shape = wpae_llm_validate_action_shape( $action, $post_id );
        $decoded_content_fidelity = wpae_llm_content_fidelity( $message, $decoded_elements );
        if ( empty( $decoded_shape['ok'] ) || count( $decoded_elements ) > 12 || $decoded_widget_count < 1 || empty( $decoded_content_fidelity['ok'] ) ) {
            $repair_error = '';
			$repair_messages = [
				[ 'role' => 'system', 'content' => 'Исправь Elementor action JSON. Верни только JSON без markdown и текста. Нужен ровно один верхнеуровневый elType=container с 3–5 заполненными native widget descendants. Используй именно post_id ' . (string) $post_id . '. ' . wpae_llm_block_archetype_hint( $message ) . wpae_llm_generation_visual_grammar_hint() . ' Сгенерируй осмысленный русский контент под запрос пользователя «' . sanitize_text_field( $message ) . '», а не служебные заглушки. Используй минимум три подходящих заполненных native widgets; для специального типа предпочти соответствующий widget (icon-list, accordion, price-list, testimonial, image или divider), а если он недоступен или требует неподдерживаемой структуры, используй заполненные heading/text-editor/button с содержанием именно этого типа, а не общий текст о преимуществах. Не используй тексты «Заголовок блока», «Короткое описание результата для клиента», «Текст заголовка» или другие placeholder-фразы. У heading не может быть пустым settings.title, у text-editor settings.editor, у button settings.text или settings.link.url; для общего CTA fallback допустим текст «Обсудить проект», но специальный блок должен сохранить содержание своего типа. Не возвращай пустые контейнеры, плоские виджеты, дополнительные верхнеуровневые элементы, REST-маршруты или пояснения. Схема: {"action":"insert_elements","post_id":' . (string) $post_id . ',"position":"end","elements":[container]}.' ],
				[ 'role' => 'user', 'content' => $message ],
			];
			if ( $vision_feedback_prompt !== '' ) {
				$repair_messages[] = [ 'role' => 'user', 'content' => $vision_feedback_prompt ];
			}
            if ( isset( $variation_seed ) ) {
                $repair_messages[0]['content'] .= ' Выбери новую композицию, не копируй прошлый блок; внутренний номер варианта: ' . (string) $variation_seed . '.';
            }
            for ( $repair_attempt = 1; $repair_attempt <= 2 && ! $action_repair; $repair_attempt++ ) {
                $repair_body = $request_body;
                $repair_body['messages'] = $repair_messages;
                $repair_response = wpae_llm_provider_request( $url, $remote_args, $repair_body, true, $runtime['provider'] );
                if ( is_wp_error( $repair_response ) ) {
                    $repair_error = sanitize_text_field( $repair_response->get_error_message() );
                    continue;
                }
                $repair_status = wp_remote_retrieve_response_code( $repair_response );
                $repair_payload = json_decode( wp_remote_retrieve_body( $repair_response ), true );
                if ( $repair_status < 200 || $repair_status >= 300 ) {
                    $repair_error = 'Repair HTTP ' . $repair_status . '.';
                    continue;
                }
                $repair_reply = wpae_llm_extract_response_text( is_array( $repair_payload ) ? $repair_payload : [] );
                if ( $repair_reply === '' ) {
                    $repair_error = 'Repair-проход вернул пустой ответ.';
                    continue;
                }
                $candidate = wpae_llm_decode_action( $repair_reply, $post_id );
                $candidate_elements = is_array( $candidate['elements'] ?? null ) ? $candidate['elements'] : [];
                $candidate_action = (string) ( $candidate['action'] ?? $candidate['type'] ?? $candidate['command'] ?? '' );
                $candidate_post_id = absint( $candidate['post_id'] ?? 0 );
                $candidate_widget_count = wpae_llm_count_widgets( $candidate_elements );
                $candidate_shape = wpae_llm_validate_action_shape( $candidate, $post_id );
                $candidate_content_fidelity = wpae_llm_content_fidelity( $message, $candidate_elements );
                if ( empty( $candidate_shape['ok'] ) || $candidate_action !== 'insert_elements' || $candidate_post_id !== $post_id || count( $candidate_elements ) > 12 || $candidate_widget_count < 1 || empty( $candidate_content_fidelity['ok'] ) ) {
                    $repair_error = 'Repair-проход вернул неподдерживаемую или пустую Elementor-команду.';
                    continue;
                }
                $reply = $repair_reply;
                $body = $repair_payload;
                $action = $candidate;
                $action_diagnostics = is_array( $action['_wpae_diagnostics'] ?? null ) ? $action['_wpae_diagnostics'] : [];
                $action_diagnostics['decoded_action'] = sanitize_key( $candidate_action );
                $action_diagnostics['decoded_post_id'] = $candidate_post_id;
                $action_diagnostics['decoded_element_count'] = count( $candidate_elements );
                $action_repair = true;
            }
        }
        if ( ! $action_repair ) {
            $action = wpae_llm_build_fallback_action( $message, $post_id );
            $action_diagnostics = [
                'response_type' => 'deterministic_fallback',
                'json_decoded' => true,
                'response_keys' => [ 'action', 'post_id', 'position', 'fallback_archetype', 'fallback_variant', 'elements' ],
                'fallback_archetype' => wpae_llm_detect_block_archetype( $message ),
                'fallback_variant' => absint( $action['fallback_variant'] ?? 0 ),
                'fallback_reason' => 'Provider and bounded repair response did not contain a usable native widget tree.',
            ];
            $action_fallback = true;
        }
        $typography_changed = 0;
        $bento_changed = 0;
        $composition_repair_changed = 0;
        $process_labels_changed = 0;
        $process_timeline_changed = 0;
        $fallback_content_changed = 0;
        if ( $action_fallback ) {
            wpae_llm_apply_fallback_archetype_content( $action['elements'], $message, $action_archetype, $fallback_content_changed );
            if ( $action_archetype === 'faq' ) {
                wpae_llm_apply_fallback_faq_content( $action['elements'], $message, $fallback_content_changed );
            }
            $fallback_fidelity = wpae_llm_content_fidelity( $message, (array) $action['elements'] );
            $missing_content = (array) ( $fallback_fidelity['missing'] ?? [] );
            if ( ! empty( $missing_content ) ) {
                wpae_llm_apply_fallback_content( $action['elements'], $missing_content, $action_archetype, $fallback_content_changed );
            }
        }
        if ( ! $targeted_edit ) {
            wpae_llm_remove_unrequested_buttons( $action['elements'], $message, $fallback_content_changed );
            $preflight_plan_audit = wpae_llm_content_plan_audit( $content_plan, (array) $action['elements'] );
            if ( empty( $preflight_plan_audit['ok'] ) && ! $action_fallback ) {
                $action = wpae_llm_build_fallback_action( $message, $post_id );
                $action_fallback = true;
                $fallback_content_changed = 0;
                $action_diagnostics = [
                    'response_type' => 'deterministic_fallback',
                    'json_decoded' => true,
                    'response_keys' => [ 'action', 'post_id', 'position', 'fallback_archetype', 'fallback_variant', 'elements' ],
                    'fallback_archetype' => $action_archetype,
                    'fallback_variant' => absint( $action['fallback_variant'] ?? 0 ),
                    'fallback_reason' => 'Decoded provider tree failed the semantic structure contract before Elementor normalization.',
                    'semantic_failures' => (array) ( $preflight_plan_audit['failures'] ?? [] ),
                ];
                wpae_llm_apply_fallback_archetype_content( $action['elements'], $message, $action_archetype, $fallback_content_changed );
                if ( $action_archetype === 'faq' ) {
                    wpae_llm_apply_fallback_faq_content( $action['elements'], $message, $fallback_content_changed );
                }
                $fallback_fidelity = wpae_llm_content_fidelity( $message, (array) $action['elements'] );
                $missing_content = (array) ( $fallback_fidelity['missing'] ?? [] );
                if ( ! empty( $missing_content ) ) {
                    wpae_llm_apply_fallback_content( $action['elements'], $missing_content, $action_archetype, $fallback_content_changed );
                }
                wpae_llm_remove_unrequested_buttons( $action['elements'], $message, $fallback_content_changed );
            }
        }
        $library_applied = false;
        $library_changed = 0;
        $library_layout_changed = 0;
        $library_skip_reason = '';
        $library_preserve_design = false;
        $template_source_fingerprint = [];
        $template_fidelity = [ 'ok' => true, 'schema' => 'wpae-template-fidelity-v1', 'status' => 'not_applicable', 'failures' => [] ];
        $selected_library = is_array( $library_retrieval['selected'] ?? null ) ? $library_retrieval['selected'] : [];
        if ( ! empty( $selected_library['elementor_data'] ) && is_array( $selected_library['elementor_data'] ) ) {
            $library_elements = wpae_llm_apply_library_template( $selected_library['elementor_data'], $message, $action_archetype, $library_changed, ! empty( $selected_library['trusted_bundled'] ) );
            if ( ! empty( $library_elements ) ) {
                $template_source_fingerprint = wpae_llm_template_fingerprint( $library_elements );
                $library_action = [
                    'action' => 'insert_elements',
                    'post_id' => $post_id,
                    'position' => 'end',
                    'elements' => $library_elements,
                ];
                $library_shape = wpae_llm_validate_action_shape( $library_action, $post_id );
                $library_fidelity = wpae_llm_content_fidelity( $message, $library_elements );
                $library_plan_audit = wpae_llm_content_plan_audit( $content_plan, $library_elements );
                if ( ! empty( $library_shape['ok'] ) && wpae_llm_count_widgets( $library_elements ) > 0 && ! empty( $library_fidelity['ok'] ) && ! empty( $library_plan_audit['ok'] ) ) {
                    $action['elements'] = $library_elements;
                    $library_applied = true;
                    $action_fallback = false;
                    $library_preserve_design = ! empty( $selected_library['trusted_bundled'] ) && $action_archetype !== 'hero';
                    if ( $library_preserve_design ) {
                        unset( $action['fallback_variant'] );
                        $placeholder_layout_changed = 0;
                        $action['elements'] = wpae_llm_normalize_library_layout( $action['elements'], $placeholder_layout_changed, $action_archetype );
                        $library_layout_changed += $placeholder_layout_changed;
                        $action['elements'] = wpae_llm_mark_preserved_library_design( $action['elements'] );
                        $action['elements'] = wpae_llm_materialize_preserved_library_colors( $action['elements'], $library_layout_changed );
                        $action['elements'] = wpae_llm_normalize_preserved_library_visual_state( $action['elements'], $library_layout_changed );
                        $action['elements'] = wpae_llm_normalize_preserved_library_typography( $action['elements'], $library_layout_changed );
                        $action['elements'] = wpae_llm_normalize_preserved_library_geometry( $action['elements'], $library_layout_changed );
                    }
                    if ( ! $library_preserve_design ) {
                        $action['elements'] = wpae_llm_normalize_library_layout( $action['elements'], $library_layout_changed, $action_archetype );
                    }
                } else {
                    $library_skip_reason = 'Adapted library block failed the native shape, content-fidelity, or semantic structure check.';
                }
            } else {
                $library_skip_reason = 'The selected library block has no repeatable content group that can be adapted.';
            }
        }
        if ( is_array( $action['elements'] ?? null ) && ! $library_preserve_design ) {
            $action['elements'] = wpae_llm_normalize_generated_typography( $action['elements'], $action_archetype, 0, $typography_changed );
            $action['elements'] = wpae_llm_apply_bento_layout( $action['elements'], $action_archetype, $bento_changed );
            $action['elements'] = wpae_llm_repair_unbalanced_repeatable_layout( $action['elements'], $message, $action_archetype, $composition_repair_changed );
            $action['elements'] = wpae_llm_normalize_process_timeline( $action['elements'], $message, $process_timeline_changed );
            $action['elements'] = wpae_llm_normalize_process_step_labels( $action['elements'], $action_archetype, $process_labels_changed );
        }
        $visual_grammar_changed = 0;
        if ( is_array( $action['elements'] ?? null ) && $library_preserve_design ) {
            $action['elements'] = wpae_llm_enforce_preserved_library_badge( $action['elements'], $action_archetype, $visual_grammar_changed );
        }
        if ( is_array( $action['elements'] ?? null ) && ! $library_preserve_design ) {
            $action['elements'] = wpae_llm_apply_generation_visual_grammar( $action['elements'], $action_archetype, $visual_grammar_changed );
            $final_bento_changed = 0;
            wpae_llm_normalize_bento_grids_recursive( $action['elements'], $final_bento_changed, $action_archetype );
            $bento_changed += $final_bento_changed;
            $cta_changed = 0;
            $action['elements'] = wpae_llm_normalize_requested_cta( $action['elements'], $message, $cta_changed );
        } elseif ( is_array( $action['elements'] ?? null ) ) {
            $cta_changed = 0;
            $action['elements'] = wpae_llm_normalize_requested_cta( $action['elements'], $message, $cta_changed, true );
        }
        if ( $action_archetype === 'process' && is_array( $action['elements'] ?? null ) ) {
            $process_contract_changed = 0;
            $action['elements'] = wpae_llm_enforce_process_timeline_contract( $action['elements'], $message, $process_contract_changed );
            $process_timeline_changed += $process_contract_changed;
        }
        $flex_contract_changed = 0;
		if ( is_array( $action['elements'] ?? null ) ) {
			$action['elements'] = wpae_llm_enforce_flex_layout_contract( $action['elements'], $action_archetype, $flex_contract_changed );
		}
		$pricing_contract_changed = 0;
		if ( $action_archetype === 'pricing' && is_array( $action['elements'] ?? null ) ) {
			$pricing_pairs = array_slice( wpae_llm_extract_pricing_content( $message ), 0, 8 );
			if ( count( $pricing_pairs ) >= 2 ) {
				$pricing_layout = wpae_llm_build_pricing_pair_layout( $action['elements'], $pricing_pairs, $pricing_contract_changed );
				if ( ! empty( $pricing_layout ) ) {
					$action['elements'] = $pricing_layout;
				}
			}
		}
		if ( wpae_llm_is_process_request( $message, $action_archetype ) && is_array( $action['elements'] ?? null ) ) {
			$final_process_changed = 0;
			$action['elements'] = wpae_llm_enforce_process_timeline_contract( $action['elements'], $message, $final_process_changed );
			$process_timeline_changed += $final_process_changed;
		}
        $hero_composition_changed = 0;
        if ( $action_archetype === 'hero' && is_array( $action['elements'] ?? null ) ) {
            $existing_for_image_rotation = [];
            if ( function_exists( 'wpae_get_elementor_data_for_post' ) ) {
                $saved_for_image_rotation = wpae_get_elementor_data_for_post( $post_id );
                if ( is_array( $saved_for_image_rotation ) ) {
                    $existing_for_image_rotation = $saved_for_image_rotation;
                }
            }
            $existing_image_urls = wpae_llm_collect_background_image_urls( $existing_for_image_rotation );
            $existing_image_urls = wpae_llm_sanitize_background_image_urls( array_merge( $existing_image_urls, $live_background_image_urls ) );
            $action['elements'] = wpae_llm_normalize_hero_composition( $action['elements'], $hero_composition_changed, $message, ! empty( $selected_library['trusted_bundled'] ), isset( $variation_seed ) ? (int) $variation_seed : -1, $existing_image_urls );
        }
        $render_cache_changed = 0;
        if ( is_array( $action['elements'] ?? null ) ) {
            wpae_llm_invalidate_render_cache( $action['elements'], $render_cache_changed );
        }
        $content_plan_audit = is_array( $action['elements'] ?? null )
            ? wpae_llm_content_plan_audit( $content_plan, $action['elements'] )
            : [ 'ok' => false, 'error' => 'No action elements were produced.' ];
        if ( $library_applied ) {
            $template_fidelity = wpae_llm_compare_template_fingerprint( $template_source_fingerprint, wpae_llm_template_fingerprint( (array) $action['elements'] ), $action_archetype );
        }
        $action_steps = [
            [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Загружены актуальные guide, skills и capabilities сайта.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION, 'custom_skills_count' => count( $guided_context['custom_skills'] ?? [] ), 'elementor_writes' => ! empty( $guided_context['capabilities']['capability_toggles']['elementor_writes'] ) ] ],
            [ 'id' => 'semantic_plan', 'status' => ! empty( $content_plan_audit['ok'] ) ? 'ok' : 'failed', 'message' => ! empty( $content_plan_audit['ok'] ) ? 'Смысловой план контента подтвержден до записи.' : 'Композиция нарушает смысловой план контента.', 'details' => [ 'plan' => $content_plan, 'audit' => $content_plan_audit ] ],
            [ 'id' => 'provider_response', 'status' => 'ok', 'message' => 'Ответ LLM-провайдера получен.', 'details' => wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] ) ],
            [
                'id' => 'command_decode',
                'status' => ! empty( $action_diagnostics['json_decoded'] ) ? 'ok' : 'failed',
                'message' => ! empty( $action_diagnostics['json_decoded'] ) ? 'Ответ разобран как Elementor JSON-команда.' : 'Ответ не разобран как Elementor JSON-команда.',
                'details' => $action_diagnostics,
            ],
            [
                'id' => 'hero_composition',
                'status' => $action_archetype === 'hero' ? 'ok' : 'skipped',
                'message' => $action_archetype === 'hero' ? 'Hero выровнен единообразно: badge, текст, иконки и CTA используют одну ось.' : 'Hero-нормализация не требуется для этого типа блока.',
                'details' => [ 'settings_updated' => $hero_composition_changed ],
            ],
			[ 'id' => 'flex_contract', 'status' => 'ok', 'message' => 'Все layout-контейнеры приведены к native Flexbox с responsive-правилами.', 'details' => [ 'settings_updated' => $flex_contract_changed, 'container_type' => 'flex', 'legacy_layout_allowed' => false ] ],
		];
		if ( $pricing_contract_changed > 0 ) {
			$action_steps[] = [ 'id' => 'pricing_contract', 'status' => 'ok', 'message' => 'Тарифный блок пересобран на финальной границе в одну чистую native Flex-композицию без generic placeholder-оболочек.', 'details' => [ 'cards_rebuilt' => true, 'card_count' => count( $pricing_pairs ?? [] ), 'container_type' => 'flex', 'generic_visual_wrappers_allowed' => false ] ];
		}
		if ( $vision_feedback_prompt !== '' ) {
			$action_steps[] = [
				'id' => 'vision_feedback_prompt',
				'status' => 'ok',
				'message' => 'Отчет AI Vision передан агенту отдельным дополнительным промтом; исходное задание сохранено главным.',
				'details' => [ 'regenerate' => $vision_regenerate, 'feedback_length' => strlen( $vision_feedback_prompt ) ],
			];
		}
		$library_trace = [
            'status' => $library_applied ? 'applied' : (string) ( $library_retrieval['status'] ?? 'skipped' ),
            'reason' => $library_applied
                ? 'Library composition was adapted to the user content and passed native checks.'
                : ( $library_skip_reason !== '' ? $library_skip_reason : (string) ( $library_retrieval['reason'] ?? 'Library retrieval was skipped.' ) ),
            'available_count' => (int) ( $library_retrieval['available_count'] ?? 0 ),
            'candidate_count' => (int) ( $library_retrieval['candidate_count'] ?? 0 ),
            'candidates' => (array) ( $library_retrieval['candidates'] ?? [] ),
            'selected' => ! empty( $selected_library ) ? array_intersect_key( $selected_library, array_flip( [ 'id', 'title', 'category', 'source', 'status', 'trusted_bundled', 'score', 'matched_terms' ] ) ) : null,
            'source_fingerprint' => $template_source_fingerprint,
            'fidelity' => $template_fidelity,
            'content_changes' => $library_changed,
            'layout_changes' => $library_layout_changed,
            'design_preserved' => $library_preserve_design,
            'bundled_fixture_id' => (string) ( $selected_library['bundled_fixture_id'] ?? '' ),
        ];
        $action_steps[] = [
            'id' => 'library_retrieval',
            'status' => $library_applied ? 'ok' : 'skipped',
            'message' => $library_applied ? 'Шаблон найден, адаптирован под контент и подготовлен к вставке.' : 'Подходящий шаблон не применен; использована обычная генерация.',
            'details' => $library_trace,
        ];
        if ( $library_applied ) {
            $action_steps[] = [
                'id' => 'template_fidelity',
                'status' => ! empty( $template_fidelity['ok'] ) ? 'ok' : 'failed',
                'message' => ! empty( $template_fidelity['ok'] ) ? 'Структурный отпечаток библиотечного шаблона сохранен после адаптации.' : 'Адаптированный шаблон не прошел структурную проверку и отклонен до записи.',
                'details' => $template_fidelity,
            ];
        }
        if ( $library_layout_changed > 0 ) {
            $action_steps[] = [ 'id' => 'library_layout', 'status' => 'ok', 'message' => 'Исходная геометрия библиотечного шаблона нормализована под native Flexbox и адаптивную bento-сетку.', 'details' => [ 'changes' => $library_layout_changed, 'container_type' => 'flex', 'max_items_per_row' => 4 ] ];
        }
        $content_fidelity = wpae_llm_content_fidelity( $message, (array) ( $action['elements'] ?? [] ) );
        $action_steps[] = [
            'id' => 'content_fidelity',
            'status' => ! empty( $content_fidelity['ok'] ) ? 'ok' : 'failed',
            'message' => ! empty( $content_fidelity['ok'] ) ? 'Явно заданный контент пользователя сохранен в native Elementor widgets.' : 'Команда не содержит весь явно заданный контент пользователя.',
            'details' => $content_fidelity,
        ];
        if ( empty( $content_fidelity['ok'] ) ) {
            return new WP_Error( 'wpae_llm_content_mismatch', 'LLM-команда отклонена: сгенерированный контент не соответствует запросу.', [ 'status' => 422, 'details' => [ 'content_fidelity' => $content_fidelity, 'action' => $action_diagnostics ] ] );
        }
        if ( empty( $content_plan_audit['ok'] ) ) {
            return new WP_Error( 'wpae_llm_content_plan_failed', 'LLM-команда отклонена: нарушен семантический план или использован запрещенный вид виджета.', [ 'status' => 422, 'details' => [ 'content_plan' => $content_plan, 'audit' => $content_plan_audit, 'steps' => $action_steps ] ] );
        }
        if ( $library_applied && empty( $template_fidelity['ok'] ) ) {
            return new WP_Error( 'wpae_llm_template_fidelity_failed', 'Библиотечный шаблон отклонен: после адаптации нарушена его структура.', [ 'status' => 422, 'details' => [ 'fidelity' => $template_fidelity, 'source_fingerprint' => $template_source_fingerprint, 'steps' => $action_steps ] ] );
        }
        if ( $action_fallback ) {
            $action_steps[] = [ 'id' => 'action_fallback', 'status' => 'ok', 'message' => 'Провайдер не вернул пригодное дерево; создан тематический native Elementor fallback с контентом запроса.', 'details' => array_merge( $action_diagnostics, [ 'content_changed' => $fallback_content_changed ] ) ];
        } elseif ( $action_repair ) {
            $action_steps[] = [ 'id' => 'action_repair', 'status' => 'ok', 'message' => 'Нарушенная JSON-команда была повторно запрошена и разобрана после repair-прохода.' ];
        } elseif ( isset( $repair_error ) && $repair_error !== '' ) {
            $action_steps[] = [ 'id' => 'action_repair', 'status' => 'failed', 'message' => 'Repair-проход не вернул пригодную Elementor-команду.', 'details' => [ 'attempts' => 2, 'error' => $repair_error ] ];
        }
        if ( $typography_changed > 0 ) {
            $action_steps[] = [ 'id' => 'typography_guard', 'status' => 'ok', 'message' => 'Семантическая типографика повторяющегося блока нормализована через native Elementor settings.', 'details' => [ 'archetype' => $action_archetype, 'author_headings_to_h5' => $typography_changed ] ];
        }
        if ( $bento_changed > 0 ) {
            $action_steps[] = [ 'id' => 'bento_layout', 'status' => 'ok', 'message' => 'Повторяющиеся native-контейнеры приведены к bento-сетке с переносом и responsive-размерами.', 'details' => [ 'archetype' => $action_archetype, 'max_items_per_row' => 4, 'containers_updated' => $bento_changed ] ];
        }
        if ( $composition_repair_changed > 0 ) {
            $action_steps[] = [ 'id' => 'composition_repair', 'status' => 'ok', 'message' => 'Несбалансированная повторяющаяся композиция упрощена до одной Flexbox-сетки без пустой боковой колонки.', 'details' => [ 'archetype' => $action_archetype, 'changes' => $composition_repair_changed ] ];
        }
        if ( $process_labels_changed > 0 ) {
            $action_steps[] = [ 'id' => 'process_labels', 'status' => 'ok', 'message' => 'Нумерация карточек процесса выровнена по порядку без изменения текста шагов.', 'details' => [ 'labels_updated' => $process_labels_changed ] ];
        }
        if ( $process_timeline_changed > 0 ) {
            $process_layout = wpae_llm_process_timeline_layout( $message );
            $action_steps[] = [ 'id' => 'process_timeline', 'status' => 'ok', 'message' => 'Процессный блок собран как ' . $process_layout . ' native Flex timeline с отдельными маркерами и connector-линией.', 'details' => [ 'containers_rebuilt' => $process_timeline_changed, 'layout' => $process_layout, 'mobile_direction' => 'single-column stack', 'icon_box_used' => false ] ];
        }
        if ( $visual_grammar_changed > 0 ) {
            $action_steps[] = [ 'id' => 'visual_grammar', 'status' => 'ok', 'message' => 'Для блока применено правило визуальной грамматики: outlined badge, отдельные native icon и семантические карточки.', 'details' => [ 'badge_or_card_icon_updates' => $visual_grammar_changed, 'badge_class' => 'wpae-generated-badge', 'card_widget' => 'heading + icon + text-editor', 'icon_position' => 'separate-native-widget' ] ];
        }
        if ( $render_cache_changed > 0 ) {
            $action_steps[] = [ 'id' => 'render_cache', 'status' => 'ok', 'message' => 'Устаревший Elementor render cache очищен перед записью обновленного контента.', 'details' => [ 'nodes_cleared' => $render_cache_changed ] ];
        }
        $execution_variation_seed = $library_applied ? -1 : ( isset( $variation_seed ) ? (int) $variation_seed : -1 );
        $execution = wpae_llm_execute_action( $action, $post_id, $action_archetype, $execution_variation_seed, $message );
        $execution['steps'] = array_merge( $action_steps, is_array( $execution['steps'] ?? null ) ? $execution['steps'] : [] );
        if ( empty( $execution['ok'] ) ) {
            return new WP_Error( 'wpae_llm_action_failed', 'LLM не выполнил задачу в Elementor.', [ 'status' => 422, 'details' => $execution ] );
        }
        return new WP_REST_Response( [ 'ok' => true, 'message' => 'Задача выполнена через Elementor. Вставлено элементов: ' . (int) $execution['inserted_count'] . '.', 'operation_id' => $execution['operation_id'] ?? null, 'action' => $execution['action'], 'write' => $execution, 'steps' => $execution['steps'], 'library' => $library_trace, 'provider' => $runtime['provider'], 'model' => $runtime['model'] ], 200 );
    }
    return new WP_REST_Response( [
        'ok' => true,
        'message' => substr( $reply, 0, 12000 ),
        'steps' => [
            [ 'id' => 'guided_context', 'status' => 'ok', 'message' => 'Чат использовал актуальные runtime-настройки сайта.', 'details' => [ 'guide_version' => WPAE_GUIDE_VERSION ] ],
            [ 'id' => 'provider_response', 'status' => 'ok', 'message' => 'Ответ LLM-провайдера получен.', 'details' => wpae_llm_response_diagnostics( is_array( $body ) ? $body : [] ) ],
            [ 'id' => 'answer_ready', 'status' => 'ok', 'message' => 'Ответ подготовлен для чата.' ],
        ],
        'provider' => $runtime['provider'],
        'model' => $runtime['model'],
    ], 200 );
}

function wpae_llm_chat_permission( WP_REST_Request $request ) {
    if ( wpae_get_request_api_key( $request ) !== '' ) {
        return wpae_auth_with_capability( $request, 'llm_chat' );
    }
    if ( ! wpae_capability_enabled( 'llm_chat' ) || ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'wpae_llm_editor_forbidden', 'LLM-чат доступен только авторизованному редактору при включенном разрешении.', [ 'status' => 403, 'capability' => 'llm_chat' ] );
    }
    $post_id = absint( $request->get_param( 'post_id' ) );
    if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_Error( 'wpae_llm_post_forbidden', 'Нет разрешения на редактирование этой страницы.', [ 'status' => 403, 'post_id' => $post_id ] );
    }
    return true;
}

function wpae_llm_undo( WP_REST_Request $request ): WP_REST_Response {
    $post_id = absint( $request->get_param( 'post_id' ) );
    $snapshot_id = sanitize_text_field( (string) $request->get_param( 'rollback_snapshot_id' ) );
    if ( $post_id <= 0 || $snapshot_id === '' || ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Недостаточно прав или параметров для отмены операции.' ], 403 );
    }
    $snapshots = function_exists( 'wpae_get_rollback_snapshots' ) ? wpae_get_rollback_snapshots() : [];
    $snapshot = is_array( $snapshots[ $snapshot_id ] ?? null ) ? $snapshots[ $snapshot_id ] : null;
    $snapshot_posts = array_map( 'absint', array_keys( (array) ( $snapshot['posts'] ?? [] ) ) );
    if ( $snapshot === null || ! in_array( $post_id, $snapshot_posts, true ) || count( $snapshot_posts ) !== 1 ) {
        return new WP_REST_Response( [ 'ok' => false, 'error' => 'Снимок отмены не найден или не относится к этой странице.', 'code' => 'wpae_undo_scope_mismatch' ], 404 );
    }
    $rollback = wpae_restore_rollback_snapshot_by_id( $snapshot_id, true );
    return new WP_REST_Response( [ 'ok' => ! empty( $rollback['ok'] ), 'operation_id' => wpae_llm_new_operation_id(), 'rollback' => $rollback ], (int) ( $rollback['status'] ?? 422 ) );
}

function wpae_get_llm_guide(): array {
    $settings = wpae_llm_get_settings();
    return [
        'optional' => true,
        'capability' => 'llm_chat',
        'enabled' => function_exists( 'wpae_capability_enabled' ) && wpae_capability_enabled( 'llm_chat' ),
        'configured' => ! empty( $settings['has_api_key'] ) && $settings['base_url'] !== '',
        'endpoint' => 'POST /wp-json/ai-executor/v1/llm/chat',
        'protocol' => 'OpenAI-compatible Chat Completions; supports OpenAI, DeepSeek, OpenRouter, Gemini, and custom HTTPS-compatible gateways.',
        'request' => [
            'message' => 'Required string, maximum 4000 characters.',
            'history' => 'Optional last 12 user/assistant messages; not stored by the plugin.',
            'context' => 'Optional bounded editor context with post_id and a selected Elementor subtree including native settings and descendants. When a selected element and an edit request are present, the chat returns a server-scoped patch_elements action instead of rebuilding the page.',
        ],
        'safety' => 'Normal chat is advisory. Explicit action requests may insert only one populated root Flexbox container or apply a bounded native patch to selected elements when elementor_writes is enabled. The plugin runs a dry-run preview before writing and routes the write through update, preflight, protected-zone, visual-regression, and rollback checks. Delete and replace actions are not supported. If the provider returns tutorial text instead of the required action JSON, the write is rejected.',
        'guided_editor_mode' => 'The floating Elementor editor chat injects the current guide, enabled custom skills, capabilities, and project design system into action requests. It uses the same internal Elementor validation/update pipeline without exposing the site API key to browser JavaScript.',
        'editor_preview_sync' => 'After a successful insert, the floating chat synchronizes new models through the open Elementor editor API. After a selected patch, it applies native settings to the selected model tree and refreshes the preview only when the canvas cannot confirm the change. The response reports the sync mode and changed ids; it does not claim realtime success without a canvas check.',
        'action_content_gate' => 'An explicit insert action is rejected unless it contains exactly one populated root container with at least one native Elementor widget. A targeted patch is rejected unless it has a current post_id, bounded patches, existing selected element ids, and native editable paths.',
        'semantic_content_plan' => 'Before decoding an insert, the server derives a bounded content plan from the user brief: block archetype, content units, explicit CTA, media requirement, allowed native widget families, and forbidden Icon Box. The post-write tree is audited against that plan, including rejection of invented buttons when no CTA was requested.',
        'template_fidelity' => 'When a private library block is selected, the adapted one-root composition receives a structural fingerprint before final normalization. The write is rejected if the heading/media/grid anchors are lost, legacy layout remains, or the required native Flexbox/badge contract is missing.',
        'flex_contract' => 'Before Elementor preflight, every container is normalized to native Flexbox, fixed height/flex-basis and negative margins are cleared, repeatable groups become responsive wrapping grids, and grid shells remain transparent while cards carry their own surfaces.',
        'visual_grammar' => 'Every generated root block receives one compact outlined rounded native badge. Icon-box is forbidden: card headings remain native heading widgets and any card icon is a separate native icon widget. Testimonial cards use a native heading for author/company plus a native text-editor for the quote, with icons decorative only. This is enforced after provider decoding and before Elementor preflight.',
        'preview_and_undo' => 'Every successful write returns operation_id, compact diff, rollback_snapshot_id, and rollback expiry. The editor chat exposes one-click undo through POST /wp-json/ai-executor/v1/llm/undo, scoped to the current post and authenticated editor.',
        'execution_trace' => 'Action and advisory responses include a safe operational steps array for the chat UI and JSON log: provider response, command decoding, validation, preview, native normalization, design-system mapping, page context, Elementor update, sync, Vision review, and final status. It never contains hidden reasoning, credentials, prompts, raw page payloads or raw provider responses.',
        'editor_vision_review' => 'When ai_vision is enabled and configured, the floating Elementor chat captures the refreshed preview and sends it to /llm/vision-review together with the original user brief, bounded visible-text/DOM context, and a bounded generated Elementor JSON excerpt. Vision must check content_fidelity as well as visual quality. Full-block generations treat received quality failures as a blocking quality gate, roll back the write, and allow at most two repair passes; a temporarily unavailable review is retained for manual verification, while selected-tree patches remain advisory. Strict atomic rollback remains available through transaction_vision_review.',
        'content_fidelity' => 'Explicit content from the user request must survive into native Elementor widgets. The server extracts explicit quoted phrases, checks the action tree before write, repairs deterministic fallback content when possible, and rejects the write with a missing-content list when fidelity cannot be proven.',
        'provider_rate_limit' => [ 'calls' => WPAE_LLM_CALL_LIMIT, 'window_seconds' => WPAE_LLM_CALL_WINDOW, 'scope' => 'site-wide' ],
        'privacy' => 'Provider keys are encrypted in wp_options. Prompts, histories, and raw provider responses are not stored or logged.',
    ];
}
