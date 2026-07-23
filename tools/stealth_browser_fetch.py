#!/usr/bin/env python3
"""
Fetch HTML via stealth-browser-mcp (BrowserManager + nodriver) — motor scraper Besoiu.

Utilizare:
  stealth-browser-mcp/venv/Scripts/python.exe tools/stealth_browser_fetch.py "https://www.autodoc24.ro/..."
  stealth-browser-mcp/venv/Scripts/python.exe tools/stealth_browser_fetch.py --url "..." --json --html-file out.html

Profil Autodoc/Ovoko: sesiune curată (ca incognito) — profilul vechi shared era marcat turnstileBotCheck.
Salvare cookies doar după fetch reușit în storage/scraper/stealth_profile_autodoc|ovoko/.

Ieșire JSON: { success, url, final_url, title, html_length, html, duration_ms, engine, error, cloudflare_challenge }
"""
from __future__ import annotations

import argparse
import asyncio
import gc
import json
import os
import shutil
import sys
import time
import uuid

TOOLS_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(TOOLS_DIR)
MCP_SRC = os.path.join(TOOLS_DIR, "stealth-browser-mcp", "src")
PROFILE_DIR = os.path.join(PROJECT_ROOT, "storage", "scraper", "stealth_profile")
AUTODOC_PROFILE_DIR = os.path.join(PROJECT_ROOT, "storage", "scraper", "stealth_profile_autodoc")
OVOKO_PROFILE_DIR = os.path.join(PROJECT_ROOT, "storage", "scraper", "stealth_profile_ovoko")
EPHEMERAL_ROOT = os.path.join(PROJECT_ROOT, "storage", "scraper", "stealth_ephemeral")
AUTODOC_BURNED_FLAG = os.path.join(AUTODOC_PROFILE_DIR, ".cf_burned")
OVOKO_BURNED_FLAG = os.path.join(OVOKO_PROFILE_DIR, ".cf_burned")

if MCP_SRC not in sys.path:
    sys.path.insert(0, MCP_SRC)


def project_profile_dir() -> str:
    os.makedirs(PROFILE_DIR, exist_ok=True)
    return PROFILE_DIR


def is_autodoc_url(url: str) -> bool:
    return "autodoc" in (url or "").lower()


def is_ovoko_url(url: str) -> bool:
    return "ovoko." in (url or "").lower()


def cf_site_key(url: str) -> str:
    """Site-uri care necesită profil dedicat + Turnstile pe ecran."""
    if is_autodoc_url(url):
        return "autodoc"
    if is_ovoko_url(url):
        return "ovoko"
    return ""


def cf_profile_dir(site: str) -> str:
    if site == "ovoko":
        return OVOKO_PROFILE_DIR
    return AUTODOC_PROFILE_DIR


def cf_burned_flag(site: str) -> str:
    if site == "ovoko":
        return OVOKO_BURNED_FLAG
    return AUTODOC_BURNED_FLAG


def cf_use_fresh_session(site: str) -> bool:
    """Profil curat (incognito-like) — evită turnstileBotCheck pe profil ars."""
    raw = (os.environ.get("STEALTH_BROWSER_FRESH_PROFILE") or "").strip().lower()
    if raw in ("1", "true", "yes", "on"):
        return True
    if raw in ("0", "false", "no", "off"):
        return False
    profile = cf_profile_dir(site)
    if not os.path.isdir(profile):
        return True
    if os.path.isfile(cf_burned_flag(site)):
        return True
    return False


def mark_cf_profile_burned(site: str) -> None:
    profile = cf_profile_dir(site)
    flag = cf_burned_flag(site)
    os.makedirs(profile, exist_ok=True)
    try:
        with open(flag, "w", encoding="utf-8") as fh:
            fh.write(time.strftime("%Y-%m-%dT%H:%M:%S"))
    except OSError:
        pass


def clear_cf_profile_burned(site: str) -> None:
    flag = cf_burned_flag(site)
    if os.path.isfile(flag):
        try:
            os.remove(flag)
        except OSError:
            pass


def wipe_cf_saved_profile(site: str) -> None:
    profile = cf_profile_dir(site)
    if os.path.isdir(profile):
        try:
            shutil.rmtree(profile, ignore_errors=True)
        except OSError:
            pass


def autodoc_use_fresh_session() -> bool:
    return cf_use_fresh_session("autodoc")


def mark_autodoc_profile_burned() -> None:
    mark_cf_profile_burned("autodoc")


def clear_autodoc_profile_burned() -> None:
    clear_cf_profile_burned("autodoc")


def wipe_autodoc_saved_profile() -> None:
    wipe_cf_saved_profile("autodoc")


def resolve_user_data_dir(url: str) -> tuple[str, bool, str]:
    """
    Returnează (path, is_ephemeral, site_key).
    site_key: '' | 'autodoc' | 'ovoko'
    """
    site = cf_site_key(url)
    if not site:
        return project_profile_dir(), False, ""

    if cf_use_fresh_session(site):
        os.makedirs(EPHEMERAL_ROOT, exist_ok=True)
        session_dir = os.path.join(EPHEMERAL_ROOT, f"{site}_{uuid.uuid4().hex[:12]}")
        os.makedirs(session_dir, exist_ok=True)
        return session_dir, True, site

    profile = cf_profile_dir(site)
    os.makedirs(profile, exist_ok=True)
    sanitize_chrome_profile(profile)
    return profile, False, site


def persist_cf_profile(site: str, ephemeral_dir: str) -> None:
    """După Turnstile reușit — salvează cookies pentru rulări următoare."""
    if not site or not ephemeral_dir or not os.path.isdir(ephemeral_dir):
        return
    wipe_cf_saved_profile(site)
    try:
        shutil.copytree(ephemeral_dir, cf_profile_dir(site))
        clear_cf_profile_burned(site)
    except OSError:
        pass


def persist_autodoc_profile(ephemeral_dir: str) -> None:
    persist_cf_profile("autodoc", ephemeral_dir)


def cleanup_ephemeral_dir(path: str) -> None:
    if path and os.path.isdir(path) and EPHEMERAL_ROOT in os.path.abspath(path):
        try:
            shutil.rmtree(path, ignore_errors=True)
        except OSError:
            pass


def autodoc_launch_args(base_args: list[str], *, fresh_session: bool) -> list[str]:
    """Sesiune nouă — profil gol (echivalent incognito pentru CF)."""
    return list(base_args)


def has_real_listing_content(html: str) -> bool:
    blob = (html or "").lower()
    return any(
        token in blob
        for token in (
            "listing-item__wrap",
            "lp-card",
            "prod-card",
            "pc-name",
            "prod-grid",
            "srch-head",
            "rezultate cautare",
            "data-product-id",
            "product-tile",
            # Allegro listing
            "/oferta/",
            "aktualna cena",
            # Ovoko
            "item-card-horizontal-container",
            "/piesa-uzata/",
            'data-testid="part-image"',
            'data-testid="formatted-price"',
        )
    )


def is_cloudflare_challenge(html: str, title: str = "") -> bool:
    blob = ((html or "") + " " + (title or "")).lower()
    if not blob.strip():
        return False
    if (title or "").strip().lower() == "turnstilebotcheck":
        return True
    if has_real_listing_content(html):
        return False
    needles = (
        "just a moment",
        "один момент",
        "un moment",
        "cf-challenge",
        "challenges.cloudflare.com",
        "challenge-platform",
        "turnstile",
        "turnstilebotcheck",
        "enable javascript and cookies",
        "checking your browser",
        "ray id",
        "_cf_chl",
        "cf_chl_opt",
        "challenge-error-text",
        "выполнение проверки",
        "cdn-cgi/challenge-platform",
        # Allegro DataDome
        "captcha-delivery.com",
        "geo.captcha-delivery.com",
        "datadome",
        'dd={',
        "ct.captcha-delivery.com",
    )
    return any(n in blob for n in needles)


def page_ready(html: str, title: str, marker: str) -> bool:
    if not html or is_cloudflare_challenge(html, title):
        return False
    if is_cookie_consent_blocking(html):
        return False
    if marker and marker.lower() in html.lower():
        return True
    if has_real_listing_content(html):
        return True
    # Fără marker explicit: nu considera pagina gata doar după dimensiune (CF poate avea 25–30 KB).
    return False


def is_cookie_consent_blocking(html: str) -> bool:
    """Autodoc / CMP — popup cookie fără conținut listă produse."""
    if not html or has_real_listing_content(html):
        return False
    blob = html.lower()
    markers = (
        "data-terms-cookies-popup",
        "terms-cookies-popup",
        "notification-popup__reject",
        "allow_all_cookies",
        "disallow_all_cookies",
        "utilizarea modulelor cookie",
    )
    return any(m in blob for m in markers)


AUTODOC_CHAT_DISMISS_JS = """
(() => {
  document.querySelectorAll('[data-chat-tooltip-close], .chat-new__tooltip-close').forEach(el => {
    try { el.click(); } catch (e) {}
  });
  document.querySelectorAll('.chat-new__tooltip').forEach(el => { el.style.display = 'none'; });
  return true;
})();
"""

AUTODOC_COOKIE_DISMISS_JS = """
(() => {
  const click = (sel) => {
    const el = document.querySelector(sel);
    if (!el) return false;
    try { el.click(); } catch (e) { el.dispatchEvent(new MouseEvent('click', { bubbles: true })); }
    return true;
  };
  const order = [
    'button[data-cookies="disallow_all_cookies"]',
    '.notification-popup__reject[data-cookies="disallow_all_cookies"]',
    '.notification-popup__reject',
    'button[data-cookies="allow_all_cookies"]',
    '[data-popup-close][data-cookies="allow_all_cookies"]',
    'button[data-cookies="selected_cookies"]',
  ];
  for (const sel of order) {
    if (click(sel)) return 'clicked:' + sel;
  }
  document.querySelectorAll(
    '[data-terms-cookies-popup], [data-terms-cookies-popup-common], .notification-popup'
  ).forEach((node) => node.remove());
  if (document.body) {
    document.body.classList.remove('modal-open', 'no-scroll', 'overflow-hidden');
    document.body.style.overflow = '';
  }
  document.documentElement.style.overflow = '';
  return 'removed';
})();
"""

EPIESA_COOKIE_DISMISS_JS = """
(() => {
  try {
    if (typeof cookieconsent !== 'undefined' && typeof cookieconsent.acceptAll === 'function') {
      cookieconsent.acceptAll();
      return 'cookieconsent.acceptAll';
    }
  } catch (e) {}
  const agree = document.querySelector('.cc-nb-okagree, button.cc-nb-okagree, .cc-nb-buttons button');
  if (agree) { agree.click(); return 'epiesa-cc-click'; }
  return 'none';
})();
"""

COOKIE_DISMISS_JS = (
    EPIESA_COOKIE_DISMISS_JS,
    AUTODOC_CHAT_DISMISS_JS,
    AUTODOC_COOKIE_DISMISS_JS,
    "(() => { const b = document.querySelector('.cc-nb-okagree, .cc-nb-buttons button.cc-nb-okagree, "
    "#accept-cookie, button[data-cc-action=accept], .cookie-accept, [data-testid=accept-cookies]'); "
    "if (b) b.click(); })();",
    "(() => { const nodes = [...document.querySelectorAll('button,a')]; "
    "const hit = nodes.find(n => /accept|sunt de acord|de acord|accept all|permite/i.test((n.textContent||'').trim())); "
    "if (hit) hit.click(); })();",
    "(() => { const hit = [...document.querySelectorAll('button')].find(n => "
    "/resping|reject all|refuz/i.test((n.textContent||'').trim())); if (hit) hit.click(); })();",
)


async def dismiss_cookie_banners(tab, aggressive: bool = False) -> None:
    rounds = 3 if aggressive else 1
    for _ in range(rounds):
        for js in COOKIE_DISMISS_JS:
            try:
                await tab.evaluate(js)
            except Exception:
                pass
        if aggressive:
            await tab.sleep(0.35)


async def probe_page_state(tab, marker: str) -> dict:
    """Verificare ușoară — fără get_content() (evită zeci de copii HTML în RAM)."""
    marker = (marker or "").strip()
    sel = ""
    if marker == "listing-item__wrap":
        sel = ".listing-item__wrap, .listing-item"
    elif marker == "prod-card":
        sel = ".prod-card, [class*='prod-card']"
    elif marker == "lp-card":
        sel = "article.lp-card, .lp-card, [class*='lp-card']"
    elif marker == "card-v2":
        sel = ".card-v2"
    js = (
        "(() => {"
        f"const marker = {json.dumps(marker)};"
        f"const sel = {json.dumps(sel)};"
        "const t = document.title || '';"
        "let hasMarker = false;"
        "if (sel) { try { hasMarker = !!document.querySelector(sel); } catch (e) {} }"
        "if (!hasMarker && marker) {"
        "  const root = document.documentElement;"
        "  hasMarker = !!(root && root.outerHTML && root.outerHTML.toLowerCase().includes(marker.toLowerCase()));"
        "}"
        "const listing = !!document.querySelector('.listing-item__wrap, .listing-item, .lp-card, .prod-card, .card-v2');"
        "const cookie = !!document.querySelector('[data-terms-cookies-popup], .notification-popup__reject');"
        "const cf = /just a moment|checking your browser|turnstile|turnstilebotcheck|cf-challenge/i.test(t + ' ' + (document.body ? document.body.innerText.slice(0, 400) : ''))"
        "  || !!document.querySelector('#challenge-running, .cf-turnstile, [id^=\"cf-\"]');"
        "return JSON.stringify({title: t, hasMarker, listing, cookie, cf});"
        "})()"
    )
    try:
        raw = await tab.evaluate(js)
        if isinstance(raw, dict):
            return raw
        if isinstance(raw, str) and raw.strip():
            parsed = json.loads(raw)
            if isinstance(parsed, dict):
                return parsed
    except Exception:
        pass
    return {"title": "", "hasMarker": False, "listing": False, "cookie": False, "cf": False}


async def fetch_tab_html_once(tab) -> tuple[str, str]:
    try:
        html = await tab.get_content() or ""
        title = str(await tab.evaluate("document.title") or "")
        return html, title
    except Exception:
        return "", ""


async def poll_until_ready(tab, deadline: float, marker: str, wait_poll: float) -> tuple[str, str]:
    title = ""
    cf_seen = False
    cookie_rounds = 0
    ready = False
    cf_extensions = 0
    while time.monotonic() < deadline:
        sleep_for = wait_poll * (2.5 if cf_seen else 1.0)
        await dismiss_cookie_banners(tab, aggressive=cookie_rounds < 6)
        cookie_rounds += 1
        await tab.sleep(sleep_for)

        state = await probe_page_state(tab, marker)
        title = str(state.get("title") or title or "")

        if state.get("cf"):
            cf_seen = True
            if cf_extensions < 3:
                deadline += 25.0
                cf_extensions += 1
            await dismiss_cookie_banners(tab, aggressive=True)
            continue

        if state.get("cookie"):
            await dismiss_cookie_banners(tab, aggressive=True)
            await tab.sleep(0.8)
            continue

        has_marker = bool(state.get("hasMarker"))
        has_listing = bool(state.get("listing"))
        if marker and (has_marker or has_listing):
            ready = True
            break
        if not marker and has_listing:
            ready = True
            break

    if not ready:
        try:
            await tab.evaluate(
                "window.scrollTo(0, Math.min(1200, document.body ? document.body.scrollHeight : 1200));"
            )
        except Exception:
            pass
        await tab.sleep(max(1.5, wait_poll))
        state = await probe_page_state(tab, marker)
        if state.get("hasMarker") or state.get("listing"):
            ready = True
            title = str(state.get("title") or title or "")

    html, final_title = await fetch_tab_html_once(tab)
    if final_title:
        title = final_title
    return html, title


def resolve_headless(host: str, arg_headless: bool | None) -> bool:
    if arg_headless is not None:
        return bool(arg_headless)
    env_h = (os.environ.get("STEALTH_BROWSER_HEADLESS") or "").strip().lower()
    if env_h in ("0", "false", "no"):
        return False
    if env_h in ("1", "true", "yes"):
        return True
    # Autodoc: --headless=new nu încarcă listing-item (CF/lazy JS); off-screen vizibil OK.
    if "autodoc" in (host or "").lower():
        return False
    # Alte site-uri: implicit headless.
    return True


def sanitize_chrome_profile(profile: str) -> None:
    """După kill forțat — evită popup «Restore pages» care blochează automatizarea."""
    for rel in (
        "Last Session",
        "Last Tabs",
        "Last Session DevTools",
        "Default/Last Session",
        "Default/Last Tabs",
        "Default/Current Session",
        "Default/Current Tabs",
        "SingletonLock",
        "SingletonCookie",
        "SingletonSocket",
        "DevToolsActivePort",
    ):
        path = os.path.join(profile, rel.replace("/", os.sep))
        if os.path.isfile(path):
            try:
                os.remove(path)
            except OSError:
                pass

    prefs_path = os.path.join(profile, "Default", "Preferences")
    if not os.path.isfile(prefs_path):
        return
    try:
        with open(prefs_path, "r", encoding="utf-8", errors="replace") as fh:
            prefs = json.load(fh)
        if not isinstance(prefs, dict):
            return
        profile_node = prefs.get("profile")
        if isinstance(profile_node, dict):
            profile_node["exit_type"] = "Normal"
            profile_node["exited_cleanly"] = True
        session = prefs.get("session")
        if isinstance(session, dict):
            session["restore_on_startup"] = 4
        with open(prefs_path, "w", encoding="utf-8") as fh:
            json.dump(prefs, fh, ensure_ascii=False)
    except Exception:
        pass


def chrome_stability_args() -> list[str]:
    """Evită popup «Restore pages» / crash bubble care blochează automatizarea."""
    return [
        "--disable-session-crashed-bubble",
        "--hide-crash-restore-bubble",
        "--disable-restore-session-state",
        "--disable-infobars",
        "--noerrdialogs",
        "--no-first-run",
        "--no-default-browser-check",
    ]


def headless_launch_args(headless: bool, *, on_screen: bool = False) -> list[str]:
    """Chrome invizibil off-screen; on_screen=True pentru rezolvare manuală Cloudflare Turnstile."""
    stable = chrome_stability_args()
    if not headless:
        if on_screen:
            return stable + [
                "--window-position=80,60",
                "--window-size=1366,900",
            ]
        return stable + [
            "--window-position=-32000,-32000",
            "--window-size=1280,800",
        ]
    return stable + [
        "--headless=new",
        "--disable-gpu",
        "--window-position=-32000,-32000",
    ]


def strip_windows_bad_chrome_flags(args: list[str]) -> list[str]:
    """Elimină flag-uri care declanșează warning + detecție bot pe Windows."""
    if os.name != "nt":
        return args
    banned = {"--no-sandbox", "--disable-setuid-sandbox", "--single-process"}
    return [a for a in args if a not in banned]


def load_browser_profile() -> dict:
    """Profil per sursă din PHP (UA, viewport, Accept-Language)."""
    raw = (os.environ.get("STEALTH_BROWSER_PROFILE") or "").strip()
    if not raw:
        return {}
    try:
        data = json.loads(raw)
        return data if isinstance(data, dict) else {}
    except Exception:
        return {}


def apply_browser_profile(options_kwargs: dict, profile: dict) -> dict:
    """Aplică amprentă browser — UA/viewport/headers diferite per scraper."""
    if not profile:
        return options_kwargs

    ua = str(profile.get("user_agent") or "").strip()
    if ua:
        options_kwargs["user_agent"] = ua

    vw = int(profile.get("viewport_width") or 0)
    vh = int(profile.get("viewport_height") or 0)
    if vw >= 1024:
        options_kwargs["viewport_width"] = vw
    if vh >= 600:
        options_kwargs["viewport_height"] = vh

    accept_lang = str(profile.get("accept_language") or "").strip()
    if accept_lang:
        headers = dict(options_kwargs.get("extra_headers") or {})
        headers["Accept-Language"] = accept_lang
        options_kwargs["extra_headers"] = headers

    return options_kwargs


async def fetch_via_mcp(
    url: str,
    wait_sec: float,
    timeout_sec: float,
    marker: str,
    headless: bool,
    *,
    on_screen: bool = False,
) -> dict:
    from browser_manager import BrowserManager
    from models import BrowserOptions

    started = time.monotonic()
    bm = BrowserManager()
    profile, ephemeral, site_key = resolve_user_data_dir(url)
    is_autodoc = site_key == "autodoc"
    if not ephemeral:
        sanitize_chrome_profile(profile)
    browser_args = strip_windows_bad_chrome_flags(
        headless_launch_args(headless, on_screen=on_screen)
    )
    if site_key:
        browser_args = autodoc_launch_args(browser_args, fresh_session=ephemeral)
    browser_profile = load_browser_profile()
    options_data = apply_browser_profile(
        {
            "headless": headless,
            "user_data_dir": profile,
            "sandbox": True,
            "viewport_width": 1366,
            "viewport_height": 768,
            "timezone_id": "Europe/Bucharest",
            "idle_timeout_seconds": 0,
            "browser_args": browser_args,
            "extra_headers": {},
        },
        browser_profile,
    )
    options = BrowserOptions(**options_data)
    instance = await bm.spawn_browser(options)
    instance_id = instance.instance_id
    html = ""
    title = ""
    final_url = url

    try:
        nav_ms = int(max(30.0, timeout_sec) * 1000)
        await bm.navigate(
            instance_id,
            url,
            wait_until="domcontentloaded",
            timeout=nav_ms,
        )
        tab = await bm.get_navigation_tab(instance_id)
        if tab is None:
            raise RuntimeError("Tab principal indisponibil după navigate")

        await tab.sleep(2.0)
        await dismiss_cookie_banners(tab, aggressive=True)
        await tab.sleep(0.8)
        await dismiss_cookie_banners(tab, aggressive=True)

        deadline = time.monotonic() + max(15.0, timeout_sec)
        poll = max(1.5, min(4.0, wait_sec / 3))
        html, title = await poll_until_ready(tab, deadline, marker, poll)

        try:
            final_url = str(await tab.evaluate("window.location.href") or url)
        except Exception:
            final_url = url

        cf = is_cloudflare_challenge(html, title)
        ok = page_ready(html, title, marker) and not cf

        # Autodoc pe ecran: așteaptă rezolvare manuală Turnstile (click utilizator).
        if not ok and cf and on_screen:
            cf_wait = max(0.0, float(os.environ.get("STEALTH_BROWSER_CF_WAIT", "120") or "120"))
            cf_deadline = time.monotonic() + min(cf_wait, 180.0)
            while time.monotonic() < cf_deadline:
                await tab.sleep(3.0)
                await dismiss_cookie_banners(tab, aggressive=True)
                state = await probe_page_state(tab, marker)
                if state.get("hasMarker") or state.get("listing"):
                    html, title = await fetch_tab_html_once(tab)
                    cf = is_cloudflare_challenge(html, title)
                    ok = page_ready(html, title, marker) and not cf
                    if ok:
                        break
                if not state.get("cf"):
                    html, title = await fetch_tab_html_once(tab)
                    cf = is_cloudflare_challenge(html, title)
                    ok = page_ready(html, title, marker) and not cf
                    if ok:
                        break

        err = ""
        if cf:
            blob = (html or "").lower()
            if site_key and ("turnstilebotcheck" in blob or title.strip().lower() == "turnstilebotcheck"):
                mark_cf_profile_burned(site_key)
                if not ephemeral:
                    wipe_cf_saved_profile(site_key)
            if "turnstilebotcheck" in blob or "turnstile" in blob:
                bootstrap = (
                    "php prd/debug/bootstrap_ovoko_turnstile.php"
                    if site_key == "ovoko"
                    else "php admin/tools/bootstrap_autodoc_turnstile.php VAT2318HP"
                )
                err = (
                    "Cloudflare Turnstile (turnstileBotCheck) — profil Chrome ars. "
                    "Se deschide sesiune curată (ca incognito). Click manual pe bifa (max 2 min). "
                    f"După succes: {bootstrap}"
                )
            else:
                err = (
                    "Cloudflare — așteptare expirată. "
                    "Reîncearcă testul; cookie-urile se salvează în storage/scraper/stealth_profile/."
                )
        elif not ok:
            if is_cookie_consent_blocking(html):
                err = (
                    "Banner cookie Autodoc încă vizibil — reîncerc cu profil "
                    "storage/scraper/stealth_profile (accept/respinge cookie)."
                )
            else:
                err = f"Pagina nu conține markerul «{marker}» în {int(timeout_sec)}s"

        keep_open = float(os.environ.get("STEALTH_BROWSER_KEEP_OPEN", "0") or "0")
        if not ok and not headless and keep_open > 0:
            await asyncio.sleep(min(keep_open, 120.0))

        if ok and site_key and ephemeral:
            persist_cf_profile(site_key, profile)

        result_payload = {
            "success": ok,
            "url": url,
            "final_url": str(final_url or url),
            "title": str(title or ""),
            "html_length": len(html or ""),
            "html": html or "",
            "duration_ms": int((time.monotonic() - started) * 1000),
            "cloudflare_challenge": cf,
            "engine": "stealth_browser_mcp",
            "profile_dir": profile,
            "profile_ephemeral": ephemeral,
            "browser_profile": {
                "source": str(browser_profile.get("source") or ""),
                "label": str(browser_profile.get("label") or ""),
                "viewport": f"{options_data.get('viewport_width', 0)}x{options_data.get('viewport_height', 0)}",
            },
            "error": err,
        }
        return result_payload
    except Exception as exc:
        return {
            "success": False,
            "url": url,
            "final_url": "",
            "title": "",
            "html_length": 0,
            "html": "",
            "duration_ms": int((time.monotonic() - started) * 1000),
            "cloudflare_challenge": False,
            "engine": "stealth_browser_mcp",
            "error": f"{type(exc).__name__}: {exc}",
        }
    finally:
        try:
            await bm.close_instance(instance_id)
        except Exception:
            pass
        if cf_site_key(url):
            try:
                _prof = profile
                _eph = ephemeral
                if _eph:
                    cleanup_ephemeral_dir(_prof)
            except NameError:
                pass
        gc.collect()


async def fetch_via_nodriver_stealth(
    url: str,
    wait_sec: float,
    timeout_sec: float,
    marker: str,
    headless: bool,
) -> dict:
    import nodriver as uc
    from platform_utils import check_browser_executable, merge_browser_args

    started = time.monotonic()
    browser = None
    profile, ephemeral, site_key = resolve_user_data_dir(url)
    is_autodoc = site_key == "autodoc"
    if not ephemeral:
        sanitize_chrome_profile(profile)
    browser_profile = load_browser_profile()
    try:
        exe = check_browser_executable()
        launch_args = strip_windows_bad_chrome_flags(
            merge_browser_args(headless_launch_args(headless))
        )
        if site_key:
            launch_args = autodoc_launch_args(launch_args, fresh_session=ephemeral)
        if browser_profile.get("user_agent"):
            ua = str(browser_profile["user_agent"])
            launch_args = [a for a in launch_args if not a.startswith("--user-agent=")]
            launch_args.append(f"--user-agent={ua}")
        vw = int(browser_profile.get("viewport_width") or 1366)
        vh = int(browser_profile.get("viewport_height") or 768)
        config = uc.Config(
            headless=headless,
            user_data_dir=profile,
            sandbox=True,
            browser_executable_path=exe,
            browser_args=launch_args,
        )
        browser = await uc.start(config=config)
        tab = browser.main_tab
        try:
            await tab.set_window_size(left=0, top=0, width=vw, height=vh)
        except Exception:
            pass
        await tab.get(url)
        await tab.sleep(1.5)
        await dismiss_cookie_banners(tab, aggressive=True)
        await tab.sleep(0.8)
        await dismiss_cookie_banners(tab, aggressive=True)

        deadline = time.monotonic() + max(15.0, timeout_sec)
        poll = max(1.5, min(4.0, wait_sec / 3))
        html, title = await poll_until_ready(tab, deadline, marker, poll)

        final_url = await tab.evaluate("window.location.href")
        cf = is_cloudflare_challenge(html, str(title or ""))
        ok = page_ready(html, str(title or ""), marker) and not cf

        return {
            "success": ok,
            "url": url,
            "final_url": str(final_url or url),
            "title": str(title or ""),
            "html_length": len(html or ""),
            "html": html or "",
            "duration_ms": int((time.monotonic() - started) * 1000),
            "cloudflare_challenge": cf,
            "engine": "stealth_browser_nodriver",
            "profile_dir": profile,
            "error": (
                "Cloudflare Turnstile — instalează deps MCP: pip install -r tools/stealth-browser-mcp/requirements.txt"
                if cf or not ok
                else ""
            ),
        }
    except Exception as exc:
        return {
            "success": False,
            "url": url,
            "final_url": "",
            "title": "",
            "html_length": 0,
            "html": "",
            "duration_ms": int((time.monotonic() - started) * 1000),
            "cloudflare_challenge": False,
            "engine": "stealth_browser_nodriver",
            "error": f"{type(exc).__name__}: {exc}",
        }
    finally:
        if browser is not None:
            try:
                browser.stop()
            except Exception:
                pass
        gc.collect()


def mcp_stack_available() -> bool:
    try:
        import pydantic  # noqa: F401
        from browser_manager import BrowserManager  # noqa: F401

        return True
    except Exception:
        return False


async def fetch_page(
    url: str,
    wait_sec: float,
    timeout_sec: float,
    marker: str = "",
    headless: bool = True,
) -> dict:
    host = url.lower()
    site = cf_site_key(host)

    if mcp_stack_available():
        # Autodoc/Ovoko: pe ecran — off-screen declanșează turnstileBotCheck mai des.
        on_screen = bool(site) and not headless
        result = await fetch_via_mcp(
            url, wait_sec, timeout_sec, marker, headless, on_screen=on_screen
        )
        return result
    return await fetch_via_nodriver_stealth(url, wait_sec, timeout_sec, marker, headless)


def main() -> int:
    parser = argparse.ArgumentParser(description="Stealth browser fetch (stealth-browser-mcp)")
    parser.add_argument("url_pos", nargs="?", help="URL de încărcat")
    parser.add_argument("--url", dest="url_flag", help="URL alternativ")
    parser.add_argument("--wait", type=float, default=3.0, help="Secunde între verificări")
    parser.add_argument("--timeout", type=float, default=60.0, help="Timeout total navigare")
    parser.add_argument("--marker", default="", help="Substring care confirmă pagina (ex: listing-item__wrap)")
    parser.add_argument(
        "--headless",
        action=argparse.BooleanOptionalAction,
        default=None,
        help="Browser invizibil",
    )
    parser.add_argument("--json", action="store_true", help="JSON pe stdout")
    parser.add_argument("--html-file", dest="html_file", help="Salvează HTML în fișier")
    args = parser.parse_args()

    url = (args.url_flag or args.url_pos or os.environ.get("STEALTH_BROWSER_URL", "")).strip()
    if not url:
        print(json.dumps({"success": False, "error": "Lipsește URL"}, ensure_ascii=False))
        return 2

    wait = max(0.0, args.wait)
    timeout = max(15.0, args.timeout)
    marker = (args.marker or "").strip()
    host = url.lower()
    if not marker and "autodoc" in host:
        marker = "listing-item__wrap"
        wait = max(wait, 12.0)
        timeout = max(timeout, 120.0)
    elif not marker and "epiesa" in host:
        marker = "prod-card"
        wait = max(wait, 10.0)
        timeout = max(timeout, 75.0)
    elif not marker and "emag" in host:
        marker = "card-v2"
        wait = max(wait, 8.0)

    headless = resolve_headless(host, args.headless)

    outer_timeout = timeout + 30.0
    if "autodoc" in host:
        cf_wait = max(0.0, float(os.environ.get("STEALTH_BROWSER_CF_WAIT", "120") or "120"))
        outer_timeout = timeout + min(cf_wait, 180.0) + 60.0

    try:
        result = asyncio.run(
            asyncio.wait_for(
                fetch_page(url, wait, timeout, marker, headless),
                timeout=outer_timeout,
            )
        )
    except asyncio.TimeoutError:
        result = {
            "success": False,
            "url": url,
            "final_url": "",
            "title": "",
            "html_length": 0,
            "html": "",
            "duration_ms": 0,
            "cloudflare_challenge": True,
            "engine": "stealth_browser_fetch",
            "error": f"Timeout total după {int(outer_timeout)}s — pagina nu a livrat markerul «{marker}»",
        }
    except Exception as exc:
        result = {
            "success": False,
            "url": url,
            "final_url": "",
            "title": "",
            "html_length": 0,
            "html": "",
            "duration_ms": 0,
            "cloudflare_challenge": False,
            "engine": "stealth_browser_fetch",
            "error": f"{type(exc).__name__}: {exc}",
        }

    if args.html_file and result.get("html"):
        with open(args.html_file, "w", encoding="utf-8", errors="replace") as fh:
            fh.write(result["html"])
        result = dict(result)
        result["html_saved"] = args.html_file
        payload = dict(result)
        payload["html"] = ""
        print(json.dumps(payload, ensure_ascii=True))
        return 0 if result.get("success") else 1

    print(json.dumps(result, ensure_ascii=True))
    return 0 if result.get("success") else 1


if __name__ == "__main__":
    sys.exit(main())
