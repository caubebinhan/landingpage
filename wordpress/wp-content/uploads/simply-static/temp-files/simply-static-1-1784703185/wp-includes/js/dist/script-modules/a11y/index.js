function clear(){const regions=document.getElementsByClassName("a11y-speak-region");const introText=document.getElementById("a11y-speak-intro-text");for(let i=0;i<regions.length;i++){regions[i].textContent="";}
if(introText){introText.setAttribute("hidden","hidden");}}
var previousMessage="";function filterMessage(message){message=message.replace(/<[^<>]+>/g," ");if(previousMessage===message){message+="\xA0";}
previousMessage=message;return message;}
function speak(message,ariaLive){clear();message=filterMessage(message);const introText=document.getElementById("a11y-speak-intro-text");const containerAssertive=document.getElementById("a11y-speak-assertive");const containerPolite=document.getElementById("a11y-speak-polite");if(containerAssertive&&ariaLive==="assertive"){containerAssertive.textContent=message;}else if(containerPolite){containerPolite.textContent=message;}
if(introText){introText.removeAttribute("hidden");}}
var setup=()=>{};export{setup,speak};