
function onReaderInit(root){
"use strict";
function q(r){return root.querySelector('[data-r="'+r+'"]');}
var PDF_URL=root.getAttribute("data-pdf");
var L={};try{L=JSON.parse(root.getAttribute("data-labels")||"{}");}catch(e){}
function t(k,v){var x=L[k]||k;return v===undefined?x:x.replace("%s",v);}
function roman(n){var m=[[10,"X"],[9,"IX"],[5,"V"],[4,"IV"],[1,"I"]],s="";m.forEach(function(x){while(n>=x[0]){s+=x[1];n-=x[0];}});return s;}
function fmt(t){if(!isFinite(t))return"0:00";var m=Math.floor(t/60),s=Math.floor(t%60);return m+":"+(s<10?"0":"")+s;}

var pieces=[],byUrl={},current=null;
var audio=q("Audio"),playBtn=q("Play"),seek=q("Seek"),seeking=false;
function playingNow(){return audio.paused||audio.ended?false:true;}
function setPlayUI(){var p=playingNow();q("IcoPlay").style.display=p?"none":"";q("IcoPause").style.display=p?"":"none";playBtn.setAttribute("aria-label",p?t("pause"):t("play"));markHits();}
function showInfo(){if(!current)return;q("NowT").textContent=current.title;var e=q("NowP");e.className="p";e.textContent=current.perf||"";q("Show").disabled=false;seek.disabled=false;playBtn.disabled=false;
 q("List").querySelectorAll("li").forEach(function(li){li.classList.toggle("on",li.getAttribute("data-id")===String(current.id));});}
function problem(){var e=q("NowP");e.className="p err";e.textContent=t("audioError");setPlayUI();}
function toggle(){if(!current)return;if(audio.paused){var r=audio.play();if(r)r.catch(problem);}else audio.pause();}
function playPiece(p){if(current)if(current.id===p.id){toggle();return;}current=p;audio.src=p.url;showInfo();var r=audio.play();if(r)r.catch(problem);
 if("mediaSession" in navigator){try{navigator.mediaSession.metadata=new MediaMetadata({title:p.title,artist:p.perf||"",album:root.getAttribute("data-sub")||""});navigator.mediaSession.setActionHandler("play",function(){audio.play();});navigator.mediaSession.setActionHandler("pause",function(){audio.pause();});}catch(e){}}}
playBtn.addEventListener("click",toggle);
["play","pause","ended"].forEach(function(ev){audio.addEventListener(ev,setPlayUI);});
audio.addEventListener("error",problem);
function updTime(){var d=audio.duration,t=audio.currentTime;q("Time").textContent=fmt(t)+" / "+fmt(d);if(!seeking)if(isFinite(d))if(d>0)seek.value=Math.round(t/d*1000);}
audio.addEventListener("loadedmetadata",updTime);audio.addEventListener("timeupdate",updTime);
seek.addEventListener("input",function(){seeking=true;if(isFinite(audio.duration))q("Time").textContent=fmt(seek.value/1000*audio.duration)+" / "+fmt(audio.duration);});
seek.addEventListener("change",function(){if(isFinite(audio.duration))audio.currentTime=seek.value/1000*audio.duration;seeking=false;});
q("Show").addEventListener("click",function(){if(current)goTo(current.page);});

var pdfDoc=null,total=0,spreads=[],idx=0,token=0,view=q("View"),spreadEl=q("Spread");
var ZL=[1,1.5,2,3],zi=0;
function setZoom(d){var n=Math.max(0,Math.min(ZL.length-1,zi+d));if(n===zi)return;zi=n;q("ZoomV").textContent=Math.round(ZL[zi]*100)+" %";q("ZoomOut").disabled=zi===0;q("ZoomIn").disabled=zi===ZL.length-1;view.classList.toggle("zoomed",zi>0);render();}
function wide(){return view.clientWidth>=860;}
q("ZoomOut").addEventListener("click",function(){setZoom(-1);});q("ZoomIn").addEventListener("click",function(){setZoom(1);});q("ZoomOut").disabled=true;
function build(){spreads=[];if(wide()){spreads.push([1]);for(var p=2;p<=total;p+=2)spreads.push(p+1<=total?[p,p+1]:[p]);}else{for(var q=1;q<=total;q++)spreads.push([q]);}}
function spreadOf(pg){for(var i=0;i<spreads.length;i++)if(spreads[i].indexOf(pg)>=0)return i;return 0;}
function goTo(pg){idx=spreadOf(pg);render();closeToc();}
function step(d){var n=idx+d;if(n<0||n>=spreads.length)return;idx=n;render();}
q("Prev").addEventListener("click",function(){step(-1);});q("Next").addEventListener("click",function(){step(1);});
root.setAttribute("tabindex","-1");
root.addEventListener("keydown",function(e){if(e.key==="Escape")closeToc();if(e.target)if(/INPUT|TEXTAREA/.test(e.target.tagName))return;if(e.key==="ArrowLeft")step(-1);if(e.key==="ArrowRight")step(1);});
var tx=null;view.addEventListener("touchstart",function(e){tx=e.touches[0].clientX;},{passive:true});
view.addEventListener("touchend",function(e){if(tx===null)return;var dx=e.changedTouches[0].clientX-tx;tx=null;if(Math.abs(dx)>60)step(dx<0?1:-1);},{passive:true});
function markHits(){var pl=playingNow();spreadEl.querySelectorAll(".hit[data-piece]").forEach(function(h){var on=current?h.getAttribute("data-piece")===String(current.id):false;h.classList.toggle("is-playing",on?pl:false);h.classList.toggle("is-current",on?!pl:false);});}

function render(){
 var my=++token;if(!pdfDoc)return;var pages=spreads[idx];
 q("Prev").disabled=idx===0;q("Next").disabled=idx===spreads.length-1;
 q("Roman").textContent=pages.map(roman).join(" – ");q("Num").textContent=pages.join(" – ")+" / "+total;
 Promise.all(pages.map(function(n){return pdfDoc.getPage(n);})).then(function(pgs){
  if(my!==token)return;
  var natW=pgs.reduce(function(s,p){return s+p.getViewport({scale:1}).width;},0),natH=pgs[0].getViewport({scale:1}).height;
  var scale=Math.max(.2,Math.min((view.clientWidth-4)/natW,(view.clientHeight-4)/natH))*ZL[zi],dpr=Math.min(window.devicePixelRatio||1,2.5),frag=document.createDocumentFragment();
  var jobs=pgs.map(function(page){
   var vp=page.getViewport({scale:scale}),w=Math.floor(vp.width),h=Math.floor(vp.height),wrap=document.createElement("div");wrap.className="pg";wrap.style.width=w+"px";wrap.style.height=h+"px";
   var cv=document.createElement("canvas");cv.width=Math.floor(vp.width*dpr);cv.height=Math.floor(vp.height*dpr);cv.style.width=w+"px";cv.style.height=h+"px";wrap.appendChild(cv);frag.appendChild(wrap);
   var t=page.render({canvasContext:cv.getContext("2d"),viewport:vp,transform:dpr!==1?[dpr,0,0,dpr,0,0]:null}).promise;
   var a=page.getAnnotations().then(function(list){list.forEach(function(an){
     if(an.subtype!=="Link"||!an.url)return;
     var r=vp.convertToViewportRectangle(an.rect),el,pc=byUrl[an.url];
     if(pc){el=document.createElement("button");el.type="button";el.className="hit";el.setAttribute("data-piece",pc.id);el.setAttribute("aria-label",t("listenTitle",pc.title));el.title=t("listenTitle",pc.title);el.addEventListener("click",function(){playPiece(pc);});}
     else if(/\.mp3(\?|$)/i.test(an.url))return;
     else{el=document.createElement("a");el.className="hit ext";el.href=an.url;el.target="_blank";el.rel="noopener";el.setAttribute("aria-label",t("openLink"));}
     el.style.left=Math.min(r[0],r[2])+"px";el.style.top=Math.min(r[1],r[3])+"px";el.style.width=Math.abs(r[2]-r[0])+"px";el.style.height=Math.abs(r[3]-r[1])+"px";wrap.appendChild(el);});});
   return Promise.all([t,a]);});
  spreadEl.replaceChildren(frag);
  return Promise.all(jobs).then(function(){if(my===token)markHits();});
 }).catch(function(err){if(err)if(err.name==="RenderingCancelledException")return;spreadEl.textContent=t("pageFail");});
}

/* Les pièces sont lues dans le PDF : un lien .mp3 = une pièce ; titre = plus gros texte lisible du haut de page, interprète = ligne « par … » à droite de l'icône */
function discover(){
 var raw=[],p=Promise.resolve();
 function label(u){try{return decodeURIComponent(u.split("/").pop()).replace(/\.mp3$/i,"").replace(/^\d+[-_ ]*/,"").replace(/[-_]+/g," ").trim();}catch(e){return "";}}
 for(var n=1;n<=total;n++)(function(n){p=p.then(function(){return pdfDoc.getPage(n).then(function(page){return Promise.all([page.getAnnotations(),page.getTextContent()]).then(function(r){
  var H=page.view[3],items=r[1].items.filter(function(i){return i.str?i.str.trim()!=="":false;}),firstPerf="";
  r[0].forEach(function(an){
   if(an.subtype!=="Link")return;
   if(!an.url)return;
   if(!/\.mp3(\?|$)/i.test(an.url))return;
   if(byUrl[an.url])return;
   var cy=(an.rect[1]+an.rect[3])/2,right=Math.max(an.rect[0],an.rect[2]);
   var line=items.filter(function(i){return Math.abs(i.transform[5]+Math.abs(i.transform[3])/3-cy)<14?i.transform[4]>right-2:false;}).sort(function(a,b){return a.transform[4]-b.transform[4];}).map(function(i){return i.str;}).join("").trim();
   var perf=/^par\s/i.test(line)?line.replace(/^par\s+/i,""):"";
   var cand=items.filter(function(i){return i.transform[5]>H*.55?/[A-Za-zÀ-ÿ]{3,}/.test(i.str):false;}).filter(function(i){return !/^par\s/i.test(i.str.trim());});
   var mx=0;cand.forEach(function(i){mx=Math.max(mx,Math.hypot(i.transform[2],i.transform[3]));});
   var seen={},parts=[];
   cand.filter(function(i){return Math.hypot(i.transform[2],i.transform[3])>=mx*.85;}).sort(function(a,b){return b.transform[5]-a.transform[5]||a.transform[4]-b.transform[4];}).forEach(function(i){var s=i.str.trim();if(!seen[s]){seen[s]=1;parts.push(s);}});
   var rec={url:an.url,page:n,raw:parts.join(" "),file:label(an.url),perf:perf};
   if(!rec.perf)rec.perf=firstPerf;else if(!firstPerf)firstPerf=rec.perf;
   raw.push(rec);byUrl[an.url]=rec;});});});});})(n);
 return p.then(function(){
  var count={};raw.forEach(function(x){count[x.raw]=(count[x.raw]||0)+1;});
  raw.forEach(function(x,k){var t=x.raw;if(!t)t=x.file||(t("piece")+" "+(k+1));else if(count[x.raw]>1)if(x.file)t=t+" · "+x.file;x.id=k+1;x.title=t;pieces.push(x);});
 });
}
function buildToc(){var ol=q("List");ol.textContent="";
 if(!pieces.length){var li=document.createElement("li");li.textContent=t("noRecordings");ol.appendChild(li);return;}
 pieces.forEach(function(p){var li=document.createElement("li");li.setAttribute("data-id",p.id);
  var go=document.createElement("button");go.type="button";go.className="go";var a=document.createElement("span");a.className="name";a.textContent=p.title;var b=document.createElement("span");b.className="meta";b.textContent=t("pageWord")+" "+roman(p.page)+(p.perf?" · "+p.perf:"");go.appendChild(a);go.appendChild(b);go.addEventListener("click",function(){goTo(p.page);});
  var pl=document.createElement("button");pl.type="button";pl.className="btn";pl.setAttribute("aria-label",t("listenTitle",p.title));pl.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><span class="lbl">'+t("listen")+'</span>';pl.addEventListener("click",function(){playPiece(p);});
  li.appendChild(go);li.appendChild(pl);ol.appendChild(li);});}
function openToc(){q("Toc").hidden=false;q("Back").hidden=false;q("TocBtn").setAttribute("aria-expanded","true");q("Close").focus();}
function closeToc(){q("Toc").hidden=true;q("Back").hidden=true;q("TocBtn").setAttribute("aria-expanded","false");}
q("TocBtn").addEventListener("click",function(){q("Toc").hidden?openToc():closeToc();});q("Close").addEventListener("click",closeToc);q("Back").addEventListener("click",closeToc);

function start(){
 if(typeof pdfjsLib==="undefined"){spreadEl.textContent=t("libFail");return;}
 pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
 pdfjsLib.getDocument({url:PDF_URL}).promise.then(function(doc){
  pdfDoc=doc;total=doc.numPages;build();idx=0;
  return discover().then(function(){buildToc();render();
   var was=wide(),timer=null;
   function relayout(){var cur=spreads[idx]?spreads[idx][0]:1,now=wide();if(now!==was){was=now;build();idx=spreadOf(cur);}render();}
   if("ResizeObserver" in window)new ResizeObserver(function(){clearTimeout(timer);timer=setTimeout(relayout,150);}).observe(view);
   else window.addEventListener("resize",function(){clearTimeout(timer);timer=setTimeout(relayout,150);});});
 }).catch(function(){spreadEl.textContent=t("openFail");});
}
start();
}

(function(){
"use strict";
function fullscreenInit(root){
  var b=root.querySelector('[data-r="Fs"]'),panel=root.querySelector('[data-r="Panel"]');
  if(!b||!panel)return;
  var can=!!(panel.requestFullscreen||panel.webkitRequestFullscreen);
  if(!can){b.hidden=true;return;}
  b.addEventListener("click",function(){
    var on=document.fullscreenElement||document.webkitFullscreenElement;
    if(on){(document.exitFullscreen||document.webkitExitFullscreen).call(document);}
    else{(panel.requestFullscreen||panel.webkitRequestFullscreen).call(panel);}
  });
}
function setup(root){
  if(root.getAttribute("data-ready"))return;root.setAttribute("data-ready","1");
  var opener=root.querySelector('[data-r="Open"]'),panel=root.querySelector('[data-r="Panel"]'),started=false;
  fullscreenInit(root);
  if(!opener||!panel)return;
  opener.addEventListener("click",function(e){
    e.preventDefault();
    var show=panel.hidden;panel.hidden=!show;
    opener.setAttribute("aria-expanded",show?"true":"false");
    if(show){
      if(!started){started=true;onReaderInit(panel);}
      panel.scrollIntoView({behavior:"smooth",block:"start"});
    }
  });
}
function all(){document.querySelectorAll(".on-reader").forEach(setup);}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",all);else all();
window.addEventListener("elementor/frontend/init",function(){
  if(window.elementorFrontend&&elementorFrontend.hooks){
    elementorFrontend.hooks.addAction("frontend/element_ready/online-reader.default",function($el){$el[0].querySelectorAll(".on-reader").forEach(setup);});
  }
});
})();
