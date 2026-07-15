/**
 * Çözüm Oto — animasyonlu bildirim (toast) yardımcısı. Toastify.js sarmalayıcısı.
 * Kullanım: showToast('Kaydedildi', 'success')  |  window.__flash otomatik gösterilir.
 */
(function (w) {
  'use strict';

  var ICONS = {
    success: '<i class="fa fa-check-circle"></i>',
    error:   '<i class="fa fa-exclamation-circle"></i>',
    info:    '<i class="fa fa-info-circle"></i>',
    warning: '<i class="fa fa-exclamation-triangle"></i>'
  };

  w.showToast = function (message, type) {
    type = type || 'info';
    if (typeof w.Toastify !== 'function') {
      return;
    }
    w.Toastify({
      text: (ICONS[type] || ICONS.info) + '<span>' + message + '</span>',
      escapeMarkup: false,
      duration: 4500,
      close: true,
      gravity: 'top',
      position: 'right',
      stopOnFocus: true,
      className: 'cz-toast cz-toast--' + type
    }).showToast();
  };

  // Sunucudan gelen flash mesajlarını sayfa yüklenince toast olarak göster.
  function initFlash() {
    var list = w.__flash;
    if (!list || !list.length) {
      return;
    }
    list.forEach(function (f, i) {
      setTimeout(function () {
        w.showToast(f.message, f.type === 'error' ? 'error' : (f.type || 'success'));
      }, i * 350);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlash);
  } else {
    initFlash();
  }
})(window);
