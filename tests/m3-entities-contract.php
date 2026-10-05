<?php
/** M3.1 ordinary production chat: external WordPress storage is the existing harness mock. */
$m3_results = [];
$m3_prior_classes = $GLOBALS['test_required_ds_classes'] ?? null;
$m3_prior_baseline = $GLOBALS['test_page_baseline'] ?? null;
$GLOBALS['test_page_baseline'] = [];
$GLOBALS['test_required_ds_classes'] = [ 'wpae-ds', 'wpae-system-test' ];
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
