tinymce.PluginManager.add('wpautoresize',function(editor){var settings=editor.settings,oldSize=300,isActive=false;if(editor.settings.inline||tinymce.Env.iOS){return;}
function isFullscreen(){return editor.plugins.fullscreen&&editor.plugins.fullscreen.isFullscreen();}
function getInt(n){return parseInt(n,10)||0;}
function resize(e){var deltaSize,doc,body,docElm,DOM=tinymce.DOM,resizeHeight,myHeight,marginTop,marginBottom,paddingTop,paddingBottom,borderTop,borderBottom;if(!isActive){return;}
doc=editor.getDoc();if(!doc){return;}
e=e||{};body=doc.body;docElm=doc.documentElement;resizeHeight=settings.autoresize_min_height;if(!body||(e&&e.type==='setcontent'&&e.initial)||isFullscreen()){if(body&&docElm){body.style.overflowY='auto';docElm.style.overflowY='auto';}
return;}
marginTop=editor.dom.getStyle(body,'margin-top',true);marginBottom=editor.dom.getStyle(body,'margin-bottom',true);paddingTop=editor.dom.getStyle(body,'padding-top',true);paddingBottom=editor.dom.getStyle(body,'padding-bottom',true);borderTop=editor.dom.getStyle(body,'border-top-width',true);borderBottom=editor.dom.getStyle(body,'border-bottom-width',true);myHeight=body.offsetHeight+getInt(marginTop)+getInt(marginBottom)+
getInt(paddingTop)+getInt(paddingBottom)+
getInt(borderTop)+getInt(borderBottom);if(myHeight&&myHeight<docElm.offsetHeight){myHeight=docElm.offsetHeight;}
if(isNaN(myHeight)||myHeight<=0){myHeight=tinymce.Env.ie?body.scrollHeight:(tinymce.Env.webkit&&body.clientHeight===0?0:body.offsetHeight);}
if(myHeight>settings.autoresize_min_height){resizeHeight=myHeight;}
if(settings.autoresize_max_height&&myHeight>settings.autoresize_max_height){resizeHeight=settings.autoresize_max_height;body.style.overflowY='auto';docElm.style.overflowY='auto';}else{body.style.overflowY='hidden';docElm.style.overflowY='hidden';body.scrollTop=0;}
if(resizeHeight!==oldSize){deltaSize=resizeHeight-oldSize;DOM.setStyle(editor.iframeElement,'height',resizeHeight+'px');oldSize=resizeHeight;if(tinymce.isWebKit&&deltaSize<0){resize(e);}
editor.fire('wp-autoresize',{height:resizeHeight,deltaHeight:e.type==='nodechange'?deltaSize:null});}}
function wait(times,interval,callback){setTimeout(function(){resize();if(times--){wait(times,interval,callback);}else if(callback){callback();}},interval);}
settings.autoresize_min_height=parseInt(editor.getParam('autoresize_min_height',editor.getElement().offsetHeight),10);settings.autoresize_max_height=parseInt(editor.getParam('autoresize_max_height',0),10);function on(){if(!editor.dom.hasClass(editor.getBody(),'wp-autoresize')){isActive=true;editor.dom.addClass(editor.getBody(),'wp-autoresize');editor.on('nodechange setcontent keyup FullscreenStateChanged',resize);resize();}}
function off(){var doc;if(!settings.wp_autoresize_on){isActive=false;doc=editor.getDoc();editor.dom.removeClass(editor.getBody(),'wp-autoresize');editor.off('nodechange setcontent keyup FullscreenStateChanged',resize);doc.body.style.overflowY='auto';doc.documentElement.style.overflowY='auto';oldSize=0;}}
if(settings.wp_autoresize_on){isActive=true;editor.on('init',function(){editor.dom.addClass(editor.getBody(),'wp-autoresize');});editor.on('nodechange keyup FullscreenStateChanged',resize);editor.on('setcontent',function(){wait(3,100);});if(editor.getParam('autoresize_on_init',true)){editor.on('init',function(){wait(10,200,function(){wait(5,1000);});});}}
editor.on('show',function(){oldSize=0;});editor.addCommand('wpAutoResize',resize);editor.addCommand('wpAutoResizeOn',on);editor.addCommand('wpAutoResizeOff',off);});