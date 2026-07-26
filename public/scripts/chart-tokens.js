/* NovixFin — leitor de tokens de tema para os gráficos (Chart.js).
   Canvas não resolve var(--token)/color-mix: o fillStyle precisa de cor
   concreta. Este helper lê os tokens dos 15 temas em runtime
   (getComputedStyle no <html>) e devolve cores resolvidas, com helper de alpha.

   Uso: var P = window.NVXCharts.palette();  ->  P.pos.hex, P.neg.a(0.3), ...
   Chamado no build de cada gráfico, então cada tema carrega com a cor certa;
   se o tema mudar em runtime, rebuildar o gráfico já pega os novos valores. */
(function () {
    'use strict';

    function raw(name) {
        try {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        } catch (e) {
            return '';
        }
    }

    function toRGB(str, fallback) {
        str = (str || '').trim();
        if (str === '' && fallback) {
            str = fallback;
        }
        if (str.charAt(0) === '#') {
            var h = str.slice(1);
            if (h.length === 3) {
                h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2);
            }
            if (h.length >= 6) {
                return {
                    r: parseInt(h.slice(0, 2), 16),
                    g: parseInt(h.slice(2, 4), 16),
                    b: parseInt(h.slice(4, 6), 16)
                };
            }
        }
        var m = str.match(/rgba?\(([^)]+)\)/);
        if (m) {
            var parts = m[1].split(',');
            return { r: parseFloat(parts[0]) || 0, g: parseFloat(parts[1]) || 0, b: parseFloat(parts[2]) || 0 };
        }
        return { r: 128, g: 128, b: 128 };
    }

    function color(name, fallback) {
        var c = toRGB(raw(name), fallback);
        var prefix = 'rgb(' + c.r + ',' + c.g + ',' + c.b;
        return {
            rgb: c,
            hex: prefix + ')',
            a: function (alpha) { return 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + alpha + ')'; }
        };
    }

    function palette() {
        // Claro/escuro é decidido pela luminância do token --bg do tema ativo
        // (getComputedStyle), não mais por nome de tema legado. Cobre os 15
        // temas sem lista fixa: fundo claro -> isLight.
        var bg = toRGB(raw('--bg'), '#101010');
        var lum = (0.299 * bg.r + 0.587 * bg.g + 0.114 * bg.b) / 255;
        var isLight = lum > 0.5;
        return {
            isLight: isLight,
            ink: color('--ink', isLight ? '#241C33' : '#E9EFF8'),
            muted: color('--muted', '#6E6580'),
            accent: color('--accent', '#8803FC'),
            accent2: color('--accent-2', '#6C4FA8'),
            pos: color('--pos', '#2E7D5B'),
            neg: color('--neg', '#B5433A'),
            gold: color('--gold', '#B07C28'),
            surface: color('--surface', isLight ? '#FFFFFF' : '#111B30')
        };
    }

    window.NVXCharts = { palette: palette, color: color };
})();
