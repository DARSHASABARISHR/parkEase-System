(function () {
  // Show/hide password: <button type="button" data-toggle-pass="inputId">
  document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.togglePass); if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      var i = btn.querySelector('i'); if (i) i.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
    });
  });
  // Captcha reload: <button data-captcha-reload="imgId" data-src="url?new=1">
  document.querySelectorAll('[data-captcha-reload]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var img = document.getElementById(btn.dataset.captchaReload); if (!img) return;
      img.src = btn.dataset.src + '&t=' + Date.now();
      var f = document.getElementById('captcha'); if (f) { f.value = ''; f.focus(); }
    });
  });
})();
