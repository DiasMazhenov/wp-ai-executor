<?php
/** M3.1 ordinary production chat: external WordPress storage is the existing harness mock. */
$m3_results = [];
$m3_prior_classes = $GLOBALS['test_required_ds_classes'] ?? null;
$m3_prior_baseline = $GLOBALS['test_page_baseline'] ?? null;
$GLOBALS['test_page_baseline'] = [];
$GLOBALS['test_required_ds_classes'] = [ 'wpae-ds', 'wpae-system-test' ];
if ( ! function_exists( 'attachment_url_to_postid' ) ) { function attachment_url_to_postid( $url ): int { return (int) ( $GLOBALS['m3_attachment_ids_by_url'][ (string) $url ] ?? 0 ); } }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type( $post_id ): string { return isset( $GLOBALS['m3_attachment_ids_by_id'][ (int) $post_id ] ) ? 'attachment' : ''; } }
if ( ! function_exists( 'wp_attachment_is_image' ) ) { function wp_attachment_is_image( $post_id ): bool { return isset( $GLOBALS['m3_attachment_ids_by_id'][ (int) $post_id ] ); } }
if ( ! function_exists( 'wp_get_attachment_metadata' ) ) { function wp_get_attachment_metadata( $post_id ): array { return (array) ( $GLOBALS['m3_attachment_metadata'][ (int) $post_id ] ?? [] ); } }
if ( ! function_exists( 'wp_get_attachment_url' ) ) { function wp_get_attachment_url( $post_id ): string { return (string) ( $GLOBALS['m3_attachment_ids_by_id'][ (int) $post_id ] ?? '' ); } }
if ( ! function_exists( 'get_post_mime_type' ) ) { function get_post_mime_type( $post_id ): string { return isset( $GLOBALS['m3_attachment_ids_by_id'][ (int) $post_id ] ) ? 'image/webp' : ''; } }
$m3_services = $run_services_route( file_get_contents( dirname(__DIR__) . '/docs/audits/2026-10-05-m3-1-entities/E-services-exact-request.txt' ), [], $incompatible_pricing_fixture, 'm3-services-real-markers' );
check( ! empty( $m3_services['response']['ok'] ) && $m3_services['writes'] === 1 && $m3_services['calls'] === 0, 'M3 Services accepted compiler resolves real mandatory design-system markers before normalization/freeze' );
if ( $m3_prior_classes === null ) { unset( $GLOBALS['test_required_ds_classes'] ); } else { $GLOBALS['test_required_ds_classes'] = $m3_prior_classes; }
if ( $m3_prior_baseline === null ) { unset( $GLOBALS['test_page_baseline'] ); } else { $GLOBALS['test_page_baseline'] = $m3_prior_baseline; }
foreach ( [ 'team' => 'A-B-team-exact-request.txt', 'testimonials' => 'C-D-testimonials-exact-request.txt' ] as $family => $fixture ) {
 $prompt = file_get_contents( dirname(__DIR__) . '/docs/audits/2026-10-05-m3-1-entities/' . $fixture );
 foreach ( [ 'grid', 'editorial_rows' ] as $variant ) {
  $record = $family . '.' . $variant;
  $result = $run_services_route( $prompt, [], $incompatible_pricing_fixture, 'm3-' . $record, false, 'active', 'active', [ 'composition_record' => $record, 'composition_version' => 1, 'visual_profile' => 'editorial_light' ] );
  $trace = $result['response']['diagnostics']['design_pipeline'] ?? [];
  $ledger = $result['response']['diagnostics']['operation_ledger'] ?? [];
  if(empty($result['response']['ok'])) { file_put_contents('/private/tmp/wpae-m3-failed.json',wp_json_encode($result)); }
  check( !empty($result['response']['ok']), 'M3 actual ordinary entity route '.$record.' '.wp_json_encode($result['error']) );
  check( $result['calls']===0 && $result['writes']===1 && $result['write_attempts']===1, 'M3 single transaction and no provider/library bypass despite candidate' );
  check( $trace['brief']['hash']===$trace['content_plan']['brief_hash'] && $ledger['brief_hash']===$trace['brief']['hash'], 'M3 one canonical Brief hash across content/Plan/ledger' );
  check( $trace['plan']['composition_decision']['record_id']===$record && $trace['plan']['composition_decision']['visual_profile']==='editorial_light', 'M3 accepted record/profile preserved' );
  check( ($ledger['current_state']??'')==='written' && !empty($ledger['accepted_contract_id']) && !empty($ledger['accepted_contract_hash']), 'M3 frozen accepted contract attached to mocked written operation' );
  $m3_results[$record]=$result;
 }
}

// Exercise the avatar focal decision through the normal production plugin chat;
// only WordPress attachment/storage boundaries are mocked by this harness.
$m3_avatar_prompt = file_get_contents( dirname(__DIR__) . '/docs/audits/2026-10-08-media-portfolio-v299/fixtures/D-testimonials-grid.txt' );
$m3_avatar_urls = [
 'https://mazhenov.kz/wp-content/uploads/2023/09/man.jpg' => 'Иллюстративный стоковый портрет мужчины в кепке и наушниках; изображение не подтверждает достоверность цитаты.',
 'https://mazhenov.kz/wp-content/uploads/2023/09/girl.jpg' => 'Иллюстративный стоковый образ женщины в наушниках; изображение не подтверждает достоверность цитаты.',
 'https://mazhenov.kz/wp-content/uploads/2023/09/girl-yellow.webp' => 'Иллюстративный стоковый портрет женщины в жёлтом свитере; изображение не подтверждает достоверность цитаты.',
];
$m3_prior_home_url = $GLOBALS['test_home_url'] ?? null;
$m3_prior_attachment_ids_by_url = $GLOBALS['m3_attachment_ids_by_url'] ?? null;
$m3_prior_attachment_ids_by_id = $GLOBALS['m3_attachment_ids_by_id'] ?? null;
$m3_prior_attachment_metadata = $GLOBALS['m3_attachment_metadata'] ?? null;
$m3_prior_attachment_meta = $GLOBALS['test_attachment_meta'] ?? null;
$GLOBALS['test_home_url'] = 'https://mazhenov.kz';
$GLOBALS['m3_attachment_ids_by_url'] = array_combine( array_keys( $m3_avatar_urls ), [ 2648, 2649, 3593 ] );
$GLOBALS['m3_attachment_ids_by_id'] = array_flip( $GLOBALS['m3_attachment_ids_by_url'] );
$GLOBALS['m3_attachment_metadata'] = [
 2648 => [ 'width' => 300, 'height' => 300 ],
 2649 => [ 'width' => 320, 'height' => 400 ],
 3593 => [ 'width' => 238, 'height' => 400 ],
];
$GLOBALS['test_attachment_meta'] = [
 2648 => [ '_wp_attachment_image_alt' => 'Иллюстративный стоковый портрет мужчины в кепке и наушниках; изображение не подтверждает достоверность цитаты.' ],
 2649 => [ '_wp_attachment_image_alt' => 'Иллюстративный стоковый образ женщины в наушниках; изображение не подтверждает достоверность цитаты.' ],
 3593 => [ '_wp_attachment_image_alt' => 'Иллюстративный стоковый портрет женщины в жёлтом свитере; изображение не подтверждает достоверность цитаты.' ],
];
$m3_avatar_result = $run_services_route( $m3_avatar_prompt, [], $incompatible_pricing_fixture, 'm3-avatar-focal-chat-route', false, 'active', 'active', [ 'composition_record' => 'testimonials.grid', 'composition_version' => 1, 'visual_profile' => 'soft_cards_light' ] );
$m3_avatar_trace = (array) ( $m3_avatar_result['response']['diagnostics']['design_pipeline'] ?? [] );
$m3_avatar_images = [];
$m3_collect_avatar_images = static function ( array $nodes ) use ( &$m3_collect_avatar_images, &$m3_avatar_images ): void {
 foreach ( $nodes as $node ) {
  if ( ! is_array( $node ) ) { continue; }
  if ( ( $node['elType'] ?? '' ) === 'widget' && ( $node['widgetType'] ?? '' ) === 'image' ) { $m3_avatar_images[] = (array) ( $node['settings'] ?? [] ); }
  $m3_collect_avatar_images( (array) ( $node['elements'] ?? [] ) );
 }
};
$m3_collect_avatar_images( (array) ( $m3_avatar_result['written']['elements'] ?? [] ) );
$m3_avatar_native_order = array_map( static fn( array $image ): array => [ 'url' => (string) ( $image['image']['url'] ?? '' ), 'alt' => (string) ( $image['image']['alt'] ?? '' ), 'position' => (string) ( $image['object-position'] ?? '' ), 'fit' => (string) ( $image['object-fit'] ?? '' ) ], $m3_avatar_images );
check( ! empty( $m3_avatar_result['response']['ok'] ) && ( $m3_avatar_result['response']['diagnostics']['action_path'] ?? '' ) === 'pipeline' && $m3_avatar_result['provider_call_count'] === 0 && $m3_avatar_result['writes'] === 1 && ( $m3_avatar_trace['brief']['hash'] ?? '' ) !== '' && ( $m3_avatar_trace['plan']['brief_hash'] ?? '' ) === $m3_avatar_trace['brief']['hash'], 'Production chat route resolves exact avatar references to one Brief/Plan and one mocked transaction write' );
$m3_avatar_composition = (array) ( $m3_avatar_trace['plan']['composition_decision'] ?? [] );
$m3_avatar_item_slots = array_values( array_filter( (array) ( $m3_avatar_composition['slot_bindings'] ?? [] ), static fn( array $slot ): bool => ( $slot['role'] ?? '' ) === 'testimonial_cards' ) )[0]['items'] ?? [];
check( count( $m3_avatar_native_order ) === 3 && array_column( $m3_avatar_native_order, 'url' ) === array_keys( $m3_avatar_urls ) && array_column( $m3_avatar_native_order, 'fit' ) === [ 'cover', 'cover', 'cover' ] && array_column( $m3_avatar_native_order, 'position' ) === [ 'top center', 'top center', 'top center' ] && array_column( $m3_avatar_native_order, 'alt' ) === array_values( $m3_avatar_urls ) && array_column( $m3_avatar_item_slots, 'group_id' ) === [ 'testimonial_1', 'testimonial_2', 'testimonial_3' ] && array_column( $m3_avatar_item_slots, 'media_ref' ) === [ 'prompt_media_0', 'prompt_media_1', 'prompt_media_2' ] && ( $m3_avatar_composition['record_id'] ?? '' ) === 'testimonials.grid' && ( $m3_avatar_composition['visual_profile'] ?? '' ) === 'soft_cards_light' && ( $m3_avatar_composition['source'] ?? '' ) === 'explicit_record', 'Production chat preserves image/alt/entity ownership, the selected grid/profile and supported top-center native crop' );
foreach ( [ 'test_home_url' => $m3_prior_home_url, 'm3_attachment_ids_by_url' => $m3_prior_attachment_ids_by_url, 'm3_attachment_ids_by_id' => $m3_prior_attachment_ids_by_id, 'm3_attachment_metadata' => $m3_prior_attachment_metadata, 'test_attachment_meta' => $m3_prior_attachment_meta ] as $m3_global => $m3_value ) {
 if ( $m3_value === null ) { unset( $GLOBALS[ $m3_global ] ); } else { $GLOBALS[ $m3_global ] = $m3_value; }
}
