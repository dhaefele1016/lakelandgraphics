/* Lakeland Graphics — shared site behaviour */
(function () {
  // --- Accent persistence (set by Tweaks panel, shared across pages) ---
  try {
    var saved = localStorage.getItem('lg-accent2');
    if (saved === 'cyan' || saved === 'magenta' || saved === 'green') {
      if (saved === 'magenta') document.documentElement.removeAttribute('data-accent');
      else document.documentElement.setAttribute('data-accent', saved);
    }
  } catch (e) {}

  // --- Mobile menu ---
  var btn = document.getElementById('menuBtn');
  var nav = document.getElementById('mobileNav');
  var header = document.querySelector('.header');
  if (btn && nav) {
    // Replace whatever icon markup the page shipped with a morphing burger
    btn.innerHTML = '<span class="burger"><span></span><span></span><span></span></span>';

    // The header uses backdrop-filter, which traps position:fixed descendants
    // in its own (short) containing block. Move the drawer to <body> so it can
    // fill the viewport.
    document.body.appendChild(nav);

    // Backdrop
    var backdrop = document.createElement('div');
    backdrop.className = 'nav-backdrop';
    document.body.appendChild(backdrop);

    var setTop = function () {
      var h = header ? header.getBoundingClientRect().height : 84;
      document.documentElement.style.setProperty('--nav-top', h + 'px');
    };

    var open = function () {
      setTop();
      nav.classList.add('open');
      backdrop.classList.add('open');
      btn.classList.add('is-open');
      btn.setAttribute('aria-label', 'Close menu');
      btn.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    };
    var close = function () {
      nav.classList.remove('open');
      backdrop.classList.remove('open');
      btn.classList.remove('is-open');
      btn.setAttribute('aria-label', 'Open menu');
      btn.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    };
    btn.setAttribute('aria-expanded', 'false');
    btn.addEventListener('click', function () {
      nav.classList.contains('open') ? close() : open();
    });
    backdrop.addEventListener('click', close);
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('open')) close();
    });
    // If the viewport grows past the mobile breakpoint, reset state
    window.addEventListener('resize', function () {
      if (window.innerWidth > 768 && nav.classList.contains('open')) close();
    });
  }

  // --- Shrink header on scroll ---
  if (header) {
    var shrinkAt = 48;
    var onScroll = function () {
      if (window.scrollY > shrinkAt) header.classList.add('is-scrolled');
      else header.classList.remove('is-scrolled');
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // --- Reveal on scroll (with bulletproof fallbacks) ---
  var els = document.querySelectorAll('.reveal');
  function showAll() { els.forEach(function (el) { el.classList.add('in'); }); }

  if ('IntersectionObserver' in window && els.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) { io.observe(el); });

    // Reveal anything already in the viewport right away
    var vh = window.innerHeight || 800;
    els.forEach(function (el) {
      if (el.getBoundingClientRect().top < vh * 0.92) el.classList.add('in');
    });
    // Safety net: if IO never fires (some embeds), reveal everything
    setTimeout(showAll, 1400);
  } else {
    showAll();
  }
})();
