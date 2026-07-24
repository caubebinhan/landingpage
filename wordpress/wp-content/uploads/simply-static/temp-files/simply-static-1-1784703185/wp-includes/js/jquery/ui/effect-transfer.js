/*!
 * jQuery UI Effects Transfer 1.13.3
 * https://jqueryui.com
 *
 * Copyright OpenJS Foundation and other contributors
 * Released under the MIT license.
 * https://jquery.org/license
 */
(function(factory){"use strict";if(typeof define==="function"&&define.amd){define(["jquery","../version","../effect"],factory);}else{factory(jQuery);}})(function($){"use strict";var effect;if($.uiBackCompat!==false){effect=$.effects.define("transfer",function(options,done){$(this).transfer(options,done);});}
return effect;});