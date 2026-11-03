(function(){
  'use strict';

  async function refreshCsrf(form){
    try{
      var url='media.php?ajax=csrf&_='+Date.now();
      var response=await fetch(url,{
        method:'GET',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
      });
      if(!response.ok) return false;
      var data=await response.json();
      if(!data||!data.ok||!data.csrf) return false;
      document.querySelectorAll('input[name="csrf"]').forEach(function(input){input.value=data.csrf;});
      var own=form.querySelector('input[name="csrf"]');
      if(own) own.value=data.csrf;
      return true;
    }catch(error){return false;}
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

      function setStatus(message,pct,type){
        if(status){
          status.textContent=message;
          status.dataset.type=type||'';
          status.hidden=false;
          status.style.display='block';
        }
        if(bar) bar.style.width=Math.max(0,Math.min(100,pct||0))+'%';
        if(percent) percent.textContent=Math.round(pct||0)+'%';
      }

      async function sendUpload(retried){
        var formData=new FormData(form);
        formData.set('ajax','1');
        var response;
        try{
          response=await fetch(form.action||window.location.href,{
            method:'POST',
            body:formData,
            credentials:'same-origin',
            cache:'no-store',
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
          });
        }catch(error){
          throw new Error('Network error while uploading. Please check your connection and try again.');
        }

        if(response.status===419&&!retried){
          setStatus('Refreshing secure session…',35,'info');
          var refreshed=await refreshCsrf(form);
          if(!refreshed) throw new Error('Your admin session could not be verified. Refresh this page, sign in again if needed, then retry the upload.');
          return sendUpload(true);
        }

        var raw=await response.text();
        var data;
        try{data=JSON.parse(raw);}catch(error){
          if(response.status===419) throw new Error('Your admin session has expired or changed. Refresh the page and sign in again before uploading.');
          throw new Error('The server returned an unexpected response. Please refresh the page and try again.');
        }
        if(!response.ok||!data.ok) throw new Error(data&&data.message?data.message:'Upload failed. Please try again.');
        return data;
      }

      form.addEventListener('submit',async function(event){
        if(busy) return;
        if(!input||!input.files||!input.files.length){
          event.preventDefault();
          setStatus('Please choose at least one image.',0,'error');
          return;
        }

        event.preventDefault();
        busy=true;
        if(button){button.disabled=true;button.textContent='Uploading…';}
        setStatus('Verifying secure session…',8,'info');

        try{
          var refreshed=await refreshCsrf(form);
          if(!refreshed) throw new Error('Could not refresh the secure session. Refresh this page and try again.');
          setStatus('Uploading images…',35,'info');
          var result=await sendUpload(false);
          setStatus(result.message||'Images uploaded successfully.',100,'success');
          if(button) button.textContent='Uploaded ✓';
          window.setTimeout(function(){window.location.reload();},650);
        }catch(error){
          setStatus(error.message||'Upload failed. Please try again.',0,'error');
          if(button){button.disabled=false;button.textContent='Upload Images';}
          busy=false;
        }
      });
    });
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
})();
