<?php
/** M2.1 production chat + composer; only external WP/transport boundaries mocked. */
require_once __DIR__ . '/../includes/elementor/compose.php';
$m2_prompt = "Создай Hero с фото\n" . $m1_copy;
$m2_benefits = "Создай Benefits\n" . $m1_features;
$m2_cases = [];
foreach ( [ 'hero', 'about' ] as $family ) {
 foreach ( [ 'split_60_40', 'split_50_50', 'split_40_60' ] as $composition ) {
  foreach ( [ 'left', 'right' ] as $side ) {
   $id = $family . '.' . $composition . '.' . $side;
   $m2_cases[$id] = [ str_replace( 'Hero', ucfirst( $family ), $m2_prompt ), [ 'media_references' => [ array_replace( $m1_media, [ 'role' => $family ] ) ] ], $id, '' ];
  }
 }
}
$m2_cases['benefits.grid'] = [ $m2_benefits, [], 'benefits.grid', '' ];
$m2_cases['benefits.linear'] = [ $m2_benefits, [], 'benefits.linear', '' ];
$m2_cases['benefits.editorial_list'] = [ $m2_benefits, [], 'benefits.editorial_list', '' ];
foreach ( [ 'editorial_light', 'soft_cards_light' ] as $profile ) {
 $m2_cases['hero.' . $profile] = [ $m2_prompt, [ 'media_references' => [ $m1_media ] ], 'hero.split_60_40.right', $profile ];
 $m2_cases['benefits.' . $profile] = [ $m2_benefits, [], 'benefits.grid', $profile ];
 $m2_cases['benefits.list.' . $profile] = [ $m2_benefits, [], 'benefits.editorial_list', $profile ];
}
$m2_cases['hero.text_only'] = [ $m1_cases['hero_stack'][0], [], 'hero.text_only', '' ];
$m2_cases['pricing.tiers'] = [ $m1_pricing, [], 'pricing.tiers', '' ];
$m2_cases['faq.native'] = [ $m1_faq, [], 'faq.native', '' ];
$m2_results = $m2_demo = [];
$m2_project = static function ( array $nodes, string $mode ) use ( &$m2_project ): array {
 return array_map( static function ( array $node ) use ( &$m2_project, $mode ): array {
  $settings = $node['settings'] ?? [];
  if ( ( $node['widgetType'] ?? '' ) === 'accordion' ) { foreach ( (array) ( $settings['tabs'] ?? [] ) as $index => $tab ) { unset( $settings['tabs'][$index]['_id'] ); } }
  $content = array_intersect_key( $settings, array_flip( [ 'title', 'editor', 'text', 'link', 'image', 'tabs' ] ) );
  $visual = array_diff_key( $settings, $content, array_flip( [ '_css_classes' ] ) );
  $item = $mode === 'content' ? $content : ( $mode === 'visual' ? $visual : [ 'type' => $node['widgetType'] ?? $node['elType'] ] );
  $children = $m2_project( (array) ( $node['elements'] ?? [] ), $mode );
  if ( $mode === 'content' ) { return array_merge( $content ? [ $content ] : [], ...$children ); }
  return [ 'node' => $item, 'children' => $children ];
 }, $nodes );
};
$m2_account = static function ( array $nodes ) use ( $m2_project ): array {
 $flat = array_merge( ...$m2_project( $nodes, 'content' ) );
 // Order of media relative to copy is deliberately variable; copy order is separate.
 $images = array_values( array_filter( $flat, static fn( $item ): bool => isset( $item['image'] ) ) );
 $copy = array_values( array_filter( $flat, static fn( $item ): bool => ! isset( $item['image'] ) ) );
 return [ 'copy_in_order' => $copy, 'media' => $images ];
};
$m2_native_controls = static function ( array $nodes ) use ( &$m2_native_controls ): array {
 $found = [];
 foreach ( $nodes as $node ) {
  $settings = $node['settings'] ?? [];
  if ( ( $node['widgetType'] ?? '' ) === 'heading' && in_array( $settings['header_size'] ?? '', [ 'h1', 'h3' ], true ) ) { $found['heading'] = array_intersect_key( $settings, array_flip( [ 'typography_font_size', 'typography_font_size_tablet', 'typography_font_size_mobile', 'typography_font_weight', 'typography_line_height', 'typography_line_height_mobile' ] ) ); }
  if ( isset( $settings['boxed_width'] ) ) { $found['copy_width'] = $settings['boxed_width']; }
  if ( isset( $settings['border_radius'] ) ) { $found['surface_radius'] = $settings['border_radius']; }
  if ( isset( $settings['padding'] ) && ! isset( $found['section'] ) ) { $found['section'] = array_intersect_key( $settings, array_flip( [ 'padding', 'padding_mobile', 'flex_gap', 'flex_direction_mobile', 'background_color' ] ) ); }
  $found += $m2_native_controls( (array) ( $node['elements'] ?? [] ) );
 }
 return $found;
};
$m2_preview = static function ( array $brief, array $context, string $instance ): array {
 $request = new WP_REST_Request(); $request->set_param( 'canonical_brief', $brief );
 foreach ( $context as $key => $value ) { $request->set_param( $key, $value ); }
 $request->set_param( 'instance_id', $instance );
 return wpae_elementor_compose( $request )->get_data();
};
foreach ( $m2_cases as $name => [ $prompt, $context, $record, $profile ] ) {
 $brief = wpae_brief_ir_parse( $prompt ); $brief['canonical_create'] = true;
 if ( ! empty( $context['media_references'] ) ) {
  $brief['media_references'] = array_map( 'wpae_reference_set_normalize', $context['media_references'] );
  foreach ( $brief['media_references'] as &$media ) { $media['group_id'] = $brief['intent']['archetype']; } unset( $media );
  $brief['groups'][0]['media_refs'] = array_column( $brief['media_references'], 'asset_id' );
 }
 $ctx = [ 'composition_record' => $record, 'composition_version' => 1, 'visual_profile' => $profile ];
 $result = $run_services_route( $prompt, [], $incompatible_pricing_fixture, 'm2-' . $name, false, 'active', 'active', array_merge( $context, $ctx, [ 'canonical_brief' => $brief ] ) );
 check( ! empty( $result['response']['ok'] ), 'M2 chat ' . $name . ': ' . wp_json_encode( $result['error'] ) );
 $trace = $result['response']['diagnostics']['design_pipeline'];
 $decision = $trace['plan']['composition_decision'];
 check( $result['writes'] === 1 && $result['write_attempts'] === 1 && $result['calls'] === 0 && $result['library_retrieval_count'] === 0 && ! $trace['library_retrieval']['called'], 'M2 ' . $name . ' one transaction, no provider/retrieval/seed' );
 check( array_slice( $result['page_data'], 0, count( $legacy_page ) ) === $legacy_page && count( $result['page_data'] ) === count( $legacy_page ) + 1 && $trace['frozen_decisions']['readback_matches'], 'M2 ' . $name . ' only selected owned root and exact neighbors/readback' );
 check( $decision['record_id'] === $record && $decision['record_version'] === 1 && $decision['record_hash'] === wpae_composition_records()[$record]['hash'], 'M2 ' . $name . ' accepted record identity/version/hash' );
 $before_page = $GLOBALS['page_data']; $before_writes = $GLOBALS['writes'];
 $preview = $m2_preview( $brief, $ctx, 'm2-preview-one' );
 check( ! empty( $preview['ok'] ), 'M2 composer ' . $name . ': ' . wp_json_encode( $preview['errors'] ?? [] ) );
 check( $preview['write_count'] === 0 && $GLOBALS['page_data'] === $before_page && $GLOBALS['writes'] === $before_writes && wpae_llm_decision_signature( $preview['elementor_data'] ) === wpae_llm_decision_signature( [ $result['written'] ] ), 'M2 composer ' . $name . ' same native decisions as chat, zero writes' );
 $other = $m2_preview( $brief, $ctx, 'm2-preview-two' );
 check( $preview['elementor_data'][0]['id'] !== $other['elementor_data'][0]['id'] && wpae_llm_decision_signature( $preview['elementor_data'] ) === wpae_llm_decision_signature( $other['elementor_data'] ), 'M2 explicit instance ID changes only IDs ' . $name );
 $hash = static fn( $data ): string => hash( 'sha256', wp_json_encode( $data ) );
 $m2_demo[] = [ 'fixture' => $name, 'request' => $prompt, 'brief_hash' => $trace['brief']['hash'], 'record' => $record, 'version' => 1, 'record_hash' => $decision['record_hash'], 'family' => $trace['plan']['archetype'], 'variant_kind' => wpae_composition_records()[$record]['variant_kind'], 'visual_profile' => $profile, 'route' => 'pipeline', 'accounting_signature' => $hash( $m2_account( [ $result['written'] ] ) ), 'structural_signature' => $hash( $m2_project( [ $result['written'] ], 'structure' ) ), 'layout_visual_signature' => $hash( $m2_project( [ $result['written'] ], 'visual' ) ), 'ids' => [ $result['written']['id'] ], 'assets' => $brief['media_references'], 'native_controls' => $profile !== '' ? $m2_native_controls( [ $result['written'] ] ) : [], 'provider_calls' => 0, 'retrieval_calls' => 0, 'attempted_writes' => 1, 'successful_writes' => 1, 'composer_write_count' => 0, 'readback' => true, 'render_status' => 'NOT_RUN' ];
 $m2_results[$name] = [ 'result' => $result, 'preview' => $preview, 'brief' => $brief, 'context' => $ctx ];
}
foreach ( [ 'hero', 'about' ] as $family ) {
 $left = $m2_results[$family . '.split_60_40.left']; $right = $m2_results[$family . '.split_60_40.right'];
 check( $left['preview']['diagnostics']['brief_hash'] === $right['preview']['diagnostics']['brief_hash'] && $m2_account( $left['preview']['elementor_data'] ) === $m2_account( $right['preview']['elementor_data'] ), 'M2 ' . $family . ' left/right use one identical Brief/copy/order/CTA/media' );
 check( $left['result']['written']['elements'][0]['elements'][0]['widgetType'] === 'image' && $right['result']['written']['elements'][1]['elements'][0]['widgetType'] === 'image' && $left['result']['written']['settings']['flex_direction_mobile'] === 'column-reverse' && $right['result']['written']['settings']['flex_direction_mobile'] === 'column', 'M2 ' . $family . ' media side and copy-first mobile native policy differ' );
 check( $m2_project( $left['preview']['elementor_data'], 'structure' ) !== $m2_project( $right['preview']['elementor_data'], 'structure' ), 'M2 semantic node order differs independently of IDs' );
 check( $m2_results[$family . '.split_60_40.right']['result']['written']['elements'][0]['settings']['width'] !== $m2_results[$family . '.split_40_60.right']['result']['written']['elements'][0]['settings']['width'], 'M2 ratios differ in native width controls' );
}
$grid = $m2_results['benefits.grid']['preview']; $list = $m2_results['benefits.editorial_list']['preview'];
check( $grid['diagnostics']['brief_hash'] === $list['diagnostics']['brief_hash'] && $m2_account( $grid['elementor_data'] ) === $m2_account( $list['elementor_data'] ) && $m2_project( $grid['elementor_data'], 'structure' ) !== $m2_project( $list['elementor_data'], 'structure' ), 'M2 Benefits one Brief preserves exact ordered groups without CTA/media and changes native topology' );
foreach ( [ 'hero', 'benefits' ] as $family ) {
 $editorial = $m2_results[$family . '.editorial_light']['preview']; $soft = $m2_results[$family . '.soft_cards_light']['preview'];
 check( $editorial['diagnostics']['brief_hash'] === $soft['diagnostics']['brief_hash'] && $m2_account( $editorial['elementor_data'] ) === $m2_account( $soft['elementor_data'] ) && $m2_project( $editorial['elementor_data'], 'structure' ) === $m2_project( $soft['elementor_data'], 'structure' ) && $m2_project( $editorial['elementor_data'], 'visual' ) !== $m2_project( $soft['elementor_data'], 'visual' ), 'M2 two profiles retain content/layout topology and change actual native settings ' . $family );
 check( $editorial['elementor_data'][0]['settings']['padding_mobile']['top'] === '2.5' && $soft['elementor_data'][0]['settings']['padding_mobile']['top'] === '2', 'M2 profiles compile different mobile section spacing ' . $family );
}
$base = $m2_results['hero.split_60_40.right'];
$m2_negatives = [
 'unknown_record' => [ 'composition_record' => 'hero.missing' ],
 'unknown_version' => [ 'composition_version' => 2 ],
 'cross_family' => [ 'composition_record' => 'benefits.grid' ],
 'wrong_cardinality' => [ 'composition_record' => 'hero.text_only' ],
 'unknown_profile' => [ 'visual_profile' => 'dark_unknown' ],
 'bad_type_fields' => [ 'visual_profile' => 'editorial_light', 'page_tokens_confirmed' => true, 'page_tokens' => [ 'type.display' => [ 'desktop' => [], 'weight' => [], 'line_height' => '0' ] ] ],
 'invalid_visual' => [ 'visual_profile' => 'editorial_light', 'page_tokens_confirmed' => true, 'page_tokens' => [ 'space.section' => '-12px' ] ],
 'bad_surface_contrast' => [ 'visual_profile' => 'editorial_light', 'page_tokens_confirmed' => true, 'page_tokens' => [ 'color.surface' => '#111827' ] ],
 'bad_contrast' => [ 'visual_profile' => 'soft_cards_light', 'page_tokens_confirmed' => true, 'page_tokens' => [ 'color.text' => '#ffffff' ] ],
];
foreach ( $m2_negatives as $name => $override ) {
 $ctx = array_merge( $base['context'], $override );
 $result = $run_services_route( $m2_prompt, [], [], 'm2-refuse-' . $name, false, 'active', 'active', array_merge( $ctx, [ 'canonical_brief' => $base['brief'] ] ) );
 check( ! empty( $result['error'] ) && $result['writes'] === 0 && $result['write_attempts'] === 0 && $result['calls'] === 0 && $result['page_data'] === $legacy_page, 'M2 chat refuses ' . $name . ' before write/provider/fallback' );
 $preview = $m2_preview( $base['brief'], $ctx, 'negative' );
 check( empty( $preview['ok'] ) && $preview['write_count'] === 0, 'M2 composer refuses ' . $name . ' with zero writes' );
}
$bad = $base['brief']; $bad['layout_constraints'][] = [ 'kind' => 'media_side', 'value' => 'left' ];
$conflict = $run_services_route( $m2_prompt, [], [], 'm2-explicit-conflict', false, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $bad ] ) );
check( ! empty( $conflict['error'] ) && $conflict['writes'] === 0 && $conflict['calls'] === 0, 'M2 explicit Brief side conflict refuses instead of overriding copy/media policy' );
$bad = $base['brief']; $bad['media_references'] = []; $bad['groups'][0]['media_refs'] = [];
$unresolved = $run_services_route( $m2_prompt, [], [], 'm2-unresolved', false, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $bad ] ) );
check( ! empty( $unresolved['error'] ) && $unresolved['writes'] === 0 && $unresolved['calls'] === 0, 'M2 required media cannot become text-only' );
$types = \Elementor\Plugin::$types; \Elementor\Plugin::$types = array_values( array_diff( $types, [ 'image' ] ) );
$missing = $run_services_route( $m2_prompt, [], [], 'm2-missing-image', false, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $base['brief'] ] ) );
$missing_preview = $m2_preview( $base['brief'], $base['context'], 'missing' );
\Elementor\Plugin::$types = $types;
check( ! empty( $missing['error'] ) && $missing['writes'] === 0 && empty( $missing_preview['ok'] ), 'M2 missing native image capability refuses chat and composer' );
$accepted = wpae_design_plan_from_brief( $base['brief'], array_merge( $base['context'], [ 'canonical_create' => true ] ) );
$accepted['composition_decision']['policy']['media_side'] = 'left';
check( in_array( 'composition_policy_mutated', wpae_design_plan_validate( $accepted, $base['brief'] )['errors'], true ), 'M2 accepted record policy cannot be substituted before compile' );
foreach ( [ true, false ] as $preview_failure ) {
 $result = $run_services_route( $m2_prompt, [], [], 'm2-failure-' . (int) $preview_failure, $preview_failure, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $base['brief'] ] ), $preview_failure ? null : [ 'code' => 'wpae_test_storage_failure', 'status' => 500, 'message' => 'storage failure' ] );
 check( ! empty( $result['error'] ) && $result['writes'] === 0 && $result['calls'] === 0 && $result['page_data'] === $legacy_page && $result['write_attempts'] === ( $preview_failure ? 0 : 1 ), 'M2 preview/final-write failure no retry or fallback' );
}
$GLOBALS['m1_raw_saved'] = 'invalid JSON';
$unreadable = $run_services_route( $m2_prompt, [], [], 'm2-unreadable', false, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $base['brief'] ] ) );
unset( $GLOBALS['m1_raw_saved'] );
check( ! empty( $unreadable['error'] ) && $unreadable['write_attempts'] === 0 && $unreadable['calls'] === 0, 'M2 saved read failure cannot initialize an empty page' );
// Explicit tokens override profile, reference overrides confirmed page, unconfirmed context ignored.
$visual_brief = $base['brief']; $visual_brief['layout_constraints'][] = [ 'kind' => 'visual_token', 'token' => 'color.primary', 'value' => '#123456' ];
$profile_ctx = array_merge( $base['context'], [ 'canonical_create' => true, 'visual_profile' => 'editorial_light', 'page_tokens_confirmed' => true, 'page_tokens' => [ 'color.primary' => '#654321', 'radius.card' => '2rem' ], 'reference_tokens_confirmed' => true, 'reference_tokens' => [ 'radius.card' => '3rem' ] ] );
$visual_plan = wpae_design_plan_from_brief( $visual_brief, $profile_ctx );
check( $visual_plan['resolved_visual']['values']['color.primary'] === '#123456' && $visual_plan['resolved_visual']['sources']['color.primary'] === 'explicit_brief' && $visual_plan['resolved_visual']['values']['radius.card'] === '3rem' && $visual_plan['resolved_visual']['sources']['radius.card'] === 'confirmed_reference' && $visual_plan['resolved_visual']['sources']['space.section'] === 'visual_profile:editorial_light', 'M2 explicit > confirmed reference > confirmed page > selected profile provenance' );
$legacy_definitions = wpae_elementor_recipe_definitions();
check( count( $legacy_definitions ) === 8, 'M2 all eight legacy recipes audited' );
foreach ( $legacy_definitions as $id => $recipe ) {
 $default = null;
 foreach ( $recipe['variants'] as $variant ) {
  $request = new WP_REST_Request(); $request->set_param( 'recipe_id', $id ); $request->set_param( 'variant', $variant ); $request->set_param( 'instance_id', 'same-instance' );
  $slots = []; foreach ( $recipe['slots'] as $key => $spec ) { $slots[$key] = $spec['default'] ?? 'Fixture'; if ( $slots[$key] === '' ) { $slots[$key] = 'Fixture'; } } $request->set_param( 'slots', $slots );
  $preview = wpae_elementor_compose( $request )->get_data();
  check( ! empty( $preview['ok'] ) && $preview['instance_id'] === 'same-instance' && $preview['write_count'] === 0, 'M2 legacy recipe compose fields/instance ' . $id . ':' . $variant );
  $default = $default ?? $preview['elementor_data'];
  check( $default === $preview['elementor_data'] && $preview['effective_variant'] === $recipe['default_variant'] && $preview['requested_variant'] === $variant && $preview['distinct'] === ( $variant === $recipe['default_variant'] ) && $preview['variant_kind'] === ( $variant === $recipe['default_variant'] ? 'default' : 'legacy_alias' ), 'M2 identical legacy tree honestly labelled ' . $id . ':' . $variant );
 }
 check( wpae_elementor_recipe_summary( $recipe )['alternatives'] === [], 'M2 legacy aliases excluded from real alternatives ' . $id );
}
// Pricing/FAQ M1 and Services verified-map regression controls still use their original path.
check( $services_library_only_success['writes'] === 1 && $services_library_only_success['calls'] === 0 && $services_library_only_success['library_retrieval_count'] === 1, 'M2 explicit verified Services library map still retrieves and writes once' );
check( $services_photo_route['writes'] === 1 && $services_split_route['writes'] === 1 && $services_text_icon_route['writes'] === 1, 'M2 all Services typed recipes remain regression controls' );

$before = $GLOBALS['page_data']; $writes_before = $GLOBALS['writes'];
$native = $base['preview']['elementor_data']; $accepted_signature = wpae_llm_decision_signature( $native );
$native[0]['settings']['flex_direction_mobile'] = 'column-reverse';
$changed_policy = wpae_llm_execute_action( [ 'action' => 'insert_elements', 'post_id' => 42, 'elements' => $native ], 42, 'hero', -1, $m2_prompt, true, [ 'frozen_decisions' => true, 'accepted_signature' => $accepted_signature, 'deterministic_ids' => true ] );
check( empty( $changed_policy['ok'] ) && $changed_policy['error'] === 'frozen_accepted_policy_mismatch' && $GLOBALS['page_data'] === $before && $GLOBALS['writes'] === $writes_before, 'M2 execute rejects altered accepted mobile policy after freeze, before write' );
$faq_native = $m2_results['faq.native']['preview']['elementor_data']; $signature = wpae_llm_decision_signature( $faq_native );
check( ( $faq_native[0]['elements'][0]['elements'][0]['widgetType'] ?? '' ) === 'accordion', 'M2 FAQ signature regression targets real Accordion' );
$faq_native[0]['elements'][0]['elements'][0]['settings']['tabs'][0]['tab_content'] = 'Foreign answer';
check( $signature !== wpae_llm_decision_signature( $faq_native ), 'M2 dropping Accordion repeater IDs never drops exact FAQ answer fidelity' );

foreach ( [ 'hero.text_only' => 'hero_stack', 'hero.split_60_40.right' => 'hero_split', 'about.split_50_50.right' => 'about_split', 'benefits.grid' => 'benefits_grid', 'benefits.editorial_list' => 'benefits_list', 'pricing.tiers' => 'pricing', 'faq.native' => 'faq' ] as $m2_case => $m1_case ) {
 check( wpae_llm_decision_signature( [ $m2_results[$m2_case]['result']['written'] ] ) === wpae_llm_decision_signature( [ $m1_results[$m1_case]['written'] ] ), 'M2 no visual profile preserves M1 native behavior ' . $m2_case );
}
foreach ( [ 'ratio_conflict', 'multiple_assets', 'group_cardinality' ] as $reason ) {
 $bad = $base['brief'];
 if ( $reason === 'ratio_conflict' ) { $bad['layout_constraints'][] = [ 'kind' => 'composition', 'value' => 'split_50_50' ]; }
 if ( $reason === 'multiple_assets' ) { $bad['media_references'][] = array_replace( $bad['media_references'][0], [ 'asset_id' => 'second-asset', 'source_url' => 'https://example.com/second-approved.jpg' ] ); $bad['groups'][0]['media_refs'][] = 'second-asset'; }
 if ( $reason === 'group_cardinality' ) { $bad['groups'] = []; }
 $resolution = wpae_composition_resolve( $bad, $base['context'], 'split_60_40', 'right' );
 check( ! empty( $resolution['errors'] ), 'M2 resolver explains ' . $reason . ' before Plan freeze' );
 $result = $run_services_route( $m2_prompt, [], [], 'm2-' . $reason, false, 'active', 'active', array_merge( $base['context'], [ 'canonical_brief' => $bad ] ) );
 check( ! empty( $result['error'] ) && $result['writes'] === 0 && $result['calls'] === 0 && $result['write_attempts'] === 0, 'M2 actual chat refuses ' . $reason . ' without losing fields/assets' );
}
$extra_visual = $base['brief']; $extra_visual['layout_constraints'][] = [ 'kind' => 'visual_token', 'token' => 'space.section_mobile', 'value' => '3rem' ];
$extra_plan = wpae_design_plan_from_brief( $extra_visual, array_merge( $base['context'], [ 'canonical_create' => true, 'visual_profile' => 'editorial_light', 'page_tokens' => [ 'space.section_mobile' => '9rem' ] ] ) );
check( $extra_plan['resolved_visual']['values']['space.section_mobile'] === '3rem' && $extra_plan['resolved_visual']['sources']['space.section_mobile'] === 'explicit_brief', 'M2 explicit Brief also overrides new profile tokens; unconfirmed page ignored' );
$request = new WP_REST_Request(); $request->set_param( 'composition_record', 'hero.text_only' ); $request->set_param( 'canonical_brief', 'Create Hero' );
$invalid_preview = wpae_elementor_compose( $request )->get_data();
check( empty( $invalid_preview['ok'] ) && $invalid_preview['write_count'] === 0 && $invalid_preview['errors'] === [ 'canonical_brief_and_record_required' ], 'M2 composer refuses raw text instead of starting another parser' );
check( wpae_llm_decision_signature( $m2_results['benefits.linear']['preview']['elementor_data'] ) === wpae_llm_decision_signature( $m2_results['benefits.grid']['preview']['elementor_data'] ) && ! wpae_composition_records()['benefits.linear']['distinct'], 'M2 typed linear Benefits is an honest alias of grid, no fake alternative' );
$request = new WP_REST_Request(); $request->set_param( 'recipe_id', 'hero.editorial' ); $request->set_param( 'variant', 'metric-led' ); $request->set_param( 'slots', [ 'metric_1' => 'Exact metric 17', 'metric_1_label' => 'Exact proof', 'headline' => 'Offer', 'subheadline' => 'Description', 'cta_primary' => 'Start' ] );
$legacy_metrics = wpae_elementor_compose( $request )->get_data();
check( ! empty( $legacy_metrics['ok'] ) && str_contains( wp_json_encode( $legacy_metrics['elementor_data'] ), 'Exact metric 17' ) && str_contains( wp_json_encode( $legacy_metrics['elementor_data'] ), 'Exact proof' ), 'M2 old hero.editorial alias retains metric/proof slots without migrated Hero adaptation' );
$request->set_param( 'canonical_brief', $base['brief'] ); $request->set_param( 'composition_record', 'hero.split_60_40.right' );
$mixed = wpae_elementor_compose( $request )->get_data();
check( empty( $mixed['ok'] ) && $mixed['write_count'] === 0 && $mixed['errors'] === [ 'mixed_legacy_typed_contract' ], 'M2 mixed legacy/typed payload refuses rather than discarding metrics/proof slots' );
$library_brief = $base['brief']; $library_brief['policy']['library']['source'] = 'required';
$library_preview = $m2_preview( $library_brief, $base['context'], 'library-only' );
check( empty( $library_preview['ok'] ) && $library_preview['write_count'] === 0 && in_array( 'composition_library_slot_map_unavailable', $library_preview['errors'], true ), 'M2 composer honors library-only canonical Brief and refuses unverified typed slot map' );
if ( getenv( 'WPAE_M2_CHAT_DEMO' ) === '1' ) { foreach ( $m2_demo as $row ) { echo wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"; } }

// Editor integration: the static report must consume accepted responsive tokens.
foreach ( [ 'editorial_light', 'soft_cards_light' ] as $profile ) {
 $case = $m2_results['hero.' . $profile];
 $plan = wpae_design_plan_from_brief( $case['brief'], array_merge( [ 'canonical_create' => true ], $case['context'] ) );
 $report = wpae_layout_report_for_plan( $plan, [ 'tokens' => [ 'space.component' => '99rem' ] ] );
 check( $report['visual_render_verified'] === false && $report['evidence'] === 'static_plan', 'Editor profile report stays static, accepted Plan values take precedence over unrelated options' );
 $native = $case['preview']['elementor_data'][0];
 foreach ( $report['breakpoints'] as $bp ) {
  $device = $bp['device_assumption']; $suffix = $device === 'desktop' ? '' : '_' . $device;
  $control = $native['settings']['flex_gap' . $suffix];
  $gap_px = wpae_layout_report_length_px( $control['size'] . $control['unit'], $bp['viewport_width'], -1 );
  check( abs( $bp['gaps']['size'] - $gap_px ) < 0.01, 'Editor static gap matches native ' . $profile . ' ' . $bp['breakpoint'] );
  $copy = $native['elements'][0]['settings'];
  $boxed = $copy['boxed_width' . $suffix];
  $expected = $device === 'mobile' ? $bp['basis']['copy_group'] : min( $bp['basis']['copy_group'], wpae_layout_report_length_px( $boxed['size'] . $boxed['unit'], $bp['viewport_width'], -1 ) );
  check( abs( $bp['boxed_copy_content_width_px']['copy_group'] - $expected ) < 0.01, 'Editor static boxed copy clamp matches native ' . $profile . ' ' . $bp['breakpoint'] );
 }
}
$editor_catalog = wpae_composition_editor_catalog();
check( count( $editor_catalog['records'] ) === 17 && ! in_array( 'benefits.linear', array_column( $editor_catalog['records'], 'id' ), true ), 'Editor projection has only distinct implemented records, no alias' );

// Real design-system marker contract, absent from the historical harness stub.
$GLOBALS['test_required_ds_classes'] = [ 'wpae-ds', 'wpae-system-test' ];
$GLOBALS['test_page_baseline'] = []; // Matches the confirmed empty saved live target.
$marker_case = $run_services_route( $m1_cases['hero_stack'][0], [], [], 'editor-real-ds-marker', false, 'active', 'active', [ 'composition_record' => 'hero.text_only', 'composition_version' => 1, 'visual_profile' => 'editorial_light' ] );
check( ! empty( $marker_case['response']['ok'] ) && $marker_case['writes'] === 1, 'Editor real wpae-ds marker contract survives frozen normalize/write/readback: ' . wp_json_encode( $marker_case['error'] ) );
$marker_plan = wpae_design_plan_from_brief( $m2_results['hero.text_only']['brief'], [ 'canonical_create' => true, 'composition_record' => 'hero.text_only', 'visual_profile' => 'editorial_light' ] );
$marker_ir = wpae_elementor_ir_from_design_plan( $marker_plan, $m2_results['hero.text_only']['brief'] );
$marker_compile = wpae_native_elementor_compile( $marker_ir, $m2_results['hero.text_only']['brief'], [], [ 'resolved_visual' => $marker_plan['resolved_visual'] ] );
check( str_contains( $marker_compile['elementor_data'][0]['settings']['_css_classes'] ?? '', 'wpae-ds' ) && wpae_llm_decision_signature( $marker_compile['elementor_data'] ) === wpae_llm_decision_signature( wpae_elementor_normalize_data( $marker_compile['elementor_data'] )['data'] ), 'Editor compiler emits technical root markers before freeze; normalizer does not add semantic class later' );
$marker_mutation = $marker_compile['elementor_data']; $marker_mutation[0]['settings']['_css_classes'] .= ' author-layout-change';
check( wpae_llm_decision_signature( $marker_mutation ) !== wpae_llm_decision_signature( $marker_compile['elementor_data'] ), 'Editor class decisions stay guarded, signature exclusions not broadened' );
unset( $GLOBALS['test_required_ds_classes'], $GLOBALS['test_page_baseline'] );
