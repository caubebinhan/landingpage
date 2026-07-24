(function(document,window,navigator){document.addEventListener('DOMContentLoaded',function(){var adminBar=document.getElementById('wpadminbar'),topMenuItems,allMenuItems,adminBarLogout,adminBarSearchForm,shortlink,skipLink,mobileEvent,adminBarSearchInput,i;if(!adminBar||!('querySelectorAll'in adminBar)){return;}
topMenuItems=adminBar.querySelectorAll('li.menupop');allMenuItems=adminBar.querySelectorAll('.ab-item');adminBarLogout=document.querySelector('#wp-admin-bar-logout a');adminBarSearchForm=document.getElementById('adminbarsearch');shortlink=document.getElementById('wp-admin-bar-get-shortlink');skipLink=adminBar.querySelector('.screen-reader-shortcut');mobileEvent=/Mobile\/.+Safari/.test(navigator.userAgent)?'touchstart':'click';removeClass(adminBar,'nojs');if('ontouchstart'in window){document.body.addEventListener(mobileEvent,function(e){if(!getClosest(e.target,'li.menupop')){removeAllHoverClass(topMenuItems);}});adminBar.addEventListener('touchstart',function bindMobileEvents(){for(var i=0;i<topMenuItems.length;i++){topMenuItems[i].addEventListener('click',mobileHover.bind(null,topMenuItems));}
adminBar.removeEventListener('touchstart',bindMobileEvents);});}
adminBar.addEventListener('click',scrollToTop);for(i=0;i<topMenuItems.length;i++){window.hoverintent(topMenuItems[i],addClass.bind(null,topMenuItems[i],'hover'),removeClass.bind(null,topMenuItems[i],'hover')).options({timeout:180});topMenuItems[i].addEventListener('keydown',toggleHoverIfEnter);}
for(i=0;i<allMenuItems.length;i++){allMenuItems[i].addEventListener('keydown',removeHoverIfEscape);}
if(adminBarSearchForm){adminBarSearchInput=document.getElementById('adminbar-search');adminBarSearchInput.addEventListener('focus',function(){addClass(adminBarSearchForm,'adminbar-focused');});adminBarSearchInput.addEventListener('blur',function(){removeClass(adminBarSearchForm,'adminbar-focused');});}
if(shortlink){shortlink.addEventListener('click',clickShortlink);}
if(window.location.hash){window.scrollBy(0,-32);}
if(adminBarLogout){adminBarLogout.addEventListener('click',emptySessionStorage);}});function removeHoverIfEscape(event){var wrapper;if(event.which!==27){return;}
wrapper=getClosest(event.target,'.menupop');if(!wrapper){return;}
wrapper.querySelector('.menupop > .ab-item').focus();removeClass(wrapper,'hover');}
function toggleHoverIfEnter(event){var wrapper;if(event.which!==13||event.ctrlKey||event.shiftKey){return;}
if(!!getClosest(event.target,'.ab-sub-wrapper')){return;}
wrapper=getClosest(event.target,'.menupop');if(!wrapper){return;}
event.preventDefault();if(hasClass(wrapper,'hover')){removeClass(wrapper,'hover');}else{addClass(wrapper,'hover');}}
function mobileHover(topMenuItems,event){var wrapper;if(!!getClosest(event.target,'.ab-sub-wrapper')){return;}
event.preventDefault();wrapper=getClosest(event.target,'.menupop');if(!wrapper){return;}
if(hasClass(wrapper,'hover')){removeClass(wrapper,'hover');}else{removeAllHoverClass(topMenuItems);addClass(wrapper,'hover');}}
function clickShortlink(event){var wrapper=event.target.parentNode,input;if(wrapper){input=wrapper.querySelector('.shortlink-input');}
if(!input){return;}
if(event.preventDefault){event.preventDefault();}
event.returnValue=false;addClass(wrapper,'selected');input.focus();input.select();input.onblur=function(){removeClass(wrapper,'selected');};return false;}
function emptySessionStorage(){if('sessionStorage'in window){try{for(var key in sessionStorage){if(key.indexOf('wp-autosave-')>-1){sessionStorage.removeItem(key);}}}catch(er){}}}
function hasClass(element,className){var classNames;if(!element){return false;}
if(element.classList&&element.classList.contains){return element.classList.contains(className);}else if(element.className){classNames=element.className.split(' ');return classNames.indexOf(className)>-1;}
return false;}
function addClass(element,className){if(!element){return;}
if(element.classList&&element.classList.add){element.classList.add(className);}else if(!hasClass(element,className)){if(element.className){element.className+=' ';}
element.className+=className;}
var menuItemToggle=element.querySelector('a');if(className==='hover'&&menuItemToggle&&menuItemToggle.hasAttribute('aria-expanded')){menuItemToggle.setAttribute('aria-expanded','true');}}
function removeClass(element,className){var testName,classes;if(!element||!hasClass(element,className)){return;}
if(element.classList&&element.classList.remove){element.classList.remove(className);}else{testName=' '+className+' ';classes=' '+element.className+' ';while(classes.indexOf(testName)>-1){classes=classes.replace(testName,'');}
element.className=classes.replace(/^[\s]+|[\s]+$/g,'');}
var menuItemToggle=element.querySelector('a');if(className==='hover'&&menuItemToggle&&menuItemToggle.hasAttribute('aria-expanded')){menuItemToggle.setAttribute('aria-expanded','false');}}
function removeAllHoverClass(topMenuItems){if(topMenuItems&&topMenuItems.length){for(var i=0;i<topMenuItems.length;i++){removeClass(topMenuItems[i],'hover');}}}
function scrollToTop(event){if(event.target&&event.target.id!=='wpadminbar'&&event.target.id!=='wp-admin-bar-top-secondary'){return;}
try{window.scrollTo({top:-32,left:0,behavior:'smooth'});}catch(er){window.scrollTo(0,-32);}}
function getClosest(el,selector){if(!window.Element.prototype.matches){window.Element.prototype.matches=window.Element.prototype.matchesSelector||window.Element.prototype.mozMatchesSelector||window.Element.prototype.msMatchesSelector||window.Element.prototype.oMatchesSelector||window.Element.prototype.webkitMatchesSelector||function(s){var matches=(this.document||this.ownerDocument).querySelectorAll(s),i=matches.length;while(--i>=0&&matches.item(i)!==this){}
return i>-1;};}
for(;el&&el!==document;el=el.parentNode){if(el.matches(selector)){return el;}}
return null;}})(document,window,navigator);