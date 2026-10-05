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
 const env = {config:{compositionCatalog:catalog,pipelineMode:mode},document:{createElement:()=>new Element()},panel:new Element(),form:new Element(),input:{},JSON,Array,String,readOperationIdentity:()=> 'identity',window:{sessionStorage:{getItem:()=>null}},deliverySnapshotKey:'key'};
 vm.createContext(env);
 vm.runInContext(js.slice(js.indexOf('    var compositionControls ='),js.indexOf('    form.appendChild(input);')),env);
 return env;
}
const plain = x=>JSON.parse(JSON.stringify(x));
test('safe PHP projection, grouped distinct records, profiles and Automatic',()=>{
 assert.equal(catalog.records.length,17);
 assert.ok(!catalog.records.some(r=>r.id==='benefits.linear' || r.family==='services'));
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
 Object.assign(e,{requestInFlight:false,editorSyncConflict:null,liveGeneratedRootIds:[],selectedElements:()=>[],targetedDesignReplacement:()=>null,readOperationIdentity:()=>identity,rememberOperationIdentity:x=>{identity=x;},newOperationIdentity:()=>`identity-${++sequence}`,clearOperationRoots(){},lastBriefKey:'brief',getPreviewWidgetCount:()=>0,messages:{querySelectorAll:()=>[]},status:{},send:{disabled:false},resetPipelinePhases(){},setPipelinePhase(){},addMessage(){},getPreviewBackgroundImageUrls:()=>[],captureEditorRootSnapshot:()=>({valid:true,root_ids:[]}),readProviderRetry:()=>null,readVisionRepair:()=>null,clearProviderRetry(){},isProviderRateLimited:()=>false,isProviderUnavailable:()=>false,deliveryRetryPending:false,strings:{},window:{sessionStorage:{setItem:(k,v)=>storage.set(k,v),getItem:k=>storage.get(k)},setInterval:()=>1,clearInterval(){},setTimeout:()=>1,clearTimeout(){}},fetch:(url,options)=>{posts.push(JSON.parse(options.body));return Promise.reject(new Error('transport response lost'));}});
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
 e.compositionSelect.value='pricing.tiers';e.refreshCompositionProfiles();
 const snapshot=e.readDeliverySnapshot('original');
 await e.request('original',false,{retryCurrentOperation:true,deliverySnapshot:snapshot});
 assert.equal(posts.length,2);assert.equal(posts[1].context.composition_record,'hero.text_only');
 assert.equal(posts[1].context.visual_profile,'editorial_light');assert.equal(posts[1].context.operation_identity,posts[0].context.operation_identity);
 assert.deepEqual(posts[1].history,posts[0].history);assert.equal(posts[1].context.retry_current_operation,true);
 await e.request('new user submit',false,{});
 assert.notEqual(posts[2].context.operation_identity,posts[0].context.operation_identity);
 await e.request('original',false,{retryCurrentOperation:true,deliverySnapshot:snapshot});assert.equal(posts.length,3,'stale replay stops');
 e.compositionSelect.value='';e.refreshCompositionProfiles();await e.request('automatic',false,{});
 assert.ok(!('composition_record' in posts[3].context));assert.ok(!('visual_profile' in posts[3].context));
 for(const opt of [{visionRepair:true},{visionRegenerate:true},{targetedDesignRepair:true},{replaceExistingRoot:true,replacesOperation:{root_ids:['owned']}}]){
 e.compositionSelect.value='hero.text_only';e.refreshCompositionProfiles();e.profileSelect.value='editorial_light';await e.request('scoped',false,opt);
 assert.ok(!('composition_record' in posts.at(-1).context));assert.ok(!('visual_profile' in posts.at(-1).context));
 }
});

test('fresh create refuses native HTML fallback over an empty saved baseline before any provider request',async()=>{
 const {e,posts}=requestHarness();
 e.config.savedBaseline={status:'valid_array',rootIds:[]};
 e.captureEditorRootSnapshot=()=>({valid:true,root_ids:['unowned-html-fallback']});
 assert.equal(await e.request('new create',false,{}),false);
 assert.equal(posts.length,0);
 assert.deepEqual(plain(e.liveGeneratedRootIds),[]);
 e.captureEditorRootSnapshot=()=>({valid:true,root_ids:[]});
 await e.request('verified empty create',false,{});
 assert.equal(posts.length,1);
});
