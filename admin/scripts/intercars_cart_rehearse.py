# -*- coding: utf-8 -*-
"""Simulare coș Intercars e-Cat: adaugă linia, izolează ID-ul, oprește înainte de comandă."""
from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
import time
from pathlib import Path
from urllib.parse import quote

MAPAREA = Path(r"D:\laragon\www\maparea\intercars\intercars_scan_coduri.py")
PROFILE = Path(r"D:\laragon\www\besoiupieseauto.ro\admin\scripts\ic_profile")
HOME_URLS = [
    "https://ro.e-cat.intercars.eu/ro/",
    "https://ro.e-cat.intercars.eu/en/",
]
PRODUCT_URLS = [
    "https://ro.e-cat.intercars.eu/ro/p/{code}",
    "https://ro.e-cat.intercars.eu/en/p/{code}",
    "https://ro.e-cat.intercars.eu/ro/product/{code}",
]
CART_URLS = [
    "https://ro.e-cat.intercars.eu/ro/cart",
    "https://ro.e-cat.intercars.eu/cart",
    "https://ro.e-cat.intercars.eu/ro/koszyk",
    "https://ro.e-cat.intercars.eu/ro/cos",
]
CHECKOUT_URLS = [
    "https://ro.e-cat.intercars.eu/ro/checkout",
    "https://ro.e-cat.intercars.eu/checkout",
    "https://ro.e-cat.intercars.eu/ro/cart/checkout",
]
ADD_SELECTORS = [
    'button:has-text("Adaugă în coș")',
    'button:has-text("Adauga in cos")',
    'button:has-text("Add to cart")',
    'button:has-text("Do koszyka")',
    'button:has-text("Koszyk")',
    '[data-testid*="add-to-cart"]',
    '[data-test*="add-to-cart"]',
    'button[class*="add-to-cart"]',
    'button[class*="addToCart"]',
]
CART_LINK_SELECTORS = [
    'a[href*="cart"]',
    'a[href*="koszyk"]',
    'a[href*="cos"]',
    '[data-testid*="cart"]',
    'a:has-text("Coș")',
    'a:has-text("Cos")',
    'button:has-text("Coș")',
]
SUBMIT_RE = re.compile(
    r"(trimite comand|plaseaz[aă] comand|finaliz[ae]az[aă] comand|złóż zamów|zloz zamow|place order|confirm order|submit order)",
    re.I,
)


def load_ic():
    spec = importlib.util.spec_from_file_location("ic_mod", str(MAPAREA))
    if spec is None or spec.loader is None:
        raise RuntimeError("Nu pot încărca intercars_scan_coduri.py")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def compact(text: str, limit: int = 160) -> str:
    return re.sub(r"\s+", " ", (text or "")).strip()[:limit]


def digits(value: str) -> str:
    return re.sub(r"\D+", "", value or "")


def extract_lines_from_json(payload) -> list[dict]:
    if isinstance(payload, str):
        try:
            payload = json.loads(payload)
        except Exception:
            return []
    if not isinstance(payload, dict) and not isinstance(payload, list):
        return []
    rows = payload
    if isinstance(payload, dict):
        for key in ("items", "lines", "cartItems", "cart_items", "products", "Positions", "data"):
            if isinstance(payload.get(key), list):
                rows = payload[key]
                break
        else:
            rows = [payload]
    lines = []
    if not isinstance(rows, list):
        return lines
    for row in rows:
        if not isinstance(row, dict):
            continue
        ident = str(
            row.get("id")
            or row.get("Id")
            or row.get("itemId")
            or row.get("cartItemId")
            or row.get("lineId")
            or row.get("positionId")
            or row.get("uid")
            or ""
        ).strip()
        code = str(
            row.get("towKod")
            or row.get("productCode")
            or row.get("code")
            or row.get("index")
            or row.get("manufacturerNumber")
            or ""
        ).strip()
        if ident == "" and code == "":
            continue
        lines.append({
            "id": ident or code,
            "code": code,
            "qty": int(row.get("qty") or row.get("quantity") or 1),
        })
    return lines


def first_visible(page, selectors: list[str]):
    for sel in selectors:
        try:
            el = page.query_selector(sel)
            if el and el.is_visible():
                return el
        except Exception:
            continue
    return None


def click_first(page, selectors: list[str]) -> str:
    el = first_visible(page, selectors)
    if el is None:
        return ""
    try:
        label = compact(el.inner_text() or el.get_attribute("aria-label") or sel_name(el))
        if SUBMIT_RE.search(label):
            return ""
        el.click(timeout=4000)
        return label or "clicked"
    except Exception:
        return ""


def sel_name(el) -> str:
    try:
        return el.get_attribute("class") or el.get_attribute("data-testid") or "el"
    except Exception:
        return "el"


def looks_like_cart_api(url: str) -> bool:
    u = (url or "").lower()
    return any(part in u for part in ("cart", "basket", "koszyk", "checkout", "order"))


def pick_authority(page) -> str:
    url = page.url or ""
    if "select-authority" not in url and "authority" not in url:
        return ""
    clicked = click_first(page, [
        'button:has-text("R86")',
        'a:has-text("R86")',
        'button:has-text("Timișoara")',
        'button:has-text("Timisoara")',
        '[data-code="R86"]',
        '[data-branch="R86"]',
        'button:has-text("R39196")',
        'a:has-text("R39196")',
    ])
    if clicked:
        page.wait_for_timeout(2000)
        return clicked
    try:
        cards = page.query_selector_all('button, a, [role="button"], li')
        for el in cards[:40]:
            text = compact(el.inner_text() or "")
            if any(token in text.upper() for token in ("R86", "R39196", "TIMISOARA", "TIMIȘOARA")):
                el.click(timeout=4000)
                page.wait_for_timeout(2000)
                return text
        if cards:
            first = cards[0]
            label = compact(first.inner_text() or "authority")
            if not SUBMIT_RE.search(label):
                first.click(timeout=4000)
                page.wait_for_timeout(2000)
                return label
    except Exception:
        return ""
    return ""


def hybris_add_to_cart(page, codes: list[str]) -> str:
    wanted = []
    for code in codes:
        text = (code or "").strip()
        if text != "" and text not in wanted:
            wanted.append(text)
    try:
        from_page = page.evaluate(
            """() => {
                const out = [];
                const add = (v) => {
                    const s = String(v || '').trim();
                    if (s && !out.includes(s)) out.push(s);
                };
                document.querySelectorAll('[data-product-code], [data-productcode], [data-code], [data-towkod]').forEach((el) => {
                    add(el.getAttribute('data-product-code') || el.getAttribute('data-productcode') || el.getAttribute('data-code') || el.getAttribute('data-towkod'));
                });
                const html = document.documentElement.innerHTML;
                const re = /"(?:productCode|towKod|code)"\\s*:\\s*"([A-Za-z0-9._\\- ]{3,32})"/g;
                let m;
                while ((m = re.exec(html))) add(m[1]);
                return out.slice(0, 12);
            }"""
        )
        url = page.url or ""
        if "search" in url or "oferta" in url.lower():
            for extra in from_page or []:
                if extra not in wanted:
                    wanted.append(str(extra))
    except Exception:
        pass
    for code in wanted:
        try:
            result = page.evaluate(
                """async (productCode) => {
                    const csrf = (window.ACC && ACC.config && ACC.config.CSRFToken)
                        || document.querySelector('input[name="CSRFToken"]')?.value
                        || document.querySelector('input[name="_csrf"]')?.value
                        || document.querySelector('meta[name="_csrf"]')?.content
                        || '';
                    const paths = ['/ro/cart/add', '/cart/add'];
                    let last = {ok:false, status:0, text:'', csrf: !!csrf, path:''};
                    for (const path of paths) {
                        const body = new URLSearchParams({productCode, qty: '1'});
                        if (csrf) body.set('CSRFToken', csrf);
                        const res = await fetch(path, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                            credentials: 'same-origin',
                        });
                        const text = await res.text();
                        last = {ok: res.ok, status: res.status, text: text.slice(0, 400), csrf: !!csrf, path, productCode};
                        if (res.ok) return last;
                    }
                    return last;
                }""",
                code,
            )
            if isinstance(result, dict) and result.get("ok"):
                captured_note = compact(str(result.get("productCode") or code) + " via " + str(result.get("path") or "cart/add"))
                return captured_note
        except Exception:
            continue
    return ""


def extract_lines_from_html(html: str) -> list[dict]:
    lines = []
    for match in re.finditer(
        r'data-(?:entry-number|entrynumber|cart-item-id|item-id)="([^"]+)"[^>]*.{0,240}?data-(?:product-code|productcode|code)="([^"]+)"',
        html or "",
        re.I | re.S,
    ):
        lines.append({"id": match.group(1), "code": match.group(2), "qty": 1})
    if lines:
        return lines
    for match in re.finditer(r'"entryNumber"\s*:\s*(\d+).{0,180}"productCode"\s*:\s*"([^"]+)"', html or "", re.S):
        lines.append({"id": match.group(1), "code": match.group(2), "qty": 1})
    return lines


def search_on_page(page, query: str) -> bool:
    query = (query or "").strip()
    if query == "":
        return False
    click_first(page, [
        'button[aria-label*="search" i]',
        'button[aria-label*="caut" i]',
        'button[title*="search" i]',
        'button[title*="caut" i]',
        '[data-testid*="search"]',
        '.js-site-search-toggle',
        'button:has-text("Caut")',
    ])
    page.wait_for_timeout(800)
    click_first(page, [
        'button:has-text("DA")',
        'button:has-text("Accept")',
        '#onetrust-accept-btn-handler',
    ])
    box = first_visible(page, [
        'input[name="query"]',
        'input[placeholder*="index" i]',
        'input#js-site-search-input',
        'input[type="search"]',
        'input[name="q"]',
        'input[name="text"]',
        'input[name="search"]',
        'input[placeholder*="Caut" i]',
        'input[placeholder*="Search" i]',
        'input[placeholder*="Szuk" i]',
        'input[aria-label*="search" i]',
        'input[aria-label*="caut" i]',
    ])
    if box is None:
        return False
    try:
        box.click()
        box.fill(query)
        page.wait_for_timeout(1200)
        option = first_visible(page, [
            '[role="option"]',
            'li[id*="downshift"]',
            '[data-testid*="suggestion"]',
        ])
        if option is not None:
            option.click()
        else:
            box.press("Enter")
        page.wait_for_timeout(3500)
        return True
    except Exception:
        return False


def run(mode: str, code: str, query: str, qty: int) -> dict:
    ic = load_ic()
    query = (query or code or "").strip()
    code = (code or query).strip()
    captured_urls: list[str] = []
    captured_lines: list[dict] = []
    steps = []

    def note(name: str, ok: bool, detail: str) -> None:
        steps.append({"name": name, "ok": ok, "detail": detail})

    sync_playwright = ic.load_playwright()
    with sync_playwright() as pw:
        ctx = ic.open_browser(pw, headless=False, profile=PROFILE)
        page = ctx.pages[0] if ctx.pages else ctx.new_page()

        def on_response(resp):
            try:
                url = resp.url or ""
                if "intercars" not in url:
                    return
                method = resp.request.method.upper()
                if method in ("GET", "POST", "PUT", "PATCH") and looks_like_cart_api(url):
                    captured_urls.append(f"{method} {int(resp.status or 0)} {url}")
                ctype = resp.headers.get("content-type") or ""
                if "json" in ctype:
                    captured_lines.extend(extract_lines_from_json(resp.text()))
            except Exception:
                pass

        ctx.on("response", on_response)
        try:
            page.goto(ic.ECAT_HOME, wait_until="domcontentloaded", timeout=60000)
            ic.wait_past_cloudflare(page, 25)
            if ic.cloudflare_wall(page) or ic.needs_login(page):
                note("open_ecat", False, "Cloudflare sau login e-Cat blochează simularea.")
                return result_payload(False, "", False, steps, captured_lines, captured_urls)
            pick_authority(page)
            note("open_ecat", True, compact(page.url))

            before = list(captured_lines)
            opened_cart = False
            for cart_url in CART_URLS:
                try:
                    page.goto(cart_url, wait_until="domcontentloaded", timeout=30000)
                    ic.wait_past_cloudflare(page, 12)
                    if "404" not in (page.title() or "") and "not found" not in (page.title() or "").lower():
                        opened_cart = True
                        break
                except Exception:
                    continue
            if not opened_cart:
                click_first(page, CART_LINK_SELECTORS)
                page.wait_for_timeout(1500)
            note("list_remote_cart", True, "Coș remote deschis: " + compact(page.url))

            if mode in ("add", "rehearse"):
                found_search = False
                for home in HOME_URLS:
                    try:
                        page.goto(home, wait_until="domcontentloaded", timeout=45000)
                        ic.wait_past_cloudflare(page, 15)
                        pick_authority(page)
                        page.wait_for_timeout(2000)
                        if "not found" not in (page.title() or "").lower():
                            found_search = True
                            break
                    except Exception:
                        continue
                typed = search_on_page(page, query) or search_on_page(page, code)
                page.wait_for_timeout(2500)
                if "p/" not in (page.url or "") and "product" not in (page.url or ""):
                    for code_try in (code, query, digits(query)):
                        if not code_try:
                            continue
                        opened_product = False
                        for tpl in PRODUCT_URLS:
                            try:
                                page.goto(tpl.format(code=quote(code_try)), wait_until="domcontentloaded", timeout=25000)
                                if "not found" not in (page.title() or "").lower() and "nu a fost" not in (page.title() or "").lower():
                                    opened_product = True
                                    break
                            except Exception:
                                continue
                        if opened_product:
                            break
                note(
                    "search_product",
                    found_search or typed or "p/" in (page.url or ""),
                    compact(page.url) + (" · căutare în pagină" if typed else ""),
                )
                added = click_first(page, ADD_SELECTORS)
                if added == "":
                    added = hybris_add_to_cart(page, [code, query, digits(query)])
                if added == "":
                    try:
                        card = page.query_selector('[data-product-code], [data-towkod], article, .product-card')
                        if card:
                            btn = card.query_selector("button")
                            if btn:
                                label = compact(btn.inner_text())
                                if not SUBMIT_RE.search(label):
                                    btn.click(timeout=4000)
                                    added = label or "card-button"
                    except Exception:
                        added = ""
                note("add_own_line", added != "", added or "Nu am găsit butonul de adăugare în coș.")
                page.wait_for_timeout(2500)
                try:
                    page.goto("https://ro.e-cat.intercars.eu/ro/cart", wait_until="domcontentloaded", timeout=30000)
                    page.wait_for_timeout(1500)
                    captured_lines.extend(extract_lines_from_html(page.content()))
                except Exception:
                    pass

            line_id = ""
            after = captured_lines
            before_ids = {str(row.get("id") or "") for row in before}
            for row in after:
                ident = str(row.get("id") or "")
                row_code = str(row.get("code") or "")
                if ident and ident not in before_ids:
                    line_id = ident
                    break
                if ident and (digits(row_code) == digits(code) or digits(row_code) == digits(query) or row_code.upper() == code.upper()):
                    line_id = ident
            isolated = line_id != ""
            note(
                "isolate_own_line",
                isolated,
                ("Lucrăm doar linia " + line_id) if isolated else "Linia adăugată nu a primit încă ID din e-Cat.",
            )

            if mode in ("preview", "rehearse"):
                preview_ok = False
                for chk in CHECKOUT_URLS:
                    try:
                        page.goto(chk, wait_until="domcontentloaded", timeout=30000)
                        ic.wait_past_cloudflare(page, 10)
                        title = (page.title() or "").lower()
                        if "404" not in title and "not found" not in title:
                            preview_ok = True
                            break
                    except Exception:
                        continue
                if not preview_ok:
                    preview_ok = click_first(page, [
                        'a[href*="checkout"]',
                        'button:has-text("Checkout")',
                        'a:has-text("Checkout")',
                        'button:has-text("Continu")',
                        'a:has-text("Continu")',
                    ]) != ""
                blocked = False
                try:
                    if SUBMIT_RE.search(page.content() or ""):
                        blocked = True
                except Exception:
                    blocked = False
                note(
                    "preview_checkout",
                    preview_ok,
                    "Checkout deschis, fără trimitere." if preview_ok else "Pagina de checkout nu a fost găsită.",
                )
                note("stop_before_submit", True, "Butonul final de comandă nu a fost apăsat." + (" Butonul există pe pagină." if blocked else ""))

            return result_payload(isolated or mode == "list", line_id, isolated, steps, after, captured_urls)
        finally:
            try:
                ctx.remove_listener("response", on_response)
            except Exception:
                pass
            ctx.close()


def result_payload(ok: bool, line_id: str, isolated: bool, steps: list, lines: list, urls: list) -> dict:
    return {
        "ok": ok,
        "success": ok,
        "submitted": False,
        "line_id": line_id,
        "isolated": isolated,
        "lines": lines[-40:],
        "urls": urls[-40:],
        "steps": steps,
        "error": "" if ok else next((s["detail"] for s in reversed(steps) if not s["ok"]), "Simularea Intercars a eșuat."),
    }


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    ap = argparse.ArgumentParser()
    ap.add_argument("--mode", choices=["list", "add", "preview", "rehearse"], default="rehearse")
    ap.add_argument("--code", default="")
    ap.add_argument("--query", default="")
    ap.add_argument("--qty", type=int, default=1)
    args = ap.parse_args()
    try:
        payload = run(args.mode, args.code, args.query, max(1, args.qty))
    except Exception as exc:
        payload = result_payload(False, "", False, [
            {"name": "crash", "ok": False, "detail": compact(str(exc), 240)},
        ], [], [])
    print(json.dumps(payload, ensure_ascii=False))
    return 0 if payload.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())
