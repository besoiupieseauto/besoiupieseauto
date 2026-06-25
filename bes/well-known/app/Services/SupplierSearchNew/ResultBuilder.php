<?php

namespace App\Services\SupplierSearchNew;

use App\Services\SupplierSearchNew\Contracts\SupplierParserInterface;
use App\Services\SupplierSearchNew\Parsers\MateromParser;
use App\Services\SupplierSearchNew\Parsers\AutopartnerParser;
use App\Services\SupplierSearchNew\Parsers\AutonetParser;
use App\Services\SupplierSearchNew\Parsers\AutototalParser;
use App\Services\SupplierSearchNew\Parsers\ElitParser;

class ResultBuilder
{
    /** @var array<string, SupplierParserInterface> */
    protected array $parsers = [];

    public function __construct(
        protected ProductAggregator $aggregator,
        MateromParser $materomParser,
        AutopartnerParser $autopartnerParser,
        AutonetParser $autonetParser,
        AutototalParser $autototalParser,
        ElitParser $elitParser
    ) {
        $this->parsers = [
            'materom' => $materomParser,
            'autopartner' => $autopartnerParser,
            'autonet' => $autonetParser,
            'autototal' => $autototalParser,
            'elit' => $elitParser,
        ];
    }

    /**
     * Build productsMap from pool responses.
     *
     * @param string $query Normalized search query
     * @param array $selectedSuppliers List of supplier keys
     * @param array $responses Map of supplier_name => Http Response
     */
    public function build(string $query, array $selectedSuppliers, array $responses, array $context = []): array
    {
        $allEntries = [];

        foreach ($selectedSuppliers as $supplier) {
            $parser = $this->parsers[$supplier] ?? null;
            if ($parser === null) {
                continue;
            }

            $response = $responses[$supplier] ?? null;
            // In pooled calls a supplier slot can contain an exception object
            // (e.g. ConnectException) instead of an HTTP response.


            if (
                $response === null ||
                !is_object($response) ||
                !method_exists($response, 'successful') ||
                !$response->successful() ||
                !method_exists($response, 'json') ||
                !method_exists($response, 'body')
            ) {
                continue;
            }

            if ($supplier === 'autonet' && method_exists($parser, 'setRequestRowMap')) {
                $parser->setRequestRowMap($context['autonet_row_map'] ?? []);
            }
            if ($supplier === 'autopartner' && method_exists($parser, 'setSeedMap')) {
                $parser->setSeedMap($context['autopartner_seed_map'] ?? []);
            }

            $rawResponse = $response->json();
            $rawBody = $response->body();
            $entries = $parser->parse($query, $rawResponse, $rawBody);
            foreach ($entries as $entry) {
                $allEntries[] = $entry;
            }
        }

        return $this->aggregator->merge($allEntries);
    }
}
