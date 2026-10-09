<?php
/** Production chat-route regressions for server-owned generated-copy intake.
 * Included by flex-generation-runtime.php after the chat harness and route factory exist.
 */

$intake_hero_prompt = 'Создай Hero для небольшой архитектурной студии, которая проектирует частные дома и общественные пространства. Напиши заголовок и описание на основе этого задания, без фото, цифр, обещаний и выдуманных достижений. CTA «Обсудить проект» → #contact.';
$intake_hero_response = [
	'family' => 'hero',
	'generated' => [
		[ 'slot_id' => 'section_title', 'text' => 'Архитектура для жизни и встреч', 'fact_refs' => [] ],
		[ 'slot_id' => 'section_intro', 'text' => 'Проектируем частные дома и общественные пространства с вниманием к задачам места и повседневному использованию.', 'fact_refs' => [] ],
	],
];
$intake_hero = $run_services_route( $intake_hero_prompt, [ provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-generated-hero' );
$intake_hero_pipeline = (array) ( $intake_hero['response']['diagnostics']['design_pipeline'] ?? [] );
check( ! empty( $intake_hero['response']['ok'] ), 'Ordinary plugin chat accepts generated Hero copy through the production intake: ' . wp_json_encode( $intake_hero['error'], JSON_UNESCAPED_UNICODE ) );
check( $intake_hero['calls'] === 1 && $intake_hero['writes'] === 1 && $intake_hero['write_attempts'] === 1 && ( $intake_hero['response']['diagnostics']['provider_calls'] ?? -1 ) === 1, 'Generated Hero uses one typed provider intake and one transaction only' );
check( ( $intake_hero_pipeline['brief']['intake']['source'] ?? '' ) === 'provider_typed_intake' && ( $intake_hero_pipeline['brief']['intake']['provider_calls'] ?? -1 ) === 1 && ( $intake_hero_pipeline['brief']['hash'] ?? '' ) === ( $intake_hero_pipeline['plan']['brief_hash'] ?? '' ), 'Generated Hero freezes one provider-derived Brief consumed by its accepted Plan' );

$intake_live_hero_prompt = 'Создай самостоятельный блок Hero для небольшой архитектурной и интерьерной студии. Сформулируй оригинальный заголовок и короткое описание: студия проектирует жилые пространства от планировочной идеи до понятной документации, учитывая дневной свет, повседневное движение и хранение. Это весь набор разрешённых фактов о тестовой студии. Не добавляй цифры, сроки, опыт, клиентов, награды, географию, гарантии, цены или обещания. Заголовок и описание должны быть написаны моделью; готовых строк для них нет. Добавь одну основную кнопку с точным текстом «Обсудить проект» и точной ссылкой #contact. Без изображения и без второй кнопки. Композиция — автоматически. Добавь блок к существующей странице.';
$intake_live_hero = $run_services_route( $intake_live_hero_prompt, [ provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-live-natural-hero-cta' );
$intake_live_hero_pipeline = (array) ( $intake_live_hero['response']['diagnostics']['design_pipeline'] ?? [] );

$intake_plan_refusal_prompt = 'Создай Hero для архитектурной студии. Оставь точную фразу «Только по записи» как дополнительный текст, а заголовок и описание напиши по заданию: проектирование жилых пространств с учётом света и хранения.';
$intake_plan_refusal = $run_services_route( $intake_plan_refusal_prompt, [ provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-plan-refusal-telemetry' );
$intake_plan_refusal_details = (array) ( $intake_plan_refusal['error']['data']['details'] ?? [] );
check( ( $intake_plan_refusal['error']['code'] ?? '' ) === 'wpae_design_plan_rejected' && $intake_plan_refusal['calls'] === 1 && $intake_plan_refusal['writes'] === 0 && $intake_plan_refusal['roots'] === array_column( $legacy_page, 'id' ) && ( $intake_plan_refusal_details['provider_calls'] ?? -1 ) === 1 && ( $intake_plan_refusal_details['provider_call_count_source'] ?? '' ) === 'canonical_intake_telemetry' && ( $intake_plan_refusal_details['intake']['provider_calls'] ?? -1 ) === 1, 'Downstream Plan refusal preserves actual canonical-intake provider-call telemetry and remains no-write' );

$intake_collect_strings = static function ( array $nodes ) use ( &$intake_collect_strings ): array {
	$strings = [];
	foreach ( $nodes as $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$settings = (array) ( $node['settings'] ?? [] );
		foreach ( [ 'title', 'editor', 'text', 'url' ] as $key ) { if ( is_string( $settings[ $key ] ?? null ) ) { $strings[] = wp_strip_all_tags( (string) $settings[ $key ] ); } }
		if ( is_array( $settings['link'] ?? null ) && is_string( $settings['link']['url'] ?? null ) ) { $strings[] = (string) $settings['link']['url']; }
		foreach ( (array) ( $settings['tabs'] ?? [] ) as $tab ) { if ( is_array( $tab ) ) { $strings[] = (string) ( $tab['tab_title'] ?? '' ); $strings[] = wp_strip_all_tags( (string) ( $tab['tab_content'] ?? '' ) ); } }
		$strings = array_merge( $strings, $intake_collect_strings( (array) ( $node['elements'] ?? [] ) ) );
	}
	return array_values( array_filter( $strings, static fn( string $value ): bool => $value !== '' ) );
};

$intake_live_hero_strings = $intake_collect_strings( [ $intake_live_hero['written'] ] );
check( ! empty( $intake_live_hero['response']['ok'] ) && $intake_live_hero['calls'] === 1 && $intake_live_hero['writes'] === 1 && in_array( 'Обсудить проект', $intake_live_hero_strings, true ) && in_array( '#contact', $intake_live_hero_strings, true ) && empty( array_filter( (array) ( $intake_live_hero_pipeline['plan']['validation']['errors'] ?? [] ), static fn( string $error ): bool => $error === 'unbound_explicit_content:text' ) ), 'Ordinary plugin chat binds natural exact CTA language before the generated Hero Plan freezes: ' . wp_json_encode( [ 'error' => $intake_live_hero['error'], 'calls' => $intake_live_hero['calls'], 'writes' => $intake_live_hero['writes'], 'native_strings' => $intake_live_hero_strings, 'plan_errors' => $intake_live_hero_pipeline['plan']['validation']['errors'] ?? [] ], JSON_UNESCAPED_UNICODE ) );

$intake_unknown_prompt = 'Создай визуальный фрагмент с заголовком и коротким пояснением для архитектурного бюро. Не добавляй фото.';
$intake_unknown_probe = wpae_brief_ir_parse( $intake_unknown_prompt );
$intake_unknown_response = [ 'family' => 'hero', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Пространство, созданное для людей', 'fact_refs' => [] ], [ 'slot_id' => 'section_intro', 'text' => 'Проектируем частные дома и общественные пространства, учитывая задачи места и повседневную жизнь.', 'fact_refs' => [] ] ] ];
$intake_unknown = $run_services_route( $intake_unknown_prompt, [ provider_reply( wp_json_encode( $intake_unknown_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-classified-freeform' );
$intake_unknown_pipeline = (array) ( $intake_unknown['response']['diagnostics']['design_pipeline'] ?? [] );
check( ( $intake_unknown_probe['intent']['archetype'] ?? '' ) === 'unknown' && ! empty( $intake_unknown['response']['ok'] ) && $intake_unknown['calls'] === 1 && $intake_unknown['writes'] === 1 && ( $intake_unknown_pipeline['brief']['archetype'] ?? '' ) === 'hero' && ( $intake_unknown_pipeline['brief']['hash'] ?? '' ) === ( $intake_unknown_pipeline['plan']['brief_hash'] ?? '' ), 'Ordinary one-line free-form chat classifies once into a supported family, then freezes one Brief for the Plan: ' . wp_json_encode( [ 'probe_family' => $intake_unknown_probe['intent']['archetype'] ?? '', 'error' => $intake_unknown['error'], 'calls' => $intake_unknown['calls'], 'brief_family' => $intake_unknown_pipeline['brief']['archetype'] ?? '', 'brief_hash' => $intake_unknown_pipeline['brief']['hash'] ?? '', 'plan_hash' => $intake_unknown_pipeline['plan']['brief_hash'] ?? '' ], JSON_UNESCAPED_UNICODE ) );

$intake_hero_strings = $intake_collect_strings( [ $intake_hero['written'] ] );
check( in_array( 'Архитектура для жизни и встреч', $intake_hero_strings, true ) && in_array( 'Проектируем частные дома и общественные пространства с вниманием к задачам места и повседневному использованию.', $intake_hero_strings, true ) && in_array( '#contact', $intake_hero_strings, true ) && ! in_array( 'https://images.unsplash.com/photo-m1-fixture?wpae=1200x800', $intake_hero_strings, true ), 'Generated Hero native readback keeps model copy and exact CTA, with no forbidden photo' );

// Prove the server-generated fields use null source spans plus an authenticated
// provider provenance record; user-supplied copy remains source-spanned.
$intake_previous_options = $GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] ?? null;
$GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'design_pipeline_mode' => 'active', 'design_engine_mode' => 'active' ];
$GLOBALS['http_calls'] = [];
$GLOBALS['responses'] = [ provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ];
$intake_direct = wpae_brief_ir_intake_extract( $intake_hero_prompt, wpae_brief_ir_parse( $intake_hero_prompt ), [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'test-key' ] );
$intake_generated_items = array_values( array_filter( (array) ( $intake_direct['brief']['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['copy_status'] ?? '' ) === 'generated' ) );
check( ! empty( $intake_direct['ok'] ) && count( $intake_generated_items ) === 2 && count( array_filter( $intake_generated_items, static fn( $item ): bool => array_key_exists( 'source_span', $item ) && $item['source_span'] === null && wpae_brief_ir_generated_content_valid( $item, $intake_direct['brief'] ) ) ) === 2, 'Generated copy has null source spans and validates only with the server HMAC provenance: ' . wp_json_encode( [ 'ok' => $intake_direct['ok'] ?? false, 'error' => $intake_direct['error'] ?? '', 'intake' => $intake_direct['brief']['intake'] ?? [], 'items' => $intake_generated_items, 'valid' => array_map( static fn( $item ): bool => wpae_brief_ir_generated_content_valid( $item, $intake_direct['brief'] ), $intake_generated_items ) ], JSON_UNESCAPED_UNICODE ) );
check( ( $GLOBALS['http_calls'][0]['body']['max_tokens'] ?? 0 ) === WPAE_BRIEF_IR_INTAKE_MAX_COMPLETION_TOKENS, 'Typed provider intake uses its shared bounded multilingual response budget' );
if ( $intake_previous_options === null ) { unset( $GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] ); } else { $GLOBALS['options'][WPAE_LLM_SETTINGS_OPTION] = $intake_previous_options; }

// The shared OpenRouter transport can make one bounded request-format retry.
// Intake telemetry and the frozen Brief must report actual HTTP attempts.
$GLOBALS['responses'] = [
	[ 'response' => [ 'code' => 400 ], 'body' => wp_json_encode( [ 'error' => [ 'message' => 'No endpoints found' ] ] ) ],
	provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
];
$intake_transport_retry = $run_services_route( $intake_hero_prompt, $GLOBALS['responses'], [], 'intake-generated-hero-transport-retry' );
$intake_transport_trace = (array) ( $intake_transport_retry['response']['diagnostics']['intake'] ?? [] );
$intake_transport_brief = (array) ( $intake_transport_retry['response']['diagnostics']['design_pipeline']['brief'] ?? [] );
check( ! empty( $intake_transport_retry['response']['ok'] ) && $intake_transport_retry['calls'] === 2 && $intake_transport_retry['writes'] === 1 && ( $intake_transport_trace['provider_calls'] ?? 0 ) === 2 && ( $intake_transport_trace['retry_count'] ?? 0 ) === 1 && ( $intake_transport_trace['retry_reason'] ?? '' ) === 'structured_response_format_rejected', 'Typed intake telemetry counts the shared transport retry, one frozen Brief, and one write' );
check( ( $intake_transport_retry['http_calls'][0]['body']['max_tokens'] ?? 0 ) === WPAE_BRIEF_IR_INTAKE_MAX_COMPLETION_TOKENS && ( $intake_transport_retry['http_calls'][1]['body']['max_tokens'] ?? 0 ) === WPAE_BRIEF_IR_INTAKE_MAX_COMPLETION_TOKENS && ! array_key_exists( 'response_format', $intake_transport_retry['http_calls'][1]['body'] ), 'The single schema retry preserves the bounded response budget while changing only the rejected format requirement' );
check( ( $intake_transport_brief['intake']['provider_calls'] ?? 0 ) === 2 && ( $intake_transport_brief['intake']['retry_count'] ?? 0 ) === 1, 'Frozen Brief summary keeps actual provider-attempt and retry counts' );
$GLOBALS['http_calls'] = [];
$GLOBALS['responses'] = [ [ 'response' => [ 'code' => 400 ], 'body' => wp_json_encode( [ 'error' => [ 'message' => 'No endpoints found' ] ] ) ], provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ];
$intake_direct_retry = wpae_brief_ir_intake_extract( $intake_hero_prompt, wpae_brief_ir_parse( $intake_hero_prompt ), [ 'provider' => 'openrouter', 'model' => 'openrouter/free', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'test-key' ] );
$intake_retry_items = array_values( array_filter( (array) ( $intake_direct_retry['brief']['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['copy_status'] ?? '' ) === 'generated' ) );
check( ! empty( $intake_direct_retry['ok'] ) && ( $intake_direct_retry['telemetry']['provider_calls'] ?? 0 ) === 2 && ( $intake_direct_retry['telemetry']['retry_count'] ?? 0 ) === 1 && count( array_filter( $intake_retry_items, static fn( $item ): bool => wpae_brief_ir_generated_content_valid( $item, $intake_direct_retry['brief'] ) ) ) === 2, 'Generated provenance remains valid while retaining the true provider-attempt count' );

$GLOBALS['http_calls'] = [];
$GLOBALS['responses'] = [
	[ 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [ 'choices' => [ [ 'finish_reason' => 'length', 'message' => [ 'content' => '{"family":"hero","generated":[' ] ] ] ] ) ],
	provider_reply( wp_json_encode( $intake_hero_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
];
$intake_length_retry = $run_services_route( $intake_hero_prompt, $GLOBALS['responses'], [], 'intake-generated-hero-length-retry' );
$intake_length_trace = (array) ( $intake_length_retry['response']['diagnostics']['intake'] ?? [] );
check( ! empty( $intake_length_retry['response']['ok'] ) && $intake_length_retry['calls'] === 2 && $intake_length_retry['writes'] === 1 && ( $intake_length_trace['retry_reason'] ?? '' ) === 'structured_response_finish_reason_length' && ( $intake_length_trace['first_finish_reason'] ?? '' ) === 'length' && ( $intake_length_trace['finish_reason'] ?? '' ) === 'stop', 'A length-truncated typed response receives exactly one bounded retry and exposes both finish reasons' );

$GLOBALS['http_calls'] = [];
$GLOBALS['responses'] = [ [ 'response' => [ 'code' => 400 ], 'body' => wp_json_encode( [ 'error' => [ 'message' => 'No endpoints found' ] ] ) ] ];
$GLOBALS['provider_sleep_usec'] = 200000;
$expired_retry_attempts = [];
$expired_retry = wpae_llm_provider_request( 'https://openrouter.ai/api/v1/chat/completions', [ 'timeout' => 5, 'body' => '{}' ], [ 'model' => 'openrouter/free', 'messages' => [] ], true, 'openrouter', microtime( true ) + 1.05, $expired_retry_attempts );
unset( $GLOBALS['provider_sleep_usec'] );
check( is_wp_error( $expired_retry ) && $expired_retry->get_error_code() === 'wpae_llm_provider_budget_exhausted' && count( $GLOBALS['http_calls'] ) === 1 && ( $expired_retry_attempts['provider_calls'] ?? 0 ) === 1 && ( $expired_retry_attempts['retry_count'] ?? -1 ) === 0, 'Expired shared deadline refuses the schema retry without claiming an unmade provider attempt' );

$intake_about_prompt = "Создай блок О нас с фото из разрешённых материалов. Заголовок: «Студия, которая слушает место».\nФакты: учитываем рельеф участка; согласуем планировку до разработки чертежей.\nСформулируй два коротких абзаца только на основе этих фактов.";
$intake_about_probe = wpae_brief_ir_parse( $intake_about_prompt );
$intake_about_slots = wpae_brief_ir_generated_copy_slots( $intake_about_probe, $intake_about_prompt );
$intake_about_facts = wpae_brief_ir_approved_facts( $intake_about_probe, $intake_about_prompt );
$intake_about_payload = [ 'family' => 'about', 'generated' => [ [ 'slot_id' => 'section_intro', 'text' => "Учитываем рельеф участка при выборе проектных решений.\n\nПланировку согласуем до перехода к чертежам.", 'fact_refs' => array_column( $intake_about_facts, 'id' ) ] ] ];
$intake_about_media = array_replace( $m1_media, [ 'role' => 'about', 'group_id' => 'about', 'alt' => 'Современный интерьер архитектурной студии.' ] );
$intake_about = $run_services_route( $intake_about_prompt, [ provider_reply( wp_json_encode( $intake_about_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-hybrid-about', false, 'active', 'active', [ 'media_references' => [ $intake_about_media ] ] );
$intake_about_pipeline = (array) ( $intake_about['response']['diagnostics']['design_pipeline'] ?? [] );
$intake_about_strings = $intake_collect_strings( [ $intake_about['written'] ] );
$intake_about_strings = array_map( static fn( string $value ): string => preg_replace( "/\\n{2,}/", "\n", $value ), $intake_about_strings );
check( array_column( $intake_about_slots, 'slot_id' ) === [ 'section_intro' ] && count( $intake_about_facts ) >= 2 && ! empty( $intake_about['response']['ok'] ) && $intake_about['calls'] === 1 && $intake_about['writes'] === 1, 'Hybrid About ordinary chat locks its exact title and generates only the authorized body slot: ' . wp_json_encode( [ 'slots' => $intake_about_slots, 'facts' => $intake_about_facts, 'error' => $intake_about['error'] ], JSON_UNESCAPED_UNICODE ) );
check( in_array( 'Студия, которая слушает место', $intake_about_strings, true ) && in_array( "Учитываем рельеф участка при выборе проектных решений.\nПланировку согласуем до перехода к чертежам.", $intake_about_strings, true ) && ( $intake_about_pipeline['brief']['hash'] ?? '' ) === ( $intake_about_pipeline['plan']['brief_hash'] ?? '' ), 'Hybrid About preserves exact title and ordered generated paragraph copy in native readback while the Plan uses one frozen Brief hash' );

$intake_about_directive_prompt = 'Создай новую редакционную секцию About. Заголовок должен быть ровно таким и не изменяться: «Проект начинается с диалога о привычках». Используй изображение https://images.unsplash.com/photo-1766230976347-c5badd3f76c9?auto=format&fit=crop&fm=jpg&h=675&ixlib=rb-4.1.0&q=80&w=1200 . Alt должен быть точным: «Современный архитектурный интерьер с панорамным светом.» Лицензия: Unsplash License. Сформулируй два коротких абзаца только из разрешённых фактов.';
$intake_about_directive_brief = wpae_brief_ir_parse( $intake_about_directive_prompt );
$intake_about_directive_slots = wpae_brief_ir_generated_copy_slots( $intake_about_directive_brief, $intake_about_directive_prompt );
$intake_about_directive_title = array_values( array_filter( (array) ( $intake_about_directive_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'title' ) );
$intake_about_directive_alt = (string) ( $intake_about_directive_brief['media_references'][0]['alt'] ?? '' );
$intake_about_directive_alt_as_copy = array_filter( (array) ( $intake_about_directive_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['exact_text'] ?? '' ) === 'Современный архитектурный интерьер с панорамным светом.' );
check( ( $intake_about_directive_brief['intent']['archetype'] ?? '' ) === 'about' && ( $intake_about_directive_title[0]['exact_text'] ?? '' ) === 'Проект начинается с диалога о привычках' && array_column( $intake_about_directive_slots, 'slot_id' ) === [ 'section_intro' ] && $intake_about_directive_alt === 'Современный архитектурный интерьер с панорамным светом.' && empty( $intake_about_directive_alt_as_copy ), 'About directive language keeps exact title in its typed slot and alt metadata out of authored copy; intake generates only the authorized intro: ' . wp_json_encode( [ 'family' => $intake_about_directive_brief['intent']['archetype'] ?? '', 'content' => $intake_about_directive_brief['content'] ?? [], 'slots' => $intake_about_directive_slots, 'alt' => $intake_about_directive_alt, 'asset' => $intake_about_directive_brief['media_references'][0] ?? [] ], JSON_UNESCAPED_UNICODE ) );

$intake_ordered_benefits_prompt = 'Создай секцию Benefits с тремя преимуществами. Заголовки и их порядок сохрани точно: «Сценарии до деталей»; «Целостность решений»; «Документация для реализации». Напиши по одному короткому пояснению для каждой темы, используя только эти факты: на первой встрече собирают пожелания и ограничения помещения; планировочные варианты сравнивают по дневному свету, маршрутам движения и хранению; после выбора направления согласуют материалы, инженерные решения и документацию; решения и открытые вопросы фиксируют для дальнейшего обсуждения. Пояснения сгенерируй моделью, сохрани связь каждого описания с соответствующим заголовком. Не добавляй цифры, сроки, опыт, клиентов, награды, гарантии, обещания, CTA, фотографии или другие изображения. Композиция — автоматически. Добавь секцию к существующей странице.';
$intake_ordered_benefits_brief = wpae_brief_ir_parse( $intake_ordered_benefits_prompt );
$intake_ordered_benefits_titles = array_values( array_filter( (array) ( $intake_ordered_benefits_brief['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'feature_title' ) );
$intake_ordered_benefits_slots = wpae_brief_ir_generated_copy_slots( $intake_ordered_benefits_brief, $intake_ordered_benefits_prompt );
$intake_ordered_benefits_spans = array_reduce( $intake_ordered_benefits_titles, static fn( bool $valid, array $item ): bool => $valid && substr( $intake_ordered_benefits_prompt, (int) $item['source_span'][0], (int) $item['source_span'][1] - (int) $item['source_span'][0] ) === (string) $item['exact_text'], true );
check( ( $intake_ordered_benefits_brief['intent']['archetype'] ?? '' ) === 'benefits' && array_column( $intake_ordered_benefits_titles, 'exact_text' ) === [ 'Сценарии до деталей', 'Целостность решений', 'Документация для реализации' ] && array_column( $intake_ordered_benefits_titles, 'group_id' ) === [ 'benefits_1', 'benefits_2', 'benefits_3' ] && $intake_ordered_benefits_spans && array_column( $intake_ordered_benefits_slots, 'slot_id' ) === [ 'benefits_1_description', 'benefits_2_description', 'benefits_3_description' ] && empty( $intake_ordered_benefits_brief['media_references'] ), 'Benefits keeps ordered quoted headings as exact grouped entities and exposes only three generated copy slots' );
$intake_inline_benefit_facts = wpae_brief_ir_approved_facts( $intake_ordered_benefits_brief, $intake_ordered_benefits_prompt );
$intake_inline_benefit_labeled_facts = array_values( array_filter( $intake_inline_benefit_facts, static fn( array $fact ): bool => ( $fact['provenance']['label'] ?? '' ) === 'labeled_fact' ) );
$intake_inline_benefit_fact_spans = array_reduce( $intake_inline_benefit_facts, static fn( bool $valid, array $fact ): bool => $valid && substr( $intake_ordered_benefits_prompt, (int) $fact['source_span'][0], (int) $fact['source_span'][1] - (int) $fact['source_span'][0] ) === (string) $fact['exact_text'], true );
check( array_column( $intake_inline_benefit_labeled_facts, 'exact_text' ) === [ 'на первой встрече собирают пожелания и ограничения помещения', 'планировочные варианты сравнивают по дневному свету, маршрутам движения и хранению', 'после выбора направления согласуют материалы, инженерные решения и документацию', 'решения и открытые вопросы фиксируют для дальнейшего обсуждения' ] && $intake_inline_benefit_fact_spans && empty( array_filter( $intake_inline_benefit_facts, static fn( array $fact ): bool => str_contains( $fact['exact_text'], 'Пояснения сгенерируй' ) || str_contains( $fact['exact_text'], 'Не добавляй' ) ) ), 'Inline Benefits facts: ' . wp_json_encode( [ 'facts' => $intake_inline_benefit_facts, 'spans_valid' => $intake_inline_benefit_fact_spans ], JSON_UNESCAPED_UNICODE ) );

$intake_inline_faq_prompt = 'Создай FAQ в виде настоящего native Elementor Accordion с тремя вопросами в указанном порядке. Вопросы сохрани точно. Сгенерируй ответы только на основе фактов сразу под соответствующим вопросом; не добавляй сроки, цены, гарантии, обязательства или дополнительные условия. Первый ответ должен быть коротким, третий — длинным, но полностью подтверждённым указанными фактами.' . "\n\n" . 'Вопрос 1: «Что обсуждается на первой встрече?» Факты: собирают пожелания и ограничения помещения.' . "\n" . 'Вопрос 2: «Как сравниваются планировочные варианты?» Факты: сравнивают дневной свет, маршруты движения и хранение.' . "\n" . 'Вопрос 3: «Что происходит после выбора планировочного направления?» Факты: согласуют материалы, инженерные решения и документацию; решения и открытые вопросы фиксируют для дальнейшего обсуждения.' . "\n\n" . 'Сохрани точные пары вопрос/ответ и порядок, не добавляй новые вопросы и визуальные карточки вместо Accordion. После создания проверь каждый ответ штатным раскрытием. Композиция — автоматически. Добавь FAQ к существующей странице.';
$intake_inline_faq_brief = wpae_brief_ir_parse( $intake_inline_faq_prompt );
$intake_inline_faq_facts = wpae_brief_ir_approved_facts( $intake_inline_faq_brief, $intake_inline_faq_prompt );
$intake_inline_faq_fact_spans = array_reduce( $intake_inline_faq_facts, static fn( bool $valid, array $fact ): bool => $valid && substr( $intake_inline_faq_prompt, (int) $fact['source_span'][0], (int) $fact['source_span'][1] - (int) $fact['source_span'][0] ) === (string) $fact['exact_text'], true );
check( ( $intake_inline_faq_brief['intent']['archetype'] ?? '' ) === 'faq' && array_column( $intake_inline_faq_facts, 'exact_text' ) === [ 'собирают пожелания и ограничения помещения', 'сравнивают дневной свет, маршруты движения и хранение', 'согласуют материалы, инженерные решения и документацию', 'решения и открытые вопросы фиксируют для дальнейшего обсуждения' ] && $intake_inline_faq_fact_spans, 'Inline FAQ fact labels after exact questions are approved with source spans and group question text is not treated as a fact' );

$intake_benefits_prompt = "Создай Benefits. Напиши описания к этим точным темам; не добавляй фото, CTA или числовые обещания.\nПреимущество 1: «Планировка под привычки»\nПреимущество 2: «Материалы местного производства»\nПреимущество 3: «Работа с рельефом»";
$intake_benefits_probe = wpae_brief_ir_parse( $intake_benefits_prompt );
$intake_benefits_slots = wpae_brief_ir_generated_copy_slots( $intake_benefits_probe, $intake_benefits_prompt );
$intake_benefits_facts = wpae_brief_ir_approved_facts( $intake_benefits_probe, $intake_benefits_prompt );
$intake_benefits_payload = [ 'family' => 'benefits', 'generated' => [] ];
$intake_benefit_copy = [ 'benefits_1_description' => 'Сверяем планировочные решения с привычками, названными в задании.', 'benefits_2_description' => 'Подбираем местные материалы в соответствии с замыслом проекта.', 'benefits_3_description' => 'Учитываем рельеф участка при разработке пространственных решений.' ];
foreach ( $intake_benefits_slots as $index => $slot ) { $intake_benefits_payload['generated'][] = [ 'slot_id' => $slot['slot_id'], 'text' => $intake_benefit_copy[ $slot['slot_id'] ] ?? '', 'fact_refs' => [ (string) ( $intake_benefits_facts[ $index ]['id'] ?? '' ) ] ]; }
$intake_benefits = $run_services_route( $intake_benefits_prompt, [ provider_reply( wp_json_encode( $intake_benefits_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-generated-benefits' );
$intake_benefits_strings = $intake_collect_strings( [ $intake_benefits['written'] ] );
check( array_column( $intake_benefits_slots, 'slot_id' ) === [ 'benefits_1_description', 'benefits_2_description', 'benefits_3_description' ] && ! empty( $intake_benefits['response']['ok'] ) && $intake_benefits['calls'] === 1 && $intake_benefits['writes'] === 1, 'Benefits ordinary chat generates one description for each of three stable groups' );
check( count( array_intersect( [ 'Планировка под привычки', 'Материалы местного производства', 'Работа с рельефом' ], $intake_benefits_strings ) ) === 3 && count( array_intersect( array_values( $intake_benefit_copy ), $intake_benefits_strings ) ) === 3 && ! in_array( 'https://images.unsplash.com/photo-m1-fixture?wpae=1200x800', $intake_benefits_strings, true ) && ( $intake_benefits['response']['diagnostics']['design_pipeline']['brief']['hash'] ?? '' ) === ( $intake_benefits['response']['diagnostics']['design_pipeline']['plan']['brief_hash'] ?? '' ), 'Benefits readback keeps title/body pairs, no added media, and the same frozen Brief hash' );

$intake_live_benefits_prompt = 'Создай блок Benefits с тремя пунктами. Используй эти точные заголовки и сохрани их порядок: «Планировка под повседневные сценарии»; «Работа с дневным светом»; «Продуманное хранение». Для каждого заголовка напиши одно короткое пояснение на основе только смысла самой темы; не добавляй цифры, сроки, гарантии, клиентов, результаты или другие бизнес-факты. Не меняй заголовки. Без фотографий, CTA, дополнительных пунктов и метрик. Композицию выбери автоматически. Добавь блок к существующей странице.';
$intake_live_benefits_probe = wpae_brief_ir_parse( $intake_live_benefits_prompt );
$intake_live_benefits_titles = array_values( array_filter( (array) ( $intake_live_benefits_probe['content'] ?? [] ), static fn( $item ): bool => is_array( $item ) && ( $item['role'] ?? '' ) === 'feature_title' ) );
$intake_live_benefits_slots = wpae_brief_ir_generated_copy_slots( $intake_live_benefits_probe, $intake_live_benefits_prompt );
$intake_live_benefits_facts = wpae_brief_ir_approved_facts( $intake_live_benefits_probe, $intake_live_benefits_prompt );
$intake_live_benefits_copy = [ 'benefits_1_description' => 'Учитываем привычные действия при организации планировки.', 'benefits_2_description' => 'Рассматриваем дневной свет как часть восприятия пространства.', 'benefits_3_description' => 'Предусматриваем хранение в общей организации интерьера.' ];
$intake_live_benefits_payload = [ 'family' => 'benefits', 'generated' => [] ];
foreach ( $intake_live_benefits_slots as $index => $slot ) {
	$intake_live_benefits_payload['generated'][] = [ 'slot_id' => $slot['slot_id'], 'text' => $intake_live_benefits_copy[ $slot['slot_id'] ] ?? '', 'fact_refs' => [ (string) ( $intake_live_benefits_facts[ $index ]['id'] ?? '' ) ] ];
}
$intake_live_benefits = $run_services_route( $intake_live_benefits_prompt, [ provider_reply( wp_json_encode( $intake_live_benefits_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-generated-benefits-ordered-quoted-headings' );
$intake_live_benefits_strings = $intake_collect_strings( [ $intake_live_benefits['written'] ] );
$intake_live_benefits_pipeline = (array) ( $intake_live_benefits['response']['diagnostics']['design_pipeline'] ?? [] );
check( array_column( $intake_live_benefits_titles, 'exact_text' ) === [ 'Планировка под повседневные сценарии', 'Работа с дневным светом', 'Продуманное хранение' ] && array_column( $intake_live_benefits_titles, 'group_id' ) === [ 'benefits_1', 'benefits_2', 'benefits_3' ] && array_column( $intake_live_benefits_slots, 'slot_id' ) === [ 'benefits_1_description', 'benefits_2_description', 'benefits_3_description' ], 'Benefits maps declared ordered headings to exact entity groups and authorized copy slots' );
check( array_reduce( $intake_live_benefits_titles, static fn( bool $valid, array $item ): bool => $valid && substr( $intake_live_benefits_prompt, (int) $item['source_span'][0], (int) $item['source_span'][1] - (int) $item['source_span'][0] ) === (string) $item['exact_text'], true ) && ! empty( $intake_live_benefits['response']['ok'] ) && $intake_live_benefits['calls'] === 1 && $intake_live_benefits['writes'] === 1 && ( $intake_live_benefits_pipeline['brief']['hash'] ?? '' ) === ( $intake_live_benefits_pipeline['plan']['brief_hash'] ?? '' ), 'Benefits ordinary chat preserves source spans and freezes one Brief before its single transaction' );
check( count( array_intersect( array_values( $intake_live_benefits_copy ), $intake_live_benefits_strings ) ) === 3 && ! array_filter( $intake_live_benefits_strings, static fn( string $text ): bool => str_contains( $text, 'images.unsplash.com' ) || str_contains( $text, '#contact' ) ), 'Benefits generated body text does not add media or CTA when forbidden' );

$intake_pricing_prompt = "Создай Pricing. Напиши только заголовок раздела и вступление; тарифные факты не меняй.\n«Старт» — «50 000 ₸/мес» — «Малый проект»\nFeatures: «Аудит», «План»\nКнопка: «Выбрать Старт», ссылка #start\n«Проект» — «150 000 ₸/год» — «Полный проект»\nFeatures: «Дизайн», «Разработка»\nКнопка: «Выбрать Проект», ссылка #project\n«Сопровождение» — «210 000 ₸/год» — «Контроль реализации»\nFeatures: «Выезды», «Отчёт»\nКнопка: «Выбрать Сопровождение», ссылка #support";
$intake_pricing_probe = wpae_brief_ir_parse( $intake_pricing_prompt );
$intake_pricing_slots = wpae_brief_ir_generated_copy_slots( $intake_pricing_probe, $intake_pricing_prompt );
$intake_pricing_payload = [ 'family' => 'pricing', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Тарифы на архитектурный проект', 'fact_refs' => [] ], [ 'slot_id' => 'section_intro', 'text' => 'Выберите состав работы, который подходит задаче вашего проекта.', 'fact_refs' => [] ] ] ];
$intake_pricing = $run_services_route( $intake_pricing_prompt, [ provider_reply( wp_json_encode( $intake_pricing_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-hybrid-pricing' );
$intake_pricing_strings = $intake_collect_strings( [ $intake_pricing['written'] ] );
check( array_column( $intake_pricing_slots, 'slot_id' ) === [ 'section_title', 'section_intro' ] && ! empty( $intake_pricing['response']['ok'] ) && $intake_pricing['calls'] === 1 && $intake_pricing['writes'] === 1, 'Pricing generated slots are limited to the explicitly requested section title and intro' );
foreach ( [ '50 000 ₸', '/мес', 'Малый проект', 'Аудит', 'План', 'Выбрать Старт', '#start', '150 000 ₸', '/год', 'Полный проект', 'Дизайн', 'Разработка', 'Выбрать Проект', '#project', '210 000 ₸', 'Контроль реализации', 'Выезды', 'Отчёт', 'Выбрать Сопровождение', '#support' ] as $pricing_exact ) { check( in_array( $pricing_exact, $intake_pricing_strings, true ), 'Hybrid Pricing exact tier fact survives intake/compiler/readback: ' . $pricing_exact ); }

$intake_faq_prompt = "Создай FAQ. Вопрос 1: «Какие сведения нужны для начала?»\nВопрос 2: «Как согласуем состав работ?»\nФакты: На первой встрече обсуждаем задачу и исходные материалы; После изучения исходных материалов согласуем состав работ и смету.\nСформулируй ответы только на основе этих фактов.";
$intake_faq_probe = wpae_brief_ir_parse( $intake_faq_prompt );
$intake_faq_slots = wpae_brief_ir_generated_copy_slots( $intake_faq_probe, $intake_faq_prompt );
$intake_faq_facts = wpae_brief_ir_approved_facts( $intake_faq_probe, $intake_faq_prompt );
$intake_faq_payload = [ 'family' => 'faq', 'generated' => [ [ 'slot_id' => 'faq_1_answer', 'text' => 'На первой встрече обсуждаем задачу и исходные материалы.', 'fact_refs' => [ 'fact_1' ] ], [ 'slot_id' => 'faq_2_answer', 'text' => 'После изучения исходных материалов согласуем состав работ и смету.', 'fact_refs' => [ 'fact_2' ] ] ] ];
$intake_faq = $run_services_route( $intake_faq_prompt, [ provider_reply( wp_json_encode( $intake_faq_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-hybrid-faq' );
$intake_faq_strings = $intake_collect_strings( [ $intake_faq['written'] ] );
check( array_column( $intake_faq_slots, 'slot_id' ) === [ 'faq_1_answer', 'faq_2_answer' ] && count( $intake_faq_facts ) === 2 && ! empty( $intake_faq['response']['ok'] ) && $intake_faq['calls'] === 1 && $intake_faq['writes'] === 1, 'FAQ ordinary chat preserves exact question groups and generates only the two authorized answers' );
check( in_array( 'Какие сведения нужны для начала?', $intake_faq_strings, true ) && in_array( 'Как согласуем состав работ?', $intake_faq_strings, true ) && in_array( 'На первой встрече обсуждаем задачу и исходные материалы.', $intake_faq_strings, true ) && in_array( 'После изучения исходных материалов согласуем состав работ и смету.', $intake_faq_strings, true ) && in_array( 'accordion', $m1_widgets( [ $intake_faq['written'] ] ), true ), 'FAQ native readback keeps question/answer group bindings in a real Accordion' );

$intake_bad_responses = [
	'invented_number' => [ 'family' => 'hero', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Лучшие решения для 12 семей', 'fact_refs' => [] ], [ 'slot_id' => 'section_intro', 'text' => 'Проектируем дома и общественные пространства под задачи заказчика.', 'fact_refs' => [] ] ] ],
	'invented_url' => [ 'family' => 'hero', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Пространство для жизни', 'fact_refs' => [] ], [ 'slot_id' => 'section_intro', 'text' => 'Обсудите задачу на https://invented.example.', 'fact_refs' => [] ] ] ],
	'unknown_fact_ref' => [ 'family' => 'benefits', 'generated' => [ [ 'slot_id' => 'benefits_1_description', 'text' => 'Сверяем планировочные решения с привычками из задания.', 'fact_refs' => [ 'fact_unknown' ] ], [ 'slot_id' => 'benefits_2_description', 'text' => 'Подбираем местные материалы в соответствии с замыслом.', 'fact_refs' => [ 'fact_2' ] ], [ 'slot_id' => 'benefits_3_description', 'text' => 'Учитываем рельеф участка при разработке решений.', 'fact_refs' => [ 'fact_3' ] ] ] ],
	'duplicate_fact_ref' => [ 'family' => 'benefits', 'generated' => [ [ 'slot_id' => 'benefits_1_description', 'text' => 'Сверяем планировочные решения с привычками из задания.', 'fact_refs' => [ 'fact_1', 'fact_1' ] ], [ 'slot_id' => 'benefits_2_description', 'text' => 'Подбираем местные материалы в соответствии с замыслом.', 'fact_refs' => [ 'fact_2' ] ], [ 'slot_id' => 'benefits_3_description', 'text' => 'Учитываем рельеф участка при разработке решений.', 'fact_refs' => [ 'fact_3' ] ] ] ],
	'duplicate_slot' => [ 'family' => 'hero', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Архитектура для жизни', 'fact_refs' => [] ], [ 'slot_id' => 'section_title', 'text' => 'Второй заголовок', 'fact_refs' => [] ] ] ],
	'wrong_family' => [ 'family' => 'about', 'generated' => [ [ 'slot_id' => 'section_title', 'text' => 'Архитектура для жизни', 'fact_refs' => [] ], [ 'slot_id' => 'section_intro', 'text' => 'Проектируем дома и пространства.', 'fact_refs' => [] ] ] ],
];
$intake_negative_hero_prompt = 'Создай Hero без фото. Напиши заголовок и описание для архитектурной студии.';
foreach ( $intake_bad_responses as $name => $payload ) {
	$prompt = in_array( $name, [ 'unknown_fact_ref', 'duplicate_fact_ref' ], true ) ? $intake_benefits_prompt : $intake_negative_hero_prompt;
	$result = $run_services_route( $prompt, [ provider_reply( wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ], [], 'intake-refusal-' . $name );
	check( ! empty( $result['error'] ) && $result['calls'] === 1 && $result['writes'] === 0 && $result['write_attempts'] === 0 && $result['page_data'] === $legacy_page, 'Typed intake ' . $name . ' rejection is one-call, no-write, and cannot enter legacy generation' );
}
$intake_invalid_json = $run_services_route( $intake_negative_hero_prompt, [ provider_reply( 'not JSON' ) ], [], 'intake-refusal-invalid-schema' );
$intake_timeout = $run_services_route( $intake_negative_hero_prompt, [ new WP_Error( 'http_request_failed', 'simulated intake timeout' ) ], [], 'intake-refusal-timeout' );
$intake_missing_slot = $run_services_route( $intake_negative_hero_prompt, [ provider_reply( wp_json_encode( [ 'family' => 'hero', 'generated' => [] ] ) ) ], [], 'intake-refusal-missing-slot' );
check( ! empty( $intake_invalid_json['error'] ) && $intake_invalid_json['calls'] === 1 && $intake_invalid_json['writes'] === 0 && $intake_invalid_json['page_data'] === $legacy_page, 'Invalid intake schema is an honest one-call refusal before transaction' );
check( ! empty( $intake_timeout['error'] ) && $intake_timeout['calls'] === 1 && $intake_timeout['writes'] === 0 && $intake_timeout['page_data'] === $legacy_page, 'Intake timeout is not retried through an independent path and never writes partial content' );
check( ! empty( $intake_missing_slot['error'] ) && $intake_missing_slot['calls'] === 1 && $intake_missing_slot['writes'] === 0 && $intake_missing_slot['page_data'] === $legacy_page, 'Missing authorized generated slots refuse without placeholder copy or partial root' );
$intake_team_strict = $run_services_route( 'Создай блок команды для архитектурной студии. Напиши представление команды.', [], [], 'intake-strict-team' );
check( ! empty( $intake_team_strict['error'] ) && $intake_team_strict['calls'] === 0 && $intake_team_strict['writes'] === 0 && $intake_team_strict['page_data'] === $legacy_page, 'Team remains entity-bound and does not enter generic invented-copy generation' );

$intake_cta_prompt = 'Создай самостоятельный CTA-блок. Заголовок: «Напишите нам ✨». Описание: «Обсудим задачу и выберем удобный способ связи». Основная кнопка: «Написать», ссылка mailto:hello@example.test. Вторая кнопка: «Позвонить», ссылка tel:+77000000000.';
$intake_cta = $run_services_route( $intake_cta_prompt, [], [], 'intake-exact-cta' );
$intake_cta_strings = $intake_collect_strings( [ $intake_cta['written'] ] );
check( ! empty( $intake_cta['response']['ok'] ) && $intake_cta['calls'] === 0 && $intake_cta['writes'] === 1, 'Exact CTA copy compiles locally without a needless provider intake call' );
check( in_array( 'Напишите нам ✨', $intake_cta_strings, true ) && in_array( 'mailto:hello@example.test', $intake_cta_strings, true ) && in_array( 'tel:+77000000000', $intake_cta_strings, true ), 'Exact CTA keeps Cyrillic emoji and byte-accurate mailto/tel destinations' );
