<?php

declare(strict_types=1);

/**
 * Entrada (login/registro) shared layout pieces.
 * Visual source of truth: Claude Design "NovixFin Entrada.html" (v4).
 *
 * Pre-auth theme inheritance: a head snippet reads
 * localStorage['novixfin-theme'], validates it against the canonical
 * whitelist (includes/themes.php) and applies data-theme on <html>
 * BEFORE first paint. No theme selector on these screens.
 */

require_once __DIR__ . '/../themes.php';

function novix_entrada_head(): void
{
    $themesJson = json_encode(NOVIX_THEMES);
    ?>
    <script>
    (function () {
        var themes = <?php echo $themesJson; ?>;
        var t = null;
        try { t = localStorage.getItem('novixfin-theme'); } catch (e) {}
        if (!t || themes.indexOf(t) === -1) { t = '<?php echo NOVIX_THEME_DEFAULT; ?>'; }
        document.documentElement.setAttribute('data-theme', t);
        var fonts = {
            ametista: 'family=Familjen+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700',
            cafe: 'family=Sora:wght@400;600;700;800&family=Hanken+Grotesk:wght@400;500;600;700',
            sereno2: 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700',
            sereno1: 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700',
            cofre: 'family=Red+Hat+Display:wght@500;700;800&family=Red+Hat+Text:wght@400;500;600;700&family=Red+Hat+Mono:wght@400;500;700',
            tinta: 'family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500;600',
            banca: 'family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Archivo:wght@400;500;600;700;800',
            suica: 'family=Instrument+Sans:wght@400;500;600;700',
            pulso: 'family=Schibsted+Grotesk:wght@400;500;700;800&family=IBM+Plex+Mono:wght@400;500;600',
            concreto: 'family=Archivo:wght@400;700;900&family=Space+Mono:wght@400;700',
            adega: 'family=Marcellus&family=Figtree:wght@400;500;600;700',
            vitrola: 'family=Young+Serif&family=Karla:wght@400;500;700',
            cabine: 'family=Chakra+Petch:wght@500;600;700&family=JetBrains+Mono:wght@400;500;700',
            sumie: 'family=Shippori+Mincho:wght@500;600;700&family=Zen+Kaku+Gothic+New:wght@400;500;700',
            deco: 'family=Poiret+One&family=Jost:wght@400;500;600;700'
        };
        var l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = 'https://fonts.googleapis.com/css2?' + (fonts[t] || fonts.ametista) + '&display=swap';
        document.head.appendChild(l);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/styles/entrada.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../public/styles/entrada.css'); ?>">
    <?php
}

/**
 * Crystal/marble brand panel shared by login and registro.
 */
function novix_entrada_brand(string $headline, string $subline): void
{
    ?>
    <div id="lg-brand">
        <span aria-hidden="true" class="lg-grad-layer"></span>
        <span aria-hidden="true" class="lg-tex-layer"></span>

        <div id="lg-gemwrap" class="lg-par">
            <div class="lg-gem-bob">
                <span aria-hidden="true" class="lg-gem-shadow"></span>
                <span aria-hidden="true" class="lg-gem-facet"></span>
                <svg viewBox="0 0 200 224" width="100%" height="100%" style="position:relative; color:var(--stage-ink); overflow:visible;">
                    <defs>
                        <linearGradient id="lgSpec" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="currentColor" stop-opacity=".6"></stop>
                            <stop offset="1" stop-color="currentColor" stop-opacity=".05"></stop>
                        </linearGradient>
                        <linearGradient id="lgBand" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="currentColor" stop-opacity="0"></stop>
                            <stop offset=".5" stop-color="currentColor" stop-opacity=".42"></stop>
                            <stop offset="1" stop-color="currentColor" stop-opacity="0"></stop>
                        </linearGradient>
                        <clipPath id="lgGemClip"><path d="M100 16 L154 44 L176 104 L150 168 L100 208 L50 168 L24 104 L46 44 Z"></path></clipPath>
                    </defs>
                    <g stroke="var(--stage-line)" stroke-width=".7" stroke-linejoin="round">
                        <path d="M46 44 L100 16 L100 58 L60 80 Z" fill="currentColor" fill-opacity=".22"></path>
                        <path d="M100 16 L154 44 L140 80 L100 58 Z" fill="url(#lgSpec)"></path>
                        <path d="M100 58 L140 80 L132 118 L68 118 L60 80 Z" fill="currentColor" fill-opacity=".17"></path>
                        <path d="M154 44 L176 104 L132 118 L140 80 Z" fill="currentColor" fill-opacity=".12"></path>
                        <path d="M176 104 L150 168 L132 118 Z" fill="currentColor" fill-opacity=".08"></path>
                        <path d="M24 104 L46 44 L60 80 Z" fill="currentColor" fill-opacity=".14"></path>
                        <path d="M24 104 L60 80 L68 118 L50 168 Z" fill="currentColor" fill-opacity=".1"></path>
                        <path d="M50 168 L68 118 L100 208 Z" fill="currentColor" fill-opacity=".07"></path>
                        <path d="M68 118 L132 118 L100 208 Z" fill="currentColor" fill-opacity=".16"></path>
                        <path d="M132 118 L150 168 L100 208 Z" fill="currentColor" fill-opacity=".09"></path>
                    </g>
                    <path d="M46 44 L100 16 L154 44" fill="none" stroke="currentColor" stroke-opacity=".45" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <g clip-path="url(#lgGemClip)"><rect class="lg-refract" x="30" y="0" width="54" height="224" fill="url(#lgBand)"></rect></g>
                </svg>
            </div>
        </div>

        <div class="lg-par lg-shard lg-shard-b">
            <svg viewBox="0 0 100 120" width="100%" height="100%" style="color:var(--stage-ink); animation:lgFloatB 10s ease-in-out infinite;">
                <g stroke="var(--stage-line)" stroke-width=".7" stroke-linejoin="round">
                    <path d="M50 6 L92 40 L50 60 Z" fill="currentColor" fill-opacity=".2"></path>
                    <path d="M50 6 L50 60 L8 40 Z" fill="currentColor" fill-opacity=".12"></path>
                    <path d="M8 40 L50 60 L28 114 Z" fill="currentColor" fill-opacity=".08"></path>
                    <path d="M50 60 L92 40 L72 114 Z" fill="currentColor" fill-opacity=".14"></path>
                    <path d="M28 114 L50 60 L72 114 Z" fill="currentColor" fill-opacity=".18"></path>
                </g>
            </svg>
        </div>
        <div class="lg-par lg-shard lg-shard-c">
            <svg viewBox="0 0 100 120" width="100%" height="100%" style="color:var(--stage-ink); animation:lgFloatC 8s ease-in-out infinite;">
                <g stroke="var(--stage-line)" stroke-width=".8" stroke-linejoin="round">
                    <path d="M50 6 L92 40 L50 60 Z" fill="currentColor" fill-opacity=".24"></path>
                    <path d="M50 6 L50 60 L8 40 Z" fill="currentColor" fill-opacity=".14"></path>
                    <path d="M8 40 L50 60 L28 114 Z" fill="currentColor" fill-opacity=".1"></path>
                    <path d="M50 60 L92 40 L72 114 Z" fill="currentColor" fill-opacity=".16"></path>
                    <path d="M28 114 L50 60 L72 114 Z" fill="currentColor" fill-opacity=".2"></path>
                </g>
            </svg>
        </div>
        <div class="lg-par lg-shard lg-shard-a">
            <svg viewBox="0 0 100 120" width="100%" height="100%" style="color:var(--stage-ink); animation:lgFloatA 7s ease-in-out infinite;">
                <g stroke="var(--stage-line)" stroke-width="1" stroke-linejoin="round">
                    <path d="M50 6 L92 40 L50 60 Z" fill="currentColor" fill-opacity=".26"></path>
                    <path d="M50 6 L50 60 L8 40 Z" fill="currentColor" fill-opacity=".16"></path>
                    <path d="M50 60 L92 40 L72 114 Z" fill="currentColor" fill-opacity=".18"></path>
                    <path d="M28 114 L50 60 L72 114 Z" fill="currentColor" fill-opacity=".22"></path>
                </g>
            </svg>
        </div>

        <div id="lg-preview" class="lg-par">
            <div class="lg-preview-bob">
                <div class="lg-preview-card">
                    <div class="lg-pv-row">
                        <span class="lg-pv-kicker">Previsão de saldo</span>
                        <span class="lg-pv-date">jul 2026</span>
                    </div>
                    <div class="lg-pv-amount-row">
                        <span class="lg-pv-amount">R$ 8.420</span>
                        <span class="lg-pv-delta">+6,2%</span>
                    </div>
                    <div class="lg-pv-bars">
                        <span style="height:44%; background:color-mix(in srgb, var(--accent) 30%, transparent);"></span>
                        <span style="height:58%; background:color-mix(in srgb, var(--accent) 34%, transparent);"></span>
                        <span style="height:50%; background:color-mix(in srgb, var(--accent) 30%, transparent);"></span>
                        <span style="height:74%; background:color-mix(in srgb, var(--accent) 44%, transparent);"></span>
                        <span style="height:66%; background:color-mix(in srgb, var(--accent) 38%, transparent);"></span>
                        <span style="height:100%; background:var(--accent);"></span>
                    </div>
                    <div class="lg-pv-divider"></div>
                    <div class="lg-pv-row">
                        <span class="lg-pv-goal-label">Reserva de emergência</span>
                        <span class="lg-pv-goal-pct">65%</span>
                    </div>
                    <div class="lg-pv-track"><span></span></div>
                    <div class="lg-pv-chips">
                        <span class="lg-pv-chip lg-pv-chip--gold">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"></circle><path d="M9 14.5 7.5 22l4.5-2.6L16.5 22 15 14.5"></path></svg>
                            Nível Esperto
                        </span>
                        <span class="lg-pv-chip lg-pv-chip--pos">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            +10 pts
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <a href="/public/index.php" title="NovixFin" class="lg-logo">
            <span class="lg-logo-mark">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="var(--accent-ink)" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18 L10 8 L14 14 L20 5"></path></svg>
            </span>
            <span class="lg-logo-name">novixfin</span>
        </a>

        <div class="lg-bottom">
            <div class="lg-headline-wrap">
                <div class="lg-headline"><?php echo $headline; ?></div>
                <div class="lg-subline"><?php echo $subline; ?></div>
            </div>
            <div class="lg-tagline">Finanças pessoais, sem plateia</div>
        </div>
    </div>
    <?php
}

/**
 * Parallax pointer script (respects reduced-motion / coarse pointers).
 */
function novix_entrada_parallax_script(): void
{
    ?>
    <script>
    (function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        if (reduce || !fine) return;
        var panel = document.getElementById('lg-brand');
        if (!panel) return;
        panel.addEventListener('mousemove', function (e) {
            var r = panel.getBoundingClientRect();
            panel.style.setProperty('--px', ((e.clientX - r.left) / r.width - 0.5).toFixed(3));
            panel.style.setProperty('--py', ((e.clientY - r.top) / r.height - 0.5).toFixed(3));
        });
        panel.addEventListener('mouseleave', function () {
            panel.style.setProperty('--px', '0');
            panel.style.setProperty('--py', '0');
        });
    })();
    </script>
    <?php
}
