<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Cele 20 de reguli ale creierului central AI — context-master + roboți Besoiu.
 *
 * @see /admin/ai-agent — tab Centru
 */
final class AiCentralBrainRules
{
    /** @return list<array{id: int, icon: string, title: string, rule: string, zone: string, priority: string}> */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'icon' => '🎯',
                'title' => 'Sursă unică de adevăr',
                'rule' => 'Stoc, preț și disponibilitate vin DOAR din BD/TecDoc/furnizori — niciodată inventate de LLM.',
                'zone' => 'Catalog · Chat · WhatsApp',
                'priority' => 'P0',
            ],
            [
                'id' => 2,
                'icon' => '🧭',
                'title' => 'Router înainte de răspuns',
                'rule' => 'context-master = strat global; apoi specialist (catalog, comenzi, import…) după zonă și evenimente Live.',
                'zone' => 'AiAgentRouter · Live',
                'priority' => 'P0',
            ],
            [
                'id' => 3,
                'icon' => '🔍',
                'title' => 'Decode înainte de generare',
                'rule' => 'Mesaj haos → plan JSON (action, intent, search_terms) → abia apoi răspuns sau căutare catalog.',
                'zone' => 'ShopChatMessageDecoder',
                'priority' => 'P0',
            ],
            [
                'id' => 4,
                'icon' => '📚',
                'title' => 'RAG obligatoriu la chat',
                'rule' => 'shop_chat_knowledge se injectează în prompt; fără potriviri → fallback reguli locale, nu fantezii.',
                'zone' => 'comunicare-chat · widget',
                'priority' => 'P0',
            ],
            [
                'id' => 5,
                'icon' => '⚖️',
                'title' => 'Roboți informativi',
                'rule' => 'AI explică, caută, ghidează — NU decide discount, preț final sau stoc comercial (scope contract).',
                'zone' => 'Contract · Roboți',
                'priority' => 'P0',
            ],
            [
                'id' => 6,
                'icon' => '📡',
                'title' => 'Canal dual extern/intern',
                'rule' => 'Widget client (external) ≠ flux admin (internal); filtrează knowledge și runtime pe channel.',
                'zone' => 'shop_chat_knowledge',
                'priority' => 'P1',
            ],
            [
                'id' => 7,
                'icon' => '👁️',
                'title' => 'Supervizor observă',
                'rule' => 'Cron monitorizează catalog, import, tokeni, erori — raportează; autonomia = propuneri, nu acțiuni fără OK.',
                'zone' => 'Supervizor · Autonomy',
                'priority' => 'P1',
            ],
            [
                'id' => 8,
                'icon' => '🧩',
                'title' => 'Specialiști mici',
                'rule' => 'Fiecare agent = folder propriu (prompt + context + runtime); nu un singur prompt uriaș pentru tot.',
                'zone' => 'robot/data/ai_agents',
                'priority' => 'P1',
            ],
            [
                'id' => 9,
                'icon' => '🪙',
                'title' => 'Buget tokeni zilnic',
                'rule' => 'Metro LLM + limită zilnică; peste 80% avertisment; epuizat → reguli locale + Ollama fallback.',
                'zone' => 'TokenBudget · Setări',
                'priority' => 'P1',
            ],
            [
                'id' => 10,
                'icon' => '📝',
                'title' => 'Evenimente → memorie',
                'rule' => 'Acțiuni admin/client → AiActionEventService → core.json; arhivare orară pentru istoric.',
                'zone' => 'Live · Cron ai_agent_cycle',
                'priority' => 'P1',
            ],
            [
                'id' => 11,
                'icon' => '🎯',
                'title' => 'Marker DOM → RAG',
                'rule' => 'Operator marchează UI → salvare button_doc cu tag dom_marker → antrenare chat și admin.',
                'zone' => 'DOM Marker · comunicare-chat',
                'priority' => 'P1',
            ],
            [
                'id' => 12,
                'icon' => '🗂️',
                'title' => 'TecDoc ≠ CMS categorii',
                'rule' => 'api_categorii = facete produse; categorii_endpoint = CMS admin — nu amesteca nodeId TecDoc cu subcategorie BD.',
                'zone' => 'Site · Catalog',
                'priority' => 'P0',
            ],
            [
                'id' => 13,
                'icon' => '🔑',
                'title' => 'Identitate produs',
                'rule' => 'URL public = randomn_id; status 0 = ascuns; id numeric ≠ idprodus ERP Laravel.',
                'zone' => 'produse · ERP',
                'priority' => 'P0',
            ],
            [
                'id' => 14,
                'icon' => '🛒',
                'title' => 'Coș până la checkout',
                'rule' => 'localStorage.besoiu_cart — comanda în admin DOAR după confirmare checkout, nu la „Adaugă în coș”.',
                'zone' => 'cart-admin.js',
                'priority' => 'P1',
            ],
            [
                'id' => 15,
                'icon' => '💬',
                'title' => '3 variante preț WhatsApp',
                'rule' => 'Economic / Mediu / Premium din stoc real + link plată — nu un singur preț inventat.',
                'zone' => 'Robot WhatsApp',
                'priority' => 'P1',
            ],
            [
                'id' => 16,
                'icon' => '✅',
                'title' => 'Verificare output',
                'rule' => 'Înainte de listă produse: preamble „Am înțeles: …” + termeni căutare — clientul vede ce ai dedus.',
                'zone' => 'Chat test · widget',
                'priority' => 'P1',
            ],
            [
                'id' => 17,
                'icon' => '📊',
                'title' => 'Prag încredere decode',
                'rule' => 'confidence < 0.5 → clarify (întrebare scurtă); ≥ 0.72 → căutare directă; altfel refine.',
                'zone' => 'Decoder · Test mesaj',
                'priority' => 'P1',
            ],
            [
                'id' => 18,
                'icon' => '🛠️',
                'title' => 'Repair cu aprobare',
                'rule' => 'Composer Repair: auto_execute=false implicit; min_confidence 0.72; max 5 iteme per ciclu.',
                'zone' => 'Supervizor · Ops',
                'priority' => 'P2',
            ],
            [
                'id' => 19,
                'icon' => '📖',
                'title' => 'Jurnal → bibliotecă RAG',
                'rule' => 'Linii utile din learned.md → context.library.jsonl per agent; promovare manuală operator.',
                'zone' => 'Editor · Bibliotecă',
                'priority' => 'P2',
            ],
            [
                'id' => 20,
                'icon' => '🤝',
                'title' => 'Om în buclă la scope',
                'rule' => 'Funcții noi majore (facturare automată, decizii comerciale) = acord adițional — AI propune, omul decide.',
                'zone' => 'Contract · Centru AI',
                'priority' => 'P0',
            ],
        ];
    }

    /** Text compact pentru manual_context context-master. */
    public static function manualContextMarkdown(): string
    {
        $lines = ["# Creier central — 20 reguli Besoiu\n"];
        foreach (self::all() as $r) {
            $lines[] = sprintf(
                '%d. **%s** — %s _(zonă: %s · %s)_',
                $r['id'],
                $r['title'],
                $r['rule'],
                $r['zone'],
                $r['priority']
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /** @return array{p0: int, p1: int, p2: int, total: int} */
    public static function stats(): array
    {
        $p0 = $p1 = $p2 = 0;
        foreach (self::all() as $r) {
            match ($r['priority']) {
                'P0' => ++$p0,
                'P1' => ++$p1,
                default => ++$p2,
            };
        }

        return ['p0' => $p0, 'p1' => $p1, 'p2' => $p2, 'total' => count(self::all())];
    }
}
