<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Catalog complet UI — butoane, filtre, select, carduri, chat AI.
 * Folosit la seedFullSystem() pentru antrenare RAG chat.
 */
final class ShopChatKnowledgeFullSeed
{
    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        $e = static fn (
            string $key,
            string $type,
            string $title,
            string $meaning,
            string $action,
            string $intent,
            string $response,
            array $examples = [],
            array $meta = [],
            int $priority = 70,
            string $channel = 'external',
        ): array => [
            'entry_type' => $type,
            'channel' => $channel,
            'title' => $title,
            'question_examples' => $examples,
            'expected_meaning' => $meaning,
            'expected_action' => $action,
            'expected_intent' => $intent,
            'expected_response' => $response,
            'metadata_json' => array_merge(['seed_key' => $key, 'source' => 'full_system_seed'], $meta),
            'tags_json' => array_values(array_unique(array_merge(
                explode('_', $key),
                [$meta['zone'] ?? '', $meta['element'] ?? '']
            ))),
            'priority' => $priority,
        ];

        return [
            // ── HOMEPAGE filterbar TecDoc ──
            $e('home_select_marca', 'button_doc', 'Home — Select marcă auto', 'Client alege marca vehiculului (VW, BMW, Dacia…).', 'filter', 'vehicle', 'Se încarcă modelele TecDoc pentru marca aleasă; lanțul filterbar avansează.', ['ce marca ai', 'selectez marca'], ['zone' => 'homepage', 'selector' => '#select_marca', 'element' => 'select'], 82),
            $e('home_select_categorie', 'button_doc', 'Home — Select categorie piese', 'Client alege categoria de piese (Frâne, Filtre, Ulei…).', 'filter', 'category', 'Filtrează produsele afișate în grid după pCategory din BD.', ['categorie frane', 'piese motor'], ['zone' => 'homepage', 'selector' => '#select_categorie', 'element' => 'select'], 80),
            $e('home_btn_search', 'button_doc', 'Home — CAUTĂ PIESĂ', 'Declanșează căutare cu filtrele vehicul + categorie setate.', 'search', 'general', 'API tecdoc_proxy get_articles intersectat cu stoc BD; afișează carduri în #_product-grid.', ['cauta piesa', 'apas cauta'], ['zone' => 'homepage', 'selector' => '#btnSearch', 'element' => 'button'], 85),
            $e('home_filter_vin', 'button_doc', 'Home — Căutare VIN / OEM', 'Client tastează VIN sau cod OEM în bara hero.', 'search', 'code', 'tecdoc_proxy search_stock — caută în produse după cod/name; poate returna compatibilitate.', ['vin', 'cod oem', '34116761244'], ['zone' => 'homepage', 'selector' => '#_filter-vin', 'element' => 'input'], 88),
            $e('home_apply_filters', 'button_doc', 'Home — Aplică filtre hero', 'Aplică textul din căutare hero pe grid produse.', 'search', 'general', 'Filtrează cardurile _product-card client-side + eventual re-fetch API.', [], ['zone' => 'homepage', 'selector' => '#_product-apply-filters', 'element' => 'button'], 75),
            $e('home_categories_popup', 'button_doc', 'Home — Popup categorii', 'Deschide arbore categorii din facete produse (api_categorii popup).', 'filter', 'category', 'Client alege categorie/subcategorie → setează filtre grid homepage.', ['categorii piese', 'deschide categorii'], ['zone' => 'homepage', 'element' => 'popup', 'api' => 'api_categorii.php?action=popup'], 78),

            // ── CATALOG sidebar ──
            $e('cat_filter_category', 'button_doc', 'Catalog — Filtru categorie', 'Click pe categorie în sidebar (Frâne, Suspensie…).', 'filter', 'category', 'Filtrează carduri data-category; URL ?category=; subcategorii per categorie.', ['filtru categorie', 'frane'], ['zone' => 'catalog', 'selector' => '.category-filter', 'element' => 'link'], 84),
            $e('cat_filter_subcategory', 'button_doc', 'Catalog — Filtru subcategorie', 'Subcategorie BD per categorie (nu TecDoc nodeId).', 'filter', 'category', 'Filtrează data-subcategory; fără intersect TecDoc greșit.', ['subcategorie', 'disc frana'], ['zone' => 'catalog', 'selector' => '.subcategory-filter', 'element' => 'link'], 83),
            $e('cat_filter_marca', 'button_doc', 'Catalog — Filtru marcă auto', 'Filtrează după pMarca + text name/desc/oem.', 'filter', 'vehicle', 'Carduri cu data-marca sau text compatibil.', ['marca vw', 'bmw'], ['zone' => 'catalog', 'selector' => '.marca-filter', 'element' => 'link'], 80),
            $e('cat_filter_brand', 'button_doc', 'Catalog — Filtru brand piesă', 'Producător piesă: Bosch, TRW, Ridex…', 'filter', 'general', 'Filtrează data-brand pe carduri produse.', ['brand bosch', 'producator'], ['zone' => 'catalog', 'selector' => '.brand-filter', 'element' => 'link'], 80),
            $e('cat_apply_filters', 'button_doc', 'Catalog — APLICĂ FILTRELE', 'Aplică toate filtrele sidebar pe grid.', 'filter', 'general', 'JS client-side pe _product-card; empty-state dacă zero rezultate.', ['aplica filtre'], ['zone' => 'catalog', 'selector' => '#applyFilters', 'element' => 'button'], 82),
            $e('cat_reset_filters', 'button_doc', 'Catalog — RESETEAZĂ filtre', 'Curăță toate filtrele sidebar și arată toate produsele active.', 'filter', 'general', 'Reset categorie/subcategorie/marcă/brand/preț; grid complet.', ['reset filtre', 'arata tot'], ['zone' => 'catalog', 'selector' => '#resetFilters', 'element' => 'button'], 76),
            $e('cat_orderby', 'button_doc', 'Catalog — Sortare produse', 'Select orderby: implicit, preț asc/desc, nume.', 'filter', 'refine', 'Reordonează cardurile vizibile în grid catalog.', ['sortare pret', 'cele mai ieftine'], ['zone' => 'catalog', 'selector' => '#orderby', 'element' => 'select'], 72),
            $e('cat_price_range', 'button_doc', 'Catalog — Slider preț', 'Interval preț min-max pe carduri.', 'filter', 'refine', 'Ascunde carduri în afara intervalului data-price.', ['sub 500 lei', 'pret maxim'], ['zone' => 'catalog', 'element' => 'range', 'selector' => '#filter-price-range'], 78),
            $e('cat_product_card', 'button_doc', 'Catalog — Card produs', 'Card _product-card cu imagine, preț, OEM, categorie.', 'search', 'general', 'Click → product.php?id=randomn_id; buton add-cart pe card.', ['produs', 'detalii piesa'], ['zone' => 'catalog', 'selector' => '._product-card', 'element' => 'card'], 85),

            // ── PAGINĂ PRODUS ──
            $e('prod_add_cart', 'button_doc', 'Produs — Adaugă în coș', 'Adaugă piesa în coș localStorage besoiu_cart.', 'order', 'general', 'Badge coș +1, popup confirmare, mini-cart; NU trimite comandă admin încă.', ['adauga in cos', 'cumpar'], ['zone' => 'product', 'selector' => '.add-cart, .prod-add-cart', 'element' => 'button'], 90),
            $e('prod_tab_desc', 'button_doc', 'Produs — Tab descriere', 'Afișează pNote HTML format TecDoc dl/dt/dd.', 'clarify', 'general', 'Descriere tehnică, OEM, specs din BD/TecDoc.', ['descriere', 'detalii tehnice'], ['zone' => 'product', 'selector' => '#product-tab-desc', 'element' => 'tab'], 70),
            $e('prod_tab_compat', 'button_doc', 'Produs — Tab compatibilitate', 'Listă vehicule compatibile din pCompatibilitati/TecDoc.', 'clarify', 'vehicle', 'Tabel modele/motorizări compatibile.', ['merge pe golf', 'compatibil cu'], ['zone' => 'product', 'selector' => '#product-tab-fitment', 'element' => 'tab'], 82),
            $e('prod_tab_specs', 'button_doc', 'Produs — Tab specificații', 'Specs scurt: diametru, montare, etc.', 'clarify', 'general', 'Grid specificații parse din pSpecs/pNote.', ['specificatii', 'dimensiuni'], ['zone' => 'product', 'selector' => '#product-tab-specs', 'element' => 'tab'], 68),

            // ── COȘ & CHECKOUT site ──
            $e('cart_badge', 'button_doc', 'Header — Icon coș + badge', 'Afișează număr produse din localStorage.', 'clarify', 'general', 'Click → cart.php; badge .cart-count actualizat instant.', ['cos cumparaturi', 'cate produse'], ['zone' => 'cart', 'selector' => '.cart-count, [data-cart-count]', 'element' => 'badge'], 75),
            $e('cart_checkout', 'button_doc', 'Coș — Finalizare comandă', 'Checkout confirmă → POST comenzi_endpoint → comandă în admin.', 'order', 'general', 'Golește coș; comandă nouă status noua în Besoiu Admin.', ['finalizez', 'plasez comanda site'], ['zone' => 'cart', 'element' => 'checkout', 'api' => 'comenzi_endpoint.php'], 92),
            $e('cart_mini_popup', 'button_doc', 'Coș — Popup mini după add', 'Confirmare „Produs adăugat” Continuă / Mergi la coș.', 'clarify', 'general', 'Nu creează comandă; doar feedback UX.', [], ['zone' => 'cart', 'element' => 'popup'], 70),

            // ── CHAT WIDGET ──
            $e('chat_fab', 'button_doc', 'Chat — Buton deschidere (FAB)', 'Deschide panoul widget chat verde.', 'greeting', 'general', 'init API → salut scurt fără chip-uri tehnice la sesiune nouă.', ['chat', 'ajutor', 'intrebare'], ['zone' => 'widget', 'selector' => '#bpa-fab, .bpa-fab', 'element' => 'button'], 88),
            $e('chat_input_send', 'button_doc', 'Chat — Input + Trimite', 'Mesaj liber client → decode AI → catalog/quiz.', 'search', 'general', 'Răspuns bot + products[] + chips[] + intent_decoded + price_tiers[].', ['caut filtru ulei', 'aveti placute'], ['zone' => 'widget', 'selector' => '#bpa-input, #bpa-send-btn', 'element' => 'input'], 95),
            $e('chat_quick_chip', 'button_doc', 'Chat — Chip sugestie (.bpa-quick-chip)', 'Buton rapid după răspuns bot; trimite text ca mesaj user.', 'refine', 'refine', 'Poate rafina listă (mai ieftin) sau căutare nouă (ulei).', ['mai ieftin', 'alt brand', 'sub 100'], ['zone' => 'widget', 'selector' => '.bpa-quick-chip', 'element' => 'button'], 86),
            $e('chat_product_card', 'button_doc', 'Chat — Card produs în chat', 'Afișează imagine, cod, stoc, preț RON din catalog.', 'search', 'general', 'Buton „Vreau să comand” pe card → quiz checkout.', ['ce pret', 'e pe stoc'], ['zone' => 'widget', 'selector' => '.bpa-product-card', 'element' => 'card'], 90),
            $e('chat_order_btn', 'button_doc', 'Chat — Vreau să comand (card)', 'Selectează produs în chatCart și pornește quiz.', 'order', 'general', 'Flux: confirm → livrare → plată → contact → plasare.', ['comand asta', 'il iau'], ['zone' => 'widget', 'selector' => '.bpa-product-card__btn', 'element' => 'button'], 93),
            $e('chat_price_tier', 'button_doc', 'Chat — Tier preț Economic/Mediu/Premium', '3 variante doar dacă ≥2 produse distincte.', 'search', 'general', 'Buton Alege → setează produs + mesaj vreau sa comand.', ['varianta ieftina', 'premium'], ['zone' => 'widget', 'selector' => '.bpa-tier__btn', 'element' => 'button'], 84),
            $e('chat_quiz_options', 'button_doc', 'Chat — Opțiuni quiz (livrare/plată)', 'Chip-uri pas quiz: ridicare locală, ramburs, etc.', 'order', 'general', 'ShopChatCheckoutQuizService avansează starea sesiunii.', ['ridicare locala', 'ramburs', 'card'], ['zone' => 'widget', 'element' => 'quiz_chip'], 88),
            $e('chat_order_form', 'button_doc', 'Chat — Formular plasare comandă', 'Nume, telefon, email → POST order în admin.', 'order', 'general', 'Succes: confirmare telefon; comandă channel=website/chat.', ['numar telefon', 'plaseaza comanda'], ['zone' => 'widget', 'selector' => '.bpa-order-form__submit', 'element' => 'button'], 91),
            $e('chat_whatsapp', 'button_doc', 'Chat — Continuă pe WhatsApp', 'Link wa.me cu context produs/conversație.', 'whatsapp', 'general', 'Escaladare la operator uman; nu plasează comandă automat.', ['whatsapp', 'vorbesc cu cineva'], ['zone' => 'widget', 'selector' => '.bpa-wa-btn, .bpa-wa-link', 'element' => 'link'], 80),
            $e('chat_verified_prefix', 'system_rule', 'Chat — Răspuns verificat AI', 'Prefix „Am înțeles: «…»” înainte de listă.', 'search', 'general', 'Arată clientului ce a decodat AI din mesaj haotic.', ['ai inteles', 'confirmare'], ['zone' => 'widget', 'service' => 'buildVerifiedPreamble'], 92),
            $e('chat_decode_ai', 'system_rule', 'Chat — Decoder mesaj haotic', 'Mesaje lungi/amestec → JSON plan: intent, search_terms, brand, preț.', 'search', 'general', 'ShopChatMessageDecoderService + RAG knowledge din admin.', ['mesaj liber', 'decode'], ['zone' => 'widget', 'service' => 'ShopChatMessageDecoderService'], 94),

            // ── Q&A fluxuri importante ──
            $e('qa_status_comanda', 'qa_pair', 'Unde e comanda mea?', 'Client întreabă status comandă.', 'order_status', 'general', 'Cere telefon/număr comandă; verifică în admin/comenzi.', ['unde e comanda', 'status comanda', 'a ajuns'], [], 85),
            $e('qa_stoc', 'qa_pair', 'E pe stoc?', 'Întrebare disponibilitate produs.', 'search', 'general', 'Răspuns cu stoc din card produs; stoc 0 → alternative.', ['e pe stoc', 'mai aveti', 's-a terminat'], [], 86),
            $e('qa_pret', 'qa_pair', 'Cât costă?', 'Client vrea preț pentru piesă/cod.', 'search', 'general', 'Afișează preț RON 2 zecimale din pPrice; tier-uri dacă multiple.', ['cat costa', 'ce pret', 'sub cat'], [], 84),
            $e('qa_compatibil', 'qa_pair', 'Merge pe mașina mea?', 'Compatibilitate vehicul.', 'search', 'vehicle', 'Folosește vehicle în sesiune + filtrare text compatibilitate.', ['merge pe', 'compatibil golf', 'pot pune pe'], [], 87),

            // ── REGULI sistem ──
            $e('rule_new_search', 'system_rule', 'Regulă: căutare nouă vs rafinare', '„dar aveti ulei” = search nou; „mai ieftin” = refine pe listă.', 'search', 'general', 'Decoder trebuie să nu confunde refine cu search nou.', ['dar aveti', 'mai ieftin'], [], 93),
            $e('rule_subcategory_no_node', 'system_rule', 'Regulă: subcategorie BD fără nodeId TecDoc', 'La pSubcategory setat nu intersecta nod TecDoc greșit.', 'filter', 'category', 'Evită zero rezultate false pe homepage/catalog.', [], [], 90),
            $e('rule_hidden_products', 'system_rule', 'Regulă: produse ascunse status=0', 'Nu afișa/nu recomanda produse cu status 0.', 'search', 'general', 'Filtru peste tot pe frontend și chat catalog.', [], [], 88),

            // ── ADMIN intern ──
            $e('admin_messages', 'button_doc', 'Admin — Mesagerie inbox', 'Inbox unificat conversații clienți.', 'clarify', 'general', 'Operator răspunde manual; template-uri reply-templates.', ['mesaje clienti'], ['zone' => 'admin', 'url' => '/admin/messages'], 65, 'internal'),
            $e('admin_chat_rag', 'button_doc', 'Admin — Chat antrenare RAG', 'Q&A, marker DOM, test mesaj, export context AI.', 'clarify', 'general', 'shop_chat_knowledge + Marker DOM → decoder AI.', ['antrenare chat', 'rag'], ['zone' => 'admin', 'url' => '/admin/comunicare-chat'], 70, 'internal'),
            $e('admin_marker_dom', 'button_doc', 'Admin — Marker DOM', 'Click pe elemente pagină → documentare → RAG.', 'clarify', 'general', 'createFromDomMarker salvare automată.', ['marker dom', 'documenteaza buton'], ['zone' => 'admin', 'element' => 'marker_tool'], 68, 'both'),
        ];
    }
}
