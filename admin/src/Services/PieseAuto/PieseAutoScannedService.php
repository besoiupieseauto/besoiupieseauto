<?php

declare(strict_types=1);

namespace Evasystem\Services\PieseAuto;

use Config\Database;
use PDO;

/**
 * Magazie produse pentru publicare PieseAuto — din tabela produse EvaSystem.
 */
final class PieseAutoScannedService
{
    /**
     * @return list<array{
     *   id:string,title:string,description:string,price:float,category_name:string,
     *   category_full:string,car_brand:string,image_url:string,images:list<string>,
     *   pieseauto_category:string,updated_at:string
     * }>
     */
    public function scannedItems(string $q = '', int $limit = 200): array
    {
        $pdo = Database::getDB();
        $stmt = $pdo->query(
            "SELECT randomn_id, pName, pNote, pPrice, pCategory, pSubcategory,
                    pMarca, pImages, pCode, id
             FROM produse
             WHERE status IS NULL OR status <> '0'
             ORDER BY id DESC
             LIMIT " . max(1, min(500, $limit))
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = [];

        foreach ($rows as $row) {
            $title = trim((string) ($row['pName'] ?? ''));
            if ($title === '') {
                continue;
            }

            $description = trim((string) ($row['pNote'] ?? ''));
            if ($description === '') {
                $description = $title;
            }

            $images = $this->parseImages((string) ($row['pImages'] ?? ''));
            $category = trim((string) ($row['pSubcategory'] ?? ''));
            if ($category === '') {
                $category = trim((string) ($row['pCategory'] ?? 'Alte piese de caroserie'));
            }
            if ($category === '') {
                $category = 'Alte piese de caroserie';
            }

            $price = (float) preg_replace('/[^\d.,]/', '', str_replace(',', '.', (string) ($row['pPrice'] ?? '0')));

            $items[] = [
                'id' => (string) ($row['randomn_id'] ?? ''),
                'title' => $title,
                'description' => $description,
                'price' => $price > 0 ? $price : 100.0,
                'category_name' => $category,
                'category_full' => trim((string) ($row['pCategory'] ?? '')) . ($category !== '' ? ' · ' . $category : ''),
                'car_brand' => trim((string) ($row['pMarca'] ?? '')),
                'cod_oem' => trim((string) ($row['pCode'] ?? '')),
                'image_url' => $images[0] ?? '',
                'images' => $images,
                'pieseauto_category' => $category,
                'updated_at' => (string) ($row['id'] ?? ''),
            ];
        }

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
                $haystack = mb_strtolower(
                    ($item['title'] ?? '') . ' ' .
                    ($item['car_brand'] ?? '') . ' ' .
                    ($item['category_name'] ?? '') . ' ' .
                    ($item['description'] ?? ''),
                    'UTF-8'
                );

                return mb_strpos($haystack, $needle) !== false;
            }));
        }

        return array_slice($items, 0, max(1, min(500, $limit)));
    }

    /** @return list<string> */
    private function parseImages(string $raw): array
    {
        $images = [];
        if ($raw === '') {
            return $images;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $img) {
                if (is_string($img) && $img !== '') {
                    $images[] = $img;
                } elseif (is_array($img) && !empty($img['url'])) {
                    $images[] = (string) $img['url'];
                }
            }
        } elseif (filter_var($raw, FILTER_VALIDATE_URL)) {
            $images[] = $raw;
        }

        return array_values(array_unique(array_filter($images)));
    }
}
