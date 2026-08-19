<?php

declare(strict_types=1);

/**
 * Denumire produs = din fișierele XLS/TecDoc (imagine_poze + besoiu_tecdoc_base).
 * Fără traducere inventată.
 */

function imagine_xls_name_key(string $brand, string $code): string
{
    return imagine_catalog_brand($brand) . '|' . imagine_catalog_norm($code);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, string>
 */
function imagine_xls_names_for_rows(PDO $pdo, array $rows): array
{
    $brands = [];
    $codes = [];
    foreach ($rows as $row) {
        $brand = imagine_catalog_brand((string) ($row['brand'] ?? ''));
        $code = imagine_catalog_norm((string) ($row['code_norm'] ?? ''));
        if ($brand === '' || $brand === '999' || $code === '') {
            continue;
        }
        $brands[$brand] = $brand;
        $codes[$code] = $code;
    }
    if ($brands === [] || $codes === []) {
        return [];
    }

    $inBrands = implode(',', array_map([$pdo, 'quote'], array_values($brands)));
    $inCodes = implode(',', array_map([$pdo, 'quote'], array_values($codes)));
    $map = [];

    try {
        $sqlPoze = 'SELECT brand, code_norm, name FROM imagine_poze.products'
            . ' WHERE brand IN (' . $inBrands . ') AND code_norm IN (' . $inCodes . ')'
            . ' AND IFNULL(name,\'\') <> \'\'';
        foreach ($pdo->query($sqlPoze) ?: [] as $hit) {
            $key = imagine_xls_name_key((string) $hit['brand'], (string) $hit['code_norm']);
            $name = trim((string) ($hit['name'] ?? ''));
            if ($name !== '') {
                $map[$key] = $name;
            }
        }
    } catch (Throwable) {
    }

    $norm = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(%s),' ',''),'-',''),'.',''),'/','')";
    try {
        $sqlTd = 'SELECT UPPER(REPLACE(b.name, \' \', \'\')) AS brand_key, '
            . sprintf($norm, 't.art_code_1') . ' AS code_key, t.art_name'
            . ' FROM besoiu_tecdoc_base.products t'
            . ' JOIN besoiu_tecdoc_base.brands b ON b.id = t.brand_id'
            . ' WHERE UPPER(REPLACE(b.name, \' \', \'\')) IN (' . $inBrands . ')'
            . ' AND (' . sprintf($norm, 't.art_code_1') . ' IN (' . $inCodes . ')'
            . ' OR ' . sprintf($norm, 'IFNULL(t.art_code_2,\'\')') . ' IN (' . $inCodes . '))'
            . ' AND IFNULL(t.art_name,\'\') <> \'\'';
        foreach ($pdo->query($sqlTd) ?: [] as $hit) {
            $key = (string) $hit['brand_key'] . '|' . (string) $hit['code_key'];
            $name = trim((string) ($hit['art_name'] ?? ''));
            if ($name !== '' && !isset($map[$key])) {
                $map[$key] = $name;
            }
        }
    } catch (Throwable) {
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function imagine_rows_with_romanian_names(PDO $pdo, array $rows): array
{
    $xls = imagine_xls_names_for_rows($pdo, $rows);
    foreach ($rows as &$row) {
        $key = imagine_xls_name_key((string) ($row['brand'] ?? ''), (string) ($row['code_norm'] ?? ''));
        $fromXls = trim((string) ($xls[$key] ?? ''));
        if ($fromXls !== '') {
            $row['product_name'] = $fromXls;
        }
    }
    unset($row);

    return $rows;
}
