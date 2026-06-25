<?php

declare(strict_types=1);

namespace Evasystem\Controllers;

use Evasystem\Core\AdminUrl;

class Redirector
{
    private string $url;

    /**
     * Constructor initializes the base URL.
     */
    public function __construct()
    {
        $this->url = $this->ProtocolUrl();
    }

    /**
     * Get the current base URL.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set a new base URL.
     *
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * Determine and set the full URL with protocol.
     *
     * @throws \Exception
     * @return string
     */
    private function ProtocolUrl(): string
    {
        return AdminUrl::siteBaseUrl();
    }

    /**
     * Get the last segment of the current URL path.
     *
     * @return string
     */
    public function thispagesurl(): string
    {
        $path = AdminUrl::normalizeRequestPath((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($path === '/' || $path === '') {
            return '';
        }

        $slug = trim(basename($path), '/');
        if ($slug === 'index.php') {
            $parent = dirname($path);
            $slug = $parent !== '/' && $parent !== '\\'
                ? trim(basename($parent), '/')
                : '';
        }

        return $slug !== '' ? AdminUrl::resolvePageKey($slug) : '';
    }

    /**
     * Redirect to the specified URL.
     *
     * @param string $url
     */
    public function redirect(string $url): void
    {
        if (str_starts_with($url, '/')) {
            header('Location: ' . $url, true, 302);
            exit;
        }

        $baseLink = rtrim($this->getUrl(), '/');
        $finalLink = $baseLink . '/' . ltrim($url, '/');

        header('Location: ' . $finalLink, true, 302);
        exit;
    }

    /**
     * Get the full URL with an optional subfolder.
     *
     * @param string $folder
     * @return string
     */
    public function geturlthis(string $folder = ''): string
    {
        return $this->getUrl() . '/' . ltrim($folder, '/');
    }
}
