<?php

namespace App\Services\SupplierSearchNew;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartsCatalogLookup
{
    /**
     * Get a single row from parts_catalog by code (code_parts or mainart_code_parts, normalized).
     */
    public function getByCode(string $normalizedCode): ?object
    {
        $row = DB::table('parts_catalog')
            ->where('code_parts', $normalizedCode)
            ->orWhere('code_parts_advanced', $normalizedCode)
            ->orWhere('mainart_code_parts', $normalizedCode)
            ->first();

        if ($row) {
            return $row;
        }

        $prefix = substr($normalizedCode, 0, min(6, strlen($normalizedCode)));
        $candidates = DB::table('parts_catalog')
            ->where('code_parts', 'LIKE', $prefix . '%')
            ->orWhere('code_parts_advanced', 'LIKE', $prefix . '%')
            ->orWhere('mainart_code_parts', 'LIKE', $prefix . '%')
            ->limit(120)
            ->get();

        $normalize = function ($v) {
            return preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $v);
        };

        foreach ($candidates as $candidate) {
            if ($normalize($candidate->code_parts ?? '') === $normalizedCode
                || $normalize($candidate->code_parts_advanced ?? '') === $normalizedCode
                || $normalize($candidate->mainart_code_parts ?? '') === $normalizedCode) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Get all rows from parts_catalog where code_parts (or mainart_code_parts) matches the normalized code.
     * Used to gather additional codes to send to supplier APIs (like in the old supplier search).
     */
    public function getAllRowsByCode(string $normalizedCode): Collection
    {
        $rows = DB::table('parts_catalog')
            ->where('code_parts', $normalizedCode)
            ->orWhere('code_parts_advanced', $normalizedCode)
            ->orWhere('mainart_code_parts', $normalizedCode)
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows;
        }

        $prefix = substr($normalizedCode, 0, min(6, strlen($normalizedCode)));
        $candidates = DB::table('parts_catalog')
            ->where('code_parts', 'LIKE', $prefix . '%')
            ->orWhere('code_parts_advanced', 'LIKE', $prefix . '%')
            ->orWhere('mainart_code_parts', 'LIKE', $prefix . '%')
            ->limit(200)
            ->get();

        $normalize = function ($v) {
            return preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $v);
        };

        return $candidates->filter(function ($candidate) use ($normalize, $normalizedCode) {
            return $normalize($candidate->code_parts ?? '') === $normalizedCode
                || $normalize($candidate->code_parts_advanced ?? '') === $normalizedCode
                || $normalize($candidate->mainart_code_parts ?? '') === $normalizedCode;
        })->values();
    }

    /**
     * Get rows from parts_catalog for Autonet by code fields.
     * Do not hard-filter by brand list here; data may contain spacing/variant brands
     * and we should not drop valid rows before building request items.
     *
     * @param string[] $brands Kept for signature compatibility.
     */
    public function getRowsForAutonet(string $query, string $rawQuery, array $brands): Collection
    {
        $rawNormalized = preg_replace('/[.\s\-\/|\\\\]+/', '', $rawQuery);

        return DB::table('parts_catalog')
            ->where(function ($q) use ($query, $rawQuery, $rawNormalized) {
                $q->where('code_parts', $query)
                    ->orWhere('code_parts_advanced', $query);
                if ($rawQuery !== '' && $rawQuery !== $query) {
                    $q->orWhere('code_parts', $rawQuery)
                        ->orWhere('code_parts_advanced', $rawQuery);
                }
                if ($rawNormalized !== '' && $rawNormalized !== $query) {
                    $q->orWhere('code_parts', $rawNormalized)
                        ->orWhere('code_parts_advanced', $rawNormalized);
                }
            })
            ->get();
    }
}
