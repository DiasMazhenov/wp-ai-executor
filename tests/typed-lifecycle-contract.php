<?php
/** Existing real chat/compiler/ledger with external WP/transaction mocks only. */
$typed_saved_globals = [ 'options' => $GLOBALS['options'], 'page_data' => $GLOBALS['page_data'] ];
foreach ( [ 'ordinary'=>[], 'selected'=>['selected_elements'=>[['id'=>'selected']]], 'retry'=>['retry_current_operation'=>true] ] as $path=>$options ) {
    $native_before=$GLOBALS['test_page_baseline']??$legacy_page;
    $native_stale=$native_before;
    $native_stale[]=container_node('imported-html-neighbor',[],[]);
    $refusal=$run_services_route($m1_cases['hero_stack'][0],[],[],'document-guard-'.$path,false,'active','active',array_merge($options,['editor_document_model'=>$native_stale]));
    check(($refusal['error']['code']??'')==='wpae_editor_document_conflict' && $refusal['calls']===0 && $refusal['write_attempts']===0 && $refusal['page_data']===$native_before,'Whole document guard refuses stale bootstrap before provider/write on '.$path);
    $native_edit=$native_before; $native_edit[0]['settings']['unsaved_user_text']='preserve me';
    $refusal=$run_services_route($m1_cases['hero_stack'][0],[],[],'document-edit-'.$path,false,'active','active',array_merge($options,['editor_document_model'=>$native_edit]));
    check(($refusal['error']['code']??'')==='wpae_editor_document_conflict' && $refusal['write_attempts']===0 && $refusal['page_data']===$native_before,'Unsaved neighbor refuses create without mutation on '.$path);
}
foreach ( [ 'hero.text_only' => 'hero_stack', 'benefits.grid' => 'benefits_grid', 'pricing.tiers' => 'pricing', 'faq.native' => 'faq' ] as $record => $fixture ) {
    $profile = in_array( $record, [ 'hero.text_only', 'benefits.grid' ], true ) ? 'editorial_light' : '';
    $case = $run_services_route( $m1_cases[$fixture][0], [], [], 'typed-' . $fixture, false, 'active', 'active', [ 'composition_record' => $record, 'composition_version' => 1, 'visual_profile' => $profile ] );
    check( ! empty( $case['response']['ok'] ), 'Typed lifecycle real create ' . $record . ' ' . wp_json_encode( $case['error'] ) );
    $GLOBALS['options'] = $case['lifecycle_options']; $GLOBALS['page_data'] = $case['page_data'];
    $operation = $case['response']['diagnostics']['operation_ledger'];
    $loaded = wpae_accepted_contract_get( $operation );
    check( ! empty( $loaded['ok'] ) && $loaded['contract']['composition']['record_id'] === $record && $loaded['contract']['profile'] === $profile, 'Typed server contract reload record/profile ' . $record );
    check( count( $loaded['contract']['after_owned'] ) === 1 && $loaded['contract']['before_owned'] === [] && wpae_brief_ir_validate( $loaded['contract']['brief'] )['ok'], 'Bounded contract only owned subtree and canonical provenance ' . $record );
    $owned = $loaded['contract']['after_owned'];
    $context = [ 'post_id' => 42, 'accepted_operation_id' => $operation['operation_id'], 'accepted_identity' => $operation['operation_identity'], 'accepted_revision' => $operation['revision'], 'lifecycle_action' => 'check_model', 'editor_owned_model' => $owned ];
    $describe=$context; $describe['lifecycle_action']='describe_operation'; $describe['accepted_revision']=0;
    $writes_before=$GLOBALS['m1_write_attempts'];
    check(wpae_accepted_lifecycle_request($describe)->get_data()['operation']['revision']===$operation['revision'] && $GLOBALS['m1_write_attempts']===$writes_before,'Read-only descriptor recovers current revision without document write '.$record);
    $describe['accepted_identity']='different operation';
    check(is_wp_error(wpae_accepted_lifecycle_request($describe)),'Descriptor refuses foreign identity '.$record);
    $check_model = wpae_accepted_lifecycle_request( $context );
    check( $check_model instanceof WP_REST_Response && $check_model->get_data()['ok'], 'Server/editor decision equality ' . $record );
    if ($record==='hero.text_only') {
        $native=$owned; $child_id=$native[0]['elements'][0]['elements'][0]['id'];
        check($native[0]['elements'][0]['elements'][0]['settings']['content_width']==='full','Hero reading container authors full native width on the selected axis');
        $GLOBALS['typed_native_defaults'][$child_id]=['content_width'=>'full'];
        unset($native[0]['elements'][0]['elements'][0]['settings']['content_width']);
        $context['editor_owned_model']=$native;
        check(wpae_accepted_lifecycle_request($context)->get_status()===200,'Sparse registered full-width default is not replaced by generation full width');
        $native_doc=new class {function get_main_id(){return 42;}};
        $native_document = array_map(static fn($root)=>($root['id']??'')===($native[0]['id']??'')?$native[0]:$root, $GLOBALS['page_data']);
        check(wpae_accepted_elementor_save_guard(['elements'=>$native_document],$native_doc)['elements']===$native_document,'Save validates whole sparse native document without synthesized design values');
        $unexpected_document = array_merge($native_document, [container_node('unexpected-neighbor', [], [])]);
        $unexpected_blocked = false;
        try { wpae_accepted_elementor_save_guard(['elements'=>$unexpected_document],$native_doc); } catch(RuntimeException $e) { $unexpected_blocked=true; }
        check($unexpected_blocked, 'Native Save refuses an unexpected neighbor despite exact owned subtree');
        $context['lifecycle_action']='resync';
        check(wpae_accepted_lifecycle_request($context)->get_data()['ok'],'Resync accepts exact native model with omitted registered default');
        $context['lifecycle_action']='check_model';
        $native[0]['elements'][0]['elements'][0]['settings']['content_width']='boxed';$context['editor_owned_model']=$native;
        check(wpae_accepted_lifecycle_request($context)->get_status()===409,'Explicit changed boxed width still refuses after sparse-model fix');
        $GLOBALS['typed_native_defaults']=[];
    }
    $document_context=$context; $document_context['lifecycle_action']='check_document_model';$document_context['editor_document_model']=$GLOBALS['page_data'];
    $typed_document_write_count=(int)$GLOBALS['m1_write_attempts'];
    check(wpae_accepted_lifecycle_request($document_context)->get_data()['ok'],'Fresh whole native document check before owned Undo ' . $record);
    $document_context['editor_document_model'][0]['settings']['foreign_user_edit']='new';
    check(wpae_accepted_lifecycle_request($document_context)->get_status()===409 && (int)$GLOBALS['m1_write_attempts']===$typed_document_write_count,'Unsaved foreign root refused before Undo without write ' . $record);
    $stale = $owned; $stale[0]['settings']['padding']['top'] = '99';
    $context['editor_owned_model'] = $stale;
    check( wpae_accepted_lifecycle_request( $context )->get_status() === 409, 'Editor/save mismatch caught ' . $record );
    $document = new class { public function get_main_id(){return 42;} };
    $blocked = false;
    try { wpae_accepted_elementor_save_guard( [ 'elements' => $stale ], $document ); } catch ( RuntimeException $e ) { $blocked = true; }
    check( $blocked, 'Actual Elementor pre-save guard refuses stale owned tree ' . $record );
    check( wpae_accepted_elementor_save_guard( [ 'elements' => $case['page_data'] ], $document )['elements'] === $case['page_data'], 'Pre-save accepts current owned tree and leaves neighbors ' . $record );
    $foreign_root = $case['page_data'][0];
    $foreign_root['settings']['owner_new_field'] = 'foreign change after typed write';
    $GLOBALS['page_data'][0] = $foreign_root;
    check( wpae_accepted_contract_eligibility( $operation, $GLOBALS['page_data'] )['status'] === 'available', 'Owned Undo remains eligible after foreign root change ' . $record );
    $undo = new WP_REST_Request();
    foreach ( [ 'typed_undo' => true, 'post_id' => 42, 'operation_id' => $operation['operation_id'], 'operation_identity' => $operation['operation_identity'], 'revision' => $operation['revision'] ] as $k=>$v ) { $undo->set_param($k,$v); }
    $changed = $GLOBALS['page_data']; $changed[count($changed)-1]['settings']['background_color'] = '#ff0000';
    check(wpae_accepted_contract_eligibility($operation,$changed)['status']==='changed_target','Changed owned target refuses inverse ' . $record);
    $held=wpae_design_operation_acquire_lock();
    check(wpae_llm_undo($undo)->get_data()['code']==='typed_undo_busy','Concurrent Undo refused before write ' . $record);
    wpae_design_operation_release_lock($held);
    $descriptors=wpae_accepted_contract_descriptors(42,$GLOBALS['page_data']);
    check($descriptors[0]['status']==='available' && $descriptors[0]['accepted_contract_id']===$operation['accepted_contract_id'],'Reload bootstrap derives owned eligibility from server ' . $record);
    $empty_request = new WP_REST_Request();
    foreach ( [ 'post_id'=>42, 'operation_id'=>$operation['operation_id'].'-undo', 'accepted_undo_identity'=>$operation['operation_identity'], 'accepted_undo_revision'=>$operation['revision'] ] as $k=>$v ) { $empty_request->set_param($k,$v); }
    check(wpae_accepted_empty_inverse($empty_request,$owned,[]), 'Empty inverse attests exact owned creation '.$record);
    check(!wpae_accepted_empty_inverse($empty_request,$GLOBALS['page_data'],[]), 'Empty inverse refuses loss of foreign roots '.$record);
    $inverse_neighbors=array_values(array_filter($GLOBALS['page_data'],static fn($root)=>!in_array($root['id'],$loaded['contract']['owned_root_ids'],true)));
    check(wpae_accepted_document_inverse($empty_request,$GLOBALS['page_data'],$inverse_neighbors), 'Nonempty inverse attests unchanged foreign neighbors '.$record);
    $altered_neighbors=$inverse_neighbors; $altered_neighbors[0]['settings']['owner_new_field']='lost user change';
    check(!wpae_accepted_document_inverse($empty_request,$GLOBALS['page_data'],$altered_neighbors), 'Inverse refuses rewritten foreign neighbor '.$record);
    $empty_request->set_param('accepted_undo_revision',0);
    check(!wpae_accepted_empty_inverse($empty_request,$owned,[]), 'Empty inverse refuses stale revision '.$record);
    check(!wpae_validate_design_system_contract([])['ok'] && wpae_validate_design_system_contract([],['verified_empty_inverse'=>true])['ok'], 'Empty restore differs from ordinary empty generation '.$record);
    $empty_request->set_param('accepted_undo_revision',$operation['revision']);
    check(wpae_build_elementor_preflight([],$empty_request,['template'=>'elementor_canvas','verified_empty_inverse'=>true])['ok'], 'Real preflight accepts verified empty restore '.$record);
    $empty_options = $GLOBALS['options']; $full_document = $GLOBALS['page_data'];
    $GLOBALS['page_data'] = $owned;
    $empty_result = wpae_llm_undo($undo);
    check($empty_result->get_data()['ok'] && $GLOBALS['page_data'] === [] && $empty_result->get_data()['root_ids'] === [], 'Guarded lifecycle actually restores empty baseline '.$record);
    $GLOBALS['options'] = $empty_options; $GLOBALS['page_data'] = $full_document;
    $GLOBALS['typed_last_update_params']=null;
    $result = wpae_llm_undo( $undo );
    check( $result->get_data()['ok'] && $result->get_data()['undo_action'] === 'undo_creation' && ! in_array( $operation['root_ids'][0], array_column( $GLOBALS['page_data'], 'id' ), true ), 'Creation Undo uses owned inverse after reload ' . $record );
    check(!array_intersect(['title','content','status','slug','page_settings'],array_keys($GLOBALS['typed_last_update_params'])), 'Owned inverse sends no user post fields or page settings ' . $record);
    check( $GLOBALS['page_data'][0] === $foreign_root, 'Owned inverse preserves foreign root exactly ' . $record );
    check( ! wpae_llm_undo( $undo )->get_data()['ok'], 'Repeated stale Undo refused ' . $record );
    $updated = wpae_design_operation_find_by_id( $operation['operation_id'] );
    check( wpae_accepted_contract_eligibility( $updated, $GLOBALS['page_data'] )['status'] === 'already_undone', 'Server bootstrap already_undone distinct ' . $record );
}
$GLOBALS['options'] = $typed_saved_globals['options']; $GLOBALS['page_data'] = $typed_saved_globals['page_data'];


foreach ( [ 'hero.text_only' => 'hero_stack', 'benefits.grid' => 'benefits_grid' ] as $record => $fixture ) {
    $case = $run_services_route( $m1_cases[$fixture][0], [], [], 'typed-repair-' . $fixture, false, 'active', 'active', [ 'composition_record' => $record, 'composition_version' => 1, 'visual_profile' => 'editorial_light' ] );
    $GLOBALS['options'] = $case['lifecycle_options']; $GLOBALS['page_data'] = $case['page_data'];
    $parent = $case['response']['diagnostics']['operation_ledger'];
    $before = wpae_accepted_contract_get($parent)['contract'];
    $report = [ 'report_id' => 'typed-report-' . $fixture, 'source' => 'provider', 'post_id' => 42, 'created_at' => gmdate('c'), 'findings' => [ [ 'severity'=>'minor', 'category'=>'spacing', 'message'=>'Excess spacing is too large', 'fix'=>'Reduce excessive spacing' ] ], 'render_context' => [ 'operation_id'=>$parent['operation_id'], 'operation_identity'=>$parent['operation_identity'], 'operation_revision'=>$parent['revision'], 'operation_root_ids'=>$parent['root_ids'], 'operation_saved_hash'=>$parent['saved_hash'], 'operation_target_fingerprint'=>$parent['target_fingerprint'] ] ];
    $GLOBALS['options'][WPAE_VISION_REPORTS_OPTION]=[$report];
    $context = [ 'post_id'=>42, 'accepted_operation_id'=>$parent['operation_id'], 'accepted_identity'=>$parent['operation_identity'], 'accepted_revision'=>$parent['revision'], 'lifecycle_action'=>'repair', 'accepted_vision_report_id'=>$report['report_id'], 'layout_correction'=>'compact_spacing', 'operation_identity'=>'manual-repair-' . $fixture, 'composition_record'=>'hero.split_40_60.left', 'visual_profile'=>'soft_cards_light' ];
    $result = wpae_accepted_lifecycle_request($context);
    check($result instanceof WP_REST_Response && $result->get_data()['ok'], 'Actual typed scoped repair ' . $record . ' ' . (is_wp_error($result)?$result->get_error_message():wp_json_encode($result->get_data())));
    $child = $result->get_data()['diagnostics']['operation_ledger'];
    $after = wpae_accepted_contract_get($child)['contract'];
    check($after['brief'] === $before['brief'] && $after['composition'] === $before['composition'] && $after['profile'] === $before['profile'], 'Repair ignores dropdown; exact record/profile/Brief copy, links, media/order preserved ' . $record);
    check($after['parent_operation_id']===$parent['operation_id'] && $after['compiled_signature']!==$before['compiled_signature'], 'Real finding causes compiled spacing delta and explicit lineage ' . $record);
    check(wpae_accepted_contract_eligibility(wpae_design_operation_find_by_id($parent['operation_id']),$GLOBALS['page_data'])['status']!=='available','Parent cannot be undone under active child ' . $record);
    $old_model = [ 'post_id'=>42, 'accepted_operation_id'=>$child['operation_id'], 'accepted_identity'=>$child['operation_identity'], 'accepted_revision'=>$child['revision'], 'lifecycle_action'=>'check_model', 'editor_owned_model'=>$before['after_owned'] ];
    check(wpae_accepted_lifecycle_request($old_model)->get_status()===409,'Server accepted replacement, editor old tree: mismatch before Save ' . $record);
    $doc = new class { function get_main_id(){return 42;} }; $blocked=false;
    try { wpae_accepted_elementor_save_guard(['elements'=>$case['page_data']],$doc); } catch(RuntimeException $e){$blocked=true;}
    check($blocked,'Replacement old editor model cannot overwrite current server tree ' . $record);
    $resync=$old_model; $resync['lifecycle_action']='resync';
    check(wpae_accepted_lifecycle_request($resync)->get_data()['ok'],'Known stale before tree supports guarded resync ' . $record);
    $resync['editor_owned_model'][0]['settings']['padding']['top']='999';
    check(is_wp_error(wpae_accepted_lifecycle_request($resync)),'Resync refuses divergent unsaved owned model ' . $record);
    $neighbors=array_values(array_filter($GLOBALS['page_data'],static fn($r)=>!in_array($r['id'],$child['root_ids'],true)));
    $undo=new WP_REST_Request(); foreach(['post_id'=>42,'operation_id'=>$child['operation_id'],'operation_identity'=>$child['operation_identity'],'revision'=>$child['revision'],'typed_undo'=>true] as $k=>$v){$undo->set_param($k,$v);}
    $undone=wpae_llm_undo($undo);
    check($undone->get_data()['ok'] && $undone->get_data()['undo_action']==='undo_repair','Undo repair returns previous block, not empty page ' . $record);
    check(wpae_accepted_owned_roots($GLOBALS['page_data'],$parent['root_ids'])===$before['after_owned'],'Undo repair exact before-owned state ' . $record);
    check(array_values(array_filter($GLOBALS['page_data'],static fn($r)=>!in_array($r['id'],$child['root_ids'],true)))===$neighbors,'Repair and inverse preserve all foreign roots ' . $record);
    $parent = wpae_design_operation_find_by_id($parent['operation_id']);
    check(wpae_accepted_contract_eligibility($parent,$GLOBALS['page_data'])['status']==='available','Creation becomes eligible after distinct repair Undo ' . $record);
    $context['accepted_revision']=$parent['revision']; $context['accepted_vision_report_id']='foreign-report';
    $typed_write_attempt_count=(int)$GLOBALS['m1_write_attempts']; $refused=wpae_accepted_lifecycle_request($context);
    check(is_wp_error($refused) && (int)$GLOBALS['m1_write_attempts']===$typed_write_attempt_count,'Foreign report rejected without fallback/append ' . $record);
    $context['accepted_operation_id']='unknown-operation';check(is_wp_error(wpae_accepted_lifecycle_request($context)),'Unknown contract/operation refusal ' . $record);
    $store=wpae_accepted_contract_store();$store[$parent['accepted_contract_id']]['expires_at']=time()-1;update_option(WPAE_ACCEPTED_CONTRACT_OPTION,$store,false);
    check(wpae_accepted_contract_eligibility($parent,$GLOBALS['page_data'])['reason']==='contract_expired','Expired contract cannot Undo or repair ' . $record);
}
check(wpae_accepted_contract_get(['operation_id'=>'historical'])['reason']==='historical_contract_unavailable','Historical operation never reconstructs lost Plan');
$unjustified=wpae_accepted_layout_delta($before['plan'],['findings'=>[['category'=>'media','message'=>'Too empty: add a photo']]],'compact_spacing');
check(empty($unjustified['ok']),'Sparse text-only does not authorize photo/topology or unsupported delta');
$GLOBALS['options'] = $typed_saved_globals['options']; $GLOBALS['page_data'] = $typed_saved_globals['page_data'];

$oversized=$before['brief'];$oversized['source_text']=str_repeat('x',262144);
check(!wpae_accepted_contract_prepare($oversized,$before['plan'],$before['after_owned'])['ok'],'Oversize refused prewrite including sealed payload reserve');

$expected=[['id'=>'test','elType'=>'container','settings'=>['content_width'=>'boxed','padding'=>['top'=>'5','unit'=>'rem']],'elements'=>[]]];
$materialized=$expected;$materialized[0]['settings']['unused_color']='#ff0000';unset($materialized[0]['settings']['content_width']);
$defaults=static fn($n)=>['content_width'=>'boxed','unused_color'=>'#ff0000'];
$projected=wpae_accepted_project_owned_model($expected,$materialized,$defaults);
check($projected!==null && wpae_accepted_owned_fingerprint($expected)===wpae_accepted_owned_fingerprint($projected),'Known server defaults preserve authored decisions across serialized editor/save model');
$materialized[0]['settings']['unused_color']='#00ff00';check(wpae_accepted_project_owned_model($expected,$materialized,$defaults)===null,'Nondefault extra control refuses; author keys not broadly filtered');
$materialized=$expected;$materialized[0]['settings']['padding']['top']='99';$projected=wpae_accepted_project_owned_model($expected,$materialized,$defaults);
check($projected===null,'Changed authored spacing still fails after default canonicalization');
$mismatch=null;wpae_accepted_project_owned_model($expected,$materialized,$defaults,$mismatch);
check($mismatch===['node_id'=>'test','control'=>'padding','reason'=>'authored_control_changed'],'Bounded mismatch identifies control without disclosing values');
$responsive_defaults=wpae_accepted_expand_responsive_defaults(['grid_auto_flow'=>'row'],['grid_auto_flow'=>['is_responsive'=>true,'tablet_default'=>'row','mobile_default'=>'row'],'unregistered'=>['mobile_default'=>'row']]);
check($responsive_defaults===['grid_auto_flow'=>'row','grid_auto_flow_tablet'=>'row','grid_auto_flow_mobile'=>'row'],'Only registered responsive device defaults expand');
$grid_expected=[['id'=>'grid','elType'=>'container','settings'=>['grid_auto_flow_tablet'=>'row','grid_auto_flow_mobile'=>'row'],'elements'=>[]]];
$grid_actual=$grid_expected; $grid_actual[0]['settings']=[];
check(wpae_accepted_project_owned_model($grid_expected,$grid_actual,static fn($n)=>$responsive_defaults)!==null,'Native omitted Grid device defaults preserve authored contract');
$grid_actual[0]['settings']['grid_auto_flow_tablet']='column';
check(wpae_accepted_project_owned_model($grid_expected,$grid_actual,static fn($n)=>$responsive_defaults)===null,'Changed Grid flow still refuses');
$slider_defaults=wpae_accepted_expand_responsive_defaults([],['grid_columns_grid'=>['type'=>'slider','is_responsive'=>true,'default'=>['unit'=>'fr','size'=>3,'sizes'=>[]],'mobile_default'=>['unit'=>'fr','size'=>1]]]);
check($slider_defaults['grid_columns_grid_mobile']===['unit'=>'fr','size'=>1,'sizes'=>[]],'Native declared slider device default retains empty sizes slot without inheriting desktop size');
