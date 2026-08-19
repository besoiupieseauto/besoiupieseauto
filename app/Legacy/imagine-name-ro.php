<?php

declare(strict_types=1);

/**
 * Denumiri catalog Autopartner (PL) → română.
 * Preferă art_name TecDoc când brand+cod coincid.
 */

function imagine_name_fold(string $s): string
{
    $s = str_replace(
        ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż', 'Ľ', 'ľ', 'Ť', 'ť'],
        ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'A', 'C', 'E', 'L', 'N', 'O', 'S', 'Z', 'Z', 'L', 'l', 'L', 'l'],
        $s
    );

    return strtoupper($s);
}

/** @return list<array{0:string,1:string}> */
function imagine_pl_ro_pairs(): array
{
    static $pairs = null;
    if (is_array($pairs)) {
        return $pairs;
    }

    $pairs = [
        ['ZESTAW NAPR. SW. RESORU', 'Set reparatie pivot arc foi'],
        ['ZESTAW NAPRAWCZY SWORZNIA RESORU', 'Set reparatie pivot arc foi'],
        ['ZESTAW NAPRAWCZY', 'Set reparatie'],
        ['ZESTAW NAPR.', 'Set reparatie'],
        ['SWORZEN WAHACZA', 'Pivot brat suspensie'],
        ['SWORZNIA RESORU', 'pivot arc foi'],
        ['USZCZELKA GLOWICY', 'Garnitura chiulasa'],
        ['USZCZELKA GL.', 'Garnitura chiulasa'],
        ['USZCZELKA GL ', 'Garnitura chiulasa '],
        ['USZCZELKA KOL. SS/WY', 'Garnitura galerie admisie/evacuare'],
        ['USZCZELKA KOL-RURA', 'Garnitura galerie-teava'],
        ['USZCZELKA KOL.', 'Garnitura galerie'],
        ['USZCZELKA POKRYWY', 'Garnitura capac'],
        ['USZCZELKA', 'Garnitura'],
        ['USZCZELNIACZE', 'Simeringuri'],
        ['USZCZELNIACZ', 'Simering'],
        ['PODUSZKA SILNIKA', 'Tampon motor'],
        ['PODUSZKA SIL.', 'Tampon motor'],
        ['PODUSZKA SKRZYNI', 'Tampon cutie viteze'],
        ['PODUSZKA', 'Tampon'],
        ['TULEJA STABILIZATORA', 'Bucsa bara stabilizatoare'],
        ['TULEJA STAB.', 'Bucsa bara stabilizatoare'],
        ['TULEJA WAHACZA', 'Bucsa brat suspensie'],
        ['TULEJA', 'Bucsa'],
        ['KLOCKI HAMULCOWE', 'Placute frana'],
        ['TARCZA HAMULCOWA', 'Disc frana'],
        ['KLOCKI', 'Placute frana'],
        ['TARCZA', 'Disc'],
        ['AMORTYZATOR', 'Amortizor'],
        ['WAHACZA', 'brat suspensie'],
        ['WAHACZ', 'Brat suspensie'],
        ['CZUJNIK', 'Senzor'],
        ['POMPA WODY', 'Pompa apa'],
        ['POMPA PALIWA', 'Pompa combustibil'],
        ['POMPA OLEJU', 'Pompa ulei'],
        ['POMPA HAMULCOWA', 'Pompa frana'],
        ['POMPKA', 'Pompa'],
        ['POMPA', 'Pompa'],
        ['FILTR OLEJU', 'Filtru ulei'],
        ['FILTR POWIETRZA', 'Filtru aer'],
        ['FILTR PALIWA', 'Filtru combustibil'],
        ['FILTR KABINOWY', 'Filtru habitaclu'],
        ['FILTR', 'Filtru'],
        ['LUSTERKO', 'Oglinda'],
        ['PASEK ROZRZADU', 'Curea distributie'],
        ['PASEK WIELOROWKOWY', 'Curea transmisie'],
        ['PASEK', 'Curea'],
        ['NAPINACZ', 'Intinzator'],
        ['OSLONA PRZEGUBU', 'Burduf planetara'],
        ['OSLONA', 'Protectie'],
        ['PRZEGUBU', 'planetara'],
        ['PRZEGUB', 'Articulatie'],
        ['CHLODNICA', 'Radiator'],
        ['PRZEWOD', 'Furtun'],
        ['SPREZYNA', 'Arc'],
        ['LOZYSKO', 'Rulment'],
        ['KONCOWKA DRAZKA KIEROWNICZEGO', 'Capat de bara'],
        ['KONC. DR. KIER.', 'Capat de bara'],
        ['LISTWA MAG', 'Banda magnetica'],
        ['LISTWA', 'Bagheta'],
        ['ZAVES VYFUKU VELKY', 'Suport esapament mare'],
        ['ZAVES VYFUKU', 'Suport esapament'],
        ['KONCOWKA', 'Capat'],
        ['LINKA', 'Cablu'],
        ['SILNIKA', 'motor'],
        ['SILNIK', 'Motor'],
        ['CEWKA', 'Bobina'],
        ['PIASTA', 'Butuc'],
        ['RESORU', 'arc foi'],
        ['RESOR', 'Arc foi'],
        ['ZAWOR', 'Supapa'],
        ['POKRYWA', 'Capac'],
        ['MOCOWANIE', 'Suport'],
        ['WIAZKA', 'Manunchi cabluri'],
        ['SPRZEGLO', 'Ambreiaj'],
        ['KOMPLET', 'Set'],
        ['ZESTAW', 'Set'],
        ['LAMPA', 'Lampa'],
        ['GENERATOR', 'Alternator'],
        ['KOMPRESOR', 'Compresor'],
        ['PODNOSNIK SZYBY', 'Macara geam'],
        ['PODNOSNIK', 'Macara geam'],
        ['SRUBA', 'Surub'],
        ['GLOWICA', 'Chiulasa'],
        ['PIERSCIEN', 'Inel'],
        ['WSPORNIK', 'Suport'],
        ['ZAWIAS', 'Balama'],
        ['ZAMEK', 'Broasca'],
        ['KABEL', 'Cablu'],
        ['ODBOJ', 'Tampon limitator'],
        ['POPYCHACZ ZAWORU', 'Culbutor supapa'],
        ['POPYCHACZ', 'Culbutor'],
        ['WKLAD REGENERACYJNY', 'Cartus regenerabil'],
        ['KAUCJA', 'Garantie / depozit'],
        ['REP. STAB.', 'Reparatie bara stabilizatoare'],
        ['SW. RESORU', 'pivot arc foi'],
        ['SWORZEN', 'Pivot'],
        ['NAPR.', 'reparatie'],
        ['STAB.', 'bara stabilizatoare'],
        ['SIL.', 'motor'],
        ['ZEW.', 'exterior'],
        ['WEW.', 'interior'],
    ];

    return $pairs;
}

function imagine_name_already_romanian(string $name): bool
{
    $fold = imagine_name_fold($name);
    $hasPl = preg_match(
        '/USZCZEL|SWORZE|TULEJA|PODUSZKA|ZESTAW|WAHACZ|CZUJNIK|KLOCKI|TARCZA HAM|AMORTYZ|OSLONA|PRZEGUB|CHLODNIC|PRZEWOD|SPREZYN|LOZYSK|PASEK |NAPINACZ|KAUCJA|WKLAD REGENER|PODNOSNIK|KONCOWKA|LUSTERKO|SILNIK|RESORU|GLOWIC/',
        $fold
    ) === 1;
    if ($hasPl) {
        return false;
    }

    return preg_match(
        '/GARNITUR|BRAT|BUCSA|FILTRU|PLACUTE|FRANA|AMORTIZOR|ETRIER|CABLU|FURTUN|LAMPA |POMPA |SENZOR|SUPORT|SET |DISC |ARC /i',
        $name
    ) === 1;
}

function imagine_pl_name_to_ro(string $name): string
{
    $name = trim($name);
    if ($name === '' || imagine_name_already_romanian($name)) {
        return $name;
    }

    $out = imagine_name_fold($name);
    $out = preg_replace('/SWORZE\S?\s+WAHACZA/u', 'SWORZEN WAHACZA', $out) ?? $out;
    $out = preg_replace('/USZCZELKA G\S?\./u', 'USZCZELKA GL.', $out) ?? $out;
    $out = preg_replace('/KO\S?C\.\s*DR\.\s*KIER\./u', 'KONC. DR. KIER.', $out) ?? $out;
    foreach (imagine_pl_ro_pairs() as [$from, $to]) {
        $out = str_replace(imagine_name_fold($from), $to, $out);
    }

    $out = preg_replace('/\bDB\b/u', 'Mercedes', $out) ?? $out;
    $out = preg_replace('/\s+P\.\s+/u', ' fata ', $out) ?? $out;
    $out = preg_replace('/\s+T\.\s+/u', ' spate ', $out) ?? $out;
    $out = preg_replace('/\s+L\.\s+/u', ' stanga ', $out) ?? $out;
    $out = preg_replace('/\s+/u', ' ', $out) ?? $out;

    return trim($out, " \t#");
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, string> brand|code_norm => art_name
 */
function imagine_tecdoc_names_for_rows(PDO $pdo, array $rows): array
{
    $brands = [];
    $codes = [];
    foreach ($rows as $row) {
        $brand = imagine_catalog_brand((string) ($row['brand'] ?? ''));
        $code = imagine_catalog_norm((string) ($row['code_norm'] ?? ''));
        if ($brand !== '' && $brand !== '999' && $code !== '') {
            $brands[$brand] = $brand;
            $codes[$code] = $code;
        }
    }
    if ($brands === [] || $codes === []) {
        return [];
    }

    $inBrands = implode(',', array_map([$pdo, 'quote'], array_values($brands)));
    $inCodes = implode(',', array_map([$pdo, 'quote'], array_values($codes)));
    $norm = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(%s),' ',''),'-',''),'.',''),'/','')";
    $sql = 'SELECT UPPER(REPLACE(b.name, \' \', \'\')) AS brand_key, '
        . sprintf($norm, 't.art_code_1') . ' AS code_key, t.art_name '
        . 'FROM besoiu_tecdoc_base.products t '
        . 'JOIN besoiu_tecdoc_base.brands b ON b.id = t.brand_id '
        . 'WHERE UPPER(REPLACE(b.name, \' \', \'\')) IN (' . $inBrands . ') '
        . 'AND (' . sprintf($norm, 't.art_code_1') . ' IN (' . $inCodes . ') '
        . 'OR ' . sprintf($norm, 'IFNULL(t.art_code_2,\'\')') . ' IN (' . $inCodes . '))';

    try {
        $map = [];
        foreach ($pdo->query($sql) ?: [] as $hit) {
            $name = trim((string) ($hit['art_name'] ?? ''));
            $key = (string) $hit['brand_key'] . '|' . (string) $hit['code_key'];
            if ($name !== '' && !isset($map[$key])) {
                $map[$key] = $name;
            }
        }

        return $map;
    } catch (Throwable) {
        return [];
    }
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function imagine_rows_with_romanian_names(PDO $pdo, array $rows): array
{
    $tecdoc = imagine_tecdoc_names_for_rows($pdo, $rows);
    foreach ($rows as &$row) {
        $brand = imagine_catalog_brand((string) ($row['brand'] ?? ''));
        $code = imagine_catalog_norm((string) ($row['code_norm'] ?? ''));
        $catalog = trim((string) ($row['product_name'] ?? ''));
        $fromTecdoc = $tecdoc[$brand . '|' . $code] ?? '';
        $row['product_name'] = $fromTecdoc !== '' ? $fromTecdoc : imagine_pl_name_to_ro($catalog);
    }
    unset($row);

    return $rows;
}
