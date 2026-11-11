document.addEventListener('DOMContentLoaded',function(){
 const sidebar=document.getElementById('sidebar'), overlay=document.getElementById('mobileOverlay');
 const open=()=>{sidebar.classList.add('open');overlay.classList.add('show');document.body.classList.add('menu-open')};
 const close=()=>{sidebar.classList.remove('open');overlay.classList.remove('show');document.body.classList.remove('menu-open')};
 document.getElementById('menuToggle')?.addEventListener('click',open);
 document.getElementById('sidebarClose')?.addEventListener('click',close);
 overlay?.addEventListener('click',close);
 document.querySelectorAll('.sidebar a').forEach(a=>a.addEventListener('click',()=>{if(innerWidth<=900)close()}));
 document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));
});
