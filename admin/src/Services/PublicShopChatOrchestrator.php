<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Super AI Chat magazin public — cele 20 capabilități (identitate, VIN, 3 prețuri, comandă, supervisor).
 */
final class PublicShopChatOrchestrator
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getDB();
        $this->ensureTables();
    }

    public static function create(): self
    {
        return new self();
    }

    public function handleInit(array $input): array
    {
        try {
            $visitorKey = $this->visitorKey($input);
            $profile = $this->loadOrCreateProfile($visitorKey, $input);
            $shopUser = $this->shopSessionUser();
            if ($shopUser !== null) {
                $profile = $this->mergeShopCustomer($profile, $shopUser);
            }

            $cart = $this->loadPersistedCart($visitorKey, (int) ($profile['id'] ?? 0));
            $onboarding = new ShopChatOnboardingService();
            $sessionState = ['client_name' => (string) ($profile['client_name'] ?? '')];
            if ($shopUser !== null && ($shopUser['name'] ?? '') !== '') {
                ShopChatSessionService::setClientName((string) $shopUser['name'], $sessionState);
                ShopChatSessionService::setOnboardingStep('done', $sessionState);
            } elseif (($profile['client_name'] ?? '') !== '') {
                ShopChatSessionService::setOnboardingStep('done', $sessionState);
            }
            $contextGreeting = $onboarding->buildInitGreeting($profile, $sessionState, $shopUser);
            require_once dirname(__DIR__, 3) . '/robot/products_catalog.php';

            return [
                'ok' => true,
                'visitor_key' => $visitorKey,
                'profile' => $this->profilePublic($profile),
                'greeting' => $contextGreeting,
                'chips' => $onboarding->buildInitChips(),
                'cart' => $cart,
                'segment' => (string) ($profile['segment'] ?? 'unknown'),
                'tone' => $this->toneForSegment((string) ($profile['segment'] ?? 'unknown')),
                'logged_in' => $shopUser !== null,
                'onboarding_step' => ShopChatSessionService::onboardingStep($sessionState),
            ];
        } catch (Throwable) {
            require_once dirname(__DIR__, 3) . '/robot/products_catalog.php';
            $onboarding = new ShopChatOnboardingService();
            $sessionState = ShopChatSessionService::defaultState();
            $profile = ['client_name' => ''];

            return [
                'ok' => true,
                'visitor_key' => bin2hex(random_bytes(8)),
                'profile' => null,
                'greeting' => $onboarding->buildInitGreeting($profile, $sessionState, null),
                'chips' => $onboarding->buildInitChips(),
                'cart' => [],
                'segment' => 'unknown',
                'tone' => 'friendly',
                'logged_in' => false,
                'onboarding_step' => 'welcome',
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $catalog
     * @param array<string, mixed> $session
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function handleMessage(string $message, array $catalog, array &$session, array $input): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['handled' => false];
        }

        $visitorKey = $this->visitorKey($input);
        $profile = $this->loadOrCreateProfile($visitorKey, $input);
        $profile['message_count'] = (int) ($profile['message_count'] ?? 0) + 1;
        $this->saveProfile($profile);

        $shopUser = $this->shopSessionUser();
        if ($shopUser !== null) {
            $profile = $this->mergeShopCustomer($profile, $shopUser);
        }

        $segment = $this->detectSegment($message, $profile);
        if ($segment !== 'unknown' && ($profile['segment'] ?? 'unknown') === 'unknown') {
            $profile['segment'] = $segment;
            $this->saveProfile($profile);
        }

        $tone = $this->toneForSegment((string) ($profile['segment'] ?? 'unknown'));
        $session['profile_id'] = (int) ($profile['id'] ?? 0);
        $session['phone'] = (string) ($profile['phone'] ?? '');
        $session['segment'] = $profile['segment'] ?? 'unknown';

        if ($this->isConsentMessage($message)) {
            $profile['gdpr_consent'] = 1;
            $profile['gdpr_consent_at'] = date('Y-m-d H:i:s');
            $this->saveProfile($profile);
            return $this->wrap($this->applyTone('Perfect — îți păstrez istoricul conversațiilor 90 de zile. Cu ce piesă te ajut?', $tone), 'consent', $profile);
        }

        if ($this->isProfileCaptureMessage($message, $profile)) {
            $parsed = $this->parseContactFromMessage($message);
            if ($parsed['phone'] !== '') {
                $profile['phone'] = $parsed['phone'];
            }
            if ($parsed['name'] !== '') {
                $profile['client_name'] = $parsed['name'];
            }
            $crm = $this->lookupCrmByPhone($profile['phone']);
            if ($crm !== null) {
                $profile['client_name'] = $profile['client_name'] !== '' ? $profile['client_name'] : (string) ($crm['name'] ?? '');
                $session['crm_hint'] = $crm;
            }
            $this->saveProfile($profile);
            $name = (string) ($profile['client_name'] ?: 'prieten');
            return $this->wrap($this->applyTone("Mulțumesc, {$name}! Am salvat contactul. Ce piesă cauți?", $tone), 'profile', $profile);
        }

        if ($this->isOrderStatusQuery($message)) {
            $status = $this->lookupOrderStatus($message, $profile);
            if ($status !== null) {
                $this->logEvent($profile, 'order_status', 'crm', '', $message, $status);
                return $this->wrap($status['reply'], 'order_status', $profile, $status);
            }
            $phone = (string) ($profile['phone'] ?? '');
            $hint = $phone !== ''
                ? 'Nu găsesc o comandă recentă pe telefonul salvat. Spune numărul comenzii (ex. ORD-1234) sau lasă alt telefon.'
                : 'Pentru status comandă, spune numărul comenzii sau telefonul folosit la checkout.';
            return $this->wrap($this->applyTone($hint, $tone), 'order_status', $profile, ['chips' => ['WhatsApp', 'Las telefon']]);
        }

        if ($this->isHumanHandoff($message)) {
            $wa = $this->buildWhatsAppHandoff($session, $profile, $message);
            $this->logEvent($profile, 'whatsapp_handoff', 'handoff', '', $message);
            return $this->wrap($wa['reply'], 'whatsapp', $profile, $wa);
        }

        if ($this->isOrderConfirmSummary($message, $session)) {
            return $this->buildOrderSummaryStep($session, $profile, $tone);
        }

        $vin = $this->extractVin($message);
        if ($vin !== '') {
            return $this->handleVinMessage($vin, $message, $catalog, $session, $profile, $tone);
        }

        if ($this->detectOrderIntent($message)) {
            return $this->handleOrderIntent($session, $profile, $tone);
        }

        if ($this->detectGreetingOnly($message)) {
            $greeting = $this->buildPersonalGreeting($profile, $shopUser);
            $this->logEvent($profile, 'greeting', 'greeting', '', $message);
            $out = $this->wrap($greeting, 'greeting', $profile);
            if ((int) ($profile['message_count'] ?? 0) >= 3 && empty($profile['phone']) && empty($profile['gdpr_consent'])) {
                $out['ui_action'] = 'ask_contact';
                $out['chips'] = ['Las telefon', 'Continuă fără cont', 'WhatsApp'];
            }
            return $out;
        }

        $ollamaReply = $this->tryOllamaAgentReply($message, $session, $input, $profile, $tone);
        if ($ollamaReply !== null) {
            return $ollamaReply;
        }

        if (!$this->looksLikeProductQuery($message)) {
            return ['handled' => false];
        }

        return $this->handleProductSearch($message, $catalog, $session, $profile, $tone);
    }

    /** @param array<string, mixed> $input */
    public function savePersistedCart(array $input, array $cart): void
    {
        $visitorKey = $this->visitorKey($input);
        $profileId = (int) ($input['profile_id'] ?? 0);
        $json = json_encode($cart, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'INSERT INTO shop_chat_carts (visitor_key, profile_id, cart_json) VALUES (:vk, :pid, :cj)
             ON DUPLICATE KEY UPDATE profile_id = VALUES(profile_id), cart_json = VALUES(cart_json), updated_at = NOW()'
        );
        $stmt->execute(['vk' => $visitorKey, 'pid' => $profileId > 0 ? $profileId : null, 'cj' => $json]);
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $sessionState */
    public function scheduleFollowupIfNeeded(array $input, array $sessionState, array $cart): void
    {
        if ($cart === []) {
            return;
        }
        $profileId = (int) ($sessionState['profile_id'] ?? 0);
        $phone = (string) ($sessionState['phone'] ?? '');
        if ($profileId <= 0 || $phone === '') {
            return;
        }
        $visitorKey = $this->visitorKey($input);

        $check = $this->pdo->prepare(
            "SELECT id FROM shop_chat_followups WHERE profile_id = :pid AND status = 'pending' AND reason = 'chat_abandoned' LIMIT 1"
        );
        $check->execute(['pid' => $profileId]);
        if ($check->fetchColumn()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO shop_chat_followups (profile_id, visitor_key, phone, email, cart_json, reason, status, scheduled_at)
             VALUES (:pid, :vk, :ph, :em, :cj, :rs, :st, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
        );
        $stmt->execute([
            'pid' => $profileId,
            'vk' => $visitorKey,
            'ph' => $phone,
            'em' => '',
            'cj' => json_encode($cart, JSON_UNESCAPED_UNICODE),
            'rs' => 'chat_abandoned',
            'st' => 'pending',
        ]);
    }

    /** @return array<string, mixed> */
    public function supervisorStats(int $days = 7): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
        $events = $this->pdo->prepare(
            'SELECT event_type, handled_by, availability, COUNT(*) AS c FROM shop_chat_events WHERE created_at >= :s GROUP BY event_type, handled_by, availability'
        );
        $events->execute(['s' => $since]);
        $rows = $events->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totals = ['messages' => 0, 'in_stock' => 0, 'not_found' => 0, 'orders' => 0, 'handoffs' => 0];
        foreach ($rows as $r) {
            $c = (int) ($r['c'] ?? 0);
            $totals['messages'] += $c;
            if (($r['availability'] ?? '') === 'in_stock') {
                $totals['in_stock'] += $c;
            }
            if (($r['availability'] ?? '') === 'not_found') {
                $totals['not_found'] += $c;
            }
            if (($r['event_type'] ?? '') === 'order_intent') {
                $totals['orders'] += $c;
            }
            if (($r['event_type'] ?? '') === 'whatsapp_handoff') {
                $totals['handoffs'] += $c;
            }
        }

        $profiles = (int) $this->pdo->query('SELECT COUNT(*) FROM shop_chat_profiles WHERE last_seen_at >= ' . $this->pdo->quote($since))->fetchColumn();
        $pendingFu = (int) $this->pdo->query("SELECT COUNT(*) FROM shop_chat_followups WHERE status = 'pending'")->fetchColumn();

        return [
            'days' => $days,
            'since' => $since,
            'profiles_active' => $profiles,
            'events' => $rows,
            'totals' => $totals,
            'followups_pending' => $pendingFu,
            'conversion_hint' => $totals['messages'] > 0
                ? round(($totals['orders'] / $totals['messages']) * 100, 1) . '% chat→comandă'
                : '—',
        ];
    }

    /**
     * Adaugă tier-uri preț, upsell și profile la răspuns catalog (după intent plan).
     *
     * @param array<string, mixed> $searchResult
     * @param array<string, mixed> $session
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $catalog
     * @return array<string, mixed>
     */
    public function enrichCatalogResponse(array $searchResult, array &$session, array $input, array $catalog): array
    {
        try {
            $visitorKey = $this->visitorKey($input);
            $profile = $this->loadOrCreateProfile($visitorKey, $input);
            $tone = $this->toneForSegment((string) ($profile['segment'] ?? 'unknown'));
            $searchResult['profile'] = $this->profilePublic($profile);
            $searchResult['tone'] = $tone;
            if (!empty($searchResult['reply'])) {
                $searchResult['reply'] = $this->applyTone((string) $searchResult['reply'], $tone);
            }
        } catch (Throwable) {
            // profil opțional — chat funcționează fără tabele shop_chat_*
        }

        $products = is_array($searchResult['products'] ?? null) ? $searchResult['products'] : [];
        $primary = $products[0] ?? null;

        if ($primary !== null && !empty($searchResult['in_stock'])) {
            try {
                $inStock = array_values(array_filter($products, static fn ($p) => is_array($p) && !empty($p['in_stock'])));
                $searchResult['price_tiers'] = $this->buildThreePriceTiers($primary, $catalog, $inStock);
            } catch (Throwable) {
                $searchResult['price_tiers'] = [];
            }
        }
        if (!empty($session['cart'])) {
            try {
                $searchResult['upsell'] = $this->suggestUpsell($session['cart'], $catalog);
            } catch (Throwable) {
                $searchResult['upsell'] = [];
            }
        }
        $searchResult['chips'] = ShopChatIntentParserService::chipsFromCatalog($catalog);
        if ($products !== []) {
            $searchResult['sources'] = $this->sourcesFromProducts($products);
        }

        return $searchResult;
    }

    // ─── Product search, VIN, pricing ─────────────────────────────────────

    /**
     * @param list<array<string,mixed>> $catalog
     * @param array<string,mixed> $session
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private function handleProductSearch(string $message, array $catalog, array &$session, array $profile, string $tone): array
    {
        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';

        $browse = widget_catalog_is_browse_query($message);
        $meta = widget_catalog_search_meta($message);
        $topic = (string) ($meta['label'] ?? widget_catalog_query_label($message));
        $hits = widget_catalog_search_message($message, $catalog, $browse ? 8 : 8);
        $mapped = $this->mapProducts($hits);

        if ($mapped === []) {
            $alt = $this->findAlternativesInCatalog($message, $catalog, 5);
            if ($alt !== []) {
                $mapped = $alt;
                $search = [
                    'reply' => $this->applyTone(
                        "Nu am exact cererea, dar am alternative în stoc:\n" . $this->formatProductLines($alt),
                        $tone
                    ),
                    'in_stock' => true,
                    'availability' => 'alternative',
                    'products' => $alt,
                ];
            } else {
                $search = [
                    'reply' => $this->applyTone(
                        'Nu am găsit potriviri pentru „' . $topic . '”. Spune cod OEM, nume scurt (ex. filtru ulei) sau VIN — verific instant.',
                        $tone
                    ),
                    'in_stock' => false,
                    'availability' => 'not_found',
                    'products' => [],
                    'chips' => ['30204A', 'filtru ulei', 'placute frana', 'WhatsApp'],
                ];
            }
        } else {
            $inStock = array_values(array_filter($mapped, static fn ($p) => !empty($p['in_stock'])));
            if ($inStock === []) {
                $alt = $this->findAlternativesInCatalog($message, $catalog, 3);
                $search = [
                    'reply' => $this->applyTone(
                        'Am identificat piesa, dar nu e în stoc acum.' . ($alt !== [] ? ' Alternative:' . "\n" . $this->formatProductLines($alt) : ' Te contactăm când revine?'),
                        $tone
                    ),
                    'in_stock' => $alt !== [],
                    'availability' => $alt !== [] ? 'alternative' : 'out_of_stock',
                    'products' => $alt !== [] ? $alt : $mapped,
                ];
            } else {
                $needsClarify = count($inStock) > 3 && !$this->hasExplicitCode($message);
                $lines = $this->formatProductLines(array_slice($inStock, 0, $browse ? 5 : 3));
                if ($browse || count($inStock) > 1) {
                    $topicLabel = ($meta['intent'] ?? '') === 'engine' ? 'piese de motor' : $topic;
                    $reply = 'Am găsit piese pentru „' . $topicLabel . '” în stoc:' . "\n" . $lines;
                    $reply .= $needsClarify
                        ? "\n\nSunt mai multe variante — spune codul exact sau ce preferi."
                        : "\n\nSpune „vreau să comand” sau alege produsul de mai jos.";
                } else {
                    $reply = "Da, îl avem în stoc:\n" . $lines;
                    $reply .= "\n\nSpune „vreau să comand” sau apasă butonul de pe produs.";
                }
                $search = [
                    'reply' => $this->applyTone($reply, $tone),
                    'in_stock' => true,
                    'availability' => 'in_stock',
                    'products' => $inStock,
                ];
            }
        }

        $primary = ($search['products'][0] ?? null);
        $inStockForTiers = array_values(array_filter($search['products'] ?? [], static fn ($p) => is_array($p) && !empty($p['in_stock'])));
        $priceTiers = $primary !== null ? $this->buildThreePriceTiers($primary, $catalog, $inStockForTiers) : [];

        $session['last_products'] = $search['products'] ?? $mapped;
        if (!empty($search['in_stock']) && count($search['products'] ?? []) === 1) {
            $session['cart'] = [$search['products'][0]];
        }

        $upsell = [];
        if (!empty($session['cart'])) {
            $upsell = $this->suggestUpsell($session['cart'], $catalog);
        }

        $this->rememberMemory((int) ($profile['id'] ?? 0), 'last_query', $message);
        $this->logEvent($profile, 'product_search', 'catalog', (string) ($search['availability'] ?? ''), $message);

        return $this->wrap(
            (string) ($search['reply'] ?? ''),
            'catalog',
            $profile,
            [
                'ui_action' => !empty($search['in_stock']) ? 'products' : 'none',
                'availability' => (string) ($search['availability'] ?? ''),
                'products' => $search['products'] ?? [],
                'sources' => $this->sourcesFromProducts($search['products'] ?? []),
                'price_tiers' => $priceTiers,
                'upsell' => $upsell,
                'chips' => ShopChatIntentParserService::chipsFromCatalog($catalog),
            ]
        );
    }

    /**
     * @param list<array<string,mixed>> $catalog
     * @param array<string,mixed> $session
     * @param array<string,mixed> $profile
     */
    private function handleVinMessage(string $vin, string $message, array $catalog, array &$session, array $profile, string $tone): array
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/system/tecdoc_stock.php';

        $partQuery = preg_replace('/\b(vin\s*[:\s]?[A-HJ-NPR-Z0-9]{17})\b/i', '', $message) ?? $message;
        $filters = ['vin' => $vin, 'name' => trim($partQuery)];

        try {
            $result = tecdoc_public_search($filters);
        } catch (Throwable $e) {
            return $this->wrap($this->applyTone('Nu am putut decoda VIN-ul acum. Încearcă cu codul OEM sau sună-ne.', $tone), 'vin_error', $profile);
        }

        $vehicle = is_array($result['vehicle'] ?? null) ? $result['vehicle'] : [];
        $vehLabel = (string) ($vehicle['label'] ?? $vehicle['manu_name'] ?? 'vehicul');
        $products = is_array($result['products'] ?? null) ? $result['products'] : [];
        $mapped = [];

        foreach (array_slice($products, 0, 6) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped[] = [
                'randomn_id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['pName'] ?? ''),
                'code' => (string) ($row['code'] ?? $row['pCode'] ?? ''),
                'oem' => (string) ($row['oem'] ?? $row['pOem'] ?? ''),
                'price' => (string) ($row['price'] ?? $row['pPrice'] ?? ''),
                'price_num' => (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', (string) ($row['price'] ?? $row['pPrice'] ?? '0'))),
                'stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0),
                'in_stock' => (int) ($row['stock'] ?? $row['pStock'] ?? 0) > 0,
                'image' => (string) ($row['image'] ?? ''),
                'url' => !empty($row['randomn_id']) ? '/product.php?id=' . rawurlencode((string) $row['randomn_id']) : '',
            ];
        }

        if ($mapped === []) {
            $this->logEvent($profile, 'vin_search', 'vin', 'not_found', $message, ['vin' => $vin]);
            return $this->wrap(
                $this->applyTone("VIN decodat: {$vehLabel}. Nu am găsit piese potrivite în stoc pentru cererea ta — spune codul OEM sau piesa exactă.", $tone),
                'vin',
                $profile,
                ['vin' => $vin, 'vehicle' => $vehLabel, 'chips' => ['WhatsApp', 'Las telefon']]
            );
        }

        $session['last_products'] = $mapped;
        $session['vin'] = $vin;
        $session['vehicle'] = $vehLabel;
        $this->rememberMemory((int) ($profile['id'] ?? 0), 'last_vin', $vin);

        $lines = $this->formatProductLines(array_slice($mapped, 0, 3));
        $reply = "VIN: {$vehLabel}\nAm găsit în stoc:\n" . $lines;

        $this->logEvent($profile, 'vin_search', 'vin', 'in_stock', $message, ['vin' => $vin, 'count' => count($mapped)]);

        return $this->wrap($this->applyTone($reply, $tone), 'vin', $profile, [
            'ui_action' => 'products',
            'availability' => 'in_stock',
            'products' => $mapped,
            'sources' => $this->sourcesFromProducts($mapped),
            'vin' => $vin,
            'vehicle' => $vehLabel,
            'price_tiers' => isset($mapped[0]) ? $this->buildThreePriceTiers($mapped[0], $catalog, $mapped) : [],
            'chips' => ['Vreau sa comand', 'WhatsApp'],
        ]);
    }

    /** @param array<string,mixed> $primary @param list<array<string,mixed>> $catalog @param list<array<string,mixed>> $searchHits @return list<array<string,mixed>> */
    private function buildThreePriceTiers(array $primary, array $catalog, array $searchHits = []): array
    {
        $alternatives = $this->findPriceAlternatives($primary, $catalog, $searchHits);
        if (count($alternatives) < 2) {
            return [];
        }

        usort($alternatives, static fn ($a, $b) => ($a['price_num'] ?? 0) <=> ($b['price_num'] ?? 0));

        $distinct = [];
        $seen = [];
        foreach ($alternatives as $p) {
            $key = $this->tierProductKey($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $distinct[] = $p;
        }

        if (count($distinct) < 2) {
            return [];
        }

        $count = count($distinct);
        if ($count === 2) {
            return [
                [
                    'tier' => 'economic',
                    'label' => 'Variantă accesibilă',
                    'hint' => 'Cel mai mic preț din stoc',
                    'product' => $distinct[0],
                ],
                [
                    'tier' => 'premium',
                    'label' => 'Variantă premium',
                    'hint' => 'Alternativă mai scumpă',
                    'product' => $distinct[1],
                ],
            ];
        }

        $economic = $distinct[0];
        $premium = $distinct[$count - 1];
        $mediu = $distinct[(int) floor($count / 2)];

        if ($this->tierProductKey($mediu) === $this->tierProductKey($economic)
            || $this->tierProductKey($mediu) === $this->tierProductKey($premium)) {
            if ($this->tierProductKey($economic) === $this->tierProductKey($premium)) {
                return [];
            }

            return [
                [
                    'tier' => 'economic',
                    'label' => 'Variantă accesibilă',
                    'hint' => 'Cel mai mic preț',
                    'product' => $economic,
                ],
                [
                    'tier' => 'premium',
                    'label' => 'Variantă premium',
                    'hint' => 'Cel mai mare preț',
                    'product' => $premium,
                ],
            ];
        }

        return [
            ['tier' => 'economic', 'label' => 'Economic', 'hint' => 'Preț mic', 'product' => $economic],
            ['tier' => 'mediu', 'label' => 'Mediu', 'hint' => 'Echilibru calitate/preț', 'product' => $mediu],
            ['tier' => 'premium', 'label' => 'Premium', 'hint' => 'Calitate superioară', 'product' => $premium],
        ];
    }

    /** @param list<array<string,mixed>> $catalog @param list<array<string,mixed>> $searchHits @return list<array<string,mixed>> */
    private function findPriceAlternatives(array $primary, array $catalog, array $searchHits = []): array
    {
        $out = [];
        $seen = [];

        $push = function (array $p) use (&$out, &$seen): void {
            if ((int) ($p['stock'] ?? 0) <= 0 && empty($p['in_stock'])) {
                return;
            }
            $key = $this->tierProductKey($p);
            if ($key === '|0' || isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $p;
        };

        $push($primary);
        foreach ($searchHits as $p) {
            if (is_array($p)) {
                $push($p);
            }
        }

        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($primary['code'] ?? '')) ?? '');
        foreach ($catalog as $p) {
            if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
                continue;
            }
            $pCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($p['code'] ?? '')) ?? '');
            if ($code !== '' && $pCode === $code) {
                $push($this->mapProducts([$p])[0]);
            }
        }

        $hay = mb_strtolower((string) ($primary['name'] ?? ''), 'UTF-8');
        $peerRx = match (true) {
            (bool) preg_match('/\b(lubrif|ulei)\b/u', $hay) => '/\b(lubrif|ulei)\b/u',
            (bool) preg_match('/\bfiltru\b/u', $hay) => '/\bfiltru\b/u',
            (bool) preg_match('/\b(disc|frana|frână|placute|plăcuțe)\b/u', $hay) => '/\b(disc|frana|frână|placute|plăcuțe)\b/u',
            (bool) preg_match('/\b(rulment)\b/u', $hay) => '/\b(rulment)\b/u',
            default => null,
        };

        if ($peerRx !== null) {
            foreach ($catalog as $p) {
                if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
                    continue;
                }
                $n = mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8');
                if (preg_match($peerRx, $n)) {
                    $push($this->mapProducts([$p])[0]);
                }
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $p */
    private function tierProductKey(array $p): string
    {
        $id = (string) ($p['randomn_id'] ?? '');
        if ($id !== '') {
            return 'id:' . $id;
        }

        return 'code:' . (string) ($p['code'] ?? '') . '|' . (string) ($p['price_num'] ?? $p['price'] ?? '');
    }

    /** @param list<array<string,mixed>> $cart @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
    private function suggestUpsell(array $cart, array $catalog): array
    {
        $item = $cart[0] ?? null;
        if ($item === null) {
            return [];
        }
        $cat = mb_strtolower((string) ($item['name'] ?? ''), 'UTF-8');
        $hints = [];
        if (str_contains($cat, 'filtru') && str_contains($cat, 'ulei')) {
            $hints[] = ['label' => 'Garnitură baie ulei', 'example' => 'garnitura baie ulei'];
        } elseif (str_contains($cat, 'disc') || str_contains($cat, 'frana') || str_contains($cat, 'frână')) {
            $hints[] = ['label' => 'Plăcuțe frână pereche', 'example' => 'placute frana'];
        } elseif (str_contains($cat, 'ulei')) {
            $hints[] = ['label' => 'Filtru ulei', 'example' => 'filtru ulei'];
        }
        if ($hints === []) {
            return [];
        }

        return $hints;
    }

    // ─── Identity, CRM, orders ────────────────────────────────────────────

    /** @param array<string,mixed> $profile @return array<string,mixed> */
    private function buildPersonalGreeting(array $profile, ?array $shopUser): string
    {
        $name = '';
        if ($shopUser !== null) {
            $name = (string) ($shopUser['name'] ?? '');
        }
        if ($name === '') {
            $name = (string) ($profile['client_name'] ?? '');
        }

        $base = $name !== ''
            ? 'Salut, ' . trim(explode(' ', $name)[0]) . '! Cu ce te pot ajuta?'
            : 'Salut! Cu ce te pot ajuta?';

        $lastOrder = $this->lastOrderHint($profile);
        if ($lastOrder !== '') {
            $base .= ' ' . $lastOrder;
        }

        $base .= ' Spune cod OEM, nume piesă sau VIN — verific stocul instant.';

        return $base;
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $profile */
    private function buildContextualGreeting(array $input, array $profile): string
    {
        $page = (string) ($input['page'] ?? '');
        $productId = (string) ($input['product_id'] ?? '');
        $productName = (string) ($input['product_name'] ?? '');

        if ($productName !== '' || ($productId !== '' && str_contains($page, 'product'))) {
            $pn = $productName !== '' ? $productName : 'acest produs';
            return "Văd că te uiți la {$pn} — verific compatibilitatea sau stocul? Spune VIN-ul sau „vreau să comand”.";
        }

        return $this->buildPersonalGreeting($profile, $this->shopSessionUser());
    }

    /** @return array<string,mixed>|null */
    private function lookupCrmByPhone(string $phone): ?array
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT client_name, phone, email, order_number, order_status, created_at
                 FROM comenzi WHERE phone LIKE :ph ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['ph' => '%' . substr($phone, -9)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'name' => (string) ($row['client_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'last_order' => (string) ($row['order_number'] ?? ''),
                'last_status' => (string) ($row['order_status'] ?? ''),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $profile @return array<string,mixed>|null */
    private function lookupOrderStatus(string $message, array $profile): ?array
    {
        $orderNo = '';
        if (preg_match('/\b(ORD[-\s]?\d+|#\d{4,})\b/i', $message, $m)) {
            $orderNo = trim($m[1]);
        }
        $phone = $this->normalizePhone((string) ($profile['phone'] ?? ''));
        if ($phone === '' && preg_match('/(\+?4?0?\d{9,10})/', $message, $pm)) {
            $phone = $this->normalizePhone($pm[1]);
        }

        try {
            if ($orderNo !== '') {
                $stmt = $this->pdo->prepare('SELECT order_number, order_status, delivery_status, notes FROM comenzi WHERE order_number LIKE :on ORDER BY id DESC LIMIT 1');
                $stmt->execute(['on' => '%' . preg_replace('/[^0-9]/', '', $orderNo) . '%']);
            } elseif ($phone !== '') {
                $stmt = $this->pdo->prepare('SELECT order_number, order_status, delivery_status, notes FROM comenzi WHERE phone LIKE :ph ORDER BY id DESC LIMIT 1');
                $stmt->execute(['ph' => '%' . substr($phone, -9)]);
            } else {
                return null;
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $reply = sprintf(
                'Ultima comandă %s: status „%s”, livrare „%s”.',
                (string) ($row['order_number'] ?? '-'),
                (string) ($row['order_status'] ?? '-'),
                (string) ($row['delivery_status'] ?? '-')
            );

            return ['reply' => $reply, 'order' => $row];
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $profile */
    private function buildWhatsAppHandoff(array $session, array $profile, string $message): array
    {
        $parts = ['Bună! Continuare chat site Besoiu Piese Auto.'];
        if (!empty($profile['client_name'])) {
            $parts[] = 'Client: ' . $profile['client_name'];
        }
        if (!empty($session['vehicle'])) {
            $parts[] = 'VIN/vehicul: ' . $session['vehicle'];
        }
        foreach ($session['cart'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $parts[] = 'Piesă: ' . ($item['name'] ?? '') . ' ' . ($item['price'] ?? '') . ' RON';
        }
        $parts[] = 'Mesaj: ' . mb_substr($message, 0, 120);
        $text = implode(' | ', $parts);
        $phone = '40726498573';
        if (is_file(dirname(__DIR__, 3) . '/robot/company.json')) {
            $cj = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/robot/company.json'), true);
            $contact = (string) ($cj['terms']['contact'] ?? '');
            if (preg_match('/(\d[\d\s]{8,})/', $contact, $m)) {
                $digits = preg_replace('/\D+/', '', $m[1]);
                if ($digits !== '') {
                    $phone = $digits;
                }
            }
        }

        return [
            'reply' => 'Te redirecționez către coleg pe WhatsApp cu contextul conversației.',
            'whatsapp_url' => 'https://wa.me/' . $phone . '?text=' . rawurlencode($text),
            'ui_action' => 'whatsapp',
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /** @param array<string,mixed> $input */
    private function visitorKey(array $input): string
    {
        $sid = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($input['session_id'] ?? '')));
        if ($sid === '') {
            $sid = bin2hex(random_bytes(8));
        }

        return hash('sha256', 'bpa_chat_' . $sid);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function loadOrCreateProfile(string $visitorKey, array $input): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM shop_chat_profiles WHERE visitor_key = :vk LIMIT 1');
        $stmt->execute(['vk' => $visitorKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $row['last_page'] = (string) ($input['page'] ?? $row['last_page'] ?? '');
            $row['last_product_id'] = (string) ($input['product_id'] ?? $row['last_product_id'] ?? '');
            $this->pdo->prepare('UPDATE shop_chat_profiles SET last_page = :pg, last_product_id = :pid, last_seen_at = NOW() WHERE id = :id')
                ->execute(['pg' => $row['last_page'], 'pid' => $row['last_product_id'], 'id' => $row['id']]);

            return $row;
        }

        $ins = $this->pdo->prepare(
            'INSERT INTO shop_chat_profiles (visitor_key, session_key, last_page, last_product_id) VALUES (:vk, :sk, :pg, :pid)'
        );
        $ins->execute([
            'vk' => $visitorKey,
            'sk' => (string) ($input['session_id'] ?? ''),
            'pg' => (string) ($input['page'] ?? ''),
            'pid' => (string) ($input['product_id'] ?? ''),
        ]);

        $stmt->execute(['vk' => $visitorKey]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $profile */
    private function saveProfile(array $profile): void
    {
        if (empty($profile['id'])) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE shop_chat_profiles SET client_name = :cn, phone = :ph, email = :em, segment = :sg,
             gdpr_consent = :gc, gdpr_consent_at = :ga, message_count = :mc, shop_customer_id = :sc, updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'cn' => (string) ($profile['client_name'] ?? ''),
            'ph' => (string) ($profile['phone'] ?? ''),
            'em' => (string) ($profile['email'] ?? ''),
            'sg' => (string) ($profile['segment'] ?? 'unknown'),
            'gc' => (int) ($profile['gdpr_consent'] ?? 0),
            'ga' => $profile['gdpr_consent_at'] ?? null,
            'mc' => (int) ($profile['message_count'] ?? 0),
            'sc' => $profile['shop_customer_id'] ?? null,
            'id' => (int) $profile['id'],
        ]);
    }

    /** @param array<string,mixed> $shopUser @param array<string,mixed> $profile @return array<string,mixed> */
    private function mergeShopCustomer(array $profile, array $shopUser): array
    {
        $profile['shop_customer_id'] = (int) ($shopUser['id'] ?? 0);
        if (($profile['client_name'] ?? '') === '') {
            $profile['client_name'] = (string) ($shopUser['name'] ?? '');
        }
        if (($profile['email'] ?? '') === '') {
            $profile['email'] = (string) ($shopUser['email'] ?? '');
        }
        $this->saveProfile($profile);

        return $profile;
    }

    /** @return array<string,mixed>|null */
    private function shopSessionUser(): ?array
    {
        $auth = dirname(__DIR__, 3) . '/system/shop-auth.php';
        if (!is_file($auth)) {
            return null;
        }
        require_once $auth;

        return shop_auth_session_user();
    }

    /** @param array<string,mixed> $profile @return array<string,mixed> */
    private function profilePublic(array $profile): array
    {
        return [
            'visitor_key' => substr((string) ($profile['visitor_key'] ?? ''), 0, 12),
            'name' => (string) ($profile['client_name'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'segment' => (string) ($profile['segment'] ?? 'unknown'),
            'gdpr_consent' => !empty($profile['gdpr_consent']),
            'message_count' => (int) ($profile['message_count'] ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function loadPersistedCart(string $visitorKey, int $profileId): array
    {
        $stmt = $this->pdo->prepare('SELECT cart_json FROM shop_chat_carts WHERE visitor_key = :vk LIMIT 1');
        $stmt->execute(['vk' => $visitorKey]);
        $json = $stmt->fetchColumn();
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function rememberMemory(int $profileId, string $key, string $value): void
    {
        if ($profileId <= 0) {
            return;
        }
        try {
            $this->pdo->prepare(
                'INSERT INTO shop_chat_memory (profile_id, memory_key, memory_value, expires_at)
                 VALUES (:pid, :mk, :mv, DATE_ADD(NOW(), INTERVAL 90 DAY))
                 ON DUPLICATE KEY UPDATE memory_value = VALUES(memory_value), expires_at = VALUES(expires_at)'
            )->execute(['pid' => $profileId, 'mk' => $key, 'mv' => $value]);
        } catch (Throwable) {
            // optional
        }
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $extra */
    private function logEvent(array $profile, string $type, string $handledBy, string $availability, string $message, array $extra = []): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO shop_chat_events (profile_id, visitor_key, event_type, handled_by, availability, message_excerpt, meta_json)
                 VALUES (:pid, :vk, :et, :hb, :av, :me, :mj)'
            )->execute([
                'pid' => $profile['id'] ?? null,
                'vk' => (string) ($profile['visitor_key'] ?? ''),
                'et' => $type,
                'hb' => $handledBy,
                'av' => $availability,
                'me' => mb_substr($message, 0, 250),
                'mj' => $extra !== [] ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable) {
            // optional
        }
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $input @param array<string,mixed> $profile @return array<string,mixed>|null */
    private function tryOllamaAgentReply(string $message, array $session, array $input, array $profile, string $tone): ?array
    {
        if (!ShopChatOllamaAgentBridge::isEnabled()) {
            return null;
        }

        $routerSlug = trim((string) ($session['router_slug'] ?? $session['segment'] ?? 'catalog-stoc'));
        if ($routerSlug === '' || $routerSlug === 'unknown') {
            $routerSlug = 'catalog-stoc';
        }

        $history = is_array($session['history'] ?? null) ? $session['history'] : [];
        $messages = [];
        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }
            $role = (string) ($row['role'] ?? '');
            $content = trim((string) ($row['content'] ?? $row['message'] ?? ''));
            if ($role !== '' && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $pagePath = trim((string) ($input['page'] ?? $input['page_path'] ?? $profile['last_page'] ?? ''));
        $bridge = new ShopChatOllamaAgentBridge(dirname(__DIR__, 3));
        $result = $bridge->tryReply(
            $routerSlug,
            $message,
            $messages,
            $pagePath,
            [
                'channel' => 'shop_chat',
                'visitor_key' => $this->visitorKey($input),
                'phone' => (string) ($profile['phone'] ?? $session['phone'] ?? ''),
                'email' => (string) ($profile['email'] ?? ''),
            ]
        );

        if ($result === null || empty($result['ok']) || trim((string) ($result['content'] ?? '')) === '') {
            return null;
        }

        $agentSlug = (string) ($result['agent_slug'] ?? 'ollama');
        $this->logEvent($profile, 'ollama_agent', (string) ($result['handled_by'] ?? 'ollama_agent'), $agentSlug, $message, [
            'model' => (string) ($result['model'] ?? ''),
        ]);

        return $this->wrap(
            $this->applyTone(trim((string) $result['content']), $tone),
            (string) ($result['handled_by'] ?? 'ollama_agent'),
            $profile,
            [
                'agent_slug' => $agentSlug,
                'llm_provider' => (string) ($result['model'] ?? 'ollama'),
            ]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $extra @return array<string,mixed> */
    private function wrap(string $reply, string $handledBy, array $profile, array $extra = []): array
    {
        return array_merge([
            'handled' => true,
            'handled_by' => $handledBy,
            'reply' => $reply,
            'profile' => $this->profilePublic($profile),
            'tone' => $this->toneForSegment((string) ($profile['segment'] ?? 'unknown')),
        ], $extra);
    }

    private function applyTone(string $text, string $tone): string
    {
        if ($tone === 'workshop') {
            return $text . ' (factură firmă / curier — spune CUI dacă e cazul)';
        }
        if ($tone === 'mechanic') {
            return $text;
        }

        return $text;
    }

    private function toneForSegment(string $segment): string
    {
        return match ($segment) {
            'workshop' => 'workshop',
            'mechanic' => 'mechanic',
            default => 'driver',
        };
    }

    /** @param array<string,mixed> $profile */
    private function detectSegment(string $message, array $profile): string
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (preg_match('/\b(atelier|service|firm[aă]|cui|factur[aă]\s+firm|b2b|flot[aă])\b/u', $lower)) {
            return 'workshop';
        }
        if (preg_match('/\b(mecanic|service\s+auto|reparat|garaj)\b/u', $lower)) {
            return 'mechanic';
        }

        return (string) ($profile['segment'] ?? 'unknown');
    }

    /** @return list<array{id:string,label:string}|string> */
    private function defaultChips(array $profile): array
    {
        $chips = ShopChatIntentParserService::defaultChips();
        if (empty($profile['gdpr_consent'])) {
            $chips[] = ['id' => 'salveaza conversatia', 'label' => 'Salvează conversația'];
        }

        return $chips;
    }

    private function detectOrderIntent(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match(
            '/\b(vreau\s+(sa\s+|să\s+)?comand|confirm\s+comand|plasez\s+comand|da,\s*comand)\b/u',
            $lower
        );
    }

    private function detectGreetingOnly(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match('/^(salut|bun[aă]|hey|hello|hi|servus|noroc)[\s!.,?]*$/u', $lower);
    }

    private function looksLikeProductQuery(string $message): bool
    {
        if ($this->detectOrderIntent($message) || $this->detectGreetingOnly($message)) {
            return false;
        }
        require_once dirname(__DIR__, 3) . '/robot/oem_lib.php';
        if (fb_extract_oem_codes($message) !== []) {
            return true;
        }
        if ($this->extractVin($message) !== '') {
            return true;
        }
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(rulment|filtru|disc|placute|ambreiaj|baterie|ulei|piesa|piese|produse|motor|stoc|pret|aveti|aveți|caut|oem|catalog)\b/u',
            $lower
        );
    }

    private function extractVin(string $message): string
    {
        if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $message, $m)) {
            $vin = strtoupper($m[1]);
            require_once dirname(__DIR__, 3) . '/system/tecdoc_vin.php';
            if (tecdoc_is_vin_query($vin)) {
                return $vin;
            }
        }

        return '';
    }

    private function isOrderStatusQuery(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(status|unde\s+e|awb|comanda\s+mea|urmarire|urmare)\b/u', $lower);
    }

    private function isHumanHandoff(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(whatsapp|sunati|suna|coleg|om|operator|uman)\b/u', $lower);
    }

    private function isConsentMessage(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');

        return (bool) preg_match('/\b(salveaza\s+conversat|accept\s+gdpr|salveaza\s+istoric|salveaza\s+date|pastrezi\s+conversat|accept\s+sa\s+pastrezi)\b/u', $lower);
    }

    /** @param array<string,mixed> $profile */
    private function isProfileCaptureMessage(string $message, array $profile): bool
    {
        if ((string) ($profile['phone'] ?? '') !== '') {
            return false;
        }

        return (bool) preg_match('/(\+?4?0?\d{9,10})/', $message)
            || (bool) preg_match('/\b(las\s+telefon|numarul\s+meu|telefon)\b/ui', $message);
    }

    /** @return array{name:string,phone:string} */
    private function parseContactFromMessage(string $message): array
    {
        $phone = '';
        if (preg_match('/(\+?4?0?\d{9,10})/', $message, $m)) {
            $phone = $this->normalizePhone($m[1]);
        }

        return ['name' => '', 'phone' => $phone];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '4' . substr($digits, 1);
        }

        return $digits;
    }

    /** @param array<string,mixed> $session */
    private function isOrderConfirmSummary(string $message, array $session): bool
    {
        return (bool) preg_match('/\b(confirm|rezumat|corect\s+asa)\b/ui', $message)
            && !empty($session['cart']);
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $profile */
    private function buildOrderSummaryStep(array $session, array $profile, string $tone): array
    {
        $cart = is_array($session['cart'] ?? null) ? $session['cart'] : [];
        $lines = [];
        $total = 0.0;
        foreach ($cart as $item) {
            $p = (float) ($item['price_num'] ?? 0);
            $total += $p;
            $lines[] = '• ' . ($item['name'] ?? '') . ' — ' . ($item['price'] ?? '') . ' RON';
        }
        $reply = "Rezumat:\n" . implode("\n", $lines) . "\nTotal: " . number_format($total, 2, ',', '.') . " RON\nConfirmi comanda?";

        return $this->wrap($this->applyTone($reply, $tone), 'order_summary', $profile, [
            'ui_action' => 'order_confirm',
            'cart' => $cart,
            'chips' => ['Da, comand', 'Modifica', 'WhatsApp'],
        ]);
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $profile */
    private function handleOrderIntent(array $session, array $profile, string $tone): array
    {
        $cart = is_array($session['cart'] ?? null) ? $session['cart'] : [];
        $last = is_array($session['last_products'] ?? null) ? $session['last_products'] : [];
        if ($cart === [] && count($last) === 1 && !empty($last[0]['in_stock'])) {
            $cart = [$last[0]];
            $session['cart'] = $cart;
        }
        if ($cart === []) {
            return $this->wrap($this->applyTone('Spune ce piesă cauți (cod sau nume), apoi comanda.', $tone), 'order', $profile);
        }

        $this->logEvent($profile, 'order_intent', 'order', 'in_stock', 'order');

        return $this->wrap($this->applyTone('Completează formularul — preț din catalog live.', $tone), 'order', $profile, [
            'ui_action' => 'order_form',
            'cart' => $cart,
            'products' => $cart,
            'sources' => $this->sourcesFromProducts($cart),
            'upsell' => [],
        ]);
    }

    /** @param array<string,mixed> $profile */
    private function lastOrderHint(array $profile): string
    {
        $crm = $this->lookupCrmByPhone((string) ($profile['phone'] ?? ''));
        if ($crm === null || ($crm['last_order'] ?? '') === '') {
            return '';
        }

        return 'Ultima comandă: ' . $crm['last_order'] . ' (' . ($crm['last_status'] ?? '') . ').';
    }

    /** @param list<array<string,mixed>> $hits @return list<array<string,mixed>> */
    private function mapProducts(array $hits): array
    {
        require_once dirname(__DIR__, 3) . '/robot/public_shop_chat.php';

        return public_shop_chat_map_products($hits);
    }

    /** @param list<array<string,mixed>> $products */
    private function formatProductLines(array $products): string
    {
        $lines = [];
        foreach ($products as $p) {
            $line = '✓ ' . (string) ($p['name'] ?? '');
            if (!empty($p['code'])) {
                $line .= ' · ' . $p['code'];
            }
            if (!empty($p['price'])) {
                $line .= ' · ' . $p['price'] . ' RON';
            }
            $line .= ' · stoc ' . (int) ($p['stock'] ?? 0);
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** @param list<array<string,mixed>> $products @return list<array<string,mixed>> */
    private function sourcesFromProducts(array $products): array
    {
        $out = [];
        foreach ($products as $p) {
            $out[] = [
                'name' => $p['name'] ?? '',
                'price' => $p['price'] ?? '',
                'stock' => (int) ($p['stock'] ?? 0),
                'randomn_id' => $p['randomn_id'] ?? '',
                'image' => $p['image'] ?? '',
                'url' => $p['url'] ?? '',
                'in_stock' => !empty($p['in_stock']),
            ];
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
    private function findAlternativesInCatalog(string $message, array $catalog, int $limit): array
    {
        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
        require_once dirname(__DIR__, 3) . '/robot/oem_lib.php';
        $codes = fb_extract_oem_codes($message);
        $hits = [];
        foreach ($codes as $code) {
            foreach (fb_search_catalog_products($code, $catalog, 5) as $row) {
                $hits[] = $row;
            }
        }
        if ($hits === []) {
            require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
            $intent = widget_catalog_resolve_intent($message);
            if ($intent === 'engine') {
                $hits = widget_catalog_engine_search($catalog, $limit);
            } elseif ($intent === 'filter') {
                $hits = widget_catalog_filter_search($message, $catalog, $limit);
            } else {
                $hits = widget_catalog_search_message($message, $catalog, $limit);
            }
        }

        return array_slice($this->mapProducts($hits), 0, $limit);
    }

    private function hasExplicitCode(string $message): bool
    {
        require_once dirname(__DIR__, 3) . '/robot/oem_lib.php';

        return fb_extract_oem_codes($message) !== [];
    }

    private function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $migration = dirname(__DIR__, 2) . '/migrations/064_shop_chat_super.sql';
        if (!is_file($migration)) {
            $done = true;

            return;
        }
        try {
            $sql = file_get_contents($migration);
            if (is_string($sql)) {
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt === '' || str_starts_with($stmt, '--')) {
                        continue;
                    }
                    $this->pdo->exec($stmt);
                }
            }
        } catch (Throwable) {
            // runner manual
        }
        $done = true;
    }
}
