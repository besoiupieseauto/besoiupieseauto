<?php



declare(strict_types=1);



namespace Evasystem\Services\PieseAuto;



/**

 * Stare conexiune PieseAuto — port serviciu, browser, platformă.

 */

final class PieseAutoStatusService

{

    /**

     * @param bool $liveProbe false = rapid (pagină); true = sondare robot (doar dacă PHP ajunge la robot)

     * @return array<string, mixed>

     */

    public function snapshot(string $targetUser = 'besoiu', bool $liveProbe = false): array

    {

        $targetUser = trim($targetUser) !== '' ? trim($targetUser) : 'besoiu';

        $scopedId = PieseAutoRobotConfig::scopedContId($targetUser);

        $listener = PieseAutoRobotConfig::readListenerFile();

        $runtime = PieseAutoRobotConfig::readRuntimeFile();

        $port = PieseAutoRobotConfig::listenerPortFromDisk();

        $serviceUrl = PieseAutoRobotConfig::tunnelUrl() !== ''

            ? PieseAutoRobotConfig::tunnelUrl()

            : 'http://127.0.0.1:' . $port;



        $canProbe = PieseAutoRobotConfig::robotReachableFromPhp();

        $ping = $this->resolvePing($listener, $runtime, $port, $serviceUrl, $liveProbe && $canProbe);

        $serviceOnline = (bool) ($ping['online'] ?? false);



        if ($serviceOnline && isset($ping['resolved_url']) && is_string($ping['resolved_url'])) {

            $serviceUrl = $ping['resolved_url'];

            $port = $this->portFromUrl($serviceUrl, $port);

        }



        $browserOpen = false;

        $platformConnected = false;

        $platformPage = '';

        $robotMessage = '';



        $cached = $this->readCachedSession($scopedId);

        if ($cached['browser_open'] || $cached['platform_connected']) {

            $browserOpen = $cached['browser_open'];

            $platformConnected = $cached['platform_connected'];

            $platformPage = $cached['page_url'];

            if ($platformConnected) {

                $robotMessage = 'Conectat la PieseAuto.ro';

            } elseif ($browserOpen) {

                $robotMessage = 'Browser Chrome activ';

            }

        }



        if ($liveProbe && $canProbe && $serviceOnline) {

            $robotState = $this->fetchRobotState($scopedId, $serviceUrl);

            if ($robotState !== null) {

                $browserOpen = (bool) ($robotState['browser_open'] ?? $robotState['browser_active'] ?? false);

                $platformConnected = (bool) ($robotState['platform_connected'] ?? false);

                $platformPage = (string) ($robotState['page_url'] ?? $robotState['platform_page'] ?? '');

                $robotMessage = (string) ($robotState['mesaj'] ?? 'Inactiv');

                if (isset($robotState['service_port']) && (int) $robotState['service_port'] > 0) {

                    $port = (int) $robotState['service_port'];

                }

            } elseif ($robotMessage === '') {

                $robotMessage = 'Robot online — apasă «Pornește browser» în panou.';

            }



            $this->applyLoginHints($robotMessage, $browserOpen, $platformConnected, $platformPage);



            if (!$platformConnected && $cached['platform_connected']) {

                $platformConnected = true;

                $browserOpen = $browserOpen || $cached['browser_open'];

                if ($platformPage === '' && $cached['page_url'] !== '') {

                    $platformPage = $cached['page_url'];

                }

                if (!self::messageIndicatesLogin($robotMessage)) {

                    $robotMessage = 'Conectat la PieseAuto.ro';

                }

            }

        } elseif ($serviceOnline && $robotMessage === '') {

            $robotMessage = 'Serviciu robot activ — verificare în browser.';

        }



        if ($robotMessage === '' && !$serviceOnline) {

            $robotMessage = PieseAutoRobotConfig::robotAccessHint();

        }



        $accountsCount = 0;

        $configured = false;

        try {

            $accounts = (new PieseAutoAccountsService())->listForCurrentUser();

            $accountsCount = count($accounts);

            $configured = $accountsCount > 0;

        } catch (\Throwable) {

            $configured = false;

        }



        return [

            'status' => 'ok',

            'service_online' => $serviceOnline,

            'service_port' => $port,

            'service_host' => '127.0.0.1',

            'service_label' => '127.0.0.1:' . $port,

            'service_url' => $serviceUrl,

            'browser_open' => $browserOpen,

            'platform_connected' => $platformConnected,

            'platform_page' => $platformPage,

            'robot_message' => $robotMessage,

            'access_hint' => PieseAutoRobotConfig::robotAccessHint(),

            'ping' => $ping,

            'accounts_count' => $accountsCount,

            'configured' => $configured,

            'target_user' => $targetUser,

            'ready' => $serviceOnline && $browserOpen && $platformConnected,

            'fast' => !$liveProbe,

            'robot_reachable_php' => $canProbe,

        ];

    }



    /**

     * @param array<string, mixed>|null $listener

     * @param array<string, mixed>|null $runtime

     * @return array{online:bool,http_code:int,url:string,body:string,error:string,resolved_url?:string}

     */

    private function resolvePing(?array $listener, ?array $runtime, int $port, string $serviceUrl, bool $doHttpPing): array
    {
        if (PieseAutoRobotConfig::tunnelUrl() !== '') {
            return $doHttpPing
                ? PieseAutoRobotConfig::pingDetails(true)
                : ['online' => true, 'http_code' => 200, 'url' => $serviceUrl, 'body' => '', 'error' => '', 'resolved_url' => $serviceUrl];
        }

        $live = PieseAutoRobotConfig::discoverActiveRobotUrl(true);
        if ($live !== null) {
            $result = PieseAutoRobotConfig::pingUrlFast($live . '/verificare_sesiune');
            $result['resolved_url'] = $live;

            return $result;
        }

        if (!$doHttpPing) {
            return ['online' => false, 'http_code' => 0, 'url' => $serviceUrl, 'body' => '', 'error' => 'skip'];
        }

        return PieseAutoRobotConfig::pingDetails(true);
    }



    /** @return array{online:bool,http_code:int,url:string,body:string,error:string,resolved_url:string} */

    private function onlinePingResult(int $port): array

    {

        $url = 'http://127.0.0.1:' . $port;



        return [

            'online' => true,

            'http_code' => 200,

            'url' => $url . '/verificare_sesiune',

            'body' => '{"online":true}',

            'error' => '',

            'resolved_url' => $url,

        ];

    }



    private function portFromUrl(string $serviceUrl, int $fallback): int

    {

        $parsed = parse_url($serviceUrl);

        $port = (int) ($parsed['port'] ?? 0);



        return $port > 0 ? $port : $fallback;

    }



    /** @return array<string, mixed>|null */

    private function fetchRobotState(string $scopedContId, string $baseUrl): ?array

    {

        $path = '/stare_completa?cont_id=' . rawurlencode($scopedContId);

        $ch = curl_init(rtrim($baseUrl, '/') . $path);

        if ($ch === false) {

            return null;

        }



        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CONNECTTIMEOUT => 1,

            CURLOPT_TIMEOUT => 3,

            CURLOPT_HTTPHEADER => [

                'ngrok-skip-browser-warning: 69420',

                'X-Robot-Channel: ' . PieseAutoRobotConfig::channelId(),

            ],

        ]);



        $body = curl_exec($ch);

        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);



        if (!is_string($body) || $body === '' || $code < 200 || $code >= 500) {

            return null;

        }



        $data = json_decode($body, true);



        return is_array($data) ? $data : null;

    }



    /** @return array{platform_connected:bool,browser_open:bool,page_url:string} */

    private function readCachedSession(string $scopedContId): array

    {

        $empty = ['platform_connected' => false, 'browser_open' => false, 'page_url' => ''];

        $profileDir = PieseAutoRobotConfig::profileDirForCont($scopedContId);

        $profileRunning = PieseAutoRobotConfig::chromeProfileRunning($profileDir);



        $runtime = PieseAutoRobotConfig::readRuntimeFile();

        if (!is_array($runtime)) {

            return $profileRunning

                ? ['platform_connected' => false, 'browser_open' => true, 'page_url' => '']

                : $empty;

        }



        $entry = $runtime['browsers'][$scopedContId] ?? null;

        if (!is_array($entry)) {

            if ($profileRunning) {

                return ['platform_connected' => false, 'browser_open' => true, 'page_url' => ''];

            }



            return $empty;

        }



        if ($profileDir === '' && isset($entry['profile_dir'])) {

            $profileDir = (string) $entry['profile_dir'];

            $profileRunning = PieseAutoRobotConfig::chromeProfileRunning($profileDir);

        }



        $cachedPlatform = (bool) ($entry['platform_connected'] ?? false);

        $loginAt = (float) ($entry['login_at'] ?? 0);

        $recentLogin = $loginAt > 0 && (time() - $loginAt) < 86400 * 7;

        $platformConnected = $cachedPlatform && ($profileRunning || $recentLogin);

        $pageUrl = (string) ($entry['page_url'] ?? '');



        return [

            'platform_connected' => $platformConnected,

            'browser_open' => $profileRunning,

            'page_url' => $pageUrl,

        ];

    }



    private function applyLoginHints(string $robotMessage, bool &$browserOpen, bool &$platformConnected, string &$platformPage): void

    {

        if (!$platformConnected && self::messageIndicatesLogin($robotMessage)) {

            $platformConnected = true;

        }

        if (!$browserOpen && $platformConnected) {

            $browserOpen = true;

        }

        if ($platformConnected && $platformPage === '') {

            $platformPage = 'pieseauto.ro/contul-meu';

        }

    }



    private static function messageIndicatesLogin(string $message): bool

    {

        $msg = mb_strtolower(trim($message));

        if ($msg === '' || $msg === 'inactiv') {

            return false;

        }



        foreach ([

            'logat cu succes',

            'conectat la pieseauto',

            'sesiune recuper',

            'deja logat',

            'browser deja activ',

            'sesiune existentă',

            'sesiune existenta',

            'conectat',

            '🏁',

            '✅',

        ] as $token) {

            if (str_contains($msg, $token)) {

                return true;

            }

        }



        return false;

    }

}


