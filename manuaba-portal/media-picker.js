(function(){
  'use strict';
  if (window.__ALOHA_MEDIA_PICKER_V13__) return;
  window.__ALOHA_MEDIA_PICKER_V13__ = true;

  var overlay = null;
  var activeTarget = null;
  var activeMultiple = false;

  function isImageFileInput(input){
    if (!input || input.type !== 'file') return false;
    var accept = (input.getAttribute('accept') || '').toLowerCase();
    if (!accept) return false;
    return accept.indexOf('image/') !== -1 && accept.indexOf('video/') === -1;
  }

  function isImageTextInput(input){
    if (!input || !['text','url'].includes((input.type || '').toLowerCase())) return false;
    var name = (input.name || '').toLowerCase();
    return /(image|logo|favicon|background|poster|og_image)/.test(name);
  }

  function closePicker(){
    if (overlay) overlay.remove();
    overlay = null;
    activeTarget = null;
    activeMultiple = false;
  }

  function openPicker(target){
    closePicker();
    activeTarget = target;
    activeMultiple = !!target.multiple;

    overlay = document.createElement('div');
    overlay.id = 'aloha-media-picker-overlay';
    overlay.innerHTML = '<div class="amp-shell" role="dialog" aria-modal="true" aria-label="Choose from Media Library">'
      + '<iframe class="amp-frame" title="Choose from Media Library" src="media.php?picker=1&multiple=' + (activeMultiple ? '1' : '0') + '"></iframe>'
      + '</div>';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function(e){
      if (e.target === overlay) closePicker();
    });
  }

  function ensureStyles(){
    if (document.getElementById('aloha-media-picker-style-v11')) return;
    var style = document.createElement('style');
    style.id = 'aloha-media-picker-style-v11';
    style.textContent = [
      'input[data-media-hidden="1"]{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}',
      '#aloha-media-picker-overlay{position:fixed;inset:0;z-index:999999;background:rgba(9,18,28,.64);display:flex;align-items:center;justify-content:center;padding:18px;box-sizing:border-box}',
      '#aloha-media-picker-overlay .amp-shell{width:min(1180px,100%);height:min(88vh,860px);background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.28)}',
      '#aloha-media-picker-overlay .amp-frame{display:block;width:100%;height:100%;border:0;background:#f7f9fb}',
      '.media-library-trigger{display:flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;width:100%!important;margin:10px 0 0!important;padding:12px 18px!important;border:0!important;border-radius:11px!important;background:linear-gradient(135deg,#28764a,#17633e)!important;color:#fff!important;font:700 13px/1.2 inherit!important;cursor:pointer!important;box-shadow:0 8px 18px rgba(23,107,67,.18)!important;transition:transform .18s ease,box-shadow .18s ease,filter .18s ease!important}',
      '.media-library-trigger::before{content:"";display:inline-block;width:16px;height:16px;flex:0 0 16px;background:no-repeat center/contain url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2716%27 height=%2716%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27white%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3E%3Crect x=%273%27 y=%274%27 width=%2718%27 height=%2716%27 rx=%272%27/%3E%3Ccircle cx=%278.5%27 cy=%279.5%27 r=%272%27/%3E%3Cpath d=%27m21 15-5-5L5 20%27/%3E%3C/svg%3E")}',
      '.media-library-trigger:hover{transform:translateY(-1px)!important;box-shadow:0 11px 22px rgba(23,107,67,.25)!important;filter:brightness(1.04)!important}',
      '.media-library-trigger:active{transform:translateY(0)!important}',
      '.media-library-trigger:focus-visible{outline:3px solid rgba(190,231,126,.8)!important;outline-offset:2px!important}',
      '.media-selected-note{margin-top:6px;font-size:11px;color:#176b43;display:block}',
      '@media(max-width:700px){#aloha-media-picker-overlay{padding:0}#aloha-media-picker-overlay .amp-shell{width:100%;height:100%;border-radius:0}.media-library-trigger{width:100%!important;margin-top:10px!important}}'
    ].join('');
    document.head.appendChild(style);
  }

  function normalizeText(el){
    return (el.textContent || '').trim().replace(/\s+/g,' ').toLowerCase();
  }

  function removeDuplicateLibraryButtons(){
    var selectors = 'button, a, input[type=button], input[type=submit], input[type=reset]';
    var controls = document.querySelectorAll(selectors);
    controls.forEach(function(el){
      if (el.closest('#aloha-media-picker-overlay')) return;
      if (el.hasAttribute('data-media-library-primary')) return;
      var txt = normalizeText(el);
      if (el.tagName === 'INPUT') txt = (el.value || '').trim().replace(/\s+/g,' ').toLowerCase();
      if (txt === 'choose from media library') {
        el.setAttribute('data-media-legacy-hidden','1');
        el.style.setProperty('display','none','important');
      }
    });
  }

  function updateTextTarget(input, item){
    if (!item || !item.path) return;
    input.value = item.path;
    input.dispatchEvent(new Event('input', {bubbles:true}));
    input.dispatchEvent(new Event('change', {bubbles:true}));
    var note = input.parentElement && input.parentElement.querySelector('[data-media-selected-note="1"]');
    if (!note && input.parentElement){
      note = document.createElement('span');
      note.setAttribute('data-media-selected-note','1');
      note.className='media-selected-note';
      input.parentElement.appendChild(note);
    }
    if (note) note.textContent='Selected: '+(item.name || item.path);
  }

  function setMediaReference(input, items){
    var form=input.form;
    if(!form) return;
    var name=input.name || '';
    if(!name) return;
    var key='media_'+name.replace(/\[\]$/,'');
    form.querySelectorAll('input[data-media-reference="1"][name="'+CSS.escape(key)+'"]').forEach(function(el){el.remove();});
    var list=activeMultiple ? items : [items[0]];
    list.forEach(function(item){
      var hidden=document.createElement('input');
      hidden.type='hidden'; hidden.name=key+(activeMultiple?'[]':''); hidden.value=item.path || '';
      hidden.setAttribute('data-media-reference','1');
      form.appendChild(hidden);
    });
    input.value='';
    input.dispatchEvent(new Event('change',{bubbles:true}));
    var note=input.parentElement && input.parentElement.querySelector('[data-media-selected-note="1"]');
    if(!note && input.parentElement){
      note=document.createElement('span'); note.setAttribute('data-media-selected-note','1'); note.className='media-selected-note'; input.parentElement.appendChild(note);
    }
    if(note) note.textContent='Selected from Media Library: '+list.length+' image'+(list.length===1?'':'s');
    closePicker();
  }

  function updateFileTarget(input, items){
    if(!items || !items.length) return;
    try { setMediaReference(input, items); }
    catch(err){ console.error('[Aloha Media Picker]',err); alert('The image could not be selected from Media Library. Please try again.'); }
  }

  async function receive(items){
    if (!activeTarget || !items || !items.length) return;
    if (activeTarget.type === 'file') await updateFileTarget(activeTarget, items);
    else { updateTextTarget(activeTarget, items[0]); closePicker(); }
  }

  window.AlohaMediaPicker = {receive:receive, close:closePicker, open:openPicker};

  window.addEventListener('message', function(event){
    if (event.origin !== window.location.origin) return;
    var data = event.data || {};
    if (data.source !== 'aloha-media-library') return;
    if (data.type === 'media-selected') receive(Array.isArray(data.items) ? data.items : []);
    if (data.type === 'media-cancelled') closePicker();
  });

  function decorate(){
    ensureStyles();
    removeDuplicateLibraryButtons();

    var inputs = Array.from(document.querySelectorAll('input[type="file"],input[type="text"],input[type="url"]'));
    inputs.forEach(function(input){
      if (input.dataset.mediaPickerBound === '1') return;
      if (!(isImageFileInput(input) || isImageTextInput(input))) return;
      if (input.closest('#aloha-media-picker-overlay')) return;

      if (input.type === 'file') {
        input.dataset.mediaHidden='1';
        input.setAttribute('aria-hidden','true');
      }

      input.dataset.mediaPickerBound='1';
      var btn=document.createElement('button');
      btn.type='button';
      btn.className='media-library-trigger';
      btn.setAttribute('data-media-library-primary','1');
      btn.setAttribute('aria-label','Choose an image from Media Library');
      btn.innerHTML='<span>Choose from Media Library</span>';
      btn.addEventListener('click',function(){openPicker(input);});
      input.insertAdjacentElement('afterend',btn);
    });
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',decorate);
  else decorate();

  // Remove duplicate legacy buttons that may be injected after initial page load.
  if (window.MutationObserver) {
    var observer = new MutationObserver(function(){ removeDuplicateLibraryButtons(); });
    observer.observe(document.body, {childList:true, subtree:true});
  }
})();
