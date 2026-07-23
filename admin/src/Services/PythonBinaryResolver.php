<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Rezolvă binarul Python 3 — fără path-uri hardcodate pe un singur drive (#37).
 */
final class PythonBinaryResolver
{
    public static function resolve(): string
    {
        foreach (self::envCandidates() as $bin) {
            if (self::isUsable($bin)) {
                return $bin;
            }
        }

        foreach (self::windowsFullPaths() as $path) {
            if (self::isUsable($path)) {
                return $path;
            }
        }

        foreach (self::whichCandidates() as $bin) {
            if (self::isUsable($bin)) {
                return $bin;
            }
        }

        return '';
    }

    /** @return list<string> */
    private static function envCandidates(): array
    {
        $keys = ['CURSOR_PYTHON', 'ROBOT_PYTHON', 'PYTHON_BIN', 'PYTHON'];
        $out = [];
        foreach ($keys as $key) {
            $val = trim((string) (getenv($key) ?: ($_ENV[$key] ?? '')));
            if ($val !== '') {
                $out[] = self::normalizePath($val);
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function whichCandidates(): array
    {
        return PHP_OS_FAMILY === 'Windows'
            ? ['py -3', 'python', 'python3']
            : ['python3', 'python', '/usr/bin/python3', '/usr/local/bin/python3'];
    }

    /** @return list<string> */
    private static function windowsFullPaths(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $paths = [];
        foreach (self::laragonRoots() as $root) {
            $glob = glob($root . '/bin/python/python*/python.exe') ?: [];
            rsort($glob);
            foreach ($glob as $path) {
                $paths[] = self::normalizePath($path);
            }
            $paths[] = $root . '/bin/python/python/python.exe';
        }

        $localAppData = getenv('LOCALAPPDATA');
        if (is_string($localAppData) && $localAppData !== '') {
            $glob = glob($localAppData . '/Programs/Python/Python*/python.exe') ?: [];
            rsort($glob);
            foreach ($glob as $path) {
                $paths[] = self::normalizePath($path);
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /** @return list<string> */
    private static function laragonRoots(): array
    {
        $roots = [];
        foreach (['LARAGON_ROOT', 'LARAGON_DIR'] as $key) {
            $val = getenv($key);
            if (is_string($val) && $val !== '') {
                $roots[] = rtrim(str_replace('\\', '/', $val), '/');
            }
        }

        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot !== '' && preg_match('#^([a-zA-Z]:[/\\\\]laragon)(?:[/\\\\]|$)#i', $docRoot, $m)) {
            $roots[] = str_replace('\\', '/', $m[1]);
        }

        $projectGuess = dirname(__DIR__, 3);
        if ($projectGuess !== '' && preg_match('#^([a-zA-Z]:[/\\\\]laragon)(?:[/\\\\]|$)#i', $projectGuess, $m)) {
            $roots[] = str_replace('\\', '/', $m[1]);
        }

        foreach (['C:', 'D:', 'E:', 'F:'] as $drive) {
            $roots[] = $drive . '/laragon';
        }

        return array_values(array_unique($roots));
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path));
    }

    private static function isUsable(string $bin): bool
    {
        $bin = trim($bin);
        if ($bin === '') {
            return false;
        }

        if (str_contains($bin, '/') || str_contains($bin, '\\') || str_ends_with(strtolower($bin), '.exe')) {
            $native = str_replace('/', DIRECTORY_SEPARATOR, $bin);

            return is_file($native);
        }

        $out = [];
        $code = 1;
        @exec($bin . ' --version 2>&1', $out, $code);

        return $code === 0;
    }
}
