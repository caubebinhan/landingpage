/*!
 * jQuery UI Effects Puff 1.13.3
 * https://jqueryui.com
 *
 * Copyright OpenJS Foundation and other contributors
 * Released under the MIT license.
 * https://jquery.org/license
 */
(function(factory){"use strict";if(typeof define==="function"&&define.amd){define(["jquery","../version","../effect","./effect-scale"],factory);}else{factory(jQuery);}})(function($){"use strict";return $.effects.define("puff","hide",function(options,done){var newOptions=$.extend(true,{},options,{fade:true,percent:parseInt(options.percent,10)||150});$.effects.effect.scale.call(this,newOptions,done);});});