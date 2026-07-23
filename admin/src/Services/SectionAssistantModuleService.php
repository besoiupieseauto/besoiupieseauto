<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Core\Comenzi\ComenziModel;
use Besoiu\Services\ComunicareHubService;
use Throwable;

/** Raspunsuri deterministe per modul admin (adaos, furnizori, vitrina, filtre, import, comenzi). */
final class SectionAssistantModuleService
{
    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    public function tryAnswer(string $message, string $section, array $context): ?array
    {
        $message = $this->normalizeComposerTypos($message);

        if (($codePlan = $this->tryProductCodeLookup($message)) !== null) {
            return $codePlan;
        }

        if ($this->wantsFullAdminCatalog($message)) {
            return $this->buildFullAdminCatalogAnswer($section, $context);
        }
        if (($sectionPlan = $this->trySectionLiveAnswer($message, $section, $context)) !== null) {
            return $sectionPlan;
        }
        if ($this->wantsAdaos($message)) {
            return $this->buildAdaosAnswer();
        }
        if ($this->wantsSupplierHowTo($message)) {
            return $this->buildSupplierHowToAnswer();
        }
        if ($this->wantsSupplierList($message)) {
            return $this->buildSupplierListAnswer();
        }
        if ($this->wantsSupplierCompare($message)) {
            return $this->buildSupplierCompareAnswer();
        }
        if ($this->wantsInvoiceList($message)) {
            return $this->buildInvoiceListAnswer();
        }
        if ($this->wantsAwbList($message)) {
            return $this->buildAwbListAnswer();
        }
        if ($this->wantsVitrina($message)) {
            return $this->buildVitrinaAnswer();
        }
        if ($this->wantsProductFilter($message)) {
            return $this->buildProductFilterAnswer($message);
        }
        if ($this->wantsProductList($message)) {
            return $this->buildProductListAnswer($message);
        }
        if ($this->wantsProductInventory($message)) {
            return $this->buildProductInventoryAnswer($message);
        }
        if ($this->wantsImportReadiness($message)) {
            return $this->buildImportReadinessAnswer();
        }
        if ($this->wantsImportOverview($message)) {
            return $this->buildImportQueueAnswer($message);
        }
        if ($this->wantsClientList($message)) {
            return $this->buildClientListAnswer($message);
        }
        if ($this->wantsClientOrders($message)) {
            return $this->buildClientOrdersAnswer($message);
        }
        if ($this->wantsCategoryList($message)) {
            return $this->buildCategoryListAnswer();
        }
        if ($this->wantsOrders($message)) {
            return $this->buildOrdersAnswer();
        }
        if ($this->wantsComunicare($message)) {
            return $this->buildComunicareAnswer();
        }
        if ($this->wantsWebsite($message)) {
            return $this->buildWebsiteAnswer();
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function tryProductCodeLookup(string $message): ?array
    {
        $code = SectionAssistantQueryHelper::extractProductCode($message);
        if ($code === null || !SectionAssistantQueryHelper::wantsProductCodeLookup($message)) {
            return null;
        }

        return $this->buildProductByCodeAnswer($code);
    }

    /** @return array<string, mixed> */
    public function buildProductByCodeAnswer(string $code): array
    {
        $items = SectionAssistantProductQueries::lookupByCode($code, 10);
        if ($items === []) {
            return [
                'source' => 'product_by_code',
                'reply_ro' => sprintf(
                    'Nu am gasit produs cu codul %s in catalog. Verifica in /admin/product sau import.',
                    $code
                ),
                'intent' => 'configure_products',
                'cheat_sheet' => [
                    'kind' => 'product_by_code',
                    'title' => 'Cautare cod produs',
                    'subtitle' => 'Cod: ' . $code,
                    'updated_at' => date('d.m.Y H:i'),
                    'stats' => [
                        ['key' => 'total', 'label' => 'Gasite', 'value' => '0'],
                    ],
                    'products' => [],
                    'products_note' => 'Niciun rezultat — codul poate fi in coada import sau sub alt OEM.',
                    'shortcuts' => [
                        ['label' => 'Lista produse', 'url' => '/admin/product'],
                        ['label' => 'Import', 'url' => '/admin/import'],
                    ],
                ],
                'suggestions' => [],
                'next_steps' => ['Verifica ortografia codului OEM', 'Cauta in importreview daca e in staging'],
            ];
        }

        $first = $items[0];
        $reply = count($items) === 1
            ? sprintf(
                '%s (%s) — pret %s, stoc %s.',
                (string) ($first['name'] ?? '-'),
                (string) ($first['brand'] ?? '-'),
                (string) ($first['price'] ?? '-'),
                (string) ($first['stock'] ?? '-')
            )
            : sprintf('%d produse gasite pentru codul %s.', count($items), $code);

        return [
            'source' => 'product_by_code',
            'reply_ro' => $reply,
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'product_by_code',
                'title' => 'Produs dupa cod',
                'subtitle' => 'Cod: ' . $code . ' — live SQL, fara LLM',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Gasite', 'value' => (string) count($items)],
                    ['key' => 'code', 'label' => 'Cod cautat', 'value' => $code],
                ],
                'products' => $items,
                'products_note' => count($items) === 1
                    ? 'Produs gasit — deschide edit pentru modificari.'
                    : 'Mai multe potriviri — verifica codul exact.',
                'shortcuts' => [
                    ['label' => 'Edit produs', 'url' => (string) ($first['edit_url'] ?? '/admin/product')],
                    ['label' => 'Lista produse', 'url' => '/admin/product'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => count($items) === 1
                ? ['Deschide edit produs pentru titlu/imagine', 'Verifica stoc si pret in furnizor']
                : ['Filtreaza dupa brand sau categorie', 'Scrie codul complet daca e ambiguu'],
        ];
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    public function tryAnswerRelaxed(string $message, string $section, array $context): ?array
    {
        $message = $this->normalizeComposerTypos($message);
        $plan = $this->tryAnswer($message, $section, $context);
        if ($plan !== null) {
            return $plan;
        }

        return $this->tryKeywordRouter($message, $section, $context);
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    public function dispatchByHandlerKey(string $handler, string $message, string $section, array $context): ?array
    {
        return match ($handler) {
            'admin_full_catalog' => $this->buildFullAdminCatalogAnswer($section, $context),
            'adaos_comercial' => $this->buildAdaosAnswer(),
            'supplier_list' => $this->buildSupplierListAnswer(),
            'supplier_compare' => $this->buildSupplierCompareAnswer(),
            'invoice_list' => $this->buildInvoiceListAnswer(),
            'awb_list' => $this->buildAwbListAnswer(),
            'cart_list' => $this->buildCartListAnswer(),
            'vitrina_homepage' => $this->buildVitrinaAnswer(),
            'import_queue' => $this->buildImportQueueAnswer($message),
            'import_readiness' => $this->buildImportReadinessAnswer(),
            'product_inventory' => $this->buildProductInventoryAnswer($message),
            'product_list' => $this->buildProductListAnswer($message),
            'product_filter' => $this->buildProductFilterAnswer($message),
            'product_by_code' => $this->buildProductByCodeAnswer(
                (string) (SectionAssistantQueryHelper::extractProductCode($message) ?? '')
            ),
            'comunicare_summary' => $this->buildComunicareAnswer(),
            'supplier_search' => $this->buildSupplierSearchAnswer(
                (string) (SectionAssistantQueryHelper::extractTermAfterPreposition($message) ?? '')
            ),
            'orders_summary' => $this->buildOrdersAnswer(),
            'category_list' => $this->buildCategoryListAnswer(),
            'client_orders' => $this->buildClientOrdersAnswer($message),
            'client_list' => $this->buildClientListAnswer($message),
            default => null,
        };
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    private function tryKeywordRouter(string $message, string $section, array $context): ?array
    {
        if ($this->wantsClientOrders($message)) {
            return $this->buildClientOrdersAnswer($message);
        }
        if ($this->wantsImportReadiness($message)) {
            return $this->buildImportReadinessAnswer();
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $routes = [
            'cart_list' => '/\b(cos|cart|cosul|cosuri)\b/u',
            'invoice_list' => '/\b(factur\w*|invoice\w*)\b/u',
            'awb_list' => '/\b(awb|curier|expedi\w*)\b/u',
            'supplier_list' => '/\b(furnizor\w*|supplier\w*)\b/u',
            'import_queue' => '/\b(import|coad[aă]|staging)\b/u',
            'vitrina_homepage' => '/\b(vitrin[aă]|homepage)\b/u',
            'adaos_comercial' => '/\b(adaos|markup|tva)\b/u',
            'category_list' => '/\b(categor\w*)\b/u',
            'product_filter' => '/\b(fara\s+imagine|fără\s+imagine|filtre\w*|badge|brand\s+\w+)\b/u',
            'product_list' => '/\b(lista|list[aă]|toate|enumera|arata|arat[aă])\b/u',
            'product_inventory' => '/\b(c[aâ]te|cite|inventar|rezumat|overview|statistici)\b/u',
            'comunicare_summary' => '/\b(mesaj\w*|comunicare|marketing|whatsapp|lead|broadcast)\b/u',
            'client_list' => '/\b(client\w*|clienti)\b/u',
            'orders_summary' => '/\b(comenz\w*|orders?)\b/u',
        ];

        foreach ($routes as $handler => $pattern) {
            if (!(bool) preg_match($pattern, $lower)) {
                continue;
            }
            if ($handler === 'cart_list' && !$this->wantsCartList($message)) {
                continue;
            }
            if ($handler === 'category_list' && !$this->wantsCategoryList($message)) {
                continue;
            }
            if ($handler === 'product_filter' && !$this->wantsProductFilter($message)) {
                continue;
            }
            if ($handler === 'product_list' && !$this->wantsProductList($message)) {
                continue;
            }
            if ($handler === 'product_inventory' && !$this->wantsProductInventory($message)) {
                continue;
            }
            if ($handler === 'comunicare_summary' && !$this->wantsComunicare($message)) {
                continue;
            }
            if ($handler === 'client_list' && !$this->wantsClientList($message)) {
                continue;
            }
            if ($handler === 'import_queue' && !$this->wantsImportOverview($message)) {
                continue;
            }
            if ($handler === 'orders_summary' && preg_match('/\b(factur\w*|awb)\b/u', $lower)) {
                continue;
            }
            if ($handler === 'orders_summary' && $this->wantsClientList($message)) {
                continue;
            }
            if ($handler === 'orders_summary' && $this->wantsClientOrders($message)) {
                $plan = $this->buildClientOrdersAnswer($message);
                if ($plan !== null) {
                    return $plan;
                }
            }
            $plan = $this->dispatchByHandlerKey($handler, $message, $section, $context);
            if ($plan !== null) {
                return $plan;
            }
        }

        if (preg_match('/\b(livr[aă]ri)\b/u', $lower) && preg_match('/\b(ce|c[aâ]te|generate|generat\w*)\b/u', $lower)) {
            return $this->buildAwbListAnswer();
        }

        if (preg_match('/\b(produs\w*)\b/u', $lower) && preg_match('/\b(fara|fără|filtre|brand|categor)\b/u', $lower)) {
            return $this->buildProductFilterAnswer($message);
        }

        if ($this->wantsProductInventory($message)) {
            return $this->buildProductInventoryAnswer($message);
        }

        if ($this->wantsComunicare($message)) {
            return $this->buildComunicareAnswer();
        }

        if ($this->wantsClientList($message)) {
            return $this->buildClientListAnswer($message);
        }

        return null;
    }

    private function wantsFullAdminCatalog(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if ((bool) preg_match('/\b(funct\w*|capabilit\w*|module|meniu|ce\s+poti|ce\s+poți)\b/u', $lower)
            && (bool) preg_match('/\b(tot|toate|full|complet\w*|admin|sistem|faze?|module)\b/u', $lower)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(toate\s+functi\w*|lista\s+completa|catalog\s+admin|ultim\w*\s+modul|toate\s+fazele)\b/u',
            $lower
        );
    }

    private function wantsAdaos(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(adaos|markup|tva|rotunj\w*\s+pret|formare\s+pret|price.?log|regul\w*\s+adaos)\b/u', $lower);
    }

    private function wantsSupplierHowTo(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(furnizor\w*|supplier\w*)\b/u', $lower)
            && (bool) preg_match('/\b(cum\s+(?:sa|să)|how\s+to|adaug[aă]?|inregistr|înregistr|configurez|configur)\b/u', $lower);
    }

    private function wantsSupplierList(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $hasSupplier = (bool) preg_match('/\b(furnizor\w*|supplier\w*)\b/u', $lower);
        $hasAsk = (bool) preg_match(
            '/\b(ce|c[aâ]te|care|lista|list[aă]|sunt|exist[aă]|acum[aă]?|pe\s+proiect|in\s+proiect|inregistrat\w*|configurat\w*|overview|total)\b/u',
            $lower
        );

        return $hasSupplier && $hasAsk;
    }

    private function wantsSupplierCompare(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(compar\w*\s+furnizor|furnizor\w*\s+compar|pozition\w*|ierarh\w*\s+furnizor|ordine\s+scan|scan_order|tier|hierarchical|autonet|intercars|autototal)\b/u',
            $lower
        );
    }

    private function wantsVitrina(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if ((bool) preg_match('/\b(vitrin[aă]|homepage|hero|gril[aă]\s+home)\b/u', $lower)) {
            return !(bool) preg_match('/\b(pune|adaug[aă]?|scoate|sterge)\b/u', $lower);
        }

        return false;
    }

    private function wantsImportQueueDetail(string $message): bool
    {
        return $this->wantsImportOverview($message);
    }

    private function wantsImportOverview(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $hasImport = (bool) preg_match(
            '/\b(coad[aă]\s+import|importreview|staging|pending|in\s+coad[aă]|import\s+produse|coad[aă])\b/u',
            $lower
        ) || (bool) preg_match('/\bimport\b/u', $lower);

        return $hasImport && SectionAssistantQueryHelper::hasLiveDataAsk($message);
    }

    private function wantsProductInventory(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        return SectionAssistantQueryHelper::resolveProductQueryMode($message) === 'summary';
    }

    private function wantsProductList(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        return SectionAssistantQueryHelper::resolveProductQueryMode($message) === 'list';
    }

    private function wantsProductFilter(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        return SectionAssistantQueryHelper::resolveProductQueryMode($message) === 'filter';
    }

    private function wantsCatalogStructure(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(categor\w*|subcategor\w*)\b/u', $lower)
            && (bool) preg_match('/\b(produse?|toate|lista|structur\w*)\b/u', $lower);
    }

    private function wantsInvoiceList(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(factur\w*|invoice\w*)\b/u', $lower)
            && (bool) preg_match(
                '/\b(ce|c[aâ]te|care|lista|list[aă]|sunt|exist[aă]|acum[aă]?|generate|generat\w*|avem)\b/u',
                $lower
            );
    }

    private function wantsAwbList(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(awb|livr[aă]ri|curier|expedi\w*)\b/u', $lower)
            && (bool) preg_match(
                '/\b(ce|c[aâ]te|care|lista|list[aă]|sunt|exist[aă]|acum[aă]?|generate|generat\w*|avem)\b/u',
                $lower
            );
    }

    private function wantsCartList(string $message): bool
    {
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        if (!(bool) preg_match('/\b(cos|cart|cosul|cosuri)\b/u', $lower)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(produs\w*|ce|c[aâ]te|care|lista|list[aă]|sunt|exist[aă]|avem|continut\w*|in\s+cos|din\s+cos)\b/u',
            $lower
        );
    }

    private function wantsOrders(string $message): bool
    {
        if ($this->wantsInvoiceList($message) || $this->wantsAwbList($message) || $this->wantsCartList($message)) {
            return false;
        }
        if ($this->wantsClientOrders($message)) {
            return false;
        }
        if ($this->wantsClientList($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        if (!(bool) preg_match('/\b(comenz\w*|comanda|orders?)\b/u', $lower)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(ce|c[aâ]te|cite|c[iî]te|care|sunt|exist[aă]|avem|acum|status|overview|rezumat|noi|sistem|in\s+sistem|din\s+sistem|d[aă]mi|dami|total)\b/u',
            $lower
        );
    }

    private function wantsCategoryList(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (!(bool) preg_match('/\b(categor\w*|subcategor\w*)\b/u', $lower)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(active|activ\w*|lista|list[aă]|ce|c[aâ]te|cite|c[iî]te|care|sunt|exist[aă]|avem|online|in\s+site|din\s+site|structur[aă])\b/u',
            $lower
        );
    }

    private function wantsClientOrders(string $message): bool
    {
        if ($this->wantsInvoiceList($message) || $this->wantsAwbList($message) || $this->wantsCartList($message)) {
            return false;
        }
        if ($this->wantsClientList($message)) {
            return false;
        }

        $name = $this->extractClientNameFromMessage($message);
        if ($name === null || mb_strlen($name, 'UTF-8') < 2) {
            return false;
        }
        if (SectionAssistantQueryHelper::isBoilerplateClientName($name)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');

        if ((bool) preg_match('/\b(comenz\w*|comanda|orders?|client\w*)\b/u', $lower)) {
            return true;
        }

        // «ce clienti sunt pentru Radu Galac», «date pentru X»
        if ((bool) preg_match('/\b(pentru|lui|de\s+la|al\s+lui)\b/u', $lower)
            && (bool) preg_match('/\b(ce|care|c[aâ]te|cite|sunt|exist[aă]|avem|date|detali\w*|info)\b/u', $lower)) {
            return true;
        }

        return false;
    }

    private function extractClientNameFromMessage(string $message): ?string
    {
        $name = null;

        if (preg_match(
            '/\b(?:pentru|lui|de\s+la|al\s+lui)\s+((?:[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*){0,4}))/u',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match(
            '/\b(?:client(?:ul)?|client\w*)\s+((?:[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*){0,4}))/u',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match(
            '/\bcomenz\w*\s+(?:lui|pentru|client(?:ul)?)\s+((?:[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*){0,4}))/iu',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match('/\b([A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[A-ZÀ-Ÿ][a-zà-ÿ]+)+)\b/u', $message, $m)) {
            $name = trim($m[1]);
        }

        if ($name === null || $name === '') {
            return null;
        }

        $name = $this->normalizeExtractedClientName($name);

        if ($name === '' || SectionAssistantQueryHelper::isBoilerplateClientName($name)) {
            return null;
        }

        return $name;
    }

    private function normalizeExtractedClientName(string $name): string
    {
        $name = SectionAssistantOrdersQueries::sanitizeClientQuery(trim($name));
        if (preg_match('/^(.+?)\s+(?:am|am\s+nevoie|sunt|care|ce|c[aâ]te|cu|sa|s[aă]|vreau|despre|orders?|comenz\w*)\b/iu', $name, $m)) {
            $name = trim($m[1]);
        }

        return SectionAssistantOrdersQueries::sanitizeClientQuery($name);
    }

    private function wantsClientList(string $message): bool
    {
        if ($this->wantsClientOrders($message)) {
            return false;
        }
        if (SectionAssistantActionService::messageLooksLikeAction($message)) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        if (!(bool) preg_match('/\b(client\w*|clienti|customers?)\b/u', $lower)) {
            return false;
        }

        return SectionAssistantQueryHelper::hasLiveDataAsk($message);
    }

    /** @return array<string, mixed> */
    private function buildClientListAnswer(string $message = ''): array
    {
        $search = SectionAssistantQueryHelper::extractTermAfterPreposition($message) ?? '';
        if ($search === '' && preg_match('/\bclient\w*\s+([A-Za-zÀ-ÿ0-9\-\.\s]{2,30})/u', $message, $m)) {
            $search = SectionAssistantQueryHelper::sanitize(trim($m[1]));
        }
        if ($search !== '' && SectionAssistantQueryHelper::isBoilerplateClientName($search)) {
            $search = '';
        }

        $total = SectionAssistantClientQueries::countClients();
        $clients = SectionAssistantClientQueries::listClients(30, $search);
        $shown = count($clients);

        $names = array_values(array_filter(
            array_map(static fn ($c) => (string) ($c['name'] ?? ''), $clients),
            static fn ($n) => $n !== '' && $n !== '-'
        ));

        return [
            'source' => 'client_list',
            'reply_ro' => $search !== ''
                ? sprintf(
                    '%d client(i) gasiti pentru „%s”%s.',
                    $shown,
                    $search,
                    $names !== [] ? ' Exemple: ' . implode(', ', array_slice($names, 0, 5)) . '.' : ''
                )
                : sprintf(
                    '%d clienti inregistrati in sistem%s.',
                    $total,
                    $names !== [] ? ' Recenti: ' . implode(', ', array_slice($names, 0, 5)) . '.' : ''
                ),
            'intent' => 'navigate',
            'cheat_sheet' => [
                'kind' => 'client_list',
                'title' => $search !== '' ? 'Clienti: ' . $search : 'Clienti in sistem (live)',
                'subtitle' => 'Lista din /admin/clienti — fara LLM',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Total clienti', 'value' => (string) $total],
                    ['key' => 'shown', 'label' => 'Afisati', 'value' => (string) $shown],
                    ['key' => 'search', 'label' => 'Filtru', 'value' => $search !== '' ? $search : '-'],
                ],
                'products' => array_map(static fn ($c) => [
                    'name' => (string) ($c['name'] ?? '-'),
                    'email' => (string) ($c['email'] ?? '-'),
                    'phone' => (string) ($c['phone'] ?? '-'),
                    'city' => (string) ($c['city'] ?? '-'),
                    'total_orders' => (string) ($c['total_orders'] ?? '0'),
                    'total_paid' => (string) ($c['total_paid'] ?? '-'),
                    'edit_url' => (string) ($c['edit_url'] ?? '/admin/clienti'),
                ], $clients),
                'products_note' => $shown > 0
                    ? 'Date live din tabelul clienti (MySQL) — aceeasi sursa ca /admin/clienti.'
                    : 'Niciun client — adauga din /admin/clienti.',
                'hints' => [
                    'Sursa: SELECT FROM clienti ORDER BY id DESC — fara LLM inventat.',
                ],
                'shortcuts' => [
                    ['label' => 'Lista clienti', 'url' => '/admin/clienti'],
                    ['label' => 'Client nou', 'url' => '/admin/addclienti'],
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildCategoryListAnswer(): array
    {
        try {
            $pdo = Database::getDB();
            $tree = SectionAssistantCatalogQueries::categoryTree($pdo);
            $subCount = SectionAssistantCatalogQueries::countSubcategories($pdo);
        } catch (Throwable) {
            $tree = [];
            $subCount = 0;
        }

        $totalProducts = 0;
        foreach ($tree as $cat) {
            $totalProducts += (int) ($cat['count'] ?? 0);
        }

        $categoryPills = array_map(static fn ($c) => [
            'name' => (string) ($c['category'] ?? '-'),
            'count' => (int) ($c['count'] ?? 0),
        ], $tree);

        return [
            'source' => 'category_list',
            'reply_ro' => $tree !== []
                ? sprintf(
                    '%d categorii active cu %d produse online (%d subcategorii distincte).',
                    count($tree),
                    $totalProducts,
                    $subCount
                )
                : 'Nu exista categorii active in catalog. Verifica produsele publicate.',
            'intent' => 'categories',
            'cheat_sheet' => [
                'kind' => 'category_list',
                'title' => 'Categorii active (live)',
                'subtitle' => 'Arbore categorii + subcategorii din produse publicate',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'cats', 'label' => 'Categorii', 'value' => (string) count($tree)],
                    ['key' => 'subs', 'label' => 'Subcategorii', 'value' => (string) $subCount],
                    ['key' => 'products', 'label' => 'Produse active', 'value' => (string) $totalProducts],
                ],
                'categories' => $categoryPills,
                'category_tree' => $tree,
                'hints' => [
                    'Date live din tabelul produse (status activ) — fara LLM.',
                ],
                'shortcuts' => [
                    ['label' => 'Categorii admin', 'url' => '/admin/categorii'],
                    ['label' => 'Produse', 'url' => '/admin/product'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildClientOrdersAnswer(string $message): array
    {
        $clientQuery = (string) ($this->extractClientNameFromMessage($message) ?? '');
        $orders = SectionAssistantOrdersQueries::listOrdersByClient($clientQuery, 30);
        $total = count($orders);

        $examples = array_map(
            static fn ($r) => (string) ($r['order_number'] ?? ''),
            array_slice($orders, 0, 5)
        );
        $examples = array_values(array_filter($examples, static fn ($n) => $n !== '' && $n !== '-'));

        $phone = $total > 0 ? trim((string) ($orders[0]['phone'] ?? '')) : '';
        $searchMode = $total > 0 ? trim((string) ($orders[0]['_search_mode'] ?? 'ok')) : '-';
        $replyRo = $total > 0
            ? $this->formatClientOrdersReply($clientQuery, $orders)
            : sprintf(
                'Nicio comanda gasita pentru „%s”. In BD client_name poate fi scurt (ex. doar prenumele) — incearca „Radu” sau telefon partial, sau deschide /admin/orders.',
                $clientQuery
            );

        return [
            'source' => 'client_orders',
            'reply_ro' => $replyRo,
            'intent' => 'orders',
            'cheat_sheet' => [
                'kind' => 'orders_list',
                'title' => 'Client: ' . $clientQuery,
                'subtitle' => 'Comenzi live — acelasi filtru ca in /admin/orders',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'client', 'label' => 'Client', 'value' => $clientQuery],
                    ['key' => 'found', 'label' => 'Comenzi gasite', 'value' => (string) $total],
                    ['key' => 'search', 'label' => 'Mod cautare', 'value' => $searchMode],
                    ['key' => 'phone', 'label' => 'Telefon', 'value' => $phone !== '' && $phone !== '-' ? $phone : '-'],
                ],
                'products' => array_map(static fn ($r) => [
                    'order_number' => (string) ($r['order_number'] ?? '-'),
                    'client_name' => (string) ($r['client_name'] ?? '-'),
                    'product_name' => (string) ($r['product_name'] ?? '-'),
                    'phone' => (string) ($r['phone'] ?? '-'),
                    'order_status' => (string) ($r['order_status'] ?? '-'),
                    'payment_status' => (string) ($r['payment_status'] ?? '-'),
                    'total_amount' => (string) ($r['total_amount'] ?? '0,00'),
                    'created_at' => (string) ($r['created_at'] ?? '-'),
                    'edit_url' => (string) ($r['edit_url'] ?? '/admin/orders'),
                ], $orders),
                'products_note' => $total > 0
                    ? 'Cautare flexibila: nume, telefon, email, notes — ca in admin.'
                    : 'Niciun rezultat — incearca prenumele singur sau ultimele cifre din telefon.',
                'hints' => [
                    'Date live din BD — potrivire pe notes/email daca numele e incomplet in client_name.',
                ],
                'shortcuts' => [
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                    ['label' => 'Clienti', 'url' => '/admin/clienti'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @param list<array<string, mixed>> $orders */
    private function formatClientOrdersReply(string $clientQuery, array $orders): string
    {
        $total = count($orders);
        $detailLines = [];
        foreach (array_slice($orders, 0, 3) as $order) {
            $detailLines[] = sprintf(
                '• %s — %s, %s RON, status %s, plata %s (%s)',
                (string) ($order['order_number'] ?? '-'),
                (string) ($order['product_name'] ?? '-'),
                (string) ($order['total_amount'] ?? '0,00'),
                (string) ($order['order_status'] ?? '-'),
                (string) ($order['payment_status'] ?? '-'),
                (string) ($order['created_at'] ?? '-')
            );
        }

        $phone = trim((string) ($orders[0]['phone'] ?? ''));
        $displayClient = trim((string) ($orders[0]['client_name'] ?? $clientQuery));

        $head = sprintf(
            'Am gasit %d comenzi pentru clientul „%s”%s.',
            $total,
            $displayClient !== '' ? $displayClient : $clientQuery,
            $phone !== '' && $phone !== '-' ? ' (tel. ' . $phone . ')' : ''
        );

        $body = implode("\n", $detailLines);
        if ($total > 3) {
            $body .= "\n… si inca " . ($total - 3) . ' comenzi in tabelul de mai jos.';
        }

        return trim($head . "\n" . $body);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function buildFullAdminCatalogAnswer(string $section, array $context): array
    {
        $modules = SectionAssistantAdminCatalog::all();
        $moduleGroups = [];
        $totalFeatures = 0;
        foreach ($modules as $key => $mod) {
            $features = [];
            foreach ($mod['features'] as $line) {
                $url = '/admin/product';
                if (preg_match('#(/admin/[a-z0-9\-/?=&]+)#i', $line, $m)) {
                    $url = $m[1];
                }
                $label = $line;
                if (preg_match('/^(.+?)\s*[—–-]\s*/u', $line, $m)) {
                    $label = trim($m[1]);
                }
                $features[] = ['label' => $label, 'url' => $url, 'description' => $line];
                ++$totalFeatures;
            }
            $moduleGroups[] = [
                'key' => $key,
                'label' => $mod['label'],
                'phase' => $mod['phase'],
                'features' => $features,
            ];
        }

        $composerCmds = $this->composerCommands();

        return [
            'source' => 'admin_full_catalog',
            'reply_ro' => sprintf('%d module admin, %d functii — de la catalog la sistem.', count($modules), $totalFeatures),
            'intent' => 'explain',
            'cheat_sheet' => [
                'kind' => 'admin_full_catalog',
                'title' => 'Catalog complet admin Besoiu',
                'subtitle' => 'Toate modulele si fazele — ce pot citi si executa',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'modules', 'label' => 'Module', 'value' => (string) count($modules)],
                    ['key' => 'features', 'label' => 'Functii', 'value' => (string) $totalFeatures],
                    ['key' => 'commands', 'label' => 'Comenzi Composer', 'value' => (string) count($composerCmds)],
                    ['key' => 'section', 'label' => 'Sectiune curenta', 'value' => $section],
                ],
                'module_groups' => $moduleGroups,
                'composer_commands' => $composerCmds,
                'hints' => [
                    'Executabil acum: badge bulk, publicare import selectiva, vitrina, reparare job.',
                    'Citire live: inventar, categorii, adaos, comparare furnizori, filtre produse, coada import.',
                ],
                'shortcuts' => [
                    ['label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial'],
                    ['label' => 'Comparare furnizori', 'url' => '/admin/suppliers?tab=compare'],
                    ['label' => 'Coada import', 'url' => '/admin/importreview'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildAdaosAnswer(): array
    {
        $snap = SectionAssistantAdaosQueries::snapshot();
        $configRows = [
            ['key' => 'TVA comercial', 'value' => number_format((float) $snap['vat_percent'], 2, ',', '.') . '%'],
            ['key' => 'Adaos global', 'value' => number_format((float) $snap['global_markup_percent'], 2, ',', '.') . '%'],
            ['key' => 'Rotunjire', 'value' => (string) $snap['round_mode']],
            ['key' => 'Reguli active', 'value' => (string) $snap['rules_active'] . ' / ' . (string) $snap['rules_total']],
        ];
        $rules = is_array($snap['rules'] ?? null) ? $snap['rules'] : [];

        return [
            'source' => 'adaos_comercial',
            'reply_ro' => sprintf(
                'Adaos global %.2f%%, TVA %.2f%%, %d reguli active.',
                (float) $snap['global_markup_percent'],
                (float) $snap['vat_percent'],
                (int) $snap['rules_active']
            ),
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'adaos_comercial',
                'title' => 'Adaos comercial & formare pret',
                'subtitle' => 'Configuratie live din baza de date',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'vat', 'label' => 'TVA', 'value' => number_format((float) $snap['vat_percent'], 1, ',', '.') . '%'],
                    ['key' => 'markup', 'label' => 'Adaos global', 'value' => number_format((float) $snap['global_markup_percent'], 1, ',', '.') . '%'],
                    ['key' => 'rules', 'label' => 'Reguli active', 'value' => (string) ($snap['rules_active'] ?? 0)],
                ],
                'config_rows' => $configRows,
                'rules' => $rules,
                'composer_commands' => array_filter($this->composerCommands(), static fn ($c) => str_contains(mb_strtolower($c['example'], 'UTF-8'), 'adaos') || str_contains(mb_strtolower($c['label'], 'UTF-8'), 'adaos')),
                'hints' => [
                    'Pret final = pret achizitie (CSV + compensator furnizor) + adaos + TVA.',
                    'Editare reguli: /admin/adaoscomercial — tab Reguli.',
                ],
                'shortcuts' => [
                    ['label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial'],
                    ['label' => 'Trace formare pret', 'url' => '/admin/adaoscomercial?tab=price-log'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSupplierHowToAnswer(): array
    {
        $total = SectionAssistantSupplierQueries::countSuppliers();

        return [
            'source' => 'supplier_howto',
            'reply_ro' => 'Deschide Furnizori din admin, apasa Adauga furnizor, completeaza numele si credentialele FTP/API, '
                . 'apoi salveaza. Poti testa conexiunea din acelasi ecran.',
            'intent' => 'suppliers',
            'cheat_sheet' => [
                'kind' => 'supplier_howto',
                'title' => 'Cum adaugi un furnizor',
                'subtitle' => 'Navigare admin — fara LLM',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Furnizori acum', 'value' => (string) $total],
                ],
                'hints' => [
                    'Meniu: Furnizori → Adauga / Edit.',
                    'Completeaza FTP sau API conform documentatiei furnizorului.',
                    'Dupa salvare: Import → alege furnizorul la sync CSV.',
                ],
                'shortcuts' => [
                    ['label' => 'Lista furnizori', 'url' => '/admin/suppliers'],
                    ['label' => 'Import produse', 'url' => '/admin/import'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [
                'Deschide /admin/suppliers',
                'Adauga furnizor nou',
                'Ruleaza import din /admin/import',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSupplierListAnswer(): array
    {
        $total = SectionAssistantSupplierQueries::countSuppliers();
        $suppliers = SectionAssistantSupplierQueries::listSuppliers(30);
        $active = 0;
        foreach ($suppliers as $row) {
            $st = strtolower(trim((string) ($row['status'] ?? '')));
            if ($st === '' || $st === 'active' || $st === 'activ' || $st === '1') {
                ++$active;
            }
        }

        $names = array_map(static fn ($s) => (string) ($s['name'] ?? ''), $suppliers);
        $names = array_values(array_filter($names, static fn ($n) => $n !== '' && $n !== '-'));

        return [
            'source' => 'supplier_list',
            'reply_ro' => $total > 0
                ? sprintf(
                    'Pe proiect sunt %d furnizor(i) inregistrati (%d activi). %s',
                    $total,
                    $active,
                    $names !== [] ? 'Exemple: ' . implode(', ', array_slice($names, 0, 6)) . '.' : ''
                )
                : 'Nu exista furnizori in baza de date. Adauga din /admin/suppliers.',
            'intent' => 'suppliers',
            'cheat_sheet' => [
                'kind' => 'supplier_list',
                'title' => 'Furnizori pe proiect',
                'subtitle' => 'Lista live din baza de date (fara LLM)',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Total furnizori', 'value' => (string) $total],
                    ['key' => 'active', 'label' => 'Activi (in lista)', 'value' => (string) $active],
                    ['key' => 'shown', 'label' => 'Afisati', 'value' => (string) count($suppliers)],
                ],
                'products' => array_map(static fn ($s) => [
                    'name' => (string) ($s['name'] ?? '-'),
                    'code' => (string) ($s['code'] ?? '-'),
                    'status' => (string) ($s['status'] ?? '-'),
                    'edit_url' => (string) ($s['edit_url'] ?? '/admin/suppliers'),
                ], $suppliers),
                'products_note' => $total > count($suppliers)
                    ? 'Afisati ' . count($suppliers) . ' din ' . $total . ' furnizori.'
                    : ($suppliers === [] ? 'Niciun furnizor — adauga din Lista furnizori.' : 'Toti furnizorii activi:'),
                'hints' => [
                    'Date live din tabelul furnizori — nu depinde de LLM.',
                    'Pentru ordine scan la import: pozitionare furnizori comparare ordine scan',
                ],
                'shortcuts' => [
                    ['label' => 'Lista furnizori', 'url' => '/admin/suppliers'],
                    ['label' => 'Comparare / ordine scan', 'url' => '/admin/suppliers?tab=compare'],
                    ['label' => 'Import CSV', 'url' => '/admin/import'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSupplierCompareAnswer(): array
    {
        $snap = SectionAssistantSupplierQueries::compareSnapshot();
        $suppliers = SectionAssistantSupplierQueries::listSuppliers(15);
        $positions = is_array($snap['positions'] ?? null) ? $snap['positions'] : [];
        $omit = is_array($snap['omit_suppliers'] ?? null) ? $snap['omit_suppliers'] : [];

        return [
            'source' => 'supplier_compare',
            'reply_ro' => sprintf(
                '%d furnizori in ordinea de scan, strategie %s, tier %d.',
                count($positions),
                (string) ($snap['price_strategy'] ?? '-'),
                (int) ($snap['compare_tier_size'] ?? 3)
            ),
            'intent' => 'suppliers',
            'cheat_sheet' => [
                'kind' => 'supplier_compare',
                'title' => 'Comparare & pozitionare furnizori',
                'subtitle' => 'Ordine scan, omit, strategie pret la import',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'positions', 'label' => 'In ierarhie', 'value' => (string) count($positions)],
                    ['key' => 'omit', 'label' => 'Omit', 'value' => (string) count($omit)],
                    ['key' => 'strategy', 'label' => 'Strategie', 'value' => (string) ($snap['price_strategy'] ?? '-')],
                    ['key' => 'tier', 'label' => 'Tier size', 'value' => (string) ($snap['compare_tier_size'] ?? 3)],
                ],
                'supplier_positions' => $positions,
                'config_rows' => [
                    ['key' => 'Brand verify', 'value' => (string) ($snap['brand_verify'] ?? '-')],
                    ['key' => 'Stock verify', 'value' => (string) ($snap['stock_verify'] ?? '-')],
                    ['key' => 'Omit furnizori', 'value' => $omit !== [] ? implode(', ', $omit) : 'Niciunul'],
                ],
                'products' => array_map(static fn ($s) => [
                    'name' => (string) ($s['name'] ?? '-'),
                    'brand' => (string) ($s['code'] ?? '-'),
                    'code' => (string) ($s['status'] ?? '-'),
                    'category' => 'Furnizor',
                    'price' => '-',
                    'edit_url' => '/admin/suppliers',
                ], $suppliers),
                'products_note' => 'Furnizori inregistrati (cod / status):',
                'hints' => [
                    'Pozitia 1 = primul scanat la acelasi cod piesa.',
                    'Editare: /admin/suppliers?tab=compare',
                ],
                'shortcuts' => [
                    ['label' => 'Comparare furnizori', 'url' => '/admin/suppliers?tab=compare'],
                    ['label' => 'Lista furnizori', 'url' => '/admin/suppliers'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildVitrinaAnswer(): array
    {
        $snap = SectionAssistantProductQueries::vitrinaSnapshot(15);

        return [
            'source' => 'vitrina_homepage',
            'reply_ro' => sprintf('%d / %d produse pe vitrina homepage.', $snap['count'], $snap['max']),
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'vitrina_homepage',
                'title' => 'Vitrina homepage',
                'subtitle' => 'Produse afisate sub hero pe site',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'on', 'label' => 'Pe vitrina', 'value' => (string) $snap['count']],
                    ['key' => 'max', 'label' => 'Maxim', 'value' => (string) $snap['max']],
                    ['key' => 'free', 'label' => 'Locuri libere', 'value' => (string) max(0, $snap['max'] - $snap['count'])],
                ],
                'products' => $snap['items'],
                'products_note' => $snap['count'] > 0 ? 'Produse curente pe vitrina:' : 'Vitrina este goala.',
                'hints' => [
                    'Composer: pune CASTROL pe vitrina / scoate de pe vitrina.',
                ],
                'shortcuts' => [
                    ['label' => 'Vitrina admin', 'url' => '/admin/vitrina'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildImportReadinessAnswer(): array
    {
        return [
            'source' => 'import_readiness',
            'reply_ro' => 'Modulul de import produse a fost dezactivat.',
            'intent' => 'import',
            'cheat_sheet' => [
                'kind' => 'import_readiness',
                'title' => 'Import dezactivat',
                'subtitle' => 'Modulul de import produse a fost eliminat din proiect.',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [],
                'shortcuts' => [['label' => 'Produse', 'url' => '/admin/product']],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildImportQueueAnswer(string $message = ''): array
    {
        return [
            'source' => 'import_queue',
            'reply_ro' => 'Modulul de import produse a fost dezactivat.',
            'intent' => 'import',
            'cheat_sheet' => [
                'kind' => 'import_queue',
                'title' => 'Import dezactivat',
                'subtitle' => 'Coada import nu mai este disponibilă.',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [],
                'shortcuts' => [['label' => 'Produse', 'url' => '/admin/product']],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildProductListAnswer(string $message): array
    {
        $limit = 50;
        if (preg_match('/\b(\d{1,3})\s*produse?\b/u', mb_strtolower($message, 'UTF-8'), $m)) {
            $limit = max(1, min(200, (int) $m[1]));
        }
        $result = SectionAssistantProductQueries::search([], $limit);
        $shown = count($result['items']);
        $total = (int) $result['total'];

        return [
            'source' => 'product_list',
            'reply_ro' => $total === 0
                ? 'Niciun produs activ in catalog.'
                : sprintf(
                    '%d produse active%s.',
                    $total,
                    $shown < $total ? ' (afisate ' . $shown . ')' : ''
                ),
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'product_list',
                'query_mode' => 'list',
                'selective' => true,
                'selectable' => $shown > 0,
                'focus' => 'all_products',
                'title' => 'Toate produsele active',
                'subtitle' => 'Lista completa din catalog',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Total active', 'value' => (string) $total, 'primary' => true],
                    ['key' => 'shown', 'label' => 'In lista', 'value' => (string) $shown],
                ],
                'products' => $result['items'],
                'products_note' => $shown < $total
                    ? 'Bifeaza + comanda CRUD (ex: pret 150). Afisate ' . $shown . ' din ' . $total . '.'
                    : ($shown > 0 ? 'Bifeaza produse + scrie comanda (pret, categorie, imagini, descriere).' : 'Niciun produs activ.'),
                'bulk_actions' => $shown > 0 ? [
                    ['id' => 'open_all', 'label' => 'Deschide in admin', 'url' => '/admin/product'],
                    ['id' => 'edit_selected', 'label' => 'Editeaza selectate', 'action' => 'edit_selected', 'requires_selection' => true],
                    ['id' => 'copy_codes', 'label' => 'Copiaza coduri', 'action' => 'copy_codes', 'requires_selection' => true],
                ] : [],
                'shortcuts' => [
                    ['label' => 'Lista produse', 'url' => '/admin/product'],
                    ['label' => 'Fara imagine', 'url' => '/admin/product?filter=no_image&page=1'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => $total > 0
                ? ['Bifeaza produse', 'Scrie comanda CRUD (ex: pret 150)', 'Confirma in cheat sheet']
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildProductFilterAnswer(string $message): array
    {
        $filters = $this->parseProductFilters($message);
        $limit = 50;
        if (preg_match('/\b(\d{1,3})\s*produse?\b/u', mb_strtolower($message, 'UTF-8'), $m)) {
            $limit = max(1, min(200, (int) $m[1]));
        }
        $result = SectionAssistantProductQueries::search($filters, $limit);
        $filterDesc = [];
        if (!empty($filters['no_image'])) {
            $filterDesc[] = 'fara imagine';
        }
        if (!empty($filters['vitrina_only'])) {
            $filterDesc[] = 'pe vitrina';
        }
        if (!empty($filters['category'])) {
            $filterDesc[] = 'categorie ' . $filters['category'];
        }
        if (!empty($filters['brand'])) {
            $filterDesc[] = 'brand ' . $filters['brand'];
        }
        if (!empty($filters['keyword'])) {
            $filterDesc[] = 'keyword ' . $filters['keyword'];
        }
        if (!empty($filters['badge'])) {
            $filterDesc[] = 'badge ' . $filters['badge'];
        }

        $isNoImage = !empty($filters['no_image']);
        $selective = $filterDesc !== [];
        $filterLabel = $filterDesc !== [] ? implode(', ', $filterDesc) : 'filtru activ';
        $shown = count($result['items']);
        $total = (int) $result['total'];

        $reply = $isNoImage
            ? ($total === 0
                ? 'Niciun produs activ fara imagine.'
                : sprintf(
                    '%d produs%s fara imagine%s.',
                    $total,
                    $total === 1 ? '' : 'e',
                    $shown < $total ? ' (afisate ' . $shown . ')' : ''
                ))
            : sprintf('%d produse gasite (%s).', $total, $filterLabel);

        $shortcuts = [
            ['label' => 'Lista produse', 'url' => '/admin/product'],
        ];
        if ($isNoImage) {
            $shortcuts = [
                ['label' => 'Filtru fara imagine', 'url' => '/admin/product?filter=no_image&page=1'],
                ['label' => 'Audit imagini', 'url' => '/admin/product#image-audit'],
            ];
        }

        return [
            'source' => 'product_filter',
            'reply_ro' => $reply,
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'product_filter',
                'query_mode' => 'filter',
                'selective' => $selective,
                'selectable' => $selective && $shown > 0,
                'focus' => $isNoImage ? 'no_image' : 'filter',
                'title' => $isNoImage ? 'Produse fara imagine' : 'Filtrare produse catalog',
                'subtitle' => $filterLabel,
                'updated_at' => date('d.m.Y H:i'),
                'stats' => $selective
                    ? [
                        ['key' => 'total', 'label' => 'Gasite', 'value' => (string) $total, 'primary' => true],
                        ['key' => 'shown', 'label' => 'In lista', 'value' => (string) $shown],
                    ]
                    : [
                        ['key' => 'total', 'label' => 'Total gasite', 'value' => (string) $total],
                        ['key' => 'shown', 'label' => 'Afisate', 'value' => (string) $shown],
                    ],
                'products' => $result['items'],
                'products_note' => $total === 0
                    ? 'Nimic de afisat pentru acest filtru.'
                    : ($shown < $total
                        ? 'Bifeaza produse + scrie comanda CRUD (ex: pret 150, categorie Frane). Afisate ' . $shown . ' din ' . $total . '.'
                        : 'Bifeaza produse + scrie comanda CRUD (ex: pret 150, sterge imagini).'),
                'bulk_actions' => $selective && $shown > 0 ? [
                    ['id' => 'open_filter', 'label' => 'Deschide in admin', 'url' => $isNoImage ? '/admin/product?filter=no_image&page=1' : '/admin/product'],
                    ['id' => 'edit_selected', 'label' => 'Editeaza selectate', 'action' => 'edit_selected', 'requires_selection' => true],
                    ['id' => 'copy_codes', 'label' => 'Copiaza coduri', 'action' => 'copy_codes', 'requires_selection' => true],
                ] : [],
                'shortcuts' => $shortcuts,
            ],
            'suggestions' => [],
            'next_steps' => $isNoImage && $total > 0
                ? ['Selecteaza produsele din lista', 'Apasa Editeaza selectate sau Deschide in admin']
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildInvoiceListAnswer(): array
    {
        $stats = SectionAssistantOrdersQueries::invoiceStats();
        $invoices = SectionAssistantOrdersQueries::listInvoices(30);
        $total = (int) ($stats['total'] ?? 0);
        $examples = array_map(
            static fn ($r) => (string) ($r['invoice_number'] ?? ''),
            array_slice($invoices, 0, 5)
        );
        $examples = array_values(array_filter($examples, static fn ($n) => $n !== ''));

        return [
            'source' => 'invoice_list',
            'reply_ro' => $total > 0
                ? sprintf(
                    '%d facturi generate (%d achitate, %d in asteptare). %s',
                    $total,
                    (int) ($stats['achitata'] ?? 0),
                    (int) ($stats['neachitata'] ?? 0),
                    $examples !== [] ? 'Exemple: ' . implode(', ', $examples) . '.' : ''
                )
                : 'Nu exista facturi generate inca. Genereaza din /admin/facturi.',
            'intent' => 'orders',
            'cheat_sheet' => [
                'kind' => 'invoice_list',
                'title' => 'Facturi generate',
                'subtitle' => 'Lista live din baza de date (fara LLM)',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Total facturi', 'value' => (string) $total],
                    ['key' => 'paid', 'label' => 'Achitate', 'value' => (string) ($stats['achitata'] ?? 0)],
                    ['key' => 'open', 'label' => 'In asteptare', 'value' => (string) ($stats['neachitata'] ?? 0), 'warn' => ($stats['neachitata'] ?? 0) > 0],
                    ['key' => 'amount', 'label' => 'Total facturat', 'value' => number_format((float) ($stats['total_amount'] ?? 0), 2, ',', '.') . ' RON'],
                ],
                'products' => array_map(static fn ($r) => [
                    'invoice_number' => (string) ($r['invoice_number'] ?? '-'),
                    'order_number' => (string) ($r['order_number'] ?? '-'),
                    'client_name' => (string) ($r['client_name'] ?? '-'),
                    'invoice_status' => (string) ($r['invoice_status'] ?? '-'),
                    'amount' => (string) ($r['amount'] ?? '0,00'),
                    'edit_url' => (string) ($r['edit_url'] ?? '/admin/facturi'),
                ], $invoices),
                'products_note' => $total > count($invoices)
                    ? 'Afisate ' . count($invoices) . ' din ' . $total . ' facturi.'
                    : ($invoices === [] ? 'Nicio factura — genereaza din Comenzi sau /admin/facturi.' : 'Facturi recente:'),
                'hints' => [
                    'Date live din tabelul facturi — nu rezumat comenzi.',
                ],
                'shortcuts' => [
                    ['label' => 'Facturi', 'url' => '/admin/facturi'],
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                    ['label' => 'Caiet facturi', 'url' => '/admin/caiet-facturi'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildAwbListAnswer(): array
    {
        $stats = SectionAssistantOrdersQueries::awbStats();
        $awbs = SectionAssistantOrdersQueries::listAwbs(30);
        $withAwb = (int) ($stats['with_awb'] ?? 0);
        $examples = array_map(
            static fn ($r) => (string) ($r['awb'] ?? ''),
            array_slice($awbs, 0, 5)
        );
        $examples = array_values(array_filter($examples, static fn ($n) => $n !== ''));

        return [
            'source' => 'awb_list',
            'reply_ro' => $withAwb > 0
                ? sprintf(
                    '%d AWB generate (%d livrari totale, %d in tranzit). %s',
                    $withAwb,
                    (int) ($stats['total'] ?? 0),
                    (int) ($stats['in_transit'] ?? 0),
                    $examples !== [] ? 'Exemple: ' . implode(', ', $examples) . '.' : ''
                )
                : 'Nu exista AWB generate inca. Genereaza din /admin/livrare sau la finalizare comanda.',
            'intent' => 'orders',
            'cheat_sheet' => [
                'kind' => 'awb_list',
                'title' => 'AWB generate',
                'subtitle' => 'Lista live din livrari (fara LLM)',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'awb', 'label' => 'Cu AWB', 'value' => (string) $withAwb],
                    ['key' => 'total', 'label' => 'Livrari totale', 'value' => (string) ($stats['total'] ?? 0)],
                    ['key' => 'transit', 'label' => 'In tranzit', 'value' => (string) ($stats['in_transit'] ?? 0)],
                    ['key' => 'delivered', 'label' => 'Livrate', 'value' => (string) ($stats['delivered'] ?? 0)],
                ],
                'products' => array_map(static fn ($r) => [
                    'awb' => (string) ($r['awb'] ?? '-'),
                    'order_number' => (string) ($r['order_number'] ?? '-'),
                    'client_name' => (string) ($r['client_name'] ?? '-'),
                    'courier' => (string) ($r['courier'] ?? '-'),
                    'delivery_status' => (string) ($r['delivery_status'] ?? '-'),
                    'total_amount' => (string) ($r['total_amount'] ?? '0,00'),
                    'edit_url' => (string) ($r['edit_url'] ?? '/admin/livrare'),
                ], $awbs),
                'products_note' => $withAwb > count($awbs)
                    ? 'Afisate ' . count($awbs) . ' din ' . $withAwb . ' AWB.'
                    : ($awbs === [] ? 'Niciun AWB — genereaza din Livrare sau la expediere comanda.' : 'AWB recente:'),
                'hints' => [
                    'Date live din tabelul livrare — nu rezumat comenzi.',
                ],
                'shortcuts' => [
                    ['label' => 'Livrare AWB', 'url' => '/admin/livrare'],
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildCartListAnswer(): array
    {
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $snapshot = SectionAssistantCartQueries::snapshot($adminUserId > 0 ? $adminUserId : null, 35);
        $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        $stats = is_array($snapshot['stats'] ?? null) ? $snapshot['stats'] : [];
        $totalLines = (int) ($stats['total_lines'] ?? count($items));
        $sessions = (int) ($snapshot['sessions'] ?? 0);
        $abandoned = (int) ($snapshot['abandoned'] ?? 0);
        $supplierLines = (int) ($snapshot['supplier_lines'] ?? 0);

        $examples = array_map(
            static fn ($r) => (string) ($r['name'] ?? ''),
            array_slice($items, 0, 3)
        );
        $examples = array_values(array_filter($examples, static fn ($n) => $n !== ''));

        return [
            'source' => 'cart_list',
            'reply_ro' => $totalLines > 0
                ? sprintf(
                    '%d linii in cosuri active (%d sesiuni magazin, %d cosuri abandonate deschise, %d linii furnizori B2B). %s',
                    $totalLines,
                    $sessions,
                    $abandoned,
                    $supplierLines,
                    $examples !== [] ? 'Exemple: ' . implode(', ', $examples) . '.' : ''
                )
                : 'Nu exista produse in cosuri active acum (magazin, abandonat sau furnizori B2B).',
            'intent' => 'orders',
            'cheat_sheet' => [
                'kind' => 'cart_list',
                'title' => 'Produse in cos (live)',
                'subtitle' => 'Magazin + cos abandonat + coș furnizori B2B',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'lines', 'label' => 'Linii listate', 'value' => (string) $totalLines],
                    ['key' => 'sessions', 'label' => 'Sesiuni magazin (7 zile)', 'value' => (string) $sessions],
                    ['key' => 'abandoned', 'label' => 'Cosuri abandonate', 'value' => (string) $abandoned, 'warn' => $abandoned > 0],
                    ['key' => 'supplier', 'label' => 'Linii furnizori B2B', 'value' => (string) $supplierLines],
                ],
                'products' => array_map(static fn ($r) => [
                    'name' => (string) ($r['name'] ?? '-'),
                    'brand' => (string) ($r['brand'] ?? '-'),
                    'code' => (string) ($r['code'] ?? '-'),
                    'category' => (string) ($r['category'] ?? '-'),
                    'price' => (string) ($r['price'] ?? '-'),
                    'edit_url' => (string) ($r['edit_url'] ?? '/admin/abandoned-carts'),
                ], $items),
                'products_note' => $totalLines > 0
                    ? 'Surse: cart_items (magazin), cart_abandonments, supplier_carts.'
                    : 'Niciun produs in cos — verifica magazinul, cos abandonat sau /admin/supplier-cart.',
                'hints' => [
                    'Date live din BD — fara LLM.',
                ],
                'shortcuts' => [
                    ['label' => 'Cos abandonat', 'url' => '/admin/abandoned-carts'],
                    ['label' => 'Cos furnizori', 'url' => '/admin/supplier-cart'],
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildOrdersAnswer(): array
    {
        try {
            $stats = (new ComenziModel())->getDashboardStats();
        } catch (Throwable) {
            $stats = ['total' => 0, 'new_orders' => 0, 'today_new' => 0, 'today_revenue' => 0, 'by_status' => []];
        }
        $orders = SectionAssistantOrdersQueries::listOrders(30);
        $total = (int) ($stats['total'] ?? 0);
        $byStatus = is_array($stats['by_status'] ?? null) ? $stats['by_status'] : [];
        $statusRows = [];
        foreach ($byStatus as $status => $cnt) {
            $statusRows[] = ['key' => (string) $status, 'value' => (string) $cnt];
        }

        $examples = array_map(
            static fn ($r) => (string) ($r['order_number'] ?? ''),
            array_slice($orders, 0, 5)
        );
        $examples = array_values(array_filter($examples, static fn ($n) => $n !== '' && $n !== '-'));

        return [
            'source' => 'orders_summary',
            'reply_ro' => sprintf(
                '%d comenzi total (%d noi, %d astazi). %s',
                $total,
                (int) ($stats['new_orders'] ?? 0),
                (int) ($stats['today_new'] ?? 0),
                $examples !== [] ? 'Lista: ' . implode(', ', $examples) . '.' : ''
            ),
            'intent' => 'orders',
            'cheat_sheet' => [
                'kind' => 'orders_list',
                'title' => 'Comenzi in sistem',
                'subtitle' => 'Lista live din baza de date (fara LLM)',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Total comenzi', 'value' => (string) $total],
                    ['key' => 'new', 'label' => 'Noi', 'value' => (string) ($stats['new_orders'] ?? 0)],
                    ['key' => 'today', 'label' => 'Astazi', 'value' => (string) ($stats['today_new'] ?? 0)],
                    ['key' => 'rev', 'label' => 'Incasari azi', 'value' => number_format((float) ($stats['today_revenue'] ?? 0), 2, ',', '.') . ' RON'],
                ],
                'config_rows' => $statusRows,
                'products' => array_map(static fn ($r) => [
                    'order_number' => (string) ($r['order_number'] ?? '-'),
                    'client_name' => (string) ($r['client_name'] ?? '-'),
                    'product_name' => (string) ($r['product_name'] ?? '-'),
                    'order_status' => (string) ($r['order_status'] ?? '-'),
                    'payment_status' => (string) ($r['payment_status'] ?? '-'),
                    'total_amount' => (string) ($r['total_amount'] ?? '0,00'),
                    'created_at' => (string) ($r['created_at'] ?? '-'),
                    'edit_url' => (string) ($r['edit_url'] ?? '/admin/orders'),
                ], $orders),
                'products_note' => $total > count($orders)
                    ? 'Afisate ' . count($orders) . ' din ' . $total . ' comenzi.'
                    : ($orders === [] ? 'Nicio comanda — deschide /admin/orders pentru a crea.' : 'Comenzi recente:'),
                'hints' => [
                    'Date live din tabelul comenzi — nu rezumat generic.',
                ],
                'shortcuts' => [
                    ['label' => 'Comenzi', 'url' => '/admin/orders'],
                    ['label' => 'Facturi', 'url' => '/admin/facturi'],
                    ['label' => 'Livrare AWB', 'url' => '/admin/livrare'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function parseProductFilters(string $message): array
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $filters = [];
        $code = SectionAssistantQueryHelper::extractProductCode($message);
        if ($code !== null) {
            $filters['keyword'] = $code;
        }
        if (preg_match('/\b(fara|fără)\s+imagine/u', $lower)) {
            $filters['no_image'] = true;
        }
        if (preg_match('/\bvitrin[aă]\b/u', $lower)) {
            $filters['vitrina_only'] = true;
        }
        foreach (['hot', 'promo', 'nou', 'top'] as $badge) {
            if (preg_match('/\b' . $badge . '\b/u', $lower)) {
                $filters['badge'] = $badge;
            }
        }
        foreach (['ulei', 'lichid', 'suspensie', 'frana', 'filtru', 'baterie', 'consumabil'] as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\w*\b/u', $lower)) {
                $filters['keyword'] = $kw;
                break;
            }
        }
        if (preg_match('/\bcategor\w*\s+([a-z0-9\-]+)/u', $lower, $m)) {
            $filters['category'] = $m[1];
        }
        if (preg_match('/\bbrand\s+([a-z0-9\-]+)/u', $lower, $m)) {
            $filters['brand'] = strtoupper($m[1]);
        }

        return $filters;
    }

    private function wantsComunicare(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(mesaj\w*|chat|comunicare|lead|broadcast|template|marketing|whatsapp|facebook|pieseauto|necitit\w*)\b/u',
            $lower
        ) && SectionAssistantQueryHelper::hasLiveDataAsk($message);
    }

    private function wantsWebsite(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(website|site|blog|cms|pagin\w*)\b/u', $lower)
            && (bool) preg_match('/\b(ce|c[aâ]te|lista|pagin\w*|overview)\b/u', $lower);
    }

    /** @return array<string, mixed> */
    private function buildComunicareAnswer(): array
    {
        try {
            $hub = new ComunicareHubService();
            $stats = $hub->hubStats();
            $recent = $hub->listRecentMessages(12);
        } catch (Throwable) {
            $stats = [];
            $recent = [];
        }

        return [
            'source' => 'comunicare_summary',
            'reply_ro' => sprintf(
                '%d mesaje necitite, %d template-uri%s.',
                (int) ($stats['messages_unread'] ?? 0),
                (int) ($stats['templates_total'] ?? 0),
                $recent !== [] ? ' — ultimele ' . count($recent) . ' mesaje listate.' : ''
            ),
            'intent' => 'navigate',
            'cheat_sheet' => [
                'kind' => 'comunicare_summary',
                'title' => 'Comunicare & mesagerie',
                'subtitle' => 'Hub comunicare — status live + mesaje recente',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'unread', 'label' => 'Mesaje necitite', 'value' => (string) ($stats['messages_unread'] ?? 0), 'warn' => ((int) ($stats['messages_unread'] ?? 0)) > 0],
                    ['key' => 'tpl', 'label' => 'Template-uri', 'value' => (string) ($stats['templates_total'] ?? 0)],
                    ['key' => 'quick', 'label' => 'Raspunsuri rapide', 'value' => (string) ($stats['templates_quick'] ?? 0)],
                    ['key' => 'recent', 'label' => 'Mesaje recente', 'value' => (string) count($recent)],
                ],
                'products' => $recent,
                'products_note' => $recent !== [] ? 'Ultimele mesaje din inbox (live).' : 'Niciun mesaj in inbox.',
                'shortcuts' => [
                    ['label' => 'Hub comunicare', 'url' => '/admin/comunicare'],
                    ['label' => 'Mesagerie', 'url' => '/admin/messages'],
                    ['label' => 'Lead-uri', 'url' => '/admin/comunicare-leads'],
                    ['label' => 'Broadcast', 'url' => '/admin/comunicare-broadcast'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildWebsiteAnswer(): array
    {
        $pages = 0;
        try {
            if (class_exists(\Besoiu\Services\WebsiteService::class)) {
                $pages = count((new \Besoiu\Services\WebsiteService())->getAll());
            }
        } catch (Throwable) {
            $pages = 0;
        }

        return [
            'source' => 'website_summary',
            'reply_ro' => $pages . ' pagini site in CMS.',
            'intent' => 'navigate',
            'cheat_sheet' => [
                'kind' => 'website_summary',
                'title' => 'Web site & blog',
                'subtitle' => 'CMS pagini site',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'pages', 'label' => 'Pagini site', 'value' => (string) $pages],
                ],
                'shortcuts' => [
                    ['label' => 'Editor site', 'url' => '/admin/website'],
                    ['label' => 'Blog', 'url' => '/admin/blog'],
                    ['label' => 'Articol nou', 'url' => '/admin/addblog'],
                ],
                'hints' => ['Editarea continutului CMS: /admin/website'],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    private function normalizeComposerTypos(string $message): string
    {
        return SectionAssistantQueryHelper::normalizeComposerTypos($message);
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    private function trySectionLiveAnswer(string $message, string $section, array $context): ?array
    {
        $section = mb_strtolower(trim($section), 'UTF-8');

        return match ($section) {
            'produse', 'categorii' => $this->tryProduseLive($message),
            'furnizori' => $this->tryFurnizoriLive($message),
            'import' => $this->tryImportLive($message),
            'comunicare' => $this->tryComunicareLive($message),
            'clienti' => $this->tryClientiLive($message),
            'comenzi' => $this->tryComenziLive($message),
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function tryProduseLive(string $message): ?array
    {
        if (($codePlan = $this->tryProductCodeLookup($message)) !== null) {
            return $codePlan;
        }
        $code = SectionAssistantQueryHelper::extractProductCode($message);
        if ($code !== null && SectionAssistantQueryHelper::hasLiveDataAsk($message)) {
            return $this->buildProductByCodeAnswer($code);
        }
        if ($this->wantsCategoryList($message)) {
            return $this->buildCategoryListAnswer();
        }
        if ($this->wantsProductFilter($message)) {
            return $this->buildProductFilterAnswer($message);
        }
        if ($this->wantsProductList($message)) {
            return $this->buildProductListAnswer($message);
        }
        if ($this->wantsProductInventory($message)) {
            return $this->buildProductInventoryAnswer($message);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function tryFurnizoriLive(string $message): ?array
    {
        if ($this->wantsSupplierCompare($message)) {
            return $this->buildSupplierCompareAnswer();
        }
        if ($this->wantsSupplierList($message)) {
            return $this->buildSupplierListAnswer();
        }
        $term = $this->extractSupplierSearchTerm($message);
        if ($term !== null) {
            return $this->buildSupplierSearchAnswer($term);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function tryImportLive(string $message): ?array
    {
        if ($this->wantsImportReadiness($message)) {
            return $this->buildImportReadinessAnswer();
        }
        if ($this->wantsImportOverview($message)) {
            return $this->buildImportQueueAnswer($message);
        }

        return null;
    }

    private function wantsImportReadiness(string $message): bool
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if (preg_match('/\b(readiness|preg[aă]tit|pregatit|checklist\s+import|verific[aă]\s+inainte|înainte\s+de\s+import|inainte\s+de\s+import)\b/u', $lower)) {
            return true;
        }
        if (preg_match('/\b(sunt\s+preg[aă]tit|sistemul\s+e\s+gata|pot\s+(?:s[aă]\s+)?import)\b/u', $lower)) {
            return true;
        }
        if (preg_match('/\b(totul\s+ok|e\s+totul\s+ok|merge\s+s[aă]|e\s+ok)\b/u', $lower)
            && preg_match('/\b(import|job|coad[aă]|proces|sistem)\b/u', $lower)) {
            return true;
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function tryComunicareLive(string $message): ?array
    {
        if ($this->wantsComunicare($message)) {
            return $this->buildComunicareAnswer();
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function tryClientiLive(string $message): ?array
    {
        if ($this->wantsClientOrders($message)) {
            return $this->buildClientOrdersAnswer($message);
        }
        if ($this->wantsClientList($message)) {
            return $this->buildClientListAnswer($message);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function tryComenziLive(string $message): ?array
    {
        if ($this->wantsClientOrders($message)) {
            return $this->buildClientOrdersAnswer($message);
        }
        if ($this->wantsClientList($message)) {
            return $this->buildClientListAnswer($message);
        }
        if ($this->wantsOrders($message)) {
            return $this->buildOrdersAnswer();
        }

        return null;
    }

    private function extractSupplierSearchTerm(string $message): ?string
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (!preg_match('/\b(furnizor\w*|supplier|autonet|intercars|autototal|materom|epiesa)\b/u', $lower)) {
            return null;
        }
        $term = SectionAssistantQueryHelper::extractTermAfterPreposition($message);
        if ($term !== null && mb_strlen($term, 'UTF-8') >= 2) {
            return $term;
        }
        foreach (['autonet', 'intercars', 'autototal', 'materom', 'epiesa'] as $code) {
            if (str_contains($lower, $code)) {
                return $code;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function extractImportKeywords(string $message): array
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $keywords = [];
        foreach (['ulei', 'lichid', 'frana', 'filtru', 'baterie', 'castrol', 'motul', 'suspensie'] as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\w*\b/u', $lower)) {
                $keywords[] = $kw;
            }
        }
        $term = SectionAssistantQueryHelper::extractTermAfterPreposition($message);
        if ($term !== null && mb_strlen($term, 'UTF-8') >= 3) {
            $keywords[] = $term;
        }

        return array_values(array_unique($keywords));
    }

    /** @return array<string, mixed> */
    private function buildProductInventoryAnswer(string $message): array
    {
        $snap = SectionAssistantProductQueries::inventorySnapshot();
        $code = SectionAssistantQueryHelper::extractProductCode($message);
        $samples = [];
        if ($code !== null) {
            $samples = SectionAssistantProductQueries::search(['keyword' => $code], 10)['items'] ?? [];
        }

        return [
            'source' => 'product_inventory',
            'reply_ro' => sprintf(
                '%d produse active online (%d categorii, %d pe vitrina, %d fara imagine).',
                (int) $snap['total'],
                (int) $snap['categories'],
                (int) $snap['vitrina'],
                (int) $snap['no_image']
            ),
            'intent' => 'configure_products',
            'cheat_sheet' => [
                'kind' => 'product_inventory',
                'query_mode' => 'summary',
                'selective' => true,
                'selectable' => false,
                'show_products_table' => false,
                'title' => 'Rezumat inventar',
                'subtitle' => 'Doar cifre — fara lista completa',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'total', 'label' => 'Produse active', 'value' => (string) $snap['total'], 'primary' => true],
                    ['key' => 'noimg', 'label' => 'Fara imagine', 'value' => (string) $snap['no_image'], 'warn' => ($snap['no_image'] ?? 0) > 0, 'primary' => ($snap['no_image'] ?? 0) > 0],
                    ['key' => 'cats', 'label' => 'Categorii', 'value' => (string) $snap['categories']],
                    ['key' => 'vitrina', 'label' => 'Pe vitrina', 'value' => (string) $snap['vitrina']],
                ],
                'products' => $samples,
                'products_note' => $samples !== []
                    ? 'Potriviri pentru codul cautat.'
                    : 'Pentru lista completa: „lista tuturor produselor”. Pentru subset: „produse fara imagine”.',
                'shortcuts' => [
                    ['label' => 'Lista completa', 'url' => '/admin/product'],
                    ['label' => 'Produse fara imagine', 'url' => '/admin/product?filter=no_image&page=1'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => (int) ($snap['no_image'] ?? 0) > 0
                ? ['Scrie: lista tuturor produselor — sau: produse fara imagine']
                : ['Scrie: lista tuturor produselor — pentru lista selectabila'],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSupplierSearchAnswer(string $query): array
    {
        $query = SectionAssistantQueryHelper::sanitize($query);
        $suppliers = SectionAssistantSupplierQueries::searchByQuery($query, 25);
        $total = count($suppliers);

        return [
            'source' => 'supplier_search',
            'reply_ro' => $total > 0
                ? sprintf('%d furnizor(i) gasit(i) pentru „%s”.', $total, $query)
                : sprintf('Niciun furnizor pentru „%s”. Incearca codul (ex. autonet).', $query),
            'intent' => 'suppliers',
            'cheat_sheet' => [
                'kind' => 'supplier_list',
                'title' => 'Cautare furnizor: ' . $query,
                'subtitle' => 'Filtru live pe nume / cod',
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'query', 'label' => 'Termen', 'value' => $query],
                    ['key' => 'found', 'label' => 'Gasiti', 'value' => (string) $total],
                ],
                'products' => $suppliers,
                'products_note' => $total > 0 ? 'Furnizori potriviti cautarii.' : 'Lista completa: ce furnizori sunt pe proiect acum.',
                'shortcuts' => [
                    ['label' => 'Furnizori', 'url' => '/admin/suppliers'],
                    ['label' => 'Comparare', 'url' => '/admin/suppliers?tab=compare'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return list<array{label:string,example:string}> */
    public function composerCommands(): array
    {
        return [
            ['label' => 'Catalog complet admin', 'example' => 'toate functiile admin toate fazele'],
            ['label' => 'Produse online / inventar', 'example' => 'ce produse sunt online acum'],
            ['label' => 'Categorii + subcategorii', 'example' => 'categorii si subcategorii toate produsele'],
            ['label' => 'Filtrare produse', 'example' => 'produse fara imagine categorie suspensie'],
            ['label' => 'Adaos comercial live', 'example' => 'ce adaos comercial si reguli active'],
            ['label' => 'Set adaos global', 'example' => 'seteaza adaos global 15%'],
            ['label' => 'Aplica regula adaos', 'example' => 'aplica regula adaos 1'],
            ['label' => 'Furnizori pe proiect', 'example' => 'ce furnizori sunt pe proiect acum'],
            ['label' => 'Comparare furnizori', 'example' => 'pozitionare furnizori comparare ordine scan'],
            ['label' => 'Mut furnizor pozitia 1', 'example' => 'pune autonet primul'],
            ['label' => 'Scan consumabile', 'example' => 'porneste scan consumabile ulei'],
            ['label' => 'Scan imagini coada', 'example' => 'scaneaza imagini coada import'],
            ['label' => 'Reprocess TecDoc', 'example' => 'reproceseaza 5 produse coada import'],
            ['label' => 'Export BaseLinker', 'example' => 'export baselinker'],
            ['label' => 'Export Autopro', 'example' => 'export autopro'],
            ['label' => 'Vitrina homepage', 'example' => 'ce produse sunt pe vitrina'],
            ['label' => 'Coada import', 'example' => 'ce produse sunt in coada import'],
            ['label' => 'Verificare import', 'example' => 'e totul ok pentru import?'],
            ['label' => 'Publica din import', 'example' => 'adauga 5 ulei cu imagini din import'],
            ['label' => 'Vitrina actiune', 'example' => 'pune castrol pe vitrina'],
            ['label' => 'Comenzi rezumat', 'example' => 'cate comenzi noi sunt'],
            ['label' => 'Mesagerie', 'example' => 'cate mesaje necitite sunt'],
            ['label' => 'Website CMS', 'example' => 'cate pagini site sunt'],
            ['label' => 'Badge bulk', 'example' => 'badge HOT toate produsele'],
            ['label' => 'Edit titlu + categorie', 'example' => 'edit titlu 30204A adauga categorie la final'],
            ['label' => 'Repara job import', 'example' => 'repara job blocat import'],
        ];
    }
}
