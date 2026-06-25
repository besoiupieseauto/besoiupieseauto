<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

/**
 * Mapare culoare disponibilitate (hex) — port din Laravel SearchingController.
 */
final class OrderDeliveryColorMapper
{
    /** @param array<string, mixed> $item */
    public function colorFromCartItem(array $item): string
    {
        $supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
        if ($supplier === 'site_produse') {
            return '7CFC00';
        }

        $plantraw = (string) ($item['plantraw'] ?? '');
        $delivery = $item['delivery'] ?? '';
        $livrare = (string) ($item['livrare'] ?? '');

        if ($supplier === 'materom') {
            return $this->materomColor($plantraw, $livrare, $delivery);
        }
        if ($supplier === 'autopartner') {
            return $this->autopartnerColor($item, $livrare);
        }
        if ($supplier === 'autonet' || $supplier === 'autototal') {
            return $this->datedSupplierColor($delivery, $livrare);
        }

        return 'FF0000';
    }

    private function materomColor(string $plantraw, string $livrare, mixed $delivery): string
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower($value, 'UTF-8');

            return str_replace(['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'], ['a', 'a', 'i', 's', 's', 't', 't'], $value);
        };

        $plantrawNormalized = $normalize($plantraw);
        $livrareNormalized = $normalize($livrare);
        $deliveryInfo = is_array($delivery) ? (string) ($delivery['info_text'] ?? '') : (string) $delivery;
        $combined = trim($livrareNormalized . ' ' . $normalize($deliveryInfo));

        if (str_contains($plantrawNormalized, 'timisoara')) {
            return '7CFC00';
        }
        if (str_contains($plantrawNormalized, 'centru logistic')) {
            return 'ADD8E6';
        }
        if (preg_match('/\b(azi|astazi)\b/u', $combined)) {
            return '7CFC00';
        }
        if (preg_match('/\bmaine\b/u', $combined)) {
            return 'ADD8E6';
        }
        if (preg_match('/(\d+)\s*(?:-|to)?\s*(\d+)?\s*zile/iu', $combined, $matches)) {
            $fromDays = (int) ($matches[1] ?? 0);
            if ($fromDays === 2) {
                return 'F5A000';
            }
            if ($fromDays > 3) {
                return 'FF0000';
            }
        }

        return 'FF0000';
    }

    /** @param array<string, mixed> $item */
    private function autopartnerColor(array $item, string $livrare): string
    {
        $deptCode = trim((string) ($item['departamentCode'] ?? $item['departamentcode'] ?? ''));
        if ($deptCode === 'CN') {
            return 'ADD8E6';
        }
        if ($deptCode === '120' || $deptCode === '72') {
            return 'F5A000';
        }

        $livrareLower = mb_strtolower($livrare, 'UTF-8');
        if (str_contains($livrareLower, 'maine') || str_contains($livrareLower, 'mâine')) {
            return 'ADD8E6';
        }
        if (str_contains($livrareLower, 'poimâine') || str_contains($livrareLower, '2 zile')) {
            return 'F5A000';
        }

        return 'FF0000';
    }

    private function datedSupplierColor(mixed $delivery, string $livrare): string
    {
        $deliveryText = is_array($delivery) ? (string) ($delivery['info_text'] ?? '') : (string) $delivery;
        if ($deliveryText === '') {
            return 'FF0000';
        }

        $deliveryDate = null;
        if (preg_match('/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i', $deliveryText, $matches)) {
            try {
                $deliveryDate = new \DateTime($matches[1]);
            } catch (\Exception) {
            }
        }
        if ($deliveryDate === null && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $deliveryText, $matches)) {
            try {
                $deliveryDate = new \DateTime($matches[3] . '-' . $matches[2] . '-' . $matches[1]);
            } catch (\Exception) {
            }
        }

        if ($deliveryDate instanceof \DateTime) {
            $deliveryDate->setTime(0, 0, 0);
            $today = new \DateTime('today');
            $daysDiff = (int) $today->diff($deliveryDate)->format('%r%a');
            if ($daysDiff === 0) {
                return '7CFC00';
            }
            if ($daysDiff === 1) {
                return 'ADD8E6';
            }
            if ($daysDiff === 2) {
                return 'F5A000';
            }
            if ($daysDiff >= 3) {
                return 'FF0000';
            }
        }

        $livrareLower = mb_strtolower($livrare, 'UTF-8');
        if (str_contains($livrareLower, 'azi')) {
            return '7CFC00';
        }
        if (str_contains($livrareLower, 'maine') || str_contains($livrareLower, 'mâine')) {
            return 'ADD8E6';
        }

        return 'FF0000';
    }

    public function supplierShortCode(string $supplier): string
    {
        $supplier = strtolower(trim($supplier));

        return match ($supplier) {
            'materom' => 'MA',
            'autototal' => 'AT',
            'autonet' => 'AN',
            'autopartner' => 'AP',
            'elit', 'site_produse' => 'ET',
            default => 'MA',
        };
    }
}
