/*!
 * jQuery UI Effects Highlight 1.13.3
 * https://jqueryui.com
 *
 * Copyright OpenJS Foundation and other contributors
 * Released under the MIT license.
 * https://jquery.org/license
 */
(function(factory){"use strict";if(typeof define==="function"&&define.amd){define(["jquery","../version","../effect"],factory);}else{factory(jQuery);}})(function($){"use strict";return $.effects.define("highlight","show",function(options,done){var element=$(this),animation={backgroundColor:element.css("backgroundColor")};if(options.mode==="hide"){animation.opacity=0;}
$.effects.saveStyle(element);element.css({backgroundImage:"none",backgroundColor:options.color||"#ffff99"}).animate(animation,{queue:false,duration:options.duration,easing:options.easing,complete:done});});});