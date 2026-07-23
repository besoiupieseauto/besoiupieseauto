<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Parsare User-Agent fără dependențe externe (device, browser, OS).
 */
final class VisitorUaParser
{
    /** @return array{device_type:string,browser:?string,os:?string} */
    public static function parse(string $ua): array
    {
        $ua = trim($ua);
        if ($ua === '') {
            return ['device_type' => 'unknown', 'browser' => null, 'os' => null];
        }

        $device = 'desktop';
        if (preg_match('/tablet|ipad|playbook|silk|(android(?!.*mobile))/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry/i', $ua)) {
            $device = 'mobile';
        }

        $browser = null;
        foreach ([
            '/Edg\/([\d.]+)/i' => 'Edge',
            '/OPR\/([\d.]+)/i' => 'Opera',
            '/Chrome\/([\d.]+)/i' => 'Chrome',
            '/Firefox\/([\d.]+)/i' => 'Firefox',
            '/Version\/([\d.]+).*Safari/i' => 'Safari',
            '/Safari\/([\d.]+)/i' => 'Safari',
        ] as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                $browser = $name;
                break;
            }
        }

        $os = null;
        foreach ([
            '/Windows NT 10/i' => 'Windows 10+',
            '/Windows NT 6\.3/i' => 'Windows 8.1',
            '/Windows NT 6\.1/i' => 'Windows 7',
            '/Windows/i' => 'Windows',
            '/Mac OS X ([\d_]+)/i' => 'macOS',
            '/Android ([\d.]+)/i' => 'Android',
            '/iPhone OS/i' => 'iOS',
            '/iPad/i' => 'iPadOS',
            '/CrOS/i' => 'Chrome OS',
            '/Linux/i' => 'Linux',
        ] as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                $os = $name;
                break;
            }
        }

        return [
            'device_type' => $device,
            'browser' => $browser ?? 'Other',
            'os' => $os ?? 'Other',
        ];
    }
}
