<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

/**
 * Verifică permisiunile chat intern din sesiunea admin.
 */
final class AdminChatPermissionGuard
{
    /** @var list<string> */
    private array $permissions;

    private string $role;

    /** @param list<string> $permissions */
    public function __construct(array $permissions, string $role = 'guest')
    {
        $this->permissions = $permissions;
        $this->role = strtolower(trim($role));
    }

    public static function fromSession(): self
    {
        $role = (string) ($_SESSION['role'] ?? 'guest');
        $raw = $_SESSION['admin_chat_permissions_raw'] ?? null;
        $perms = AdminChatPermissionCatalog::normalizePermissions($raw, $role);

        return new self($perms, $role);
    }

    public function can(string $key): bool
    {
        if ($this->role === 'super_ambassador') {
            return true;
        }

        return in_array($key, $this->permissions, true);
    }

    /** Marker DOM — formular RAG doar cu permisiune explicită (fără bypass super_ambassador). */
    public function canDomMarkerDocument(): bool
    {
        return in_array('chat.dom_marker_doc', $this->permissions, true);
    }

    public function canUseChat(): bool
    {
        return $this->can('chat.use');
    }

    /** @param array<string, mixed> $input */
    public function filterPlan(array $plan, array $input = []): array
    {
        if (!$this->canUseChat()) {
            return $this->denialPlan('chat.use', 'Chat intern dezactivat pentru contul tău. Cere un super ambassador să activeze permisiunile în Setări → Utilizatori.');
        }

        $source = (string) ($plan['source'] ?? '');
        $execute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        if (str_contains($source, 'learn') || !empty($plan['can_learn']) || !empty($plan['learned'])) {
            if (!$this->can('chat.learn') && (str_contains($source, 'learn') || !empty($plan['learned']))) {
                return $this->denialPlan('chat.learn', 'Nu ai permisiunea de antrenare chat (Învață).');
            }
        }

        if ($this->planIsNavigateOnly($plan) && !$this->can('chat.navigate')) {
            return $this->denialPlan('chat.navigate', 'Nu ai permisiunea de navigare din chat.');
        }

        $pending = is_array($plan['pending_action'] ?? null) ? $plan['pending_action'] : null;
        if ($pending !== null) {
            $perm = AdminChatPermissionCatalog::permissionForActionType(
                (string) ($pending['type'] ?? ''),
                $pending
            );
            if (!$this->can($perm)) {
                return $this->denialPlan($perm, 'Nu ai permisiunea să ' . $this->actionLabel($perm) . ' din chat.');
            }
            if ($execute && !$this->can('chat.confirm_execute')) {
                return $this->denialPlan('chat.confirm_execute', 'Poți vedea previzualizarea, dar nu ai voie să confirmi/execuți modificări din chat.');
            }

            return $plan;
        }

        if ($this->planIsWriteAction($plan) && !$execute) {
            $type = (string) (($plan['action']['type'] ?? '') ?: ($plan['intent'] ?? ''));
            if ($type !== '') {
                $perm = AdminChatPermissionCatalog::permissionForActionType($type, is_array($plan['action'] ?? null) ? $plan['action'] : []);
                if (!$this->can($perm)) {
                    return $this->denialPlan($perm, 'Nu ai permisiunea să ' . $this->actionLabel($perm) . ' din chat.');
                }
            }
        }

        if ($source === 'action_executed' || ($execute && $this->planIsWriteAction($plan))) {
            if (!$this->can('chat.confirm_execute')) {
                return $this->denialPlan('chat.confirm_execute', 'Execuția modificărilor din chat este restricționată pentru contul tău.');
            }
        }

        if ($this->planIsQuery($plan) && !$this->can('chat.query')) {
            return $this->denialPlan('chat.query', 'Ai acces doar la navigare — interogările live (inventar, liste) sunt restricționate.');
        }

        if ($this->messageIsRepair($input) && !$this->can('chat.repair')) {
            return $this->denialPlan('chat.repair', 'Nu ai permisiunea de reparații sistem din chat.');
        }

        return $plan;
    }

    /** @return array<string, mixed> */
    public function denialPlan(string $permKey, string $message): array
    {
        return [
            'ok' => false,
            'source' => 'chat_permission_denied',
            'reply' => $message,
            'reply_ro' => $message,
            'intent' => 'denied',
            'permission_required' => $permKey,
            'cheat_sheet' => [
                'kind' => 'permission_denied',
                'title' => 'Permisiune chat lipsă',
                'subtitle' => AdminChatPermissionCatalog::groups()['core']['features'][$permKey]['label']
                    ?? AdminChatPermissionCatalog::groups()['edit']['features'][$permKey]['label']
                    ?? AdminChatPermissionCatalog::groups()['advanced']['features'][$permKey]['label']
                    ?? $permKey,
                'hints' => [
                    'Super ambassador poate ajusta permisiunile în Setări → Utilizatori → Chat intern.',
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @param array<string, mixed> $plan */
    private function planIsNavigateOnly(array $plan): bool
    {
        $kind = (string) (($plan['cheat_sheet']['kind'] ?? '') ?: ($plan['kind'] ?? ''));

        return $kind === 'navigate' || $kind === 'organism_navigate';
    }

    /** @param array<string, mixed> $plan */
    private function planIsQuery(array $plan): bool
    {
        $source = (string) ($plan['source'] ?? '');
        if ($source === 'conversation' || $source === 'section_capabilities') {
            return false;
        }

        $writeSources = ['action_preview', 'action_executed', 'learned_adapter', 'learn_failed', 'chat_permission_denied'];
        if (in_array($source, $writeSources, true)) {
            return false;
        }

        return str_contains($source, 'product_')
            || str_contains($source, 'inventory')
            || str_contains($source, 'import_')
            || str_contains($source, 'orders_')
            || str_contains($source, 'supplier_')
            || str_contains($source, 'organism+')
            || str_contains($source, 'query')
            || str_contains($source, 'vitrina')
            || str_contains($source, 'adaos')
            || str_contains($source, 'invoice')
            || str_contains($source, 'client_')
            || str_contains($source, 'comunicare');
    }

    /** @param array<string, mixed> $plan */
    private function planIsWriteAction(array $plan): bool
    {
        $source = (string) ($plan['source'] ?? '');

        return str_starts_with($source, 'action_')
            || !empty($plan['pending_action'])
            || $source === 'learned_adapter';
    }

    /** @param array<string, mixed> $input */
    private function messageIsRepair(array $input): bool
    {
        $msg = mb_strtolower(trim((string) ($input['message'] ?? '')), 'UTF-8');

        return str_contains($msg, 'repara')
            || str_contains($msg, 'fix ')
            || str_contains($msg, 'job blocat');
    }

    private function actionLabel(string $permKey): string
    {
        return match ($permKey) {
            'chat.edit_light' => 'faci corecții ușoare (badge, vitrină, titlu)',
            'chat.edit_product' => 'modifici produse',
            'chat.edit_bulk' => 'modifici produse în masă',
            'chat.import' => 'publici din import',
            'chat.supplier_adaos' => 'modifici adaos/furnizori',
            'chat.repair' => 'repari job-uri/alerte',
            default => 'execuți această acțiune',
        };
    }
}
