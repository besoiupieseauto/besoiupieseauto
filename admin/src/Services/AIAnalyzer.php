<?php

namespace Evasystem\Services;

use GuzzleHttp\Client;
use Evasystem\Services\WebsiteCrawler;
use Evasystem\Services\SmmAnalyzer;
use Evasystem\Services\PromptGenerator;
use Evasystem\Services\RealCompetitorAnalyzer;
use Evasystem\Services\SmmScraperService;

class AIAnalyzer {
    private $openaiKey;
    private WebsiteCrawler $crawler;
    private SmmAnalyzer $smm;
    private SmmScraperService $scraper;
    private RealCompetitorAnalyzer $competitor;

    public function __construct() {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $this->openaiKey = $_ENV['OPENAI_KEY'];

        $this->crawler = new WebsiteCrawler();
        $this->smm = new SmmAnalyzer();
        $this->scraper = new SmmScraperService();
        $this->competitor = new RealCompetitorAnalyzer();
    }

    public function generateReport(array $data): string {
        if($data['website'] == ''){
            $web = 'https://galacweb.com/';
        }  else {
            $web = $data['website'];
        }
        $websiteData = $this->crawler->analyze($web);
        $scrapedLinks = $this->scraper->analyze($data['denumire']);
        $smmData = $this->smm->analyze($scrapedLinks);

        $keywords = $data['domeniu'] . ' ' . $data['denumire'] . ' Moldova';
        $competitorNames = $this->findCompetitorsFromSearch($keywords);
        $competitorHtml = $this->competitor->analyzeMultiple($competitorNames);

        $prompt = (new PromptGenerator())->build($data, $websiteData, $smmData);
        $response = $this->askGPT($prompt);
        $_SESSION['recomandari'] = $response;
        // HTML structurat cu Bootstrap
        $output = "<div class='card mb-4'><div class='card-body'>";
        $output .= "<h3 class='card-title'>📌 {$data['denumire']}</h3>";
        $output .= "<p><strong>IDNO:</strong> {$data['idno']}<br>";
        $output .= "<strong>Domeniu:</strong> {$data['domeniu']}<br>";
        $output .= "<strong>Website:</strong> <a href='{$data['website']}' target='_blank'>{$data['website']}</a></p>";
        $output .= "</div></div>";

        $output .= "<div class='row'>
        <div class='col-md-6'><div class='card mb-4'><div class='card-body'>
        <h5>📊 Website</h5>
        <ul>
        <li><strong>Status:</strong> {$websiteData['status_code']}</li>
        <li><strong>Răspuns:</strong> {$websiteData['response_time_ms']} ms</li>
        <li><strong>Titlu:</strong> {$websiteData['title']}</li>
        <li><strong>Descriere:</strong> {$websiteData['description']}</li>
        <li><strong>Pixel Facebook:</strong> " . ($websiteData['has_facebook_pixel'] ? 'DA' : 'NU') . "</li>
        <li><strong>Google Ads:</strong> " . ($websiteData['has_google_ads'] ? 'DA' : 'NU') . "</li>
        <li><strong>Google Analytics:</strong> " . ($websiteData['has_google_analytics'] ? 'DA' : 'NU') . "</li>
        <li><strong>Favicon:</strong> " . ($websiteData['has_favicon'] ? 'DA' : 'NU') . "</li>
        </ul></div></div></div>

        <div class='col-md-6'><div class='card mb-4'><div class='card-body'>
        <h5>📣 Social Media</h5>
        <ul>
        <li><strong>Facebook:</strong> <a href='{$scrapedLinks['facebook']}' target='_blank'>{$scrapedLinks['facebook']}</a></li>
        <li><strong>Instagram:</strong> <a href='{$scrapedLinks['instagram']}' target='_blank'>{$scrapedLinks['instagram']}</a></li>
        <li><strong>LinkedIn:</strong> <a href='{$scrapedLinks['linkedin']}' target='_blank'>{$scrapedLinks['linkedin']}</a></li>
        </ul></div></div></div>
        </div>";

        $output .= "<div class='card mb-4'><div class='card-body'>
        <h5>🧠 Recomandări AI</h5>
        <pre style='white-space:pre-wrap'>{$response}</pre>
        </div></div>";

        $output .= "<div class='card mb-4'><div class='card-body'>
        <h5>🔎 Concurenți analizați</h5>
        {$competitorHtml}
        </div>";

        $output .= "</div>";

        return $output;
    }

    private function askGPT(string $prompt): string {
        $client = new Client();
        $res = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1200,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
            ]
        ]);

        $body = json_decode($res->getBody(), true);
        return $body['choices'][0]['message']['content'] ?? 'Eroare la interpretarea răspunsului AI.';
    }

    private function findCompetitorsFromSearch(string $keywords): array {
        $token = $_ENV['APIFY_TOKEN'];
        $actorUrl = "https://api.apify.com/v2/acts/apify~google-search-scraper/run-sync-get-dataset-items?token=$token";

        $postData = json_encode([
            'queries' => [$keywords],
            'maxPagesPerQuery' => 1,
            'resultsPerPage' => 10
        ]);

        $ch = curl_init($actorUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        $companies = [];

        foreach ($data as $entry) {
            if (isset($entry['title'])) {
                $title = $entry['title'];
                if (stripos($title, $keywords) === false) {
                    $companies[] = trim(preg_replace('/[^A-Za-z0-9 .-]/', '', $title));
                }
            }
        }

        return array_slice(array_unique($companies), 0, 3);
    }
}
