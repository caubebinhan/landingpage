window.autosave=function(){return true;};(function($,window){function autosave(){var initialCompareString,initialCompareData={},lastTriggerSave=0,$document=$(document);function setInitialCompare(){initialCompareData={post_title:$('#title').val()||'',content:$('#content').val()||'',excerpt:$('#excerpt').val()||''};initialCompareString=getCompareString(initialCompareData);}
function getPostData(type){var post_name,parent_id,data,time=(new Date()).getTime(),cats=[],editor=getEditor();if(editor&&editor.isDirty()&&!editor.isHidden()&&time-3000>lastTriggerSave){editor.save();lastTriggerSave=time;}
data={post_id:$('#post_ID').val()||0,post_type:$('#post_type').val()||'',post_author:$('#post_author').val()||'',post_title:$('#title').val()||'',content:$('#content').val()||'',excerpt:$('#excerpt').val()||''};if(type==='local'){return data;}
$('input[id^="in-category-"]:checked').each(function(){cats.push(this.value);});data.catslist=cats.join(',');if(post_name=$('#post_name').val()){data.post_name=post_name;}
if(parent_id=$('#parent_id').val()){data.parent_id=parent_id;}
if($('#comment_status').prop('checked')){data.comment_status='open';}
if($('#ping_status').prop('checked')){data.ping_status='open';}
if($('#auto_draft').val()==='1'){data.auto_draft='1';}
return data;}
function getCompareString(postData){if(typeof postData==='object'){return(postData.post_title||'')+'::'+(postData.content||'')+'::'+(postData.excerpt||'');}
return($('#title').val()||'')+'::'+($('#content').val()||'')+'::'+($('#excerpt').val()||'');}
function disableButtons(){$document.trigger('autosave-disable-buttons');setTimeout(enableButtons,5000);}
function enableButtons(){$document.trigger('autosave-enable-buttons');}
function getEditor(){return typeof tinymce!=='undefined'&&tinymce.get('content');}
function autosaveLocal(){var blog_id,post_id,hasStorage,intervalTimer,lastCompareString,isSuspended=false;function checkStorage(){var test=Math.random().toString(),result=false;try{window.sessionStorage.setItem('wp-test',test);result=window.sessionStorage.getItem('wp-test')===test;window.sessionStorage.removeItem('wp-test');}catch(e){}
hasStorage=result;return result;}
function getStorage(){var stored_obj=false;if(hasStorage&&blog_id){stored_obj=sessionStorage.getItem('wp-autosave-'+blog_id);if(stored_obj){stored_obj=JSON.parse(stored_obj);}else{stored_obj={};}}
return stored_obj;}
function setStorage(stored_obj){var key;if(hasStorage&&blog_id){key='wp-autosave-'+blog_id;sessionStorage.setItem(key,JSON.stringify(stored_obj));return sessionStorage.getItem(key)!==null;}
return false;}
function getSavedPostData(){var stored=getStorage();if(!stored||!post_id){return false;}
return stored['post_'+post_id]||false;}
function setData(stored_data){var stored=getStorage();if(!stored||!post_id){return false;}
if(stored_data){stored['post_'+post_id]=stored_data;}else if(stored.hasOwnProperty('post_'+post_id)){delete stored['post_'+post_id];}else{return false;}
return setStorage(stored);}
function suspend(){isSuspended=true;}
function resume(){isSuspended=false;}
function save(data){var postData,compareString,result=false;if(isSuspended||!hasStorage){return false;}
if(data){postData=getSavedPostData()||{};$.extend(postData,data);}else{postData=getPostData('local');}
compareString=getCompareString(postData);if(typeof lastCompareString==='undefined'){lastCompareString=initialCompareString;}
if(compareString===lastCompareString){return false;}
postData.save_time=(new Date()).getTime();postData.status=$('#post_status').val()||'';result=setData(postData);if(result){lastCompareString=compareString;}
return result;}
function run(){post_id=$('#post_ID').val()||0;if($('#wp-content-wrap').hasClass('tmce-active')){$document.on('tinymce-editor-init.autosave',function(){window.setTimeout(function(){checkPost();},1500);});}else{checkPost();}
intervalTimer=window.setInterval(save,15000);$('form#post').on('submit.autosave-local',function(){var editor=getEditor(),post_id=$('#post_ID').val()||0;if(editor&&!editor.isHidden()){editor.on('submit',function(){save({post_title:$('#title').val()||'',content:$('#content').val()||'',excerpt:$('#excerpt').val()||''});});}else{save({post_title:$('#title').val()||'',content:$('#content').val()||'',excerpt:$('#excerpt').val()||''});}
var secure=('https:'===window.location.protocol);wpCookies.set('wp-saving-post',post_id+'-check',24*60*60,false,false,secure);});}
function compare(str1,str2){function removeSpaces(string){return string.toString().replace(/[\x20\t\r\n\f]+/g,'');}
return(removeSpaces(str1||'')===removeSpaces(str2||''));}
function checkPost(){var content,post_title,excerpt,$notice,postData=getSavedPostData(),cookie=wpCookies.get('wp-saving-post'),$newerAutosaveNotice=$('#has-newer-autosave').parent('.notice'),$headerEnd=$('.wp-header-end');if(cookie===post_id+'-saved'){wpCookies.remove('wp-saving-post');setData(false);return;}
if(!postData){return;}
content=$('#content').val()||'';post_title=$('#title').val()||'';excerpt=$('#excerpt').val()||'';if(compare(content,postData.content)&&compare(post_title,postData.post_title)&&compare(excerpt,postData.excerpt)){return;}
if(!$headerEnd.length){$headerEnd=$('.wrap h1, .wrap h2').first();}
$notice=$('#local-storage-notice').insertAfter($headerEnd).addClass('notice-warning');if($newerAutosaveNotice.length){$newerAutosaveNotice.slideUp(150,function(){$notice.slideDown(150);});}else{$notice.slideDown(200);}
$notice.find('.restore-backup').on('click.autosave-local',function(){restorePost(postData);$notice.fadeTo(250,0,function(){$notice.slideUp(150);});});}
function restorePost(postData){var editor;if(postData){lastCompareString=getCompareString(postData);if($('#title').val()!==postData.post_title){$('#title').trigger('focus').val(postData.post_title||'');}
$('#excerpt').val(postData.excerpt||'');editor=getEditor();if(editor&&!editor.isHidden()&&typeof switchEditors!=='undefined'){if(editor.settings.wpautop&&postData.content){postData.content=switchEditors.wpautop(postData.content);}
editor.undoManager.transact(function(){editor.setContent(postData.content||'');editor.nodeChanged();});}else{$('#content-html').trigger('click');$('#content').trigger('focus');document.execCommand('selectAll');document.execCommand('insertText',false,postData.content||'');}
return true;}
return false;}
blog_id=typeof window.autosaveL10n!=='undefined'&&window.autosaveL10n.blog_id;if(checkStorage()&&blog_id&&($('#content').length||$('#excerpt').length)){$(run);}
return{hasStorage:hasStorage,getSavedPostData:getSavedPostData,save:save,suspend:suspend,resume:resume};}
function autosaveServer(){var _blockSave,_blockSaveTimer,previousCompareString,lastCompareString,nextRun=0,isSuspended=false;function tempBlockSave(){_blockSave=true;window.clearTimeout(_blockSaveTimer);_blockSaveTimer=window.setTimeout(function(){_blockSave=false;},10000);}
function suspend(){isSuspended=true;}
function resume(){isSuspended=false;}
function response(data){_schedule();_blockSave=false;lastCompareString=previousCompareString;previousCompareString='';$document.trigger('after-autosave',[data]);enableButtons();if(data.success){$('#auto_draft').val('');}}
function triggerSave(){nextRun=0;wp.heartbeat.connectNow();}
function postChanged(){var changed=false;if(window.tinymce){window.tinymce.each(['content','excerpt'],function(field){var editor=window.tinymce.get(field);if(!editor||editor.isHidden()){if(($('#'+field).val()||'')!==initialCompareData[field]){changed=true;return false;}}else if(editor.isDirty()){changed=true;return false;}});if(($('#title').val()||'')!==initialCompareData.post_title){changed=true;}
return changed;}
return getCompareString()!==initialCompareString;}
function save(){var postData,compareString;if(isSuspended||_blockSave||!window.autosave()){return false;}
if((new Date()).getTime()<nextRun){return false;}
postData=getPostData();compareString=getCompareString(postData);if(typeof lastCompareString==='undefined'){lastCompareString=initialCompareString;}
if(compareString===lastCompareString){return false;}
previousCompareString=compareString;tempBlockSave();disableButtons();$document.trigger('wpcountwords',[postData.content]).trigger('before-autosave',[postData]);postData._wpnonce=$('#_wpnonce').val()||'';return postData;}
function _schedule(){nextRun=(new Date()).getTime()+(autosaveL10n.autosaveInterval*1000)||60000;}
$(function(){_schedule();}).on('heartbeat-send.autosave',function(event,data){var autosaveData=save();if(autosaveData){data.wp_autosave=autosaveData;}}).on('heartbeat-tick.autosave',function(event,data){if(data.wp_autosave){response(data.wp_autosave);}}).on('heartbeat-connection-lost.autosave',function(event,error,status){if('timeout'===error||603===status){var $notice=$('#lost-connection-notice');if(!wp.autosave.local.hasStorage){$notice.find('.hide-if-no-sessionstorage').hide();}
$notice.show();disableButtons();}}).on('heartbeat-connection-restored.autosave',function(){$('#lost-connection-notice').hide();enableButtons();});return{tempBlockSave:tempBlockSave,triggerSave:triggerSave,postChanged:postChanged,suspend:suspend,resume:resume};}
$(function(){setInitialCompare();}).on('tinymce-editor-init.autosave',function(event,editor){if('content'===editor.id||'excerpt'===editor.id){window.setTimeout(function(){editor.save();setInitialCompare();},1000);}});return{getPostData:getPostData,getCompareString:getCompareString,disableButtons:disableButtons,enableButtons:enableButtons,local:autosaveLocal(),server:autosaveServer()};}
window.wp=window.wp||{};window.wp.autosave=autosave();}(jQuery,window));