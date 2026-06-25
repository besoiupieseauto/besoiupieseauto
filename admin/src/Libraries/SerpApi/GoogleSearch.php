<?php

namespace Evasystem\Libraries\SerpApi;

// Prevenim redeclararea clasei
if (!class_exists('GoogleSearchResults')) {
    require_once __DIR__ . '/../../../../vendor/serpapi/google-search-results-php/google-search-results.php';
}

class GoogleSearch extends \GoogleSearchResults {
    public function __construct(array $params) {
        parent::__construct($params['api_key']);
        $this->params = $params;
    }

    public function get_json($q = null) {
        return json_decode($this->get_results($q), false);
    }
}
