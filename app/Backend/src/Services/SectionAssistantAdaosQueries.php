<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Throwable;

final class SectionAssistantAdaosQueries
{
    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        try {
            $service = new AdaosComercialService();
            $rules = $service->getAll();
            $active = $service->getActiveRules();
            $ruleRows = [];
            foreach ($active as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $filters = [];
                if (!empty($rule['category_filter'])) {
                    $filters[] = 'Cat: ' . $rule['category_filter'];
                }
                if (!empty($rule['brand_filter'])) {
                    $filters[] = 'Brand: ' . $rule['brand_filter'];
                }
                if ($rule['price_min'] !== null && $rule['price_min'] !== '') {
                    $filters[] = 'Peste ' . $rule['price_min'] . ' RON';
                }
                if ($rule['price_max'] !== null && $rule['price_max'] !== '') {
                    $filters[] = 'Max ' . $rule['price_max'] . ' RON';
                }
                $type = (string) ($rule['adjustment_type'] ?? 'percentage');
                $val = (float) ($rule['adjustment_value'] ?? 0);
                $adj = $type === 'fixed'
                    ? '+' . number_format($val, 2, ',', '.') . ' RON'
                    : '+' . number_format($val, 2, ',', '.') . '%';
                $ruleRows[] = [
                    'id' => (int) ($rule['id'] ?? 0),
                    'name' => (string) ($rule['name'] ?? 'Regula'),
                    'adjustment' => $adj,
                    'filters' => $filters !== [] ? implode(' | ', $filters) : 'Toate produsele',
                ];
            }

            return [
                'vat_percent' => $service->getCommercialVatPercent(),
                'global_markup_percent' => $service->getGlobalCommercialMarkupPercent(),
                'round_mode' => $service->getGlobalPriceRoundMode(),
                'round_value' => $service->getGlobalPriceRoundValue(),
                'rules_total' => count($rules),
                'rules_active' => count($active),
                'rules' => $ruleRows,
            ];
        } catch (Throwable) {
            return [
                'vat_percent' => 0,
                'global_markup_percent' => 0,
                'round_mode' => 'none',
                'round_value' => 0,
                'rules_total' => 0,
                'rules_active' => 0,
                'rules' => [],
            ];
        }
    }
}
