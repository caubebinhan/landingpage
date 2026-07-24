"use strict";var wp;(wp||={}).isShallowEqual=(()=>{var __defProp=Object.defineProperty;var __getOwnPropDesc=Object.getOwnPropertyDescriptor;var __getOwnPropNames=Object.getOwnPropertyNames;var __hasOwnProp=Object.prototype.hasOwnProperty;var __export=(target,all)=>{for(var name in all)
__defProp(target,name,{get:all[name],enumerable:true});};var __copyProps=(to,from,except,desc)=>{if(from&&typeof from==="object"||typeof from==="function"){for(let key of __getOwnPropNames(from))
if(!__hasOwnProp.call(to,key)&&key!==except)
__defProp(to,key,{get:()=>from[key],enumerable:!(desc=__getOwnPropDesc(from,key))||desc.enumerable});}
return to;};var __toCommonJS=(mod)=>__copyProps(__defProp({},"__esModule",{value:true}),mod);var index_exports={};__export(index_exports,{default:()=>isShallowEqual,isShallowEqual:()=>isShallowEqual,isShallowEqualArrays:()=>isShallowEqualArrays,isShallowEqualObjects:()=>isShallowEqualObjects});function isShallowEqualObjects(a,b){if(a===b){return true;}
const aKeys=Object.keys(a);const bKeys=Object.keys(b);if(aKeys.length!==bKeys.length){return false;}
let i=0;while(i<aKeys.length){const key=aKeys[i];const aValue=a[key];if(aValue===void 0&&!b.hasOwnProperty(key)||aValue!==b[key]){return false;}
i++;}
return true;}
function isShallowEqualArrays(a,b){if(a===b){return true;}
if(a.length!==b.length){return false;}
for(let i=0,len=a.length;i<len;i++){if(a[i]!==b[i]){return false;}}
return true;}
function isShallowEqual(a,b){if(a&&b){if(a.constructor===Object&&b.constructor===Object){return isShallowEqualObjects(a,b);}else if(Array.isArray(a)&&Array.isArray(b)){return isShallowEqualArrays(a,b);}}
return a===b;}
return __toCommonJS(index_exports);})();