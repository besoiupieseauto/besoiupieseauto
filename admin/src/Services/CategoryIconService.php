<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;

/**
 * Rezolvă și persistă iconițe pentru categorii / subcategorii / marcă / model / motorizare.
 */
final class CategoryIconService
{
    public const DEFAULT_ICON = 'img/icons/22_cutie_produse.svg';

    /** @var array<string, string> slug categorie root → cale SVG */
    private const ROOT_ICONS = [
        'frane' => 'img/icons/01_frane.svg',
        'filtre' => 'img/icons/02_filtre.svg',
        'ulei' => 'img/icons/03_ulei_lichide.svg',
        'suspensie' => 'img/icons/04_suspensie.svg',
        'motor' => 'img/icons/05_motor.svg',
        'electric' => 'img/icons/06_electric.svg',
        'caroserie' => 'img/icons/07_caroserie.svg',
        'transmisie' => 'img/icons/08_transmisie.svg',
    ];

    /** @var array<string, string> */
    private const TYPE_ICONS = [
        'marca' => 'img/icons/17_marca_auto.svg',
        'model' => 'img/icons/18_model_auto.svg',
        'motorizare' => 'img/icons/20_motorizare_ceas.svg',
    ];

    /** @var array<string, string> cuvânt cheie (normalizat) → slug categorie root */
    private const KEYWORD_TO_ROOT = [
        'fran' => 'frane',
        'disc' => 'frane',
        'placut' => 'frane',
        'etrier' => 'frane',
        'tambur' => 'frane',
        'abs' => 'frane',
        'filtr' => 'filtre',
        'polen' => 'filtre',
        'habitacl' => 'filtre',
        'ulei' => 'ulei',
        'lichid' => 'ulei',
        'antigel' => 'ulei',
        'refriger' => 'ulei',
        'adblue' => 'ulei',
        'suspens' => 'suspensie',
        'amortiz' => 'suspensie',
        'arcuri' => 'suspensie',
        'bascul' => 'suspensie',
        'rulment' => 'suspensie',
        'rotula' => 'suspensie',
        'cuzinet' => 'suspensie',
        'motor' => 'motor',
        'piston' => 'motor',
        'distribut' => 'motor',
        'curea' => 'motor',
        'segment' => 'motor',
        'chiulas' => 'motor',
        'turbo' => 'motor',
        'inject' => 'motor',
        'bobina' => 'motor',
        'bujie' => 'motor',
        'termostat' => 'motor',
        'garnitur' => 'motor',
        'biela' => 'motor',
        'ventil' => 'motor',
        'electric' => 'electric',
        'alternator' => 'electric',
        'baterie' => 'electric',
        'faruri' => 'electric',
        'proiector' => 'electric',
        'senzor' => 'electric',
        'demaror' => 'electric',
        'stergator' => 'electric',
        'caroser' => 'caroserie',
        'parbriz' => 'caroserie',
        'oglind' => 'caroserie',
        'aripa' => 'caroserie',
        'capota' => 'caroserie',
        'grila' => 'caroserie',
        'transmis' => 'transmisie',
        'ambreiaj' => 'transmisie',
        'cutie' => 'transmisie',
        'cardan' => 'transmisie',
        'diferential' => 'transmisie',
        'planetar' => 'transmisie',
    ];

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $indexById
     */
    public function resolve(array $row, array $indexById = []): string
    {
        $icon = trim((string) ($row['icon'] ?? ''));
        if ($icon !== '') {
            return $icon;
        }

        $type = (string) ($row['type'] ?? 'categorie');
        $slug = $this->normalize((string) ($row['slug'] ?? ''));
        $label = $this->normalize((string) ($row['label'] ?? ''));

        if ($type === 'categorie' && isset(self::ROOT_ICONS[$slug])) {
            return self::ROOT_ICONS[$slug];
        }

        if ($type === 'subcategorie') {
            $parentIcon = $this->resolveParentIcon($row, $indexById);
            if ($parentIcon !== '') {
                return $parentIcon;
            }

            $keywordIcon = $this->resolveByKeywords($slug . ' ' . $label);
            if ($keywordIcon !== '') {
                return $keywordIcon;
            }

            return self::DEFAULT_ICON;
        }

        if (isset(self::TYPE_ICONS[$type])) {
            return self::TYPE_ICONS[$type];
        }

        $keywordIcon = $this->resolveByKeywords($slug . ' ' . $label);
        if ($keywordIcon !== '') {
            return $keywordIcon;
        }

        if ($type === 'categorie' && isset(self::ROOT_ICONS[$slug])) {
            return self::ROOT_ICONS[$slug];
        }

        return self::DEFAULT_ICON;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function buildIndex(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $index[(int) $row['id']] = $row;
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function applyToPayload(array $payload, CategoriiModel $model): array
    {
        if (trim((string) ($payload['icon'] ?? '')) !== '') {
            return $payload;
        }

        $index = [];
        $parentId = (int) ($payload['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $model->findById($parentId);
            if ($parent) {
                $index[$parentId] = $parent;
            }
        }

        $row = array_merge([
            'type' => 'categorie',
            'slug' => '',
            'label' => '',
            'parent_id' => $parentId > 0 ? $parentId : null,
            'icon' => '',
        ], $payload);

        $payload['icon'] = $this->resolve($row, $index);

        return $payload;
    }

    /**
     * @return array{updated:int,skipped:int,details:array<int,array{id:int,label:string,icon:string}>}
     */
    public function backfillMissingIcons(CategoriiModel $model): array
    {
        $all = $model->findAll();
        $index = $this->buildIndex($all);
        $ordered = $this->sortForBackfill($all);

        $updated = 0;
        $skipped = 0;
        $details = [];

        foreach ($ordered as $row) {
            if (trim((string) ($row['icon'] ?? '')) !== '') {
                $skipped++;
                continue;
            }

            $icon = $this->resolve($row, $index);
            $id = (int) $row['id'];
            if ($model->update($id, ['icon' => $icon])) {
                $index[$id]['icon'] = $icon;
                $updated++;
                $details[] = [
                    'id' => $id,
                    'label' => (string) ($row['label'] ?? ''),
                    'icon' => $icon,
                ];
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'details' => $details,
        ];
    }

    /** Rezolvă icon după numele categoriei părinte (facete produse). */
    public function resolveForProductSubcategory(string $subcategoryLabel, string $categoryLabel = ''): string
    {
        $haystack = $this->normalize($categoryLabel . ' ' . $subcategoryLabel);
        $keywordIcon = $this->resolveByKeywords($haystack);
        if ($keywordIcon !== '') {
            return $keywordIcon;
        }

        $categorySlug = $this->normalize($this->slugify($categoryLabel));
        if (isset(self::ROOT_ICONS[$categorySlug])) {
            return self::ROOT_ICONS[$categorySlug];
        }

        return self::DEFAULT_ICON;
    }

    /** @return list<array{path:string,label:string}> */
    public static function presetIcons(): array
    {
        $labels = [
            'frane' => 'Frâne',
            'filtre' => 'Filtre',
            'ulei' => 'Ulei & Lichide',
            'suspensie' => 'Suspensie',
            'motor' => 'Motor',
            'electric' => 'Electric',
            'caroserie' => 'Caroserie',
            'transmisie' => 'Transmisie',
        ];

        $presets = [];
        foreach (self::ROOT_ICONS as $slug => $path) {
            $presets[] = ['path' => $path, 'label' => $labels[$slug] ?? $slug];
        }

        foreach (self::TYPE_ICONS as $type => $path) {
            $presets[] = [
                'path' => $path,
                'label' => match ($type) {
                    'marca' => 'Marcă auto',
                    'model' => 'Model auto',
                    'motorizare' => 'Motorizare',
                    default => $type,
                },
            ];
        }

        $presets[] = ['path' => self::DEFAULT_ICON, 'label' => 'Generic (cutie)'];

        return $presets;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $indexById
     */
    private function resolveParentIcon(array $row, array $indexById): string
    {
        $parentId = (int) ($row['parent_id'] ?? 0);
        if ($parentId <= 0 || !isset($indexById[$parentId])) {
            return '';
        }

        $parent = $indexById[$parentId];
        $parentIcon = trim((string) ($parent['icon'] ?? ''));
        if ($parentIcon !== '') {
            return $parentIcon;
        }

        $parentSlug = $this->normalize((string) ($parent['slug'] ?? ''));
        if (isset(self::ROOT_ICONS[$parentSlug])) {
            return self::ROOT_ICONS[$parentSlug];
        }

        return $this->resolveByKeywords(
            ($parent['slug'] ?? '') . ' ' . ($parent['label'] ?? '')
        );
    }

    private function resolveByKeywords(string $haystack): string
    {
        $normalized = $this->normalize($haystack);
        $pairs = self::KEYWORD_TO_ROOT;
        uksort($pairs, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($pairs as $keyword => $rootSlug) {
            $needle = trim($keyword);
            if ($needle === '' || !isset(self::ROOT_ICONS[$rootSlug])) {
                continue;
            }
            if (mb_strlen($needle, 'UTF-8') >= 4 && str_contains($normalized, $needle)) {
                return self::ROOT_ICONS[$rootSlug];
            }
            if (mb_strlen($needle, 'UTF-8') < 4
                && preg_match('/(^|[\s\-_\/])' . preg_quote($needle, '/') . '($|[\s\-_\/])/u', $normalized)) {
                return self::ROOT_ICONS[$rootSlug];
            }
        }

        return '';
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function sortForBackfill(array $rows): array
    {
        $typeOrder = [
            'categorie' => 0,
            'subcategorie' => 1,
            'marca' => 2,
            'model' => 3,
            'motorizare' => 4,
        ];

        usort($rows, static function (array $a, array $b) use ($typeOrder): int {
            $ta = $typeOrder[$a['type'] ?? 'categorie'] ?? 9;
            $tb = $typeOrder[$b['type'] ?? 'categorie'] ?? 9;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $map = [
            'â' => 'a', 'ă' => 'a', 'î' => 'i',
            'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        ];

        return strtr($value, $map);
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower(trim($label), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $this->normalize($slug)) ?? $slug;

        return trim($slug, '-');
    }
}
