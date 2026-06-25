<?php

namespace Evasystem\Services;

use GoogleSearchResults;

class SmmScraperService {
    private $serpApiKey;

    public function __construct() {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $this->serpApiKey = $_ENV['SERPAPI_KEY'];
    }

    public function analyze($firma) {
        $platforms = ['facebook', 'instagram', 'linkedin'];
        $results = [];

        foreach ($platforms as $platform) {
            $query = $firma . ' site:' . $platform . '.com';

            $search = new GoogleSearchResults($this->serpApiKey);
            $data = $search->get_json([
                'q' => $query,
                'hl' => 'ro',
                'gl' => 'ro'
            ]);

            $results[$platform] = false;

            foreach ($data->organic_results ?? [] as $result) {
                if (isset($result->link) && str_contains($result->link, "$platform.com")) {
                    $results[$platform] = $result->link;
                    break;
                }
            }
        }

        return $results;
    }
}
