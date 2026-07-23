<?php

declare(strict_types=1);

/**
 * CSS above-the-fold — inline în <head>.
 * Mobile-first, aliniat 1:1 cu home-critical.css ca să nu existe CLS când CSS-ul full sosește.
 */
function besoiu_home_inline_critical_css(): string
{
    return <<<'CSS'
:root{--green:#047857;--green-dark:#065f46;--line:#e2e8f0;--muted:#64748b;--text:#0f172a;--shadow-soft:0 10px 25px rgba(15,23,42,.06)}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--text);background:#fff;line-height:1.45}
img,svg{display:block;max-width:100%}
.visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.page{min-height:100vh;width:100%;overflow:hidden}
.container{width:min(1460px,calc(100% - 24px));margin:0 auto}
.topbar{min-height:40px;height:38px;border-bottom:1px solid var(--line);background:#f9fbfd}
.header{min-height:72px;height:72px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:30}
.header-inner{height:100%;display:flex;align-items:center;gap:12px}
.logo-img,.logo picture{width:125px;height:42px;object-fit:contain;display:block;flex-shrink:0}
.search-main{display:none}
.filterbar{padding:14px 0 18px;background:#fff}
.home-filter-panel{padding:14px 14px 16px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow-soft)}
.filterbar .container,.filterbar .home-filter-panel{display:grid;grid-template-columns:1fr;gap:14px;align-items:start}
.cat-btn{width:100%;height:54px;border:0;border-radius:999px;background:var(--green);color:#fff;font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;padding:0 20px;gap:12px}
.vehicle-box{display:grid;grid-template-columns:1fr;min-height:280px;height:auto;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#fff;box-shadow:var(--shadow-soft)}
.vehicle-item{min-height:64px;height:auto;padding:12px 16px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--line);border-right:0}
.vehicle-search{width:calc(100% - 24px);margin:12px;height:52px;border:0;border-radius:999px;background:var(--green);color:#fff;font-weight:900}
.hero{position:relative;padding:28px 0 36px;min-height:640px;contain:layout style paint;content-visibility:auto;background:#fff}
.hero .container{display:grid;grid-template-columns:1fr;min-height:560px;gap:8px;align-items:start}
.eyebrow{font-size:14px;font-weight:900;color:var(--green);text-transform:uppercase;letter-spacing:1px;margin:0 0 8px;min-height:1.2em}
.hero h1{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;font-size:clamp(30px,8vw,34px);line-height:1.08;letter-spacing:-1px;margin:0 0 16px;font-weight:900;min-height:2.2em}
.hero h1 span{color:var(--green)}
.hero p{font-size:15px;line-height:1.65;margin:0 0 20px;min-height:3.2em;color:#334155;max-width:none}
.quick-check{width:100%;height:auto;border:1px solid var(--line);border-radius:12px;background:#fff;display:grid;grid-template-columns:1fr;overflow:hidden;box-shadow:var(--shadow-soft);margin-bottom:22px}
.quick-check input{border:0;outline:0;height:50px;padding:0 16px;font-size:15px}
.quick-check button{border:0;background:var(--green);color:#fff;font-weight:900;font-size:14px;margin:0;border-radius:0 0 10px 10px;height:48px;cursor:pointer}
.hero-benefits{display:grid;grid-template-columns:repeat(2,1fr);gap:0;max-width:100%;margin-top:22px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff;box-shadow:var(--shadow-soft)}
.hero-benefits div{min-height:74px;padding:14px 10px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;border-right:1px solid var(--line);border-bottom:1px solid var(--line)}
.hero-benefits div:nth-child(2n){border-right:0}
.hero-benefits div:nth-last-child(-n+2){border-bottom:0}
.hero-benefits .bico img,.hero-benefits .bico .ui-icon-28{width:36px!important;height:36px!important}
.hero-benefits b{display:block;font-size:12px;line-height:1.35}
.hero-art--promo-only{min-height:380px;height:auto;margin-top:12px;display:flex;align-items:stretch}
.hero-promo-banner{min-height:360px;display:flex;flex-direction:column;position:relative;border:1px solid #e2e8f0;border-radius:18px;background:#fff;overflow:hidden}
.hero-promo-track{position:relative;min-height:360px;flex:1}
.hero-promo-slide{display:none}
.hero-promo-slide.is-active{display:grid;grid-template-columns:1fr}
.hero-promo-slide-media{min-height:170px;display:flex;align-items:center;justify-content:center;padding:16px;border-bottom:1px solid #f1f5f9}
.hero-promo-slide-media img,.hero-promo-slide-media picture img{width:auto;height:auto;max-height:150px;max-width:100%;aspect-ratio:1/1;object-fit:contain}
/* CMS utility classes pe live nu trebuie să schimbe geometria above-the-fold */
.hero .bpa-pad-sm,.hero .bpa-pad-md,.hero .bpa-pad-lg,.hero .bpa-pad-xl,
.filterbar .bpa-pad-sm,.filterbar .bpa-pad-md,.filterbar .bpa-pad-lg,
.header .bpa-pad-sm,.header .bpa-pad-md,.topbar .bpa-pad-md{padding:0!important;margin:0}
.hero .bpa-mw-container,.filterbar .bpa-mw-container{max-width:none}
@media(min-width:769px){
.container{width:min(1460px,calc(100% - 72px))}
.header{height:92px;min-height:92px}
.search-main{display:grid;height:58px;border:1px solid var(--line);border-radius:999px;grid-template-columns:1fr 160px;flex:1;min-width:0}
.eyebrow{font-size:20px;letter-spacing:0}
.hero h1{font-size:40px}
.hero p{font-size:16px}
.quick-check{width:min(610px,100%);height:64px;grid-template-columns:1fr 250px;border-radius:10px}
.quick-check input{height:100%;padding:0 22px}
.quick-check button{margin:8px;border-radius:8px;height:auto}
.hero-benefits{max-width:620px;border:0;border-radius:0;box-shadow:none;background:transparent}
.hero-benefits div{min-height:82px;padding:18px 12px;border-bottom:0}
.hero-benefits .bico img,.hero-benefits .bico .ui-icon-28{width:48px!important;height:48px!important}
.hero-benefits b{font-size:13px}
.hero-promo-banner{border-radius:24px}
.filterbar{padding:18px 0 22px}
.home-filter-panel{padding:18px 20px 22px;border-radius:18px}
.cat-btn{height:60px;font-size:15px;justify-content:flex-start;padding:0 28px}
}
@media(min-width:1101px){
.hero{padding:38px 0 54px;min-height:0}
.hero .container{grid-template-columns:48% 52%;min-height:510px;align-items:center}
.hero h1{font-size:64px;letter-spacing:-2px;margin-bottom:22px}
.hero p{font-size:18px;max-width:560px;margin-bottom:28px}
.filterbar{padding:24px 0 30px}
.filterbar .container,.filterbar .home-filter-panel{grid-template-columns:260px minmax(0,1fr);gap:34px}
.vehicle-box{grid-template-columns:repeat(3,1fr) 190px;min-height:72px;border-radius:999px}
.vehicle-item{border-right:1px solid var(--line);border-bottom:0;min-height:72px;height:100%;padding:0 24px}
.vehicle-search{width:auto;margin:0 12px;height:48px}
.hero-benefits{grid-template-columns:repeat(4,1fr)}
.hero-art--promo-only{min-height:0;margin-top:0}
.hero-promo-slide.is-active{grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr)}
.hero-promo-slide-media{min-height:280px;border-bottom:0;border-right:1px solid #f1f5f9}
.hero-promo-slide-media img,.hero-promo-slide-media picture img{max-height:min(300px,36vh)}
}
CSS;
}

function besoiu_render_home_inline_critical(): void
{
    echo '<style id="besoiu-home-inline-critical">' . besoiu_home_inline_critical_css() . '</style>' . "\n";
}

/** CSS above-the-fold generic (topbar+header+shell) — pentru pagini non-home. */
function besoiu_shell_inline_critical_css(): string
{
    return <<<'CSS'
:root{--green:#047857;--line:#e2e8f0;--muted:#64748b;--text:#0f172a;--shadow-soft:0 10px 25px rgba(15,23,42,.06)}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--text);background:#fff;line-height:1.45}
img,svg{display:block;max-width:100%}
.visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.page{min-height:100vh;width:100%;overflow:hidden}
.container{width:min(1460px,calc(100% - 72px));margin:0 auto}
.topbar{min-height:40px;height:38px;border-bottom:1px solid var(--line);background:#f9fbfd}
.header{min-height:72px;height:92px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:30}
.logo-img,.logo picture{width:125px;height:42px;object-fit:contain;display:block}
CSS;
}

function besoiu_render_shell_inline_critical(): void
{
    echo '<style id="besoiu-shell-inline-critical">' . besoiu_shell_inline_critical_css() . '</style>' . "\n";
}
