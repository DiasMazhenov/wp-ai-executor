<?php
/** Cross-family fixtures included by the runtime harness, with external WP/transport mocks only. */
$m1_media = [ 'asset_id' => 'm1-asset', 'source_url' => 'https://images.unsplash.com/photo-m1-fixture?wpae=1200x800', 'role' => 'hero', 'alt' => 'Студия', 'license' => 'Unsplash License', 'allowed_reuse' => true, 'provenance' => [ 'source' => 'test_approved_catalog', 'approved_catalog' => true, 'catalog_id' => 'm1-test-media-fixture' ] ];
$m1_copy = "Надзаголовок: «СТУДИЯ»\nЗаголовок: «Работа со смыслом»\nОписание: «Согласуем задачу и доведём проект до результата.»\nКнопка: «Начать», ссылка #start";
$m1_features = "Преимущество 1: «Понятный план»\nОписание преимущества 1: «Сроки согласованы.»\nПреимущество 2: «Общая команда»\nОписание преимущества 2: «Работаем вместе.»";
$m1_pricing = "Создай Pricing\n«Старт» — «50 000 ₸/мес» — «Малый проект»\nFeatures: «Аудит», «План»\nКнопка: «Выбрать Старт», ссылка #start\n«Проект» — «150 000 ₸/год» — «Полный проект»\nFeatures: «Дизайн», «Разработка»\nКнопка: «Выбрать Проект», ссылка #project";
$m1_faq = "Создай FAQ\nВопрос 1: «Как начать?»\nОтвет 1: «Обсудим задачу.»\nВопрос 2: «Как согласовать?»\nОтвет 2: «Покажем план.»";
$m1_cases = [
 'hero_stack' => [ "Создай Hero text-only без фото\n" . $m1_copy, [], 'hero', 'stacked_left' ],
 'hero_split' => [ "Создай Hero 60/40 с фото\n" . $m1_copy, [ 'media_references' => [ $m1_media ] ], 'hero', 'split_60_40' ],
 'about_split' => [ "Создай About 50/50 с фото\n" . $m1_copy, [ 'media_references' => [ array_replace( $m1_media, [ 'role' => 'about' ] ) ] ], 'about', 'split_50_50' ],
 'benefits_grid' => [ "Создай Benefits benefits.grid\n" . $m1_features, [], 'benefits', 'three_cards' ],
 'benefits_list' => [ "Создай Benefits editorial_list\n" . $m1_features, [], 'benefits', 'editorial_list' ],
 'pricing' => [ $m1_pricing, [], 'pricing', 'three_cards' ],
 'faq' => [ $m1_faq, [], 'faq', 'linear' ],
];
$m1_results = [];
$m1_summary = [];
$m1_widgets = static function ( array $nodes ) use ( &$m1_widgets ): array {
 $types = [];
 foreach ( $nodes as $node ) {
  if ( isset( $node['widgetType'] ) ) { $types[] = $node['widgetType']; }
  $types = array_merge( $types, $m1_widgets( (array) ( $node['elements'] ?? [] ) ) );
 }
 return $types;
};
$m1_topology = static function ( array $nodes ) use ( &$m1_topology ): array {
 return array_map( static fn( array $node ): array => [ $node['widgetType'] ?? $node['elType'], $m1_topology( (array) ( $node['elements'] ?? [] ) ) ], $nodes );
};
foreach ( $m1_cases as $name => [ $prompt, $ctx, $family, $composition ] ) {
 $result = $run_services_route( $prompt, [], $incompatible_pricing_fixture, 'm1-' . $name, false, 'active', 'active', $ctx );
 $m1_results[ $name ] = $result;
 $trace = $result['response']['diagnostics']['design_pipeline'] ?? [];
 $ledger = $result['response']['diagnostics']['operation_ledger'] ?? [];
 check( ! empty( $result['response']['ok'] ), 'M1 ' . $name . ' actual chat success: ' . wp_json_encode( $result['error'], JSON_UNESCAPED_UNICODE ) );
 check( $trace['brief']['archetype'] === $family && $trace['plan']['archetype'] === $family && $trace['brief']['hash'] === $trace['content_plan']['brief_hash'] && $ledger['brief_hash'] === $trace['brief']['hash'], 'M1 ' . $name . ' one canonical Brief hash/family across content, Plan and ledger' );
 check( ( $trace['plan']['composition_decision']['identity'] ?? '' ) === $family . '.' . $composition, 'M1 ' . $name . ' explicit/default composition is recorded' );
 check( $result['calls'] === 0 && $result['writes'] === 1 && $result['write_attempts'] === 1 && ( $result['response']['diagnostics']['action_path'] ?? '' ) === 'pipeline', 'M1 ' . $name . ' one mock transaction, zero providers despite candidate' );
 check( $ledger['current_state'] === 'written' && ! empty( $trace['frozen_decisions']['readback_matches'] ) && $trace['frozen_decisions']['plan_hash'] === $trace['plan']['hash'], 'M1 ' . $name . ' frozen Plan and compiled semantic signature match saved readback' );
 check( array_slice( $result['page_data'], 0, count( $legacy_page ) ) === $legacy_page && $ledger['root_ids'] === [ $result['written']['id'] ], 'M1 ' . $name . ' exact neighbors and owned ledger root preserved' );
 $widgets = array_count_values( $m1_widgets( [ $result['written'] ] ) );
 $m1_summary[] = [ 'fixture' => $name, 'request' => $prompt, 'family' => $family, 'brief_hash' => $trace['brief']['hash'], 'composition' => $composition, 'widgets' => $widgets, 'topology_hash' => hash( 'sha256', wp_json_encode( $m1_topology( [ $result['written'] ] ) ) ), 'validation' => 'ok', 'route' => 'pipeline', 'provider_calls' => $result['calls'], 'writes' => $result['writes'], 'readback' => true, 'render' => 'NOT_RUN' ];
}
check( $m1_topology( [ $m1_results['hero_stack']['written'] ] ) !== $m1_topology( [ $m1_results['hero_split']['written'] ] ), 'M1 Hero split versus stacked topology differs independently of IDs/settings' );
check( $m1_topology( [ $m1_results['benefits_grid']['written'] ] ) !== $m1_topology( [ $m1_results['benefits_list']['written'] ] ), 'M1 Benefits grid versus list topology differs independently of IDs/settings' );
check( in_array( 'accordion', $m1_widgets( [ $m1_results['faq']['written'] ] ), true ), 'M1 FAQ is native Accordion, not a text downgrade' );
$pricing_copy = wp_json_encode( $m1_results['pricing']['written'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
foreach ( [ '50 000 ₸', '/мес', '150 000 ₸', '/год', 'Аудит', 'План', 'Дизайн', 'Разработка', 'Выбрать Старт', '#start', 'Выбрать Проект', '#project' ] as $exact ) { check( str_contains( $pricing_copy, $exact ), 'M1 Pricing preserves exact tier field ' . $exact ); }
$m1_long = str_repeat( 'Длинный текст без обрезания. ', 55 );
$m1_long_prompt = "Создай Hero text-only без фото\nЗаголовок: «Длинный текст»\nОписание: «" . trim( $m1_long ) . '»';
$m1_long_result = $run_services_route( $m1_long_prompt, [], [], 'm1-long' );
check( ! empty( $m1_long_result['response']['ok'] ) && str_contains( wp_json_encode( $m1_long_result['written'], JSON_UNESCAPED_UNICODE ), trim( $m1_long ) ), 'M1 long explicit copy over 500 characters survives real chat/compiler/write' );
check( ! str_contains( wp_json_encode( $m1_long_result['written'] ), 'text-only' ) && ! str_contains( wp_json_encode( $m1_long_result['written'] ), 'min_height' ), 'M1 instructions are not visible text and long text has no fixed clipping height' );
$m1_refusals = [
 'forbidden_media' => [ "Создай Hero без фото\n" . $m1_copy, [ 'media_references' => [ $m1_media ] ] ],
 'unresolved_media' => [ "Создай Hero 60/40 с фото\n" . $m1_copy, [] ],
 'about_missing_media' => [ "Создай About\n" . $m1_copy, [] ],
 'library_only' => [ "Создай Hero только из библиотеки\n" . $m1_copy, [] ],
 'wrong_post' => [ "Создай Hero без фото\n" . $m1_copy, [ 'post_id' => 99 ] ],
];
foreach ( $m1_refusals as $name => [ $prompt, $ctx ] ) {
 $result = $run_services_route( $prompt, [], [], 'm1-refuse-' . $name, false, 'active', 'active', $ctx );
 check( ! empty( $result['error'] ) && $result['calls'] === 0 && $result['writes'] === 0 && $result['page_data'] === $legacy_page, 'M1 ' . $name . ' no-write refusal without provider/legacy: ' . wp_json_encode( $result['error'] ) );
}
$m1_local = wpae_brief_ir_parse( $m1_cases['hero_stack'][0] );
$m1_local_success = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-local-success', false, 'active', 'active', [ 'canonical_brief' => $m1_local ] );
check( ( $m1_local_success['error']['code'] ?? '' ) === 'wpae_client_brief_not_trusted' && $m1_local_success['calls'] === 0 && $m1_local_success['writes'] === 0 && $m1_local_success['page_data'] === $legacy_page, 'M1 rejects client-supplied canonical Brief before provider or transaction; intake owns canonicalization' );
$m1_unknown = $m1_local;
$m1_unknown['layout_constraints'][] = [ 'kind' => 'composition', 'value' => 'unknown_composition', 'source_span' => [ 0, 0 ], 'provenance' => [ 'source' => 'local_fixture' ] ];
// Replace the existing explicit selection instead of creating an ambiguous duplicate.
$m1_unknown['layout_constraints'] = array_values( array_filter( $m1_unknown['layout_constraints'], static fn( array $c ): bool => $c['kind'] !== 'composition' || $c['value'] === 'unknown_composition' ) );
$m1_unknown_result = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-unknown', false, 'active', 'active', [ 'canonical_brief' => $m1_unknown ] );
check( ( $m1_unknown_result['error']['code'] ?? '' ) === 'wpae_client_brief_not_trusted' && $m1_unknown_result['writes'] === 0 && $m1_unknown_result['calls'] === 0 && $m1_unknown_result['page_data'] === $legacy_page, 'M1 client cannot substitute an unknown composition through a submitted Brief' );
$m1_pair_brief = wpae_brief_ir_parse( $m1_cases['benefits_grid'][0] );
$m1_pair_brief['canonical_create'] = true;
$m1_pair_plan = wpae_design_plan_from_brief( $m1_pair_brief, [ 'canonical_create' => true ] );
$m1_pair_collection_index = array_key_first( array_filter( (array) $m1_pair_plan['sections'][0]['children'], static fn( $child ): bool => is_array( $child ) && ( $child['role'] ?? '' ) === 'feature_cards' ) );
$m1_pair_plan['sections'][0]['children'][ $m1_pair_collection_index ]['items'][0]['body_ref'] = $m1_pair_plan['sections'][0]['children'][ $m1_pair_collection_index ]['items'][1]['body_ref'];
check( in_array( 'cross_group_binding', wpae_design_plan_validate( $m1_pair_plan, $m1_pair_brief )['errors'], true ), 'M1 Plan rejects cross-group Benefits binding' );
$m1_pair_plan['sections'][0]['children'][ $m1_pair_collection_index ]['items'][0]['body_ref'] = 'missing_ref';
check( in_array( 'unknown_content_ref:missing_ref', wpae_design_plan_validate( $m1_pair_plan, $m1_pair_brief )['errors'], true ), 'M1 Plan rejects unknown content reference' );
$m1_capability_types = \Elementor\Plugin::$types;
\Elementor\Plugin::$types = array_values( array_diff( $m1_capability_types, [ 'accordion' ] ) );
$m1_capability = $run_services_route( $m1_faq, [], [], 'm1-missing-accordion' );
\Elementor\Plugin::$types = $m1_capability_types;
check( ! empty( $m1_capability['error'] ) && $m1_capability['writes'] === 0 && $m1_capability['calls'] === 0, 'M1 FAQ capability refusal cannot downgrade Accordion into copy' );
foreach ( [ 'stale_revision', 'protected_zone' ] as $reason ) {
 $result = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-' . $reason, false, 'active', 'active', [], [ 'code' => 'wpae_test_' . $reason, 'status' => 409, 'message' => $reason ] );
 check( ! empty( $result['error'] ) && $result['writes'] === 0 && $result['calls'] === 0 && $result['page_data'] === $legacy_page, 'M1 existing boundary ' . $reason . ' preserves document and cannot restart generation' );
}
$m1_failure = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-transaction-failure', true );
check( ! empty( $m1_failure['error'] ) && $m1_failure['writes'] === 0 && $m1_failure['calls'] === 0 && $m1_failure['page_data'] === $legacy_page, 'M1 transaction failure terminates without repeat write or fallback' );
$m1_before_saved = $GLOBALS['page_data'];
foreach ( [ '{}', '{"root":{}}', 'broken json', '' ] as $raw ) {
 $GLOBALS['m1_raw_saved'] = $raw;
 check( is_wp_error( wpae_get_elementor_data_for_post( 42 ) ), 'M1 saved read rejects missing/invalid/object JSON instead of []' );
}
$GLOBALS['m1_raw_saved'] = '[]';
check( wpae_get_elementor_data_for_post( 42 ) === [], 'M1 genuine saved empty list remains valid data' );
unset( $GLOBALS['m1_raw_saved'] );
check( $GLOBALS['page_data'] === $m1_before_saved, 'M1 saved read regression is non-mutating' );

// Locally supplied specialized refs still pass through the real production entrypoint.
$m1_pricing_brief = wpae_brief_ir_parse( $m1_pricing );
foreach ( [ 'unknown_ref', 'cross_group' ] as $reason ) {
 $bad = $m1_pricing_brief;
 $bad['pricing_items'][0]['description_ref'] = $reason === 'unknown_ref' ? 'unknown' : $bad['pricing_items'][1]['description_ref'];
 $result = $run_services_route( $m1_pricing, [], [], 'm1-local-' . $reason, false, 'active', 'active', [ 'canonical_brief' => $bad ] );
 check( ( $result['error']['code'] ?? '' ) === 'wpae_client_brief_not_trusted' && $result['calls'] === 0 && $result['writes'] === 0 && $result['page_data'] === $legacy_page, 'M1 real chat refuses client-supplied ' . $reason . ' typed Brief before write' );
}
$bad_faq = "Создай FAQ\nВопрос 1: «Как начать?»\nОтвет 2: «Обсудим задачу.»\nВопрос 2: «Как согласовать?»\nОтвет 1: «Покажем план.»";
$cross_faq = $run_services_route( $bad_faq, [], [], 'm1-cross-faq' );
check( ! empty( $cross_faq['error'] ) && $cross_faq['writes'] === 0 && $cross_faq['calls'] === 0, 'M1 explicit FAQ numbering cannot bind the answer of another question' );
$m1_tier_nodes = [];
$m1_subtree_has_button = static function ( array $node ) use ( &$m1_subtree_has_button ): bool {
 if ( ( $node['widgetType'] ?? '' ) === 'button' ) { return true; }
 foreach ( (array) ( $node['elements'] ?? [] ) as $child ) { if ( is_array( $child ) && $m1_subtree_has_button( $child ) ) { return true; } }
 return false;
};
$find_m1_tiers = static function ( array $nodes ) use ( &$find_m1_tiers, &$m1_tier_nodes, &$m1_subtree_has_button ): void {
 foreach ( $nodes as $node ) {
  $children = (array) ( $node['elements'] ?? [] );
  if ( ( $node['elType'] ?? '' ) === 'container' && ( $node['settings']['flex_direction'] ?? '' ) === 'column' && ( $node['settings']['border_border'] ?? '' ) === 'solid' && count( $children ) === 2 && ( $children[0]['elType'] ?? '' ) === 'container' && ( $children[1]['elType'] ?? '' ) === 'container' && $m1_subtree_has_button( $children[1] ) ) {
   $encoded = wp_json_encode( $node, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
   if ( str_contains( $encoded, 'Старт' ) || str_contains( $encoded, 'Проект' ) ) { $m1_tier_nodes[] = $node; }
  }
  $find_m1_tiers( (array) ( $node['elements'] ?? [] ) );
 }
};
$find_m1_tiers( [ $m1_results['pricing']['written'] ] );
check( count( $m1_tier_nodes ) === 2, 'M1 Pricing owns exactly two native tier cards' );
foreach ( [ [ 'Старт', '50 000 ₸', '/мес', 'Аудит', 'План', '#start' ], [ 'Проект', '150 000 ₸', '/год', 'Дизайн', 'Разработка', '#project' ] ] as $index => $fields ) {
 $tier_json = wp_json_encode( $m1_tier_nodes[ $index ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
 foreach ( $fields as $field ) { check( str_contains( $tier_json, $field ), 'M1 tier ' . $index . ' retains owned field ' . $field ); }
 $other_href = $index === 0 ? '#project' : '#start';
 check( ! str_contains( $tier_json, $other_href ), 'M1 CTA cannot leak into the other tier' );
}
$m1_style_brief = wpae_brief_ir_parse( $m1_cases['hero_stack'][0] );
$m1_style_plan = wpae_design_plan_from_brief( $m1_style_brief, [ 'canonical_create' => true, 'page_tokens' => [ 'color.primary' => '#123456' ] ] );
check( $m1_style_plan['resolved_visual']['values']['color.primary'] !== '#123456', 'M1 ignores unconfirmed page tokens' );
$m1_style_plan = wpae_design_plan_from_brief( $m1_style_brief, [ 'canonical_create' => true, 'page_tokens_confirmed' => true, 'page_tokens' => [ 'color.primary' => '#123456', 'color.muted' => '#aaaaaa' ] ] );
check( $m1_style_plan['resolved_visual']['values']['color.primary'] === '#123456' && $m1_style_plan['resolved_visual']['sources']['color.primary'] === 'confirmed_page' && $m1_style_plan['resolved_visual']['values']['color.muted'] === '#aaaaaa' && $m1_style_plan['resolved_visual']['sources']['color.muted'] === 'confirmed_page' && ! empty( $m1_style_plan['resolved_visual']['contrast']['errors'] ) && in_array( 'color.muted_on_color.page_bg', $m1_style_plan['resolved_visual']['contrast']['errors'], true ) && $m1_style_plan['resolved_visual']['adjustments'] === [], 'M1 confirmed page colors retain provenance; low contrast is reported instead of replaced with an unconfirmed gray' );
$m1_changed = $m1_results['hero_stack']['written'];
$m1_changed['settings']['padding_mobile']['left'] = '99';
check( wpae_llm_decision_signature( [ $m1_changed ] ) !== wpae_llm_decision_signature( [ $m1_results['hero_stack']['written'] ] ), 'M1 signature detects accepted responsive geometry changes' );
$m1_changed = $m1_results['hero_stack']['written'];
$m1_changed['settings']['_css_classes'] .= ' changed-visual-class';
check( wpae_llm_decision_signature( [ $m1_changed ] ) !== wpae_llm_decision_signature( [ $m1_results['hero_stack']['written'] ] ), 'M1 signature freezes visual classes while permitting operation metadata' );

$m1_grid_no_photo = $run_services_route( "Создай Benefits без фото\n" . $m1_features, [], [], 'm1-grid-no-photo' );
check( ! empty( $m1_grid_no_photo['response']['ok'] ), 'M1 Benefits media prohibition does not select Hero stacked composition' );
$m1_plain_long = $run_services_route( 'Create Hero text-only. Title: "Long". Body: "' . trim( $m1_long ) . '"', [], [], 'm1-plain-long' );
check( ! empty( $m1_plain_long['response']['ok'] ) && str_contains( wp_json_encode( $m1_plain_long['written'], JSON_UNESCAPED_UNICODE ), trim( $m1_long ) ), 'M1 plain quoted long text has the same lossless contract as angle quotes' );
$m1_explicit_color = $run_services_route( $m1_cases['hero_stack'][0] . "\nАкцент: #123456. Фон: #ffffff.", [], [], 'm1-explicit-color', false, 'active', 'active', [ 'page_tokens_confirmed' => true, 'page_tokens' => [ 'color.primary' => '#654321' ] ] );
check( ! empty( $m1_explicit_color['response']['ok'] ) && str_contains( wp_json_encode( $m1_explicit_color['written'] ), '#123456' ) && $m1_explicit_color['written']['settings']['background_color'] === '#ffffff', 'M1 explicit visual values win confirmed page values through Plan tokens' );

$m1_final_failure = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-final-write-failure', false, 'active', 'active', [], [ 'code' => 'wpae_test_storage_failure', 'status' => 500, 'message' => 'storage failure' ] );
check( ! empty( $m1_final_failure['error'] ) && $m1_final_failure['write_attempts'] === 1 && $m1_final_failure['writes'] === 0 && $m1_final_failure['calls'] === 0 && $m1_final_failure['page_data'] === $legacy_page, 'M1 final transaction error attempts one write and cannot retry or fall back' );
foreach ( [ '{}', 'broken json', '' ] as $index => $raw ) {
 $GLOBALS['m1_raw_saved'] = $raw;
 $result = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'm1-missing-saved-' . $index );
 check( ! empty( $result['error'] ) && $result['write_attempts'] === 0 && $result['calls'] === 0 && $result['page_data'] === $legacy_page, 'M1 actual chat rejects unreadable saved data without treating render as an empty page' );
}
unset( $GLOBALS['m1_raw_saved'] );
if ( getenv( 'WPAE_M1_CHAT_DEMO' ) === '1' ) { foreach ( $m1_summary as $row ) { echo wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"; } }
