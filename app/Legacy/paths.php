<?php

declare(strict_types=1);

function besoiu_root(): string
{
    return defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__);
}

function besoiu_backend_root(): string
{
    return defined('BESOIU_BACKEND_ROOT') ? (string) BESOIU_BACKEND_ROOT : besoiu_root() . '/backend';
}

function besoiu_config_path(string $file = ''): string
{
    $base = besoiu_root() . '/config';

    return $file === '' ? $base : $base . '/' . ltrim($file, '/');
}
