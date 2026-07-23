<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Generează text descriptiv (~400 cuvinte) pentru tab-ul Descriere — propoziții complete în română.
 */
final class ProductProseDescriptionService
{
    public const DEFAULT_TARGET_WORDS = 400;

    /**
     * @param array<string, mixed> $ctx Context din ProductDescriptionTabsService::contextFromProduct
     */
    public function buildHtml(array $ctx, int $targetWords = self::DEFAULT_TARGET_WORDS): string
    {
        $targetWords = max(150, min(800, $targetWords));
        $paragraphs = $this->buildParagraphs($ctx, $targetWords);
        if ($paragraphs === []) {
            return '';
        }

        $html = '<div class="besoiu-product-tab-prose">';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . $this->h($paragraph) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<string>
     */
    private function buildParagraphs(array $ctx, int $targetWords): array
    {
        $data = $this->extractData($ctx);
        if ($data['name'] === '' && $data['brand'] === '' && $data['code'] === '') {
            return [];
        }

        $paragraphs = [];
        $paragraphs[] = $this->paragraphIntro($data);
        $paragraphs[] = $this->paragraphCategoryRole($data);

        $specParagraph = $this->paragraphTechnicalSpecs($data, $ctx);
        if ($specParagraph !== '') {
            $paragraphs[] = $specParagraph;
        }

        $compatParagraph = $this->paragraphCompatibility($data);
        if ($compatParagraph !== '') {
            $paragraphs[] = $compatParagraph;
        }

        if ($data['oem'] !== '') {
            $paragraphs[] = $this->paragraphOem($data);
        }

        $paragraphs[] = $this->paragraphQuality($data);
        $paragraphs[] = $this->paragraphCommerce($data);

        $paragraphs = array_values(array_filter($paragraphs, static fn (string $p): bool => trim($p) !== ''));

        return $this->expandToTargetWords($paragraphs, $data, $targetWords);
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<string, string>
     */
    private function extractData(array $ctx): array
    {
        $fieldValues = is_array($ctx['field_values'] ?? null) ? $ctx['field_values'] : [];

        $name = trim((string) ($fieldValues['denumire'] ?? $ctx['piece_name'] ?? ''));
        $brand = trim((string) ($fieldValues['brand_piesa'] ?? $ctx['pBrand'] ?? ''));
        $code = trim((string) ($fieldValues['cod'] ?? $ctx['pCode'] ?? ''));
        $category = trim((string) ($fieldValues['categorie'] ?? $ctx['pCategory'] ?? ''));
        $subcategory = trim((string) ($fieldValues['subcategorie'] ?? $ctx['pSubcategory'] ?? ''));
        $position = trim((string) ($fieldValues['pozitie'] ?? ''));
        $vehicle = trim((string) ($fieldValues['vehicul_manual'] ?? ''));
        $compatText = trim((string) ($fieldValues['compat_text'] ?? ''));
        $oem = trim((string) ($fieldValues['oem_codes'] ?? $ctx['pOem'] ?? ''));
        $shipping = trim((string) ($fieldValues['livrare'] ?? $ctx['pShipping'] ?? ''));
        $warranty = trim((string) ($fieldValues['garantie'] ?? $ctx['pWarranty'] ?? ''));
        $retur = trim((string) ($fieldValues['retur'] ?? $ctx['pReturn'] ?? ''));
        $condition = trim((string) ($fieldValues['stare'] ?? 'Nou'));

        if ($shipping === '') {
            $shipping = 'livrare rapidă în România sau ridicare locală din magazin';
        }
        if ($warranty === '') {
            $warranty = 'garanție conform politicii magazinului Besoiu Piese Auto';
        }
        if ($retur === '') {
            $retur = 'posibilitate de retur în termenii legali și ai magazinului';
        }

        $pieceLabel = $subcategory !== '' ? $subcategory : ($category !== '' ? $category : $name);
        $compatSummary = $this->summarizeCompatEntries(is_array($ctx['entries'] ?? null) ? $ctx['entries'] : []);
        if ($compatSummary === '' && $compatText !== '') {
            $compatSummary = $compatText;
        }
        if ($compatSummary === '' && $vehicle !== '') {
            $compatSummary = $vehicle;
        }

        return [
            'name' => $name,
            'brand' => $brand,
            'code' => $code,
            'category' => $category,
            'subcategory' => $subcategory,
            'piece_label' => $pieceLabel,
            'position' => $position,
            'vehicle' => $vehicle,
            'compat_summary' => $compatSummary,
            'oem' => $oem,
            'shipping' => $shipping,
            'warranty' => $warranty,
            'retur' => $retur,
            'condition' => $condition !== '' ? $condition : 'Nou',
            'spec_lines' => $this->extractSpecLines($ctx),
        ];
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<string>
     */
    private function extractSpecLines(array $ctx): array
    {
        $lines = [];
        $html = trim((string) ($ctx['description_html'] ?? ''));
        if ($html !== '' && preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim(strip_tags($match[1]));
                $value = trim(strip_tags($match[2]));
                if ($label === '' || $value === '') {
                    continue;
                }
                $labelLower = mb_strtolower($label, 'UTF-8');
                if (str_contains($labelLower, 'descrier') && str_contains($labelLower, 'compatibil')) {
                    continue;
                }
                if (in_array($labelLower, ['producătorul', 'producatorul', 'număr articol', 'numar articol', 'condiție', 'conditie'], true)) {
                    continue;
                }
                $lines[] = $label . ': ' . $value;
            }
        }

        if ($lines === []) {
            $plain = trim((string) ($ctx['specs_text'] ?? ''));
            if ($plain !== '' && function_exists('tecdoc_desc_is_plain_spec_sheet') && tecdoc_desc_is_plain_spec_sheet($plain)
                && function_exists('tecdoc_desc_plain_text_to_rows')) {
                foreach (tecdoc_desc_plain_text_to_rows($plain) as $row) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $value = trim((string) ($row['value'] ?? ''));
                    if ($label !== '' && $value !== '') {
                        $lines[] = $label . ': ' . $value;
                    }
                }
            }
        }

        return array_slice(array_values(array_unique($lines)), 0, 12);
    }

    /** @param list<array<string, mixed>> $entries */
    private function summarizeCompatEntries(array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        $brands = [];
        $models = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $brand = trim((string) ($entry['CAR_BRAND'] ?? ''));
            $model = trim((string) ($entry['CAR_MODEL'] ?? ''));
            if ($brand !== '') {
                $brands[$brand] = true;
            }
            if ($model !== '') {
                $models[$model] = true;
            }
        }

        $brandList = array_slice(array_keys($brands), 0, 6);
        $modelList = array_slice(array_keys($models), 0, 8);
        if ($brandList !== [] && $modelList !== []) {
            return 'vehicule de la ' . $this->naturalList($brandList)
                . ', inclusiv modele precum ' . $this->naturalList($modelList);
        }
        if ($brandList !== []) {
            return 'vehicule de la ' . $this->naturalList($brandList);
        }
        if ($modelList !== []) {
            return 'modele precum ' . $this->naturalList($modelList);
        }

        return '';
    }

    /** @param array<string, string> $data */
    private function paragraphIntro(array $data): string
    {
        $piece = $data['piece_label'] !== '' ? $data['piece_label'] : 'piesă auto';
        $brandPart = $data['brand'] !== '' ? ' produsă de ' . $this->formatBrand($data['brand']) : '';
        $codePart = $data['code'] !== '' ? ', cu numărul de articol ' . $data['code'] : '';
        $namePart = $data['name'] !== '' && $data['name'] !== $piece
            ? ' Denumirea completă a articolului este «' . $data['name'] . '».'
            : '';

        return 'Această ' . mb_strtolower($piece, 'UTF-8') . $brandPart . $codePart
            . ' reprezintă o componentă auto comercializată în stare ' . mb_strtolower($data['condition'], 'UTF-8')
            . ', pregătită pentru montaj profesionist sau utilizare în atelier autorizat.'
            . $namePart
            . ' Besoiu Piese Auto selectează fiecare articol astfel încât clientul să primească un produs original, sigilat și conform specificațiilor declarate de producător.';
    }

    /** @param array<string, string> $data */
    private function paragraphCategoryRole(array $data): string
    {
        $category = $data['category'] !== '' ? $data['category'] : 'piese auto';
        $subcategory = $data['subcategory'] !== '' ? $data['subcategory'] : $data['piece_label'];
        $positionPart = $data['position'] !== ''
            ? ' Montajul corect al componentei, în poziția «' . $data['position'] . '», contribuie direct la funcționarea optimă a ansamblului.'
            : '';

        return 'În categoria «' . $category . '», subcategoria «' . $subcategory . '» descrie rolul precis al piesei în sistemul vehiculului.'
            . ' Alegerea unei componente potrivite nu este doar o chestiune de compatibilitate mecanică, ci și de siguranță rutieră, confort la condus și durabilitate pe termen lung.'
            . $positionPart
            . ' De aceea, recomandăm verificarea detaliată a codului de articol și a listei de compatibilitate înainte de comandă.';
    }

    /**
     * @param array<string, string> $data
     * @param array<string, mixed> $ctx
     */
    private function paragraphTechnicalSpecs(array $data, array $ctx): string
    {
        $lines = $data['spec_lines'];
        if ($lines === []) {
            return '';
        }

        $sentences = [];
        $sentences[] = 'Specificațiile tehnice declarate pentru acest articol includ următoarele detalii relevante pentru montaj și identificare:';
        foreach (array_slice($lines, 0, 8) as $line) {
            $sentences[] = $line . '.';
        }
        $sentences[] = 'Aceste informații permit mecanicului să confirme rapid dacă piesa corespunde cerințelor vehiculului și ale aplicației concrete din service.';

        return implode(' ', $sentences);
    }

    /** @param array<string, string> $data */
    private function paragraphCompatibility(array $data): string
    {
        if ($data['compat_summary'] === '') {
            return 'Compatibilitatea exactă a piesei trebuie confirmată după seria de șasiu (VIN), deoarece echiparea fabricii poate varia chiar și între unități aparent identice.'
                . ' Echipa Besoiu Piese Auto vă poate ajuta gratuit cu verificarea VIN înainte de plasarea comenzii, astfel încât să evitați retururile și timpii morți în atelier.';
        }

        $vehiclePart = $data['vehicle'] !== ''
            ? ' Referința manuală indică vehiculul «' . $data['vehicle'] . '», însă lista TecDoc poate fi mai amplă.'
            : '';

        return 'Conform datelor de compatibilitate disponibile, articolul se potrivește pentru ' . $data['compat_summary'] . '.'
            . $vehiclePart
            . ' Reamintim că lista afișată poate fi parțială; compatibilitatea finală se validează obligatoriu după VIN, iar consultanții noștri stau la dispoziție pentru confirmare rapidă.';
    }

    /** @param array<string, string> $data */
    private function paragraphOem(array $data): string
    {
        $oemList = preg_split('/[,;\|]+/u', $data['oem']) ?: [];
        $oemList = array_values(array_filter(array_map('trim', $oemList)));
        $display = $oemList !== [] ? $this->naturalList(array_slice($oemList, 0, 10)) : $data['oem'];

        return 'Pentru identificare alternativă, codurile OEM și echivalențele asociate includ ' . $display . '.'
            . ' Aceste referințe sunt utile atunci când căutați piesa după catalogul constructorului auto, după factură sau după eticheta componentei demontate din vehicul.';
    }

    /** @param array<string, string> $data */
    private function paragraphQuality(array $data): string
    {
        $brandPart = $data['brand'] !== ''
            ? ' Producătorul ' . $this->formatBrand($data['brand']) . ' este recunoscut pentru standarde stricte de control al calității și pentru consistența materialelor utilizate.'
            : ' Piesa provine dintr-un flux comercial verificat, cu accent pe conformitate și trasabilitate.';

        return 'Starea comercială a produsului este «' . $data['condition'] . '», ceea ce garantează lipsa uzurii anterioare și integritatea componentei la livrare.'
            . $brandPart
            . ' Montajul trebuie realizat conform procedurilor tehnice ale vehiculului, iar după înlocuire se recomandă verificarea sistemului în condiții de siguranță.';
    }

    /** @param array<string, string> $data */
    private function paragraphCommerce(array $data): string
    {
        return 'Besoiu Piese Auto asigură ' . mb_strtolower($data['shipping'], 'UTF-8')
            . ', oferă ' . mb_strtolower($data['warranty'], 'UTF-8')
            . ' și respectă politica de ' . mb_strtolower($data['retur'], 'UTF-8')
            . '. Comanda poate fi plasată online, iar echipa noastră vă sprijină la fiecare pas — de la identificarea piesei corecte până la confirmarea compatibilității după VIN.'
            . ' Astfel, primiți nu doar un cod de articol, ci o soluție completă, explicată clar și adaptată nevoilor reale din service sau flotă.';
    }

    /**
     * @param list<string> $paragraphs
     * @param array<string, string> $data
     * @return list<string>
     */
    private function expandToTargetWords(array $paragraphs, array $data, int $targetWords): array
    {
        $extras = $this->optionalParagraphs($data);
        $idx = 0;
        while ($this->wordCount(implode(' ', $paragraphs)) < $targetWords && $idx < count($extras)) {
            $candidate = $extras[$idx];
            $idx++;
            if ($candidate === '' || in_array($candidate, $paragraphs, true)) {
                continue;
            }
            $paragraphs[] = $candidate;
        }

        return $paragraphs;
    }

    /** @param array<string, string> $data @return list<string> */
    private function optionalParagraphs(array $data): array
    {
        $brand = $data['brand'] !== '' ? $this->formatBrand($data['brand']) : 'producătorul indicat';
        $piece = $data['piece_label'] !== '' ? mb_strtolower($data['piece_label'], 'UTF-8') : 'componenta';

        return [
            'Atunci când înlocuiți ' . $piece . ', este important să respectați cu strictețe cuplul de strângere, curățarea suprafețelor de contact și eventualele instrucțiuni suplimentare din documentația ' . $brand . '.'
                . ' O montare corectă prelungește durata de viață a ansamblului și menține performanțele sistemului la nivelul așteptat de șofer.',
            'Pentru flote, ateliere partenere și clienți individuali, Besoiu Piese Auto pune accent pe claritatea informației: fiecare pagină de produs trebuie să explice ce primiți, pentru ce aplicații este indicată piesa și cum o puteți comanda rapid, fără ambiguități.',
            'Dacă comparați mai multe variante din aceeași categorie, verificați întotdeauna codul de articol, poziția de montaj și intervalul de motorizări compatibile.'
                . ' Diferențe aparent minore pot indica aplicații distincte, iar confirmarea după VIN elimină riscul unei comenzi greșite.',
            'Magazinul nostru activează pe piața românească de piese auto cu focus pe transparență, suport tehnic de bază și livrare eficientă.'
                . ' Indiferent dacă comandați pentru un autoturism personal sau pentru un parc auto, veți găsi aceeași atenție pentru detalii și aceeași disponibilitate pentru clarificări.',
            'În cazul în care aveți nevoie de factură, documente de garanție sau recomandări de montaj, echipa Besoiu Piese Auto vă stă la dispoziție prin canalele de contact afișate pe site.'
                . ' Scopul nostru este ca fiecare client să înțeleagă exact ce cumpără, înainte ca piesa să ajungă în atelier.',
        ];
    }

    /** @param list<string> $items */
    private function naturalList(array $items): string
    {
        $items = array_values(array_filter(array_map('trim', $items)));
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' și ' . $items[1];
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' și ' . $last;
    }

    private function formatBrand(string $brand): string
    {
        $brand = trim($brand);
        if ($brand === '') {
            return '';
        }

        if (mb_strtoupper($brand, 'UTF-8') === $brand && mb_strlen($brand, 'UTF-8') <= 6) {
            return mb_convert_case($brand, MB_CASE_TITLE, 'UTF-8');
        }

        return $brand;
    }

    private function wordCount(string $text): int
    {
        if (preg_match_all('/\p{L}+/u', $text, $matches)) {
            return count($matches[0]);
        }

        return 0;
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
