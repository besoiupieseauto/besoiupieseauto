<?php

declare(strict_types=1);

/**
 * Șabloane scenarii din planul strategic marketing (besoiupieseimport/docs).
 * Sursă editabilă: planner_strategic_plan.json
 *
 * @return array{
 *   source: string,
 *   sourceDocs: string,
 *   templates: array<int, array<string, mixed>>
 * }
 */
function planner_strategic_plan_catalog(): array
{
    $path = __DIR__ . '/planner_strategic_plan.json';
    if (!is_readable($path)) {
        return ['source' => '', 'sourceDocs' => '', 'templates' => []];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['source' => '', 'sourceDocs' => '', 'templates' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !is_array($decoded['templates'] ?? null)) {
        return ['source' => '', 'sourceDocs' => '', 'templates' => []];
    }

    return $decoded;
}

/**
 * @return array<int, array<string, mixed>>
 */
function planner_strategic_plan_templates(): array
{
    $catalog = planner_strategic_plan_catalog();

    return $catalog['templates'];
}
