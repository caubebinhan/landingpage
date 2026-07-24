/*!
 * jQuery UI Effects Scale 1.13.3
 * https://jqueryui.com
 *
 * Copyright OpenJS Foundation and other contributors
 * Released under the MIT license.
 * https://jquery.org/license
 */
(function(factory){"use strict";if(typeof define==="function"&&define.amd){define(["jquery","../version","../effect","./effect-size"],factory);}else{factory(jQuery);}})(function($){"use strict";return $.effects.define("scale",function(options,done){var el=$(this),mode=options.mode,percent=parseInt(options.percent,10)||(parseInt(options.percent,10)===0?0:(mode!=="effect"?0:100)),newOptions=$.extend(true,{from:$.effects.scaledDimensions(el),to:$.effects.scaledDimensions(el,percent,options.direction||"both"),origin:options.origin||["middle","center"]},options);if(options.fade){newOptions.from.opacity=1;newOptions.to.opacity=0;}
$.effects.effect.size.call(this,newOptions,done);});});