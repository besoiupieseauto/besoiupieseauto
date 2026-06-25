<?php

namespace Evasystem\Services;

class PromptGenerator {
    public function build(array $data, array $websiteData, array $smmData): string {
        $prompt = "Analizează următoarea firmă pe baza datelor oferite. Răspunde doar în baza acestor date, fără a inventa.

";

        $prompt .= "1. 📌 **Informații firmă**\n";
        $prompt .= "- Nume: {$data['denumire']}\n";
        $prompt .= "- Cod fiscal (IDNO): {$data['idno']}\n";
        $prompt .= "- Domeniu: {$data['domeniu']}\n";
        $prompt .= "- Website: {$data['website']}\n";

        $prompt .= "\n2. 🌐 **Date website**\n";
        $prompt .= "- Titlu pagină: {$websiteData['title']}\n";
        $prompt .= "- Facebook Pixel: " . ($websiteData['has_facebook_pixel'] ? 'DA' : 'NU') . "\n";
        $prompt .= "- Google Tag Manager: " . ($websiteData['has_google_ads'] ? 'DA' : 'NU') . "\n";

        $prompt .= "\n3. 📣 **Social Media**\n";
        $prompt .= "- Facebook: " . ($smmData['facebook'] ? 'activ' : 'inactiv') . "\n";
        $prompt .= "- Instagram: " . ($smmData['instagram'] ? 'activ' : 'inactiv') . "\n";
        $prompt .= "- LinkedIn: " . ($smmData['linkedin'] ? 'activ' : 'inactiv') . "\n";

        $prompt .= "\n4. ⚔️ **Cerință analiză**\n";
        $prompt .= "Pe baza acestor date, oferă următoarele:\n";
        $prompt .= "- O analiză succintă dar clară privind gradul de digitalizare\n";
        $prompt .= "- Ce lipsește evident\n";
        $prompt .= "- Ce ar trebui făcut urgent\n";
        $prompt .= "- 3 pași concreți pentru îmbunătățire\n";

        $prompt .= "\nEvită generalitățile și nu presupune nimic ce nu e evident din datele de mai sus.";

        return $prompt;
    }
}
?>