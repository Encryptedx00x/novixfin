<?php

declare(strict_types=1);

/**
 * Canonical NovixFin theme registry (Entrada slice, 2026-07).
 *
 * Single source of truth for the 15 tokenized theme slugs used by
 * localStorage['novixfin-theme'], users.theme and data-theme on <html>.
 * Legacy values 'dark'/'black'/'light' were retired by migration
 * 20240101000007_theme_15_slugs_v3.php (mapping: light -> ametista,
 * dark/black -> tinta).
 */

const NOVIX_THEME_DEFAULT = 'ametista';

const NOVIX_THEMES = [
    'ametista',
    'cafe',
    'sereno2',
    'sereno1',
    'cofre',
    'tinta',
    'banca',
    'suica',
    'pulso',
    'concreto',
    'adega',
    'vitrola',
    'cabine',
    'sumie',
    'deco',
];

/**
 * Display metadata per theme: nome exibido e as 3 cores do swatch
 * (c1 = fundo, c2 = accent, c3 = accent secundário), conforme o design.
 * Ordem = ordem de exibição no seletor.
 */
const NOVIX_THEME_META = [
    'ametista' => ['name' => 'Ametista',        'c1' => '#F0EDF5', 'c2' => '#8803FC', 'c3' => '#B07C28'],
    'cafe'     => ['name' => 'Café Coado',      'c1' => '#F6EFE2', 'c2' => '#B65437', 'c3' => '#2F7561'],
    'sereno2'  => ['name' => 'Sereno v2',       'c1' => '#101413', 'c2' => '#A9C7A9', 'c3' => '#E3C990'],
    'sereno1'  => ['name' => 'Sereno Clássico', 'c1' => '#101413', 'c2' => '#A9C7A9', 'c3' => '#D9A08C'],
    'cofre'    => ['name' => 'Azul-Cofre',      'c1' => '#0A1120', 'c2' => '#3D7BFD', 'c3' => '#E8B04B'],
    'tinta'    => ['name' => 'Tinta & Recibo',  'c1' => '#0E100A', 'c2' => '#C6F135', 'c3' => '#F27059'],
    'banca'    => ['name' => 'Banca de Jornal', 'c1' => '#F7F4EC', 'c2' => '#B3261E', 'c3' => '#1F5F3C'],
    'suica'    => ['name' => 'Grade Suíça',     'c1' => '#F2F3F5', 'c2' => '#2038E4', 'c3' => '#0C0D10'],
    'pulso'    => ['name' => 'Suíça com Pulso', 'c1' => '#F4F3EE', 'c2' => '#2038E4', 'c3' => '#E8442E'],
    'concreto' => ['name' => 'Concreto',        'c1' => '#EFECE3', 'c2' => '#FF4D24', 'c3' => '#141310'],
    'adega'    => ['name' => 'Adega',           'c1' => '#241018', 'c2' => '#D9A441', 'c3' => '#A9CBA5'],
    'vitrola'  => ['name' => 'Vitrola',         'c1' => '#F3E7CE', 'c2' => '#C9642F', 'c3' => '#D9A13B'],
    'cabine'   => ['name' => 'Cabine',          'c1' => '#0B0D11', 'c2' => '#FF8A3D', 'c3' => '#57D07E'],
    'sumie'    => ['name' => 'Sumi-ê',          'c1' => '#F5F1E8', 'c2' => '#35507B', 'c3' => '#B5483A'],
    'deco'     => ['name' => 'Salão Déco',      'c1' => '#111111', 'c2' => '#C8A24B', 'c3' => '#2E8B6A'],
];

/**
 * Google Fonts query string per theme (families used by that theme's tokens).
 */
const NOVIX_THEME_FONTS = [
    'ametista' => 'family=Familjen+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700',
    'cafe'     => 'family=Sora:wght@400;600;700;800&family=Hanken+Grotesk:wght@400;500;600;700',
    'sereno2'  => 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700',
    'sereno1'  => 'family=Instrument+Serif:ital@0;1&family=Albert+Sans:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700',
    'cofre'    => 'family=Red+Hat+Display:wght@500;700;800&family=Red+Hat+Text:wght@400;500;600;700&family=Red+Hat+Mono:wght@400;500;700',
    'tinta'    => 'family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500;600',
    'banca'    => 'family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Archivo:wght@400;500;600;700;800',
    'suica'    => 'family=Instrument+Sans:wght@400;500;600;700',
    'pulso'    => 'family=Schibsted+Grotesk:wght@400;500;700;800&family=IBM+Plex+Mono:wght@400;500;600',
    'concreto' => 'family=Archivo:wght@400;700;900&family=Space+Mono:wght@400;700',
    'adega'    => 'family=Marcellus&family=Figtree:wght@400;500;600;700',
    'vitrola'  => 'family=Young+Serif&family=Karla:wght@400;500;700',
    'cabine'   => 'family=Chakra+Petch:wght@500;600;700&family=JetBrains+Mono:wght@400;500;700',
    'sumie'    => 'family=Shippori+Mincho:wght@500;600;700&family=Zen+Kaku+Gothic+New:wght@400;500;700',
    'deco'     => 'family=Poiret+One&family=Jost:wght@400;500;600;700',
];

/**
 * Validate a theme slug against the canonical whitelist.
 * Anything unknown (including the legacy dark/black/light) falls back
 * to the default theme.
 */
function novix_valid_theme($theme): string
{
    if (is_string($theme) && in_array($theme, NOVIX_THEMES, true)) {
        return $theme;
    }

    return NOVIX_THEME_DEFAULT;
}

/**
 * Google Fonts stylesheet URL for a theme slug.
 */
function novix_theme_fonts_url($theme): string
{
    $slug = novix_valid_theme($theme);
    $query = isset(NOVIX_THEME_FONTS[$slug]) ? NOVIX_THEME_FONTS[$slug] : NOVIX_THEME_FONTS[NOVIX_THEME_DEFAULT];

    return 'https://fonts.googleapis.com/css2?' . $query . '&display=swap';
}
