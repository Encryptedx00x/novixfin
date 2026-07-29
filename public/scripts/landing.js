/* NovixFin — Landing "Seletor Seta" (porte fiel do design aprovado).
   Fonte visual da verdade: Claude Design "NovixFin - Landing Seletor Seta.html".
   Uma fonte de estado (theme atual): setas da hero + seletor da seção "15 jeitos"
   + carrossel ficam em sincronia. Troca via View Transitions API (fallback direto,
   prefers-reduced-motion sem morph). Persistência: localStorage['novixfin-theme']. */
(function () {
  'use strict';

  // ---- dados dos 15 temas (copy + fontes + cores dos mini-cards) ----
  var themes = [
    { id: 'ametista', name: 'Ametista', tag: 'pedra lapidada · claro', c1: '#F0EDF5', c2: '#8803FC', fd: "'Familjen Grotesk', sans-serif",
      h1a: 'Bruto entra. Lapidado ', h1acc: 'fica', h1post: '.', sub: 'Lance cada gasto em segundos e veja o mês bruto virar patrimônio: previsão de saldo, metas que avançam faceta a faceta e cartões sob controle.',
      fonts: 'family=Familjen+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700' },
    { id: 'cafe', name: 'Café Coado', tag: 'claro quente · humano', c1: '#F6EFE2', c2: '#B65437', fd: "'Sora', sans-serif",
      h1a: 'Dinheiro claro, cabeça ', h1acc: 'leve', h1post: '.', sub: 'Lance o gasto na hora do café, veja quanto ainda pode gastar sem medo e chegue ao fim do mês sem susto, em 3 segundos por lançamento.',
      fonts: 'family=Sora:wght@400;600;700;800&family=Hanken+Grotesk:wght@400;500;600;700' },
    { id: 'sereno2', name: 'Sereno v2', tag: 'zen escuro · números limpos', c1: '#101413', c2: '#A9C7A9', fd: "'Instrument Serif', serif",
      h1a: 'Respire. Está tudo ', h1acc: 'anotado', h1post: '.', sub: 'O NovixFin tira o peso do dinheiro dos seus ombros: lance em segundos, veja o que vem pela frente e durma sabendo quanto sobra.',
      fonts: 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700' },
    { id: 'cofre', name: 'Azul-Cofre', tag: 'navy bancário · seguro', c1: '#0A1120', c2: '#3D7BFD', fd: "'Red Hat Display', sans-serif",
      h1a: 'O cofre é seu. As chaves ', h1acc: 'também', h1post: '.', sub: 'Saldos consolidados, previsão de 90 dias e cada centavo rastreável: a firmeza de um banco, sem um banco olhando por cima do seu ombro.',
      fonts: 'family=Red+Hat+Display:wght@500;700;800&family=Red+Hat+Text:wght@400;500;600;700&family=Red+Hat+Mono:wght@400;500;700' },
    { id: 'tinta', name: 'Tinta & Recibo', tag: 'extrato vivo · terminal calmo', c1: '#0E100A', c2: '#C6F135', fd: "'Space Grotesk', sans-serif",
      h1a: 'Seu dinheiro, ', h1acc: 'preto no branco', h1post: '.', sub: 'Lance cada gasto em segundos e veja o mês inteiro como um extrato vivo, com previsão do saldo antes de ele acontecer.',
      fonts: 'family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500;600' },
    { id: 'banca', name: 'Banca de Jornal', tag: 'editorial impresso · claro', c1: '#F7F4EC', c2: '#B3261E', fd: "'Newsreader', serif",
      h1a: 'Suas finanças, ', h1acc: 'em edição extra', h1post: '.', sub: 'Cada lançamento vira notícia clara: o que entrou, o que saiu, o que vem pela frente. Leia o seu mês como a primeira página do seu jornal.',
      fonts: 'family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Archivo:wght@400;500;600;700;800' },
    { id: 'suica', name: 'Grade Suíça', tag: 'grid frio · funcional', c1: '#F2F3F5', c2: '#2038E4', fd: "'Instrument Sans', sans-serif",
      h1a: 'Clareza absoluta, ', h1acc: 'centavo por centavo', h1post: '.', sub: 'Sem ruído, sem enfeite, sem susto. Registre em segundos e leia o seu mês num grid que não mente.',
      fonts: 'family=Instrument+Sans:wght@400;500;600;700' },
    { id: 'pulso', name: 'Suíça com Pulso', tag: 'grid com calor', c1: '#F4F3EE', c2: '#2038E4', fd: "'Schibsted Grotesk', sans-serif",
      h1a: 'Clareza que também tem ', h1acc: 'pulso', h1post: '.', sub: 'O rigor de um grid que não mente, com o calor de quem entende que dinheiro também é emoção.',
      fonts: 'family=Schibsted+Grotesk:wght@400;500;700;800&family=IBM+Plex+Mono:wght@400;500;600' },
    { id: 'concreto', name: 'Concreto', tag: 'brutal · sem enfeite', c1: '#EFECE3', c2: '#FF4D24', fd: "'Archivo', sans-serif",
      h1a: 'Sem filtro. Seu dinheiro ', h1acc: 'na real', h1post: '.', sub: 'Números grandes, verdade dura e um botão que faz o que promete. Lance qualquer gasto em segundos, sem cerimônia.',
      fonts: 'family=Archivo:wght@400;700;900&family=Space+Mono:wght@400;700' },
    { id: 'adega', name: 'Adega', tag: 'bordô de guarda · premium', c1: '#241018', c2: '#D9A441', fd: "'Marcellus', serif",
      h1a: 'Patrimônio se faz como vinho: ', h1acc: 'com tempo', h1post: '.', sub: 'Guarde, acompanhe e veja seus objetivos amadurecerem: cada lançamento é uma garrafa a mais na sua adega.',
      fonts: 'family=Marcellus&family=Figtree:wght@400;500;600;700' },
    { id: 'vitrola', name: 'Vitrola', tag: 'retrô 70s · quente', c1: '#F3E7CE', c2: '#C9642F', fd: "'Young Serif', serif",
      h1a: 'Suas finanças no ', h1acc: 'ritmo certo', h1post: '.', sub: 'Sem correria, sem planilha fria. Lance, acompanhe e celebre cada meta batida como um disco novo na vitrola.',
      fonts: 'family=Young+Serif&family=Karla:wght@400;500;700' },
    { id: 'cabine', name: 'Cabine', tag: 'cockpit noturno · preciso', c1: '#0B0D11', c2: '#FF8A3D', fd: "'Chakra Petch', sans-serif",
      h1a: 'Pilote seu dinheiro com ', h1acc: 'precisão de instrumento', h1post: '.', sub: 'Todos os sistemas nominais: saldo, previsão e metas monitorados como painéis de voo, leitura em um relance.',
      fonts: 'family=Chakra+Petch:wght@500;600;700&family=JetBrains+Mono:wght@400;500;700' },
    { id: 'sumie', name: 'Sumi-ê', tag: 'papel e tinta · calmo', c1: '#F5F1E8', c2: '#35507B', fd: "'Shippori Mincho', serif",
      h1a: 'O dinheiro também pede ', h1acc: 'quietude', h1post: '.', sub: 'Sem alarde, sem urgência artificial. Registre em segundos e deixe que a clareza apareça, como tinta assentando no papel.',
      fonts: 'family=Shippori+Mincho:wght@500;600;700&family=Zen+Kaku+Gothic+New:wght@400;500;700' },
    { id: 'deco', name: 'Salão Déco', tag: 'ouro sobre preto · gala', c1: '#111111', c2: '#C8A24B', fd: "'Poiret One', sans-serif",
      h1a: 'A elegância de saber exatamente ', h1acc: 'quanto você tem', h1post: '.', sub: 'Lance em segundos, acompanhe com clareza e celebre cada meta como uma noite de gala. Finanças com classe.',
      fonts: 'family=Poiret+One&family=Jost:wght@400;500;600;700' },
    { id: 'sereno1', name: 'Sereno Clássico', tag: 'o zen original', c1: '#101413', c2: '#A9C7A9', fd: "'Instrument Serif', serif",
      h1a: 'Respire. Está tudo ', h1acc: 'anotado', h1post: '.', sub: 'O NovixFin tira o peso do dinheiro dos seus ombros: lance em segundos, veja o que vem pela frente e durma sabendo quanto sobra.',
      fonts: 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700' }
  ];
  var selInk = { ametista: ['#241C33', '#6E6580'], cafe: ['#2B2016', '#8B7A67'], sereno2: ['#F1EDE2', '#8F9A92'], sereno1: ['#F1EDE2', '#8F9A92'], cofre: ['#E9EFF8', '#8CA0BC'], tinta: ['#EDEEE3', '#9A9E8C'], banca: ['#1B1810', '#6F6858'], suica: ['#0C0D10', '#5D6470'], pulso: ['#111216', '#5E6169'], concreto: ['#141310', '#5B574A'], adega: ['#F4E8D6', '#C3A296'], vitrola: ['#33261A', '#8A7358'], cabine: ['#E3E9F0', '#7E8A99'], sumie: ['#26241F', '#7D786C'], deco: ['#EFE6D0', '#9C9484'] };
  var selOrder = ['ametista', 'cafe', 'sereno2', 'sereno1', 'cofre', 'tinta', 'banca', 'suica', 'pulso', 'concreto', 'adega', 'vitrola', 'cabine', 'sumie', 'deco'];

  function themeById(id) { for (var i = 0; i < themes.length; i++) { if (themes[i].id === id) return themes[i]; } return themes[0]; }
  function lpAll(name) { return document.querySelectorAll('[data-lp="' + name + '"]'); }
  function lpSet(name, txt) { var els = lpAll(name); for (var i = 0; i < els.length; i++) els[i].textContent = txt; }

  var state = { theme: 'ametista', tab: 'dash', demo: 0, privacy: false, selN: 0, carIdx: 15, carSnap: false };
  var reduced = false;
  var fontsLoaded = { ametista: true };

  function loadFonts(id) {
    if (fontsLoaded[id]) return;
    var th = themeById(id);
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = 'https://fonts.googleapis.com/css2?' + th.fonts + '&display=swap';
    document.head.appendChild(l);
    fontsLoaded[id] = true;
  }

  // ---- fonte única de verdade: state.theme ----
  function applyTheme(id) {
    document.documentElement.setAttribute('data-theme', id);
    try { localStorage.setItem('novixfin-theme', id); } catch (e) {}
    loadFonts(id);
    state.theme = id;
    renderTheme();
  }

  function renderTheme() {
    var th = themeById(state.theme);
    lpSet('h1a', th.h1a); lpSet('h1acc', th.h1acc); lpSet('h1post', th.h1post);
    lpSet('sub', th.sub);
    lpSet('selName', th.name); lpSet('selTag', th.tag); lpSet('themeName', th.name);
    var selIdx = selOrder.indexOf(state.theme); if (selIdx < 0) selIdx = 0;
    var pad2 = function (n) { return n < 10 ? '0' + n : '' + n; };
    lpSet('selPos', pad2(selIdx + 1) + ' / ' + pad2(selOrder.length));
    var big = lpAll('selNameBig');
    for (var i = 0; i < big.length; i++) {
      big[i].textContent = th.name;
      if (!reduced) big[i].style.animation = (state.selN % 2 === 0 ? 'lpNameA' : 'lpNameB') + ' .42s cubic-bezier(.2,.8,.2,1) both';
    }
    renderCarousel();
  }

  // ---- carrossel acoplado (3 cópias dos 15, wrap invisível) ----
  var track = null;
  function buildCarousel() {
    track = document.getElementById('lp-track');
    if (!track) return;
    var html = '';
    for (var k = 0; k < 3; k++) {
      for (var oi = 0; oi < selOrder.length; oi++) {
        var th = themeById(selOrder[oi]);
        var im = selInk[th.id] || ['#111111', '#777777'];
        var abs = k * 15 + oi;
        html += '<button type="button" data-car-id="' + th.id + '" data-car-abs="' + abs + '" title="Vestir ' + th.name + '" style="flex: none; width: 220px; text-align: left; cursor: pointer; padding: 15px 17px; background: ' + th.c1 + '; border: 1px solid rgba(0,0,0,.12); border-radius: 16px; opacity: .72; transform: scale(.96); transition: transform .45s ease, opacity .45s ease, border-color .45s ease; font-family: var(--f-b);">' +
          '<div style="font-size: 11px; font-weight: 700; color: ' + im[1] + '; letter-spacing: .08em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + th.name.toUpperCase() + '</div>' +
          '<div style="font-family: ' + th.fd + '; font-size: 21px; font-weight: 700; color: ' + im[0] + '; margin-top: 6px;">R$ 12.450</div>' +
          '<div style="display: flex; gap: 5px; margin-top: 10px; align-items: flex-end; height: 34px;"><span style="flex: 1; height: 45%; background: ' + im[0] + '; opacity: .8; border-radius: 99px;"></span><span style="flex: 1; height: 65%; background: ' + im[0] + '; opacity: .8; border-radius: 99px;"></span><span style="flex: 1; height: 80%; background: ' + th.c2 + '; border-radius: 99px;"></span><span style="flex: 1; height: 90%; border: 1.5px dashed ' + th.c2 + '; border-radius: 99px; box-sizing: border-box;"></span></div>' +
          '</button>';
      }
    }
    track.innerHTML = html;
    track.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-car-id]') : null;
      if (!btn) return;
      goTheme(btn.getAttribute('data-car-id'), parseInt(btn.getAttribute('data-car-abs'), 10));
    });
  }

  function renderCarousel() {
    if (!track) return;
    track.style.transition = state.carSnap ? 'none' : 'transform .45s cubic-bezier(.2,.8,.2,1)';
    track.style.transform = 'translateX(' + (-(state.carIdx * 236 + 110)) + 'px)';
    var btns = track.querySelectorAll('[data-car-id]');
    for (var i = 0; i < btns.length; i++) {
      var id = btns[i].getAttribute('data-car-id');
      var active = id === state.theme;
      var th = themeById(id);
      btns[i].style.border = active ? '2px solid ' + th.c2 : '1px solid rgba(0,0,0,.12)';
      btns[i].style.opacity = active ? '1' : '.72';
      btns[i].style.transform = 'scale(' + (active ? '1.04' : '.96') + ')';
    }
  }

  var wrapT = null;
  function afterWrap() {
    clearTimeout(wrapT);
    wrapT = setTimeout(function () {
      var ci = state.carIdx, mid = 15 + ((ci % 15) + 15) % 15;
      if (ci === mid) return;
      state.carSnap = true; state.carIdx = mid;
      renderCarousel();
      setTimeout(function () { state.carSnap = false; renderCarousel(); }, 60);
    }, 520);
  }

  function goTheme(id, idxHint) {
    if (id === state.theme) return;
    loadFonts(id);
    var th = themeById(id);
    var ready = (document.fonts && document.fonts.load)
      ? Promise.race([document.fonts.load('700 32px ' + th.fd).catch(function () {}), new Promise(function (r) { setTimeout(r, 450); })])
      : Promise.resolve();
    ready.then(function () {
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var apply = function () {
        state.selN++;
        state.carIdx = idxHint !== undefined ? idxHint : 15 + selOrder.indexOf(id);
        applyTheme(id);
        afterWrap();
      };
      if (!reduce && document.startViewTransition) document.startViewTransition(apply); else apply();
      var j = selOrder.indexOf(id);
      loadFonts(selOrder[(j + 1 + 15) % 15]);
      loadFonts(selOrder[(j - 1 + 15) % 15]);
    });
  }

  function selGo(dir) {
    var i = selOrder.indexOf(state.theme); if (i < 0) i = 0;
    var next = selOrder[(i + dir + selOrder.length) % selOrder.length];
    goTheme(next, state.carIdx + dir);
  }

  // ---- privacidade (olhinho) ----
  function renderPrivacy() {
    var p = state.privacy;
    lpSet('privEye', p ? '👁' : '👁‍🗨');
    lpSet('privLabel', p ? 'mostrar' : 'ocultar');
    lpSet('privSaldo', p ? 'R$ ••••••' : 'R$ 12.450,80');
    lpSet('privV1', p ? '−••,••' : '−46,90');
    lpSet('privV2', p ? '+•.•••,••' : '+6.500,00');
    lpSet('heroSaldo', p ? 'R$ ••••••' : 'R$ 12.450,80');
    lpSet('heroIn', p ? '•.•••' : '8.200');
    lpSet('heroOut', p ? '•.•••' : '3.980');
  }

  // ---- demo "3 segundos" ----
  function renderDemo() {
    var d = state.demo;
    lpSet('demoVal', d >= 3 ? 'R$ 46,90' : d === 2 ? 'R$ 46,9' : d === 1 ? 'R$ 46' : 'R$ ');
    var cat = document.getElementById('lp-demo-cat');
    if (cat) { cat.style.background = d >= 4 ? 'var(--accent)' : 'var(--surface)'; cat.style.color = d >= 4 ? 'var(--accent-ink)' : 'var(--muted)'; }
    var btn = document.getElementById('lp-demo-btn');
    if (btn) btn.style.transform = d === 5 ? 'scale(0.96)' : 'scale(1)';
    var toast = document.getElementById('lp-demo-toast');
    if (toast) { toast.style.opacity = d >= 6 ? '1' : '0'; toast.style.transform = d >= 6 ? 'translateY(0)' : 'translateY(12px)'; }
  }

  // ---- tour de produto (tabs) ----
  function setTab(tb) {
    if (!tb || tb === state.tab) return;
    state.tab = tb;
    var panels = document.querySelectorAll('[data-tab-panel]');
    for (var i = 0; i < panels.length; i++) panels[i].hidden = panels[i].getAttribute('data-tab-panel') !== tb;
    var btns = document.querySelectorAll('[data-act="tab"]');
    for (var j = 0; j < btns.length; j++) {
      var act = btns[j].getAttribute('data-tab') === tb;
      btns[j].style.border = '1px solid ' + (act ? 'var(--accent)' : 'var(--line)');
      btns[j].style.background = act ? 'var(--accent)' : 'var(--surface)';
      btns[j].style.color = act ? 'var(--accent-ink)' : 'var(--muted)';
      btns[j].style.clipPath = act ? 'var(--cut)' : 'none';
    }
    if (reduced) return;
    var p = document.getElementById('tourPanel');
    if (!p) return;
    p.style.animation = 'none';
    void p.offsetWidth;
    p.style.animation = 'nvxTabIn .32s cubic-bezier(.16,1,.3,1)';
  }

  // ---- reveals / counters / barras / linha da previsão ----
  var io = null;
  function finishCount(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    var dec = el.getAttribute('data-dec') === '1';
    el.textContent = dec ? target.toFixed(1).replace('.', ',') : Math.round(target).toLocaleString('pt-BR');
  }
  function runCount(el) {
    if (reduced) { finishCount(el); return; }
    var target = parseFloat(el.getAttribute('data-count'));
    var dec = el.getAttribute('data-dec') === '1';
    var t0 = performance.now();
    var dur = 1400;
    var c1 = 1.70158, c3 = c1 + 1;
    var step = function (t) {
      var p = Math.min(1, (t - t0) / dur);
      var e = p >= 1 ? 1 : 1 + c3 * Math.pow(p - 1, 3) + c1 * Math.pow(p - 1, 2);
      var v = target * e;
      el.textContent = dec ? v.toFixed(1).replace('.', ',') : Math.round(v).toLocaleString('pt-BR');
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }
  function setupObservers() {
    var els = document.querySelectorAll('[data-reveal],[data-bar],[data-count],[data-draw],[data-fadein]');
    if (!('IntersectionObserver' in window)) {
      for (var i = 0; i < els.length; i++) {
        var el = els[i];
        if (el.hasAttribute('data-bar')) el.style.height = el.getAttribute('data-bar') + '%';
        if (el.hasAttribute('data-count')) finishCount(el);
        if (el.hasAttribute('data-draw')) el.style.strokeDashoffset = '0';
        if (el.hasAttribute('data-fadein')) el.style.opacity = '1';
      }
      return;
    }
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        if (el.hasAttribute('data-reveal')) { el.style.opacity = '1'; el.style.transform = 'none'; }
        if (el.hasAttribute('data-bar')) { el.style.height = el.getAttribute('data-bar') + '%'; }
        if (el.hasAttribute('data-draw')) { el.style.strokeDashoffset = '0'; }
        if (el.hasAttribute('data-fadein')) { el.style.opacity = '1'; }
        if (el.hasAttribute('data-count')) { runCount(el); }
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -10% 0px' });
    Array.prototype.forEach.call(els, function (el) {
      if (el.hasAttribute('data-reveal') && !reduced) {
        var d = 0; var sib = el.previousElementSibling;
        while (sib) { if (sib.hasAttribute && sib.hasAttribute('data-reveal')) d++; sib = sib.previousElementSibling; }
        el.style.opacity = '0';
        el.style.transform = 'translateY(32px)';
        el.style.transition = 'opacity .9s cubic-bezier(.16,1,.3,1), transform .9s cubic-bezier(.16,1,.3,1)';
        el.style.transitionDelay = (Math.min(d, 6) * 110) + 'ms';
      }
      if (el.hasAttribute('data-bar')) {
        if (reduced) { el.style.height = el.getAttribute('data-bar') + '%'; }
        else { el.style.transition = 'height 1.1s cubic-bezier(.2,.7,.2,1) .1s'; }
      }
      io.observe(el);
    });
  }

  function setupFloat() {
    if (reduced) return;
    var els = document.querySelectorAll('[data-float]');
    for (var i = 0; i < els.length; i++) {
      els[i].style.animation = 'nvxCardFloat ' + (7 + i * 0.9).toFixed(1) + 's ease-in-out ' + (i * 0.4).toFixed(1) + 's infinite';
      els[i].style.willChange = 'transform';
    }
  }

  function setupShimmer() {
    if (reduced) return;
    var els = document.querySelectorAll('[data-shimmer]');
    Array.prototype.forEach.call(els, function (el) {
      var base = el.getAttribute('data-shimmer') === 'accent' ? 'var(--accent)' : 'var(--ink)';
      var band = el.getAttribute('data-shimmer') === 'accent'
        ? 'color-mix(in srgb, var(--accent) 45%, white)'
        : 'color-mix(in srgb, var(--ink) 42%, white)';
      el.style.backgroundImage = 'linear-gradient(100deg, ' + base + ' 42%, ' + band + ' 50%, ' + base + ' 58%)';
      el.style.backgroundSize = '220% 100%';
      el.style.backgroundRepeat = 'repeat';
      el.style.webkitBackgroundClip = 'text';
      el.style.backgroundClip = 'text';
      el.style.webkitTextFillColor = 'transparent';
      el.style.color = 'transparent';
      el.style.animation = 'nvxShimmer 5.5s linear infinite';
    });
  }

  function setupCtas() {
    var els = document.querySelectorAll('[data-cta]');
    Array.prototype.forEach.call(els, function (el) {
      var cs = window.getComputedStyle(el);
      if (cs.position === 'static') el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.style.transition = 'transform .16s cubic-bezier(.16,1,.3,1), box-shadow .16s cubic-bezier(.16,1,.3,1)';
      el.addEventListener('mouseenter', function () {
        if (!reduced) {
          el.style.transform = 'translateY(-2px) scale(1.02)';
          el.style.boxShadow = '0 12px 26px color-mix(in srgb, var(--accent) 34%, transparent)';
          var sw = document.createElement('span');
          sw.setAttribute('aria-hidden', 'true');
          sw.style.cssText = 'position:absolute;top:0;left:0;height:100%;width:38%;pointer-events:none;background:linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent);animation:nvxSweep .62s cubic-bezier(.16,1,.3,1);';
          el.appendChild(sw);
          setTimeout(function () { if (sw.parentNode) sw.parentNode.removeChild(sw); }, 640);
        }
      });
      el.addEventListener('mouseleave', function () { el.style.transform = ''; el.style.boxShadow = ''; });
    });
  }

  function setupParallax() {
    if (reduced) return;
    var els = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));
    if (!els.length) return;
    els.forEach(function (el) { el.setAttribute('data-base-t', el.style.transform || ''); el.style.willChange = 'transform'; });
    var ticking = false;
    var onScroll = function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        var vh = window.innerHeight;
        els.forEach(function (el) {
          var host = el.parentElement;
          if (!host) return;
          var r = host.getBoundingClientRect();
          if (r.bottom < -100 || r.top > vh + 100) return;
          var f = parseFloat(el.getAttribute('data-parallax')) || 0.1;
          var off = (r.top + r.height / 2 - vh / 2) * f;
          el.style.transform = (el.getAttribute('data-base-t') + ' translate3d(0,' + off.toFixed(1) + 'px,0)').trim();
        });
        ticking = false;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function setupKinetic() {
    if (reduced) return;
    var els = document.querySelectorAll('[data-kinetic]');
    for (var i = 0; i < els.length; i++) {
      var k = parseInt(els[i].getAttribute('data-kinetic'), 10) || 0;
      els[i].style.animation = 'nvxKinetic .8s cubic-bezier(.16,1,.3,1) ' + (0.08 + k * 0.08).toFixed(2) + 's both';
    }
  }

  function setupTilt() {
    if (reduced) return;
    if (window.matchMedia && window.matchMedia('(hover: none)').matches) return;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-tilt]'));
    if (!nodes.length) return;
    var items = nodes.map(function (el) {
      return { el: el, max: parseFloat(el.getAttribute('data-tilt')) || 5, base: el.getAttribute('data-tilt-base') || '' };
    });
    var pv = 'perspective(1400px) ';
    window.addEventListener('mousemove', function (ev) {
      items.forEach(function (it) {
        var r = it.el.getBoundingClientRect();
        if (r.width === 0) return;
        var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var dx = (ev.clientX - cx) / (r.width / 2);
        var dy = (ev.clientY - cy) / (r.height / 2);
        if (Math.abs(dx) > 1.6 || Math.abs(dy) > 1.6) { it.el.style.transform = it.base || (pv + 'rotateY(0deg) rotateX(0deg)'); return; }
        var ry = Math.max(-it.max, Math.min(it.max, dx * it.max));
        var rx = Math.max(-it.max, Math.min(it.max, -dy * it.max));
        if (it.base) { it.el.style.transform = pv + 'rotateY(' + (ry - 6).toFixed(2) + 'deg) rotateX(' + (rx + 3).toFixed(2) + 'deg)'; }
        else { it.el.style.transform = pv + 'rotateY(' + ry.toFixed(2) + 'deg) rotateX(' + rx.toFixed(2) + 'deg)'; it.el.style.boxShadow = '0 24px 60px rgba(0,0,0,.14)'; }
      });
    }, { passive: true });
  }

  // scrub da previsão por scroll (sem pin, sem altura morta)
  function setupPin() {
    var wrap = document.querySelector('[data-pin-wrap]');
    var pbars = Array.prototype.slice.call(document.querySelectorAll('[data-pbar]'));
    var pline = document.querySelector('[data-pline]');
    var parea = document.querySelector('[data-parea]');
    var plabel = document.querySelector('[data-plabel]');
    var setFinal = function () {
      pbars.forEach(function (b) { b.style.height = b.getAttribute('data-pbar') + '%'; });
      if (pline) pline.style.strokeDashoffset = '0';
      if (parea) parea.style.opacity = '1';
      if (plabel) { plabel.style.opacity = '1'; plabel.style.transform = 'none'; }
    };
    if (!wrap || reduced) { setFinal(); return; }
    pbars.forEach(function (b) { b.style.height = '6%'; b.style.transition = 'height .15s cubic-bezier(.16,1,.3,1)'; });
    if (pline) { pline.style.strokeDashoffset = '1'; pline.style.transition = 'none'; }
    if (parea) { parea.style.opacity = '0'; parea.style.transition = 'none'; }
    if (plabel) { plabel.style.opacity = '0'; plabel.style.transform = 'translateY(6px)'; plabel.style.transition = 'opacity .3s cubic-bezier(.16,1,.3,1), transform .3s cubic-bezier(.16,1,.3,1)'; }
    var clamp = function (v) { return v < 0 ? 0 : v > 1 ? 1 : v; };
    var seg = function (p, a, b) { return clamp((p - a) / (b - a)); };
    var ticking = false;
    var onPin = function () {
      if (ticking) return; ticking = true;
      requestAnimationFrame(function () {
        var r = wrap.getBoundingClientRect();
        var vh = window.innerHeight;
        var cy = r.top + r.height / 2;
        var p = clamp((vh - cy) / (vh * 0.62));
        var pb = seg(p, 0.08, 0.56);
        pbars.forEach(function (b) { b.style.height = (parseFloat(b.getAttribute('data-pbar')) * pb).toFixed(1) + '%'; });
        if (pline) pline.style.strokeDashoffset = String(1 - seg(p, 0.5, 0.78));
        if (parea) parea.style.opacity = String(seg(p, 0.6, 0.86));
        if (plabel) { var lp = seg(p, 0.8, 0.98); plabel.style.opacity = String(lp); plabel.style.transform = 'translateY(' + ((1 - lp) * 6).toFixed(1) + 'px)'; }
        ticking = false;
      });
    };
    window.addEventListener('scroll', onPin, { passive: true });
    onPin();
  }
  function forcePrevisao() {
    var pbars = document.querySelectorAll('[data-pbar]');
    Array.prototype.forEach.call(pbars, function (b) { b.style.height = b.getAttribute('data-pbar') + '%'; });
    var pl = document.querySelector('[data-pline]'); if (pl) pl.style.strokeDashoffset = '0';
    var pa = document.querySelector('[data-parea]'); if (pa) pa.style.opacity = '1';
    var plb = document.querySelector('[data-plabel]'); if (plb) { plb.style.opacity = '1'; plb.style.transform = 'none'; }
  }

  // ---- modo apresentação ----
  var present = { active: false, i: 0, paused: false, bar: null, pauseBtn: null, nextBtn: null, timer: null, pulse: null, autoScrolling: false, onUserScroll: null, onKey: null };
  function presentStops() {
    return [
      { sel: '[data-screen-label="hero"]', cap: 'Lance um gasto em 3 segundos e o saldo se atualiza na hora.', dur: 3800 },
      { sel: '#demo', cap: 'Lançamento rápido: valor, categoria, conta. Pronto.', dur: 4400 },
      { sel: '#previsao', cap: 'A previsão desenha o seu saldo antes de o mês acontecer.', dur: 5200 },
      { sel: '[data-screen-label="tour de produto"]', cap: 'Dashboard, transações, cartões, metas e kanban, no seu tema.', dur: 4800 },
      { sel: '[data-screen-label="gamificação"]', cap: 'Constância vira pontos, níveis e conquistas. Sem joguinho.', dur: 4600 },
      { sel: '#temas', cap: '15 personalidades, o mesmo app por dentro. Escolha a sua.', dur: 4600 },
      { sel: '[data-screen-label="prova social"]', cap: 'Milhares de brasileiros já organizam o mês por aqui.', dur: 4400 },
      { sel: '[data-screen-label="cta final"]', cap: 'Comece grátis agora. O primeiro lançamento leva 3 segundos.', dur: 5200, last: true }
    ];
  }
  function startPresent() {
    if (present.active) return;
    present.active = true;
    present.i = 0;
    present.paused = false;
    buildPresentUI();
    document.body.style.overflow = '';
    present.onUserScroll = function () { if (!present.autoScrolling) presentPause(); };
    present.onKey = function (e) {
      if (e.key === 'Escape') { presentExit(); return; }
      if (['ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', ' ', 'Home', 'End'].indexOf(e.key) >= 0) presentPause();
    };
    window.addEventListener('wheel', present.onUserScroll, { passive: true });
    window.addEventListener('touchmove', present.onUserScroll, { passive: true });
    window.addEventListener('keydown', present.onKey);
    presentGo(0);
  }
  function buildPresentUI() {
    var bar = document.createElement('div');
    bar.setAttribute('data-present-ui', '1');
    bar.style.cssText = 'position:fixed;left:50%;bottom:26px;transform:translateX(-50%);z-index:2000;max-width:calc(100vw - 32px);display:flex;align-items:center;gap:16px;padding:14px 16px 14px 22px;background:color-mix(in srgb, var(--surface) 88%, transparent);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--line-soft);border-radius:var(--r);box-shadow:0 20px 60px rgba(0,0,0,.28);color:var(--ink);font-family:var(--f-b);opacity:0;transition:opacity .42s cubic-bezier(.16,1,.3,1);';
    var dot = '<span style="width:8px;height:8px;background:var(--accent);flex-shrink:0;' + (reduced ? '' : 'animation:nvxBlink 1.4s ease-in-out infinite;') + '"></span>';
    var cap = document.createElement('div');
    cap.style.cssText = 'font-size:15px;font-weight:600;line-height:1.35;max-width:520px;display:flex;align-items:center;gap:11px;';
    cap.innerHTML = dot + '<span data-present-txt="1">Preparando a apresentação…</span>';
    var count = document.createElement('div');
    count.setAttribute('data-present-count', '1');
    count.style.cssText = 'font-family:var(--f-m);font-size:12px;color:var(--muted);white-space:nowrap;';
    var mkBtn = function (txt, primary) {
      var b = document.createElement('button');
      b.textContent = txt;
      b.style.cssText = 'font-family:var(--f-b);font-size:13px;font-weight:700;padding:9px 15px;min-height:40px;cursor:pointer;border-radius:var(--r-btn);white-space:nowrap;transition:transform .16s cubic-bezier(.16,1,.3,1);' + (primary ? 'background:var(--accent);color:var(--accent-ink);border:1px solid var(--accent);' : 'background:transparent;color:var(--ink);border:1px solid var(--line);');
      return b;
    };
    var ctrls = document.createElement('div');
    ctrls.style.cssText = 'display:flex;align-items:center;gap:8px;';
    if (reduced) {
      var next = mkBtn('Próximo →', true);
      next.addEventListener('click', function () { presentGo(present.i + 1); });
      present.nextBtn = next;
      ctrls.appendChild(next);
    } else {
      var pause = mkBtn('❚❚ Pausar', false);
      pause.addEventListener('click', function () { present.paused ? presentResume() : presentPause(); });
      present.pauseBtn = pause;
      ctrls.appendChild(pause);
    }
    var skip = mkBtn('Pular ▸', false);
    skip.addEventListener('click', function () { presentSkip(); });
    var exit = mkBtn('✕', false);
    exit.setAttribute('aria-label', 'Sair da apresentação');
    exit.addEventListener('click', function () { presentExit(); });
    ctrls.appendChild(skip); ctrls.appendChild(exit);
    bar.appendChild(cap); bar.appendChild(count); bar.appendChild(ctrls);
    document.body.appendChild(bar);
    present.bar = bar;
    requestAnimationFrame(function () { bar.style.opacity = '1'; });
  }
  function presentGo(i) {
    var stops = presentStops();
    if (i >= stops.length) { presentSkip(); return; }
    present.i = i;
    present.paused = false;
    if (present.pauseBtn) present.pauseBtn.textContent = '❚❚ Pausar';
    var s = stops[i];
    var el = document.querySelector(s.sel);
    var txt = present.bar && present.bar.querySelector('[data-present-txt]');
    var cnt = present.bar && present.bar.querySelector('[data-present-count]');
    if (txt) { txt.style.opacity = '0'; setTimeout(function () { txt.textContent = s.cap; txt.style.transition = 'opacity .3s cubic-bezier(.16,1,.3,1)'; txt.style.opacity = '1'; }, 160); }
    if (cnt) cnt.textContent = (i + 1) + ' / ' + stops.length;
    if (el) {
      var y = el.getBoundingClientRect().top + window.scrollY - 84;
      present.autoScrolling = true;
      window.scrollTo({ top: Math.max(0, y), behavior: reduced ? 'auto' : 'smooth' });
      setTimeout(function () { present.autoScrolling = false; }, reduced ? 60 : 900);
      if (s.sel === '#previsao') setTimeout(function () { forcePrevisao(); }, reduced ? 80 : 950);
    }
    if (reduced) { if (present.nextBtn) present.nextBtn.textContent = s.last ? 'Concluir ✓' : 'Próximo →'; return; }
    if (present.timer) clearTimeout(present.timer);
    if (s.last) { presentArriveEnd(); return; }
    present.timer = setTimeout(function () { if (!present.paused) presentGo(i + 1); }, s.dur);
  }
  function presentPause() {
    if (!present.active || present.paused) return;
    present.paused = true;
    if (present.timer) clearTimeout(present.timer);
    if (present.pauseBtn) present.pauseBtn.textContent = '▶ Retomar';
  }
  function presentResume() {
    if (!present.active || !present.paused) return;
    present.paused = false;
    if (present.pauseBtn) present.pauseBtn.textContent = '❚❚ Pausar';
    presentGo(present.i);
  }
  function presentSkip() {
    if (present.timer) clearTimeout(present.timer);
    var stops = presentStops();
    present.i = stops.length - 1;
    var last = stops[stops.length - 1];
    var el = document.querySelector(last.sel);
    var txt = present.bar && present.bar.querySelector('[data-present-txt]');
    var cnt = present.bar && present.bar.querySelector('[data-present-count]');
    if (txt) txt.textContent = last.cap;
    if (cnt) cnt.textContent = stops.length + ' / ' + stops.length;
    if (el) { var y = el.getBoundingClientRect().top + window.scrollY - 84; present.autoScrolling = true; window.scrollTo({ top: y, behavior: reduced ? 'auto' : 'smooth' }); setTimeout(function () { present.autoScrolling = false; }, reduced ? 60 : 900); }
    presentArriveEnd();
  }
  function presentArriveEnd() {
    present.paused = true;
    if (present.pauseBtn) present.pauseBtn.textContent = '↺ De novo';
    if (present.nextBtn) present.nextBtn.textContent = '↺ De novo';
    var again = present.pauseBtn || present.nextBtn;
    if (again) {
      var clone = again.cloneNode(true);
      again.parentNode.replaceChild(clone, again);
      clone.addEventListener('click', function () { presentGo(0); });
      present.pauseBtn = reduced ? null : clone;
      present.nextBtn = reduced ? clone : null;
    }
    var ctaSpan = document.querySelector('[data-screen-label="cta final"] [data-cta]');
    if (ctaSpan && !reduced) {
      ctaSpan.style.transition = 'transform .42s cubic-bezier(.16,1,.3,1)';
      var n = 0;
      var pulse = setInterval(function () {
        if (!present.active || n >= 6) { clearInterval(pulse); if (ctaSpan) ctaSpan.style.transform = ''; return; }
        ctaSpan.style.transform = (n % 2 === 0) ? 'scale(1.05)' : 'scale(1)';
        n++;
      }, 420);
      present.pulse = pulse;
    }
  }
  function presentExit() {
    present.active = false;
    if (present.timer) clearTimeout(present.timer);
    if (present.pulse) clearInterval(present.pulse);
    if (present.onUserScroll) { window.removeEventListener('wheel', present.onUserScroll); window.removeEventListener('touchmove', present.onUserScroll); }
    if (present.onKey) window.removeEventListener('keydown', present.onKey);
    var bar = present.bar;
    if (bar) { bar.style.opacity = '0'; setTimeout(function () { if (bar && bar.parentNode) bar.parentNode.removeChild(bar); }, 420); }
    present.bar = null; present.pauseBtn = null; present.nextBtn = null;
  }

  // ---- boot ----
  function boot() {
    var forcedMotion = /[?&]motion=(on|1)/.test(window.location.search);
    var mqReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    reduced = mqReduce && !forcedMotion;
    if (reduced) {
      var st = document.createElement('style');
      st.textContent = '* { animation: none !important; transition: none !important; }';
      document.head.appendChild(st);
      var dup = document.querySelector('[data-mq-dup]'); if (dup) dup.style.display = 'none';
      var tr = document.querySelector('[data-mq-track]'); if (tr) { tr.style.width = 'auto'; tr.style.flexWrap = 'wrap'; tr.style.justifyContent = 'center'; tr.style.rowGap = '12px'; tr.style.margin = '0 32px'; }
    }

    // tema inicial: o snippet pre-paint do <head> já validou e aplicou data-theme
    var t = document.documentElement.getAttribute('data-theme') || 'ametista';
    var known = false;
    for (var i = 0; i < selOrder.length; i++) { if (selOrder[i] === t) { known = true; break; } }
    if (!known) t = 'ametista';
    state.theme = t;
    state.carIdx = 15 + Math.max(0, selOrder.indexOf(t));

    buildCarousel();
    renderTheme();
    renderPrivacy();
    loadFonts(t);

    // eventos delegados
    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-act]') : null;
      if (!el) return;
      var act = el.getAttribute('data-act');
      if (act === 'sel-prev') selGo(-1);
      else if (act === 'sel-next') selGo(1);
      else if (act === 'privacy') { state.privacy = !state.privacy; renderPrivacy(); }
      else if (act === 'present') startPresent();
      else if (act === 'tab') setTab(el.getAttribute('data-tab'));
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-selkey]'), function (el) {
      el.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); selGo(-1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); selGo(1); }
      });
    });

    // demo "3 segundos"
    if (reduced) { state.demo = 5; renderDemo(); }
    else { setInterval(function () { state.demo = (state.demo + 1) % 8; renderDemo(); }, 900); }

    setTimeout(function () { setupObservers(); setupParallax(); setupFloat(); setupShimmer(); setupCtas(); setupKinetic(); setupPin(); setupTilt(); }, 120);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();

/* ---- NAV MOBILE: hambúrguer abre/fecha o painel (links + CTAs) ----
   Só existe abaixo de 768px (o botão é display:none no desktop, via CSS).
   Não toca no switcher de tema nem em nada da lógica da landing. */
(function () {
    'use strict';
    function boot() {
        var nav = document.querySelector('nav[data-screen-label="nav"]');
        var toggle = document.getElementById('lp-navtoggle');
        if (!nav || !toggle) { return; }

        function setOpen(open) {
            if (open) { nav.setAttribute('data-navopen', '1'); }
            else { nav.removeAttribute('data-navopen'); }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        }
        function isOpen() { return nav.getAttribute('data-navopen') === '1'; }

        toggle.addEventListener('click', function () { setOpen(!isOpen()); });

        // navegar por âncora fecha o painel
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a[href]') && isOpen()) { setOpen(false); }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) { setOpen(false); }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && isOpen()) { setOpen(false); }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
})();
