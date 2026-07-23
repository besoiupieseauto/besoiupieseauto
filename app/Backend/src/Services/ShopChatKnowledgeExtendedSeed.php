<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * +100 exemple RAG — chat extern (magazin/widget) + intern (admin).
 */
final class ShopChatKnowledgeExtendedSeed
{
    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        $H = [ShopChatKnowledgeSeedHelper::class, 'entry'];
        $out = [];

        // ── EXTERN: categorii piese (25) ──
        $categories = [
            ['ext_cat_suspensie', 'Suspensie / amortizoare', 'suspensie amortizor arc', 'search', 'suspension', 'Caut piese suspensie: amortizor, arc, pivot.'],
            ['ext_cat_ambreiaj', 'Ambreiaj / kit ambreiaj', 'ambreiaj kit disc presiune', 'search', 'general', 'Kit ambreiaj, disc, placa presiune după cod sau vehicul.'],
            ['ext_cat_distrib', 'Distribuție / curea', 'curea distributie kit distributie', 'search', 'engine', 'Curea/lant distribuție, rolă întinzător — intent engine.'],
            ['ext_cat_evacuare', 'Evacuare / eșapament', 'esapament catalizator furtun', 'search', 'general', 'Tobe, catalizator, garnituri evacuare.'],
            ['ext_cat_racire', 'Răcire / radiator', 'radiator termostat pompa apa', 'search', 'engine', 'Radiator, termostat, furtun antigel.'],
            ['ext_cat_electric', 'Electric / alternator', 'alternator demaror bujii bobina', 'search', 'general', 'Alternator, demaror, bujii, senzori.'],
            ['ext_cat_transmisie', 'Transmisie / cardan', 'transmisie cardan cv joint', 'search', 'general', 'Piese transmisie, planetare, cardan.'],
            ['ext_cat_directie', 'Direcție / cap bară', 'directie cap bara bileta', 'search', 'general', 'Cap bară, bieletă, cremalieră.'],
            ['ext_cat_clima', 'Climatizare / compresor AC', 'clima compresor freon condensator', 'search', 'general', 'Compresor AC, condensator, filtru habitaclu.'],
            ['ext_cat_caroserie', 'Caroserie / parbriz', 'parbriz oglinzi far stop', 'search', 'general', 'Parbriz, oglinzi, faruri, stopuri.'],
            ['ext_cat_filtru_aer', 'Filtru aer motor', 'filtru aer motor', 'search', 'filter', 'Filtru aer — exclude filtru ulei/combustibil.'],
            ['ext_cat_filtru_pol', 'Filtru polen habitaclu', 'filtru polen habitaclu', 'search', 'filter', 'Filtru habitaclu/polen.'],
            ['ext_cat_filtru_comb', 'Filtru combustibil', 'filtru combustibil motorina', 'search', 'filter', 'Filtru motorină/benzină — nu ulei.'],
            ['ext_cat_frana_disc', 'Disc frână', 'disc frana fata spate', 'search', 'brake', 'Discuri frână față/spate.'],
            ['ext_cat_frana_plac', 'Plăcuțe frână', 'placute frana set placute', 'search', 'brake', 'Set plăcuțe frână.'],
            ['ext_cat_rulment', 'Rulment roată', 'rulment roata butuc', 'search', 'general', 'Rulment roată/butuc — NU confunda cu rulment motor.'],
            ['ext_cat_ulei_cutie', 'Ulei cutie viteze', 'ulei cutie viteze', 'search', 'oil', 'Ulei transmisie/cutie — separat de ulei motor.'],
            ['ext_cat_baterie', 'Baterie auto', 'baterie 60ah 74ah', 'search', 'general', 'Baterii după Ah/dimensiuni.'],
            ['ext_cat_sterg', 'Ștergătoare parbriz', 'stergatoare parbriz lama', 'search', 'general', 'Lame ștergător după lungime.'],
            ['ext_cat_bec', 'Becuri / LED', 'bec h7 h4 led far', 'search', 'general', 'Becuri far, stop, LED.'],
            ['ext_cat_turbo', 'Turbo / intercooler', 'turbo intercooler', 'search', 'engine', 'Turbo, intercooler, furtun turbo.'],
            ['ext_cat_inject', 'Injectoare / pompa injecție', 'injector pompa injectie', 'search', 'engine', 'Injectoare, rampă, pompa HP.'],
            ['ext_cat_garnit', 'Garnituri motor', 'garnitura chiuloasa capac', 'search', 'engine', 'Garnitură chiuloasă, capac distribuție.'],
            ['ext_cat_ergonom', 'Accesorii interior', 'covorase husa volan', 'search', 'general', 'Accesorii — prioritate mică față de mecanică.'],
            ['ext_cat_universal', 'Piese universale / consumabile', 'antigel lichid frana spray', 'search', 'general', 'Consumabile: antigel, lichid frână, AD Blue.'],
        ];
        foreach ($categories as [$key, $title, $ex, $action, $intent, $resp]) {
            $out[] = $H($key, 'qa_pair', $title, "Client caută: {$title}.", $action, $intent, $resp, explode(' ', $ex), ['zone' => 'catalog'], 78);
        }

        // ── EXTERN: brand + categorie (12) ──
        foreach ([
            ['ext_br_bosch_frana', 'Bosch frână', 'bosch disc frana placute bosch', 'brake'],
            ['ext_br_trw_frana', 'TRW frână', 'trw placute disc trw', 'brake'],
            ['ext_br_mann_filtru', 'Mann filtru', 'mann filtru ulei aer mann', 'filter'],
            ['ext_br_mahle_filtru', 'Mahle filtru', 'mahle filtru ulei', 'filter'],
            ['ext_br_febi_susp', 'Febi suspensie', 'febi bileta cap bara', 'suspension'],
            ['ext_br_sachs_amb', 'Sachs ambreiaj', 'sachs ambreiaj kit', 'general'],
            ['ext_br_skf_rulment', 'SKF rulment', 'skf rulment roata', 'general'],
            ['ext_br_ridex_ieftin', 'Ridex variantă accesibilă', 'ridex ieftin buget', 'refine'],
            ['ext_br_glyco', 'Glyco cuzineti', 'glyco cuzineti biela', 'engine'],
            ['ext_br_elring', 'Elring garnituri', 'elring garnitura', 'engine'],
            ['ext_br_valeo', 'Valeo ambreiaj/electric', 'valeo ambreiaj alternator', 'general'],
            ['ext_br_continental', 'Continental curea', 'continental curea distributie', 'engine'],
        ] as [$key, $title, $ex, $intent]) {
            $out[] = $H($key, 'qa_pair', $title, "Client vrea brand specific: {$title}.", 'search', $intent, "Caut {$title} în stoc; filtrez pBrand.", explode(' ', $ex), ['zone' => 'search'], 76);
        }

        // ── EXTERN: vehicule populare RO (10) ──
        foreach ([
            ['ext_vw_golf', 'Golf', 'golf 5 golf 6 golf iv'],
            ['ext_dacia_logan', 'Dacia Logan / Sandero', 'logan sandero dacia'],
            ['ext_dacia_duster', 'Dacia Duster', 'duster dacia'],
            ['ext_bmw_e90', 'BMW seria 3', 'bmw e90 e91 seria 3'],
            ['ext_audi_a4', 'Audi A4', 'audi a4 b8'],
            ['ext_ford_focus', 'Ford Focus', 'focus ford'],
            ['ext_renault_clio', 'Renault Clio', 'clio renault'],
            ['ext_mercedes_w204', 'Mercedes C-Class', 'mercedes w204 c class'],
            ['ext_opel_astra', 'Opel Astra', 'astra opel h'],
            ['ext_peugeot_307', 'Peugeot 307/308', 'peugeot 307 308'],
        ] as [$key, $title, $ex]) {
            $out[] = $H($key, 'qa_pair', "Piese {$title}", "Compatibilitate vehicul {$title}.", 'search', 'vehicle', "Setez vehicle_hint {$title}; filtrez compatibilitate text.", explode(' ', $ex), ['zone' => 'vehicle'], 80);
        }

        // ── EXTERN: livrare, plată, retur (12) ──
        $commerce = [
            ['ext_livr_cat', 'Cât durează livrarea?', 'cat dureaza livrarea cand ajunge', 'clarify', 'general', 'Explic termen livrare: ridicare Utvin sau curier; NU inventa AWB.'],
            ['ext_livr_cost', 'Cost livrare', 'cat costa transportul livrare', 'clarify', 'general', 'Tarif fix sau ridicare locală gratuită — din setări site.'],
            ['ext_livr_ridic', 'Ridicare Utvin/Timiș', 'ridic de la sediu utvin', 'clarify', 'general', 'Opțiune ridicare_locală în checkout.'],
            ['ext_plat_ramb', 'Plata ramburs', 'ramburs la livrare numerar', 'clarify', 'order', 'Quiz/checkout: payment ramburs.'],
            ['ext_plat_card', 'Plata card', 'plata card online pos', 'clarify', 'order', 'Opțiuni card_fizic/card_online dacă active.'],
            ['ext_retur', 'Retur produs', 'returnez retur garantie', 'clarify', 'general', 'Trimite la pagina retur-garantie; fără decizie automat.'],
            ['ext_garant', 'Garantie piese', 'garantie cat timp', 'clarify', 'general', 'Info garanție conform furnizor/produs — redirect politică.'],
            ['ext_factura', 'Factură / bon', 'factura bon fiscal', 'clarify', 'general', 'Factură la comandă; date firmă opțional checkout.'],
            ['ext_reduc', 'Reducere / cupon', 'cupon reducere discount', 'clarify', 'general', 'Cod reducere în checkout site — chat informativ.'],
            ['ext_prog', 'Program / telefon', 'program telefon contact', 'clarify', 'general', 'Date contact din footer/CMS — NU inventa.'],
            ['ext_awb', 'Unde e coletul AWB?', 'awb curier urmarire', 'order_status', 'general', 'Cere număr AWB; status livrare din admin.'],
            ['ext_anul', 'Anulez comanda', 'anulez comanda sterg comanda', 'order_status', 'general', 'Escaladare operator; chat NU anulează singur.'],
        ];
        foreach ($commerce as [$key, $title, $ex, $action, $intent, $resp]) {
            $out[] = $H($key, 'qa_pair', $title, "Întrebare comercială: {$title}", $action, $intent, $resp, explode(' ', $ex), ['zone' => 'commerce'], 82);
        }

        // ── EXTERN: conversație & clarificări (8) ──
        foreach ([
            ['ext_multum', 'Mulțumesc / la revedere', 'multumesc ms pa la revedere', 'greeting', 'general', 'Răspuns scurt prietenos; oferă ajutor dacă mai are nevoie.'],
            ['ext_nu_inteleg', 'Nu înțeleg răspunsul', 'nu inteleg ce zici', 'clarify', 'general', 'Reformulează simplu; întreabă ce anume caută.'],
            ['ext_repet', 'Repetă / altfel', 'repeta altfel mai simplu', 'clarify', 'general', 'Reformulare scurtă fără jargon.'],
            ['ext_om', 'Vreau să vorbesc cu om', 'operator om agent real', 'whatsapp', 'general', 'Oferă WhatsApp sau telefon — escaladare.'],
            ['ext_urg', 'E urgent', 'urgent azi neaparat', 'clarify', 'general', 'Prioritizează telefon/WhatsApp; verifică stoc rapid.'],
            ['ext_gresit', 'Ați greșit produsul', 'gresit alt produs nu e asta', 'clarify', 'refine', 'Cere cod/OEM corect; căutare nouă.'],
            ['ext_mai_mult', 'Arată mai multe', 'mai multe optiuni alte variante', 'refine', 'refine', 'Extinde listă sau relaxează filtre.'],
            ['ext_primul', 'Primul / al doilea produs', 'primul al doilea produsul 2', 'refine', 'refine', 'Selectează din last_results după poziție.'],
        ] as [$key, $title, $ex, $action, $intent, $resp]) {
            $out[] = $H($key, 'qa_pair', $title, $title, $action, $intent, $resp, explode(' ', $ex), ['zone' => 'dialog'], 74);
        }

        // ── EXTERN: OEM / cod patterns (8) ──
        foreach ([
            ['ext_oem_bmw', 'Cod OEM BMW', '34116761244 bmw oem', 'search', 'code', 'Caut cod OEM BMW în pOem/pCode.'],
            ['ext_oem_vw', 'Cod VW/Audi', '1k0615301 vw audi', 'search', 'code', 'Cross-ref VW group în stoc.'],
            ['ext_oem_ren', 'Cod Renault/Dacia', '7700100100 renault', 'search', 'code', 'Cod Renault/Dacia în catalog.'],
            ['ext_ref_cruz', 'Referință încrucișată', 'echivalent inlocuitor cross ref', 'search', 'code', 'Caut echivalențe OEM; products_oem dacă există.'],
            ['ext_cod_gresit', 'Cod invalid / negăsit', 'codul nu exista nu gasiti', 'clarify', 'code', 'Sugerează verificare cod sau vehicul; search logs.'],
            ['ext_2cod', 'Două coduri în mesaj', 'am nevoie de X si Y', 'search', 'code', 'Extrage ambele oem_codes din mesaj.'],
            ['ext_fara_brand', 'Cod fără brand', 'cod 1234 fara marca', 'search', 'code', 'Caut cross-number; afișează brand din stoc.'],
            ['ext_dup_cod', 'Același cod mai multe branduri', 'acelasi cod bosch trw', 'search', 'code', 'Listează variante brand pentru același OEM.'],
        ] as [$key, $title, $ex, $action, $intent, $resp]) {
            $out[] = $H($key, 'qa_pair', $title, $title, $action, $intent, $resp, explode(' ', $ex), ['zone' => 'oem'], 86);
        }

        // ── EXTERN: reguli sistem (8) ──
        foreach ([
            ['ext_rule_pret_ron', 'Prețuri în RON', '', 'system_rule', 'general', 'Toate prețurile afișate RON cu 2 zecimale; fără EUR.'],
            ['ext_rule_nu_invent', 'NU inventa stoc/preț', '', 'system_rule', 'general', 'Doar date din BD; dacă lipsește — spune că verifici.'],
            ['ext_rule_gdpr', 'Date personale', '', 'system_rule', 'general', 'Telefon/email doar pentru comandă; fără stocare inutilă în chat.'],
            ['ext_rule_ro', 'Limba română', '', 'system_rule', 'general', 'Răspunsuri în română; corectează diacritice ușor.'],
            ['ext_rule_1intent', 'Un intent principal', '', 'system_rule', 'general', 'Mesaj haos → un plan JSON clar, nu 3 căutări amestecate.'],
            ['ext_rule_chips_dup', 'Fără chip-uri la init', '', 'system_rule', 'general', 'Salut init: chips[] gol — fără OEM random.'],
            ['ext_rule_refine_ctx', 'Refine necesită context', '', 'system_rule', 'refine', 'refine doar dacă last_products există în sesiune.'],
            ['ext_rule_order_ctx', 'Order necesită produs', '', 'system_rule', 'order', 'Quiz comandă: produs din card selectat sau last_results.'],
        ] as [$key, $title, $_ex, $type, $intent, $resp]) {
            $out[] = $H($key, $type, $title, $title, 'search', $intent, $resp, [], ['zone' => 'rules'], 90, 'both');
        }

        // ── INTERN admin: Q&A operator (15) ──
        foreach ([
            ['int_com_noua', 'Comandă nouă site', 'comanda noua website status', 'Comandă channel website în /admin/comenzi.', 'clarify', 'general', 'Verifică order_status noua; contact client.', 'internal'],
            ['int_com_aband', 'Coș abandonat', 'cos abandonat recuperare', 'Lead coș abandonat follow-up.', 'clarify', 'general', 'Modul abandoned-carts; template WhatsApp.', 'internal'],
            ['int_tpl_wa', 'Template WhatsApp', 'template whatsapp oferta', 'Răspuns rapid din reply-templates.', 'clarify', 'general', 'Aplică template + variabile client_name.', 'internal'],
            ['int_tpl_stoc', 'Template stoc disponibil', 'template stoc avem pe stoc', 'Confirmare stoc către client.', 'clarify', 'general', 'Template categorie stoc/oferte.', 'internal'],
            ['int_lead', 'Lead contact site', 'lead formular contact nou', 'Mesaj nou din formulare.', 'clarify', 'general', 'comunicare-leads inbox.', 'internal'],
            ['int_client_caut', 'Caut client după telefon', 'caut client telefon', 'CRM clienți după phone.', 'clarify', 'general', '/admin/clienti search telefon.', 'internal'],
            ['int_export', 'Export produse', 'export csv produse', 'Export catalog pentru marketplace.', 'clarify', 'general', '/admin/export — fără date sensibile.', 'internal'],
            ['int_import', 'Import CSV furnizor', 'import csv stoc pret', 'Pipeline import produse.', 'clarify', 'general', '/admin/import staging → publish.', 'internal'],
            ['int_awb_gen', 'Generare AWB', 'awb fancourier sameday', 'Modul livrare ERP.', 'clarify', 'general', 'Laravel/M1 livrare — AWB manual.', 'internal'],
            ['int_fact_smart', 'Factură SmartBill', 'factura smartbill', 'Emitere factură client.', 'clarify', 'general', 'Modul facturi — verificare date fiscale.', 'internal'],
            ['int_bot_wa', 'Robot WhatsApp', 'bot whatsapp raspuns automat', 'Flux bot informativ — fără decizii comerciale.', 'clarify', 'general', '/admin/bots/whatsapp monitor.', 'internal'],
            ['int_bot_pa', 'Scanner PieseAuto', 'pieseauto scanner', 'Marketplace robot.', 'clarify', 'general', '/admin/bots/pieseauto.', 'internal'],
            ['int_search_log', 'Search log negăsit', 'cautare negasita vin oem log', 'Extindere stoc din analiză.', 'clarify', 'general', '/admin/searchlogs — OEM/VIN lipsă.', 'internal'],
            ['int_err_sys', 'Eroare sistem', 'eroare red flag sistem', 'Jurnal erori admin.', 'clarify', 'general', '/admin/system-errors — tehnic.', 'internal'],
            ['int_perm', 'Permisiuni operator', 'permisiuni rol operator', 'RBAC admin — ce vede operatorul.', 'clarify', 'general', 'AdminPermissionCatalog pe rol.', 'internal'],
        ] as [$key, $title, $ex, $meaning, $action, $intent, $resp, $ch]) {
            $out[] = $H($key, 'qa_pair', $title, $meaning, $action, $intent, $resp, explode(' ', $ex), ['zone' => 'admin'], 72, $ch);
        }

        // ── INTERN: module admin button_doc (10) ──
        foreach ([
            ['int_btn_comenzi', 'Admin — Lista comenzi', 'comenzi site status', 'order', 'general', 'Filtrare status plată/livrare.', 'internal'],
            ['int_btn_produse', 'Admin — CRUD produse', 'edit produs ascunde vitrina', 'clarify', 'general', 'status 0 ascunde; randomn_id public.', 'internal'],
            ['int_btn_vitrina', 'Admin — Vitrina homepage', 'produs vitrina homepage', 'clarify', 'general', 'Flag vitrină pe produse selectate.', 'internal'],
            ['int_btn_categorii', 'Admin — CMS categorii', 'categorii cms admin', 'clarify', 'general', 'Tabel categorii ≠ facete api_categorii.', 'internal'],
            ['int_btn_adaos', 'Admin — Adaos comercial', 'adaos markup pret', 'clarify', 'general', 'Reguli markup la import CSV.', 'internal'],
            ['int_btn_cron', 'Admin — Cron sync', 'cron import sync', 'clarify', 'general', 'Joburi programate furnizori/imagine.', 'internal'],
            ['int_btn_alerts', 'Admin — Alerte ops', 'alerte notificari', 'clarify', 'general', 'Red flags migrări/API/quota.', 'internal'],
            ['int_btn_ai_agent', 'Admin — AI Agent', 'ai agent supervizor', 'clarify', 'general', 'Ciclu autonom monitorizare — nu chat client.', 'internal'],
            ['int_btn_scraper', 'Admin — Scraper', 'scraper epiesa import', 'clarify', 'general', 'Tool scraping concurență/stoc.', 'internal'],
            ['int_btn_workspace', 'Admin — Switch departament', 'workspace departament', 'clarify', 'general', 'Comunicare/Comenzi/Produse workspace.', 'internal'],
        ] as [$key, $title, $ex, $action, $intent, $resp, $ch]) {
            $out[] = $H($key, 'button_doc', $title, "Operator folosește: {$title}", $action, $intent, $resp, explode(' ', $ex), ['zone' => 'admin'], 68, $ch);
        }

        // ── BOTH: rag_chunk politici & flux (12) ──
        foreach ([
            ['rag_ident', 'Identitate magazin', 'both', 'BESOIU PIESE AUTO Utvin Timiș — piese auto B2C România.'],
            ['rag_tecdoc', 'TecDoc RapidAPI', 'both', 'Catalog TecDoc intersectat cu stoc local; cache 24h; quota flag.'],
            ['rag_doua_produse', 'Două tabele produse', 'both', 'Site: randomn_id; ERP Laravel: idprodus — NU amesteca.'],
            ['rag_cos_flow', 'Flux coș site', 'both', 'localStorage besoiu_cart → checkout → comenzi_endpoint → admin comenzi.'],
            ['rag_bot_info', 'Roboți informativi', 'both', 'Boți NU decid preț/stoc final — doar informează din DB.'],
            ['rag_livr_opt', 'Opțiuni livrare', 'both', 'ridicare_locala sau tarif_fix curier — din checkout.'],
            ['rag_plat_opt', 'Opțiuni plată', 'both', 'ramburs, numerar, card_fizic, card_online — configurabil.'],
            ['rag_chat_sess', 'Sesiune chat', 'both', 'visitor_key + session_key; profil shop_chat_profiles.'],
            ['rag_decode_flow', 'Flux decode mesaj', 'both', 'isMessyInput → decodeWithAi + RAG → catalog → verified preamble.'],
            ['rag_quiz_steps', 'Pași quiz chat', 'both', 'confirm produs → livrare → plată → date → submit order.'],
            ['rag_refine_ex', 'Exemple refine', 'both', '„mai ieftin”, „sub 200”, „doar bosch”, „al 3-lea” pe listă existentă.'],
            ['rag_escalad', 'Escaladare om', 'both', 'WhatsApp/telefon când client insistent sau comandă complexă.'],
        ] as [$key, $title, $ch, $text]) {
            $out[] = $H($key, 'rag_chunk', $title, $text, 'clarify', 'general', $text, [], ['zone' => 'architecture'], 85, $ch);
        }

        return $out;
    }
}
