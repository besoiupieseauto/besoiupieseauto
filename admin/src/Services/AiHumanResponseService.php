<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Formatează răspunsuri tehnice în ton uman pentru operator.
 */
final class AiHumanResponseService
{
    /** @param list<array<string, mixed>> $hits @param list<array<string, mixed>> $corpusHits */
    public function formatLibraryAnswer(string $message, array $hits, array $corpusHits = []): string
    {
        $intro = $this->introForQuery($message);
        $lines = [$intro, ''];

        $shown = 0;
        foreach (array_slice($hits, 0, 3) as $hit) {
            $text = trim((string) ($hit['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $shown++;
            $lines[] = $this->formatFragment($text, $shown);
            if ($shown >= 2) {
                break;
            }
        }

        foreach (array_slice($corpusHits, 0, 1) as $c) {
            $title = trim((string) ($c['title'] ?? ''));
            $text = trim((string) ($c['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $shown++;
            $lines[] = '';
            $lines[] = ($title !== '' ? '**' . $title . '** — ' : '') . mb_substr($text, 0, 400);
        }

        if ($shown === 0) {
            return '';
        }

        $lines[] = '';
        $lines[] = '—';
        $lines[] = 'Date din biblioteca Besoiu (indexate local). Verifică prețul și stocul înainte de publicare.';

        return implode("\n", $lines);
    }

    public function formatDbStats(string $kind, array $stats): string
    {
        return match ($kind) {
            'products_active' => sprintf(
                "Am verificat în baza de date live:\n\n• **Produse active:** %s\n• **Categorii distincte:** %s\n• **Fără imagine:** %s%s\n\nAceste cifre sunt direct din MySQL — nu sunt estimate.",
                $stats['active'] ?? '—',
                $stats['cats'] ?? '—',
                $stats['no_img'] ?? '—',
                isset($stats['vitrina']) ? "\n• **Pe vitrină:** " . $stats['vitrina'] : ''
            ),
            'import_queue' => sprintf(
                "Coada de import arată așa:\n\n• **Pending:** %s\n• **Conflict live:** %s\n\nPoți verifica detaliile în modulul Import.",
                $stats['pending'] ?? '—',
                $stats['conflict'] ?? '—'
            ),
            'orders' => sprintf(
                "Despre comenzi:\n\n• **Total:** %s\n• **Ultimele 7 zile:** %s",
                $stats['total'] ?? '—',
                $stats['week'] ?? '—'
            ),
            'categories' => (string) ($stats['text'] ?? ''),
            default => implode("\n", array_map(static fn ($v) => (string) $v, $stats)),
        };
    }

    /** @param list<array<string, mixed>> $products */
    public function formatCatalogProducts(array $products, string $vehicleContext = ''): string
    {
        if ($products === []) {
            return '';
        }

        $count = count($products);
        $lines = [
            $count === 1
                ? 'Am găsit un produs în catalogul nostru:'
                : 'Am găsit **' . $count . ' produse** în catalogul nostru:',
            '',
        ];

        foreach ($products as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $stock = (int) ($p['stock'] ?? 0);
            $stockLabel = $stock > 0 ? $stock . ' buc. în stoc' : 'momentan indisponibil';
            $lines[] = sprintf(
                "**%d.** %s\n   Preț: **%s RON** · %s · Cod: %s",
                $i + 1,
                (string) ($p['name'] ?? 'Produs'),
                (string) ($p['price'] ?? '—'),
                $stockLabel,
                (string) ($p['code'] ?? '—')
            );
            $rid = (string) ($p['randomn_id'] ?? '');
            if ($rid !== '') {
                $lines[] = '   [Vezi produs](/product.php?id=' . $rid . ')';
            }
            $lines[] = '';
        }

        if ($vehicleContext !== '') {
            $lines[] = trim($vehicleContext);
            $lines[] = '';
        }

        $lines[] = 'Prețurile sunt în RON, direct din stoc. Spune-mi dacă vrei detalii despre un cod anume.';

        return implode("\n", $lines);
    }

    private function introForQuery(string $message): string
    {
        $msg = mb_strtolower(trim($message));
        if (preg_match('/\bce\s+(stii|știi|stie|știe)\b/u', $msg) || str_contains($msg, 'despre')) {
            return 'Da, am informații în biblioteca noastră despre asta:';
        }
        if (preg_match('/\b(pret|preț|cat costa|cât costă|stoc)\b/u', $msg)) {
            return 'Iată ce am despre preț și disponibilitate (din biblioteca indexată):';
        }

        return 'Am găsit următoarele în biblioteca Besoiu:';
    }

    private function formatFragment(string $text, int $index): string
    {
        if (preg_match('/^Produs:\s*(.+?)\s*\|\s*SKU:\s*([^|]+)\s*\|\s*Pre[tț]:\s*([^|]+)\s*\|\s*URL:\s*(.+)$/iu', $text, $m)) {
            return sprintf(
                "**%d.** %s\n   SKU: %s · Preț: **%s**\n   Sursă: %s",
                $index,
                trim($m[1]),
                trim($m[2]),
                trim($m[3]),
                trim($m[4])
            );
        }

        if (preg_match('/^(.{10,120}?)\s*\|\s*SKU:/u', $text)) {
            return "**{$index}.** " . $text;
        }

        return "**{$index}.** " . mb_substr($text, 0, 500);
    }
}
