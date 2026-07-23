# Creier central — 20 reguli Besoiu

1. **Sursă unică de adevăr** — Stoc, preț și disponibilitate vin DOAR din BD/TecDoc/furnizori — niciodată inventate de LLM. _(zonă: Catalog · Chat · WhatsApp · P0)_
2. **Router înainte de răspuns** — context-master = strat global; apoi specialist (catalog, comenzi, import…) după zonă și evenimente Live. _(zonă: AiAgentRouter · Live · P0)_
3. **Decode înainte de generare** — Mesaj haos → plan JSON (action, intent, search_terms) → abia apoi răspuns sau căutare catalog. _(zonă: ShopChatMessageDecoder · P0)_
4. **RAG obligatoriu la chat** — shop_chat_knowledge se injectează în prompt; fără potriviri → fallback reguli locale, nu fantezii. _(zonă: comunicare-chat · widget · P0)_
5. **Roboți informativi** — AI explică, caută, ghidează — NU decide discount, preț final sau stoc comercial (scope contract). _(zonă: Contract · Roboți · P0)_
6. **Canal dual extern/intern** — Widget client (external) ≠ flux admin (internal); filtrează knowledge și runtime pe channel. _(zonă: shop_chat_knowledge · P1)_
7. **Supervizor observă** — Cron monitorizează catalog, import, tokeni, erori — raportează; autonomia = propuneri, nu acțiuni fără OK. _(zonă: Supervizor · Autonomy · P1)_
8. **Specialiști mici** — Fiecare agent = folder propriu (prompt + context + runtime); nu un singur prompt uriaș pentru tot. _(zonă: robot/data/ai_agents · P1)_
9. **Buget tokeni zilnic** — Metro LLM + limită zilnică; peste 80% avertisment; epuizat → reguli locale + Ollama fallback. _(zonă: TokenBudget · Setări · P1)_
10. **Evenimente → memorie** — Acțiuni admin/client → AiActionEventService → core.json; arhivare orară pentru istoric. _(zonă: Live · Cron ai_agent_cycle · P1)_
11. **Marker DOM → RAG** — Operator marchează UI → salvare button_doc cu tag dom_marker → antrenare chat și admin. _(zonă: DOM Marker · comunicare-chat · P1)_
12. **TecDoc ≠ CMS categorii** — api_categorii = facete produse; categorii_endpoint = CMS admin — nu amesteca nodeId TecDoc cu subcategorie BD. _(zonă: Site · Catalog · P0)_
13. **Identitate produs** — URL public = randomn_id; status 0 = ascuns; id numeric ≠ idprodus ERP Laravel. _(zonă: produse · ERP · P0)_
14. **Coș până la checkout** — localStorage.besoiu_cart — comanda în admin DOAR după confirmare checkout, nu la „Adaugă în coș”. _(zonă: cart-admin.js · P1)_
15. **3 variante preț WhatsApp** — Economic / Mediu / Premium din stoc real + link plată — nu un singur preț inventat. _(zonă: Robot WhatsApp · P1)_
16. **Verificare output** — Înainte de listă produse: preamble „Am înțeles: …” + termeni căutare — clientul vede ce ai dedus. _(zonă: Chat test · widget · P1)_
17. **Prag încredere decode** — confidence < 0.5 → clarify (întrebare scurtă); ≥ 0.72 → căutare directă; altfel refine. _(zonă: Decoder · Test mesaj · P1)_
18. **Repair cu aprobare** — Composer Repair: auto_execute=false implicit; min_confidence 0.72; max 5 iteme per ciclu. _(zonă: Supervizor · Ops · P2)_
19. **Jurnal → bibliotecă RAG** — Linii utile din learned.md → context.library.jsonl per agent; promovare manuală operator. _(zonă: Editor · Bibliotecă · P2)_
20. **Om în buclă la scope** — Funcții noi majore (facturare automată, decizii comerciale) = acord adițional — AI propune, omul decide. _(zonă: Contract · Centru AI · P0)_