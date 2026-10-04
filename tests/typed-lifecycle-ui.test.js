const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const src = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/elementor-llm-chat.js'),'utf8');
const helpers = src.slice(src.indexOf('    function typedLifecyclePost('),src.indexOf('    function addActionControls('));
class Element {constructor(){this.children=[];this.listeners={};} appendChild(x){this.children.push(x);} addEventListener(k,f){this.listeners[k]=f;}}
function harness(){
 let saved = {roots:['owned']};const posts=[];const errors=[];
 const env={config:{postId:5214,endpoint:'/chat',undoEndpoint:'/undo',nonce:'test'},fetch:async(url,o)=>{posts.push(JSON.parse(o.body));return {ok:true,json:async()=>({ok:true})};},Promise,JSON,Number,String,Array,Object,Error,document:{createElement:()=>new Element(),querySelectorAll:()=>[{disabled:true,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}]},messages:new Element(),requestInFlight:false,addMessage:(_,m)=>errors.push(m),captureEditorRootSnapshot:()=>saved,window:{elementor:{getPreviewContainer:()=>({})},location:{reload:()=>{env.reload=true;}}},getEditorModelChildren:()=>[{id:'owned'}],getEditorModelId:m=>m.id,serializeSelectedModel:m=>m,cloneEditorValue:x=>x,request:(...args)=>env.requestArgs=args};
 vm.createContext(env);vm.runInContext(helpers,env);
 return {env,posts,errors,setSnapshot:x=>{saved=x;}};
}
test('server/model comparison and explicit scoped repair use exact operation, no dropdown',async()=>{
 const {env,posts}=harness(); const op={operation_id:'op',operation_identity:'identity',revision:7,root_ids:['owned'],accepted_contract_id:'contract'};
 await env.verifyTypedEditorModel({diagnostics:{operation_ledger:op}});
 assert.equal(posts[0].context.lifecycle_action,'check_model');assert.equal(posts[0].context.accepted_revision,7);assert.deepEqual(posts[0].context.editor_owned_model,[{id:'owned'}]);
 env.addTypedRepairControl(op,{report:{report_id:'report'}});env.messages.children.at(-1).listeners.click();
 const ctx=env.requestArgs[2].lifecycleContext;assert.equal(ctx.accepted_operation_id,'op');assert.equal(ctx.accepted_vision_report_id,'report');assert.equal(ctx.layout_correction,'compact_spacing');assert.ok(!('composition_record' in ctx));assert.ok(!('visual_profile' in ctx));
});
test('durable Undo checks clean saved UI and full root baseline before request/reload',async()=>{
 const {env,posts,setSnapshot}=harness();env.rememberTypedEditorBaseline();
 env.addTypedUndoControl({status:'available',action:'undo_repair',operation_id:'child',operation_identity:'identity',revision:9});const button=env.messages.children[0].children[0];
 assert.equal(button.textContent,'Отменить последнее исправление');
 setSnapshot({roots:['owned','foreign-unsaved']});button.listeners.click();assert.equal(posts.length,0);
 setSnapshot({roots:['owned']});env.document.querySelectorAll=()=>[{disabled:false,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}];button.listeners.click();assert.equal(posts.length,0);
 env.document.querySelectorAll=()=>[{disabled:true,textContent:'Опубликовать',getBoundingClientRect:()=>({width:100,height:30})}];button.listeners.click();await new Promise(r=>setImmediate(r));assert.equal(posts[0].typed_undo,true);assert.equal(posts[0].revision,9);assert.equal(env.reload,true);
 const other=harness();other.env.addTypedUndoControl({status:'unavailable',reason:'historical_contract_unavailable'});assert.equal(other.env.messages.children[0].children[0].disabled,true);
});
