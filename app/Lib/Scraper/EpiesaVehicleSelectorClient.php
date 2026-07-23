<?php

declare(strict_types=1);

/**
 * Client JSON pentru selectorul vehicul ePiesa (/api/selector.php).
 * Lanț: marci → modele → versiuni → motorizări → URL catalog.
 */
final class EpiesaVehicleSelectorClient
{
    private const BASE = 'https://www.epiesa.ro';

    /** @param array<string, scalar|null> $params @return list<array<string, mixed>>|array<string, mixed> */
    public static function api(array $params): array
    {
        $url = self::BASE . '/api/selector.php?' . http_build_query($params);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL indisponibil pentru selector ePiesa.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'BesoiuEpiesaVehicleCrawler/1.0',
            CURLOPT_REFERER => self::BASE . '/',
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            throw new RuntimeException('Răspuns gol de la selector ePiesa.');
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException('JSON invalid selector ePiesa: ' . mb_substr($body, 0, 120));
        }

        if (isset($json['error']) && is_string($json['error']) && $json['error'] !== '') {
            throw new RuntimeException('Selector ePiesa: ' . $json['error']);
        }

        if ($code >= 400) {
            throw new RuntimeException('Selector ePiesa HTTP ' . $code);
        }

        return $json;
    }

    /** @return list<array{id:int,nume:string,top?:bool}> */
    public static function marci(): array
    {
        $rows = self::api(['pas' => 'marci']);
        if (!array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $nume = trim((string) ($row['nume'] ?? ''));
            if ($id <= 0 || $nume === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nume' => $nume,
                'top' => !empty($row['top']),
            ];
        }

        return $out;
    }

    /** @return list<array{nume:string}> */
    public static function modele(int $mfaId): array
    {
        $rows = self::api(['pas' => 'modele', 'mfa' => $mfaId]);
        if (!array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nume = trim((string) ($row['nume'] ?? ''));
            if ($nume === '') {
                continue;
            }
            $out[] = ['nume' => $nume];
        }

        return $out;
    }

    /** @return list<array{id:int,label:string}> */
    public static function versiuni(int $mfaId, string $modelName): array
    {
        $rows = self::api(['pas' => 'versiuni', 'mfa' => $mfaId, 'ds' => $modelName]);
        if (!array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            if ($id <= 0 || $label === '') {
                continue;
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    /** @return list<array{id:int,label:string}> */
    public static function motorizari(int $modId): array
    {
        $rows = self::api(['pas' => 'motorizari', 'mod' => $modId]);
        if (!array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            if ($id <= 0 || $label === '') {
                continue;
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    public static function resolveCatalogUrl(string $marcaNume, string $modelNume, int $modId, int $typId): string
    {
        $res = self::api([
            'pas' => 'url',
            'marca' => $marcaNume,
            'model' => $modelNume,
            'mod' => $modId,
            'typid' => $typId,
        ]);

        $path = trim((string) ($res['url'] ?? ''));
        if ($path === '') {
            throw new RuntimeException('URL catalog lipsă pentru combinația vehicul.');
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim(self::BASE, '/') . '/' . ltrim($path, '/');
    }

    public static function sleepMs(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }
        usleep(min(2_000_000, $ms * 1000));
    }
}
