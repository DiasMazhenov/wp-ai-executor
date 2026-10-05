<?php
/** M3.1 ordinary production chat: external WordPress storage is the existing harness mock. */
$m3_results = [];
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
