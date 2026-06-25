<?php

namespace Evasystem\Services;

use Evasystem\Services\WebsiteCrawler;

class RealCompetitorAnalyzer {
    private WebsiteCrawler $crawler;

    public function __construct() {
        $this->crawler = new WebsiteCrawler();
    }

    public function analyzeMultiple(array $companyNames): string {
        $output = "<h3>🔎 Concurenți analizați:</h3><ul>";

        foreach ($companyNames as $companyName) {
            $url = $this->findWebsiteForCompany($companyName);

            if (!$url) {
                $output .= "<li><strong>$companyName</strong>: domeniu negăsit</li>";
                continue;
            }

            $data = $this->crawler->analyze($url);

            if (isset($data['error'])) {
                $output .= "<li><strong>$companyName</strong>: site inaccesibil</li>";
                continue;
            }

            $output .= "<li><strong>{$companyName}</strong><br>
            Titlu pagină: {$data['title']}<br>
            Facebook Pixel: " . ($data['has_facebook_pixel'] ? 'DA' : 'NU') . "<br>
            Google Tag Manager: " . ($data['has_google_ads'] ? 'DA' : 'NU') . "<br>
            Google Analytics: " . ($data['has_google_analytics'] ? 'DA' : 'NU') . "<br>
            Facebook Link în pagină: " . ($data['social_links']['facebook'] ? 'DA' : 'NU') . "<br>
            Trafic estimat: " . ($data['estimated_traffic']['monthly_visits'] ?? 'necunoscut') . " vizite/lună<br>
            </li><br>";
        }

        $output .= "</ul>";
        return $output;
    }

    private function findWebsiteForCompany(string $company): ?string {
        $token = $_ENV['APIFY_TOKEN'];
        $actorUrl = "https://api.apify.com/v2/acts/apify~google-search-scraper/run-sync-get-dataset-items?token=$token";

        $postData = json_encode([
            'queries' => [$company],
            'maxPagesPerQuery' => 1,
            'resultsPerPage' => 5
        ]);

        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0]['url'] ?? null;
    }

    public function getCrawler(): WebsiteCrawler {
        return $this->crawler;
    }
}
