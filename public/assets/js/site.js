(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Paper Kit mobil menü düzeltmesi ----------
     PK, panel içine yapılan HER tıklamada menüyü kapatır; bu da
     "Hizmetler" alt menüsü açılırken panelin kaymasına yol açar.
     Bu davranışı kaldırıp kapatmayı yalnızca gerçek linklere bağlarız. */
  if (window.jQuery) {
    // PK kendi handler'ını DOM ready'de bağlar; kaldırma işlemi ondan sonra çalışmalı.
    window.jQuery(function ($) {
      $('.navbar-collapse').off('click');
      $('.navbar-collapse').on('click', 'a[href]:not(.dropdown-toggle)', function () {
        $('html').removeClass('nav-open');
        if (window.pk && pk.misc) pk.misc.navbar_menu_visible = 0;
        $('#bodyClick').remove();
        setTimeout(function () { $('.navbar-toggler').removeClass('toggled'); }, 550);
      });
    });
  }

  /* ---------- Flash mesajlarını otomatik gizle ---------- */
  document.querySelectorAll('.flash-wrap .alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 6000);
  });

  /* ---------- Scroll'da beliren içerikler ---------- */
  var revealSelectors = [
    '.section-head', '.card-service', '.stat', '.why-list li', '.card-faq',
    '.masonry__item', '.contact-info__item', '.card-contact-form', '.card-prose'
  ];
  var revealEls = document.querySelectorAll(revealSelectors.join(','));

  if (!reduceMotion && 'IntersectionObserver' in window && revealEls.length) {
    revealEls.forEach(function (el) { el.classList.add('reveal'); });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var parent = el.parentElement;
        var siblings = parent ? Array.prototype.filter.call(parent.children, function (c) {
          return c.classList.contains('reveal');
        }) : [el];
        var idx = siblings.indexOf(el);
        el.style.transitionDelay = Math.min(Math.max(idx, 0) * 70, 420) + 'ms';
        el.classList.add('is-visible');
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(function (el) { io.observe(el); });
  }

  /* ---------- İstatistik sayaçları ---------- */
  var stats = document.querySelectorAll('.stat strong');
  if (!reduceMotion && 'IntersectionObserver' in window && stats.length) {
    var animateCount = function (el) {
      var text = el.textContent.trim();
      var m = text.match(/^([^\d]*)([\d.]+)(.*)$/);
      if (!m) return; // "Aynı Gün" gibi sayısız metinleri atla
      var prefix = m[1], numStr = m[2], suffix = m[3];
      var hasDots = numStr.indexOf('.') !== -1;
      var target = parseInt(numStr.replace(/\./g, ''), 10);
      if (!target) return;
      var dur = 1400, start = null;
      var fmt = function (n) { return hasDots ? n.toLocaleString('tr-TR') : String(n); };
      var step = function (ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + fmt(Math.round(target * eased)) + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    var statIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        animateCount(entry.target);
        statIo.unobserve(entry.target);
      });
    }, { threshold: 0.5 });

    stats.forEach(function (el) { statIo.observe(el); });
  }
})();
