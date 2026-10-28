(function () {
  var btn = document.getElementById('themeBtn');
  if (btn) btn.addEventListener('click', function () {
    var n = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', n); localStorage.setItem('pe-theme', n);
  });
  // Live elapsed counters: <span class="elapsed" data-in="unix_seconds">
  var skew = (window.PE_SERVER_NOW ? window.PE_SERVER_NOW * 1000 - Date.now() : 0);
  function tick() {
    document.querySelectorAll('.elapsed').forEach(function (el) {
      var s = Math.max(0, Math.floor((Date.now() + skew) / 1000 - Number(el.dataset.in)));
      el.textContent = Math.floor(s / 3600) + 'h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + 'm ' + String(s % 60).padStart(2, '0') + 's';
    });
  }
  tick(); setInterval(tick, 1000);
  document.querySelectorAll('.vnum').forEach(function (i) {
    i.addEventListener('input', function () { i.value = i.value.toUpperCase().replace(/[^A-Z0-9]/g, ''); });
  });
})();
