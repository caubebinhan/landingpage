window.wpCookies={each:function(obj,cb,scope){var n,l;if(!obj){return 0;}
scope=scope||obj;if(typeof(obj.length)!=='undefined'){for(n=0,l=obj.length;n<l;n++){if(cb.call(scope,obj[n],n,obj)===false){return 0;}}}else{for(n in obj){if(obj.hasOwnProperty(n)){if(cb.call(scope,obj[n],n,obj)===false){return 0;}}}}
return 1;},getHash:function(name){var cookie=this.get(name),values;if(cookie){this.each(cookie.split('&'),function(pair){pair=pair.split('=');values=values||{};values[pair[0]]=pair[1];});}
return values;},setHash:function(name,values_obj,expires,path,domain,secure){var str='';this.each(values_obj,function(val,key){str+=(!str?'':'&')+key+'='+val;});this.set(name,str,expires,path,domain,secure);},get:function(name){var e,b,cookie=document.cookie,p=name+'=';if(!cookie){return;}
b=cookie.indexOf('; '+p);if(b===-1){b=cookie.indexOf(p);if(b!==0){return null;}}else{b+=2;}
e=cookie.indexOf(';',b);if(e===-1){e=cookie.length;}
return decodeURIComponent(cookie.substring(b+p.length,e));},set:function(name,value,expires,path,domain,secure){var d=new Date();if(typeof(expires)==='object'&&expires.toGMTString){expires=expires.toGMTString();}else if(parseInt(expires,10)){d.setTime(d.getTime()+(parseInt(expires,10)*1000));expires=d.toGMTString();}else{expires='';}
document.cookie=name+'='+encodeURIComponent(value)+
(expires?'; expires='+expires:'')+
(path?'; path='+path:'')+
(domain?'; domain='+domain:'')+
(secure?'; secure':'');},remove:function(name,path,domain,secure){this.set(name,'',-1000,path,domain,secure);}};window.getUserSetting=function(name,def){var settings=getAllUserSettings();if(settings.hasOwnProperty(name)){return settings[name];}
if(typeof def!=='undefined'){return def;}
return'';};window.setUserSetting=function(name,value,_del){if('object'!==typeof userSettings){return false;}
var uid=userSettings.uid,settings=wpCookies.getHash('wp-settings-'+uid),path=userSettings.url,secure=!!userSettings.secure;name=name.toString().replace(/[^A-Za-z0-9_-]/g,'');if(typeof value==='number'){value=parseInt(value,10);}else{value=value.toString().replace(/[^A-Za-z0-9_-]/g,'');}
settings=settings||{};if(_del){delete settings[name];}else{settings[name]=value;}
wpCookies.setHash('wp-settings-'+uid,settings,31536000,path,'',secure);wpCookies.set('wp-settings-time-'+uid,userSettings.time,31536000,path,'',secure);return name;};window.deleteUserSetting=function(name){return setUserSetting(name,'',1);};window.getAllUserSettings=function(){if('object'!==typeof userSettings){return{};}
return wpCookies.getHash('wp-settings-'+userSettings.uid)||{};};