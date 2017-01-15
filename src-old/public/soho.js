/**
 * JavaScript application for SoHo
 * $Id: core.css,v 3.X 2012/07/23 19:20:54 evolya Exp $
 */
WG=(function(){var g={wg_appName:"SoHo",wg_appVersion:"3.0",wg_url:"/",wg_lastUpdate:0,wg_serverTimeOffset:null,wg_updateDelay:30000,wg_sessionAge:0,wg_autolockDelay:0,wg_started:false};var d={wg_logged:false,wg_login:null,wg_name:null,wg_avatar:null,wg_pwdhash:null};viewdata={available:{},loaded:{}};uidata={lastAction:0,autolockThread:null,trayIcons:{},uiComponents:null};var c=function(){if(WG.live){WG.live.stop();WG.live=null}};var b=function(){if(!WG.live){WG.live=new WG.LiveService()}};var a=function(k){if(console.log){console.log("[start] Open user session...")}d.wg_logged=true;d.wg_login=k.sessiondata.userLogin;d.wg_name=k.sessiondata.userName;d.wg_avatar=k.sessiondata.userAvatar;d.wg_pwdhash=k.sessiondata.userPwdHash;g.wg_lastUpdate=k.sessiondata.serverTime;g.wg_sessionAge=k.sessiondata.sessionAge;g.wg_autolockDelay=k.settings.autolock*60000;g.wg_serverTimeOffset=(1*k.time.serverGMT);var i=document.createElement("link");i.type="text/css";i.rel="stylesheet";i.href=g.wg_url+"css.php?t="+new Date().getTime();var j=document.getElementsByTagName("link")[0];j.parentNode.insertBefore(i,j);if(console){console.log("[start] Load modules CSS stylesheet ("+g.wg_url+"css.php)")}if(localStorage){if(localStorage.getItem("WG.nightmode")==="on"){document.body.setAttribute("nightmode","on")}}viewdata.available=k.views;WG.ui.createMenu(k.menu);WG.ui.createTrayIcons();WG.ui.addTrayMenuItem("nightmode","Switch night mode...",WG.ui.TrayMenuStack.TOP,function(){var l=$("body#wg");if(l.hasAttr("nightmode")){l.removeAttr("nightmode");if(localStorage){localStorage.setItem("WG.nightmode","off")}}else{l.attr("nightmode","on");if(localStorage){localStorage.setItem("WG.nightmode","on")}}});WG.ui.addTrayMenuItem("lock","Lock my session...",WG.ui.TrayMenuStack.BOTTOM,function(){lock()});WG.ui.addTrayMenuItem("logout","Log out...",WG.ui.TrayMenuStack.BOTTOM,function(){logout()});WG.View.applyStandardBehavior(uidata.uiComponents.main);if(g.wg_autolockDelay>0){uidata.autolockThread=setInterval(function(){if(new Date().getTime()-uidata.lastAction>g.wg_autolockDelay){lock()}},30000)}b();if(localStorage&&localStorage.getItem("WG.defaultView")){WG.setView(localStorage.getItem("WG.defaultView"))}else{WG.setView("dashboard")}uidata.lastAction=new Date().getTime()};var f=function(){g={wg_lastUpdate:-1,wg_updateDelay:-1,wg_sessionAge:-1,wg_started:true};d={wg_logged:false,wg_login:null,wg_name:null,wg_avatar:null,wg_pwdhash:null};viewdata={available:{},loaded:{}};c();WG.ui.removeAllTrayMenuItems();WG.ui.destroyMenu();WG.ui.removeAllTrayIcons();WG.ui.removeCurrentView();WG.ui.viewHistory={};if(uidata.autolockThread!==null){clearInterval(uidata.autolockThread);uidata.autolockThread=null}uidata.uiComponents.appName.setAttribute("class","");if(localStorage){localStorage.removeItem("WG.lock");localStorage.removeItem("WG.defaultView")}};logout=function(){if(!d.wg_logged){WG.setStatus("You are not logged.",WG.status.ALERT,"close");return}WG.setStatus("Logout...",WG.status.WAIT);var i=g.wg_url;f();WG.ajax({url:i+"ws.php",data:{w:"auth",logout:"please"},success:function(){setTimeout("window.location.reload()",1600);WG.setStatus("You are logged out.",WG.status.SUCCESS)},error:function(k,l,j){setTimeout("window.location.reload()",10);if(l!="success"){WG.setStatus("Unable to log out: "+l,WG.status.FAILURE)}}})};lock=function(){if(!d.wg_logged){return}if(uidata.uiComponents.locker.style.display=="block"){return}if(console){console.log("Lock")}uidata.uiComponents.locker.innerHTML="<h1>"+WG.util.htmlspecialchars(g.wg_appName)+"</h1><p>This session is locked by <b>"+WG.util.htmlspecialchars(d.wg_name)+"</b><br />Enter your password</p>";var i=document.createElement("input");i.setAttribute("type","password");i.onkeydown=function(){this.style.backgroundColor="#fff"};i.onblur=function(){this.focus()};var j=document.createElement("form");j.onsubmit=function(){if(WG.security.sha1(d.wg_login+":"+i.value).substr(0,15)===d.wg_pwdhash){unlock()}else{i.value="";i.style.backgroundColor="#666"}return false};j.appendChild(i);uidata.uiComponents.locker.appendChild(j);uidata.uiComponents.locker.style.display="block";i.focus();if(localStorage){localStorage.setItem("WG.lock",true)}};unlock=function(){if(console){console.log("Unlock")}uidata.uiComponents.locker.style.display="none";if(localStorage){localStorage.removeItem("WG.lock")}uidata.lastAction=new Date().getTime()};welcome=function(l,i){if(console){console.log("[start] Ask for welcome message...")}WG.setStatus("Get welcome message...",WG.status.WAIT);var j=function(m,o,n){if(console){console.error("[start] Unable to get welcome message. This attempt has been logged.")}WG.setStatus("Unable to reach welcome service: <em>"+n+"</em>",WG.status.FAILURE,function(){WG.setView("login",null,true)});if(i){i()}};var k=function(m){WG.setStatus("Open session...",WG.status.WAIT);a(m);WG.setStatus(null);if(l){l()}};WG.ajax({url:g.wg_url+"ws.php",data:{w:"welcome"},success:k,error:j})};var h=function(){if(g.wg_started){throw"WG is allready started"}if(console){console.log("[start] WG is starting, appURL is: "+g.wg_url)}WG.trigger("beforeStart");document.body.setAttribute("id","wg");uidata.uiComponents=WG.ui.createUIComponents();document.body.appendChild(uidata.uiComponents.container);WG.ui.bindEvents();g.started=true;if(!d.wg_logged){if(console){console.log("[start] Start is finished. User is not logged: display login view.")}WG.setView("login",null,true)}else{if(console){console.log("[start] Start is finished. User is allready logged: get welcome message.")}welcome()}};return{init:function(i){if($("body").attr("id")=="wg"){return false}if(console){console.log("[start] Initialization...")}if("appName" in i){document.title=g.wg_appName=i.appName}if("appVersion" in i){g.wg_appVersion=i.appVersion}if("appUrl" in i){g.wg_url=i.appUrl}if("lastUpdate" in i){g.wg_lastUpdate=parseInt(i.lastUpdate)}if("updateDelay" in i){g.wg_updateDelay=parseInt(i.updateDelay);if(console){console.warn("[deprecated] Usage of appdata.wg_updateDelay is deprecated")}}if("sessionAge" in i){g.wg_sessionAge=parseInt(i.sessionAge);if(console){console.warn("[deprecated] Usage of appdata.wg_sessionAge is deprecated")}}if("logged" in i){d.wg_logged=(i.logged===true)}$("body .nojs").remove();h()},appName:function(){return g.wg_appName},appURL:function(){return g.wg_url},userName:function(){return d.wg_name},userAvatar:function(){return d.wg_avatar},lastUpdate:function(){return g.wg_lastUpdate},live:null,trim:jQuery.trim,isLogged:function(){return d.wg_logged},setView:function(j,l,i,k){WG.ui.setView(j,l,i,k);return WG},currentView:function(){return WG.ui.currentView},ajax:function(l,k){var i=WG.security.AES!=null;var j="dataType" in l?l.dataType:"json";var m={cache:(k===true),url:l.url,dataType:i?"text":j,type:"POST",success:function(o,q,n){if(WG.security.AES!=null){o=WG.security.aesDecrypt(o);if(j=="json"){try{o=JSON.parse(o)}catch(p){if("error" in l){l.error(n,q,"invalid JSON after decrypt")}return}}}if("success" in l){l.success(o,q,n)}},error:function(n,p,o){if("error" in l){l.error(n,p,o)}}};if("data" in l){if(i){for(name in l.data){l.data[name]=$.jCryption.encrypt(""+l.data[name],WG.security.AES.password)}}m.data=l.data}if("context" in l){m.context=l.context}if(console){console.log("[ajax] POST "+l.url+" (AES="+(i?"on":"off")+", type="+j+", cache="+(k===true)+")")}return jQuery.ajax(m)},status:{WAIT:0,OK:1,SUCCESS:1,INFO:5,NOTICE:5,ALERT:10,WARNING:10,ERROR:20,FAILURE:20},setStatus:function(o,j,m,n){var l=uidata.uiComponents.status;if(!o){l.style.display="none";return this}l.style.display="block";var k="";switch(j){case WG.status.WAIT:k="wait-msg";break;case WG.status.SUCCESS:k="success-msg";break;case WG.status.INFO:case WG.status.NOTICE:k="info-msg";break;case WG.status.ALERT:case WG.status.WARNING:k="alert-msg";break;case WG.status.ERROR:case WG.status.FAILURE:k="failure-msg";break}l.setAttribute("class",k);l.innerHTML=o;if(m){if(m=="close"){n="close";m=function(){WG.setStatus(null)}}else{if(!n){n="continue"}}var i=document.createElement("a");i.innerHTML=n;i.onclick=m;i.setAttribute("class","continue");i.setAttribute("href","javascript:;");l.appendChild(i);i.focus()}return WG},time:{getLocalTime:function(){return new Date()},getServerOffset:function(){return g.wg_serverTimeOffset},getServerTime:function(){if(g.wg_serverTimezone===null){return new Date()}var k=new Date(),m=k.getTimezoneOffset(),j=g.wg_serverTimeOffset,l=(j/100*-1)*60,n=(m-l)*60000;var i=j<360?k.getTime()+n:k.getTime()-n;return new Date(i)}}}})();WG.util={each:function(a,b){if(!a){return}if(a instanceof Object){for(v in a){b(a[v],v)}}},createElement:function(d,a,c){var b=document.createElement(d);if(a){for(attr in a){if(attr=="onclick"){b.onclick=a[attr]}else{b.setAttribute(attr,a[attr])}}}if(c){c.appendChild(b)}return b},implementListenerPattern:function(a){a.bind=function(c,d,b){if(!this.listeners){this.listeners=[]}this.listeners.push({e:c,c:d,o:b===true});return this};a.one=function(b,c){return this.bind(b,c,true)};a.unbind=function(f,g){if(!this.listeners){return false}if(f&&g){for(var d=0,c=this.listeners.length;d<c;d++){var b=this.listeners[d];if(!b){continue}if((b.e===f||f==="*")&&b.c===g){this.listeners.splice(d,1);return true}}}else{if(f){for(var d=0,c=this.listeners.length;d<c;d++){var b=this.listeners[d];if(!b){continue}if(b.e===f||f==="*"){this.listeners.splice(d,1);return true}}}else{this.listeners=[];return true}}return false};a.trigger=function(f,g){if(!this.listeners){return this}for(var d=0,c=this.listeners.length;d<c;d++){var b=this.listeners[d];if(!b){continue}if(b.e===f||b.e==="*"){this.eventDispath=b.c;this.eventDispath(g,this,f);if(b.o){this.listeners.splice(d,1)}}}this.eventDispath=null;return this}},htmlspecialchars:function(c,j,h,b){var f=0,d=0,g=false;if(typeof j==="undefined"||j===null){j=2}c=c.toString();if(b!==false){c=c.replace(/&/g,"&amp;")}c=c.replace(/</g,"&lt;").replace(/>/g,"&gt;");var a={ENT_NOQUOTES:0,ENT_HTML_QUOTE_SINGLE:1,ENT_HTML_QUOTE_DOUBLE:2,ENT_COMPAT:2,ENT_QUOTES:3,ENT_IGNORE:4};if(j===0){g=true}if(typeof j!=="number"){j=[].concat(j);for(d=0;d<j.length;d++){if(a[j[d]]===0){g=true}else{if(a[j[d]]){f=f|a[j[d]]}}}j=f}if(j&a.ENT_HTML_QUOTE_SINGLE){c=c.replace(/'/g,"&#039;")}if(!g){c=c.replace(/"/g,"&quot;")}return c},nl2br:function(c,b){var a=(b||typeof b==="undefined")?"<br />":"<br>";return(c+"").replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g,"$1"+a+"$2")},str_replace:function(u,c,n,m){var h=0,g=0,q="",l="",d=0,p=0,k=[].concat(u),a=[].concat(c),t=n,b=Object.prototype.toString.call(a)==="[object Array]",o=Object.prototype.toString.call(t)==="[object Array]";t=[].concat(t);if(m){this.window[m]=0}for(h=0,d=t.length;h<d;h++){if(t[h]===""){continue}for(g=0,p=k.length;g<p;g++){q=t[h]+"";l=b?(a[g]!==undefined?a[g]:""):a[0];t[h]=(q).split(k[g]).join(l);if(m&&t[h]!==q){this.window[m]+=(q.length-t[h].length)/k[g].length}}}return o?t:t[0]},url2links:function(b){var a=/(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;return b.replace(a,"<a href='$1' target='_blank'>$1</a>")},asynchFileUpload:function(d,f,b){var a=new Date().getTime();var g="file-upload-asynch-"+a+"-"+Math.round(Math.random()*9999);var c=document.createElement("iframe");c.setAttribute("id",g);c.setAttribute("name",g);c.setAttribute("loaded","false");uidata.uiComponents.main.appendChild(c);c.onload=function(){if(this.getAttribute("loaded")!="true"){this.loaded="true";var h=c.contentDocument.getElementsByTagName("body")[0];c.loaded="true";var k=h.innerHTML,j=null;try{j=JSON.parse(h.innerHTML)}catch(i){j=null}$(c).remove();if(j==null){b(k)}else{if("upload" in j&&j.upload=="OK"){f(j)}else{b(j)}}}};d.target=g;return true},parse_url:function(h,j){var k=["source","scheme","authority","userInfo","user","pass","host","port","relative","path","directory","file","query","fragment"],l=(this.php_js&&this.php_js.ini)||{},g=(l["phpjs.parse_url.mode"]&&l["phpjs.parse_url.mode"].local_value)||"php",b={php:/^(?:([^:\/?#]+):)?(?:\/\/()(?:(?:()(?:([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?()(?:(()(?:(?:[^?#\/]*\/)*)()(?:[^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,strict:/^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,loose:/^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/\/?)?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/};var d=b[g].exec(h),c={},f=14;while(f--){if(d[f]){c[k[f]]=d[f]}}if(j){return c[j.replace("PHP_URL_","").toLowerCase()]}if(g!=="php"){var a=(l["phpjs.parse_url.queryKey"]&&l["phpjs.parse_url.queryKey"].local_value)||"queryKey";b=/(?:^|&)([^&=]*)=?([^&]*)/g;c[a]={};c[k[12]].replace(b,function(m,i,n){if(i){c[a][i]=n}})}delete c.source;return c}};WG.util.implementListenerPattern(WG);$.fn.reverse=[].reverse;$.fn.hasAttr=function(a){return this.attr(a)!==undefined};WG.security={inOperation:false,login:function(b){var c=parseInt(b.getAttribute("expires"))*1000;if(c<=new Date().getTime()){b.style.display="none";var a=setTimeout(function(){WG.setView("login",null,true)},3000);WG.setStatus("Sorry, this form was expired. I will ask a new one...",WG.status.WARNING,function(){clearTimeout(a);WG.setView("login",null,true)},"Go on!");return false}WG.security.vkb.div.style.display="none";if(WG.security.inOperation){return false}var g=b.getAttribute("salt"),i=b.getElementsByTagName("input"),f=i[0],h=i[1],j=i[2],k=h.value;if(f.value.length==0){f.focus();return false}if(k.length==0){h.focus();return false}if(window.location.protocol!="https:"&&j.checked!==true){if(!confirm("This connection will be unsecured. Are you sure to continue?")){return false}}WG.security.inOperation=true;b.style.display="none";k=WG.security.sha1(f.value+":"+k);if(b.hasAttribute("apikey")){k="s:"+WG.security.sha1(g+":"+k+":"+b.getAttribute("apikey"))}else{k="b:"+WG.security.sha1(g+":"+k)}$(WG.security.vkb.div).hide().remove().appendTo("#viewLogin");if(j.checked===true){WG.setStatus("Open secured channel...",WG.status.WAIT);WG.security.openAES(function(){WG.setStatus("Authentication...",WG.status.WAIT);var l={};l[f.getAttribute("name")]=f.value;l[h.getAttribute("name")]=k;WG.security.authenticate(l);WG.security.inOperation=false},function(){WG.security.AES=null;WG.setStatus("AES handshake failure",WG.status.FAILURE);WG.security.inOperation=false;setTimeout("WG.setView('login', null, true);",1500)})}else{WG.security.AES=null;WG.setStatus("Authentication...",WG.status.WAIT);var d={};d[f.getAttribute("name")]=f.value;d[h.getAttribute("name")]=k;WG.security.authenticate(d)}return false},authenticate:function(a){var b=function(d,g,f){WG.setStatus("Authentication Failure: <em>"+f+"</em>",WG.status.FAILURE,function(){WG.setView("login",null,true)});if(console){console.error("[security] Authentication failure: "+f)}WG.security.inOperation=false};var c=function(f,g,d){WG.setStatus(null);if(console){console.log("[security] Authentication success!")}WG.security.inOperation=false;welcome()};a.w="auth";WG.ajax({url:WG.appURL()+"ws.php",data:a,success:c,error:b})},AES:null,openAES:function(d,b){if(console){console.log("Open AES channel...")}if(WG.security.AES==null){var a=WG.security.random(32),c=new jsSHA(a,"ASCII");WG.security.AES={hashObj:c,password:c.getHash("SHA-512","HEX")}}$.jCryption.authenticate(WG.security.AES.password,WG.appURL()+"publickeys.php",WG.appURL()+"handshake.php",function(){uidata.uiComponents.appName.setAttribute("class","securized");d()},function(){WG.security.AES=null;uidata.uiComponents.appName.setAttribute("class","");b()})},aesEncrypt:function(a){if(a instanceof Object){for(key in a){a[key]=WG.security.aesEncrypt(a)}}return $.jCryption.encrypt(""+a,WG.security.AES.password)},aesDecrypt:function(a){return $.jCryption.decrypt(""+a,WG.security.AES.password)},vkb:{pwd:null,div:null,timer:null,key:null,shifted:false,shift:function(){for(var a=0;a<4;a++){document.getElementById("row"+a).style.display=this.shifted?"inherit":"none";document.getElementById("row"+a+"_shift").style.display=this.shifted?"none":"inherit"}this.shifted=!this.shifted},keypress:function(){switch(this.key){case"Backspace":if(WG.security.vkb.pwd.value.length>0){WG.security.vkb.pwd.value=WG.security.vkb.pwd.value.substr(0,WG.security.vkb.pwd.value.length-1)}break;case"Shift":this.shift();break;case"&lt;":WG.security.vkb.pwd.value+="<";break;case"&gt;":WG.security.vkb.pwd.value+=">";break;default:WG.security.vkb.pwd.value+=this.key;break}this.timer=this.key=null}},random:function(f){var d="012345689ABCDEFGHIJKLMNOPRSTUVWXTZabcefghiklmopqrstuvwyz;:-.!@-_~$%*^+()[],/!|",c="",a=d.length;while(f-->0){var b=Math.floor(Math.random()*a);c+=d.substring(b,b+1)}return c},cookies:function(){var d=[];for(var b=0,a,f,c=document.cookie.split(";");b<c.length;b++){a=c[b].substr(0,c[b].indexOf("="));f=c[b].substr(c[b].indexOf("=")+1);a=a.replace(/^\s+|\s+$/g,"");d[a]=unescape(f)}return d},cookie:function(b){for(var c=0,a,f,d=document.cookie.split(";");c<d.length;c++){a=d[c].substr(0,d[c].indexOf("="));f=d[c].substr(d[c].indexOf("=")+1);a=a.replace(/^\s+|\s+$/g,"");if(b==a){return unescape(f)}}return null},phpSessionID:function(){return WG.security.cookie("PHPSESSID")},sha1:function(s){var c=function(y,j){var i=(y<<j)|(y>>>(32-j));return i};var t=function(A){var z="";var y;var j;for(y=7;y>=0;y--){j=(A>>>(y*4))&15;z+=j.toString(16)}return z};var g;var w,u;var b=new Array(80);var m=1732584193;var k=4023233417;var h=2562383102;var f=271733878;var d=3285377520;var r,q,p,o,n;var x;var a=s.length;var l=[];for(w=0;w<a-3;w+=4){u=s.charCodeAt(w)<<24|s.charCodeAt(w+1)<<16|s.charCodeAt(w+2)<<8|s.charCodeAt(w+3);l.push(u)}switch(a%4){case 0:w=2147483648;break;case 1:w=s.charCodeAt(a-1)<<24|8388608;break;case 2:w=s.charCodeAt(a-2)<<24|s.charCodeAt(a-1)<<16|32768;break;case 3:w=s.charCodeAt(a-3)<<24|s.charCodeAt(a-2)<<16|s.charCodeAt(a-1)<<8|128;break}l.push(w);while((l.length%16)!=14){l.push(0)}l.push(a>>>29);l.push((a<<3)&4294967295);for(g=0;g<l.length;g+=16){for(w=0;w<16;w++){b[w]=l[g+w]}for(w=16;w<=79;w++){b[w]=c(b[w-3]^b[w-8]^b[w-14]^b[w-16],1)}r=m;q=k;p=h;o=f;n=d;for(w=0;w<=19;w++){x=(c(r,5)+((q&p)|(~q&o))+n+b[w]+1518500249)&4294967295;n=o;o=p;p=c(q,30);q=r;r=x}for(w=20;w<=39;w++){x=(c(r,5)+(q^p^o)+n+b[w]+1859775393)&4294967295;n=o;o=p;p=c(q,30);q=r;r=x}for(w=40;w<=59;w++){x=(c(r,5)+((q&p)|(q&o)|(p&o))+n+b[w]+2400959708)&4294967295;n=o;o=p;p=c(q,30);q=r;r=x}for(w=60;w<=79;w++){x=(c(r,5)+(q^p^o)+n+b[w]+3395469782)&4294967295;n=o;o=p;p=c(q,30);q=r;r=x}m=(m+r)&4294967295;k=(k+q)&4294967295;h=(h+p)&4294967295;f=(f+o)&4294967295;d=(d+n)&4294967295}x=t(m)+t(k)+t(h)+t(f)+t(d);return x.toLowerCase()},base64_encode:function(k){var f="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";var d,c,b,o,n,m,l,p,j=0,q=0,h="",g=[];if(!k){return k}do{d=k.charCodeAt(j++);c=k.charCodeAt(j++);b=k.charCodeAt(j++);p=d<<16|c<<8|b;o=p>>18&63;n=p>>12&63;m=p>>6&63;l=p&63;g[q++]=f.charAt(o)+f.charAt(n)+f.charAt(m)+f.charAt(l)}while(j<k.length);h=g.join("");var a=k.length%3;return(a?h.slice(0,a-3):h)+"===".slice(a||3)},base64_decode:function(j){var d="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";var c,b,a,n,m,l,k,o,h=0,p=0,f="",g=[];if(!j){return j}j+="";do{n=d.indexOf(j.charAt(h++));m=d.indexOf(j.charAt(h++));l=d.indexOf(j.charAt(h++));k=d.indexOf(j.charAt(h++));o=n<<18|m<<12|l<<6|k;c=o>>16&255;b=o>>8&255;a=o&255;if(l==64){g[p++]=String.fromCharCode(c)}else{if(k==64){g[p++]=String.fromCharCode(c,b)}else{g[p++]=String.fromCharCode(c,b,a)}}}while(h<j.length);f=g.join("");return f}};WG.ui={createUIComponents:function(){if(console){console.log("[start] Create UI components...")}var b=document.createElement("div");b.setAttribute("id","container");b.onmousemove=function(){uidata.lastAction=new Date().getTime()};var i=document.createElement("header");b.appendChild(i);var k=document.createElement("div");k.setAttribute("id","top");i.appendChild(k);var j=document.createElement("div");j.setAttribute("id","appName");j.innerHTML=WG.util.htmlspecialchars(WG.appName());k.appendChild(j);var f=document.createElement("div");f.setAttribute("id","live");k.appendChild(f);var d=document.createElement("ul");d.setAttribute("id","menu");i.appendChild(d);var g=document.createElement("div");g.setAttribute("id","main");b.appendChild(g);var a=document.createElement("div");a.setAttribute("id","wrapper");a.setAttribute("class","fit-height");g.appendChild(a);var h=document.createElement("div");h.setAttribute("id","status");i.appendChild(h);var l=document.createElement("div");l.setAttribute("id","locker");b.appendChild(l);l.oncontextmenu=function(){return false};l.onmousedown=function(){return false};l.onmouseup=function(){return false};var c=document.createElement("div");c.setAttribute("id","menuOpts");i.appendChild(c);return{container:b,header:i,top:k,appName:j,live:f,menu:d,main:g,wrapper:a,status:h,locker:l,menuOpts:c}},bindEvents:function(){if(console){console.log("[start] Bind UI events...")}document.location.hash="";$(window).bind("hashchange",function(c){if(!(document.location.hash in WG.ui.viewHistory)){return}var b=WG.ui.viewHistory[document.location.hash];if(console){console.log("[ui] Set view: "+b.name)}var a;if(viewdata.loaded[b.name]){a=viewdata.loaded[b.name]}else{a=new WG.View(b.name);viewdata.loaded[b.name]=a}WG.ui.removeCurrentView();$(uidata.uiComponents.wrapper).scrollTop(0);WG.ui.currentView=a;uidata.uiComponents.wrapper.appendChild(a.node);WG.trigger("viewChange",a.name);if(!a.display(b.param,(b.noCache===true),b.hash)&&a.dist!=WG.View.DistributionModel.KEEP_ALIVE){WG.ui.getTrayIcon("power").setNotification("This page has been restored from cache. Click here to refresh this page.",function(){WG.setView(a.name,null,true)},3000);WG.trigger("viewRestored",a.name)}WG.ui.viewHistory[document.location.hash].noCache=false;if(b.hash){document.location.hash=b.hash}WG.trigger("viewChanged",a.name)}).resize(function(a){$(".fit-height").trigger("fitheight")})},viewCount:1,viewHistory:{},setView:function(c,f,b,d){var a="#view-"+WG.ui.viewCount++;WG.ui.viewHistory[a]={name:c,param:f,noCache:b,hash:d};if(localStorage&&c!="login"){localStorage.setItem("WG.defaultView",c)}WG.setStatus(null);WG.search.setDefault();document.location.hash=a},currentView:null,removeCurrentView:function(){uidata.uiComponents.wrapper.innerHTML="";if(WG.ui.currentView!=null){if(WG.ui.currentView.xhr){if(console){console.log("[ui] Stop loading for view: "+WG.ui.currentView.name)}WG.ui.currentView.xhr.abort()}WG.trigger("viewRemoved",WG.ui.currentView.name);WG.ui.currentView=null}},createMenu:function(d){for(var m=0,h=d.length;m<h;m++){var r=d[m];var q=document.createElement("li");q.setAttribute("class","top-level-menu module-"+r.module);if("subs" in r){q.onmouseover=function(){$("ul",this).show()};q.onmouseout=function(){$("ul",this).hide()}}var p=document.createElement("a");p.setAttribute("view",r.view);p.onclick=function(){WG.setView(this.getAttribute("view"))};p.innerHTML=WG.util.htmlspecialchars(r.label);q.appendChild(p);if("subs" in r){var n=document.createElement("ul");q.appendChild(n);for(var g=0,f=r.subs.length;g<f;g++){var b=r.subs[g],o=document.createElement("li"),c=document.createElement("a");c.innerHTML=WG.util.htmlspecialchars(b.label);c.setAttribute("view",b.view);c.onclick=function(){WG.setView(this.getAttribute("view"));this.parentNode.parentNode.style.display="none"};o.appendChild(c);n.appendChild(o)}}uidata.uiComponents.menu.appendChild(q)}var p=document.createElement("a");p.setAttribute("class","toggleMenu");p.onclick=function(){WG.ui.toggleMenuVisibility()};uidata.uiComponents.menuOpts.appendChild(p)},destroyMenu:function(){uidata.uiComponents.menu.innerHTML=""},setMenuVisible:function(a){if(a){$(uidata.uiComponents.menu).removeClass("wide");$(uidata.uiComponents.wrapper).removeClass("fit-width");$(uidata.uiComponents.menuOpts).removeClass("min")}else{$(uidata.uiComponents.menu).addClass("wide");$(uidata.uiComponents.wrapper).addClass("fit-width");$(uidata.uiComponents.menuOpts).addClass("min")}},toggleMenuVisibility:function(){WG.ui.setMenuVisible($(uidata.uiComponents.menuOpts).hasClass("min"))},createTrayIcons:function(){if(!WG.isLogged()){return false}WG.util.each(uidata.trayIcons,function(a){WG.ui.initTrayIcon(a)});return true},initTrayIcon:function(a){if(a.initialized){return}if(console){console.log("[ui] Init TrayIcon: "+a.name)}a.node=document.createElement("div");a.node.setAttribute("id",a.name);a.node.setAttribute("class","tray");if("onClick" in a.data){a.onclick_substitut=a.data.onClick;a.a=document.createElement("a");a.a.onclick=function(){a.onclick_substitut()};a.node.appendChild(a.a)}if("onInit" in a.data){a.onInit=a.data.onInit;a.onInit()}uidata.uiComponents.live.appendChild(a.node);a.initialized=true;if("onAppear" in a.data){a.onAppear=a.data.onAppear;a.onAppear()}},addTrayIcon:function(a){if(!a.name){alert("Error in WG.ui.addTrayIcon: icon name missing");return null}var b;if(!(a.name in uidata.trayIcons)){b=new WG.TrayIcon(a);uidata.trayIcons[a.name]=b}else{b=uidata.trayIcons[a.name]}if(WG.isLogged()&&uidata.uiComponents&&!b.initialized){WG.ui.initTrayIcon(b)}return b},removeTrayIcon:function(a){},removeAllTrayIcons:function(){if(console){console.warn("[todo] WG.ui.removeAllTrayIcons()")}},getTrayIcons:function(){return uidata.trayIcons},getTrayIcon:function(a){return uidata.trayIcons.hasOwnProperty(a)?uidata.trayIcons[a]:null},getTrayIconsCount:function(){return Object.keys(uidata.trayIcons).length},TrayMenuStack:{TOP:0,MIDDLE:5,BOTTOM:10},addTrayMenuItem:function(f,d,c,g,b){var a=document.createElement("li");a.setAttribute("name",f);if(b){a.setAttribute("vkb",b)}a.innerHTML=d;a.onclick=function(h){$(WG.ui.getTrayIcon("power").trayMenu).hide();g(h)};WG.ui.getTrayIcon("power").trayMenu.appendChild(a)},removeTrayMenuItem:function(a){},removeAllTrayMenuItems:function(){WG.ui.getTrayIcon("power").trayMenu.innerHTML=""},theme:{rgb2hex:function(a){a=a.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);function b(c){return("0"+parseInt(c).toString(16)).slice(-2)}return b(a[1])+b(a[2])+b(a[3])},getBackgroundColor:function(){return WG.ui.theme.rgb2hex($("body").css("background-color"))},getForegroundColor:function(){return WG.ui.theme.rgb2hex($("body").css("color"))}}};WG.View=function(b){this.name=b;this.dist=WG.View.DistributionModel.LOCAL_CACHE;this.loadedTime=0;this.loadedUrl=null;this.localCacheAge=120000;if(b in viewdata.available){var c=viewdata.available[b];if("dist" in c){if(c.dist in WG.View.DistributionModel){this.dist=WG.View.DistributionModel[c.dist]}}}if(this.dist===WG.View.DistributionModel.CONTINUE){this.isAllDataFragmentsLoaded=false;this.currentFragmentCursor=0;var a=this;$(window).scroll(function(){if($(window).scrollTop()==$(document).height()-$(window).height()){a.nextFragment()}})}this.node=document.createElement("div");this.node.setAttribute("class","view fit-height");this.node.setAttribute("id","view-"+b);this.xhr=null};WG.View.prototype.getURL=function(b){var a=[WG.appURL(),"view.php?v=",escape(this.name)];if(b instanceof Object){for(key in b){a.push("&");a.push(encodeURIComponent(key));a.push("=");a.push(encodeURIComponent(b[key]))}}return a.join("")};WG.View.prototype.display=function(d,a,c){var b=this.getURL(d);if(console){console.log("[ui] View URL: "+b+" (cache="+(a?"no":"yes")+", current="+(b!=this.loadedUrl?"no":"yes")+")")}if(a||b!=this.loadedUrl){this.download(b,c);return true}switch(this.dist){case WG.View.DistributionModel.KEEP_ALIVE:if(!this.loadedUrl){this.download(b,c);return true}return false;break;case WG.View.DistributionModel.REFRESH:this.download(b,c);return true;break;case WG.View.DistributionModel.LOCAL_CACHE:default:if(!this.loadedUrl){this.download(b,c);return true}else{if(new Date().getTime()-this.loadedTime>this.localCacheAge){this.download(b,c);return true}}return false;break}};WG.View.prototype.setContents=function(a){$(this.node).html(a);$(window).trigger("resize")};WG.View.prototype.download=function(a,b){this.loadedUrl=null;this.node.innerHTML="";WG.setStatus("Loading view...",WG.status.WAIT);if(console){console.log("[ajax] GET "+a+" (AES="+(WG.security.AES==null?"off":"on")+", cache=off)")}this.xhr=jQuery.ajax({url:a,cache:false,context:this,dataType:WG.security.AES!=null?"text":"html",type:"GET",success:function(d,f,c){this.xhr=null;this.loadedUrl=a;this.loadedTime=new Date().getTime();if(WG.security.AES!=null){WG.setStatus("Decrypting data...",WG.status.WAIT);d=WG.security.aesDecrypt(d)}WG.setStatus(null);this.setContents(d);if(typeof b=="string"){document.location.hash="#"+b}WG.trigger("viewLoaded",this.name)},error:function(c,f,d){this.xhr=null;WG.setStatus("Unable to get this view: <em>"+d+" ("+f+")</em>",WG.status.FAILURE,function(){if(this.refresh){this.refresh()}else{WG.setStatus("<b>Fatal Error</b>: please restart using F5 on your keyboard",WG.status.FAILURE)}})}})};WG.util.implementListenerPattern(WG.View.prototype);WG.View.applyStandardBehavior=function(a){$(a).on("click",'a[href]:not([target="_blank"])',function(b){return !WG.View.handleLink(this.getAttribute("href"),b)}).on("submit","form",function(b){return !WG.View.handleForms(this,b)}).on("click","ul.tabmenu li a",function(c){var b=this.parentNode,d=b.parentNode;$("li.selected",d).removeClass("selected");$(b).addClass("selected");$(".tabcontent.tab-"+d.getAttribute("tab"),a).removeClass("selected");$(".tabcontent"+this.getAttribute("href"),a).addClass("selected");c.preventDefault();return false}).on("click","ul.view-topbar-menu > li",function(b){}).on("fitheight",".fit-height",function(){var c=$(this),b=$(window).height(),d=0,f=0;f=+parseInt(c.css("padding-top"),10)+parseInt(c.css("padding-bottom"),10)+parseInt(c.css("margin-top"),10)+parseInt(c.css("margin-bottom"),10)+parseInt(c.css("borderTopWidth"),10)+parseInt(c.css("borderBottomWidth"),10);d=b-f-c.offset().top;c.css("height",d+"px")}).on("keydown","textarea.elastic",function(){this.style.height="";this.rows=this.value.split("\n").length;this.style.height=this.scrollHeight+"px"})};WG.View.handleLink=function(a){if(a.substr(0,1)=="?"){a="index.php"+a}else{if(a.substr(0,10)!="index.php?"){return false}}var a=a.substr(10).split("&"),h=null,f=null,c={},g=0,d=a.length;for(;g<d;g++){var b=a[g];if(b.indexOf("#")!==-1){f=unescape(b.substr(b.indexOf("#")+1,b.length));b=b.substr(0,b.indexOf("#"))}var k=b.split("="),l=k.shift();k=k.join();if(l=="view"||l=="v"){h=k}else{c[l]=unescape(k)}}if(h!=null){WG.setView(h,c,false,f);return true}return false};WG.View.handleForms=function(c,a){var p=$(c),k={};if(p.hasAttr("enctype")&&c.enctype=="multipart/form-data"){throw"Not implemented yet"}var g=p.attr("action"),b=WG.util.parse_url(g),f=("query" in b)?b.query.split("&"):[],l=0,h=f.length;for(;l<h;l++){var d=f[l];var n=d.split("="),o=n.shift();n=n.join();if(o=="view"||o=="v"){k.v=n}else{k[o]=unescape(n)}}$("input,textarea,select",c).each(function(){var i=$(this);if(i.hasAttr("name")){if(i.is(":disabled")){return}if(i.attr("type")=="checkbox"){if(i.is(":checked")){k[this.name]=this.value}i.attr("disabled","disabled");return}if(i.attr("name")=="view"&&!("v" in k)){k.v=i.val()}else{k[this.name]=i.val()}i.attr("disabled","disabled")}});WG.setStatus("Sending data...",WG.status.WAIT);var m=WG.ui.currentView;WG.ajax({url:"view.php",data:k,dataType:"html",success:function(i){WG.setStatus(null);if(console){console.log("Loaded URL: "+m.getURL(i))}m.loadedUrl=m.getURL(i);m.setContents(i)},error:function(i,q,j){WG.setStatus("Unable to post this form: "+j,WG.status.FAILURE,function(){WG.setStatus(null)},"close")}});a.preventDefault();return true};WG.View.DistributionModel={REFRESH:1,LOCAL_CACHE:2,KEEP_ALIVE:3,CONTINUE:4,PAGINATION:5};WG.View.prototype.supportDataFragments=function(){return(this.dist===WG.View.DistributionModel.CONTINUE||this.dist===WG.View.DistributionModel.PAGINATION)};WG.View.prototype.isAllDataFragmentsLoaded=false;WG.View.prototype.currentFragmentCursor=0;WG.View.prototype.setDataFragment=function(b,g,d,f,c){var a=this;console.log("Load fragment: "+g);WG.ajax({url:WG.appURL()+"view.php?v="+escape(this.name),dataType:"html",cache:(!c)?false:true,data:{frag:g},success:function(i,j,h){if(f){b.innerHTML=i}else{b.innerHTML+=i}if(h.status!=206){a.isAllDataFragmentsLoaded=true;a.trigger("fragmentsLoaded",{frag:g,opts:d,status:h.status})}else{a.trigger("fragmentLoaded",{frag:g,opts:d,status:h.status})}a.currentFragmentCursor=g},error:function(h,j,i){console.log("Fragment load ERROR: "+j+" ("+i+")")}})};WG.View.prototype.nextFragment=function(){if(!this.supportDataFragments()){throw"Invalid distribution model"}if(this.isAllDataFragmentsLoaded){return}this.setDataFragment(document.getElementById("dataContainer"),this.currentFragmentCursor+1,null,false,true)};WG.TrayIcon=function(a){this.name=a.name;this.data=a;this.badgeText=null;this.notification=null;this.node=null;this.initialized=false};WG.TrayIcon.prototype.setBadgeText=function(b){if(!b){if(this.badgeText){$(this.badgeText).remove();this.badgeText=null}return this}if(!this.badgeText){this.badgeText=document.createElement("span");this.badgeText.setAttribute("class","badge");this.node.appendChild(this.badgeText);var a=this;this.badgeText.onclick=function(){$(a.a).click()}}this.badgeText.innerHTML=b;return this};WG.TrayIcon.prototype.getBadgeText=function(){return(this.badgeText)?this.badgeText.innerHTML:null};WG.TrayIcon.prototype.setLoadingIndicator=function(a){if(a){$(this.node).addClass("loading")}else{$(this.node).removeClass("loading")}return this};WG.TrayIcon.prototype.hasLoadingIndicator=function(){return $(this.node).hasClass("loading")};WG.TrayIcon.prototype.setNotification=function(d,c,b){var a=this;if(!d){if(this.notification){$(this.notification).stop().fadeTo(1000,0,function(){$(a.notification).remove();a.notification=null})}return this}if(!this.notification){this.notification=document.createElement("div");this.notification.setAttribute("class","notification");$(this.notification).fadeTo(0,0);this.node.appendChild(this.notification)}if(c){this.notification.onclick=c}this.notification.innerHTML=d;$(this.notification).stop().fadeTo(1000,0.9);if(this.notificationThread!=null){clearTimeout(this.notificationThread)}if(!b){b=6000}if(b){setTimeout(function(){a.setNotification(null)},b)}return this};WG.util.implementListenerPattern(WG.TrayIcon.prototype);WG.LiveService=function(){var a=this;this.thead=setInterval(function(){a.refresh()},10000);if(console){console.log("[live] Start live service...")}};WG.LiveService.prototype.refresh=function(){if(!WG.live){return}var a=this.getParameters();if(!a){return}WG.ajax({url:WG.appURL()+"live.php",data:a,success:function(b){if("error" in b){WG.ui.getTrayIcon("power").setNotification("Error in live service.");return}WG.lastUpdate=b.serverTime;WG.live.propagation(b)},error:function(){WG.ui.getTrayIcon("power").setNotification("Unable to connect live service.")}})};WG.LiveService.prototype.getParameters=function(){var c=0,b={};for(listener in WG.live.bind){var a=WG.live.bind[listener];if(!("view" in a)||(WG.ui.currentView!=null&&WG.ui.currentView.name===a.view)){b[a.event]=1;c++}}if(c<1){return null}eventsList=[];for(event in b){eventsList.push(event)}return{t:WG.lastUpdate,l:eventsList.join("|")}};WG.LiveService.prototype.stop=function(){if(console){console.log("[live] Stop live service...")}clearInterval(this.thread)};WG.LiveService.prototype.bind={};WG.LiveService.prototype.propagation=function(b){for(listener in WG.live.bind){var a=WG.live.bind[listener];if(!("view" in a)||(WG.ui.currentView!=null&&WG.ui.currentView.name===a.view)){if(a.event in b){a.onChange(b[a.event])}}}};WG.ui.addTrayIcon({name:"power",onInit:function(){$(this.node).addClass("icon");this.trayMenu=document.createElement("ul");this.trayMenu.setAttribute("class","drop-down");this.node.appendChild(this.trayMenu)},onClick:function(){$(this.trayMenu).toggle()}});WG.ui.addTrayIcon({name:"clock",onInit:function(){this.node.innerHTML="--:--";this.updateClock=function(){var c=WG.time.getServerTime();var b=c.getHours(),a=c.getMinutes();if(b<10){b="0"+b}if(a<10){a="0"+a}this.node.innerHTML=b+":"+a}},onHide:function(){clearInterval(this.intervalThread)},onAppear:function(){this.updateClock();var a=this;this.intervalThread=setInterval(function(){a.updateClock()},5000)}});WG.search={trayIcon:null,onQuickSearch:function(b,a,c){},onQuickAction:function(b,a,c){},onReset:function(a,b){},setDefault:function(){WG.search.onQuickSearch=function(query){$("[quicksearch]").each(function(){if(this.getAttribute("quicksearch").toLowerCase().match(query)){$(this).show()}else{$(this).hide()}});return false};WG.search.onQuickAction=function(query,event,field){var v=$(".[quicksearch]:visible");if(v.size()===1){url=v.attr("url");if(url!=null&&url.length>0){if(url.substr(0,11)=="javascript:"){WG.search.onReset(event,field);e=v.get(0);e.qstmp=function(d){eval(d)};e.qstmp(url.substr(11));e.qstmp=null}else{event.preventDefault();WG.View.handleLink(url)}return false}}return true};WG.search.onReset=function(event,field){if(event){event.preventDefault()}$("[quicksearch]").show();if(field){$(field).val("")}return false};if(WG.search.trayIcon.input){WG.search.onReset(null,WG.search.trayIcon.input)}},quickSearch:function(a){WG.search.onQuickSearch(a)}};window.quickSearch=function(a){WG.search.quickSearch(a)};WG.search.trayIcon=WG.ui.addTrayIcon({name:"searchbox",onInit:function(){WG.search.setDefault();this.input=document.createElement("input");this.input.setAttribute("type","search");this.input.setAttribute("id","qs");this.input.setAttribute("name","qs");this.input.setAttribute("placeholder","Search...");$(this.input).keyup(function(b){var a=jQuery.trim($(this).val());if(a.length>0){if(b.keyCode==13){if(!WG.search.onQuickAction(a,b,this)){return false}WG.setView("search",{q:a});return false}return WG.search.onQuickSearch(a,b,this)}else{return WG.search.onReset(b,this)}});this.node.appendChild(this.input);thiz=this;this.getFocus=function(){thiz.input.focus()};WG.bind("viewChange",this.getFocus)},onClick:function(){},onMouseOver:function(){},onHide:function(){},onAppear:function(){},onDestroy:function(){window.quickSearch=WG.quickSearch=undefined;WG.unbind("*",this.getFocus)}});