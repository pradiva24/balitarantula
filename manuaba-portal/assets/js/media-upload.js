(function(){
  'use strict';
  async function refreshCsrf(form){
    try{
      var url='media.php?ajax=csrf&_=' + Date.now();
      var r=await fetch(url,{method:'GET',credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!r.ok) return false;
      var data=await r.json();
      if(!data || !data.ok || !data.csrf) return false;
      document.querySelectorAll('input[name="csrf"]').forEach(function(input){input.value=data.csrf;});
      var own=form.querySelector('input[name="csrf"]'); if(own) own.value=data.csrf;
      return true;
    }catch(e){ return false; }
  }
  function boot(){
    document.querySelectorAll('form[data-media-upload="1"]').forEach(function(form){
      if(form.dataset.mediaBound==='1') return;
      form.dataset.mediaBound='1';
      var input=form.querySelector('input[type=file]');
      var button=form.querySelector('[data-upload-button]');
      var status=form.querySelector('[data-upload-status]');
      var bar=form.querySelector('[data-upload-bar]');
      var percent=form.querySelector('[data-upload-percent]');
      var busy=false;
      function setStatus(msg,pct,type){
        if(status){status.textContent=msg;status.dataset.type=type||'';status.hidden=false;}
        if(bar) bar.style.width=Math.max(0,Math.min(100,pct||0))+'%';
        if(percent) percent.textContent=Math.round(pct||0)+'%';
      }
      form.addEventListener('submit',async function(e){
        if(busy) return;
        if(!input || !input.files || !input.files.length){
          e.preventDefault(); setStatus('Please choose at least one image.',0,'error'); return;
        }
        e.preventDefault();
        busy=true;
        if(button){button.disabled=true;button.textContent='Preparing…';}
        setStatus('Checking secure session…',5,'info');
        await refreshCsrf(form);
        setStatus('Uploading images…',8,'info');
        if(button) button.textContent='Uploading…';
        var timer=setInterval(function(){
          if(!busy){clearInterval(timer);return;}
          var current=parseFloat(bar ? bar.style.width : '8')||8;
          if(current<92) setStatus('Uploading images…',current+3,'info');
        },350);
        form.dataset.uploadTimer=String(Date.now());
        form.submit();
      });
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();
})();
