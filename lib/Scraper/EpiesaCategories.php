<?php
declare(strict_types=1);

final class EpiesaCategories
{
  /** @return array<string, array{slug: string, label: string, url: string}> */
  public static function presets(): array
  {
    $base = 'https://www.epiesa.ro/gmtn1:auto/gmtn2:';
    return [
      'uleiuri' => [
        'slug'  => 'uleiuri',
        'label' => 'Uleiuri',
        'url'   => $base . 'uleiuri-si-lubrifianti-auto/',
      ],
      'filtre' => [
        'slug'  => 'filtre',
        'label' => 'Filtre',
        'url'   => $base . 'filtre-auto/',
      ],
      'frane' => [
        'slug'  => 'frane',
        'label' => 'Frâne',
        'url'   => $base . 'sisteme-de-franare/',
      ],
      'baterii' => [
        'slug'  => 'baterii',
        'label' => 'Baterii',
        'url'   => $base . 'baterii-auto/',
      ],
      'becuri' => [
        'slug'  => 'becuri',
        'label' => 'Becuri',
        'url'   => $base . 'becuri-auto/',
      ],
      'anvelope' => [
        'slug'  => 'anvelope',
        'label' => 'Anvelope',
        'url'   => $base . 'anvelope-auto/',
      ],
    ];
  }

  /** @return array{slug: string, label: string} */
  public static function resolveFromUrl(string $url): array
  {
    $url = trim($url);
    foreach (self::presets() as $preset) {
      if ($url !== '' && str_contains($url, $preset['url'])) {
        return ['slug' => $preset['slug'], 'label' => $preset['label']];
      }
    }

    if (preg_match('#gmtn2:([^/]+)#', $url, $m)) {
      $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($m[1])) ?: 'altele';
      $label = ucwords(str_replace('-', ' ', $slug));

      return ['slug' => $slug, 'label' => $label];
    }

    return ['slug' => 'altele', 'label' => 'Altele'];
  }

  public static function labelForSlug(string $slug): string
  {
    $presets = self::presets();
    if (isset($presets[$slug])) {
      return $presets[$slug]['label'];
    }

    return ucwords(str_replace('-', ' ', $slug));
  }
}
