(function(){function contains(node){if(!(0 in arguments)){throw new TypeError('1 argument is required');}
do{if(this===node){return true;}}while(node=node&&node.parentNode);return false;}
if('HTMLElement'in self&&'contains'in HTMLElement.prototype){try{delete HTMLElement.prototype.contains;}catch(e){}}
if('Node'in self){Node.prototype.contains=contains;}else{document.contains=Element.prototype.contains=contains;}}());