const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const src = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/elementor-llm-chat.js'),'utf8');
const helpers = src.slice(src.indexOf('    function typedLifecyclePost('),src.indexOf('    function addActionControls('));
class Element {constructor(){this.children=[];this.listeners={};} appendChild(x){this.children.push(x);} addEventListener(k,f){this.listeners[k]=f;}}
function harness(){
 let saved = {roots:['owned']};const posts=[];const errors=[];
 const env={config:{postId:5214,endpoint:'/chat',undoEndpoint:'/undo',nonce:'test'},fetch:async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);const c=p.context||{};const body=c.lifecycle_action==='describe_operation'?{ok:true,write_count:0,operation:{post_id:5214,operation_id:c.accepted_operation_id,operation_identity:c.accepted_identity,accepted_contract_id:c.accepted_contract_id,root_ids:c.accepted_root_ids,revision:Math.max(7,c.accepted_revision),eligibility:{status:'available'}}}:{ok:true};return {ok:true,json:async()=>body};},Promise,JSON,Number,String,Array,Object,Error,document:{createElement:()=>new Element(),querySelectorAll:()=>[{disabled:true,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}]},messages:new Element(),requestInFlight:false,addMessage:(_,m)=>errors.push(m),captureEditorRootSnapshot:()=>saved,window:{elementor:{getPreviewContainer:()=>({})},location:{reload:()=>{env.reload=true;}}},getEditorModelChildren:m=>m.id?[]:[{id:'owned',elType:'container'}],getEditorModelId:m=>m.id,serializeSelectedModel:m=>m,cloneEditorValue:x=>x,request:(...args)=>env.requestArgs=args};
 vm.createContext(env);vm.runInContext(helpers,env);
 return {env,posts,errors,setSnapshot:x=>{saved=x;}};
}
test('server/model comparison and explicit scoped repair use exact operation, no dropdown',async()=>{
 const {env,posts}=harness(); const op={operation_id:'op',operation_identity:'identity',revision:7,root_ids:['owned'],accepted_contract_id:'contract'};
 await env.verifyTypedEditorModel({diagnostics:{operation_ledger:op}});
 assert.equal(posts[0].context.lifecycle_action,'check_model');assert.equal(posts[0].context.accepted_revision,7);assert.equal(posts[0].context.accepted_contract_id,'contract');assert.deepEqual(posts[0].context.accepted_root_ids,['owned']);assert.deepEqual(posts[0].context.editor_owned_model,[{id:'owned',elType:'container',widgetType:'',isInner:false,settings:{},elements:[]}]);
 env.addTypedRepairControl(op,{report:{report_id:'report'}});env.messages.children.at(-1).listeners.click();await new Promise(r=>setImmediate(r));
 const ctx=env.requestArgs[2].lifecycleContext;assert.equal(ctx.accepted_operation_id,'op');assert.equal(ctx.accepted_vision_report_id,'report');assert.equal(ctx.layout_correction,'compact_spacing');assert.ok(!('composition_record' in ctx));assert.ok(!('visual_profile' in ctx));
});
test('durable Undo verifies fresh server document and visible Save before reload',async()=>{
 const {env,posts,errors}=harness();
 const op={status:'available',action:'undo_repair',operation_id:'child',operation_identity:'identity',revision:4,accepted_contract_id:'contract',root_ids:['owned']};
 env.addTypedUndoControl(op);const button=env.messages.children[0].children[0];assert.equal(button.textContent,'Отменить последнее исправление');
 env.document.querySelectorAll=()=>[{disabled:false,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}];button.listeners.click();assert.equal(posts.length,0);
 env.document.querySelectorAll=()=>[{disabled:true,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}];
 env.fetch=async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);if(p.context&&p.context.lifecycle_action==='describe_operation')return {ok:true,json:async()=>({ok:true,operation:{post_id:5214,operation_id:'child',operation_identity:'identity',accepted_contract_id:'contract',root_ids:['owned'],revision:9,eligibility:{status:'available'}}})};return {ok:false,json:async()=>({ok:false,code:'typed_document_model_mismatch'})};};
 button.listeners.click();await new Promise(r=>setImmediate(r));assert.equal(posts[0].context.lifecycle_action,'describe_operation');assert.equal(posts[1].context.lifecycle_action,'check_document_model');assert.equal(posts.length,2);assert.ok(!env.reload);assert.ok(errors.at(-1).includes('mismatch'));
 posts.length=0;op.revision=4;env.fetch=async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);if(p.context&&p.context.lifecycle_action==='describe_operation')return {ok:true,json:async()=>({ok:true,operation:{post_id:5214,operation_id:'child',operation_identity:'identity',accepted_contract_id:'contract',root_ids:['owned'],revision:9,eligibility:{status:'available'}}})};if(url==='/undo')return {ok:true,json:async()=>({ok:true})};return {ok:true,json:async()=>({ok:true})};};button.listeners.click();await new Promise(r=>setImmediate(r));
 assert.equal(posts[0].context.accepted_revision,4);assert.equal(posts[1].context.lifecycle_action,'check_document_model');assert.equal(posts[1].context.accepted_revision,9);assert.equal(posts[2].typed_undo,true);assert.equal(posts[2].revision,9);assert.equal(posts[2].accepted_contract_id,'contract');assert.deepEqual(posts[2].accepted_root_ids,['owned']);assert.equal(env.reload,true);
 const other=harness();other.env.addTypedUndoControl({status:'unavailable',reason:'historical_contract_unavailable'});assert.equal(other.env.messages.children[0].children[0].disabled,true);
});

test('Undo refuses native edit arriving during read-only descriptor refresh',async()=>{
 const {env,posts}=harness();env.addTypedUndoControl({status:'available',operation_id:'child',operation_identity:'identity',revision:4,accepted_contract_id:'contract',root_ids:['owned']});
 let resolveCheck;env.fetch=(url,o)=>{posts.push(JSON.parse(o.body));return new Promise(r=>{resolveCheck=r;});};
 env.messages.children[0].children[0].listeners.click();env.getEditorModelChildren=()=>[{id:'owned',elType:'container',settings:{title:'new local edit'}}];
 resolveCheck({ok:true,json:async()=>({ok:true,operation:{post_id:5214,operation_id:'child',operation_identity:'identity',accepted_contract_id:'contract',root_ids:['owned'],revision:5,eligibility:{status:'available'}}})});await new Promise(r=>setImmediate(r));assert.equal(posts.length,1);assert.ok(!env.reload);
});

test('actual Container unwrap and settings serialization remove only registered defaults',()=>{
 const {env}=harness();
 const child={id:'child',elType:'widget',settings:{title:'Exact'}};
 const childrenFn=src.slice(src.indexOf('    function getEditorModelChildren('),src.indexOf('    function collectEditorModelTree('));vm.runInContext(childrenFn,env);
 const model={toJSON:()=>({id:'owned',elType:'container',settings:{padding:5,defaultColor:'red'}}),get:key=>key==='elements'?{models:[]}:key==='settings'?{toJSON:options=>{assert.deepEqual(JSON.parse(JSON.stringify(options)),{remove:['default']});return {padding:5};}}:null};
 const container={model:{get:key=>key==='elements'?{models:[model]}:null}};
 assert.equal(env.getEditorModelChildren(container)[0],model);
 assert.deepEqual(JSON.parse(JSON.stringify(env.serializeTypedModel(model))).settings,{padding:5});
});

test('reload descriptor exposes a read-only exact-operation model check',async()=>{
 const {env,posts,errors}=harness();
 env.fetch=async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);return {ok:true,json:async()=>p.context.lifecycle_action==='describe_operation'?{ok:true,write_count:0,operation:{post_id:5214,accepted_contract_id:'contract',operation_id:'child',operation_identity:'identity',revision:7,root_ids:['owned'],eligibility:{status:'available'}}}:{ok:true}};};
 env.addTypedUndoControl({status:'available',accepted_contract_id:'contract',operation_id:'child',operation_identity:'identity',revision:4,root_ids:['owned']});
 const button=env.messages.children[0].children[1];assert.equal(button.textContent,'Проверить owned модель перед Save');
 button.listeners.click();await new Promise(r=>setImmediate(r));
 assert.equal(posts[0].context.lifecycle_action,'describe_operation');assert.equal(posts[1].context.lifecycle_action,'check_model');assert.equal(posts[1].context.accepted_operation_id,'child');assert.equal(posts[1].context.accepted_revision,7);assert.equal(posts[1].context.accepted_contract_id,'contract');assert.deepEqual(posts[1].context.accepted_root_ids,['owned']);
 assert.ok(errors[0].includes('соответствует'));assert.ok(!env.reload);
});

test('refreshed but ineligible descriptor exposes server revision and stops before model check or Undo',async()=>{
 const {env,posts,errors}=harness();
 env.fetch=async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);return {ok:true,json:async()=>({ok:true,write_count:0,operation:{post_id:5214,operation_id:'child',operation_identity:'identity',accepted_contract_id:'contract',root_ids:['owned'],revision:11,eligibility:{status:'changed_target',reason:'owned_fingerprint_changed'}}})};};
 const op={status:'available',operation_id:'child',operation_identity:'identity',revision:4,accepted_contract_id:'contract',root_ids:['owned']};
 env.addTypedUndoControl(op);env.messages.children[0].children[0].listeners.click();await new Promise(r=>setImmediate(r));
 assert.equal(posts.length,1);assert.equal(posts[0].context.lifecycle_action,'describe_operation');assert.ok(errors[0].includes('revision 11'));assert.ok(errors[0].includes('changed_target/owned_fingerprint_changed'));assert.ok(!env.reload);
});

test('descriptor scope mismatch stops before owned check and mutation',async()=>{
 const {env,posts,errors}=harness();
 env.fetch=async(url,o)=>{const p=JSON.parse(o.body);posts.push(p);return {ok:true,json:async()=>({ok:true,operation:{post_id:5214,operation_id:'child',operation_identity:'foreign-identity',accepted_contract_id:'contract',root_ids:['owned'],revision:5,eligibility:{status:'available'}}})};};
 env.addTypedUndoControl({status:'available',operation_id:'child',operation_identity:'identity',revision:4,accepted_contract_id:'contract',root_ids:['owned']});
 env.messages.children[0].children[0].listeners.click();await new Promise(r=>setImmediate(r));
 assert.equal(posts.length,1);assert.ok(errors[0].includes('scope'));assert.ok(!env.reload);
});


test('native root fingerprint ignores render cache but retains authored changes',()=>{
 const {env}=harness();
 const fingerprint=src.slice(src.indexOf('    function getEditorModelFingerprint('),src.indexOf('    function captureEditorRootSnapshot('));vm.runInContext(fingerprint,env);
 let title='Exact',cache='first paint';
 const model={id:'owned',toJSON:()=>({id:'owned',elType:'widget',widgetType:'heading',settings:{title},htmlCache:cache}),get:key=>key==='settings'?{toJSON:()=>({title})}:null};
 const original=env.getEditorModelFingerprint(model);cache='later paint';assert.equal(env.getEditorModelFingerprint(model),original);
 title='User edit';assert.notEqual(env.getEditorModelFingerprint(model),original);
});
