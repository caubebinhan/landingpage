"use strict";var wp;(wp||={}).wordcount=(()=>{var __defProp=Object.defineProperty;var __getOwnPropDesc=Object.getOwnPropertyDescriptor;var __getOwnPropNames=Object.getOwnPropertyNames;var __hasOwnProp=Object.prototype.hasOwnProperty;var __export=(target,all)=>{for(var name in all)
__defProp(target,name,{get:all[name],enumerable:true});};var __copyProps=(to,from,except,desc)=>{if(from&&typeof from==="object"||typeof from==="function"){for(let key of __getOwnPropNames(from))
if(!__hasOwnProp.call(to,key)&&key!==except)
__defProp(to,key,{get:()=>from[key],enumerable:!(desc=__getOwnPropDesc(from,key))||desc.enumerable});}
return to;};var __toCommonJS=(mod)=>__copyProps(__defProp({},"__esModule",{value:true}),mod);var index_exports={};__export(index_exports,{count:()=>count});var defaultSettings={HTMLRegExp:/<\/?[a-z][^>]*?>/gi,HTMLcommentRegExp:/<!--[\s\S]*?-->/g,spaceRegExp:/&nbsp;|&#160;/gi,HTMLEntityRegExp:/&\S+?;/g,connectorRegExp:/--|\u2014/g,removeRegExp:new RegExp(["[","!-/:-@[-`{-~","\x80-\xBF\xD7\xF7","\u2000-\u2BFF","\u2E00-\u2E7F","]"].join(""),"g"),astralRegExp:/[\uD800-\uDBFF][\uDC00-\uDFFF]/g,wordsRegExp:/\S\s+/g,characters_excluding_spacesRegExp:/\S/g,characters_including_spacesRegExp:/[^\f\n\r\t\v\u00AD\u2028\u2029]/g,l10n:{type:"words"}};function stripTags(settings,text){return text.replace(settings.HTMLRegExp,"\n");}
function transposeAstralsToCountableChar(settings,text){return text.replace(settings.astralRegExp,"a");}
function stripHTMLEntities(settings,text){return text.replace(settings.HTMLEntityRegExp,"");}
function stripConnectors(settings,text){return text.replace(settings.connectorRegExp," ");}
function stripRemovables(settings,text){return text.replace(settings.removeRegExp,"");}
function stripHTMLComments(settings,text){return text.replace(settings.HTMLcommentRegExp,"");}
function stripShortcodes(settings,text){if(settings.shortcodesRegExp){return text.replace(settings.shortcodesRegExp,"\n");}
return text;}
function stripSpaces(settings,text){return text.replace(settings.spaceRegExp," ");}
function transposeHTMLEntitiesToCountableChars(settings,text){return text.replace(settings.HTMLEntityRegExp,"a");}
function loadSettings(type="words",userSettings={}){const mergedSettings={...defaultSettings,...userSettings};const settings={...mergedSettings,type,shortcodes:[]};settings.shortcodes=settings.l10n?.shortcodes??[];if(settings.shortcodes&&settings.shortcodes.length){settings.shortcodesRegExp=new RegExp("\\[\\/?(?:"+settings.shortcodes.join("|")+")[^\\]]*?\\]","g");}
if(settings.type!=="characters_excluding_spaces"&&settings.type!=="characters_including_spaces"){settings.type="words";}
return settings;}
function countWords(text,regex,settings){text=[stripTags.bind(null,settings),stripHTMLComments.bind(null,settings),stripShortcodes.bind(null,settings),stripSpaces.bind(null,settings),stripHTMLEntities.bind(null,settings),stripConnectors.bind(null,settings),stripRemovables.bind(null,settings)].reduce((result,fn)=>fn(result),text);text=text+"\n";return text.match(regex)?.length??0;}
function countCharacters(text,regex,settings){text=[stripTags.bind(null,settings),stripHTMLComments.bind(null,settings),stripShortcodes.bind(null,settings),transposeAstralsToCountableChar.bind(null,settings),stripSpaces.bind(null,settings),transposeHTMLEntitiesToCountableChars.bind(null,settings)].reduce((result,fn)=>fn(result),text);text=text+"\n";return text.match(regex)?.length??0;}
function count(text,type,userSettings){const settings=loadSettings(type,userSettings);let matchRegExp;switch(settings.type){case"words":matchRegExp=settings.wordsRegExp;return countWords(text,matchRegExp,settings);case"characters_including_spaces":matchRegExp=settings.characters_including_spacesRegExp;return countCharacters(text,matchRegExp,settings);case"characters_excluding_spaces":matchRegExp=settings.characters_excluding_spacesRegExp;return countCharacters(text,matchRegExp,settings);default:return 0;}}
return __toCommonJS(index_exports);})();