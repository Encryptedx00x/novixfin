<?php

declare(strict_types=1);

// NovixFin — Landing "Seletor Seta" (15 temas tokenizados).
// Porte fiel do design aprovado (Claude Design). Pagina publica/marketing:
// nao toca auth alem de isAuthenticated() para os CTAs.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/themes.php';

$isAuthenticated = isAuthenticated();

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovixFin | Bruto entra. Lapidado fica.</title>
    <meta name="description" content="Finanças pessoais com previsão de saldo, lançamento em 3 segundos, gamificação adulta e 15 temas completos. Grátis no plano pessoal.">
    <script>
    // Pre-paint: aplica o tema salvo (localStorage['novixfin-theme']) antes do
    // primeiro paint, validado contra a whitelist canônica (includes/themes.php).
    (function () {
        var themes = <?php echo json_encode(NOVIX_THEMES); ?>;
        var t = null;
        try { t = localStorage.getItem('novixfin-theme'); } catch (e) {}
        if (!t || themes.indexOf(t) === -1) { t = '<?php echo NOVIX_THEME_DEFAULT; ?>'; }
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/styles/landing.css">
</head>
<body>
<div style="font-family: var(--f-b); background: var(--bg); color: var(--ink); overflow-x: hidden;">

  <!-- ============ NAV ============ -->
  <nav data-screen-label="nav" style="position: sticky; top: 0; z-index: 60; background: color-mix(in srgb, var(--surface) 72%, transparent); backdrop-filter: blur(14px) saturate(1.15); -webkit-backdrop-filter: blur(14px) saturate(1.15); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 0 32px; min-height: 66px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 24px;">
      <div style="display: flex; align-items: center; gap: 13px;">
        <div style="display: flex; align-items: center; gap: 8px;">
          <div style="width: 15px; height: 15px; background: var(--sheen); clip-path: var(--cut); border-radius: var(--r-chip); flex-shrink: 0;"></div>
          <span style="font-family: var(--f-d); font-size: 20px; font-weight: var(--w-d); letter-spacing: var(--ls-d);">novixfin</span>
        </div>
        <span style="font-family: var(--f-m); font-size: 10.5px; letter-spacing: .2em; color: var(--muted); font-weight: 600; border-left: 1px solid var(--line-soft); padding-left: 13px;">FINANÇAS PESSOAIS</span>
      </div>
      <div style="display: flex; gap: 2px; justify-content: center;">
        <a href="#previsao" style="color: var(--muted); font-size: 12.5px; letter-spacing: .15em; font-weight: 700; min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center;">PRODUTO</a>
        <a href="#temas" style="color: var(--muted); font-size: 12.5px; letter-spacing: .15em; font-weight: 700; min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center;">TEMAS</a>
        <a href="#seguranca" style="color: var(--muted); font-size: 12.5px; letter-spacing: .15em; font-weight: 700; min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center;">SEGURANÇA</a>
        <a href="#faq" style="color: var(--muted); font-size: 12.5px; letter-spacing: .15em; font-weight: 700; min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center;">FAQ</a>
      </div>
      <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
        <a href="<?php echo $isAuthenticated ? '/public/dashboard.php' : '/public/login.php'; ?>" style="font-size: 13.5px; font-weight: 700; cursor: pointer; min-height: 44px; padding: 0 10px; display: inline-flex; align-items: center; color: var(--ink);"><?php echo $isAuthenticated ? 'Abrir app' : 'Entrar'; ?></a>
        <a href="<?php echo $isAuthenticated ? '/public/dashboard.php' : '/public/register.php'; ?>" style="background: var(--sheen); color: var(--accent-ink); font-family: var(--f-d); font-weight: 700; font-size: 14px; padding: 11px 20px; clip-path: var(--cut); border-radius: var(--r-btn); cursor: pointer; border: var(--card-b); box-shadow: var(--card-sh); transition: transform .16s cubic-bezier(.16,1,.3,1);" data-cta="1" data-hv="lp-hv-1"><?php echo $isAuthenticated ? 'Abrir app' : 'Criar conta grátis'; ?></a>
      </div>
    </div>
  </nav>

  <!-- ============ HERO ============ -->
  <header data-screen-label="hero" style="position: relative; overflow: hidden;">
    <div data-sig="regmarks" style="display: var(--disp-regmarks); position: absolute; top: 24px; left: 28px; color: var(--muted); font-size: 18px; opacity: .6;">+</div>
    <div data-sig="regmarks2" style="display: var(--disp-regmarks); position: absolute; top: 24px; right: 28px; color: var(--muted); font-size: 18px; opacity: .6;">+</div>
    <div data-crys-abstract="1" data-parallax="0.12" style="position: absolute; right: -160px; top: -120px; width: 560px; height: 560px; background: var(--grad); opacity: .1; clip-path: polygon(0 0, 100% 18%, 82% 100%, 12% 84%); transform: rotate(8deg); pointer-events: none;"></div>
    <div data-crys-abstract="1" data-parallax="-0.08" style="position: absolute; left: -220px; bottom: -180px; width: 480px; height: 480px; background: var(--grad); opacity: .07; clip-path: polygon(20% 0, 100% 30%, 74% 100%, 0 70%); pointer-events: none;"></div>
    <div data-crys="cluster" data-parallax="0.1" style="top: 198px"></div>
    <div data-crys="single" data-parallax="-0.07"></div>
    <div style="max-width: 1220px; margin: 0 auto; padding: 92px 32px 52px; display: grid; grid-template-columns: 1.06fr 1fr; gap: 72px; align-items: center; position: relative;">
      <div style="display: flex; flex-direction: column; gap: 26px;">
        <div data-sig="filete" style="display: var(--disp-filete); border-top: 4px double var(--ink); width: 120px;"></div>
        <div data-kinetic="0" data-glass="1" style="display: inline-flex; align-items: center; gap: 10px; border: 1px solid var(--line); background: var(--surface); padding: 9px 16px; width: fit-content; font-size: 14px; color: var(--muted); font-weight: 600; border-radius: var(--r-chip);">
          <span style="width: 9px; height: 9px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>
          grátis · sem cartão · um lançamento em 3 segundos
        </div>
        <h1 data-shimmer="ink" data-kinetic="1" style="color: var(--ink); margin: 8px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(46px, 5.6vw, 82px); line-height: 1.02;"><span data-lp="h1a">Bruto entra. Lapidado </span><span data-lp="h1acc" style="color: var(--accent);">fica</span><span data-lp="h1post">.</span></h1>
        <p data-kinetic="2" style="margin: 4px 0 0; font-size: 20px; color: var(--muted); line-height: 1.55; max-width: 520px;" data-lp="sub">Lance cada gasto em segundos e veja o mês bruto virar patrimônio: previsão de saldo, metas que avançam faceta a faceta e cartões sob controle.</p>
        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
          <a href="<?php echo $isAuthenticated ? '/public/dashboard.php' : '/public/register.php'; ?>" style="background: var(--sheen); color: var(--accent-ink); font-family: var(--f-d); font-weight: 700; font-size: 18px; padding: 17px 32px; clip-path: var(--cut); border-radius: var(--r-btn); cursor: pointer; border: var(--card-b); box-shadow: var(--card-sh); transition: transform .16s ease;" data-cta="1" data-hero-cta="1" data-hv="lp-hv-2"><?php echo $isAuthenticated ? 'Abrir app' : 'Criar conta grátis'; ?></a>
          <button data-act="present" data-present-btn="1" aria-label="Apresentar o NovixFin" style="border: 1px solid var(--line); background: transparent; color: var(--ink); font-family: var(--f-b); font-size: 18px; font-weight: 600; padding: 16px 24px; border-radius: var(--r-btn); cursor: pointer; display: inline-flex; align-items: center; gap: 9px; min-height: 44px; transition: transform .16s cubic-bezier(.16,1,.3,1);" data-hv="lp-hv-2"><span style="color: var(--accent);">▶</span> Apresentar</button>
        </div>
        </div>

      <!-- mock com perspectiva -->
      <div data-mockwrap="1" style="position: relative; padding: 18px 18px 98px;">
        <div data-marble="1"></div>
        <div data-contact="1" style="position:absolute; left:50%; bottom:60px; transform:translateX(-50%); width:70%; height:54px; background:radial-gradient(ellipse at center, rgba(40,20,60,.2), rgba(40,20,60,0) 72%); filter:blur(9px); z-index:1; pointer-events:none;"></div>
        <div data-mocktilt="1" data-tilt="7" data-tilt-base="perspective(1400px) rotateY(-6deg) rotateX(3deg)" style="transform: perspective(1400px) rotateY(-6deg) rotateX(3deg); transform-origin: 60% 40%; transition: transform .42s cubic-bezier(.16,1,.3,1);">
          <div data-card="1" data-float="1" data-glass="1" style="background: var(--facet), var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); clip-path: var(--cut-card); padding: 30px 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div style="font-size: 13.5px; color: var(--muted); font-weight: 600;">Saldo total · julho</div>
              <div data-act="privacy" style="border: 1px solid var(--line); font-size: 12px; font-weight: 700; color: var(--muted); padding: 8px 12px; border-radius: var(--r-chip); cursor: pointer; min-height: 32px; display: inline-flex; align-items: center; gap: 6px;"><span data-lp="privEye">👁‍🗨</span><span data-lp="privLabel">ocultar</span></div>
            </div>
            <div data-shimmer="accent" style="font-family: var(--f-m); font-size: 44px; font-weight: 700; color: var(--accent); margin-top: 8px; font-variant-numeric: tabular-nums;" data-lp="heroSaldo">R$ 12.450,80</div>
            <div style="display: flex; gap: 18px; margin-top: 8px; font-family: var(--f-m); font-size: 14px; font-variant-numeric: tabular-nums;">
              <span style="color: var(--pos);">↑ <span data-lp="heroIn">8.200</span> <span style="color: var(--muted); font-family: var(--f-b);">entrou</span></span>
              <span style="color: var(--neg);">↓ <span data-lp="heroOut">3.980</span> <span style="color: var(--muted); font-family: var(--f-b);">saiu</span></span>
            </div>
            <div style="position: relative; height: 176px; margin-top: 28px;">
          <div style="position: absolute; left: 34px; right: 0; top: 10px; bottom: 22px;">
            <div style="position: absolute; left: 0; right: 0; top: 0; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; top: 33%; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; top: 66%; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; bottom: 0; border-bottom: 1px solid var(--line);"></div>
            <span style="position: absolute; left: -34px; top: -7px; width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">15k</span>
            <span style="position: absolute; left: -34px; top: calc(33% - 7px); width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">10k</span>
            <span style="position: absolute; left: -34px; top: calc(66% - 7px); width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">5k</span>
            <div style="position: absolute; left: 0; width: 54%; top: 0; bottom: 0; display: flex; align-items: flex-end; gap: 9px;"><div data-bar="42" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-bar="55" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-bar="47" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-bar="63" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-bar="58" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-bar="64" style="flex: 1; height: 6%; background: var(--grad); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 10px 22px color-mix(in srgb, var(--accent) 32%, transparent);"></div></div>
            <div style="position: absolute; left: 54%; top: 0; bottom: 0; border-left: 1px dashed var(--line);"></div>
            <span style="position: absolute; left: 54%; top: -18px; transform: translateX(-50%); font-family: var(--f-m); font-size: 10px; color: var(--accent); font-weight: 700; background: var(--surface); padding: 0 5px;">hoje</span>
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none;">
              <path data-fadein="1" d="M54,36 L66,30 L80,24 L100,14 L100,100 L54,100 Z" fill="var(--accent)" fill-opacity="0.1" style="opacity: 0; transition: opacity .9s cubic-bezier(.16,1,.3,1) .7s;"></path>
              <path data-draw="1" d="M54,36 L66,30 L80,24 L100,14" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" pathLength="1" stroke-dasharray="1" stroke-dashoffset="1" style="transition: stroke-dashoffset 1.3s cubic-bezier(.16,1,.3,1) .4s;"></path>
            </svg>
            <span style="position: absolute; right: 0; top: -22px; font-family: var(--f-m); font-size: 11px; font-weight: 700; color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--surface)); border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); padding: 3px 9px; border-radius: var(--r-chip); white-space: nowrap;">ago → R$ 15,4k</span>
          </div>
          <div style="position: absolute; left: 34px; right: 0; bottom: 0; display: flex; justify-content: space-between; font-family: var(--f-m); font-size: 10.5px; color: var(--muted);"><span>mai</span><span>ago</span></div>
        </div>
          </div>
        </div>
        <div data-glass="1" style="position: absolute; z-index: 3; bottom: 0px; right: 16px; background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); transform: rotate(-2deg); animation: nvxFloatA 7s ease-in-out infinite;">
          <div style="height: 3px; background: var(--prism); display: var(--disp-prism); border-radius: 2px 2px 0 0;"></div>
          <div style="padding: 13px 18px; display: flex; align-items: center; gap: 11px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 700; color: var(--stage-ink); background: var(--gold); padding: 5px 11px; border-radius: var(--r-chip);"><span style="width: 8px; height: 8px; background: currentColor; transform: rotate(45deg);"></span>+50</span>
            <span style="font-size: 13.5px; font-weight: 600; line-height: 1.3;">Meta concluída<br><span style="color: var(--muted); font-weight: 500;">Reserva de emergência ✓</span></span>
          </div>
        </div>
        <div data-glass="1" data-toast="ifood" style="position: absolute; z-index: 3; top: -25px; left: -5px; background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 11px 16px; display: flex; align-items: center; gap: 9px; transform: rotate(2deg); animation: nvxFloatB 9s ease-in-out infinite;">
          <span style="width: 9px; height: 9px; background: var(--pos); transform: rotate(45deg);"></span>
          <span style="font-size: 13px; font-weight: 600;">iFood lançado <span style="font-family: var(--f-m); color: var(--neg);">−46,90</span> · <b style="color: var(--pos);">+10 pts</b></span>
        </div>
      </div>
    </div>

    <!-- setas laterais de tema (hero): absolutas nas bordas em telas largas; flanqueiam o label no rodapé em telas menores -->
    <div id="lp-hero-label" style="max-width: 1220px; margin: 0 auto; padding: 12px 32px 46px; display: flex; align-items: center; justify-content: center; gap: 16px;">
      <button type="button" class="lp-hero-arrow" data-act="sel-prev" data-selkey="1" aria-label="Tema anterior" title="Tema anterior" style="order: 0; position: absolute; left: clamp(6px, 1.6vw, 30px); top: 50%; transform: translateY(-50%); z-index: 5; width: clamp(64px, 5.8vw, 84px); height: clamp(110px, 13vw, 150px); display: grid; place-items: center; background: none; border: none; color: var(--ink); cursor: pointer; padding: 0; transition: color .2s ease;" data-hv="lp-hv-3">
        <span class="lp-hero-halo" style="display: grid; place-items: center; width: 58px; height: 58px; border-radius: 50%; transition: background .22s ease, transform .25s cubic-bezier(.16,1,.3,1);">
          <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
        </span>
      </button>
      <span style="order: 1; font-family: var(--f-m); font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: inline-flex; align-items: center; gap: 9px;"><span style="width: 9px; height: 9px; border-radius: 50%; background: var(--accent);"></span><span data-lp="selName">Ametista</span></span>
      <button type="button" class="lp-hero-arrow" data-act="sel-next" data-selkey="1" aria-label="Próximo tema" title="Próximo tema" style="order: 2; position: absolute; right: clamp(6px, 1.6vw, 30px); top: 50%; transform: translateY(-50%); z-index: 5; width: clamp(64px, 5.8vw, 84px); height: clamp(110px, 13vw, 150px); display: grid; place-items: center; background: none; border: none; color: var(--ink); cursor: pointer; padding: 0; transition: color .2s ease;" data-hv="lp-hv-3">
        <span class="lp-hero-halo" style="display: grid; place-items: center; width: 58px; height: 58px; border-radius: 50%; transition: background .22s ease, transform .25s cubic-bezier(.16,1,.3,1);">
          <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>
        </span>
      </button>
    </div>
  </header>

  <!-- ============ CREDIBILIDADE ============ -->
  <section data-screen-label="credibilidade" style="border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft); background: var(--surface);">
    <div style="overflow: hidden; padding: 26px 0;">
      <div data-mq-track="1" style="display: flex; width: max-content; animation: nvxMarquee 36s linear infinite;" data-hv="lp-hv-4"><div style="display: flex; align-items: center; gap: 64px; padding-right: 64px; flex-shrink: 0;"><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>LANÇAMENTO EM 3 SEGUNDOS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>PREVISÃO DE ATÉ 90 DIAS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>15 TEMAS COMPLETOS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>SEUS DADOS ENTRAM E SAEM EM CSV</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>GRÁTIS NO PLANO PESSOAL</span></div><div data-mq-dup="1" aria-hidden="true" style="display: flex; align-items: center; gap: 64px; padding-right: 64px; flex-shrink: 0;"><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>LANÇAMENTO EM 3 SEGUNDOS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>PREVISÃO DE ATÉ 90 DIAS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>15 TEMAS COMPLETOS</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>SEUS DADOS ENTRAM E SAEM EM CSV</span><span style="display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .14em; color: var(--muted); font-weight: 700; white-space: nowrap;"><span style="width: 8px; height: 8px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>GRÁTIS NO PLANO PESSOAL</span></div></div>
    </div>
  </section>

  <!-- ============ PROBLEMA → SOLUÇÃO ============ -->
  <section data-screen-label="problema-solucao" style="max-width: 1220px; margin: 0 auto; padding: 112px 32px 96px;">
    <div data-reveal="1" style="max-width: 720px;">
      <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">VOCÊ CONHECE ESSA SENSAÇÃO</div>
      <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">"Eu ganho bem. Pra onde está indo meu dinheiro?"</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 44px;">
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 16px; font-weight: 700; color: var(--neg); text-decoration: line-through; text-decoration-thickness: 2px;">"Descubro o estrago no fim do mês."</div>
        <div data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 20px 22px; flex: 1;">
          <div style="font-size: 15px; font-weight: 700;">Previsão de saldo</div>
          <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">O NovixFin projeta as próximas semanas com suas recorrências e faturas: você vê o buraco <i>antes</i> de cair nele.</div>
        </div>
      </div>
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 16px; font-weight: 700; color: var(--neg); text-decoration: line-through; text-decoration-thickness: 2px;">"Anotar gasto é chato, eu desisto."</div>
        <div data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 20px 22px; flex: 1;">
          <div style="font-size: 15px; font-weight: 700;">Lançamento em 3 segundos</div>
          <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Valor, categoria, pronto. Um toque de qualquer tela, atalhos pros gastos de sempre, e pontos por manter o hábito.</div>
        </div>
      </div>
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 16px; font-weight: 700; color: var(--neg); text-decoration: line-through; text-decoration-thickness: 2px;">"Planilha funciona… por duas semanas."</div>
        <div data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 20px 22px; flex: 1;">
          <div style="font-size: 15px; font-weight: 700;">Um app que dá vontade de abrir</div>
          <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Metas com progresso visível, conquistas celebradas e 15 temas pra chamar de seu. Constância vem de prazer, não de culpa.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ DEMO 3 SEGUNDOS ============ -->
  <section id="demo" data-screen-label="demo 3s" style="background: var(--surface); border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 108px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center;">
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 20px;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">A AÇÃO Nº 1, SEM FRICÇÃO</div>
        <h2 style="color: var(--ink); margin: 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">Lançou em 3 segundos. De verdade.</h2>
        <p style="margin: 0; font-size: 18px; color: var(--muted); line-height: 1.6; max-width: 460px;">Na fila do café, no semáforo, no sofá: o lançamento rápido abre em um toque, entende vírgula de centavo e confirma com você ainda olhando. Pendente ou efetivada: você decide depois.</p>
        <div style="display: flex; gap: 26px; margin-top: 6px;">
          <div><div style="font-family: var(--f-m); font-size: 30px; font-weight: 700; color: var(--accent);">1 toque</div><div style="font-size: 13px; color: var(--muted); font-weight: 600;">de qualquer tela</div></div>
          <div style="border-left: 1px solid var(--line-soft); padding-left: 26px;"><div style="font-family: var(--f-m); font-size: 30px; font-weight: 700; color: var(--accent);">3 s</div><div style="font-size: 13px; color: var(--muted); font-weight: 600;">por lançamento</div></div>
          <div style="border-left: 1px solid var(--line-soft); padding-left: 26px;"><div style="font-family: var(--f-m); font-size: 30px; font-weight: 700; color: var(--accent);">+10 pts</div><div style="font-size: 13px; color: var(--muted); font-weight: 600;">quando efetiva</div></div>
        </div>
      </div>
      <div data-reveal="1" style="position: relative; display: flex; justify-content: center;">
        <div style="width: 420px; background: var(--facet), var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); clip-path: var(--cut-card); padding: 24px 26px; transform: perspective(1200px) rotateY(6deg); transition: transform .5s ease;" data-hv="lp-hv-5">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700;">Novo lançamento</div>
            <div style="width: 32px; height: 32px; border: 1px solid var(--line-soft); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--muted);">✕</div>
          </div>
          <div style="display: flex; border: 1px solid var(--line); border-radius: var(--r-btn); overflow: hidden; margin-top: 14px;">
            <span style="flex: 1; text-align: center; font-size: 13px; font-weight: 700; background: var(--accent); color: var(--accent-ink); padding: 9px 0; clip-path: var(--cut);">Despesa</span>
            <span style="flex: 1; text-align: center; font-size: 13px; font-weight: 600; color: var(--muted); padding: 9px 0;">Receita</span>
          </div>
          <div style="margin-top: 14px;">
            <div style="font-size: 11.5px; color: var(--muted); font-weight: 600;">Valor</div>
            <div style="font-family: var(--f-m); font-size: 36px; font-weight: 700; border-bottom: 2px solid var(--accent); padding-bottom: 6px; font-variant-numeric: tabular-nums;"><span data-lp="demoVal">R$ </span><span style="animation: nvxBlink 1s step-end infinite; color: var(--accent); font-weight: 400;">|</span></div>
          </div>
          <div style="display: flex; gap: 7px; flex-wrap: wrap; margin-top: 14px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; background: var(--surface); color: var(--muted); border: 1px solid var(--line-soft); padding: 7px 13px; border-radius: var(--r-chip); transition: background .3s, color .3s;" id="lp-demo-cat"><span style="width: 7px; height: 7px; background: currentColor; transform: rotate(45deg);"></span>Alimentação</span>
            <span style="font-size: 12px; font-weight: 600; color: var(--muted); border: 1px solid var(--line-soft); padding: 7px 13px; border-radius: var(--r-chip);">Transporte</span>
            <span style="font-size: 12px; font-weight: 600; color: var(--muted); border: 1px solid var(--line-soft); padding: 7px 13px; border-radius: var(--r-chip);">Casa</span>
            <span style="font-size: 12px; font-weight: 600; color: var(--muted); border: 1px solid var(--line-soft); padding: 7px 13px; border-radius: var(--r-chip);">Lazer</span>
          </div>
          <div style="display: flex; gap: 9px; margin-top: 14px;">
            <div style="flex: 1; background: var(--surface); border: 1px solid var(--line-soft); border-radius: var(--r-chip); padding: 9px 13px;"><div style="font-size: 10.5px; color: var(--muted); font-weight: 600;">Conta</div><div style="font-size: 13px; font-weight: 700;">Nubank ▾</div></div>
            <div style="flex: 1; background: var(--surface); border: 1px solid var(--line-soft); border-radius: var(--r-chip); padding: 9px 13px;"><div style="font-size: 10.5px; color: var(--muted); font-weight: 600;">Data</div><div style="font-size: 13px; font-weight: 700;">hoje ▾</div></div>
          </div>
          <div style="background: var(--sheen); color: var(--accent-ink); font-family: var(--f-d); font-weight: 700; font-size: 15px; text-align: center; padding: 14px 0; margin-top: 16px; clip-path: var(--cut); border-radius: var(--r-btn); transform: scale(1); transition: transform .18s ease;" id="lp-demo-btn">Lançar transação</div>
        </div>
        <div style="position: absolute; bottom: -18px; right: 8px; background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 12px 18px; display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(12px); transition: opacity .35s ease, transform .35s ease;" id="lp-demo-toast">
          <span style="width: 9px; height: 9px; background: var(--pos); transform: rotate(45deg);"></span>
          <span style="font-size: 13.5px; font-weight: 700;">Lançada em 2,8s · <span style="color: var(--pos);">+10 pontos</span></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ PREVISÃO — SEÇÃO ESTRELA ============ -->
  <section id="previsao" data-screen-label="previsão estrela" data-pin-wrap="1" style="max-width: 1220px; margin: 0 auto; padding: 96px 32px;">
    <div data-pin-inner="1">
    <div style="display: grid; grid-template-columns: 1fr 1.15fr; gap: 64px; align-items: center;">
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 20px;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">O RECURSO-ESTRELA</div>
        <h2 style="color: var(--ink); margin: 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.8vw, 54px); line-height: 1.06;">Veja o seu mês antes de ele acontecer.</h2>
        <p style="margin: 0; font-size: 18px; color: var(--muted); line-height: 1.6; max-width: 460px;">Salário, aluguel, assinaturas, fatura do cartão: o NovixFin cruza tudo que é recorrente com o que você lança no dia a dia e desenha o saldo das próximas semanas. Decisão com antecedência, não arrependimento com extrato.</p>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 4px;">
          <div style="display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>Recorrências e faturas entram sozinhas na projeção</div>
          <div style="display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>Pendente vs efetivada: o "vai entrar" não vira ilusão</div>
          <div style="display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0;"></span>Horizonte de 4 semanas a 90 dias</div>
        </div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 30px 34px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div style="font-family: var(--f-d); font-size: 19px; font-weight: 700;">Previsão de saldo</div>
          <div style="display: flex; align-items: center; border: 1px solid var(--line); border-radius: var(--r-btn); overflow: hidden;">
            <span style="font-size: 12px; font-weight: 600; color: var(--muted); padding: 8px 14px;">4 sem</span>
            <span style="font-size: 12px; font-weight: 700; background: var(--accent); color: var(--accent-ink); padding: 8px 15px; clip-path: var(--cut);">90 dias</span>
          </div>
        </div>
        <div style="font-family: var(--f-m); font-size: 40px; font-weight: 700; margin-top: 12px; font-variant-numeric: tabular-nums;">R$ <span data-count="18200" data-suf="">0</span></div>
        <div style="display: inline-flex; font-size: 12.5px; color: var(--pos); font-weight: 700; margin-top: 4px; background: var(--bg); border: 1px solid var(--line-soft); padding: 4px 12px; border-radius: var(--r-chip);">saldo projetado pra outubro · no azul, com folga</div>
        <div style="position: relative; height: 218px; margin-top: 30px;">
          <div style="position: absolute; left: 34px; right: 0; top: 12px; bottom: 22px;">
            <div style="position: absolute; left: 0; right: 0; top: 0; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; top: 33%; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; top: 66%; border-top: 1px solid var(--line-soft);"></div>
            <div style="position: absolute; left: 0; right: 0; bottom: 0; border-bottom: 1px solid var(--line);"></div>
            <span style="position: absolute; left: -34px; top: -7px; width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">18k</span>
            <span style="position: absolute; left: -34px; top: calc(33% - 7px); width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">12k</span>
            <span style="position: absolute; left: -34px; top: calc(66% - 7px); width: 28px; text-align: right; font-family: var(--f-m); font-size: 10px; color: var(--muted);">6k</span>
            <div style="position: absolute; left: 0; width: 46%; top: 0; bottom: 0; display: flex; align-items: flex-end; gap: 10px;"><div data-pbar="30" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-pbar="42" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-pbar="36" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-pbar="52" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-pbar="46" style="flex: 1; height: 6%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 6px 14px rgba(0,0,0,.08);"></div><div data-pbar="60" style="flex: 1; height: 6%; background: var(--grad); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 10px 22px color-mix(in srgb, var(--accent) 32%, transparent);"></div></div>
            <div style="position: absolute; left: 46%; top: 0; bottom: 0; border-left: 1px dashed var(--line);"></div>
            <span style="position: absolute; left: 46%; top: -18px; transform: translateX(-50%); font-family: var(--f-m); font-size: 10px; color: var(--accent); font-weight: 700; background: var(--surface); padding: 0 5px;">hoje</span>
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none;">
              <path data-parea="1" d="M46,40 L60,32 L74,25 L88,18 L100,10 L100,100 L46,100 Z" fill="var(--accent)" fill-opacity="0.1" style="opacity: 0; transition: opacity .9s cubic-bezier(.16,1,.3,1) .7s;"></path>
              <path data-pline="1" d="M46,40 L60,32 L74,25 L88,18 L100,10" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" pathLength="1" stroke-dasharray="1" stroke-dashoffset="1" style="transition: stroke-dashoffset 1.3s cubic-bezier(.16,1,.3,1) .4s;"></path>
            </svg>
            <span style="position: absolute; right: 0; top: -22px; font-family: var(--f-m); font-size: 11px; font-weight: 700; color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--surface)); border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); padding: 3px 9px; border-radius: var(--r-chip); white-space: nowrap;" data-plabel="1">out → R$ 18.200</span>
          </div>
          <div style="position: absolute; left: 34px; right: 0; bottom: 0; display: flex; justify-content: space-between; font-family: var(--f-m); font-size: 10.5px; color: var(--muted);"><span>jun</span><span>out</span></div>
        </div>
      </div>
    </div>
    </div>
  </section>

  <!-- ============ TOUR DE PRODUTO ============ -->
  <section data-screen-label="tour de produto" style="background: var(--surface); border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 108px 32px;">
      <div data-reveal="1" style="text-align: center; max-width: 680px; margin: 0 auto;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">TOUR DE PRODUTO</div>
        <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">Navegue o app sem sair daqui.</h2>
        <p style="margin: 12px 0 0; font-size: 17px; color: var(--muted); line-height: 1.6;">Telas reais, no tema que você escolheu ali em cima, não screenshots congelados.</p>
      </div>
      <div data-reveal="1" style="display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
        <button data-act="tab" data-tab="dash" style="font-family: var(--f-b); font-size: 14px; font-weight: 700; padding: 11px 20px; min-height: 44px; cursor: pointer; border: 1px solid var(--accent); background: var(--accent); color: var(--accent-ink); border-radius: var(--r-btn); clip-path: var(--cut);">Dashboard</button>
        <button data-act="tab" data-tab="tx" style="font-family: var(--f-b); font-size: 14px; font-weight: 700; padding: 11px 20px; min-height: 44px; cursor: pointer; border: 1px solid var(--line); background: var(--surface); color: var(--muted); border-radius: var(--r-btn); clip-path: none;">Transações</button>
        <button data-act="tab" data-tab="card" style="font-family: var(--f-b); font-size: 14px; font-weight: 700; padding: 11px 20px; min-height: 44px; cursor: pointer; border: 1px solid var(--line); background: var(--surface); color: var(--muted); border-radius: var(--r-btn); clip-path: none;">Cartões</button>
        <button data-act="tab" data-tab="goal" style="font-family: var(--f-b); font-size: 14px; font-weight: 700; padding: 11px 20px; min-height: 44px; cursor: pointer; border: 1px solid var(--line); background: var(--surface); color: var(--muted); border-radius: var(--r-btn); clip-path: none;">Metas</button>
        <button data-act="tab" data-tab="kan" style="font-family: var(--f-b); font-size: 14px; font-weight: 700; padding: 11px 20px; min-height: 44px; cursor: pointer; border: 1px solid var(--line); background: var(--surface); color: var(--muted); border-radius: var(--r-btn); clip-path: none;">Kanban</button>
      </div>

      <div id="tourPanel" data-tilt="3" data-tilt-base="" data-reveal="1" style="margin-top: 16px; background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 22px;">
        <div data-tab-panel="dash">
          <div style="display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 16px;">
            <div data-float="1" style="background: var(--facet), var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); clip-path: var(--cut-card); padding: 20px 24px;">
              <div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">Saldo total</div>
              <div style="font-family: var(--f-m); font-size: 34px; font-weight: 700; color: var(--accent); margin-top: 6px;">R$ 12.450,80</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 6px;">4 contas · 2 carteiras · multi-moeda</div>
              <div style="position: relative; height: 92px; margin-top: 16px;">
                <div style="position: absolute; left: 0; right: 0; top: 33%; border-top: 1px solid var(--line-soft);"></div>
                <div style="position: absolute; left: 0; right: 0; top: 66%; border-top: 1px solid var(--line-soft);"></div>
                <div style="position: absolute; left: 0; right: 0; bottom: 0; border-bottom: 1px solid var(--line);"></div>
                <div style="position: absolute; left: 0; width: 52%; top: 0; bottom: 0; display: flex; align-items: flex-end; gap: 7px;"><div style="flex: 1; height: 44%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip);"></div><div style="flex: 1; height: 58%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip);"></div><div style="flex: 1; height: 50%; background: linear-gradient(180deg, color-mix(in srgb, var(--ink) 90%, transparent), color-mix(in srgb, var(--ink) 60%, transparent)); clip-path: var(--cut); border-radius: var(--r-chip);"></div><div style="flex: 1; height: 68%; background: var(--grad); clip-path: var(--cut); border-radius: var(--r-chip); box-shadow: 0 8px 18px color-mix(in srgb, var(--accent) 30%, transparent);"></div></div>
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none;">
                  <path d="M52,32 L72,24 L100,14 L100,100 L52,100 Z" fill="var(--accent)" fill-opacity="0.1"></path>
                  <path d="M52,32 L72,24 L100,14" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>
                </svg>
              </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
              <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 18px 20px; flex: 1;"><div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">entrou</div><div style="font-family: var(--f-m); font-size: 24px; font-weight: 700; color: var(--pos); margin-top: 8px;">+8.200,00</div></div>
              <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 18px 20px; flex: 1;"><div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">saiu</div><div style="font-family: var(--f-m); font-size: 24px; font-weight: 700; color: var(--neg); margin-top: 8px;">−3.980,00</div></div>
            </div>
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 18px 20px;">
              <div style="font-size: 14px; font-weight: 700; color: var(--ink);">Hoje</div>
              <div style="display: flex; align-items: center; gap: 9px; padding: 9px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg); flex-shrink: 0;"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600; color: var(--ink);">iFood</span><span style="font-family: var(--f-m); font-size: 13px; color: var(--neg);">−46,90</span></div>
              <div style="display: flex; align-items: center; gap: 9px; padding: 9px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; border: 1.5px dashed var(--muted); transform: rotate(45deg); box-sizing: border-box; flex-shrink: 0;"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600; color: var(--ink);">Freela</span><span style="font-family: var(--f-m); font-size: 13px; color: var(--pos);">+900,00</span></div>
              <div style="display: flex; align-items: center; gap: 9px; padding: 9px 0;"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg); flex-shrink: 0;"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600; color: var(--ink);">Uber</span><span style="font-family: var(--f-m); font-size: 13px; color: var(--neg);">−18,40</span></div>
            </div>
          </div>
        </div>
        <div data-tab-panel="tx" hidden>
          <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 10px 26px;">
            <div style="display: flex; justify-content: space-between; padding: 12px 0 8px; border-bottom: 1px solid var(--line);"><span style="font-size: 11.5px; font-weight: 700; color: var(--muted); letter-spacing: .06em;">HOJE · QUA 8 JUL</span><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted);">−65,30</span></div>
            <div style="display: flex; align-items: center; gap: 13px; padding: 13px 0; border-bottom: 1px solid var(--line-soft);"><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted); width: 42px;">21:04</span><span style="width: 10px; height: 10px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 14.5px; font-weight: 600;">iFood <span style="font-size: 12px; color: var(--muted); font-weight: 500;">· Alimentação · Nubank</span></span><span style="font-family: var(--f-m); font-size: 14px; color: var(--neg);">−46,90</span></div>
            <div style="display: flex; align-items: center; gap: 13px; padding: 13px 0; border-bottom: 1px solid var(--line-soft);"><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted); width: 42px;">19:32</span><span style="width: 10px; height: 10px; border: 1.5px dashed var(--muted); transform: rotate(45deg); box-sizing: border-box;"></span><span style="flex: 1; font-size: 14.5px; font-weight: 600;">Freela logo <span style="font-size: 10.5px; color: var(--muted); border: 1px dashed var(--line); padding: 1px 6px; font-weight: 700; border-radius: var(--r-chip);">pendente</span></span><span style="font-size: 12px; font-weight: 700; color: var(--accent); border: 1.5px solid var(--accent); padding: 5px 12px; border-radius: var(--r-chip); cursor: pointer;">✓ efetivar</span><span style="font-family: var(--f-m); font-size: 14px; color: var(--pos);">+900,00</span></div>
            <div style="display: flex; align-items: center; gap: 13px; padding: 13px 0; border-bottom: 1px solid var(--line-soft);"><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted); width: 42px;">14:11</span><span style="width: 10px; height: 10px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 14.5px; font-weight: 600;">Uber <span style="font-size: 12px; color: var(--muted); font-weight: 500;">· Transporte · Nubank</span></span><span style="font-family: var(--f-m); font-size: 14px; color: var(--neg);">−18,40</span></div>
            <div style="display: flex; justify-content: space-between; padding: 14px 0 8px; border-bottom: 1px solid var(--line);"><span style="font-size: 11.5px; font-weight: 700; color: var(--muted); letter-spacing: .06em;">ONTEM · TER 7 JUL</span><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted);">+6.240,73</span></div>
            <div style="display: flex; align-items: center; gap: 13px; padding: 13px 0;"><span style="font-family: var(--f-m); font-size: 12px; color: var(--muted); width: 42px;">09:00</span><span style="width: 10px; height: 10px; background: var(--pos); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 14.5px; font-weight: 600;">Salário ACME <span style="font-size: 12px; color: var(--muted); font-weight: 500;">· Renda · Itaú</span></span><span style="font-family: var(--f-m); font-size: 14px; color: var(--pos);">+6.500,00</span></div>
          </div>
        </div>
        <div data-tab-panel="card" hidden>
          <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 16px;">
            <div style="background: var(--facet), var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); clip-path: var(--cut-card); padding: 22px 24px;">
              <div style="display: flex; justify-content: space-between;"><span style="font-size: 14px; font-weight: 700;">Nubank · crédito</span><span style="font-size: 12px; color: var(--muted); font-weight: 600;">fecha em 12 dias</span></div>
              <div style="font-size: 12.5px; color: var(--muted); font-weight: 600; margin-top: 16px;">Fatura atual</div>
              <div style="font-family: var(--f-m); font-size: 32px; font-weight: 700; margin-top: 4px;">R$ 1.284,50</div>
              <div style="height: 8px; background: var(--line-soft); border-radius: var(--r-chip); margin-top: 14px;"><div style="width: 58%; height: 100%; background: var(--accent); border-radius: var(--r-chip);"></div></div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 6px;"><span>58% do limite</span><span>limite R$ 2.200</span></div>
              <div style="background: var(--sheen); color: var(--accent-ink); font-family: var(--f-d); font-weight: 700; font-size: 14px; text-align: center; padding: 12px 0; margin-top: 16px; clip-path: var(--cut); border-radius: var(--r-btn); cursor: pointer;">Pagar fatura · +15 pts</div>
            </div>
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 22px 24px;">
              <div style="font-size: 14px; font-weight: 700;">Lançamentos da fatura</div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Spotify <span style="font-size: 11.5px; color: var(--muted); font-weight: 500;">· recorrente</span></span><span style="font-family: var(--f-m); font-size: 13px;">21,90</span></div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Mercado Dia</span><span style="font-family: var(--f-m); font-size: 13px;">214,37</span></div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Gasolina Shell</span><span style="font-family: var(--f-m); font-size: 13px;">180,00</span></div>
              <div style="display: flex; align-items: center; gap: 12px; padding: 11px 0;"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Amazon <span style="font-size: 11.5px; color: var(--muted); font-weight: 500;">· parcela 2/4</span></span><span style="font-family: var(--f-m); font-size: 13px;">89,90</span></div>
            </div>
          </div>
        </div>
        <div data-tab-panel="goal" hidden>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px;">
              <div style="display: flex; justify-content: space-between; align-items: baseline;"><span style="font-family: var(--f-d); font-size: 17px; font-weight: 700;">Reserva de emergência</span><span style="font-family: var(--f-m); font-size: 17px; font-weight: 700; color: var(--accent);">62%</span></div>
              <div style="height: 10px; background: var(--line-soft); margin-top: 14px; border-radius: var(--r-chip); position: relative;"><div data-bar-x="62" style="width: 62%; height: 100%; background: var(--accent); border-radius: var(--r-chip);"></div><div style="position: absolute; left: 62%; top: 50%; width: 14px; height: 14px; background: var(--accent); transform: translate(-50%,-50%) rotate(45deg); border: 2.5px solid var(--surface); box-sizing: border-box;"></div></div>
              <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); margin-top: 10px;"><span>R$ 6.200 de R$ 10.000</span><span style="font-weight: 700; color: var(--pos);">no ritmo · faltam 4 meses</span></div>
              <div style="font-size: 13px; color: var(--muted); margin-top: 14px; border-top: 1px solid var(--line-soft); padding-top: 12px;">Aporte sugerido este mês: <b style="color: var(--ink);">R$ 950</b>, cabe na sua sobra prevista.</div>
            </div>
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px;">
              <div style="display: flex; justify-content: space-between; align-items: baseline;"><span style="font-family: var(--f-d); font-size: 17px; font-weight: 700;">Viagem Chapada</span><span style="font-family: var(--f-m); font-size: 17px; font-weight: 700; color: var(--accent);">45%</span></div>
              <div style="height: 10px; background: var(--line-soft); margin-top: 14px; border-radius: var(--r-chip); position: relative;"><div style="width: 45%; height: 100%; background: var(--accent); border-radius: var(--r-chip);"></div><div style="position: absolute; left: 45%; top: 50%; width: 14px; height: 14px; background: var(--accent); transform: translate(-50%,-50%) rotate(45deg); border: 2.5px solid var(--surface); box-sizing: border-box;"></div></div>
              <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--muted); margin-top: 10px;"><span>R$ 1.350 de R$ 3.000</span><span>meta: dezembro</span></div>
              <div style="display: flex; align-items: center; gap: 10px; margin-top: 14px; border-top: 1px solid var(--line-soft); padding-top: 12px;">
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--stage-ink); background: var(--gold); padding: 5px 11px; border-radius: var(--r-chip);"><span style="width: 7px; height: 7px; background: currentColor; transform: rotate(45deg);"></span>+50</span>
                <span style="font-size: 13px; color: var(--muted);">ao concluir, celebramos com você</span>
              </div>
            </div>
          </div>
        </div>
        <div data-tab-panel="kan" hidden>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 16px 18px;">
              <div style="font-size: 12px; font-weight: 700; letter-spacing: .08em; color: var(--muted);">A PAGAR · 3</div>
              <div style="border: 1px solid var(--line-soft); border-left: 3px solid var(--neg); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 12px; cursor: grab;"><div style="font-size: 13.5px; font-weight: 700;">Aluguel</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--neg); margin-top: 3px;">−1.800,00 · dia 10</div></div>
              <div style="border: 1px solid var(--line-soft); border-left: 3px solid var(--neg); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 10px; cursor: grab;"><div style="font-size: 13.5px; font-weight: 700;">Internet</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--neg); margin-top: 3px;">−99,90 · dia 12</div></div>
            </div>
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 16px 18px;">
              <div style="font-size: 12px; font-weight: 700; letter-spacing: .08em; color: var(--muted);">PENDENTE · 2</div>
              <div style="border: 1.5px dashed var(--line); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 12px; cursor: grab;"><div style="font-size: 13.5px; font-weight: 700;">Freela logo</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--pos); margin-top: 3px;">+900,00 · aguardando</div></div>
              <div style="border: 1.5px dashed var(--line); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 10px; cursor: grab; transform: rotate(-1.5deg); border-color: var(--accent);"><div style="font-size: 13.5px; font-weight: 700;">Reembolso plano</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--pos); margin-top: 3px;">+230,00 · arraste →</div></div>
            </div>
            <div style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 16px 18px;">
              <div style="font-size: 12px; font-weight: 700; letter-spacing: .08em; color: var(--muted);">EFETIVADA · 24</div>
              <div style="border: 1px solid var(--line-soft); border-left: 3px solid var(--pos); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 12px;"><div style="font-size: 13.5px; font-weight: 700;">Salário ACME</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--pos); margin-top: 3px;">+6.500,00 · ✓</div></div>
              <div style="border: 1px solid var(--line-soft); border-left: 3px solid var(--pos); border-radius: var(--r-chip); padding: 12px 14px; margin-top: 10px;"><div style="font-size: 13.5px; font-weight: 700;">iFood</div><div style="font-family: var(--f-m); font-size: 13px; color: var(--neg); margin-top: 3px;">−46,90 · ✓ +10 pts</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ FEATURES GRID ============ -->
  <section data-screen-label="features" style="max-width: 1220px; margin: 0 auto; padding: 112px 32px;">
    <div data-reveal="1" style="max-width: 640px;">
      <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">TUDO QUE O DIA A DIA PEDE</div>
      <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">Completo sem ser complicado.</h2>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 18px; margin-top: 40px; justify-content: center;">
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px; flex: 1 1 300px; max-width: 372px; box-sizing: border-box; transition: transform .18s ease;" data-hv="lp-hv-6">
        <div style="width: 34px; height: 34px; background: var(--accent); clip-path: var(--cut); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; color: var(--accent-ink); font-family: var(--f-m); font-size: 15px; font-weight: 700;">R$</div>
        <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700; margin-top: 14px;">Contas e carteiras</div>
        <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Banco, dinheiro vivo, vale-refeição: saldos consolidados com transferências entre eles em dois toques.</div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px; flex: 1 1 300px; max-width: 372px; box-sizing: border-box; transition: transform .18s ease;" data-hv="lp-hv-6">
        <div style="width: 34px; height: 34px; border: 2px solid var(--accent); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 15px; font-weight: 800;">▦</div>
        <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700; margin-top: 14px;">Cartões e faturas</div>
        <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Fatura aberta, fechada e prevista na projeção. Pagar fatura vale +15 pontos, e quitar vira hábito.</div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px; flex: 1 1 300px; max-width: 372px; box-sizing: border-box; transition: transform .18s ease;" data-hv="lp-hv-6">
        <div style="width: 34px; height: 34px; background: var(--pos); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; color: var(--surface); font-size: 16px;">↻</div>
        <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700; margin-top: 14px;">Recorrências</div>
        <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Assinaturas e contas fixas se lançam sozinhas e aparecem na previsão antes de pesar no bolso.</div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px; flex: 1 1 300px; max-width: 372px; box-sizing: border-box; transition: transform .18s ease;" data-hv="lp-hv-6">
        <div style="width: 34px; height: 34px; background: var(--gold); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; color: var(--stage-ink);"><span style="width: 11px; height: 11px; background: currentColor; transform: rotate(45deg); display: inline-block;"></span></div>
        <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700; margin-top: 14px;">Metas com progresso</div>
        <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Defina o objetivo, acompanhe o avanço e receba aporte sugerido conforme a sobra prevista do mês.</div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 24px 26px; flex: 1 1 300px; max-width: 372px; box-sizing: border-box; transition: transform .18s ease;" data-hv="lp-hv-6">
        <div style="width: 34px; height: 34px; border: 2px solid var(--accent-2); border-radius: var(--r-chip); display: flex; align-items: center; justify-content: center; color: var(--accent-2); font-family: var(--f-m); font-size: 13px; font-weight: 700;">$€</div>
        <div style="font-family: var(--f-d); font-size: 18px; font-weight: 700; margin-top: 14px;">Multi-moeda</div>
        <div style="font-size: 14px; color: var(--muted); line-height: 1.55; margin-top: 6px;">Recebe em dólar, gasta em real? Conversão automática com câmbio do dia e consolidação na sua moeda.</div>
      </div>
    </div>
  </section>

  <!-- ============ ANTES / DEPOIS ============ -->
  <section data-screen-label="antes-depois" style="background: var(--surface); border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 108px 32px;">
      <div data-reveal="1" style="text-align: center; max-width: 620px; margin: 0 auto;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">A MUDANÇA, LADO A LADO</div>
        <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">Da planilha que você abandona ao painel que te espera.</h2>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-top: 44px; align-items: stretch;">
        <div data-reveal="1" style="border: 1px dashed var(--line); border-radius: var(--r); padding: 24px; background: var(--bg); position: relative; transform: rotate(-0.6deg);">
          <div style="display: inline-flex; width: fit-content; background: var(--neg); color: var(--surface); font-size: 11.5px; font-weight: 800; letter-spacing: .1em; padding: 5px 12px; border-radius: var(--r-chip); margin: -2px 0 8px;">ANTES · gastos_2026_FINAL_v3.xlsx</div>
          <div style="font-family: monospace; font-size: 12.5px; color: var(--muted); line-height: 2.1; margin-top: 10px; overflow: hidden;">
            <div style="display: flex; gap: 10px; border-bottom: 1px solid var(--line-soft);"><span style="width: 30px;">A</span><span style="flex: 1;">mercado???</span><span>-214</span><span style="color: var(--neg);">#REF!</span></div>
            <div style="display: flex; gap: 10px; border-bottom: 1px solid var(--line-soft);"><span style="width: 30px;">B</span><span style="flex: 1;">uber (ou 99?)</span><span>-18,4</span><span></span></div>
            <div style="display: flex; gap: 10px; border-bottom: 1px solid var(--line-soft);"><span style="width: 30px;">C</span><span style="flex: 1;">salario</span><span>6500</span><span style="color: var(--neg);">#DIV/0!</span></div>
            <div style="display: flex; gap: 10px; border-bottom: 1px solid var(--line-soft);"><span style="width: 30px;">D</span><span style="flex: 1;">esqueci o que foi isso</span><span>-89,9</span><span></span></div>
            <div style="display: flex; gap: 10px;"><span style="width: 30px;">E</span><span style="flex: 1; color: var(--neg); font-weight: 700;">SALDO = ???</span><span></span><span style="color: var(--neg);">#N/A</span></div>
          </div>
          <div style="font-size: 13px; color: var(--muted); margin-top: 16px; line-height: 1.5;">Atualizada pela última vez há 23 dias. Fórmula quebrada. Nenhuma previsão. Zero vontade de voltar.</div>
        </div>
        <div data-reveal="1" data-card="1" style="background: var(--facet), var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); clip-path: var(--cut-card); padding: 24px; position: relative;">
          <div style="display: inline-flex; width: fit-content; background: var(--pos); color: var(--surface); font-size: 11.5px; font-weight: 800; letter-spacing: .1em; padding: 5px 12px; border-radius: var(--r-chip); margin: -2px 0 8px;">DEPOIS · NovixFin</div>
          <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 10px;">
            <div><div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">Saldo total</div><div style="font-family: var(--f-m); font-size: 32px; font-weight: 700; color: var(--accent);">R$ 12.450,80</div></div>
            <div style="text-align: right;"><div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">sobra prevista</div><div style="font-family: var(--f-m); font-size: 20px; font-weight: 700; color: var(--pos);">+R$ 1.240</div></div>
          </div>
          <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line-soft); margin-top: 12px;"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Mercado Dia <span style="font-size: 11.5px; color: var(--muted); font-weight: 500;">· Alimentação</span></span><span style="font-family: var(--f-m); font-size: 13px; color: var(--neg);">−214,37</span></div>
          <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line-soft);"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Uber <span style="font-size: 11.5px; color: var(--muted); font-weight: 500;">· Transporte</span></span><span style="font-family: var(--f-m); font-size: 13px; color: var(--neg);">−18,40</span></div>
          <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0;"><span style="width: 9px; height: 9px; background: var(--pos); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Salário ACME <span style="font-size: 11.5px; color: var(--muted); font-weight: 500;">· Renda</span></span><span style="font-family: var(--f-m); font-size: 13px; color: var(--pos);">+6.500,00</span></div>
          <div style="font-size: 13px; color: var(--muted); margin-top: 14px; line-height: 1.5;">Categorizado na hora, previsão sempre viva, 3 segundos por lançamento, e <b style="color: var(--ink);">+10 pontos</b> a cada um.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 15 TEMAS + SWITCHER ============ -->
  <section id="temas" data-screen-label="15 temas switcher" style="max-width: 1220px; margin: 0 auto; padding: 112px 32px;">
    <div data-reveal="1" style="text-align: center; max-width: 760px; margin: 0 auto;">
      <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">ÚNICO NO MERCADO</div>
      <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(38px, 4.4vw, 62px); line-height: 1.04;">Escolha o seu NovixFin: <span style="background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent;">15 jeitos de ver seu dinheiro</span>.</h2>
      <p style="margin: 14px auto 0; font-size: 17px; color: var(--muted); line-height: 1.6; max-width: 600px;">Nenhum concorrente te deixa escolher a alma do app. Aqui, do terminal preto-no-branco ao salão art déco, cada tema é um design completo, não um filtro de cor. <b style="color: var(--ink);">Clique e vista agora:</b></p>
    </div>

    <div data-reveal="1" id="lp-sel" tabindex="0" role="group" aria-label="Seletor de tema. Use as setas para folhear os 15 temas." data-selkey="1" style="margin-top: 30px; display: flex; align-items: center; justify-content: center; gap: clamp(14px, 4.5vw, 46px); padding: 28px 8px 12px;">
      <button type="button" data-act="sel-prev" aria-label="Tema anterior" title="Tema anterior" style="width: 64px; height: 64px; flex: none; display: grid; place-items: center; background: var(--surface); border: 1px solid var(--line-soft); border-radius: var(--r-chip); clip-path: var(--cut); color: var(--ink); cursor: pointer; padding: 0; transition: transform .2s ease, color .2s ease, border-color .2s ease;" data-hv="lp-hv-7">
        <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
      </button>
      <div style="min-width: min(430px, 52vw); text-align: center; display: flex; flex-direction: column; align-items: center; gap: 9px;">
        <span style="display: inline-flex; align-items: center; gap: 10px;">
          <span style="width: 14px; height: 14px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 16%, transparent); transition: background-color .5s ease;"></span>
          <span style="font-family: var(--f-m); font-size: 11.5px; letter-spacing: .2em; color: var(--muted);" data-lp="selPos">01 / 15</span>
        </span>
        <span aria-live="polite" style="display: flex; align-items: center; justify-content: center; min-height: 62px; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(30px, 3.5vw, 46px); line-height: 1.08; color: var(--ink); text-wrap: balance; " data-lp="selNameBig">Ametista</span>
        <span style="font-size: 13.5px; color: var(--muted); min-height: 19px;" data-lp="selTag">pedra lapidada · claro</span>
      </div>
      <button type="button" data-act="sel-next" aria-label="Próximo tema" title="Próximo tema" style="width: 64px; height: 64px; flex: none; display: grid; place-items: center; background: var(--surface); border: 1px solid var(--line-soft); border-radius: var(--r-chip); clip-path: var(--cut); color: var(--ink); cursor: pointer; padding: 0; transition: transform .2s ease, color .2s ease, border-color .2s ease;" data-hv="lp-hv-7">
        <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>
      </button>
    </div>

    <div data-reveal="1" style="overflow: hidden; margin-top: 26px; padding: 12px 0 16px; -webkit-mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent); mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent);">
      <div class="lp-track" id="lp-track" style="display: flex; gap: 16px; width: max-content; margin-left: 50%; transform: translateX(-3650px); transition: transform .45s cubic-bezier(.2,.8,.2,1); will-change: transform;">
        
      </div>
    </div>
    <div data-reveal="1" style="text-align: center; font-size: 14px; color: var(--muted); margin-top: 14px;">Os 15, lado a lado: clique num card ou folheie nas setas. Seu tema fica salvo e te espera no login.</div>
  </section>

  <!-- ============ GAMIFICAÇÃO ============ -->
  <section data-screen-label="gamificação" style="background: var(--surface); border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 108px 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center;">
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 18px;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">CONSTÂNCIA QUE COMPENSA</div>
        <h2 style="color: var(--ink); margin: 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">Pontos por cuidar do seu dinheiro. Sem joguinho.</h2>
        <p style="margin: 0; font-size: 18px; color: var(--muted); line-height: 1.6; max-width: 470px;">Cada transação efetivada vale <b style="color: var(--ink);">+10</b>, fatura paga <b style="color: var(--ink);">+15</b>, meta concluída <b style="color: var(--ink);">+50</b>. Pontos viram níveis, níveis viram badges, e a celebração aparece na conquista, não piscando na sua cara o dia todo.</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px;">
          <span style="display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--line); padding: 9px 16px; border-radius: var(--r-chip); font-size: 13.5px; font-weight: 700;"><span style="width: 9px; height: 9px; background: var(--gold); transform: rotate(45deg);"></span>Nível 7 · Lapidador</span>
          <span style="display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--line); padding: 9px 16px; border-radius: var(--r-chip); font-size: 13.5px; font-weight: 700;"><span style="width: 9px; height: 9px; background: var(--pos); transform: rotate(45deg);"></span>30 dias sem atraso</span>
          <span style="display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--line); padding: 9px 16px; border-radius: var(--r-chip); font-size: 13.5px; font-weight: 700;"><span style="width: 9px; height: 9px; background: var(--accent); transform: rotate(45deg);"></span>1ª meta batida</span>
        </div>
      </div>
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 14px; align-items: center;">
        <div data-card="1" data-float="1" style="width: 380px; background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 22px 26px;">
          <div style="display: flex; justify-content: space-between; align-items: baseline;"><span style="font-size: 13.5px; font-weight: 700;">Seus pontos</span><span style="font-family: var(--f-m); font-size: 26px; font-weight: 700; color: var(--accent);"><span data-count="2350">0</span></span></div>
          <div style="height: 9px; background: var(--line-soft); border-radius: var(--r-chip); margin-top: 12px;"><div data-bar-x="1" style="width: 72%; height: 100%; background: var(--grad); border-radius: var(--r-chip);"></div></div>
          <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-top: 8px;"><span>Nível 7</span><span>650 pts para o nível 8</span></div>
        </div>
        <div style="width: 380px; background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); transform: rotate(-1.5deg); animation: nvxFloatC 8s ease-in-out infinite;">
          <div style="height: 3px; background: var(--prism); display: var(--disp-prism);"></div>
          <div style="padding: 15px 20px; display: flex; align-items: center; gap: 12px;">
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 15px; font-weight: 700; color: var(--stage-ink); background: var(--gold); padding: 6px 12px; border-radius: var(--r-chip);"><span style="width: 8px; height: 8px; background: currentColor; transform: rotate(45deg);"></span>+50</span>
            <span style="font-size: 14px; font-weight: 700; line-height: 1.3;">Meta concluída, parabéns!<br><span style="color: var(--muted); font-weight: 500;">Reserva de emergência · 100%</span></span>
          </div>
        </div>
        <div style="font-size: 12.5px; color: var(--muted);">o toast aparece uma vez, comemora e sai da frente</div>
      </div>
    </div>
  </section>

  <!-- ============ COMPARATIVO ============ -->
  <section data-screen-label="comparativo" style="max-width: 1220px; margin: 0 auto; padding: 112px 32px;">
    <div data-reveal="1" style="max-width: 640px;">
      <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">SEM RODEIO</div>
      <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(34px, 3.6vw, 52px); line-height: 1.08;">NovixFin vs o que você usa hoje.</h2>
    </div>
    <div data-reveal="1" data-card="1" style="margin-top: 38px; background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); overflow: hidden;">
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line); font-size: 13px; font-weight: 800; letter-spacing: .06em;">
        <div style="padding: 16px 22px; color: var(--muted);">RECURSO</div>
        <div style="padding: 16px 18px; background: var(--accent); color: var(--accent-ink); text-align: center;">NOVIXFIN</div>
        <div style="padding: 16px 18px; color: var(--muted); text-align: center;">PLANILHA</div>
        <div style="padding: 16px 18px; color: var(--muted); text-align: center;">APPS GENÉRICOS</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Lançamento em 3 segundos</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">manual e lento</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">3–6 toques</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Previsão de saldo (recorrências + fatura)</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--neg); font-weight: 700;">✗</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">raro</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Pendente vs efetivada</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">na fé da fórmula</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">às vezes</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Kanban de transações</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--neg); font-weight: 700;">✗</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--neg); font-weight: 700;">✗</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Gamificação adulta (pontos, níveis, badges)</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--neg); font-weight: 700;">✗</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">infantilizada</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line-soft); font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">15 temas completos</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">✓ único</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--neg); font-weight: 700;">✗</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">claro/escuro</div>
      </div>
      <div style="display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; font-size: 14px; align-items: center;">
        <div style="padding: 14px 22px; font-weight: 600;">Preço do plano pessoal</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--pos); font-weight: 800;">grátis, pra sempre</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">grátis + seu sábado</div>
        <div style="padding: 14px 18px; text-align: center; color: var(--muted);">freemium travado</div>
      </div>
    </div>
  </section>

  <!-- ============ SEGURANÇA ============ -->
  <section id="seguranca" data-screen-label="segurança" style="background: var(--surface); border-top: 1px solid var(--line-soft); border-bottom: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 108px 32px; display: grid; grid-template-columns: 1.1fr 1fr; gap: 60px; align-items: center;">
      <div data-reveal="1" style="display: flex; flex-direction: column; gap: 18px;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">SEGURANÇA &amp; PRIVACIDADE</div>
        <h2 style="color: var(--ink); margin: 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(32px, 3.2vw, 46px); line-height: 1.1;">Seu dinheiro é assunto seu.</h2>
        <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 4px;">
          <div style="display: flex; gap: 14px; align-items: flex-start;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0; margin-top: 5px;"></span><div style="font-size: 15.5px; line-height: 1.5;"><b>Criptografia de ponta a ponta no armazenamento.</b><br><span style="color: var(--muted); font-size: 14px;">Senhas com hash forte, dados cifrados, sessões protegidas.</span></div></div>
          <div style="display: flex; gap: 14px; align-items: flex-start;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0; margin-top: 5px;"></span><div style="font-size: 15.5px; line-height: 1.5;"><b>Modo privacidade em um toque, em toda tela.</b><br><span style="color: var(--muted); font-size: 14px;">O olhinho esconde todos os valores: pro busão, pra mesa do café, pra tela compartilhada.</span></div></div>
          <div style="display: flex; gap: 14px; align-items: flex-start;"><span style="width: 10px; height: 10px; background: var(--accent); transform: rotate(45deg); flex-shrink: 0; margin-top: 5px;"></span><div style="font-size: 15.5px; line-height: 1.5;"><b>Seus dados entram e saem com você.</b><br><span style="color: var(--muted); font-size: 14px;">Export CSV completo a qualquer momento. Sem anúncio, sem venda de dados. O produto é o app, não você.</span></div></div>
        </div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 26px 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 14px; font-weight: 700;">Experimente o olhinho →</span>
          <button data-act="privacy" style="border: 1px solid var(--line); background: var(--surface); color: var(--ink); font-family: var(--f-b); font-size: 13px; font-weight: 700; padding: 10px 16px; min-height: 44px; border-radius: var(--r-chip); cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><span data-lp="privEye">👁‍🗨</span> <span data-lp="privLabel">ocultar</span></button>
        </div>
        <div style="margin-top: 18px; border-top: 1px solid var(--line-soft); padding-top: 16px;">
          <div style="font-size: 12.5px; color: var(--muted); font-weight: 600;">Saldo total</div>
          <div style="font-family: var(--f-m); font-size: 34px; font-weight: 700; color: var(--accent); margin-top: 2px; font-variant-numeric: tabular-nums;" data-lp="privSaldo">R$ 12.450,80</div>
          <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line-soft); margin-top: 10px;"><span style="width: 9px; height: 9px; background: var(--neg); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">iFood</span><span style="font-family: var(--f-m); font-size: 13.5px; color: var(--neg);" data-lp="privV1">−46,90</span></div>
          <div style="display: flex; align-items: center; gap: 12px; padding: 10px 0;"><span style="width: 9px; height: 9px; background: var(--pos); transform: rotate(45deg);"></span><span style="flex: 1; font-size: 13.5px; font-weight: 600;">Salário ACME</span><span style="font-family: var(--f-m); font-size: 13.5px; color: var(--pos);" data-lp="privV2">+6.500,00</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ NÚMEROS + DEPOIMENTOS ============ -->
  <section data-screen-label="prova social" style="max-width: 1220px; margin: 0 auto; padding: 112px 32px;">
    <div data-reveal="1" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; text-align: center;">
      <div><div style="font-family: var(--f-m); font-size: 44px; font-weight: 700; color: var(--accent);"><span data-count="12400">0</span>+</div><div style="font-size: 13.5px; color: var(--muted); font-weight: 600;">brasileiros no controle</div></div>
      <div style="border-left: 1px solid var(--line-soft);"><div style="font-family: var(--f-m); font-size: 44px; font-weight: 700; color: var(--accent);"><span data-count="100">0</span> mil</div><div style="font-size: 13.5px; color: var(--muted); font-weight: 600;">transações lançadas</div></div>
      <div style="border-left: 1px solid var(--line-soft);"><div style="font-family: var(--f-m); font-size: 44px; font-weight: 700; color: var(--accent);"><span data-count="4.8" data-dec="1">0</span>★</div><div style="font-size: 13.5px; color: var(--muted); font-weight: 600;">nota média</div></div>
      <div style="border-left: 1px solid var(--line-soft);"><div style="font-family: var(--f-m); font-size: 44px; font-weight: 700; color: var(--accent);"><span data-count="35">0</span>%</div><div style="font-size: 13.5px; color: var(--muted); font-weight: 600;">de economia média em 3 meses</div></div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 54px;">
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 26px 28px; display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 15px; line-height: 1.6;">"Eu achava que o problema era ganhar pouco. Era não ver. Em dois meses de NovixFin saí do cheque especial: a previsão me mostrou o buraco 3 semanas antes."</div>
        <div style="display: flex; align-items: center; gap: 12px; margin-top: auto;">
          <span style="width: 40px; height: 40px; border-radius: 50%; background: var(--accent); color: var(--accent-ink); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">M</span>
          <div><div style="font-size: 14px; font-weight: 700;">Marina R.</div><div style="font-size: 12.5px; color: var(--muted);">designer · São Paulo · tema Sereno</div></div>
        </div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 26px 28px; display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 15px; line-height: 1.6;">"Lanço a corrida no semáforo. 3 segundos é real. Fim do dia já sei quanto sobrou. Antes eu só descobria quando o cartão estourava."</div>
        <div style="display: flex; align-items: center; gap: 12px; margin-top: auto;">
          <span style="width: 40px; height: 40px; border-radius: 50%; background: var(--pos); color: var(--surface); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">R</span>
          <div><div style="font-size: 14px; font-weight: 700;">Rafael T.</div><div style="font-size: 12.5px; color: var(--muted);">motorista de app · BH · tema Tinta &amp; Recibo</div></div>
        </div>
      </div>
      <div data-reveal="1" data-card="1" style="background: var(--surface); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 26px 28px; display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 15px; line-height: 1.6;">"A gente batia boca por dinheiro todo mês. Agora é meta em conjunto: Chapada em dezembro, 45% e subindo. O +50 da primeira meta a gente até comemorou."</div>
        <div style="display: flex; align-items: center; gap: 12px; margin-top: auto;">
          <span style="width: 40px; height: 40px; border-radius: 50%; background: var(--gold); color: var(--stage-ink); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">C</span>
          <div><div style="font-size: 14px; font-weight: 700;">Camila &amp; Edu</div><div style="font-size: 12.5px; color: var(--muted);">casal · Curitiba · tema Café Coado</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ FAQ ============ -->
  <section id="faq" data-screen-label="faq" style="background: var(--surface); border-top: 1px solid var(--line-soft);">
    <div style="max-width: 860px; margin: 0 auto; padding: 108px 32px;">
      <div data-reveal="1" style="text-align: center;">
        <div style="font-size: 13px; letter-spacing: .16em; color: var(--accent); font-weight: 700;">PERGUNTAS DIRETAS</div>
        <h2 style="color: var(--ink); margin: 14px 0 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(32px, 3.4vw, 48px); line-height: 1.08;">O que todo mundo pergunta.</h2>
      </div>
      <div data-reveal="1" style="margin-top: 38px; display: flex; flex-direction: column; gap: 10px;">
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">É grátis mesmo? Qual a pegadinha?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">O plano pessoal é gratuito pra sempre: contas, cartões, metas, previsão, os 15 temas: tudo. Sem anúncio e sem venda de dados. Planos pagos futuros serão para recursos além do uso pessoal, nunca tirando o que hoje é seu.</div>
        </details>
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">Preciso conectar meu banco?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">Não. O NovixFin funciona sem nenhuma credencial bancária: você lança em 3 segundos ou importa o CSV que o seu banco exporta. Controle total, risco zero.</div>
        </details>
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">Como funciona a previsão de saldo?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">Cruzamos suas recorrências (salário, aluguel, assinaturas), as faturas previstas dos cartões e o seu padrão de gasto pra projetar o saldo de 4 semanas a 90 dias. Pendente e efetivada são separados: o "vai cair" nunca se disfarça de "caiu".</div>
        </details>
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">Consigo migrar da minha planilha?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">Sim: exporta a planilha como CSV e importa no NovixFin em minutos, com mapeamento de colunas e categorias. E se um dia quiser sair, o export leva tudo de volta.</div>
        </details>
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">Os 15 temas mudam o que no app?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">Cores, tipografia, formas, o desenho dos gráficos: cada tema é uma direção de design completa, não um filtro. O app por dentro é o mesmo; a experiência é sua. Troque quando quiser, fica salvo na sua conta.</div>
        </details>
        <details style="background: var(--bg); border: var(--card-b); box-shadow: var(--card-sh); border-radius: var(--r); padding: 0 22px;">
          <summary style="color: var(--ink); font-size: 16px; font-weight: 700; padding: 18px 0; display: flex; justify-content: space-between; align-items: center; min-height: 44px;">Funciona bem no celular?<span style="color: var(--accent); font-size: 20px;">+</span></summary>
          <div style="font-size: 14.5px; color: var(--muted); line-height: 1.6; padding-bottom: 18px;">O NovixFin é pensado pro uso diário no navegador do celular: lançamento a um toque de qualquer tela, botões generosos, modo privacidade. App nativo está no roadmap.</div>
        </details>
      </div>
    </div>
  </section>

  <!-- ============ CTA FINAL — PALCO ============ -->
  <section data-screen-label="cta final" style="background: var(--stage); position: relative; overflow: hidden; clip-path: polygon(0 46px, 100% 0, 100% 100%, 0 100%);">
    <div aria-hidden="true" style="position: absolute; inset: 0; pointer-events: none; background: var(--stage-tex, none);"></div>
    <div aria-hidden="true" style="position: absolute; inset: 0; pointer-events: none; opacity: .05; mix-blend-mode: overlay; background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22140%22 height=%22140%22%3E%3Cfilter id=%22n%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.85%22 numOctaves=%222%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23n)%22/%3E%3C/svg%3E');"></div>
    <div data-parallax="0.1" style="position: absolute; right: -140px; top: -100px; width: 520px; height: 520px; background: var(--stage-ink); opacity: .06; clip-path: polygon(0 0, 100% 18%, 82% 100%, 12% 84%); transform: rotate(10deg);"></div>
    <div data-parallax="-0.07" style="position: absolute; left: -180px; bottom: -160px; width: 460px; height: 460px; background: var(--stage-ink); opacity: .05; clip-path: polygon(20% 0, 100% 30%, 74% 100%, 0 70%);"></div>
    <div data-reveal="1" style="max-width: 900px; margin: 0 auto; padding: 132px 32px 116px; text-align: center; position: relative; color: var(--stage-ink);">
      <h2 style="margin: 0; font-family: var(--f-d); font-weight: var(--w-d); letter-spacing: var(--ls-d); font-size: clamp(40px, 5vw, 72px); line-height: 1.02; color: var(--stage-ink);"><span data-lp="h1a">Bruto entra. Lapidado </span><span data-lp="h1acc">fica</span><span data-lp="h1post">.</span></h2>
      <p style="margin: 18px auto 0; font-size: 19px; color: var(--stage-muted); line-height: 1.55; max-width: 560px;">Crie a conta agora e lance o primeiro gasto antes do café esfriar. O resto o NovixFin lapida com você.</p>
      <div style="display: flex; gap: 14px; justify-content: center; margin-top: 34px; flex-wrap: wrap;">
        <a href="<?php echo $isAuthenticated ? '/public/dashboard.php' : '/public/register.php'; ?>" style="background: var(--stage-ink); color: var(--stage); font-family: var(--f-d); font-weight: 700; font-size: 18px; padding: 18px 36px; clip-path: var(--cut); border-radius: var(--r-btn); cursor: pointer; transition: transform .16s ease;" data-cta="1" data-hv="lp-hv-2"><?php echo $isAuthenticated ? 'Abrir app' : 'Criar conta grátis'; ?></a>
        <a href="#temas" style="border: 1.5px solid var(--stage-line); color: var(--stage-ink); font-size: 18px; font-weight: 600; padding: 16px 30px; border-radius: var(--r-btn); display: inline-flex; align-items: center;">Escolher meu tema</a>
      </div>
      <div style="display: flex; gap: 22px; justify-content: center; margin-top: 26px; font-size: 14px; color: var(--stage-muted); font-weight: 600; flex-wrap: wrap;">
        <span>✓ grátis pra sempre</span><span>✓ sem cartão de crédito</span><span>✓ sem conectar banco</span><span>✓ seus dados são seus</span>
      </div>
    </div>
  </section>

  <!-- ============ FOOTER ============ -->
  <footer data-screen-label="footer" style="background: var(--bg); border-top: 1px solid var(--line-soft);">
    <div style="max-width: 1220px; margin: 0 auto; padding: 56px 32px 40px; display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 32px;">
      <div>
        <div style="display: flex; align-items: center; gap: 9px;">
          <div style="width: 17px; height: 17px; background: var(--sheen); clip-path: var(--cut); border-radius: var(--r-chip);"></div>
          <span style="font-family: var(--f-d); font-size: 21px; font-weight: var(--w-d);">novixfin</span>
        </div>
        <div style="font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-top: 12px; max-width: 280px;">Finanças pessoais que dão vontade de abrir todo dia. Feito no Brasil, pro jeito brasileiro de lidar com dinheiro.</div>
        <div style="font-size: 12.5px; color: var(--muted); margin-top: 16px;">tema ativo: <b style="color: var(--ink);" data-lp="themeName">Ametista</b></div>
      </div>
      <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
        <div style="font-size: 12px; letter-spacing: .12em; color: var(--muted); font-weight: 700; margin-bottom: 4px;">PRODUTO</div>
        <a href="#previsao" style="color: var(--muted);">Previsão de saldo</a>
        <a href="#demo" style="color: var(--muted);">Lançamento rápido</a>
        <a href="#temas" style="color: var(--muted);">Os 15 temas</a>
        <a href="#seguranca" style="color: var(--muted);">Segurança</a>
      </div>
      <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
        <div style="font-size: 12px; letter-spacing: .12em; color: var(--muted); font-weight: 700; margin-bottom: 4px;">RECURSOS</div>
        <span style="color: var(--muted); cursor: pointer;">Cartões e faturas</span>
        <span style="color: var(--muted); cursor: pointer;">Metas de economia</span>
        <span style="color: var(--muted); cursor: pointer;">Kanban</span>
        <span style="color: var(--muted); cursor: pointer;">Import / Export CSV</span>
      </div>
      <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
        <div style="font-size: 12px; letter-spacing: .12em; color: var(--muted); font-weight: 700; margin-bottom: 4px;">NOVIXFIN</div>
        <span style="color: var(--muted); cursor: pointer;">Sobre</span>
        <span style="color: var(--muted); cursor: pointer;">Privacidade</span>
        <span style="color: var(--muted); cursor: pointer;">Termos</span>
        <span style="color: var(--muted); cursor: pointer;">Contato</span>
      </div>
    </div>
    <div style="max-width: 1220px; margin: 0 auto; padding: 0 32px 32px; display: flex; justify-content: space-between; font-size: 12.5px; color: var(--muted); border-top: 1px solid var(--line-soft); padding-top: 20px;">
      <span>© 2026 NovixFin. Todos os direitos reservados.</span>
      <span style="display: inline-flex; align-items: center; gap: 12px;">feito com constância, como as suas metas <span style="font-family: var(--f-m); font-size: 10.5px; letter-spacing: .14em; border: 1px solid var(--line-soft); padding: 3px 9px; border-radius: var(--r-chip); color: var(--muted);">build F16</span></span>
    </div>
  </footer>
</div>

    <script src="/public/scripts/landing.js"></script>
</body>
</html>
