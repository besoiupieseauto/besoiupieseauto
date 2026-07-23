<?php
declare(strict_types=1);

if (!function_exists('besoiu_product_reviews_html')) {
    /**
     * Secțiune recenzii produs — blocuri + formular adăugare.
     */
    function besoiu_product_reviews_html(bool $previewMode = false): string
    {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $reviews = [
            [
                'name' => 'Mihai P.',
                'date' => '12 aprilie 2026',
                'rating' => 5,
                'text' => 'Produs bun, livrare rapidă și compatibilitate confirmată după VIN.',
                'verified' => true,
            ],
            [
                'name' => 'Andrei T.',
                'date' => '3 martie 2026',
                'rating' => 5,
                'text' => 'Plăcuțele se potrivesc perfect pe Audi A3. Recomand magazinul.',
                'verified' => true,
            ],
            [
                'name' => 'Cristina M.',
                'date' => '18 februarie 2026',
                'rating' => 4,
                'text' => 'Calitate Bosch, ambalaj ok. Livrare în 2 zile.',
                'verified' => false,
            ],
        ];

        if ($previewMode) {
            $reviews = array_slice($reviews, 0, 2);
        }

        $html = '<div class="besoiu-reviews-section">';
        if ($previewMode) {
            $html .= '<p class="besoiu-reviews-preview-note">Previzualizare — recenziile reale apar pe pagina produsului.</p>';
        }

        $html .= '<div class="besoiu-reviews-list">';
        foreach ($reviews as $review) {
            $html .= besoiu_product_review_block_html($review, $h);
        }
        $html .= '</div>';

        $html .= '<div class="besoiu-review-form review-form">';
        $html .= '<h3><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Adaugă o recenzie</h3>';
        $html .= '<form class="besoiu-review-form__inner" action="#" method="post" onsubmit="return false;">';
        $html .= '<div class="form-field">';
        $html .= '<label for="besoiu-review-rating">Evaluarea ta <span class="required">*</span></label>';
        $html .= '<div class="besoiu-review-rating-input" role="group" aria-label="Stele evaluare">';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<button type="button" class="besoiu-review-star-btn" data-rating="' . $i . '" aria-label="' . $i . ' stele"><i class="fa-regular fa-star" aria-hidden="true"></i></button>';
        }
        $html .= '</div>';
        $html .= '<select name="rating" id="besoiu-review-rating" required hidden>';
        $html .= '<option value="">Evaluează…</option>';
        foreach ([5 => 'Perfect', 4 => 'Bun', 3 => 'Mediu', 2 => 'Slab', 1 => 'Foarte slab'] as $val => $label) {
            $html .= '<option value="' . $val . '">' . $h($label) . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="form-field">';
        $html .= '<label for="besoiu-review-text">Recenzia ta <span class="required">*</span></label>';
        $html .= '<textarea id="besoiu-review-text" name="review" rows="5" placeholder="Scrie recenzia ta aici…" required></textarea>';
        $html .= '</div>';
        $html .= '<div class="form-row">';
        $html .= '<div class="form-field"><label for="besoiu-review-name">Nume <span class="required">*</span></label>';
        $html .= '<input type="text" id="besoiu-review-name" name="name" required placeholder="Numele tău"></div>';
        $html .= '<div class="form-field"><label for="besoiu-review-email">Email <span class="required">*</span></label>';
        $html .= '<input type="email" id="besoiu-review-email" name="email" required placeholder="adresa@exemplu.ro"></div>';
        $html .= '</div>';
        $html .= '<div class="check-row"><input type="checkbox" id="besoiu-review-save"><label for="besoiu-review-save">Salvează numele și emailul meu pentru data viitoare.</label></div>';
        $html .= '<button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Trimite recenzia</button>';
        $html .= '</form></div></div>';

        return $html;
    }

    /**
     * @param array{name:string, date:string, rating:int, text:string, verified:bool} $review
     * @param callable(string):string $h
     */
    function besoiu_product_review_block_html(array $review, callable $h): string
    {
        $rating = max(1, min(5, (int) ($review['rating'] ?? 5)));
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= $rating ? 'fa-solid fa-star' : 'fa-regular fa-star';
            $stars .= '<i class="' . $class . '" aria-hidden="true"></i>';
        }

        $verified = !empty($review['verified'])
            ? '<span class="besoiu-review-block__badge"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Client verificat</span>'
            : '';

        return '<article class="besoiu-review-block">'
            . '<div class="besoiu-review-block__head">'
            . '<div class="besoiu-review-block__avatar" aria-hidden="true"><i class="fa-solid fa-user" aria-hidden="true"></i></div>'
            . '<div class="besoiu-review-block__meta">'
            . '<div class="besoiu-review-block__stars review-stars">' . $stars . '</div>'
            . '<div class="review-meta"><strong>' . $h((string) ($review['name'] ?? 'Client')) . '</strong>'
            . ' · ' . $h((string) ($review['date'] ?? '')) . '</div>'
            . $verified
            . '</div></div>'
            . '<p class="besoiu-review-block__text review-text">' . $h((string) ($review['text'] ?? '')) . '</p>'
            . '</article>';
    }
}
