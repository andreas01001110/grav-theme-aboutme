(function () {
  var root = document.documentElement;

  // Hell/Dunkel umschalten und die Wahl des Besuchers merken
  document.querySelectorAll('[data-mode-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dark = root.getAttribute('data-theme') !== 'dark';
      root.setAttribute('data-theme', dark ? 'dark' : 'light');
      try { localStorage.setItem('nh-theme', dark ? 'dark' : 'light'); } catch (e) {}
      document.querySelectorAll('[data-mode-toggle]').forEach(function (b) {
        b.setAttribute('aria-pressed', dark ? 'true' : 'false');
      });
    });
    btn.setAttribute('aria-pressed', root.getAttribute('data-theme') === 'dark' ? 'true' : 'false');
  });

  // Sprachwahl merken: Wer DE oder EN anklickt, wird danach nicht mehr automatisch umgeleitet.
  document.querySelectorAll('.lang-switch a[hreflang]').forEach(function (a) {
    a.addEventListener('click', function () {
      var code = a.getAttribute('hreflang');
      if (code) {
        document.cookie = 'aboutme_lang=' + encodeURIComponent(code) + '; path=/; max-age=31536000; SameSite=Lax';
      }
    });
  });

  // E-Mail-Adressen: stehen im HTML nur kodiert (Schutz vor Adress-Sammlern, siehe aboutme.php)
  // und werden hier für Besucher wieder zu normalen mailto-Links.
  document.querySelectorAll('a.nh-mail[data-m]').forEach(function (a) {
    try {
      var mail = atob(a.getAttribute('data-m')).split('').reverse().join('');
      var query = a.getAttribute('data-q') ? atob(a.getAttribute('data-q')) : '';
      a.setAttribute('href', 'mailto:' + mail + query);
      if (a.getAttribute('data-t')) { a.textContent = mail; }
      a.removeAttribute('rel');
    } catch (e) {}
  });

  // Menü auf dem Handy
  var burger = document.querySelector('[data-menu-toggle]');
  var panel = document.getElementById('mobile-menu');
  if (burger && panel) {
    var close = function () {
      panel.classList.remove('open');
      burger.setAttribute('aria-expanded', 'false');
    };
    burger.addEventListener('click', function () {
      var open = !panel.classList.contains('open');
      panel.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    panel.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  // Menüleiste: Sobald der Name nicht mehr vollständig passt, Stufe für Stufe kompakter werden:
  // 1. mobile Variante (Menü hinter dem Burger), 2. DE/EN ins Handy-Menü,
  // 3. Name etwas kleiner, 4. nur Monogramm.
  var nav = document.querySelector('.nav');
  var brandName = document.querySelector('.brand-name');
  if (nav && brandName) {
    var steps = ['nav-compact', 'nav-tight', 'nav-small', 'nav-mini'];
    var fits = function () {
      return brandName.scrollWidth <= brandName.clientWidth + 1 && nav.scrollWidth <= nav.clientWidth + 1;
    };
    var fitNav = function () {
      steps.forEach(function (s) { root.classList.remove(s); });
      for (var i = 0; i < steps.length && !fits(); i++) {
        root.classList.add(steps[i]);
      }
      // Wird die Leiste wieder breit genug, das offene Handy-Menü schließen
      if (!root.classList.contains('nav-compact') && panel) {
        panel.classList.remove('open');
        if (burger) burger.setAttribute('aria-expanded', 'false');
      }
    };
    var timer;
    window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(fitNav, 60); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitNav);
    fitNav();
  }

  // Bild-Slider ganz oben auf der Seite: so weit nach oben ziehen, dass er am Fensterrand beginnt
  // (unter der schwebenden Menüleiste hindurch). Abstand wird gemessen, damit es bei jeder Leistenhöhe passt.
  var topSlider = document.querySelector('main > .hero-slider:first-child');
  if (topSlider) {
    var pull = function () {
      topSlider.style.marginTop = '0px';
      var top = topSlider.getBoundingClientRect().top + window.scrollY;
      topSlider.style.marginTop = '';
      root.style.setProperty('--slider-pull', Math.max(0, Math.round(top)) + 'px');
    };
    pull();
    window.addEventListener('resize', pull);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(pull);
  }

  // Karussells (Bild-Slider und News): Pfeile, Punkte und optional automatisches Weiterblättern.
  // Grundlage ist ein waagerecht scrollender Streifen; ohne JavaScript lässt er sich wischen.
  document.querySelectorAll('[data-carousel]').forEach(function (box) {
    var track = box.querySelector('[data-track]');
    if (!track) return;
    var items = Array.prototype.slice.call(track.children);
    var dots = Array.prototype.slice.call(box.querySelectorAll('[data-goto]'));
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var step = function () { return items.length > 1 ? items[1].offsetLeft - items[0].offsetLeft : track.clientWidth; };
    var current = function () { return Math.round(track.scrollLeft / Math.max(step(), 1)); };
    var maxIndex = function () { return Math.max(0, Math.round((track.scrollWidth - track.clientWidth) / Math.max(step(), 1))); };
    var go = function (i) {
      var max = maxIndex();
      if (i > max) i = 0;
      if (i < 0) i = max;
      track.scrollTo({ left: i * step(), behavior: reduce ? 'auto' : 'smooth' });
    };
    var mark = function () {
      var i = current();
      dots.forEach(function (d, n) { d.setAttribute('aria-current', n === i ? 'true' : 'false'); });
    };
    box.querySelectorAll('[data-prev]').forEach(function (b) { b.addEventListener('click', function () { go(current() - 1); }); });
    box.querySelectorAll('[data-next]').forEach(function (b) { b.addEventListener('click', function () { go(current() + 1); }); });
    dots.forEach(function (d) { d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-goto'), 10) || 0); }); });
    var t;
    track.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(mark, 80); }, { passive: true });
    mark();

    var seconds = parseInt(box.getAttribute('data-autoplay'), 10) || 0;
    if (seconds > 0 && !reduce && items.length > 1) {
      var timer = null;
      var paused = false;
      var start = function () { stop(); timer = setInterval(function () { if (!paused && !document.hidden) go(current() + 1); }, seconds * 1000); };
      var stop = function () { if (timer) clearInterval(timer); timer = null; };
      box.addEventListener('mouseenter', function () { paused = true; });
      box.addEventListener('mouseleave', function () { paused = false; });
      box.addEventListener('focusin', function () { paused = true; });
      box.addEventListener('focusout', function () { paused = false; });
      track.addEventListener('pointerdown', function () { start(); });
      start();
    }
  });

  // Galerie: Bilder groß anzeigen und blättern. Ohne JavaScript öffnet der Link das Bild direkt.
  var links = Array.prototype.slice.call(document.querySelectorAll('a[data-lightbox]'));
  if (links.length && typeof HTMLDialogElement === 'function') {
    var svg = function (d) {
      return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    };
    // Beschriftungen kommen übersetzt aus den data-Attributen des body
    var label = function (key, fallback) { return document.body.getAttribute('data-lb-' + key) || fallback; };
    var box = document.createElement('dialog');
    box.className = 'lightbox';
    box.setAttribute('aria-label', label('view', 'Bildansicht'));
    box.innerHTML =
      '<figure><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="lb-btn lb-close">' + svg('M6 6l12 12M18 6 6 18') + '</button>' +
      '<button type="button" class="lb-btn lb-prev">' + svg('m15 5-7 7 7 7') + '</button>' +
      '<button type="button" class="lb-btn lb-next">' + svg('m9 5 7 7-7 7') + '</button>';
    box.querySelector('.lb-close').setAttribute('aria-label', label('close', 'Schließen'));
    box.querySelector('.lb-prev').setAttribute('aria-label', label('prev', 'Vorheriges Bild'));
    box.querySelector('.lb-next').setAttribute('aria-label', label('next', 'Nächstes Bild'));
    document.body.appendChild(box);
    var img = box.querySelector('img');
    var cap = box.querySelector('figcaption');
    var current = 0;

    // Nie über die echte Auflösung hinaus vergrößern: Bei Bildschirm-Skalierung (z. B. 125 %, 150 %)
    // würde der Browser das Bild sonst hochrechnen und Schrift in Screenshots unscharf darstellen.
    img.addEventListener('load', function () {
      var dpr = window.devicePixelRatio || 1;
      var w = Math.round(img.naturalWidth / dpr);
      var h = Math.round(img.naturalHeight / dpr);
      img.style.maxWidth = 'min(100%, ' + w + 'px)';
      img.style.maxHeight = 'min(calc(100vh - 150px), ' + h + 'px)';
    });

    var show = function (i) {
      current = (i + links.length) % links.length;
      var a = links[current];
      img.style.maxWidth = '';
      img.style.maxHeight = '';
      img.src = a.getAttribute('href');
      img.alt = (a.querySelector('img') || {}).alt || '';
      cap.textContent = a.getAttribute('data-caption') || '';
    };
    links.forEach(function (a, i) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        show(i);
        box.showModal();
      });
    });
    box.querySelector('.lb-close').addEventListener('click', function () { box.close(); });
    box.querySelector('.lb-prev').addEventListener('click', function () { show(current - 1); });
    box.querySelector('.lb-next').addEventListener('click', function () { show(current + 1); });
    box.addEventListener('click', function (e) { if (e.target === box || e.target.tagName === 'FIGURE') box.close(); });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });
    box.addEventListener('close', function () { img.removeAttribute('src'); links[current].focus(); });
  }
})();
