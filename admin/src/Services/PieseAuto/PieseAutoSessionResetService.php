<?php

declare(strict_types=1);

namespace Evasystem\Services\PieseAuto;

/**
 * Reset sesiune Chrome robot: oprește browserul și șterge profilul local.
 * Funcționează și când robotul Python rulează cod vechi (fără /reset_sesiune).
 */
final class PieseAutoSessionResetService
{
    /** @return array{success:bool,mesaj:string,cont_id:string,profile_deleted:bool,runtime_cleaned:bool,robot_stopped:bool} */
    public function reset(string $targetUser): array
    {
        $contId = PieseAutoRobotConfig::scopedContId($targetUser);
        if ($contId === '') {
            return [
                'success' => false,
                'mesaj' => 'Utilizator target invalid.',
                'cont_id' => '',
                'profile_deleted' => false,
                'runtime_cleaned' => false,
                'robot_stopped' => false,
            ];
        }

        $robotStopped = false;
        $stop = $this->callRobot('/stop_total', 'POST', ['cont_id' => $contId]);
        if ($stop['ok'] || ($stop['http_code'] >= 200 && $stop['http_code'] < 300)) {
            $robotStopped = true;
        }
        usleep(800000);

        // Robot nou — endpoint dedicat (ignorăm 404)
        $reset = $this->callRobot('/reset_sesiune', 'POST', ['cont_id' => $contId]);
        if ($reset['ok']) {
            return [
                'success' => true,
                'mesaj' => (string) ($reset['body']['mesaj'] ?? 'Sesiune ștearsă — profil Chrome gol. Poți porni login nou.'),
                'cont_id' => $contId,
                'profile_deleted' => true,
                'runtime_cleaned' => true,
                'robot_stopped' => true,
            ];
        }

        $profileDeleted = $this->deleteChromeProfile($contId);
        $runtimeCleaned = $this->cleanRuntimeEntry($contId);

        if (!$profileDeleted && is_dir($this->profileDir($contId))) {
            return [
                'success' => false,
                'mesaj' => 'Nu am putut șterge profilul Chrome. Închide manual fereastra robot și reîncearcă.',
                'cont_id' => $contId,
                'profile_deleted' => false,
                'runtime_cleaned' => $runtimeCleaned,
                'robot_stopped' => $robotStopped,
            ];
        }

        return [
            'success' => true,
            'mesaj' => 'Sesiune ștearsă — profil Chrome gol. Poți porni login nou.',
            'cont_id' => $contId,
            'profile_deleted' => $profileDeleted,
            'runtime_cleaned' => $runtimeCleaned,
            'robot_stopped' => $robotStopped,
        ];
    }

    /** @param array<string, mixed> $body
     * @return array{ok:bool,http_code:int,body:array<string,mixed>}
     */
    private function callRobot(string $path, string $method, array $body): array
    {
        $path = PieseAutoRobotConfig::rewriteRobotPath($path);
        $url = PieseAutoRobotConfig::effectiveUrl() . $path;
        $json = json_encode(
            PieseAutoRobotConfig::rewriteRobotJsonBody($body),
            JSON_UNESCAPED_UNICODE
        );

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'http_code' => 0, 'body' => []];
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'ngrok-skip-browser-warning: 69420',
                'X-Robot-Channel: ' . PieseAutoRobotConfig::channelId(),
            ],
        ]);

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = [];
        if (is_string($response) && $response !== '') {
            $parsed = json_decode($response, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        }

        $ok = $code >= 200 && $code < 300
            && (
                ($decoded['status'] ?? '') === 'succes'
                || ($decoded['status'] ?? '') === 'lansat'
                || ($decoded['reset'] ?? false) === true
            );

        return ['ok' => $ok, 'http_code' => $code, 'body' => $decoded];
    }

    private function profileDir(string $contId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $contId) ?? '';

        return PieseAutoRobotConfig::robotDir()
            . DIRECTORY_SEPARATOR
            . 'profil_pa_'
            . $safe;
    }

    private function deleteChromeProfile(string $contId): bool
    {
        $dir = $this->profileDir($contId);
        if (!is_dir($dir)) {
            return true;
        }

        $robotRoot = realpath(PieseAutoRobotConfig::robotDir());
        $target = realpath($dir);
        if ($robotRoot === false || $target === false || !str_starts_with($target, $robotRoot)) {
            return false;
        }
        if (!str_contains($target, 'profil_pa_')) {
            return false;
        }

        return $this->rrmdir($target);
    }

    private function cleanRuntimeEntry(string $contId): bool
    {
        $path = PieseAutoRobotConfig::robotDir()
            . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'runtime_'
            . PieseAutoRobotConfig::channelId()
            . '.json';

        if (!is_file($path)) {
            return true;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return true;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return false;
        }

        $browsers = $data['browsers'] ?? null;
        if (!is_array($browsers) || !isset($browsers[$contId])) {
            return true;
        }

        unset($browsers[$contId]);
        $data['browsers'] = $browsers;
        $data['updated_at'] = time();

        return @file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    private function rrmdir(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!$this->rrmdir($path)) {
                    return false;
                }
            } elseif (!@unlink($path)) {
                return false;
            }
        }

        return @rmdir($dir);
    }
}
