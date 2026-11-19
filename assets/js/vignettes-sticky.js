/* Colonnes fixes de l'accueil (conteneurs .on-sticky) plus hautes que l'écran.
   Le modèle fixe la colonne à 170 px du haut : si elle est plus haute que l'écran, son bas n'est jamais visible.
   On remonte alors la position fixe (valeur « top » négative) : la colonne défile avec la page jusqu'à ce que son bas
   atteigne le bas de l'écran, puis reste fixée là. Recalculé à chaque changement de taille (liste de pistes dépliée, etc.). */
(function(){
var TOP=170,MARGE=16;
function ajuster(el){
  if(getComputedStyle(el).position!=='sticky'){el.style.top='';return;}
  var h=el.offsetHeight,dispo=window.innerHeight-TOP-MARGE;
  el.style.top=h>dispo?(window.innerHeight-h-MARGE)+'px':'';
}
function tout(){var l=document.querySelectorAll('.e-con.on-sticky');for(var i=0;i<l.length;i++)ajuster(l[i]);}
function init(){
  tout();
  window.addEventListener('resize',tout);
  window.addEventListener('load',tout);
  if(window.ResizeObserver){
    var ro=new ResizeObserver(tout),l=document.querySelectorAll('.e-con.on-sticky');
    for(var i=0;i<l.length;i++)ro.observe(l[i]);
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
