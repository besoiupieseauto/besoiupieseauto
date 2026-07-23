<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Checkout tip quiz — întrebări pas cu pas până la plasare comandă.
 */
final class ShopChatCheckoutQuizService
{
    /** @var list<string> */
    private const STEPS = [
        'product_confirm',
        'delivery',
        'payment',
        'contact_name',
        'contact_phone',
        'contact_city',
        'contact_address',
        'confirm',
    ];

    /**
     * @param list<array<string, mixed>> $cart
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function start(array $cart, array &$state): array
    {
        if ($cart === []) {
            return [
                'handled' => true,
                'reply' => 'Spune mai întâi ce produs vrei — apoi începem comanda pas cu pas.',
                'ui_action' => 'none',
            ];
        }

        $quiz = [
            'step' => 'product_confirm',
            'cart' => $cart,
            'answers' => [],
        ];
        ShopChatSessionService::setQuiz($quiz, $state);

        $item = $cart[0];
        $name = (string) ($item['name'] ?? 'Produs');
        $price = (string) ($item['price'] ?? '');

        return [
            'handled' => true,
            'reply' => "Perfect! Ai ales:\n✓ {$name} · {$price} RON\n\nConfirmi acest produs? (da / nu)",
            'ui_action' => 'quiz',
            'quiz_step' => 'product_confirm',
            'quiz_options' => ['Da, confirm', 'Nu, alt produs'],
            'cart' => $cart,
            'products' => $cart,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    public function handleMessage(string $message, array &$state, array $apiInput = []): ?array
    {
        $quiz = ShopChatSessionService::getQuiz($state);
        if ($quiz === null) {
            return null;
        }

        $step = (string) ($quiz['step'] ?? '');
        $answers = is_array($quiz['answers'] ?? null) ? $quiz['answers'] : [];
        $cart = is_array($quiz['cart'] ?? null) ? $quiz['cart'] : [];
        $lower = mb_strtolower(trim($message), 'UTF-8');

        if ($step === 'product_confirm') {
            if (preg_match('/\b(nu|alt|schimb)\b/u', $lower)) {
                ShopChatSessionService::setQuiz(null, $state);

                return [
                    'handled' => true,
                    'reply' => 'OK — spune ce alt produs vrei sau rafinează căutarea (preț, brand).',
                    'ui_action' => 'none',
                ];
            }
            if (!preg_match('/\b(da|ok|confirm|merge|perfect)\b/u', $lower)) {
                return $this->prompt($step, $cart, $answers);
            }
            $answers['product_confirmed'] = true;
            $quiz['answers'] = $answers;
            $quiz['step'] = 'delivery';
            ShopChatSessionService::setQuiz($quiz, $state);

            return $this->prompt('delivery', $cart, $answers);
        }

        if ($step === 'delivery') {
            $delivery = $this->matchOption($lower, [
                'ridicare_locala' => ['ridicare', 'magazin', 'local'],
                'tarif_fix' => ['livrare', 'curier', 'acasa', 'acasă'],
            ]);
            if ($delivery === null) {
                return $this->prompt('delivery', $cart, $answers);
            }
            $answers['delivery_method'] = $delivery;
            $answers['delivery_label'] = $delivery === 'ridicare_locala' ? 'Ridicare magazin' : 'Livrare curier';
            $quiz['answers'] = $answers;
            $quiz['step'] = 'payment';
            ShopChatSessionService::setQuiz($quiz, $state);

            return $this->prompt('payment', $cart, $answers);
        }

        if ($step === 'payment') {
            $payment = $this->matchOption($lower, [
                'ramburs' => ['ramburs', 'livrare'],
                'numerar' => ['numerar', 'cash'],
                'card_fizic' => ['card', 'pos'],
            ]);
            if ($payment === null) {
                return $this->prompt('payment', $cart, $answers);
            }
            $answers['payment_method'] = $payment;
            $answers['payment_label'] = match ($payment) {
                'numerar' => 'Numerar',
                'card_fizic' => 'Card la ridicare',
                default => 'Ramburs',
            };
            $quiz['answers'] = $answers;
            $quiz['step'] = 'contact_name';
            ShopChatSessionService::setQuiz($quiz, $state);
            $existing = ShopChatSessionService::clientName($state);

            if ($existing !== '') {
                $answers['client_name'] = $existing;
                $quiz['answers'] = $answers;
                $quiz['step'] = 'contact_phone';
                ShopChatSessionService::setQuiz($quiz, $state);

                return $this->prompt('contact_phone', $cart, $answers);
            }

            return $this->prompt('contact_name', $cart, $answers);
        }

        if ($step === 'contact_name') {
            $name = trim($message);
            if (mb_strlen($name) < 2) {
                return $this->prompt('contact_name', $cart, $answers);
            }
            $answers['client_name'] = $name;
            ShopChatSessionService::setClientName($name, $state);
            $quiz['answers'] = $answers;
            $quiz['step'] = 'contact_phone';
            ShopChatSessionService::setQuiz($quiz, $state);

            return $this->prompt('contact_phone', $cart, $answers);
        }

        if ($step === 'contact_phone') {
            if (!preg_match('/(\+?4?0?\d{9,10})/', $message, $m)) {
                return $this->prompt('contact_phone', $cart, $answers);
            }
            $answers['phone'] = preg_replace('/\D+/', '', $m[1]);
            $quiz['answers'] = $answers;
            $quiz['step'] = 'contact_city';
            ShopChatSessionService::setQuiz($quiz, $state);

            return $this->prompt('contact_city', $cart, $answers);
        }

        if ($step === 'contact_city') {
            $city = trim($message);
            if (mb_strlen($city) < 2) {
                return $this->prompt('contact_city', $cart, $answers);
            }
            $answers['city'] = $city;
            $quiz['answers'] = $answers;
            $quiz['step'] = 'contact_address';
            ShopChatSessionService::setQuiz($quiz, $state);

            if (($answers['delivery_method'] ?? '') === 'ridicare_locala') {
                $quiz['step'] = 'confirm';
                ShopChatSessionService::setQuiz($quiz, $state);

                return $this->prompt('confirm', $cart, $answers);
            }

            return $this->prompt('contact_address', $cart, $answers);
        }

        if ($step === 'contact_address') {
            $answers['address'] = trim($message);
            $quiz['answers'] = $answers;
            $quiz['step'] = 'confirm';
            ShopChatSessionService::setQuiz($quiz, $state);

            return $this->prompt('confirm', $cart, $answers);
        }

        if ($step === 'confirm') {
            if (!preg_match('/\b(da|confirm|plasez|ok)\b/u', $lower)) {
                return $this->prompt('confirm', $cart, $answers);
            }

            ShopChatSessionService::setQuiz(null, $state);
            $state['cart'] = $cart;
            $state['quiz_form'] = $answers;

            $lines = [];
            $total = 0.0;
            foreach ($cart as $item) {
                $p = (float) ($item['price_num'] ?? str_replace(',', '.', preg_replace('/[^\d.,]/', '', (string) ($item['price'] ?? '')) ?? ''));
                $total += $p;
                $lines[] = '• ' . ($item['name'] ?? '') . ' — ' . ($item['price'] ?? '') . ' RON';
            }

            return [
                'handled' => true,
                'reply' => "Comanda ta este pregătită:\n" . implode("\n", $lines)
                    . "\nTotal: " . number_format($total, 2, ',', '.') . " RON\n\n"
                    . 'Apasă „Plasează comanda” mai jos sau completează ultimele detalii.',
                'ui_action' => 'order_form',
                'cart' => $cart,
                'products' => $cart,
                'quiz_complete' => true,
                'form_prefill' => $answers,
            ];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $cart
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    private function prompt(string $step, array $cart, array $answers): array
    {
        $prompts = [
            'product_confirm' => ['Confirmi produsul ales?', ['Da, confirm', 'Nu, alt produs']],
            'delivery' => ['Cum preferați livrarea?', ['Ridicare magazin', 'Livrare curier']],
            'payment' => ['Modalitate de plată?', ['Ramburs', 'Numerar', 'Card la ridicare']],
            'contact_name' => ['Numele complet pentru comandă?', []],
            'contact_phone' => ['Telefon de contact? (ex. 0726...)', []],
            'contact_city' => ['Localitatea?', []],
            'contact_address' => ['Adresa de livrare?', []],
            'confirm' => [$this->buildSummary($cart, $answers) . "\n\nPlasez comanda?", ['Da, plasez', 'Nu']],
        ];

        [$text, $options] = $prompts[$step] ?? ['Continuăm comanda?', []];

        return [
            'handled' => true,
            'reply' => $text,
            'ui_action' => 'quiz',
            'quiz_step' => $step,
            'quiz_options' => $options,
            'cart' => $cart,
        ];
    }

    /** @param list<array<string, mixed>> $cart @param array<string, mixed> $answers */
    private function buildSummary(array $cart, array $answers): string
    {
        $item = $cart[0] ?? [];
        $lines = [
            'Rezumat comandă:',
            '• ' . ($item['name'] ?? '') . ' — ' . ($item['price'] ?? '') . ' RON',
            '• Livrare: ' . ($answers['delivery_label'] ?? '—'),
            '• Plată: ' . ($answers['payment_label'] ?? '—'),
            '• Client: ' . ($answers['client_name'] ?? '—') . ', ' . ($answers['phone'] ?? '—'),
        ];
        if (!empty($answers['city'])) {
            $lines[] = '• Localitate: ' . $answers['city'];
        }
        if (!empty($answers['address'])) {
            $lines[] = '• Adresă: ' . $answers['address'];
        }

        return implode("\n", $lines);
    }

    /** @param array<string, list<string>> $map */
    private function matchOption(string $lower, array $map): ?string
    {
        foreach ($map as $key => $words) {
            foreach ($words as $w) {
                if (str_contains($lower, $w)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
