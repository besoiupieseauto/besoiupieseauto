import os
import re
import shutil
import socket
import subprocess
import sys
import threading
import time
import random
import json
import requests
from collections import deque
from flask import Flask, request, jsonify, make_response
from flask_cors import CORS

try:
    import setuptools
except ImportError:
    pass

import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

API_KEY_2CAPTCHA = "CHEIA_TA_AICI"

try:
    from twocaptcha import TwoCaptcha
    solver = TwoCaptcha(API_KEY_2CAPTCHA)
except ImportError:
    solver = None
    print("Warning: Biblioteca 2captcha nu este instalata.")

browsere_active = {}
browser_active_flags = {}
status_clienti = {}
lucrari_active = set()
stop_flags = {}
publish_queues = {}
launch_in_progress = set()
launch_lock = threading.Lock()
last_publish_results = {}

_BASE_DIR = os.path.dirname(os.path.abspath(__file__))
_DATA_DIR = os.path.join(_BASE_DIR, "data")
os.makedirs(_DATA_DIR, exist_ok=True)


def _channel_id():
    raw = (
        os.environ.get("ROBOT_CHANNEL_ID")
        or os.environ.get("ROBOT_CHANNEL")
        or "default"
    )
    cleaned = re.sub(r"[^a-z0-9_-]", "", str(raw).lower())
    return cleaned or "default"


CHANNEL_ID = _channel_id()
RUNTIME_FILE = os.path.join(_DATA_DIR, f"runtime_{CHANNEL_ID}.json")
LISTENER_FILE = os.path.join(_DATA_DIR, f"listener_{CHANNEL_ID}.json")


def _load_runtime():
    try:
        if os.path.isfile(RUNTIME_FILE):
            with open(RUNTIME_FILE, encoding="utf-8") as fh:
                data = json.load(fh)
                if isinstance(data, dict):
                    return data
    except Exception:
        pass
    return {}


def _save_runtime(state):
    try:
        state = dict(state or {})
        state["channel"] = CHANNEL_ID
        state["updated_at"] = time.time()
        with open(RUNTIME_FILE, "w", encoding="utf-8") as fh:
            json.dump(state, fh, indent=2, ensure_ascii=False)
    except Exception as ex:
        print(f"WARN: nu pot salva runtime ({CHANNEL_ID}): {ex}")


def persist_listener(port):
    payload = {
        "channel": CHANNEL_ID,
        "port": int(port),
        "pid": os.getpid(),
        "started_at": time.time(),
        "robot_dir": _BASE_DIR,
    }
    try:
        with open(LISTENER_FILE, "w", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2)
    except Exception as ex:
        print(f"WARN: nu pot salva listener ({CHANNEL_ID}): {ex}")
    runtime = _load_runtime()
    runtime["listener_port"] = int(port)
    runtime["listener_pid"] = os.getpid()
    _save_runtime(runtime)


def normalize_cont_id(cont_id):
    raw = (cont_id or "default").replace("manual_", "")
    raw = re.sub(r"[^a-zA-Z0-9]", "", raw) or "default"
    prefix = f"{CHANNEL_ID}_"
    if raw.lower().startswith(prefix):
        return raw
    return prefix + raw


def update_browser_session(cont_id, profile_dir=None, page_url=None, debug_port=None, platform_connected=None):
    cont_id = normalize_cont_id(cont_id)
    runtime = _load_runtime()
    browsers = runtime.setdefault("browsers", {})
    entry = browsers.get(cont_id, {})
    if profile_dir:
        entry["profile_dir"] = profile_dir
    if page_url:
        entry["page_url"] = page_url
    if debug_port:
        entry["debug_port"] = int(debug_port)
    if platform_connected is not None:
        entry["platform_connected"] = bool(platform_connected)
        if platform_connected:
            entry["login_at"] = time.time()
    entry["updated_at"] = time.time()
    browsers[cont_id] = entry
    _save_runtime(runtime)


def persist_platform_login(cont_id, page_url="", connected=True):
    cont_id = normalize_cont_id(cont_id)
    user_data = _profile_dir_for(cont_id)
    update_browser_session(
        cont_id,
        profile_dir=user_data,
        page_url=page_url or None,
        platform_connected=connected,
    )


def _profile_dir_for(cont_id):
    base_dir = os.path.dirname(os.path.abspath(__file__))
    return os.path.join(base_dir, f"profil_pa_{normalize_cont_id(cont_id)}")


def chrome_profile_in_use(user_data_dir):
    if not user_data_dir or not os.path.isdir(user_data_dir):
        return False
    for name in ("SingletonLock", "SingletonSocket", "SingletonCookie"):
        if os.path.exists(os.path.join(user_data_dir, name)):
            return True
    return False


def _mesaj_indica_login(mesaj):
    ml = (mesaj or "").lower()
    return any(
        token in ml
        for token in (
            "logat cu succes",
            "conectat la pieseauto",
            "sesiune recuperată",
            "sesiune recuperata",
            "conectat",
            "🏁",
        )
    )


def resolve_session_status(cont_id):
    """Stare browser/platformă — live Selenium sau cache + profil Chrome."""
    cont_id = normalize_cont_id(cont_id)
    mesaj = status_clienti.get(cont_id, "Inactiv")
    page_url = ""
    browser_open = False
    platform_connected = False

    driver = browsere_active.get(cont_id)
    if driver and driver_live(driver, timeout=3.0):
        browser_open = True
        _set_browser_active(cont_id, True)
        try:
            page_url = driver.current_url or ""
            platform_connected = login_reusit(driver)
            if platform_connected:
                persist_platform_login(cont_id, page_url, True)
        except Exception:
            pass
        return {
            "browser_open": browser_open,
            "platform_connected": platform_connected,
            "page_url": page_url,
            "mesaj": mesaj,
        }

    if driver:
        browsere_active.pop(cont_id, None)
    _set_browser_active(cont_id, False)

    user_data = _profile_dir_for(cont_id)
    profile_running = chrome_profile_in_use(user_data)
    runtime = _load_runtime()
    entry = (runtime.get("browsers") or {}).get(cont_id, {})
    page_url = str(entry.get("page_url") or "")
    cached_platform = bool(entry.get("platform_connected"))
    login_at = float(entry.get("login_at") or 0)
    recent_login = login_at > 0 and (time.time() - login_at) < 86400 * 7

    if profile_running:
        browser_open = True
    if cached_platform and (profile_running or recent_login):
        platform_connected = True
    elif _mesaj_indica_login(mesaj):
        platform_connected = True
        if profile_running:
            browser_open = True

    if platform_connected and not _mesaj_indica_login(mesaj) and mesaj.lower() in ("inactiv", ""):
        mesaj = "Conectat la PieseAuto.ro"

    return {
        "browser_open": browser_open,
        "platform_connected": platform_connected,
        "page_url": page_url,
        "mesaj": mesaj,
    }


def _assert_channel_request():
    hdr = (request.headers.get("X-Robot-Channel") or "").strip().lower()
    if hdr and hdr != CHANNEL_ID:
        return _corsify({"status": "eroare", "mesaj": "Canal robot invalid."}), 403
    return None


def _set_publish_result(cont_id, ok, message):
    last_publish_results[cont_id.replace("manual_", "")] = {
        "ok": bool(ok),
        "message": str(message or "")[:500],
        "at": time.time(),
    }


def _publish_queue_for(cont_id):
    if cont_id not in publish_queues:
        publish_queues[cont_id] = deque()
    return publish_queues[cont_id]


def _start_publish_job(data, cont_id, driver):
    lucrari_active.add(cont_id)
    threading.Thread(
        target=proceseaza_adauga_piesa,
        args=(data, cont_id, driver),
        daemon=True
    ).start()


def _process_next_in_queue(cont_id, driver):
    if not driver or not driver_live(driver):
        lucrari_active.discard(cont_id)
        return

    queue = _publish_queue_for(cont_id)
    if not queue:
        lucrari_active.discard(cont_id)
        return

    next_data = queue.popleft()
    remaining = len(queue)
    update_status(cont_id, f"📋 Coadă: următorul produs ({remaining} rămase)...")
    _start_publish_job(next_data, cont_id, driver)


def este_oprit(cont_id):
    return bool(stop_flags.get(cont_id))


def _set_browser_active(cont_id, active):
    cont_id = normalize_cont_id(cont_id)
    if not cont_id:
        return
    if active:
        browser_active_flags[cont_id] = True
    else:
        browser_active_flags.pop(cont_id, None)


def driver_live(driver, timeout=2.0):
    """Verifică dacă sesiunea Selenium răspunde."""
    if not driver:
        return False
    result = {"ok": False, "dead": False}

    def _probe():
        try:
            _ = driver.current_url
            result["ok"] = True
        except Exception as ex:
            if _sesiune_moarta(ex):
                result["dead"] = True

    probe = threading.Thread(target=_probe, daemon=True)
    probe.start()
    probe.join(max(0.5, float(timeout or 2.0)))
    if result["ok"]:
        return True
    if result["dead"]:
        return False
    return False


def _sesiune_moarta(ex):
    msg = str(ex or "").lower()
    return any(
        token in msg
        for token in (
            "invalid session",
            "session deleted",
            "disconnected",
            "no such window",
            "chrome not reachable",
            "unable to connect",
            "connection refused",
            "max retries exceeded",
            "failed to establish a new connection",
            "10061",
            "httpconnectionpool",
            "target window already closed",
        )
    )


def opreste_complet(cont_id):
    cont_id = normalize_cont_id(cont_id)
    if not cont_id:
        return
    stop_flags[cont_id] = True
    lucrari_active.discard(cont_id)
    base_dir = os.path.dirname(os.path.abspath(__file__))
    user_data = os.path.join(base_dir, f"profil_pa_{cont_id}")
    opreste_chrome_profil(user_data, cont_id)
    update_status(cont_id, "🛑 Oprit. Poți relansa acum.")
    stop_flags[cont_id] = False


def reset_sesiune_complet(cont_id):
    """Oprește browserul, șterge profilul Chrome și resetează starea la zero."""
    cont_id = normalize_cont_id(cont_id)
    if not cont_id:
        return False, "Cont invalid."

    stop_flags[cont_id] = True
    lucrari_active.discard(cont_id)
    launch_in_progress.discard(cont_id)
    publish_queues.pop(cont_id, None)
    last_publish_results.pop(cont_id, None)

    base_dir = os.path.dirname(os.path.abspath(__file__))
    user_data = os.path.join(base_dir, f"profil_pa_{cont_id}")

    update_status(cont_id, "🧹 Curăț sesiunea (profil Chrome)...")
    opreste_chrome_profil(user_data, cont_id)

    if os.path.isdir(user_data):
        try:
            shutil.rmtree(user_data, ignore_errors=True)
        except Exception as ex:
            stop_flags[cont_id] = False
            return False, f"Nu am putut șterge profilul: {ex}"

    time.sleep(1.0)
    curata_lockuri_profil(user_data)

    runtime = _load_runtime()
    browsers = runtime.get("browsers", {})
    browsers.pop(cont_id, None)
    runtime["browsers"] = browsers
    _save_runtime(runtime)

    status_clienti[cont_id] = "Inactiv"
    stop_flags[cont_id] = False

    return True, "Sesiune ștearsă — profil Chrome gol. Poți porni login nou."


def pauza_aleatorie(minim=1.5, maxim=3.5):
    time.sleep(random.uniform(minim, maxim))


def update_status(cont_id, mesaj):
    timestamp = time.strftime("%H:%M:%S")
    status_clienti[cont_id] = f"{timestamp} - {mesaj}"
    line = f"DEBUG [{cont_id}]: {mesaj}"
    try:
        print(line)
    except UnicodeEncodeError:
        print(line.encode(sys.stdout.encoding or "ascii", errors="replace").decode(sys.stdout.encoding or "ascii", errors="replace"))


def tasteaza_uman(element, text):
    element.clear()
    time.sleep(random.uniform(0.3, 0.7))
    for litera in str(text or ""):
        element.send_keys(litera)
        time.sleep(random.uniform(0.05, 0.15))


def _corsify(data):
    response = make_response(jsonify(data))
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Methods"] = "*"
    response.headers["Access-Control-Allow-Headers"] = "*"
    return response


def inchide_cookie_banner(driver, cont_id):
    try:
        wait = WebDriverWait(driver, 5)
        accept_btn = wait.until(EC.element_to_be_clickable((By.ID, "onetrust-accept-btn-handler")))
        pauza_aleatorie(0.5, 1.2)
        driver.execute_script("arguments[0].click();", accept_btn)
        update_status(cont_id, "🍪 Banner cookie închis.")
    except Exception:
        pass


def asteapta_pagina(driver, cont_id, minim=3, maxim=6):
    try:
        WebDriverWait(driver, 90).until(
            lambda d: d.execute_script("return document.readyState") == "complete"
        )
    except Exception:
        update_status(cont_id, "⚠️ Pagina se încarcă lent, continui cu așteptare extra...")

    pauza_aleatorie(minim, maxim)
    inchide_cookie_banner(driver, cont_id)


def click_js(driver, element):
    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", element)
    time.sleep(1)
    driver.execute_script("arguments[0].click();", element)


def _elemente_vizibile(elems):
    return [e for e in elems if e.is_displayed()]


def _primul_element(driver, selectors, timeout=12, clickable=False):
    deadline = time.time() + timeout
    while time.time() < deadline:
        for sel in selectors:
            try:
                elems = _elemente_vizibile(driver.find_elements(By.CSS_SELECTOR, sel))
                if elems:
                    return elems[0]
            except Exception:
                pass
        time.sleep(0.35)
    return None


def _termeni_cautare_categorie(categorie_nume, categorie_principala=""):
    raw = (categorie_nume or "Alte piese de caroserie").strip()
    main = (categorie_principala or "").strip()
    termeni = []
    if raw and raw.lower() not in ("diverse", "diverse piese"):
        termeni.append(raw)
    if main and main.lower() not in ("diverse",):
        termeni.append(main)
    if main and raw and main.lower() != raw.lower():
        termeni.append(f"{main} {raw}")
    if " -> " in raw:
        termeni.append(raw.split(" -> ")[-1].strip())
    if "/" in raw:
        parts = [p.strip() for p in raw.split("/") if p.strip()]
        if parts:
            termeni.append(parts[-1])
    words = [w for w in raw.replace("/", " ").replace("->", " ").split() if len(w) >= 3]
    if len(words) >= 2:
        termeni.append(" ".join(words[:3]))
    termeni.extend([
        "Alte piese de caroserie",
        "caroserie",
        "motor",
        "frana",
    ])
    out = []
    seen = set()
    for t in termeni:
        t = t.strip()
        if len(t) < 3:
            continue
        key = t.lower()
        if key in seen or key in ("diverse", "diverse piese"):
            continue
        seen.add(key)
        out.append(t[:80])
    return out


def _deschide_modal_categorie(driver, cont_id, wait):
    trigger = _primul_element(driver, [
        ".js-cs-btn",
        "button.js-cs-btn",
        "[class*='category'] button",
        "[class*='categor'] button",
        "button[aria-label*='ategor']",
    ], timeout=12, clickable=True)
    if trigger:
        click_js(driver, trigger)
        time.sleep(1.5)
    btn_quick = _primul_element(driver, [".js-search-btn"], timeout=2)
    if btn_quick:
        click_js(driver, btn_quick)
        time.sleep(0.8)


def _gaseste_input_categorie(driver, wait):
    input_selectors = [
        ".js-quicksearch-input",
        "input[placeholder*='caractere']",
        "input[placeholder*='Caut']",
        "input[placeholder*='caut']",
        ".modal.show input[type='text']",
        ".modal input[type='search']",
        "[class*='quicksearch'] input",
        ".js-cs-modal input",
        ".modal input[type='text']",
    ]
    el = _primul_element(driver, input_selectors, timeout=4)
    if el:
        return el
    return _first_visible_or_wait(driver, input_selectors, wait, 20)


def _declanseaza_cautare_categorie(driver, input_el, term):
    driver.execute_script("""
        var el = arguments[0];
        el.focus();
        el.value = '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    """, input_el)
    time.sleep(0.25)
    tasteaza_uman(input_el, term)
    try:
        input_el.send_keys(Keys.ARROW_DOWN)
        time.sleep(0.25)
        input_el.send_keys(Keys.ENTER)
    except Exception:
        pass


def _click_text_in_modal(driver, text):
    needle = (text or "").strip()
    if len(needle) < 3:
        return False
    needle_low = needle.lower()
    xpaths = [
        f"//*[contains(@class,'modal') or contains(@class,'quicksearch') or contains(@class,'cs-')]"
        f"//*[self::li or self::button or self::a or self::div or self::span]"
        f"[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZĂÂÎȘȚ', 'abcdefghijklmnopqrstuvwxyzăâîșț'), '{needle_low[:40]}')]",
        f"//*[@role='option' or @role='listitem'][contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZĂÂÎȘȚ', 'abcdefghijklmnopqrstuvwxyzăâîșț'), '{needle_low[:40]}')]",
    ]
    for xp in xpaths:
        try:
            elems = _elemente_vizibile(driver.find_elements(By.XPATH, xp))
            for el in elems:
                txt = (el.text or "").strip()
                if len(txt) >= 3 and needle_low in txt.lower():
                    click_js(driver, el)
                    return True
        except Exception:
            pass
    return False


def selecteaza_categorie_pieseauto(driver, cont_id, categorie_nume, wait, categorie_principala=""):
    """Selectează categoria — căutare rapidă, apoi navigare în modal (categorie → subcategorie)."""
    inchide_cookie_banner(driver, cont_id)
    termeni = _termeni_cautare_categorie(categorie_nume, categorie_principala)

    _deschide_modal_categorie(driver, cont_id, wait)
    input_cautare = _gaseste_input_categorie(driver, wait)
    if not input_cautare:
        raise TimeoutException("Nu găsesc câmpul de căutare categorie pe PieseAuto.")

    result_selectors = [
        ".js-quicksearch-item[data-idx='0']",
        ".js-quicksearch-item",
        "[class*='quicksearch-item']",
        ".modal [data-idx='0']",
        ".modal li button",
        ".modal li a",
        ".modal [role='option']",
        ".modal li",
    ]

    for term in termeni:
        update_status(cont_id, f"🔎 Caut categorie: {term[:55]}...")
        try:
            click_js(driver, input_cautare)
        except Exception:
            pass

        _declanseaza_cautare_categorie(driver, input_cautare, term)

        deadline = time.time() + 16
        while time.time() < deadline:
            rezultat = _primul_element(driver, result_selectors, timeout=1)
            if rezultat:
                click_js(driver, rezultat)
                time.sleep(2)
                update_status(cont_id, f"✅ Categorie selectată: {term[:55]}")
                return
            if _click_text_in_modal(driver, term):
                time.sleep(2)
                update_status(cont_id, f"✅ Categorie selectată (listă): {term[:55]}")
                return
            time.sleep(0.45)

        input_cautare = _gaseste_input_categorie(driver, wait) or input_cautare

    # Navigare ierarhică: categorie principală → subcategorie
    if categorie_principala:
        update_status(cont_id, f"🔎 Navighez categorie principală: {categorie_principala[:40]}...")
        _deschide_modal_categorie(driver, cont_id, wait)
        if _click_text_in_modal(driver, categorie_principala):
            time.sleep(1.5)
            sub = (categorie_nume or "Alte piese de caroserie").strip()
            if _click_text_in_modal(driver, sub):
                time.sleep(2)
                update_status(cont_id, f"✅ Subcategorie selectată: {sub[:55]}")
                return

    # Ultimă încercare — fallback sigur
    update_status(cont_id, "🔎 Fallback: Alte piese de caroserie...")
    _deschide_modal_categorie(driver, cont_id, wait)
    input_cautare = _gaseste_input_categorie(driver, wait)
    if input_cautare:
        _declanseaza_cautare_categorie(driver, input_cautare, "Alte piese de caroserie")
        deadline = time.time() + 20
        while time.time() < deadline:
            rezultat = _primul_element(driver, result_selectors, timeout=1)
            if rezultat:
                click_js(driver, rezultat)
                time.sleep(2)
                update_status(cont_id, "✅ Categorie fallback: Alte piese de caroserie")
                return
            if _click_text_in_modal(driver, "Alte piese de caroserie"):
                time.sleep(2)
                update_status(cont_id, "✅ Categorie fallback: Alte piese de caroserie")
                return
            time.sleep(0.45)

    raise TimeoutException(
        f"Niciun rezultat categorie pentru «{(categorie_nume or '')[:60]}». "
        "Verifică screenshot — poate fi CAPTCHA sau nume categorie invalid."
    )


def _first_visible_or_wait(driver, selectors, wait, timeout=20):
    end = time.time() + timeout
    while time.time() < end:
        el = _primul_element(driver, selectors, timeout=1)
        if el:
            return el
        time.sleep(0.3)
    for sel in selectors:
        try:
            return wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, sel)))
        except Exception:
            continue
    return None


def salveaza_debug(driver, cont_id, eroare):
    try:
        base_dir = os.path.dirname(os.path.abspath(__file__))
        poza = os.path.join(base_dir, f"eroare_{cont_id}.png")
        driver.save_screenshot(poza)
        return (
            f"{type(eroare).__name__}: {str(eroare)} | "
            f"URL: {driver.current_url} | TITLE: {driver.title} | Screenshot: {poza}"
        )
    except Exception:
        return f"{type(eroare).__name__}: {str(eroare)}"


def _port_robot_http_ok(port):
    """True dacă portul răspunde cu stare_completa de la robotul PieseAuto."""
    try:
        import urllib.request

        cont_probe = normalize_cont_id("besoiu")
        url = f"http://127.0.0.1:{int(port)}/stare_completa?cont_id={cont_probe}"
        req = urllib.request.Request(
            url,
            headers={"X-Robot-Channel": CHANNEL_ID, "ngrok-skip-browser-warning": "69420"},
        )
        with urllib.request.urlopen(req, timeout=2) as resp:
            body = resp.read().decode("utf-8", errors="ignore")
            return resp.status == 200 and '"service_online"' in body
    except Exception:
        return False


def _port_tcp_open(port):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(1.0)
    try:
        return sock.connect_ex(("127.0.0.1", int(port))) == 0
    finally:
        sock.close()


def _port_netstat_listening(port):
    try:
        import subprocess

        out = subprocess.check_output(["netstat", "-ano"], text=True, errors="ignore")
        needle = f":{int(port)} "
        for line in out.splitlines():
            if needle in line and "LISTENING" in line:
                return True
    except Exception:
        pass
    return False


def _port_blocked_or_zombie(port):
    if _port_robot_http_ok(port):
        return False
    if _port_tcp_open(port):
        return True
    if _port_netstat_listening(port):
        return True
    return False


def gaseste_port_liber(preferat=0):
    """Returnează un port TCP liber. Evită porturi zombie (LISTEN fără HTTP valid)."""
    candidates = []
    if preferat:
        candidates.append(int(preferat))
    for fallback in (5011, 5012, 5013, 5014, 5811, 5007):
        if fallback not in candidates:
            candidates.append(fallback)

    for port in candidates:
        if _port_robot_http_ok(port):
            print(f"INFO: portul {port} are deja robot activ - refolosesc.")
            return port
        if _port_blocked_or_zombie(port):
            print(f"WARN: portul {port} ocupat/zombie - incerc alt port.")
            continue
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            try:
                s.bind(("0.0.0.0", port))
                return port
            except OSError:
                continue

    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("0.0.0.0", 0))
        return s.getsockname()[1]


def curata_lockuri_profil(user_data_dir):
    for name in ("SingletonLock", "SingletonCookie", "SingletonSocket", "lockfile"):
        path = os.path.join(user_data_dir, name)
        try:
            if os.path.lexists(path):
                os.remove(path)
        except OSError:
            pass


def opreste_chrome_profil(user_data_dir, cont_id=None):
    """Inchide driver mort si procese Chrome/chromedriver ramase pe acelasi profil."""
    if cont_id:
        driver = browsere_active.pop(cont_id, None)
        if driver:
            try:
                driver.quit()
            except Exception:
                pass
        _set_browser_active(cont_id, False)
        launch_in_progress.discard(cont_id)

    if sys.platform == "win32" and user_data_dir:
        profile_abs = os.path.abspath(user_data_dir)
        profile_fwd = profile_abs.replace("\\", "/")
        profile_name = os.path.basename(profile_abs).replace("'", "''")
        for proc_name in ("chrome.exe", "chromedriver.exe"):
            ps = (
                f"Get-CimInstance Win32_Process -Filter \"name='{proc_name}'\" | "
                "Where-Object { $_.CommandLine -and "
                f"($_.CommandLine -like '*{profile_name}*' -or $_.CommandLine -like '*{profile_fwd}*') }} | "
                "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
            )
            try:
                subprocess.run(
                    ["powershell", "-NoProfile", "-NonInteractive", "-Command", ps],
                    capture_output=True,
                    timeout=20,
                )
            except Exception:
                pass

    curata_lockuri_profil(user_data_dir)
    time.sleep(1.5)


def _chrome_pornire_esuata(ex):
    return _sesiune_moarta(ex) or any(
        token in str(ex or "").lower()
        for token in (
            "session not created",
            "cannot connect to chrome",
            "chrome failed to start",
        )
    )


def build_chrome_options(user_data_dir):
    options = uc.ChromeOptions()
    options.add_argument(f"--user-data-dir={user_data_dir}")
    options.add_argument("--start-maximized")
    options.add_argument("--disable-popup-blocking")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    options.add_argument("--disable-extensions")
    options.add_argument("--disable-gpu")
    return options


def porneste_chrome(user_data_dir):
    """Porneste Chrome cu fallback pe versiuni si curatare profil."""
    last_err = None
    for attempt in range(3):
        if attempt > 0:
            opreste_chrome_profil(user_data_dir)
            time.sleep(2.0)
        for ver in (None, 145, 144, 131, 130, 148, 146):
            try:
                options = build_chrome_options(user_data_dir)
                if ver:
                    driver = uc.Chrome(options=options, version_main=ver, use_subprocess=True)
                else:
                    driver = uc.Chrome(options=options, use_subprocess=True)
                time.sleep(2.0)
                if not driver_live(driver, timeout=5.0):
                    try:
                        driver.quit()
                    except Exception:
                        pass
                    raise RuntimeError("Chrome pornit dar sesiunea Selenium nu răspunde.")
                return driver
            except Exception as ex:
                last_err = ex
                if _chrome_pornire_esuata(ex):
                    break
    raise last_err or RuntimeError("Nu am putut porni Chrome.")


def extrage_mesaj_eroare_login(driver):
    selectors = [
        ".alert-danger",
        ".error-message",
        ".field-error",
        ".form-error",
        "[class*='error']",
        ".text-danger",
    ]
    for sel in selectors:
        for el in driver.find_elements(By.CSS_SELECTOR, sel):
            try:
                if el.is_displayed():
                    txt = (el.text or "").strip()
                    if txt and len(txt) < 200:
                        return txt
            except Exception:
                pass
    body = (driver.page_source or "").lower()
    if "parola gre" in body or "parola gresit" in body:
        return "Ai introdus parola greșită."
    if "email" in body and "gre" in body:
        return "Email sau parolă incorectă."
    return "Autentificare respinsă de site."


def verifica_eroare_login(driver):
    msg = extrage_mesaj_eroare_login(driver)
    body = (driver.page_source or "").lower()
    if "parola gre" in body or "parola gresit" in body or "ai introdus parola" in body:
        return True
    if msg and msg != "Autentificare respinsă de site.":
        return True
    return False


def login_reusit(driver):
    if verifica_eroare_login(driver):
        return False
    url = (driver.current_url or "").lower()
    if len(driver.find_elements(By.NAME, "password")) > 0 and "action=auth" in url:
        return False
    if len(driver.find_elements(By.NAME, "email")) > 0 and "action=auth" in url:
        return False
    indicatoare = [
        "a[href*='logout']",
        "a[href*='action=logout']",
        ".user-menu",
        ".member-menu",
        ".js-logout",
    ]
    for sel in indicatoare:
        if driver.find_elements(By.CSS_SELECTOR, sel):
            return True
    if "members.php" in url and "action=auth" not in url:
        return True
    if "pieseauto.ro" in url and "action=auth" not in url and "show=password" not in url:
        return True
    return len(driver.find_elements(By.NAME, "password")) == 0 and len(driver.find_elements(By.NAME, "email")) == 0


def asteapta_rezultat_login(driver, cont_id, timeout=20):
    """Asteapta dupa submit parola — succes sau mesaj de eroare."""
    start = time.time()
    while time.time() - start < timeout:
        if verifica_eroare_login(driver):
            return "error"
        if login_reusit(driver):
            return "ok"
        time.sleep(0.8)
    if verifica_eroare_login(driver):
        return "error"
    if login_reusit(driver):
        return "ok"
    return "unknown"


def delogare_si_pregateste_login(driver, cont_id):
    """Închide sesiunea existentă și deschide formularul de autentificare."""
    update_status(cont_id, "🔄 Delogare sesiune veche...")
    try:
        logout_urls = [
            "https://www.pieseauto.ro/members.php?action=logout",
            "https://www.pieseauto.ro/contul-meu/?action=logout",
        ]
        for url in logout_urls:
            try:
                driver.get(url)
                asteapta_pagina(driver, cont_id, 2, 4)
                if not login_reusit(driver):
                    break
            except Exception:
                pass

        for sel in ("a[href*='logout']", "a[href*='action=logout']", ".js-logout"):
            for link in driver.find_elements(By.CSS_SELECTOR, sel):
                try:
                    if link.is_displayed():
                        click_js(driver, link)
                        pauza_aleatorie(1.5, 2.5)
                        break
                except Exception:
                    pass
            if not login_reusit(driver):
                break
    except Exception:
        pass

    driver.get("https://www.pieseauto.ro/members.php?action=auth")
    asteapta_pagina(driver, cont_id, 3, 5)


def executa_login_pieseauto(driver, cont_id, email, password):
    """Completează formularul de login PieseAuto.ro."""
    update_status(cont_id, "🔑 Login cu datele din Stația 1...")
    wait = WebDriverWait(driver, 30)

    email_field = wait.until(EC.element_to_be_clickable((By.NAME, "email")))
    tasteaza_uman(email_field, email)
    click_js(driver, driver.find_element(By.CSS_SELECTOR, "button.btn-blue"))

    pauza_aleatorie(1.5, 2.5)

    pass_field = wait.until(EC.visibility_of_element_located((By.NAME, "password")))
    pass_field.clear()
    time.sleep(0.3)
    pass_field.send_keys(str(password or ""))
    pauza_aleatorie(0.5, 1.0)
    click_js(driver, driver.find_element(By.CSS_SELECTOR, ".js-pass-form button.btn-blue"))

    update_status(cont_id, "⏳ Verific login...")
    return asteapta_rezultat_login(driver, cont_id)


def navigare_sau_refresh(driver, url, cont_id):
    url_tinta = url.split("?")[0].split("#")[0].rstrip("/")
    url_actual = driver.current_url.split("?")[0].split("#")[0].rstrip("/")

    if url_tinta == url_actual:
        update_status(cont_id, "📄 Pagina este deja activă.")
    else:
        update_status(cont_id, "🌐 Navigăm la URL...")
        driver.get(url)
        asteapta_pagina(driver, cont_id, 2, 4)


def lanseaza_instanta(cont_id, email, password, force_fresh=False):
    global browsere_active

    cont_id = normalize_cont_id(cont_id)
    stop_flags[cont_id] = False

    base_dir = os.path.dirname(os.path.abspath(__file__))
    user_data = os.path.join(base_dir, f"profil_pa_{cont_id}")

    try:
        existing = browsere_active.get(cont_id)
        if existing:
            if driver_live(existing, timeout=3.0):
                if login_reusit(existing) and not force_fresh:
                    update_status(cont_id, "✅ Deja logat pe PieseAuto.ro.")
                    persist_platform_login(cont_id, existing.current_url, True)
                else:
                    update_status(cont_id, "✅ Robotul este deja activ.")
                _set_browser_active(cont_id, True)
                try:
                    update_browser_session(
                        cont_id,
                        profile_dir=user_data,
                        page_url=existing.current_url,
                        platform_connected=login_reusit(existing),
                    )
                except Exception:
                    pass
                return
            browsere_active.pop(cont_id, None)
            _set_browser_active(cont_id, False)

        if not force_fresh:
            session = resolve_session_status(cont_id)
            if session.get("platform_connected") and session.get("browser_open"):
                update_status(cont_id, "✅ Deja logat — sesiune Chrome activă.")
                return
            if chrome_profile_in_use(user_data) and session.get("platform_connected"):
                update_status(cont_id, "✅ Deja logat — refolosesc Chrome deschis.")
                try:
                    driver = porneste_chrome(user_data)
                    if driver and driver_live(driver, timeout=5.0):
                        browsere_active[cont_id] = driver
                        _set_browser_active(cont_id, True)
                        persist_platform_login(cont_id, driver.current_url, True)
                        update_status(cont_id, "✅ Sesiune refolosită — deja logat.")
                        return
                except Exception:
                    update_status(cont_id, "✅ Deja logat pe PieseAuto.ro (Chrome activ).")
                    return

        update_status(cont_id, "🧹 Pregătire Chrome...")
        if force_fresh or not chrome_profile_in_use(user_data):
            opreste_chrome_profil(user_data, cont_id)

        driver = None
        for launch_try in range(2):
            try:
                update_status(cont_id, "🚀 Pornire Chrome..." + (" (reîncercare)" if launch_try else ""))
                driver = porneste_chrome(user_data)
                browsere_active[cont_id] = driver
                _set_browser_active(cont_id, True)

                update_status(cont_id, "🌐 Deschid PieseAuto.ro...")
                driver.get("https://www.pieseauto.ro/members.php?action=auth")
                asteapta_pagina(driver, cont_id, 3, 5)
                if not driver_live(driver, timeout=4.0):
                    raise RuntimeError("Chrome s-a închis imediat după deschiderea paginii.")
                break
            except Exception as ex:
                browsere_active.pop(cont_id, None)
                _set_browser_active(cont_id, False)
                if driver:
                    try:
                        driver.quit()
                    except Exception:
                        pass
                driver = None
                opreste_chrome_profil(user_data, cont_id)
                if launch_try == 0 and (_sesiune_moarta(ex) or _chrome_pornire_esuata(ex)):
                    continue
                raise

        if not driver:
            raise RuntimeError("Nu am putut menține browserul deschis.")

        update_browser_session(
            cont_id,
            profile_dir=user_data,
            page_url=driver.current_url,
        )

        has_credentials = bool(email and password)

        if login_reusit(driver) and not force_fresh:
            update_status(cont_id, "✅ Deja logat pe PieseAuto.ro.")
            persist_platform_login(cont_id, driver.current_url, True)
            return

        if force_fresh and login_reusit(driver):
            delogare_si_pregateste_login(driver, cont_id)

        if has_credentials and len(driver.find_elements(By.NAME, "email")) > 0:
            rezultat = executa_login_pieseauto(driver, cont_id, email, password)
            if rezultat == "ok":
                update_status(cont_id, "🏁 Logat cu succes!")
                persist_platform_login(cont_id, driver.current_url, True)
                update_browser_session(
                    cont_id,
                    profile_dir=user_data,
                    page_url=driver.current_url,
                    platform_connected=True,
                )
            elif rezultat == "error":
                msg = extrage_mesaj_eroare_login(driver)
                update_status(cont_id, f"❌ Login eșuat: {msg}")
                browsere_active.pop(cont_id, None)
                _set_browser_active(cont_id, False)
                try:
                    driver.quit()
                except Exception:
                    pass
            else:
                update_status(cont_id, "❌ Login neconfirmat — verifică email/parola salvată în Stația 1.")
                browsere_active.pop(cont_id, None)
                _set_browser_active(cont_id, False)
        elif has_credentials and login_reusit(driver):
            update_status(cont_id, "✅ Deja logat pe PieseAuto.ro.")
            persist_platform_login(cont_id, driver.current_url, True)
        elif has_credentials:
            update_status(cont_id, "⚠️ Formular login indisponibil — încearcă «Sesiune nouă».")
        else:
            if login_reusit(driver):
                update_status(cont_id, "✅ Sesiune recuperată (deja logat).")
                persist_platform_login(cont_id, driver.current_url, True)
            else:
                update_status(cont_id, "⚠️ Nu sunt pe formular login — verifică manual browserul.")

    except Exception as e:
        msg = str(e)
        if _sesiune_moarta(e):
            msg = "Chrome s-a închis prematur. Închide alte ferestre Chrome robot, repornește start_pieseauto_visible.bat și încearcă din nou."
        elif len(msg) > 220:
            msg = msg[:220] + "..."
        update_status(cont_id, f"❌ Eroare la pornire: {msg}")
        try:
            opreste_chrome_profil(user_data, cont_id)
        except Exception:
            browsere_active.pop(cont_id, None)
            _set_browser_active(cont_id, False)
    finally:
        launch_in_progress.discard(cont_id)


@app.route("/verificare_sesiune", methods=["GET", "OPTIONS"])
def verificare():
    denied = _assert_channel_request()
    if denied:
        return denied
    return _corsify({"status": "online", "channel": CHANNEL_ID})


@app.route("/comanda", methods=["POST", "OPTIONS"])
def comanda():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    data = request.json or {}
    cont_id = normalize_cont_id(data.get("cont_id", "default"))
    force_fresh = bool(data.get("force_fresh") or data.get("force_login"))

    if not force_fresh:
        session = resolve_session_status(cont_id)
        if session.get("platform_connected"):
            return _corsify({
                "status": "activ",
                "mesaj": "Deja logat pe PieseAuto.ro — nu este nevoie de login.",
                "platform_connected": True,
                "browser_active": bool(session.get("browser_open")),
            })

    if cont_id in launch_in_progress:
        return _corsify({"status": "activ", "mesaj": "Browserul se deschide deja — așteaptă."})

    existing = browsere_active.get(cont_id)
    if existing and driver_live(existing, timeout=1.5):
        return _corsify({"status": "activ", "mesaj": "Browser deja activ."})

    if browser_active_flags.get(cont_id) and existing and driver_live(existing, timeout=1.5):
        return _corsify({"status": "activ", "mesaj": "Browser deja activ."})

    with launch_lock:
        if cont_id in launch_in_progress:
            return _corsify({"status": "activ", "mesaj": "Browserul se deschide deja — așteaptă."})
        launch_in_progress.add(cont_id)

    threading.Thread(
        target=lanseaza_instanta,
        args=(
            cont_id,
            data.get("user"),
            data.get("pass"),
            bool(data.get("force_fresh") or data.get("force_login")),
        ),
        daemon=True
    ).start()

    return _corsify({"status": "lansat", "mesaj": "Pornire browser (o singură fereastră)."})


@app.route("/analizeaza_cerere", methods=["GET", "OPTIONS"])
def analizeaza_cerere():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = normalize_cont_id(request.args.get("cont_id", ""))
    url = request.args.get("url")
    driver = browsere_active.get(cont_id)

    if not driver:
        return _corsify({"status": "eroare", "mesaj": "Browser inactiv"}), 400

    try:
        navigare_sau_refresh(driver, url, cont_id)

        wait = WebDriverWait(driver, 30)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, ".cr-title, h1")))

        titlu = driver.find_element(By.CSS_SELECTOR, ".cr-title, h1").text.strip()
        masina = driver.find_element(By.CSS_SELECTOR, ".cr-car, .request-car").text.replace("pentru", "").strip()

        return _corsify({
            "status": "succes",
            "date_cerere": {
                "titlu": titlu,
                "masina": masina
            }
        })

    except Exception as e:
        return _corsify({"status": "eroare", "mesaj": str(e)}), 500


@app.route("/adauga_piesa_noua", methods=["POST", "OPTIONS"])
def adauga_piesa():
    if request.method == "OPTIONS":
        return _corsify({"status": "ok"})

    denied = _assert_channel_request()
    if denied:
        return denied

    data = request.get_json() or {}
    cont_id = normalize_cont_id(data.get("cont_id", ""))
    driver = browsere_active.get(cont_id)

    if not driver or not browser_active_flags.get(cont_id):
        return _corsify({"status": "eroare", "mesaj": "Browser inactiv — apasă «Lansează browser robot»."}), 400

    if cont_id in lucrari_active:
        queue = _publish_queue_for(cont_id)
        queue.append(data)
        queue_len = len(queue)
        return _corsify({
            "status": "succes",
            "mesaj": f"Adăugat în coadă ({queue_len} în așteptare).",
            "queued": True,
            "queue_size": queue_len,
        })

    _start_publish_job(data, cont_id, driver)

    update_status(cont_id, "📥 Comandă primită. Robotul lucrează în fundal...")

    return _corsify({
        "status": "succes",
        "mesaj": "Comanda a fost pornită. Robotul continuă încărcarea produsului."
    })


def proceseaza_adauga_piesa(data, cont_id, driver):
    job_ok = False
    job_msg = "Publicare nefinalizată"
    try:
        if este_oprit(cont_id):
            update_status(cont_id, "🛑 Oprit — nu mai public.")
            return
        driver.get("https://www.pieseauto.ro/vinde/")
        update_status(cont_id, "🌐 Navigare la formular...")
        asteapta_pagina(driver, cont_id, 8, 12)

        wait = WebDriverWait(driver, 90)

        update_status(cont_id, f"📍 Formular încărcat: {driver.current_url}")

        selecteaza_categorie_pieseauto(
            driver,
            cont_id,
            data.get("categorie_nume", "Alte piese de caroserie"),
            wait,
            data.get("categorie_principala", ""),
        )

        time.sleep(2)
        update_status(cont_id, "✍️ Completez titlu și descriere...")

        titlu_in = wait.until(EC.presence_of_element_located((By.NAME, "product[project_title]")))
        tasteaza_uman(titlu_in, data.get("titlu"))

        desc_text = data.get("descriere", "")
        driver.execute_script("""
            var text = arguments[0];

            var ed = document.querySelector('.jqte_editor');
            if (ed) {
                ed.innerHTML = text;
                ed.dispatchEvent(new Event('input', { bubbles: true }));
                ed.dispatchEvent(new Event('change', { bubbles: true }));
            }

            var tx = document.querySelector('textarea[name="product[description]"]');
            if (tx) {
                tx.value = text;
                tx.dispatchEvent(new Event('input', { bubbles: true }));
                tx.dispatchEvent(new Event('change', { bubbles: true }));
            }
        """, desc_text)

        update_status(cont_id, "💰 Completez stare și preț...")

        stare_val = data.get("stare_produs", "Second")
        driver.execute_script("""
            var sel = document.querySelector('select[name="question[3]"]');
            if (sel) {
                sel.value = arguments[0];
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        """, stare_val)

        pret_in = wait.until(EC.presence_of_element_located((By.NAME, "product[currentprice]")))
        tasteaza_uman(pret_in, str(data.get("pret", "0")))

        update_status(cont_id, "🧹 Curățare imagini vechi...")

        driver.execute_script("""
            let deleteButtons = document.querySelectorAll('.img_delete, .js-img-delete');
            deleteButtons.forEach(btn => btn.click());
        """)

        time.sleep(4)

        imagini = data.get("imagini_multiple", [])
        if not imagini and data.get("imagine_url"):
            imagini = [data.get("imagine_url")]

        if imagini:
            for index, url_img in enumerate(imagini):
                img_path = None

                try:
                    img_path = os.path.join(os.getcwd(), f"temp_{cont_id}_{index}.jpg")
                    update_status(cont_id, f"📸 Imagine {index + 1}/{len(imagini)}: descarc...")

                    res = requests.get(url_img, stream=True, timeout=30)

                    if res.status_code == 200:
                        with open(img_path, "wb") as f:
                            for chunk in res.iter_content(chunk_size=8192):
                                if chunk:
                                    f.write(chunk)

                        file_input = wait.until(EC.presence_of_element_located((By.NAME, "auctionimage[]")))
                        file_input.send_keys(img_path)

                        update_status(cont_id, f"✅ Imagine {index + 1} trimisă. Aștept procesare...")
                        time.sleep(15)

                    else:
                        update_status(cont_id, f"⚠️ Imagine {index + 1}: serverul a răspuns {res.status_code}")

                except Exception as img_err:
                    update_status(cont_id, f"⚠️ Eroare imagine {index + 1}: {str(img_err)}")

                finally:
                    try:
                        if img_path and os.path.exists(img_path):
                            os.remove(img_path)
                    except Exception:
                        pass
        else:
            update_status(cont_id, "📂 Nicio imagine primită.")

        update_status(cont_id, "📤 Finalizare: Se publică anunțul...")

        try:
            btn_publica = wait.until(EC.element_to_be_clickable((By.NAME, "do_save")))
            click_js(driver, btn_publica)
            update_status(cont_id, "✅ Anunț publicat cu succes!")
            job_ok = True
            job_msg = "Anunț publicat cu succes"
        except Exception as e_pub:
            job_msg = f"Nu am putut apăsa Final: {str(e_pub)[:120]}"
            update_status(cont_id, f"⚠️ Atenție: {job_msg}")

    except Exception as e:
        detalii = salveaza_debug(driver, cont_id, e)
        job_msg = detalii
        update_status(cont_id, f"❌ Eroare critică: {detalii}")

    finally:
        _set_publish_result(cont_id, job_ok, job_msg)
        if este_oprit(cont_id):
            lucrari_active.discard(cont_id)
            publish_queues.pop(cont_id, None)
            return
        _process_next_in_queue(cont_id, driver)


@app.route("/trimite_oferta_site", methods=["POST", "OPTIONS"])
def trimite_oferta():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    data = request.json or {}
    cont_id = normalize_cont_id(data.get("cont_id", ""))
    driver = browsere_active.get(cont_id)

    if not driver:
        return _corsify({"status": "eroare"}), 400

    try:
        navigare_sau_refresh(driver, data.get("url"), cont_id)

        if len(driver.find_elements(By.NAME, "msg_group[1][desc]")) == 0:
            btn = driver.find_element(By.CSS_SELECTOR, "button.btn-make-offer")
            click_js(driver, btn)
            pauza_aleatorie(1, 2)

        wait = WebDriverWait(driver, 30)

        desc_in = wait.until(EC.presence_of_element_located((By.NAME, "msg_group[1][desc]")))
        tasteaza_uman(desc_in, data.get("descriere"))

        pret_in = driver.find_element(By.NAME, "msg_group[1][price]")
        tasteaza_uman(pret_in, data.get("pret"))

        driver.execute_script(f"""
            document.querySelector("select[name='msg_group[1][currency]']").value = '{data.get("moneda", "41")}';
            document.querySelector("input[name='new_sh'][value='{data.get("stare", "2")}']").click();
            document.querySelector("input[name='availability'][value='{data.get("disponibilitate", "1")}']").click();
        """)

        pauza_aleatorie(1, 2)

        click_js(driver, driver.find_element(By.CSS_SELECTOR, ".js-submit-btn"))

        update_status(cont_id, "✅ Ofertă trimisă cu succes!")
        return _corsify({"status": "succes"})

    except Exception as e:
        return _corsify({"status": "eroare", "mesaj": str(e)}), 500
@app.route("/stop", methods=["POST", "GET", "OPTIONS"])
@app.route("/stop_total", methods=["POST", "GET", "OPTIONS"])
def stop_total():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = request.args.get("cont_id", "")
    if not cont_id:
        data = request.get_json(silent=True) or {}
        cont_id = data.get("cont_id", "")

    cont_id = normalize_cont_id(cont_id)
    opreste_complet(cont_id)

    return _corsify({
        "status": "succes",
        "mesaj": "Robot oprit. Poți relansa."
    })


@app.route("/reset_sesiune", methods=["POST", "GET", "OPTIONS"])
def reset_sesiune():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = request.args.get("cont_id", "")
    if not cont_id:
        data = request.get_json(silent=True) or {}
        cont_id = data.get("cont_id", "")

    cont_id = normalize_cont_id(cont_id)
    ok, mesaj = reset_sesiune_complet(cont_id)

    return _corsify({
        "status": "succes" if ok else "eroare",
        "mesaj": mesaj,
        "reset": ok,
    }), (200 if ok else 500)


@app.route("/este_ocupat", methods=["GET", "OPTIONS"])
def este_ocupat():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = normalize_cont_id(request.args.get("cont_id", ""))
    session = resolve_session_status(cont_id) if cont_id else {
        "browser_open": False,
        "platform_connected": False,
        "page_url": "",
        "mesaj": "Inactiv",
    }
    browser_active = bool(session["browser_open"])

    return _corsify({
        "status": "ok",
        "busy": cont_id in lucrari_active,
        "browser_active": browser_active,
        "platform_connected": bool(session["platform_connected"]),
        "queue_size": len(_publish_queue_for(cont_id)) if cont_id else 0,
        "mesaj": session["mesaj"],
        "last_publish": last_publish_results.get(cont_id),
    })


@app.route("/stare_completa", methods=["GET", "OPTIONS"])
def stare_completa():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = normalize_cont_id(request.args.get("cont_id", ""))
    session = resolve_session_status(cont_id) if cont_id else {
        "browser_open": False,
        "platform_connected": False,
        "page_url": "",
        "mesaj": "Inactiv",
    }
    browser_open = bool(session["browser_open"])
    platform_connected = bool(session["platform_connected"])
    page_url = str(session["page_url"] or "")
    mesaj = str(session["mesaj"] or "Inactiv")

    if browser_open and platform_connected and not mesaj.lower().startswith("inactiv"):
        pass
    elif browser_open and platform_connected:
        mesaj = "Conectat la PieseAuto.ro"

    listener_port = None
    try:
        if os.path.isfile(LISTENER_FILE):
            with open(LISTENER_FILE, encoding="utf-8") as fh:
                listener = json.load(fh)
                if listener.get("channel") == CHANNEL_ID:
                    listener_port = listener.get("port")
    except Exception:
        pass

    return _corsify({
        "status": "ok",
        "channel": CHANNEL_ID,
        "service_online": True,
        "service_port": listener_port,
        "browser_open": browser_open,
        "platform_connected": platform_connected,
        "page_url": page_url,
        "mesaj": mesaj,
        "busy": cont_id in lucrari_active if cont_id else False,
        "queue_size": len(_publish_queue_for(cont_id)) if cont_id else 0,
    })


@app.route("/get_status", methods=["GET", "OPTIONS"])
def get_status():
    if request.method == "OPTIONS":
        return _corsify({})

    denied = _assert_channel_request()
    if denied:
        return denied

    cont_id = normalize_cont_id(request.args.get("cont_id", ""))
    return _corsify({"status": status_clienti.get(cont_id, "Inactiv")})


if __name__ == "__main__":
    port_dorit = int(os.environ.get("ROBOT_PIESEAUTO_PORT", "5011"))
    port = gaseste_port_liber(port_dorit)
    persist_listener(port)
    print("=" * 50)
    print(f"Robot PieseAuto — canal: {CHANNEL_ID}")
    if port != port_dorit:
        print(f"ATENTIE: portul {port_dorit} este ocupat — pornesc pe portul liber {port}.")
    print(f"Port: {port} | Runtime: {RUNTIME_FILE}")
    print("=" * 50)
    app.run(host="0.0.0.0", port=port, threaded=True)
