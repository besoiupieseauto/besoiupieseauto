<?php

declare(strict_types=1);

namespace Evasystem\Services\SupplierSearch\Clients;

use Evasystem\Services\SupplierSearch\SupplierSearchConfig;

final class AutonetClient
{
    public static function applyPrefix(string $brand, string $code): string
    {
        $brand = strtoupper($brand);

        return match ($brand) {
            'GKN' => 'GKN' . $code,
            'KYB' => 'KY' . $code,
            'SPIDAN' => 'SP' . $code,
            'STABILUS' => 'STA' . $code,
            'NISSENS' => 'NS' . $code,
            'AIRTEX' => $code . ' AIR',
            'AUTLOG' => $code . ' AT',
            'COFLE' => $code . ' CO',
            'CS GERMANY' => $code . ' LS',
            'DAYCO' => $code . '-DY',
            'HITACHI' => $code . ' HI',
            'LESJÖFORS', 'LESJOFORS' => $code . ' LESJ',
            'LPR' => $code . ' LPR',
            'MEAT & DORIA' => $code . ' MD',
            'MEYLE' => $code . ' MY',
            'MOBILETRON' => $code . ' MB',
            'NRF' => $code . ' NRF',
            'POLCAR' => $code . ' POL',
            'PRASCO' => $code . ' PRA',
            'TEXTAR' => $code . ' TEX',
            'TOPRAN' => $code . ' TO',
            'ASSO' => $code . 'AS',
            'BILSTEIN' => $code . 'BS',
            'BUGIAD' => $code . 'BUG',
            'CIFAM' => $code . 'CF',
            'CORTECO' => $code . 'CO',
            'ELRING' => $code . 'EL',
            'ELSTOCK' => $code . 'EL',
            'FAE' => $code . 'FA',
            'GATES' => $code . '-GT',
            'HEPU' => $code . 'HE',
            default => $code,
        };
    }

    public static function normalizeCode(string $apiCode): string
    {
        $apiCode = preg_replace('/^(GKN|KYB|KY|SP|STA|NS|M)/i', '', $apiCode) ?? $apiCode;
        $apiCode = preg_replace(
            '/(\sAIR|\sCO|\sLS|\sHI|\sLESJ|\sLPR|\sMD|\sMY|\sMB|\sNRF|\sPOL|\sPRA|\sTEX|\sTO|\sER|AS|BS|BUG|A|CF|CO|EL|FA|-GT|AT|-DY|HEP|HE|FE|LMI)$/i',
            '',
            $apiCode
        ) ?? $apiCode;

        return $apiCode;
    }

    public static function mapAutonetLemforderPartNoToCatalogStyle(string $partNo): string
    {
        $partNo = trim($partNo);
        if ($partNo === '' || !preg_match('/LMI$/i', $partNo)) {
            return $partNo;
        }

        return (string) preg_replace('/LMI$/i', ' 01', $partNo);
    }

    /** @param array<int, array<string, mixed>> $items @return array{success:bool,data:array<int,mixed>,error:string,status:int} */
    public function getDeliveryData(array $items): array
    {
        $taxCode = SupplierSearchConfig::autonetTaxCode();
        $token = SupplierSearchConfig::autonetSecurityToken();
        if ($taxCode === '' || $token === '') {
            return ['success' => false, 'data' => [], 'error' => 'Credențiale Autonet lipsă (AUTONET_TAX_CODE / AUTONET_SECURITY_TOKEN).', 'status' => 0];
        }

        $url = SupplierSearchConfig::autonetBaseUrl() . '/GetDeliveryData';
        $headers = [
            'TAX-CODE: ' . $taxCode,
            'SECURITY-TOKEN: ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $branch = SupplierSearchConfig::autonetBranch();
        if ($branch !== '') {
            $headers[] = 'BRANCH: ' . $branch;
        }

        $payload = json_encode($items, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = trim((string) curl_error($ch));
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['success' => false, 'data' => [], 'error' => $error !== '' ? $error : 'Răspuns gol Autonet', 'status' => $status];
        }

        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'data' => is_array($decoded) ? $decoded : [], 'error' => 'HTTP ' . $status, 'status' => $status];
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }
        if ($decoded !== [] && !isset($decoded[0])) {
            $decoded = [$decoded];
        }

        return ['success' => true, 'data' => $decoded, 'error' => '', 'status' => $status];
    }
}
