<?php
declare(strict_types=1);

use Besoiu\Services\CategoryIconService;

/** @var array<int, array<string, mixed>> $besoiuFlat */
/** @var array<int, array<string, mixed>> $besoiuTreeRoots */
/** @var array<string, array<string, string>> $typeMeta */
/** @var CategoryIconService $iconService */
/** @var array<int, array<string, mixed>> $iconIndex */
/** @var int $besoiuCount */
/** @var string $searchQ */

function besoiu_tree_filter_flat(array $flat, string $q): array
{
    if ($q === '') {
        return $flat;
    }
    $qLower = mb_strtolower($q);
    $byTreeId = [];
    foreach ($flat as $cat) {
        $tid = cat_tree_id($cat);
        if ($tid > 0) {
            $byTreeId[$tid] = $cat;
        }
    }
    $keep = [];
    foreach ($flat as $cat) {
        $blob = mb_strtolower(
            cat_display_label($cat) . ' '
            . cat_art_name($cat) . ' '
            . cat_path($cat) . ' '
            . cat_tree_id($cat)
        );
        if (!str_contains($blob, $qLower)) {
            continue;
        }
        $tid = cat_tree_id($cat);
        $keep[$tid] = true;
        $pid = cat_parent_tree_id($cat);
        while ($pid > 0 && isset($byTreeId[$pid])) {
            $keep[$pid] = true;
            $pid = cat_parent_tree_id($byTreeId[$pid]);
        }
    }
    return array_values(array_filter($flat, static fn (array $c): bool => isset($keep[cat_tree_id($c)])));
}

function besoiu_tree_build_roots(array $flat): array
{
    $byTreeId = [];
    foreach ($flat as $cat) {
        $tid = cat_tree_id($cat);
        if ($tid <= 0) {
            continue;
        }
        $cat['_children'] = [];
        $byTreeId[$tid] = $cat;
    }
    $roots = [];
    foreach ($byTreeId as $tid => $cat) {
        $pid = cat_parent_tree_id($cat);
        if ($pid > 0 && isset($byTreeId[$pid])) {
            $byTreeId[$pid]['_children'][] = &$byTreeId[$tid];
        } else {
            $roots[] = &$byTreeId[$tid];
        }
    }
    unset($cat);
    besoiu_tree_sort_nodes($roots);
    return $roots;
}

function besoiu_tree_sort_nodes(array &$nodes): void
{
    usort($nodes, static fn (array $a, array $b): int => cat_tree_id($a) <=> cat_tree_id($b));
    foreach ($nodes as &$node) {
        if (!empty($node['_children'])) {
            besoiu_tree_sort_nodes($node['_children']);
        }
    }
    unset($node);
}

function besoiu_tree_count_leaves(array $nodes): int
{
    $n = 0;
    foreach ($nodes as $node) {
        $children = $node['_children'] ?? [];
        if ($children === []) {
            $n++;
        } else {
            $n += besoiu_tree_count_leaves($children);
        }
    }
    return $n;
}

function besoiu_tree_render_nodes(
    array $nodes,
    CategoryIconService $iconService,
    array $iconIndex,
    array $typeMeta,
    int $depth = 0
): void {
    foreach ($nodes as $idx => $cat) {
        $children = $cat['_children'] ?? [];
        $hasChildren = $children !== [];
        $treeId = cat_tree_id($cat);
        $parentTreeId = cat_parent_tree_id($cat);
        $level = cat_level($cat);
        $artName = cat_art_name($cat);
        $displayLabel = cat_display_label($cat);
        $pathLabel = cat_path($cat);
        $catType = (string) ($cat['type'] ?? 'categorie');
        $typeClass = isset($typeMeta[$catType]) ? $catType : 'categorie';
        $isLeaf = !empty(cat_meta($cat)['is_leaf']) || !$hasChildren;
        $isActive = (int) ($cat['is_active'] ?? 0) === 1;
        $displayIcon = $iconService->resolve($cat, $iconIndex);
        $dbId = (int) ($cat['id'] ?? 0);
        $searchBlob = mb_strtolower($displayLabel . ' ' . $artName . ' ' . $pathLabel . ' ' . $treeId);
        $openDefault = $level <= 1 || $depth === 0;
        ?>
        <li class="cp-tree-node cp-tree-node--l<?= (int) $level ?> <?= $hasChildren ? 'cp-tree-node--branch' : 'cp-tree-node--leaf' ?> <?= $isLeaf ? 'cp-tree-node--art' : '' ?>"
            data-tree-id="<?= (int) $treeId ?>"
            data-parent-tree-id="<?= (int) $parentTreeId ?>"
            data-level="<?= (int) $level ?>"
            data-db-id="<?= $dbId ?>"
            data-search="<?= h_cat($searchBlob) ?>"
            style="--cp-tree-depth:<?= (int) $level ?>;--cp-tree-idx:<?= (int) $idx ?>">
            <div class="cp-tree-row" role="treeitem" aria-expanded="<?= $hasChildren && $openDefault ? 'true' : 'false' ?>" tabindex="0">
                <div class="cp-tree-row__main">
                    <div class="cp-tree-cell cp-tree-cell--toggle">
                        <?php if ($hasChildren): ?>
                        <button type="button" class="cp-tree-toggle <?= $openDefault ? 'is-open' : '' ?>" aria-label="Deschide / închide" data-tree-toggle>
                            <svg class="cp-tree-toggle__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M9 6l6 6-6 6"/></svg>
                        </button>
                        <?php else: ?>
                        <span class="cp-tree-toggle cp-tree-toggle--leaf" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--level">
                        <span class="cp-tree-level-badge" title="Nivel ierarhic">N<?= (int) $level ?></span>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--id">
                        <span class="cp-tree-id-badge" title="ID arbore Excel">#<?= (int) $treeId ?></span>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--label">
                        <div class="cp-tree-labels">
                            <span class="cp-tree-display"><?= h_cat($displayLabel) ?></span>
                            <?php if ($isLeaf && $artName !== '' && $artName !== $displayLabel): ?>
                            <span class="cp-tree-art" title="ART_NAME exact"><?= h_cat($artName) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--path">
                        <span class="cp-tree-path" title="<?= h_cat($pathLabel) ?>"><?= h_cat($pathLabel !== '' ? $pathLabel : '—') ?></span>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--type">
                        <span class="cp-type cp-type--<?= h_cat($typeClass) ?>"><?= h_cat($typeMeta[$typeClass]['short'] ?? $catType) ?></span>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--meta">
                        <?php if ($hasChildren): ?>
                        <span class="cp-tree-children-count"><?= count($children) ?> sub</span>
                        <?php endif; ?>
                        <span class="cp-tree-db">DB <?= $dbId ?></span>
                    </div>
                    <div class="cp-tree-cell cp-tree-cell--actions">
                        <button type="button" onclick="toggleCat(<?= $dbId ?>, <?= $isActive ? 0 : 1 ?>)" class="cp-toggle <?= $isActive ? 'is-on' : 'is-off' ?>" aria-label="Vizibilitate">
                            <span></span>
                        </button>
                        <button type="button" onclick='editCat(<?= json_encode(array_merge($cat, ['icon' => $displayIcon, '_children' => []]), JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="cp-icon-btn" title="Editează">
                            <i data-lucide="pencil" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php if ($hasChildren): ?>
            <div class="cp-tree-children <?= $openDefault ? 'is-open' : '' ?>" data-tree-children>
                <div class="cp-tree-children__inner">
                    <ul class="cp-tree-list" role="group">
                        <?php besoiu_tree_render_nodes($children, $iconService, $iconIndex, $typeMeta, $depth + 1); ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </li>
        <?php
    }
}

$besoiuFiltered = besoiu_tree_filter_flat($besoiuFlat, $searchQ);
$besoiuTreeRoots = besoiu_tree_build_roots($besoiuFiltered);
$besoiuVisibleRoots = count($besoiuTreeRoots);
$besoiuVisibleLeaves = besoiu_tree_count_leaves($besoiuTreeRoots);
$besoiuMaxLevel = 0;
foreach ($besoiuFiltered as $row) {
    $besoiuMaxLevel = max($besoiuMaxLevel, cat_level($row));
}
?>

<div class="cp-besoiu-tree" id="besoiuTree" data-max-level="<?= (int) $besoiuMaxLevel ?>">
    <div class="cp-tree-toolbar">
        <div class="cp-tree-toolbar__left">
            <span class="cp-tree-toolbar__title">Arbore catalog Besoiu</span>
            <span class="cp-tree-toolbar__meta"><?= (int) $besoiuVisibleRoots ?> rădăcini · <?= (int) count($besoiuFiltered) ?> noduri · <?= (int) $besoiuVisibleLeaves ?> frunze</span>
        </div>
        <div class="cp-tree-toolbar__actions">
            <button type="button" class="cp-btn cp-btn--ghost cp-tree-btn" data-tree-action="expand-all">Deschide tot</button>
            <button type="button" class="cp-btn cp-btn--ghost cp-tree-btn" data-tree-action="collapse-all">Închide tot</button>
            <button type="button" class="cp-btn cp-btn--ghost cp-tree-btn" data-tree-action="expand-l1">Doar nivel 1</button>
            <button type="button" class="cp-btn cp-btn--ghost cp-tree-btn" data-tree-action="expand-l2">Până la nivel 2</button>
            <label class="cp-tree-density">
                <span>Densitate</span>
                <input type="range" id="besoiuTreeDensity" min="0" max="2" value="1" step="1">
            </label>
        </div>
    </div>

    <div class="cp-tree-legend">
        <span class="cp-tree-legend__item cp-tree-legend__item--l1"><i></i> Nivel 1 — grup principal</span>
        <span class="cp-tree-legend__item cp-tree-legend__item--l2"><i></i> Nivel 2 — subcategorie / frunză ART_NAME</span>
        <span class="cp-tree-legend__item cp-tree-legend__item--path"><i></i> Cale completă · ID · DB#</span>
    </div>

    <div class="cp-tree-scroll">
    <div class="cp-tree-head" aria-hidden="true">
        <div class="cp-tree-head__cell cp-tree-head__toggle"></div>
        <div class="cp-tree-head__cell cp-tree-head__level">Niv.</div>
        <div class="cp-tree-head__cell cp-tree-head__id">ID</div>
        <div class="cp-tree-head__cell cp-tree-head__label">Denumire afișare / ART_NAME</div>
        <div class="cp-tree-head__cell cp-tree-head__path">Cale</div>
        <div class="cp-tree-head__cell cp-tree-head__type">Tip</div>
        <div class="cp-tree-head__cell cp-tree-head__meta">Info</div>
        <div class="cp-tree-head__cell cp-tree-head__actions">Acțiuni</div>
    </div>

    <div class="cp-tree-body" role="tree" aria-label="Arbore categorii Besoiu">
        <?php if ($besoiuTreeRoots === []): ?>
        <div class="cp-empty cp-tree-empty">
            <p class="cp-empty__title">Nu există noduri în arbore.</p>
            <p>Apasă <strong>Reînnoiește din Excel</strong> sau <a href="?view=besoiu&amp;page=1">resetează filtrele</a>.</p>
        </div>
        <?php else: ?>
        <ul class="cp-tree-list cp-tree-list--root" role="group">
            <?php besoiu_tree_render_nodes($besoiuTreeRoots, $iconService, $iconIndex, $typeMeta); ?>
        </ul>
        <?php endif; ?>
    </div>
    </div>

    <div class="cp-tree-footer">
        <span id="besoiuTreeVisibleCount"><?= (int) count($besoiuFiltered) ?></span> / <?= (int) $besoiuCount ?> noduri afișate
        <?php if ($searchQ !== ''): ?>
        · filtru: <strong><?= h_cat($searchQ) ?></strong>
        <?php endif; ?>
    </div>
</div>
