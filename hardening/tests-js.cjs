const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
let collection, timer, calls = [], confirm = true, inputCallback, refreshes=0;
const messageElement={addEventListener(name,fn) {assert.equal(name,'input');inputCallback=fn;}};
const $ = (value) => value === context.document ? { ready: fn => fn() } : {
  text(text) { this.value = text; return this; },
  html() { return this.value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'); }
};
$.getJSON = (url, query, callback) => {
  const request = {query, callback, fail(fn) {this.failure = fn; return this;}, abort() {this.aborted = true; this.failure?.();}};
  calls.push(request); return request;
};
const context = { document:{querySelectorAll:()=>[messageElement]}, $, window:{confirm:()=>confirm}, MIN_MENTION_LENGTH:2,
  U_AJAX_MENTION_URL:'/mention', SIMPLE_MENTION_GROUP_NAME:'({CNT})', MENTION_LARGE_THRESHOLD:50,
  MENTION_CONFIRM_GROUP:'Confirm {CNT}',
  Tribute:function(options) { collection = options.collection[0]; this.attach = ()=>{}; this.events={commandEvent:true,keyup:function(instance,event) {assert.equal(this,messageElement);assert.equal(instance.commandEvent,false);refreshes++;}}; },
  setTimeout(fn, ms) {assert.equal(ms,300); timer = fn; return 1;}, clearTimeout() {timer=null;}
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(__dirname + '/../styles/all/template/js/mention.js','utf8'),context);
let results = [];
collection.values('jo', data => results.push(data));
collection.values('jos', data => results.push(data));
assert.equal(calls.length,0); timer(); assert.equal(calls.length,1); assert.equal(calls[0].query.q,'jos');
collection.values('jose', data=>results.push(data)); assert(calls[0].aborted);
calls[0].callback(['stale']); assert.equal(results.length,0);
timer(); calls[1].callback(['fresh']); assert.equal(results[0][0],'fresh');
collection.values('j', data=>results.push(data)); assert.equal(results.at(-1).length,0);
collection.values('error', data=>results.push(data)); timer(); calls.at(-1).failure(); assert.equal(results.at(-1).length,0);
assert.equal(collection.menuItemTemplate({original:{type:'user',value:'<img onerror=bad>'}}),'&lt;img onerror=bad&gt;');
confirm=false; assert.equal(collection.selectTemplate({original:{type:'group',cnt:51,value:'Group',group_id:1}}),null);
confirm=true; assert.equal(collection.selectTemplate({original:{type:'group',cnt:51,value:'Group',group_id:1}}),'[smention g=1]Group[/smention]');
assert.equal(collection.requireLeadingSpace,true);
assert.equal(collection.searchOpts.skip,true);
inputCallback.call(messageElement,{inputType:'deleteContentBackward'});
inputCallback.call(messageElement,{inputType:'deleteContentForward'});
inputCallback.call(messageElement,{inputType:'deleteByCut'});
assert.equal(refreshes,3);
collection.values('longusername',data=>results.push(data)); timer();
const long=calls.at(-1);
collection.values('long',data=>results.push(data)); timer();
assert(long.aborted);assert.equal(calls.at(-1).query.q,'long');
console.log('PASS debounce, abort, stale response, minimum length, errors, escaped labels and large-group confirmation');
console.log('PASS input refresh on Backspace/Delete/cut, shorter query and server order');
