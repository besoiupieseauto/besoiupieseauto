<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Builders;

use PDO;

final class AutototalSearchBuilder
{
    /** @return array<int, array{itemkey:string,quantity:int,targets:array<int,array<string,mixed>>}> */
    public function buildRequests(string $query, string $rawQuery, ?PDO $pdo, int $maxRequests = 18): array
    {
        $requests = [];
        $requestIndexByKey = [];
        $normalizedQuery = preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query;

        $addRequest = function (
            string $itemkey,
            string $baseCode,
            ?string $manufacturer = null,
            ?string $dbName = null,
            ?string $supBrand = null
        ) use (&$requests, &$requestIndexByKey): void {
            $itemkey = trim($itemkey);
            if ($itemkey === '') {
                return;
            }
            $baseCode = trim($baseCode);
            if ($baseCode === '') {
                return;
            }
            $requestKey = $itemkey;
            if (!isset($requestIndexByKey[$requestKey])) {
                $requestIndexByKey[$requestKey] = count($requests);
                $requests[] = [
                    'itemkey' => $itemkey,
                    'quantity' => 2,
                    'targets' => [],
                    '_target_seen' => [],
                ];
            }
            $idx = $requestIndexByKey[$requestKey];
            $target = [
                'base_code' => $baseCode,
                'manufacturer' => $manufacturer !== null ? trim($manufacturer) : null,
                'db_name' => $dbName,
                'sup_brand' => $supBrand !== null ? trim($supBrand) : null,
            ];
            $targetKey = $target['base_code'] . '|' . (string) $target['sup_brand'];
            if (!isset($requests[$idx]['_target_seen'][$targetKey])) {
                $requests[$idx]['_target_seen'][$targetKey] = true;
                $requests[$idx]['targets'][] = $target;
            }
        };

        $addRequest($rawQuery !== '' ? $rawQuery : $query, $normalizedQuery);

        if ($pdo === null) {
            return array_slice($this->stripInternalKeys($requests), 0, $maxRequests);
        }

        $stmt = $pdo->prepare('SELECT code_parts, mainart_code_parts, mainart_brands, brands FROM parts_catalog WHERE code_parts = ?');
        $stmt->execute([$query]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $candidateCodes = [];
        foreach ($rows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            if ($mainart === '') {
                continue;
            }
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart) ?? '';
            if ($baseCode === '') {
                continue;
            }
            $originalCode = $mainart;
            if (($row->mainart_brands ?? '') === 'INA') {
                $originalCode = str_replace(' ', '', $originalCode);
            }
            $candidateCodes[$mainart] = true;
            $candidateCodes[$originalCode] = true;
        }

        $branduriByCodSursa = [];
        $itemkeyByArticle = [];
        if ($candidateCodes !== []) {
            $codes = array_keys($candidateCodes);
            $ph = implode(',', array_fill(0, count($codes), '?'));

            $stmtBp = $pdo->prepare(
                "SELECT cod_sursa, itemkey, sup_brand FROM autototal_branduri_proprii WHERE cod_sursa IN ($ph)"
            );
            $stmtBp->execute($codes);
            foreach ($stmtBp->fetchAll(PDO::FETCH_OBJ) as $bpRow) {
                $cod = (string) ($bpRow->cod_sursa ?? '');
                if ($cod === '') {
                    continue;
                }
                $branduriByCodSursa[$cod][] = $bpRow;
            }

            $stmtIk = $pdo->prepare(
                "SELECT art_article_nr, itemkey FROM autototal_data WHERE art_article_nr IN ($ph)"
            );
            $stmtIk->execute($codes);
            foreach ($stmtIk->fetchAll(PDO::FETCH_OBJ) as $ikRow) {
                $art = (string) ($ikRow->art_article_nr ?? '');
                $ik = (string) ($ikRow->itemkey ?? '');
                if ($art !== '' && $ik !== '') {
                    $itemkeyByArticle[$art] = $ik;
                }
            }
        }

        foreach ($rows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            if ($mainart === '') {
                continue;
            }
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart) ?? '';
            if ($baseCode === '') {
                continue;
            }
            $manufacturer = isset($row->mainart_brands) ? (string) $row->mainart_brands : null;
            $dbName = isset($row->brands) ? (string) $row->brands : null;

            $originalCode = $mainart;
            if (($manufacturer ?? '') === 'INA') {
                $originalCode = str_replace(' ', '', $originalCode);
            }

            $branduriRows = $branduriByCodSursa[$mainart] ?? [];
            if ($originalCode !== $mainart) {
                $branduriRows = array_merge($branduriRows, $branduriByCodSursa[$originalCode] ?? []);
            }

            foreach ($branduriRows as $bp) {
                $addRequest(
                    (string) ($bp->itemkey ?? ''),
                    $baseCode,
                    $manufacturer,
                    $dbName,
                    isset($bp->sup_brand) ? (string) $bp->sup_brand : null
                );
            }

            $itemkey = $itemkeyByArticle[$mainart] ?? '';
            if ($itemkey === '' && $originalCode !== $mainart) {
                $itemkey = $itemkeyByArticle[$originalCode] ?? '';
            }
            if ($itemkey !== '') {
                $addRequest($itemkey, $baseCode, $manufacturer, $dbName);
            }
        }

        return array_slice($this->stripInternalKeys($requests), 0, $maxRequests);
    }

    /** @param array<int, array<string, mixed>> $requests @return array<int, array<string, mixed>> */
    private function stripInternalKeys(array $requests): array
    {
        foreach ($requests as &$request) {
            unset($request['_target_seen']);
        }
        unset($request);

        return $requests;
    }
}
