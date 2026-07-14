(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Mobil menü (çekmece + karartma) ---------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  var backdrop = document.getElementById('nav-backdrop');

  function openNav() {
    nav.classList.add('is-open');
    toggle.classList.add('is-active');
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('nav-open');
    if (backdrop) {
      backdrop.hidden = false;
      requestAnimationFrame(function () { backdrop.classList.add('is-visible'); });
    }
  }

  function closeNav() {
    nav.classList.remove('is-open');
    toggle.classList.remove('is-active');
    toggle.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('nav-open');
    if (backdrop) {
      backdrop.classList.remove('is-visible');
      setTimeout(function () { backdrop.hidden = true; }, 350);
    }
  }

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.contains('is-open') ? closeNav() : openNav();
    });
    if (backdrop) backdrop.addEventListener('click', closeNav);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) closeNav();
    });
    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', closeNav);
    });
  }

  /* ---------- Hizmetler alt menüsü (mobilde akordeon, masaüstünde hover) ---------- */
  document.querySelectorAll('.nav__dropdown-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var dd = btn.closest('.nav__dropdown');
      var open = dd.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Dışarı tıklanınca açık dropdown'ları kapat (masaüstü).
  document.addEventListener('click', function (e) {
    document.querySelectorAll('.nav__dropdown.is-open').forEach(function (dd) {
      if (!dd.contains(e.target)) {
        dd.classList.remove('is-open');
        var b = dd.querySelector('.nav__dropdown-toggle');
        if (b) b.setAttribute('aria-expanded', 'false');
      }
    });
  });

  /* ---------- Flash mesajı kapatma + otomatik gizleme ---------- */
  document.querySelectorAll('.flash').forEach(function (el) {
    var close = el.querySelector('.flash__close');
    if (close) close.addEventListener('click', function () { el.remove(); });
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 6000);
  });

  /* ---------- Header gölgesi (scroll) ---------- */
  var header = document.getElementById('site-header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 10);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Scroll'da beliren içerikler ---------- */
  var revealSelectors = [
    '.section__head', '.card', '.stat', '.why__text', '.faq__item',
    '.masonry__item', '.contact-info__item', '.contact-form', '.prose',
    '.cta__inner', '.footer__col'
  ];
  var revealEls = document.querySelectorAll(revealSelectors.join(','));

  if (!reduceMotion && 'IntersectionObserver' in window && revealEls.length) {
    revealEls.forEach(function (el) { el.classList.add('reveal'); });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        // Aynı grid/kapsayıcıdaki kardeşlere kademeli gecikme uygula.
        var parent = el.parentElement;
        var siblings = parent ? Array.prototype.filter.call(parent.children, function (c) {
          return c.classList.contains('reveal');
        }) : [el];
        var idx = siblings.indexOf(el);
        el.style.transitionDelay = Math.min(idx * 70, 420) + 'ms';
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
      var hasDots = numStr.indexOf('.') !== -1; // 5.000 → binlik ayracı
      var target = parseInt(numStr.replace(/\./g, ''), 10);
      if (!target) return;
      var dur = 1400, start = null;
      var fmt = function (n) {
        return hasDots ? n.toLocaleString('tr-TR') : String(n);
      };
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
