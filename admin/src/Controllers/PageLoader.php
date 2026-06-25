<?php

namespace Evasystem\Controllers;

use Evasystem\Core\AdminPageResolver;
use Evasystem\Core\AdminUrl;

class PageLoader
{
    private string $url;
    private array $routes;

    public function __construct(array $routes)
    {
        $this->url = $this->getCurrentUrl();
        $this->routes = $routes;
    }

    // Obține URL-ul curent fără query string (normalizat — evită /admin/cron/index.php)
    private function getCurrentUrl(): string
    {
        return AdminUrl::currentRequestPath();
    }

    // Ultimul segment din URL
    private function getLastSegment(): string
    {
        $path = AdminUrl::normalizeRequestPath($this->url);
        if ($path === '/' || $path === '') {
            return '';
        }

        return trim(basename($path), '/');
    }

    // Apelare dinamică în funcție de load_type
    public function callDynamic(string $method): void
    {
        $allowed = ['loadPage', 'simplepag', 'webproeject', 'querypag', 'startpag'];

        if (in_array($method, $allowed) && method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->loadPageByFile('404.php');
        }
    }

    public function loadPage(): void
    {
        $file = $this->resolveRouteFile();
        $this->loadPageByFile($file);
    }

    public function simplepag(): void
    {
        $file = $this->resolveRouteFile();
       
        $this->renderLogin($file);
    }

    public function webproeject(): void
    {
        $file = $this->resolveLocalPath('webproject/index.php');
        if ($file !== null && file_exists($file)) {
            require_once $file;
        }
    }

    public function querypag(): void
    {
        $file = $this->resolveLocalPath('controllers/pages/usersveryfi.php');
        if ($file !== null && file_exists($file)) {
            require_once $file;
        }
    }

    public function startpag(): void
    {
        $file = $this->resolveLocalPath('admin/pages/startpag.php');
        if ($file !== null && file_exists($file)) {
            require_once $file;
        }
    }

    private function resolveRouteFile(): string
    {
        $lastSegment = $this->getLastSegment();
        $resolvedKey = AdminUrl::resolvePageKey($lastSegment);

        if (isset($this->routes[$lastSegment])) {
            return (string) $this->routes[$lastSegment];
        }
        if (isset($this->routes[$resolvedKey])) {
            return (string) $this->routes[$resolvedKey];
        }
        if (count($this->routes) === 1) {
            return (string) reset($this->routes);
        }

        $resolved = AdminPageResolver::resolveTemplate($lastSegment);
        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = AdminPageResolver::resolveTemplate($resolvedKey);
        if ($resolved !== null) {
            return $resolved;
        }

        return '404.php';
    }

    // Render standard
    private function loadPageByFile(string $file): void
    {
        $templates = new Templates($file);
        $templates->render();
    }

    // Render pentru pagini tip login
    private function renderLogin(string $file): void
    {
        $templates = new Templates($file);
        $templates->rederLogin();
    }

    private function resolveLocalPath(?string $path): ?string
    {
        if (!is_string($path) || trim($path) === '') {
            return null;
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        $root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, "\\/") . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}
