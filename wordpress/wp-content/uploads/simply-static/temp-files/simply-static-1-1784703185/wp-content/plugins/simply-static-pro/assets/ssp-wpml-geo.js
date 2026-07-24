function WPML_Integration(){var languages={};var self=this;this.start=function start(){languages=this.getLanguagesInSwitcher();this.registerSwitcher();this.switchToClientLanguage();}
this.redirectTo=function redirectTo(href){window.location.replace(href);}
this.getStoredLanguage=function getStoredLanguage(){return this.getCookie('simply_static_lang');}
this.storeLanguage=function storeLanguage(lang){return this.setCookie('simply_static_lang',lang,30);}
this.getCurrentPageLanguage=function getCurrentPageLanguage(){var lang=this.getLanguageFromHref(window.location.href);if(!lang){var htmlLang=document.getElementsByTagName('html')[0].getAttribute('lang');if(htmlLang){lang=htmlLang.substring(0,2);}}
return lang}
this.switchToClientLanguage=function switchToClientLanguage(){if(Object.keys(languages).length<1){return false;}
let current_language=navigator.language.substring(0,2);if(this.getStoredLanguage()){if(this.getStoredLanguage()!==this.getCurrentPageLanguage()){var href=languages[this.getStoredLanguage()]||'';if(!href){return;}
this.redirectTo(href);}
return;}
for(const[key,value]of Object.entries(languages)){if(key===current_language){this.redirectToNewLanguage(key,value);}}}
this.redirectToNewLanguage=function redirectToNewLanguage(lang,href){this.storeLanguage(lang);this.redirectTo(href);}
this.registerSwitcher=function registerSwitcher(){var links=document.getElementsByClassName('wpml-ls-link');for(var l=0;l<links.length;l++){var link=links[l];link.addEventListener('click',this.maybeSwitchLanguage);}}
this.getLanguageFromHref=function getLanguageFromHref(href){if(Object.keys(languages).length<1){return this.getCurrentLanguage();}
for(var lang in languages){var langHref=languages[lang];if(href===langHref){return lang;}}
return'';}
this.maybeSwitchLanguage=function maybeSwitchLanguage(event){var link=null;if(!event.target.classList.contains('wpml-ls-link')){link=event.target.parentElement;}else{link=event.target;}
var href=link.getAttribute('href');var lang=self.getLanguageFromHref(href);if(!lang){return true;}
if(lang===self.getCurrentLanguage()){return true;}
self.storeLanguage(lang);}
this.getLanguagesInSwitcher=function getLanguagesInSwitcher(){let languages_links={};let languages=document.getElementsByClassName('wpml-ls-native');for(const language of languages){let language_tag=language.getAttribute('lang');let language_href=language.parentElement.getAttribute('href');if(!language_href){language_href=language.parentElement.parentElement.getAttribute('href');}
if(language_tag){if(language_tag.includes("-")){let parts=language_tag.split('-');languages_links[language_tag]=parts[0];}else{languages_links[language_tag]=language_href;}}}
return languages_links;}
this.setCookie=function setCookie(cname,cvalue,exdays){const d=new Date();d.setTime(d.getTime()+(exdays*24*60*60*1000));let expires="expires="+d.toUTCString();document.cookie=cname+"="+cvalue+";"+expires+";path=/";}
this.getCookie=function getCookie(cname){let name=cname+"=";let ca=document.cookie.split(';');for(let i=0;i<ca.length;i++){let c=ca[i];while(c.charAt(0)==' '){c=c.substring(1);}
if(c.indexOf(name)==0){return c.substring(name.length,c.length);}}
return"";}
this.getCurrentLanguage=function getCurrentLanguage(){return this.getStoredLanguage();}
this.start();return this;}
window.simply_static_wpml=new WPML_Integration();