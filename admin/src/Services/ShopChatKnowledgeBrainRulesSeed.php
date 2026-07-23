<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Cele 20 reguli creier central → intrări system_rule în shop_chat_knowledge.
 */
final class ShopChatKnowledgeBrainRulesSeed
{
    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        $out = [];
        foreach (AiCentralBrainRules::all() as $rule) {
            $out[] = self::entryFromRule($rule);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function entryForId(int $ruleId): ?array
    {
        foreach (AiCentralBrainRules::all() as $rule) {
            if ((int) ($rule['id'] ?? 0) === $ruleId) {
                return self::entryFromRule($rule);
            }
        }

        return null;
    }

    /**
     * @return array{rule_id:int,seed_key:string,test_message:string,title:string}|null
     */
    public static function ruleMeta(int $ruleId): ?array
    {
        foreach (AiCentralBrainRules::all() as $rule) {
            if ((int) ($rule['id'] ?? 0) !== $ruleId) {
                continue;
            }

            return [
                'rule_id' => $ruleId,
                'seed_key' => self::seedKey($ruleId),
                'test_message' => self::testMessage($rule),
                'title' => (string) ($rule['title'] ?? ''),
            ];
        }

        return null;
    }

    /** @param array{id:int,icon:string,title:string,rule:string,zone:string,priority:string} $rule */
    private static function entryFromRule(array $rule): array
    {
        $id = (int) ($rule['id'] ?? 0);
        $priority = match ((string) ($rule['priority'] ?? 'P1')) {
            'P0' => 95,
            'P1' => 82,
            default => 68,
        };

        return ShopChatKnowledgeSeedHelper::entry(
            self::seedKey($id),
            'system_rule',
            trim((string) ($rule['icon'] ?? '') . ' ' . (string) ($rule['title'] ?? '')),
            (string) ($rule['rule'] ?? ''),
            'enforce',
            'brain_rule',
            'Respectă: ' . (string) ($rule['rule'] ?? ''),
            self::examplesForRule($rule),
            [
                'source' => 'brain_rules_v1',
                'brain_rule_id' => $id,
                'zone' => (string) ($rule['zone'] ?? ''),
                'priority_label' => (string) ($rule['priority'] ?? 'P1'),
                'test_message' => self::testMessage($rule),
            ],
            $priority,
            'both',
        );
    }

    private static function seedKey(int $id): string
    {
        return sprintf('brain_rule_%02d', max(1, min(20, $id)));
    }

    /** @param array{id:int,title:string,rule:string} $rule */
    private static function testMessage(array $rule): string
    {
        return match ((int) ($rule['id'] ?? 0)) {
            1 => 'cat costa disc frana bosch',
            2 => 'ce agent foloseste chatul',
            3 => 'ceva de frana golf nu scump',
            4 => 'reguli rag chat',
            5 => 'poti sa imi faci reducere',
            6 => 'flux intern admin chat',
            7 => 'supervizor erori import',
            8 => 'agent specialist catalog',
            9 => 'tokeni ai buget',
            10 => 'evenimente live admin',
            11 => 'marker dom element',
            12 => 'categorii tecdoc cms',
            13 => 'randomn_id produs',
            14 => 'cos checkout comanda',
            15 => 'whatsapp 3 preturi',
            16 => 'am inteles ce caut',
            17 => 'confidence decode mesaj',
            18 => 'composer repair auto',
            19 => 'biblioteca rag agent',
            20 => 'functie noua contract',
            default => mb_strtolower((string) ($rule['title'] ?? 'regula')),
        };
    }

    /** @param array{id:int,title:string} $rule @return list<string> */
    private static function examplesForRule(array $rule): array
    {
        $id = (int) ($rule['id'] ?? 0);
        $title = mb_strtolower((string) ($rule['title'] ?? ''));

        $base = match ($id) {
            1 => ['pret stoc', 'cat costa', 'disponibil', 'inventezi pret'],
            2 => ['context master', 'agent activ', 'router ai'],
            3 => ['decode mesaj', 'plan json', 'search terms'],
            4 => ['rag chat', 'shop chat knowledge', 'context injectat'],
            5 => ['reducere', 'discount', 'decizie comerciala'],
            6 => ['widget extern', 'intern admin', 'canal chat'],
            7 => ['supervizor', 'cron monitor', 'propuneri autonomie'],
            8 => ['agent folder', 'specialist mic', 'runtime md'],
            9 => ['tokeni', 'buget zilnic', 'metro llm'],
            10 => ['evenimente', 'live cron', 'memorie ai'],
            11 => ['marker dom', 'marcheaza ui', 'dom_marker'],
            12 => ['tecdoc', 'api categorii', 'cms categorii'],
            13 => ['randomn_id', 'id produs', 'status ascuns'],
            14 => ['cos', 'checkout', 'localStorage cart'],
            15 => ['whatsapp', 'economic mediu premium', '3 preturi'],
            16 => ['am inteles', 'preamble', 'verificare output'],
            17 => ['incredere', 'confidence', 'clarify refine'],
            18 => ['composer repair', 'auto execute', 'aprobari'],
            19 => ['biblioteca rag', 'learned md', 'context library'],
            20 => ['scope contract', 'acord aditional', 'om in bucla'],
            default => [$title, 'regula ' . $id],
        };

        return array_values(array_unique(array_merge([self::testMessage($rule)], $base)));
    }
}
