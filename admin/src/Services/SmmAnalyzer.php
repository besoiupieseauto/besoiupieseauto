<?php

namespace Evasystem\Services;

class SmmAnalyzer {
    private string $apifyToken;

    public function __construct() {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $this->apifyToken = $_ENV['APIFY_TOKEN'];
    }

    public function analyze(array $socialLinks): array {
        $results = [];

        if (!empty($socialLinks['facebook'])) {
            $results['facebook'] = $this->scrapeFacebookDetails($socialLinks['facebook']);
        } else {
            $results['facebook'] = false;
        }

        if (!empty($socialLinks['instagram'])) {
            $results['instagram'] = $this->scrapeInstagramDetails($socialLinks['instagram']);
        } else {
            $results['instagram'] = false;
        }

        if (!empty($socialLinks['linkedin'])) {
            $results['linkedin'] = 'Verificare avansată LinkedIn poate necesita API dedicat.';
        } else {
            $results['linkedin'] = false;
        }

        return $results;
    }

    private function scrapeFacebookDetails(string $url): array {
        $actorUrl = "https://api.apify.com/v2/acts/trudax~facebook-page-scraper/run-sync-get-dataset-items?token={$this->apifyToken}";

        $postData = json_encode(['startUrls' => [['url' => $url]]]);

        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0] ?? ['error' => 'No data from Facebook actor'];
    }

    private function scrapeInstagramDetails(string $url): array {
        $actorUrl = "https://api.apify.com/v2/acts/apify~instagram-profile-crawler/run-sync-get-dataset-items?token={$this->apifyToken}";

        $postData = json_encode(['usernames' => [basename($url)]]);

        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0] ?? ['error' => 'No data from Instagram actor'];
    }
}
