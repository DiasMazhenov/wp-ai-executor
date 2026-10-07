const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root,'assets/js/elementor-llm-chat.js'),'utf8');
const catalog = JSON.parse(execFileSync('php',['-r', `define('ABSPATH', '/'); function wp_json_encode($x){return json_encode($x);} require '${root}/includes/elementor/recipes.php'; echo json_encode(wpae_composition_editor_catalog());`],{encoding:'utf8'}));
class Element {
 constructor(){this.children=[];this.value='';this.listeners={};this.disabled=false;}
 appendChild(child){this.children.push(child);}
 setAttribute(){}
 addEventListener(name,fn){this.listeners[name]=fn;}
 set textContent(v){this.children=[];this.text=v;this.value='';}
}
function controls(mode='active') {
 const env = {config:{compositionCatalog:catalog,pipelineMode:mode},document:{createElement:()=>new Element()},panel:new Element(),form:new Element(),input:{},JSON,Array,String,readOperationIdentity:()=> 'identity',window:{elementor:{getPreviewContainer:()=>({})},sessionStorage:{getItem:()=>null}},deliverySnapshotKey:'key'};
 vm.createContext(env);
 vm.runInContext(js.slice(js.indexOf('    var compositionControls ='),js.indexOf('    form.appendChild(input);')),env);
 return env;
}
const plain = x=>JSON.parse(JSON.stringify(x));
test('safe PHP projection, grouped distinct records, profiles and Automatic',()=>{
 for (const id of ['team.grid','team.editorial_rows','testimonials.grid','testimonials.editorial_rows']) assert.ok(catalog.records.some(r=>r.id===id && r.profiles.includes('editorial_light') && r.profiles.includes('soft_cards_light')));
	assert.ok(!catalog.records.some(r=>r.id==='benefits.linear'));
	assert.ok(catalog.records.some(r=>r.id==='services.icon_cards' && r.label==='Карточки с иконками'));
	assert.ok(catalog.records.some(r=>r.id==='process.ordered_steps' && r.label==='Упорядоченные этапы'));
 assert.ok(catalog.records.every(r=>Object.keys(r).sort().join(',')==='family,family_label,id,label,profiles,version'));
 const e=controls();
 assert.deepEqual(plain(e.compositionSelectionSnapshot({},[])),{});
 e.compositionSelect.value='hero.split_60_40.left';e.refreshCompositionProfiles();
 assert.equal(e.profileSelect.children.length,3);
 e.profileSelect.value='editorial_light';
 assert.deepEqual(plain(e.compositionSelectionSnapshot({},[])),{composition_record:'hero.split_60_40.left',composition_version:1,visual_profile:'editorial_light'});
 for(const opt of [{visionRepair:true},{visionRegenerate:true},{targetedDesignRepair:true},{replaceExistingRoot:true}]) assert.deepEqual(plain(e.compositionSelectionSnapshot(opt,[])),{});
 assert.deepEqual(plain(e.compositionSelectionSnapshot({},[{id:'selected'}])),{});
 e.compositionSelect.value='pricing.tiers';e.refreshCompositionProfiles();
 assert.equal(e.profileSelect.children.length,1);assert.equal(e.profileSelect.disabled,true);
 for(const mode of ['off','shadow']) {const disabled=controls(mode);disabled.compositionSelect.value='hero.text_only'; assert.deepEqual(plain(disabled.compositionSelectionSnapshot({},[])),{});}
});
function requestHarness() {
 const e=controls(); const storage=new Map(); let identity=''; let sequence=0;const posts=[];
 Object.assign(e,{requestInFlight:false,editorSyncConflict:null,liveGeneratedRootIds:[],selectedElements:()=>[],targetedDesignReplacement:()=>null,readOperationIdentity:()=>identity,rememberOperationIdentity:x=>{identity=x;},newOperationIdentity:()=>`identity-${++sequence}`,clearOperationRoots(){},lastBriefKey:'brief',getPreviewWidgetCount:()=>0,messages:{querySelectorAll:()=>[]},status:{},send:{disabled:false},resetPipelinePhases(){},setPipelinePhase(){},addMessage(){},getPreviewBackgroundImageUrls:()=>[],getPreviewInheritedPaletteContext:()=>({page_tokens_confirmed:true,page_tokens_source:'elementor_preview_computed_body',page_tokens:{'color.page_bg':'#ffffff','color.text':'#333333'},page_tokens_viewport:{width:1025,height:860}}),captureEditorRootSnapshot:()=>({ids:[],fingerprints:{}}),readProviderRetry:()=>null,readVisionRepair:()=>null,clearProviderRetry(){},isProviderRateLimited:()=>false,isProviderUnavailable:()=>false,deliveryRetryPending:false,strings:{},window:{location:{href:'https://mazhenov.kz/wp-admin/post.php?post=5214&action=elementor',origin:'https://mazhenov.kz'},elementor:{getPreviewContainer:()=>({})},sessionStorage:{setItem:(k,v)=>storage.set(k,v),getItem:k=>storage.get(k)},setInterval:()=>1,clearInterval(){},setTimeout:()=>1,clearTimeout(){}},fetch:(url,options)=>{posts.push(JSON.parse(options.body));return Promise.reject(new Error('transport response lost'));}});
 e.getEditorModelId=m=>m.id; e.getEditorModelFingerprint=m=>JSON.stringify(m);
 vm.runInContext(js.slice(js.indexOf('    function getEditorModelChildren('),js.indexOf('    function collectEditorModelTree(')),e);
 vm.runInContext(js.slice(js.indexOf('    function serializeTypedModel('),js.indexOf('    function verifyTypedEditorModel(')),e);
 vm.runInContext(js.slice(js.indexOf('    function captureEditorRootSnapshot('),js.indexOf('    function editorModelMatchesSnapshot(')),e);
 e.config.endpoint='/llm/chat'; e.config.postId=5214;
 vm.runInContext(js.slice(js.indexOf('    var requestInFlight ='),js.indexOf("    open.addEventListener('click'")),e);
 return {e,posts};
}
test('real submit request payload and replay keep snapshot and operation identity',async()=>{
 const {e,posts}=requestHarness();
 e.compositionSelect.value='hero.text_only';e.refreshCompositionProfiles();e.profileSelect.value='editorial_light';
 const pending=e.request('original',false,{});
 await e.request('duplicate while busy',false,{});assert.equal(posts.length,1);
 await pending;
 assert.equal(posts[0].message,'original');assert.equal(posts[0].context.composition_version,1);
 assert.equal(posts[0].context.page_tokens_source,'elementor_preview_computed_body');assert.deepEqual(plain(posts[0].context.page_tokens),{'color.page_bg':'#ffffff','color.text':'#333333'});assert.deepEqual(plain(posts[0].context.page_tokens_viewport),{width:1025,height:860});
 e.compositionSelect.value='pricing.tiers';e.refreshCompositionProfiles();
 const snapshot=e.readDeliverySnapshot('original');
 await e.request('original',false,{retryCurrentOperation:true,deliverySnapshot:snapshot});
 assert.equal(posts.length,2);assert.equal(posts[1].context.composition_record,'hero.text_only');
 assert.equal(posts[1].context.visual_profile,'editorial_light');assert.equal(posts[1].context.operation_identity,posts[0].context.operation_identity);
 assert.deepEqual(posts[1].history,posts[0].history);assert.equal(posts[1].context.retry_current_operation,true);
 await e.request('new user submit',false,{});
 assert.notEqual(posts[2].context.operation_identity,posts[0].context.operation_identity);assert.equal(posts[2].context.composition_record,'pricing.tiers');assert.notEqual(posts[2].context.composition_record,posts[1].context.composition_record,'new request reads current UI selectors instead of replaying the old delivery snapshot');
 await e.request('original',false,{retryCurrentOperation:true,deliverySnapshot:snapshot});assert.equal(posts.length,3,'stale replay stops');
 e.compositionSelect.value='';e.refreshCompositionProfiles();await e.request('automatic',false,{});
 assert.ok(!('composition_record' in posts[3].context));assert.ok(!('visual_profile' in posts[3].context));
 for(const opt of [{visionRepair:true},{visionRegenerate:true},{targetedDesignRepair:true},{replaceExistingRoot:true,replacesOperation:{root_ids:['owned']}}]){
 e.compositionSelect.value='hero.text_only';e.refreshCompositionProfiles();e.profileSelect.value='editorial_light';await e.request('scoped',false,opt);
 assert.ok(!('composition_record' in posts.at(-1).context));assert.ok(!('visual_profile' in posts.at(-1).context));
 }
});

test('read-only descriptor keeps exact contract hash and exposes frozen composition evidence',async()=>{
 const evidence={schema:'wpae-composition-evidence-v1',status:'complete',request:{mode:'explicit_record',record_id:'team.grid',record_version:1,visual_profile:'editorial_light'},accepted:{record_id:'team.grid',record_version:1,record_hash:'record-hash',source:'explicit_record',visual_profile:'editorial_light',selection_reasons:['honored_explicit_record']},hashes:{brief_sha256:'brief-hash',plan_sha256:'plan-hash'},surface:{profile:'editorial_light'},generation:{provider_calls:0,transaction_write_count:1},operation:{post_id:5214,operation_id:'op-1',operation_identity:'identity-1',root_ids:['root-1'],revision:7,accepted_contract_id:'contract-1',accepted_contract_sha256:'contract-hash'},native:{saved_native_fingerprint:'native-fingerprint'}};
 const descriptor={post_id:5214,operation_id:'op-1',operation_identity:'identity-1',accepted_contract_id:'contract-1',accepted_contract_hash:'contract-hash',root_ids:['root-1'],revision:7,eligibility:{status:'available'},composition_evidence:evidence};
 const makeEnv=returned=>{const env={config:{postId:5214,endpoint:'/chat'},JSON,Number,String,Boolean,Array,fetch:async()=>({ok:true,json:async()=>({ok:true,write_count:0,operation:returned})})};vm.createContext(env);vm.runInContext(js.slice(js.indexOf('    function typedLifecyclePost('),js.indexOf('    function serializeTypedModel(')),env);return env;};
 const env=makeEnv(descriptor);const expected={...descriptor,revision:6,eligibility:{status:'unknown'}};const fresh=await env.describeTypedOperation(expected);
 assert.deepEqual(plain(fresh.composition_evidence),evidence);assert.equal(fresh.operation.revision,7);assert.equal(fresh.write_count,0);assert.match(env.formatCompositionEvidence(fresh.composition_evidence),/team\.grid/);assert.match(env.formatCompositionEvidence(fresh.composition_evidence),/contract-hash/);
 const wrongHash=makeEnv({...descriptor,accepted_contract_hash:'other-hash'});await assert.rejects(wrongHash.describeTypedOperation(expected),/contract\/root scope/,'same operation and root with a different accepted-contract hash is rejected');
});

test('preview palette context accepts only opaque styles from the current same-origin post preview',()=>{
 const helper=js.slice(js.indexOf('    function previewOpaqueColorToHex('),js.indexOf('    function reloadPreviewIframe()'));
 const run=(src,bg,text,post=5214)=>{
  const doc={location:{href:src},body:{classList:{contains:(name)=>name==='page-id-5214'}},documentElement:{clientWidth:1025,clientHeight:860},defaultView:{getComputedStyle:()=>({backgroundColor:bg,color:text})}};
  const iframe={src,contentDocument:doc,clientWidth:1025,clientHeight:860,getAttribute:()=>src};
  const env={config:{postId:5214},window:{location:{href:'https://mazhenov.kz/wp-admin/post.php?post=5214&action=elementor',origin:'https://mazhenov.kz'}},URL,Number,String,Array,getPreviewIframe:()=>iframe};
  vm.createContext(env);vm.runInContext(helper,env);
  return env.getPreviewInheritedPaletteContext();
 };
 const good=run('https://mazhenov.kz/pricing-contract-live-v123/?elementor-preview=5214&ver=1','rgb(255, 255, 255)','rgb(51, 51, 51)');
 assert.deepEqual(plain(good),{page_tokens_confirmed:true,page_tokens_source:'elementor_preview_computed_body',page_tokens:{'color.page_bg':'#ffffff','color.text':'#333333'},page_tokens_viewport:{width:1025,height:860}});
 assert.equal(run('https://mazhenov.kz/pricing-contract-live-v123/?elementor-preview=5214','rgba(255, 255, 255, 0.5)','rgb(51, 51, 51)'),null,'translucent background cannot confirm the painted canvas');
 assert.equal(run('https://other.example/?elementor-preview=5214','rgb(255, 255, 255)','rgb(51, 51, 51)'),null,'cross-origin page cannot supply the context');
 assert.equal(run('https://mazhenov.kz/?elementor-preview=999','rgb(255, 255, 255)','rgb(51, 51, 51)'),null,'a different post preview cannot supply the context');
});

test('fresh create refuses native HTML fallback over an empty saved baseline before any provider request',async()=>{
 const {e,posts}=requestHarness();
 e.config.savedBaseline={status:'valid_array',rootIds:[]};
 e.window.elementor.getPreviewContainer=()=>({get:()=>[{id:'unowned-html-fallback'}]});
 assert.equal(await e.request('new create',false,{}),false);
 assert.equal(posts.length,0);
 assert.deepEqual(plain(e.liveGeneratedRootIds),[]);
 e.window.elementor.getPreviewContainer=()=>({get:()=>[]});
 await e.request('verified empty create',false,{});
 assert.equal(posts.length,1);
});
