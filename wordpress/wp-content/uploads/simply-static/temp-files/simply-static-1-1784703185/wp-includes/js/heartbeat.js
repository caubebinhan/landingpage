(function($,window,undefined){var Heartbeat=function(){var $document=$(document),settings={suspend:false,suspendEnabled:true,screenId:'',url:'',lastTick:0,queue:{},mainInterval:60,tempInterval:0,originalInterval:0,minimalInterval:0,countdown:0,connecting:false,connectionError:false,errorcount:0,hasConnected:false,hasFocus:true,userActivity:0,userActivityEvents:false,checkFocusTimer:0,beatTimer:0};function initialize(){var options,hidden,visibilityState,visibilitychange;if(typeof window.pagenow==='string'){settings.screenId=window.pagenow;}
if(typeof window.ajaxurl==='string'){settings.url=window.ajaxurl;}
if(typeof window.heartbeatSettings==='object'){options=window.heartbeatSettings;if(!settings.url&&options.ajaxurl){settings.url=options.ajaxurl;}
if(options.interval){settings.mainInterval=options.interval;if(settings.mainInterval<1){settings.mainInterval=1;}else if(settings.mainInterval>3600){settings.mainInterval=3600;}}
if(options.minimalInterval){options.minimalInterval=parseInt(options.minimalInterval,10);settings.minimalInterval=options.minimalInterval>0&&options.minimalInterval<=600?options.minimalInterval:0;}
if(settings.minimalInterval&&settings.mainInterval<settings.minimalInterval){settings.mainInterval=settings.minimalInterval;}
if(!settings.screenId){settings.screenId=options.screenId||'front';}
if(options.suspension==='disable'){settings.suspendEnabled=false;}}
settings.mainInterval=settings.mainInterval*1000;settings.originalInterval=settings.mainInterval;if(settings.minimalInterval){settings.minimalInterval=settings.minimalInterval*1000;}
if(typeof document.hidden!=='undefined'){hidden='hidden';visibilitychange='visibilitychange';visibilityState='visibilityState';}else if(typeof document.msHidden!=='undefined'){hidden='msHidden';visibilitychange='msvisibilitychange';visibilityState='msVisibilityState';}else if(typeof document.webkitHidden!=='undefined'){hidden='webkitHidden';visibilitychange='webkitvisibilitychange';visibilityState='webkitVisibilityState';}
if(hidden){if(document[hidden]){settings.hasFocus=false;}
$document.on(visibilitychange+'.wp-heartbeat',function(){if(document[visibilityState]==='hidden'){blurred();window.clearInterval(settings.checkFocusTimer);}else{focused();if(document.hasFocus){settings.checkFocusTimer=window.setInterval(checkFocus,10000);}}});}
if(document.hasFocus){settings.checkFocusTimer=window.setInterval(checkFocus,10000);}
$(window).on('pagehide.wp-heartbeat',function(){suspend();if(settings.xhr&&settings.xhr.readyState!==4){settings.xhr.abort();}});$(window).on('pageshow.wp-heartbeat',function(event){if(event.originalEvent.persisted){focused();}});window.setInterval(checkUserActivity,30000);$(function(){settings.lastTick=time();scheduleNextTick();});}
function time(){return(new Date()).getTime();}
function isLocalFrame(frame){var origin,src=frame.src;if(src&&/^https?:\/\//.test(src)){origin=window.location.origin?window.location.origin:window.location.protocol+'//'+window.location.host;if(src.indexOf(origin)!==0){return false;}}
try{if(frame.contentWindow.document){return true;}}catch(e){}
return false;}
function checkFocus(){if(settings.hasFocus&&!document.hasFocus()){blurred();}else if(!settings.hasFocus&&document.hasFocus()){focused();}}
function setErrorState(error,status){var trigger;if(error){switch(error){case'abort':break;case'timeout':trigger=true;break;case'error':if(503===status&&settings.hasConnected){trigger=true;break;}
case'parsererror':case'empty':case'unknown':settings.errorcount++;if(settings.errorcount>2&&settings.hasConnected){trigger=true;}
break;}
if(trigger&&!hasConnectionError()){settings.connectionError=true;$document.trigger('heartbeat-connection-lost',[error,status]);wp.hooks.doAction('heartbeat.connection-lost',error,status);}}}
function clearErrorState(){settings.hasConnected=true;if(hasConnectionError()){settings.errorcount=0;settings.connectionError=false;$document.trigger('heartbeat-connection-restored');wp.hooks.doAction('heartbeat.connection-restored');}}
function connect(){var ajaxData,heartbeatData;if(settings.connecting||settings.suspend){return;}
settings.lastTick=time();heartbeatData=$.extend({},settings.queue);settings.queue={};$document.trigger('heartbeat-send',[heartbeatData]);wp.hooks.doAction('heartbeat.send',heartbeatData);ajaxData={data:heartbeatData,interval:settings.tempInterval?settings.tempInterval / 1000:settings.mainInterval / 1000,_nonce:typeof window.heartbeatSettings==='object'?window.heartbeatSettings.nonce:'',action:'heartbeat',screen_id:settings.screenId,has_focus:settings.hasFocus};if('customize'===settings.screenId){ajaxData.wp_customize='on';}
settings.connecting=true;settings.xhr=$.ajax({url:settings.url,type:'post',timeout:30000,data:ajaxData,dataType:'json'}).always(function(){settings.connecting=false;scheduleNextTick();}).done(function(response,textStatus,jqXHR){var newInterval;if(!response){setErrorState('empty');return;}
clearErrorState();if(response.nonces_expired){$document.trigger('heartbeat-nonces-expired');wp.hooks.doAction('heartbeat.nonces-expired');}
if(response.heartbeat_interval){newInterval=response.heartbeat_interval;delete response.heartbeat_interval;}
if(response.heartbeat_nonce&&typeof window.heartbeatSettings==='object'){window.heartbeatSettings.nonce=response.heartbeat_nonce;delete response.heartbeat_nonce;}
if(response.rest_nonce&&typeof window.wpApiSettings==='object'){window.wpApiSettings.nonce=response.rest_nonce;}
$document.trigger('heartbeat-tick',[response,textStatus,jqXHR]);wp.hooks.doAction('heartbeat.tick',response,textStatus,jqXHR);if(newInterval){interval(newInterval);}}).fail(function(jqXHR,textStatus,error){setErrorState(textStatus||'unknown',jqXHR.status);$document.trigger('heartbeat-error',[jqXHR,textStatus,error]);wp.hooks.doAction('heartbeat.error',jqXHR,textStatus,error);});}
function scheduleNextTick(){var delta=time()-settings.lastTick,interval=settings.mainInterval;if(settings.suspend){return;}
if(!settings.hasFocus){interval=120000;}else if(settings.countdown>0&&settings.tempInterval){interval=settings.tempInterval;settings.countdown--;if(settings.countdown<1){settings.tempInterval=0;}}
if(settings.minimalInterval&&interval<settings.minimalInterval){interval=settings.minimalInterval;}
window.clearTimeout(settings.beatTimer);if(delta<interval){settings.beatTimer=window.setTimeout(function(){connect();},interval-delta);}else{connect();}}
function blurred(){settings.hasFocus=false;}
function focused(){settings.userActivity=time();resume();if(!settings.hasFocus){settings.hasFocus=true;scheduleNextTick();}}
function suspend(){settings.suspend=true;}
function resume(){settings.suspend=false;}
function userIsActive(){settings.userActivityEvents=false;$document.off('.wp-heartbeat-active');$('iframe').each(function(i,frame){if(isLocalFrame(frame)){$(frame.contentWindow).off('.wp-heartbeat-active');}});focused();}
function checkUserActivity(){var lastActive=settings.userActivity?time()-settings.userActivity:0;if(lastActive>300000&&settings.hasFocus){blurred();}
if((settings.suspendEnabled&&lastActive>600000)||lastActive>3600000){suspend();}
if(!settings.userActivityEvents){$document.on('mouseover.wp-heartbeat-active keyup.wp-heartbeat-active touchend.wp-heartbeat-active',function(){userIsActive();});$('iframe').each(function(i,frame){if(isLocalFrame(frame)){$(frame.contentWindow).on('mouseover.wp-heartbeat-active keyup.wp-heartbeat-active touchend.wp-heartbeat-active',function(){userIsActive();});}});settings.userActivityEvents=true;}}
function hasFocus(){return settings.hasFocus;}
function hasConnectionError(){return settings.connectionError;}
function connectNow(){settings.lastTick=0;scheduleNextTick();}
function disableSuspend(){settings.suspendEnabled=false;}
function interval(speed,ticks){var newInterval,oldInterval=settings.tempInterval?settings.tempInterval:settings.mainInterval;if(speed){if('fast'===speed){newInterval=5000;}else if('long-polling'===speed){settings.mainInterval=0;return 0;}else{speed=parseInt(speed,10);if(speed>=1&&speed<=3600){newInterval=speed*1000;}else{newInterval=settings.originalInterval;}}
if(settings.minimalInterval&&newInterval<settings.minimalInterval){newInterval=settings.minimalInterval;}
if(5000===newInterval){ticks=parseInt(ticks,10)||30;ticks=ticks<1||ticks>30?30:ticks;settings.countdown=ticks;settings.tempInterval=newInterval;}else{settings.countdown=0;settings.tempInterval=0;settings.mainInterval=newInterval;}
if(newInterval!==oldInterval){scheduleNextTick();}}
return settings.tempInterval?settings.tempInterval / 1000:settings.mainInterval / 1000;}
function enqueue(handle,data,noOverwrite){if(handle){if(noOverwrite&&this.isQueued(handle)){return false;}
settings.queue[handle]=data;return true;}
return false;}
function isQueued(handle){if(handle){return settings.queue.hasOwnProperty(handle);}}
function dequeue(handle){if(handle){delete settings.queue[handle];}}
function getQueuedItem(handle){if(handle){return this.isQueued(handle)?settings.queue[handle]:undefined;}}
initialize();return{hasFocus:hasFocus,connectNow:connectNow,disableSuspend:disableSuspend,interval:interval,hasConnectionError:hasConnectionError,enqueue:enqueue,dequeue:dequeue,isQueued:isQueued,getQueuedItem:getQueuedItem};};window.wp=window.wp||{};window.wp.heartbeat=new Heartbeat();}(jQuery,window));