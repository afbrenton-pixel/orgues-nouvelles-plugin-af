/* Lecteur audio et vidéo des vignettes d'accueil (repris de l'ancien plugin « Vignettes d'accueil »).
   Les liens MP3 / Vimeo et le type de ressource viennent de window.ONV_MEDIA (voir includes/vignettes/player.php). */
(function(){
var audio=new Audio();audio.preload='none';
var cur=null;
var PLAY='<svg viewBox="0 0 24 24" width="100%" height="100%" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="rgba(31,42,68,.85)"/><path d="M9.5 7.3v9.4l7.6-4.7z" fill="#fff"/></svg>';
var MPLAY='<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M7 4.5v15l12-7.5z" fill="currentColor"/></svg>';
var MPAUSE='<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M6 4.5h4.2v15H6zM13.8 4.5H18v15h-4.2z" fill="currentColor"/></svg>';
function q(el,s){return el.querySelector(s);}
function setIcon(card,playing){var b=q(card,'.onv-mbtn');if(b){b.innerHTML=playing?MPAUSE:MPLAY;b.setAttribute('aria-label',playing?'Pause':'Lecture');}card.classList.toggle('onv-playing',playing);}
function fill(card,p){var f=q(card,'.onv-fill');if(f)f.style.width=Math.max(0,Math.min(100,p*100))+'%';}
function stopAll(){
  if(!cur)return;var c=cur;cur=null;
  if(c.type==='audio'){audio.pause();audio.removeAttribute('src');try{audio.load();}catch(e){}
    c.el.classList.remove('onv-active','onv-playing');fill(c.el,0);setIcon(c.el,false);}
  else{var w=q(c.el,'.onv-vwrap');if(w)w.parentNode.removeChild(w);var a=q(c.el,'.onv-media > a');if(a)a.style.display='';
    c.el.classList.remove('onv-video-on','onv-playing','onv-active');}
}
function loadTrack(c){audio.src=c.tracks[c.i];var n=q(c.el,'.onv-count');if(n)n.textContent=c.tracks.length>1?(c.i+1)+'/'+c.tracks.length:'';fill(c.el,0);var p=audio.play();if(p&&p.catch)p.catch(function(){setIcon(c.el,false);});}
function startAudio(card){
  stopAll();
  var tracks=[];try{tracks=JSON.parse(card.getAttribute('data-onv-audio'))||[];}catch(e){}
  if(!tracks.length)return;
  cur={el:card,type:'audio',tracks:tracks,i:0};
  card.classList.add('onv-active');setIcon(card,true);loadTrack(cur);
}
function toggleAudio(card){
  if(cur&&cur.el===card&&cur.type==='audio'){
    if(audio.paused){var p=audio.play();if(p&&p.catch)p.catch(function(){});setIcon(card,true);}else{audio.pause();setIcon(card,false);}
  }else{startAudio(card);}
}
function startVideo(card){
  stopAll();
  var u=card.getAttribute('data-onv-video');if(!u)return;
  u+=(u.indexOf('?')>-1?'&':'?')+'autoplay=1';
  var m=q(card,'.onv-media');if(!m)return;
  var a=q(m,':scope > a');if(a)a.style.display='none';
  var w=document.createElement('div');w.className='onv-vwrap';
  var f=document.createElement('iframe');f.src=u;f.setAttribute('allow','autoplay; fullscreen; picture-in-picture');f.setAttribute('allowfullscreen','');f.setAttribute('title','Vidéo');
  w.appendChild(f);m.appendChild(w);
  card.classList.add('onv-video-on','onv-playing');
  cur={el:card,type:'video'};
  setTimeout(function(){try{card.scrollIntoView({behavior:'smooth',block:'nearest'});}catch(e){}},50);
}
audio.addEventListener('timeupdate',function(){if(cur&&cur.type==='audio'&&audio.duration)fill(cur.el,audio.currentTime/audio.duration);});
audio.addEventListener('ended',function(){if(!cur||cur.type!=='audio')return;if(cur.i<cur.tracks.length-1){cur.i++;loadTrack(cur);}else{stopAll();}});
audio.addEventListener('error',function(){if(cur&&cur.type==='audio'){stopAll();}});

var PICTO={
 audio:'<svg viewBox="0 0 138 138" width="100%" height="100%" aria-hidden="true" focusable="false"><path fill="currentColor" d="M27.4,89.9h3v-15.1h-3v15.1Zm81.2,0h2.9v-15.1h-2.9v15.1Zm6.9-16.4v17.9l-6.9,6.9h-3v-27.9c-.6-9.8-4.6-18.6-10.6-25-6.6-7.1-15.6-11.4-25.5-11.4s-18.9,4.3-25.5,11.4c-6.1,6.6-10.1,15.6-10.6,25.6v27.2h-3l-6.9-6.9v-17.8l3.5-3.5c.7-11.2,5.2-21.4,12.3-28.9,7.7-8.2,18.4-13.4,30.2-13.4s22.5,5.1,30.2,13.4c7,7.5,11.6,17.7,12.3,29l3.5,3.4h0Zm-77.4-3.8c0-3.5,2.9-6.4,6.4-6.4h2c3.5,0,6.4,2.9,6.4,6.4v23.2c0,3.5-2.9,6.4-6.4,6.4h-2c-3.5,0-6.4-2.9-6.4-6.4v-23.2Zm49.3,0c0-3.5,2.9-6.4,6.4-6.4h2c3.5,0,6.4,2.9,6.4,6.4v23.2c0,3.5-2.9,6.4-6.4,6.4h-2c-3.5,0-6.4-2.9-6.4-6.4v-23.2Zm44.9-.7c0-35-28.3-63.3-63.3-63.3S5.7,34.1,5.7,69s28.3,63.3,63.3,63.3,63.3-28.3,63.3-63.3"/></svg>',
 partitions:'<svg viewBox="0 0 138 138" width="100%" height="100%" aria-hidden="true" focusable="false"><path fill="currentColor" d="M66.2,82.6h-4.4c.7,2.3,1.2,4.2,1.7,5.8,1.8-1.7,2.5-3.8,2.7-5.8m-7.3-9.8c.7,2.5,1.4,4.9,2.1,7.2h5c-.1-.5-.2-1.1-.4-1.5-1.2-3.4-3.8-5.6-6.5-5.6h-.2v-.1Zm-1.5,17.2c1.1,0,2.2-.1,3.2-.4-.6-1.9-1.3-4.4-2-7.1h-4.7c.6,1.4,1.4,2.3,1.5,2.4,.2,.2,.2,.5,.1,.8-.1,.2-.3,.3-.5,.3h-.2c-.1,0-2.3-.7-4.1-2.7-.2-.2-.4-.5-.6-.8h-5.7c2.1,5.2,7.8,7.5,13,7.5m-8.8-10c-.6-1.8-.8-4,.1-6.9,.3-1.1,.8-2,1.5-2.9h-4.5c-1.7,2.9-2.7,6.3-2.1,9.8h5Zm3.2-17.8c-1.4,1.6-3,3.4-4.5,5.4h5.6c.4-.3,.8-.6,1.2-.7-.6-2-1.1-3.9-1.5-5.5l-.8,.8m3.8-16.9h-4.2c.2,1.4,.6,3,1.1,4.9,1.1-1.2,2.2-2.7,3.1-4.9m.5,28.4c-.8,.5-1.4,1.1-1.9,1.8-1,1.6-1.2,3.1-1,4.5h4.7c-.7-2.1-1.2-4.2-1.8-6.3m-16.2,8.9h-10.2v9.9h21.6c-4.2-1.2-7.7-3.7-10.2-7.5-.5-.8-.9-1.6-1.2-2.4m-1.2-12.4h-9v9.8h9.2c-.9-3.2-.9-6.5-.2-9.8m6.6-12.5h-15.6v9.8h9.8c.1-.3,.2-.7,.4-1,1.8-3.9,3.6-6.6,5.4-8.8m4-4.1l.9-.9c-.2-.8-.5-1.7-.7-2.4-.5-1.7-1-3.3-1.4-4.9H29.7v9.8h18c.5-.6,1.1-1.1,1.6-1.6m7.9-12.8c1.4-5.4,.6-7.2,.2-7.7-.3-.3-.6-.5-1-.5-1.5,0-3.7,2-4.4,4.7-.6,2.1-.7,3.8-.7,5.5h5.4c.1-.7,.3-1.3,.5-2m.1,14.4h52v-9.8H61.6c-.8,3.2-2.2,6.5-4.3,9.8m9.3,12.4h42.7v-9.8H55.5c-.2,.3-.4,.6-.6,.9,.6,2,1.3,4.5,2,7.2,1-.2,2-.3,3-.3,2.6-.1,4.9,.6,6.7,2m5,12.4h37.7v-9.8h-40.4c2,2.8,2.9,6.3,2.7,9.8m-7,12.4h44.7v-9.9h-38.1c-.1,.4-.2,.8-.3,1.1-1.1,3.8-3.6,6.8-6.6,8,.1,.3,.2,.6,.3,.8m47.3,2.6h-46.5c1.7,5,3.7,11.5-.6,16-1.9,2-4.2,3-6.9,3s-5.3-1-7.1-2c-.4-.1-.7-.3-1.1-.4-1.8-1-3-2.9-3.2-5-.1-1.7,.4-3.3,1.5-4.5,1.1-1.3,2.6-2.1,4.3-2.2h.5c3.3,0,6,2.5,6.3,5.8,.1,1.8-.5,3.5-1.7,4.8h.5c1.5,0,2.8-.5,4.1-1.6,3.5-2.9,1.5-9,0-13.9H27V42.7h20.5c-.3-1.8-.4-3.7-.3-5.7,.8-12.5,6.9-14.5,7.2-14.6h.2c.1,0,.3,0,.4,.2,.2,.2,4.1,3.8,6.1,10.1,1,3.3,1.2,6.6,.8,10.1h49.9c.1-.1,.1,52.2,.1,52.2Zm20.4-26c0-34.9-28.3-63.3-63.3-63.3S5.7,34.1,5.7,69s28.3,63.3,63.3,63.3,63.3-28.3,63.3-63.3"/></svg>',
 textes:'<svg viewBox="0 0 138 138" width="100%" height="100%" aria-hidden="true" focusable="false"><circle cx="69" cy="69" r="63.3" fill="none" stroke="currentColor" stroke-width="3"/><path d="M44 46h50M44 63h50M44 80h50M44 97h30" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/></svg>'
};
function decorate(){
  var map=window.ONV_MEDIA||{};
  var items=document.querySelectorAll('.e-loop-item');
  for(var k=0;k<items.length;k++){
    var it=items[k];if(it.getAttribute('data-onv-dec'))continue;
    var m=/e-loop-item-(\d+)/.exec(it.className);if(!m||!map[m[1]])continue;
    var info=map[m[1]];it.setAttribute('data-onv-dec','1');it.classList.add('onv-card');
    var imgw=it.querySelector('.elementor-widget-image .elementor-widget-container');
    if(imgw)imgw.classList.add('onv-media');
    var tagw=it.querySelector('.elementor-widget-text-editor');
    if(tagw)tagw.classList.add('onv-tagw');
    if(info.audio&&info.audio.length){it.classList.add('onv-has-audio');it.setAttribute('data-onv-audio',JSON.stringify(info.audio));}
    else if(info.video){it.classList.add('onv-has-video');it.setAttribute('data-onv-video',info.video);}
    if(!imgw){
      it.classList.add('onv-compactcard');
      if(it.classList.contains('onv-has-audio'))it.classList.add('onv-barfull');
      if(tagw&&info.kind&&PICTO[info.kind]){
        var row=document.createElement('div');row.className='onv-trow';
        tagw.parentNode.insertBefore(row,tagw);row.appendChild(tagw);
        var pc=document.createElement('span');pc.className='onv-picto onv-picto-'+info.kind;pc.innerHTML=PICTO[info.kind];row.appendChild(pc);
      }
    }
  }
}
function init(){
  decorate();
  var cards=document.querySelectorAll('.onv-card.onv-has-audio, .onv-card.onv-has-video');
  for(var k=0;k<cards.length;k++){
    var c=cards[k];if(c.getAttribute('data-onv-ready'))continue;c.setAttribute('data-onv-ready','1');if(c.classList.contains('onv-has-audio')){try{if(JSON.parse(c.getAttribute('data-onv-audio')).length>1)c.classList.add('onv-multi');}catch(e){}}
    var a=q(c,'.onv-media > a');
    if(a){var s=document.createElement('span');s.className='onv-play';s.innerHTML=PLAY;a.appendChild(s);}
    if(c.classList.contains('onv-has-audio')){
      var tag=q(c,'.onv-tagw');
      var mini=document.createElement('span');mini.className='onv-mini';
      mini.innerHTML='<button type="button" class="onv-mbtn" aria-label="Pause"></button><span class="onv-bar"><span class="onv-fill"></span></span><span class="onv-count"></span><button type="button" class="onv-nbtn onv-prev" aria-label="Morceau précédent"><svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true"><path d="M6 5h2.4v14H6zM20 5v14L9.5 12z" fill="currentColor"/></svg></button><button type="button" class="onv-nbtn onv-next" aria-label="Morceau suivant"><svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true"><path d="M15.6 5H18v14h-2.4zM4 5v14l10.5-7z" fill="currentColor"/></svg></button>';
      if(c.classList.contains('onv-barfull')){c.appendChild(mini);setIcon(c,false);}else if(tag&&tag.parentNode)tag.parentNode.insertBefore(mini,tag.nextSibling);else{var b=q(c,'.onv-body');if(b)b.insertBefore(mini,b.firstChild);}
    }
  }
}
document.addEventListener('click',function(e){
  var card=e.target.closest?e.target.closest('.onv-card.onv-has-audio, .onv-card.onv-has-video'):null;if(!card)return;
  var bar=e.target.closest('.onv-bar');
  if(bar&&!(cur&&cur.el===card)){e.preventDefault();toggleAudio(card);return;}
  if(bar&&cur&&cur.el===card&&cur.type==='audio'&&audio.duration){var r=bar.getBoundingClientRect();audio.currentTime=audio.duration*Math.max(0,Math.min(1,(e.clientX-r.left)/r.width));e.preventDefault();return;}
  var nb=e.target.closest('.onv-nbtn');
  if(nb){e.preventDefault();if(cur&&cur.el===card&&cur.type==='audio'){var nx=cur.i+(nb.classList.contains('onv-next')?1:-1);if(nx>=0&&nx<cur.tracks.length){cur.i=nx;setIcon(card,true);loadTrack(cur);}}return;}
  if(e.target.closest('.onv-mbtn')){e.preventDefault();toggleAudio(card);return;}
  if(e.target.closest('.onv-picto')&&card.classList.contains('onv-has-audio')){e.preventDefault();toggleAudio(card);return;}
  var media=e.target.closest('.onv-media');
  if(media&&!e.target.closest('.onv-vwrap')){
    e.preventDefault();
    if(card.classList.contains('onv-has-video'))startVideo(card);else toggleAudio(card);
  }
});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
