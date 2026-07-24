"use strict";var wp;(wp||={}).tokenList=(()=>{var __defProp=Object.defineProperty;var __getOwnPropDesc=Object.getOwnPropertyDescriptor;var __getOwnPropNames=Object.getOwnPropertyNames;var __hasOwnProp=Object.prototype.hasOwnProperty;var __export=(target,all)=>{for(var name in all)
__defProp(target,name,{get:all[name],enumerable:true});};var __copyProps=(to,from,except,desc)=>{if(from&&typeof from==="object"||typeof from==="function"){for(let key of __getOwnPropNames(from))
if(!__hasOwnProp.call(to,key)&&key!==except)
__defProp(to,key,{get:()=>from[key],enumerable:!(desc=__getOwnPropDesc(from,key))||desc.enumerable});}
return to;};var __toCommonJS=(mod)=>__copyProps(__defProp({},"__esModule",{value:true}),mod);var index_exports={};__export(index_exports,{default:()=>TokenList});var TokenList=class{_currentValue;_valueAsArray;constructor(initialValue=""){this._currentValue="";this._valueAsArray=[];this.value=initialValue;}
entries(...args){return this._valueAsArray.entries(...args);}
forEach(...args){return this._valueAsArray.forEach(...args);}
keys(...args){return this._valueAsArray.keys(...args);}
values(...args){return this._valueAsArray.values(...args);}
get value(){return this._currentValue;}
set value(value){value=String(value);this._valueAsArray=[...new Set(value.split(/\s+/g).filter(Boolean))];this._currentValue=this._valueAsArray.join(" ");}
get length(){return this._valueAsArray.length;}
toString(){return this.value;}*[Symbol.iterator](){return yield*this._valueAsArray;}
item(index){return this._valueAsArray[index];}
contains(item){return this._valueAsArray.indexOf(item)!==-1;}
add(...items){this.value+=" "+items.join(" ");}
remove(...items){this.value=this._valueAsArray.filter((val)=>!items.includes(val)).join(" ");}
toggle(token,force){if(void 0===force){force=!this.contains(token);}
if(force){this.add(token);}else{this.remove(token);}
return force;}
replace(token,newToken){if(!this.contains(token)){return false;}
this.remove(token);this.add(newToken);return true;}
supports(_token){return true;}};return __toCommonJS(index_exports);})();if(typeof wp.tokenList==='object'&&wp.tokenList.default){wp.tokenList=wp.tokenList.default;}