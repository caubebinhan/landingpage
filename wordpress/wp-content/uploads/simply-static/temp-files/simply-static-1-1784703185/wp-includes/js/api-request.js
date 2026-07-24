(function($){var wpApiSettings=window.wpApiSettings;function apiRequest(options){options=apiRequest.buildAjaxOptions(options);return apiRequest.transport(options);}
apiRequest.buildAjaxOptions=function(options){var url=options.url;var path=options.path;var method=options.method;var namespaceTrimmed,endpointTrimmed,apiRoot;var headers,addNonceHeader,addAcceptHeader,headerName;if(typeof options.namespace==='string'&&typeof options.endpoint==='string'){namespaceTrimmed=options.namespace.replace(/^\/|\/$/g,'');endpointTrimmed=options.endpoint.replace(/^\//,'');if(endpointTrimmed){path=namespaceTrimmed+'/'+endpointTrimmed;}else{path=namespaceTrimmed;}}
if(typeof path==='string'){apiRoot=wpApiSettings.root;path=path.replace(/^\//,'');if('string'===typeof apiRoot&&-1!==apiRoot.indexOf('?')){path=path.replace('?','&');}
url=apiRoot+path;}
addNonceHeader=!(options.data&&options.data._wpnonce);addAcceptHeader=true;headers=options.headers||{};for(headerName in headers){if(!headers.hasOwnProperty(headerName)){continue;}
switch(headerName.toLowerCase()){case'x-wp-nonce':addNonceHeader=false;break;case'accept':addAcceptHeader=false;break;}}
if(addNonceHeader){headers=$.extend({'X-WP-Nonce':wpApiSettings.nonce},headers);}
if(addAcceptHeader){headers=$.extend({'Accept':'application/json, */*;q=0.1'},headers);}
if(typeof method==='string'){method=method.toUpperCase();if('PUT'===method||'DELETE'===method){headers=$.extend({'X-HTTP-Method-Override':method},headers);method='POST';}}
options=$.extend({},options,{headers:headers,url:url,method:method});delete options.path;delete options.namespace;delete options.endpoint;return options;};apiRequest.transport=$.ajax;window.wp=window.wp||{};window.wp.apiRequest=apiRequest;})(jQuery);