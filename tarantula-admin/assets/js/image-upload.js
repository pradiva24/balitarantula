(function(){
  'use strict';
  const MAX_SOURCE = 20 * 1024 * 1024;
  const MAX_DIM = 2400;
  const TARGET = 5 * 1024 * 1024;
  const TYPES = ['image/jpeg','image/png','image/webp','image/gif'];

  function loadImage(file){
    return new Promise((resolve,reject)=>{
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = ()=>{ URL.revokeObjectURL(url); resolve(img); };
      img.onerror = ()=>{ URL.revokeObjectURL(url); reject(new Error('Browser could not decode image')); };
      img.src = url;
    });
  }

  async function compress(file){
    if (!file || !TYPES.includes(file.type) || file.size <= 2 * 1024 * 1024) return file;

    const bitmap = await loadImage(file);
    let w = bitmap.naturalWidth || bitmap.width;
    let h = bitmap.naturalHeight || bitmap.height;
    if (!w || !h) throw new Error('Invalid image dimensions');

    const scale = Math.min(1, MAX_DIM / w, MAX_DIM / h);
    w = Math.max(1, Math.round(w * scale));
    h = Math.max(1, Math.round(h * scale));

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d', {alpha:false});
    if (!ctx) throw new Error('Canvas unavailable');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(bitmap, 0, 0, w, h);

    // Use JPEG, not WebP: this MAMP installation has no WebP encoder.
    let quality = 0.82;
    let blob = null;
    for(let i=0;i<5;i++){
      blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
      if (blob && blob.size <= TARGET) break;
      quality -= 0.10;
      if (quality < 0.42) quality = 0.42;
    }

    if (!blob) throw new Error('Browser could not create JPEG');
    if (blob.size >= file.size && file.size <= TARGET) return file;

    const base = file.name.replace(/\.[^.]+$/i,'');
    return new File([blob], base + '.jpg', {
      type:'image/jpeg',
      lastModified:file.lastModified
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form[enctype="multipart/form-data"]:not([data-media-upload="1"]):not(.picker-upload)').forEach(function(form){
      form.addEventListener('submit', async function(ev){
        if(form.dataset.compressing === '1') return;

        const inputs = [...form.querySelectorAll('input[type="file"]')]
          .filter(i => i.files && i.files.length);
        const files = inputs.flatMap(i => [...i.files]);
        const images = files.filter(f => TYPES.includes(f.type));
        const tooLarge = images.some(f => f.size > MAX_SOURCE);

        if (tooLarge) {
          ev.preventDefault();
          alert('Gambar maksimal 20 MB per file.');
          return;
        }

        const needCompress = images.some(f => f.size > 2 * 1024 * 1024);
        if (!needCompress) return;

        ev.preventDefault();
        form.dataset.compressing = '1';
        const button = form.querySelector('button[type="submit"],button:not([type])');
        const oldText = button ? button.textContent : '';
        if(button){ button.disabled = true; button.textContent = 'Compressing image…'; }

        try {
          for(const input of inputs){
            const dt = new DataTransfer();
            for(const f of [...input.files]) dt.items.add(await compress(f));
            input.files = dt.files;
          }
          form.submit();
        } catch(e) {
          form.dataset.compressing = '';
          if(button){ button.disabled = false; button.textContent = oldText; }
          alert('Gambar besar tidak dapat diproses oleh browser. Coba JPG/JPEG dengan ukuran maksimal 20 MB.');
        }
      });
    });
  });
})();
