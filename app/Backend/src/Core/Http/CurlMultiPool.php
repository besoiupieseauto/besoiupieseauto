<?php

declare(strict_types=1);

namespace Besoiu\Core\Http;

/**
 * curl_multi cu deadline — evită bucle infinite (Windows / conexiuni blocate).
 */
final class CurlMultiPool
{
    /**
     * @param list<\CurlHandle|resource> $handles
     * @param array<int, array{body:string,status:int,error:string}>|null $outResults
     */
    public static function run(array $handles, float $deadlineSeconds = 25.0, float $selectTimeout = 0.25, ?array &$outResults = null): void
    {
        if ($handles === []) {
            $outResults = [];
            return;
        }

        $multi = curl_multi_init();
        if ($multi === false) {
            $outResults = [];
            foreach ($handles as $handle) {
                if (is_resource($handle) || $handle instanceof \CurlHandle) {
                    curl_close($handle);
                }
            }

            return;
        }

        try {
            foreach ($handles as $handle) {
                curl_multi_add_handle($multi, $handle);
            }

            $deadline = microtime(true) + max(1.0, $deadlineSeconds);
            $running = null;

            do {
                $status = curl_multi_exec($multi, $running);
                if ($status !== CURLM_OK) {
                    break;
                }

                if ($running <= 0) {
                    break;
                }

                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    break;
                }

                curl_multi_select($multi, min($selectTimeout, $remaining));
            } while ($running > 0);
        } finally {
            $outResults = [];
            foreach ($handles as $index => $handle) {
                $outResults[$index] = [
                    'body' => (string) curl_multi_getcontent($handle),
                    'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                    'error' => trim((string) curl_error($handle)),
                ];
                curl_multi_remove_handle($multi, $handle);
            }
            curl_multi_close($multi);
        }
    }
}
