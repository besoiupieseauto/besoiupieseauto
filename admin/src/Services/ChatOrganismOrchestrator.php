<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Organism central chat — mapează mesaj → capabilitate → execuție 1:1 modul admin.
 */
final class ChatOrganismOrchestrator
{
    private ChatCapabilityRegistryService $registry;

    public function __construct(
        private readonly string $projectRoot,
        private readonly SectionAssistantModuleService $modules,
        private readonly SectionAssistantActionService $actions,
        ?ChatCapabilityRegistryService $registry = null,
    ) {
        $this->registry = $registry ?? new ChatCapabilityRegistryService();
    }

    public static function create(string $projectRoot): self
    {
        return new self(
            $projectRoot,
            new SectionAssistantModuleService(),
            new SectionAssistantActionService($projectRoot),
        );
    }

    /** @return array{organism:array<string,mixed>,stats:array<string,int>,modules:list<array<string,mixed>>} */
    public function status(?string $section = null): array
    {
        return [
            'organism' => [
                'name' => 'Besoiu Chat Organism',
                'version' => (string) ($this->registry->registry()['version'] ?? '1.0'),
            ],
            'stats' => $this->registry->stats(),
            'modules' => $this->registry->modulesForUi($section),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null plan Composer sau null
     */
    public function tryResolve(string $message, string $section, array $context, array $input): ?array
    {
        $match = $this->registry->match($message, $section);
        if ($match === null) {
            return null;
        }

        $cap = $match['capability'];
        $kind = (string) ($cap['kind'] ?? '');
        $status = (string) ($cap['status'] ?? 'live');

        if ($status === 'navigate_only' || $kind === 'navigate') {
            return $this->buildNavigatePlan($cap, $message);
        }

        if ($kind === 'query') {
            $handler = (string) ($cap['handler'] ?? '');
            if ($handler === '') {
                return null;
            }
            $plan = $this->modules->dispatchByHandlerKey($handler, $message, $section, $context);
            if ($plan !== null) {
                return $this->decoratePlan($plan, $cap, $match, 'query');
            }
        }

        if ($kind === 'action') {
            $actionInput = array_merge($input, [
                'execute_action' => !empty($input['execute_action'])
                    || !empty($input['confirm_execute']),
            ]);
            $plan = $this->actions->tryHandle($message, $section, $context, $actionInput);
            if ($plan !== null) {
                return $this->decoratePlan($plan, $cap, $match, 'action');
            }
        }

        return $this->buildNavigatePlan($cap, $message, true);
    }

    /** @param array<string, mixed> $cap @param array<string, mixed> $match */
    private function decoratePlan(array $plan, array $cap, array $match, string $via): array
    {
        $capUi = $this->registry->capabilityForUi($cap);
        $plan['source'] = 'organism+' . (string) ($cap['id'] ?? 'unknown');
        $plan['organism'] = [
            'capability_id' => (string) ($cap['id'] ?? ''),
            'module' => (string) ($cap['module'] ?? ''),
            'module_label' => (string) ($cap['module_label'] ?? ''),
            'label' => (string) ($cap['label'] ?? ''),
            'kind' => (string) ($cap['kind'] ?? ''),
            'status' => (string) ($cap['status'] ?? 'live'),
            'score' => (float) ($match['score'] ?? 0),
            'via' => $via,
        ];

        if (is_array($plan['cheat_sheet'] ?? null)) {
            $hints = is_array($plan['cheat_sheet']['hints'] ?? null) ? $plan['cheat_sheet']['hints'] : [];
            array_unshift(
                $hints,
                'Organism: «' . ($capUi['label'] ?? '') . '» [' . ($cap['module'] ?? '') . '] — '
                . ($via === 'action' ? 'actiune' : 'interogare live') . '.'
            );
            $plan['cheat_sheet']['hints'] = $hints;
            $plan['cheat_sheet']['organism_capability'] = $capUi;
        }

        return $plan;
    }

    /** @param array<string, mixed> $cap */
    private function buildNavigatePlan(array $cap, string $message, bool $fallback = false): array
    {
        $url = (string) ($cap['url'] ?? $cap['module_url'] ?? '/admin/product');
        $label = (string) ($cap['label'] ?? 'Deschide modul');
        $capUi = $this->registry->capabilityForUi($cap);

        return [
            'source' => 'organism+navigate',
            'reply_ro' => $fallback
                ? 'Nu pot executa inca automat — deschide modulul in admin si continua de acolo.'
                : ('Deschid «' . $label . '» — foloseste butonul de mai jos.'),
            'intent' => 'navigate',
            'organism' => [
                'capability_id' => (string) ($cap['id'] ?? ''),
                'module' => (string) ($cap['module'] ?? ''),
                'label' => $label,
                'kind' => 'navigate',
                'status' => (string) ($cap['status'] ?? 'navigate_only'),
                'via' => 'navigate',
            ],
            'cheat_sheet' => [
                'kind' => 'organism_navigate',
                'selective' => true,
                'selectable' => false,
                'title' => $label,
                'subtitle' => (string) ($cap['module_label'] ?? ''),
                'updated_at' => date('d.m.Y H:i'),
                'stats' => [
                    ['key' => 'module', 'label' => 'Modul', 'value' => (string) ($cap['module_label'] ?? ''), 'primary' => true],
                ],
                'organism_capability' => $capUi,
                'hints' => [
                    'Organism: navigare catre «' . $label . '».',
                    'Cerere: «' . mb_substr($message, 0, 80, 'UTF-8') . '»',
                ],
                'shortcuts' => [
                    ['label' => $label, 'url' => $url],
                    ['label' => 'Modul ' . ($cap['module_label'] ?? ''), 'url' => (string) ($cap['module_url'] ?? $url)],
                ],
            ],
            'suggestions' => [],
            'next_steps' => array_values(array_filter((array) ($cap['examples'] ?? []))),
        ];
    }

    private function messageRequestsImmediateAction(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(acum|execut[aă]|confirm[aă]|da\s+execut|ruleaza|ruleaz[aă])\b/u', $lower);
    }
}
