<?php

declare(strict_types=1);

namespace Evasystem\Services;

/**
 * Legătură audit imagini Cursor ↔ import / publish.
 */
final class ImageAuditImportBridge
{
    public function __construct(
        private readonly ProductImageAuditService $auditService,
    ) {
    }

    /**
     * @param array<string, mixed> $row rând produs (staging sau live)
     * @return array<string, mixed>
     */
    public function attachAuditMeta(array $row): array
    {
        $root = dirname(__DIR__, 3);
        $pipeline = $root . '/system/image_search_pipeline.php';
        if (!is_file($pipeline)) {
            return $row;
        }
        require_once $pipeline;

        return besoiu_image_attach_audit_meta($row);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function listEnabledSources(?array $categories = null): array
    {
        $root = dirname(__DIR__, 3);
        $pipeline = $root . '/system/image_search_pipeline.php';
        if (!is_file($pipeline)) {
            return [];
        }
        require_once $pipeline;

        $out = [];
        foreach (besoiu_image_search_sources_ordered($categories) as $src) {
            $out[] = ['id' => $src['id'], 'label' => $src['label']];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function loadAudit(string $publicId): ?array
    {
        return $this->auditService->loadProductAuditResult($publicId);
    }

    public function needsImageReplace(?array $audit): bool
    {
        if ($audit === null) {
            return false;
        }
        $reco = strtolower((string) ($audit['recommendation'] ?? ''));
        $verdict = strtolower((string) ($audit['verdict'] ?? ''));
        $score = (int) ($audit['match_score'] ?? 0);

        if ($reco === 'replace') {
            return true;
        }
        if (in_array($verdict, ['mismatch', 'error', 'no_image'], true)) {
            return true;
        }

        return $verdict === 'partial' && $score < 65;
    }
}
