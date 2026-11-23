document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm)){e.preventDefault();e.stopPropagation()}}));
  document.querySelectorAll('[data-currency]').forEach(el=>el.addEventListener('input',()=>{const n=Number(el.value);if(el.value!==''&&(!Number.isFinite(n)||n<0))el.setCustomValidity('Masukkan angka nol atau lebih.');else el.setCustomValidity('')}));
});
