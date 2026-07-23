<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Evaluează dacă query-ul a avut context suficient și dacă cererea a fost îndeplinită. */
final class SectionAssistantOutcomeEvaluator
{
    /** @param array<string, mixed> $plan @param array<string, mixed> $input @param array<string, mixed> $context @return array<string, mixed> */
    public function evaluate(string $message, array $plan, array $input, array $context): array
    {
        $source = (string) ($plan['source'] ?? '');
        $missingContext = [];
        $notDone = [];

        $history = is_array($input['conversation_history'] ?? null) ? $input['conversation_history'] : [];
        $fullText = $this->combinedText($message, $history);

        if ($this->looksLikeProductAction($message) && !$this->hasProductCode($fullText)) {
            $missingContext[] = 'Cod produs (ex: 30204A)';
        }

        if ($this->looksLikeTitleEdit($message) && !preg_match('/\b(categor|subcategor|titlu|denumir)\b/ui', $message)) {
            $missingContext[] = 'Ce adaugi in titlu (categorie, subcategorie)';
        }

        if ($this->looksLikeBulkAction($message) && !preg_match('/\b(toate|tot\s+catalog|vizibil|selectat|\d+)\b/ui', $message)) {
            $missingContext[] = 'Scope: toate produsele, numar, sau selectie';
        }

        if ($this->looksLikeImportAction($message) && !preg_match('/\b(\d+|ulei|lichid|import|coada|consumabil)\b/ui', $message)) {
            $missingContext[] = 'Cate produse / ce filtru din import (ex: 5 ulei cu imagini)';
        }

        $status = 'ok';

        switch ($source) {
            case 'action_executed':
                $status = $missingContext === [] ? 'ok' : 'partial';
                break;
            case 'action_preview':
                $status = 'partial';
                $notDone[] = 'Actiunea asteapta confirmare (buton rosu Confirma)';
                break;
            case 'action_error':
                $status = 'fail';
                $notDone[] = trim((string) ($plan['reply_ro'] ?? 'Actiune esuata'));
                if (preg_match('/\bcod\b/ui', (string) ($plan['reply_ro'] ?? ''))) {
                    $missingContext[] = 'Cod produs explicit in mesaj';
                }
                break;
            case 'invoice_list':
            case 'awb_list':
            case 'cart_list':
            case 'orders_summary':
            case 'supplier_list':
            case 'client_list':
            case 'learned_adapter':
            case 'learned_pattern':
                $status = 'ok';
                break;
            case 'unknown_learnable':
                $status = 'partial';
                $notDone[] = 'Ruta necunoscuta — apasa Invata pentru adaptare';
                break;
            case 'learn_failed':
                $status = 'fail';
                $notDone[] = 'Invatare esuata — reformuleaza sau deschide modulul admin';
                break;
            default:
                if (str_starts_with($source, 'intent_router+') || str_starts_with($source, 'llm_intent+')) {
                    $status = 'ok';
                } elseif (SectionAssistantActionService::messageLooksLikeAction($message)) {
                    $status = 'fail';
                    $notDone[] = 'Cererea pare actiune executabila dar nu s-a rulat';
                    $missingContext[] = 'Comanda clara: actiune + detaliu (ex: edit titlu 30204A adauga categorie la final)';
                } elseif ($this->looksLikeSupplierInventory($message) && in_array($source, ['composer-2.5', 'openai', 'groq'], true)) {
                    $status = 'partial';
                    $notDone[] = 'Raspuns AI — foloseste: ce furnizori sunt pe proiect (inventar live)';
                } elseif ($this->looksLikeLiveInventory($message) && in_array($source, ['composer-2.5', 'openai', 'groq'], true)) {
                    $status = 'partial';
                    $notDone[] = 'Raspuns AI — prefera comenzi inventar live (ex: ce produse sunt online acum)';
                } elseif ($this->looksLikeInvoiceInventory($message) && $source !== 'invoice_list') {
                    $status = 'fail';
                    $notDone[] = 'Intrebare despre facturi — trebuie lista live din facturi, nu rezumat comenzi sau AI';
                } elseif ($this->looksLikeAwbInventory($message) && $source !== 'awb_list') {
                    $status = 'fail';
                    $notDone[] = 'Intrebare despre AWB — trebuie lista live din livrari, nu rezumat comenzi sau AI';
                } elseif (in_array($source, ['composer-2.5', 'openai', 'groq'], true) && trim((string) ($plan['reply_ro'] ?? '')) === '') {
                    $status = 'fail';
                    $notDone[] = 'Fara raspuns util';
                }
                break;
        }

        if (!empty($plan['llm_error'])) {
            $sanitized = mb_strtolower(trim((string) $plan['llm_error']), 'UTF-8');
            $isTechnicalNoise = str_contains($sanitized, 'traceback')
                || str_contains($sanitized, 'import error')
                || str_contains($sanitized, 'cursor-sdk')
                || str_contains($sanitized, 'line 16');
            $localDataSources = ['orders_summary', 'invoice_list', 'awb_list', 'cart_list', 'supplier_list', 'learned_adapter', 'learned_pattern'];
            if (!in_array($source, $localDataSources, true) && !$isTechnicalNoise) {
                $status = $status === 'ok' ? 'partial' : $status;
                $notDone[] = 'Eroare LLM: ' . mb_substr((string) $plan['llm_error'], 0, 120, 'UTF-8');
            }
        }

        $missingContext = array_values(array_unique(array_filter($missingContext)));
        $notDone = array_values(array_unique(array_filter($notDone)));

        $trainingHints = [];
        if ($missingContext !== []) {
            $trainingHints[] = 'Data viitoare include in mesaj: ' . implode(', ', $missingContext);
        }
        if ($notDone !== []) {
            $trainingHints[] = 'Neindeplinit: ' . implode('; ', array_slice($notDone, 0, 3));
        }
        if ($status === 'ok' && $history !== []) {
            $trainingHints[] = 'Context conversatie folosit (' . count($history) . ' mesaje anterioare).';
        }

        [$checks, $correctItems] = $this->buildChecks($status, $source, $missingContext, $notDone, $history);
        $evaluationLog = $this->buildEvaluationLog($status, $source, $checks, $message);

        return [
            'status' => $status,
            'outcome_label' => match ($status) {
                'ok' => 'OK — cerere indeplinita',
                'partial' => 'Partial — mai e un pas sau context incomplet',
                default => 'Nefinalizat — de invatat',
            },
            'missing_context' => $missingContext,
            'not_done' => $notDone,
            'training_hints' => $trainingHints,
            'checks' => $checks,
            'correct_items' => $correctItems,
            'evaluation_log' => $evaluationLog,
            'badge_ok' => $status === 'ok',
            'can_learn' => in_array($source, ['unknown_learnable', 'learn_failed'], true)
                || ($status !== 'ok' && $this->looksLikeLearnableQuestion($message)),
            'source' => $source,
            'section' => (string) ($context['section'] ?? ''),
        ];
    }

    /**
     * @param list<string> $missingContext
     * @param list<string> $notDone
     * @param list<array<string, mixed>> $history
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function buildChecks(
        string $status,
        string $source,
        array $missingContext,
        array $notDone,
        array $history
    ): array {
        $checks = [];
        $correctItems = [];

        $liveSources = [
            'action_executed',
            'invoice_list',
            'awb_list',
            'cart_list',
            'orders_summary',
            'supplier_list',
            'client_list',
            'product_list',
            'learned_adapter',
            'learned_pattern',
        ];
        $aiSources = ['composer-2.5', 'openai', 'groq'];

        if (in_array($source, $liveSources, true)) {
            $checks[] = [
                'id' => 'source',
                'label' => 'Sursa raspuns',
                'status' => 'pass',
                'detail' => 'Date live: ' . $source,
            ];
            $correctItems[] = 'Raspuns din sursa live (' . $source . ')';
        } elseif (in_array($source, $aiSources, true)) {
            $checks[] = [
                'id' => 'source',
                'label' => 'Sursa raspuns',
                'status' => $status === 'ok' ? 'pass' : 'warn',
                'detail' => 'Model AI: ' . $source,
            ];
        } elseif ($source !== '') {
            $checks[] = [
                'id' => 'source',
                'label' => 'Sursa raspuns',
                'status' => $status === 'ok' ? 'pass' : 'fail',
                'detail' => $source,
            ];
        } else {
            $checks[] = [
                'id' => 'source',
                'label' => 'Sursa raspuns',
                'status' => 'fail',
                'detail' => 'Sursa necunoscuta',
            ];
        }

        if ($missingContext === []) {
            $checks[] = [
                'id' => 'context_complete',
                'label' => 'Context mesaj',
                'status' => 'pass',
                'detail' => 'Toate informatiile necesare sunt prezente',
            ];
            $correctItems[] = 'Context mesaj complet';
        } else {
            foreach ($missingContext as $i => $missing) {
                $checks[] = [
                    'id' => 'context_' . $i,
                    'label' => 'Context lipsa',
                    'status' => 'fail',
                    'detail' => $missing,
                ];
            }
        }

        if ($notDone === [] && $status === 'ok') {
            $checks[] = [
                'id' => 'request_done',
                'label' => 'Cerere indeplinita',
                'status' => 'pass',
                'detail' => 'Toate actiunile cerute au fost finalizate',
            ];
            $correctItems[] = 'Cererea a fost indeplinita';
        } else {
            foreach ($notDone as $i => $item) {
                $checks[] = [
                    'id' => 'not_done_' . $i,
                    'label' => 'Neindeplinit',
                    'status' => 'fail',
                    'detail' => $item,
                ];
            }
        }

        if ($status === 'partial' && $notDone === [] && $missingContext !== []) {
            $checks[] = [
                'id' => 'partial_status',
                'label' => 'Status partial',
                'status' => 'warn',
                'detail' => 'Raspuns util dar lipseste context sau un pas intermediar',
            ];
        }

        if ($history !== []) {
            $checks[] = [
                'id' => 'conversation_thread',
                'label' => 'Istoric conversatie',
                'status' => 'pass',
                'detail' => count($history) . ' mesaje anterioare disponibile',
            ];
            if ($status === 'ok') {
                $correctItems[] = 'Context conversatie utilizat (' . count($history) . ' mesaje)';
            }
        }

        return [$checks, array_values(array_unique(array_filter($correctItems)))];
    }

    /** @param list<array<string, mixed>> $checks @return array<string, mixed> */
    private function buildEvaluationLog(string $status, string $source, array $checks, string $message): array
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            $st = (string) ($check['status'] ?? '');
            if ($st === 'pass') {
                ++$passed;
            } elseif ($st === 'warn') {
                ++$warnings;
            } else {
                ++$failed;
            }
        }

        return [
            'evaluated_at' => date('c'),
            'auto_status' => $status,
            'source' => $source,
            'message_excerpt' => mb_substr(trim($message), 0, 120, 'UTF-8'),
            'summary' => [
                'passed' => $passed,
                'failed' => $failed,
                'warnings' => $warnings,
                'total' => count($checks),
            ],
        ];
    }

    /** @param list<array<string, mixed>> $history */
    private function combinedText(string $message, array $history): string
    {
        $parts = [$message];
        foreach ($history as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $parts[] = (string) ($turn['message'] ?? '');
        }

        return implode("\n", $parts);
    }

    private function hasProductCode(string $text): bool
    {
        if (preg_match('/\b(?:cod(?:ul)?|code|sku)\s*[:\s-]*([A-Za-z0-9\-_.]+)/ui', $text)) {
            return true;
        }

        return (bool) preg_match('/\b(\d{4,}[A-Z0-9]{0,4})\b/u', strtoupper($text));
    }

    private function looksLikeProductAction(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(titlu|denumire|badge|produs|30204|edit|modific|fx)\b/u', $lower)
            && (bool) preg_match('/\b(edit|modific|adaug|badge|fx|fix)\b/u', $lower);
    }

    private function looksLikeTitleEdit(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(titlu|denumire|nume)\b/u', $lower)
            && (bool) preg_match('/\b(edit|modific|adaug|fx|fix)\b/u', $lower);
    }

    private function looksLikeBulkAction(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(badge|adaos|vitrin|export|publica)\b/u', $lower)
            && (bool) preg_match('/\b(aplic|pune|setez|fa|execut)\b/u', $lower);
    }

    private function looksLikeImportAction(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(import|coada|publica|adaug[aă]?)\b/u', $lower)
            && (bool) preg_match('/\b(din import|din coada|produse)\b/u', $lower);
    }

    private function looksLikeLiveInventory(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(ce produse|cate produse|inventar|online acum|categorii|subcategorii)\b/u', $lower);
    }

    private function looksLikeSupplierInventory(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(furnizor\w*|supplier\w*)\b/u', $lower)
            && (bool) preg_match('/\b(ce|cate|care|lista|sunt|exist[aă]|proiect|acum)\b/u', $lower);
    }

    private function looksLikeInvoiceInventory(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(factur\w*|invoice\w*)\b/u', $lower)
            && (bool) preg_match('/\b(ce|cate|care|lista|sunt|exist[aă]|acum|generate|generat\w*|avem)\b/u', $lower);
    }

    private function looksLikeAwbInventory(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match('/\b(awb|livr[aă]ri|curier|expedi\w*)\b/u', $lower)
            && (bool) preg_match('/\b(ce|cate|care|lista|sunt|exist[aă]|acum|generate|generat\w*|avem)\b/u', $lower);
    }

    private function looksLikeLearnableQuestion(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        return (bool) preg_match(
            '/\b(ce|cate|care|lista|sunt|exist[aă]|avem|acum|generate|generat\w*)\b/u',
            $lower
        );
    }
}
