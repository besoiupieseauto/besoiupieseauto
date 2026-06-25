<?php
declare(strict_types=1);

namespace Evasystem\Core;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html as PhpWordHtml;

class DocxStudio
{
    private string $docsDir;
    private string $uploadsDir;
    private string $savedDir;
    private string $tmpDir;
    private ?string $versionsDir;
    private string $publicSavedPrefix;
    /** @var string[] */
    private array $allowedRoots = [];

    /** @param array{docsDir:string,uploadsDir:string,savedDir:string,tmpDir:string,versionsDir?:?string,publicSavedPrefix?:string,allowedRoots?:string[]} $cfg */
    public function __construct(array $cfg)
    {
        $this->docsDir           = rtrim($cfg['docsDir'], '/');
        $this->uploadsDir        = rtrim($cfg['uploadsDir'], '/');
        $this->savedDir          = rtrim($cfg['savedDir'], '/');
        $this->tmpDir            = rtrim($cfg['tmpDir'], '/');
        $this->versionsDir       = $cfg['versionsDir'] ?? null;
        $this->publicSavedPrefix = $cfg['publicSavedPrefix'] ?? 'saved_docseidt';
        $this->allowedRoots      = $cfg['allowedRoots'] ?? [];

        $this->ensureDirs([$this->docsDir, $this->uploadsDir, $this->savedDir, $this->tmpDir, $this->versionsDir]);

        // Temp + ZIP driver (preferă ZipArchive)
        Settings::setTempDir($this->tmpDir);
        if (\extension_loaded('zip')) {
            Settings::setZipClass(Settings::ZIPARCHIVE);
        } else {
            Settings::setZipClass(Settings::PCLZIP);
        }
    }

    private function ensureDirs(array $dirs): void
    {
        foreach ($dirs as $d) {
            if (!$d) continue;
            if (!is_dir($d)) @mkdir($d, 0775, true);
            if (!is_dir($d) || !is_writable($d)) {
                throw new \RuntimeException("Folder inaccesibil sau read-only: $d");
            }
        }
    }

    public function diag(): array
    {
        $dirs = [
            'docsDir'     => $this->docsDir,
            'uploadsDir'  => $this->uploadsDir,
            'savedDir'    => $this->savedDir,
            'tmpDir'      => $this->tmpDir,
            'versionsDir' => $this->versionsDir,
        ];
        $out = [];
        foreach ($dirs as $k => $d) {
            $out[$k] = [
                'path'     => $d,
                'exists'   => $d ? is_dir($d) : null,
                'writable' => $d ? (is_dir($d) && is_writable($d)) : null,
            ];
        }
        return [
            'dirs'       => $out,
            'ext'        => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'gd'  => extension_loaded('gd'),
            ],
            'tempDirUsed'=> Settings::getTempDir(),
            'zipClass'   => Settings::getZipClass(),
        ];
    }

    public function listDocs(): array
    {
        $out = [];
        foreach (glob($this->docsDir.'/*.docx') ?: [] as $f) {
            $out[] = ['file'=>basename($f), 'size'=>filesize($f), 'mtime'=>filemtime($f)];
        }
        usort($out, fn($a,$b)=>$b['mtime']<=>$a['mtime']);
        return $out;
    }

    public function uploadAndOpen(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Eroare upload: code '.$file['error']);
        }
        $name = preg_replace('~[^a-z0-9._-]+~i', '_', (string)($file['name'] ?? 'upload.docx'));
        $dest = $this->uploadsDir . '/' . uniqid('up_', true) . '_' . $name;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@copy($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Eroare upload: nu pot muta fișierul.');
            }
        }
        $html = $this->docxToHtml($dest);
        return ['ok'=>true,'source'=>'upload','file'=>$dest,'html'=>$html];
    }

    public function openFromList(string $filename): array
    {
        $safe = basename($filename);
        $full = $this->docsDir . '/' . $safe;
        if (!is_file($full)) throw new \RuntimeException('Fișier inexistent: '.$safe);
        $html = $this->docxToHtml($full);
        return ['ok'=>true,'source'=>'list','file'=>$safe,'html'=>$html];
    }

    /** Generează DOCX din HTML — tolerant (dacă HTML-ul e problematic, cade pe text simplu). */
    public function saveFromHtml(string $html, ?string $saveAs=null): array
    {
        $html = $this->sanitizeHtml($html);
        if ($html === '') throw new \InvalidArgumentException('HTML gol după sanitizare.');

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('DejaVu Sans');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        try {
            // addHtml acceptă subset de HTML; imaginile base64 pot fi limitate
            PhpWordHtml::addHtml($section, $html, false, false);
        } catch (\Throwable $e) {
            // fallback: text simplu
            $section->addText($this->htmlToPlain($html));
        }

        $fname = $saveAs ?: ('doc_'.date('Ymd_His').'_'.substr(sha1((string)mt_rand()),0,6).'.docx');
        $fname = preg_replace('~[^a-z0-9._-]+~i', '_', $fname);
        if (!preg_match('~\\.docx$~i', $fname)) $fname .= '.docx';

        $full = $this->savedDir.'/'.$fname;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($full);
        @chmod($full, 0664);

        // Validare ZIP minimă (document Word sănătos)
        if (!$this->validateDocx($full)) {
            @unlink($full);
            throw new \RuntimeException('DOCX corupt (validare ZIP a eșuat).');
        }

        $public = rtrim($this->publicSavedPrefix,'/').'/'.basename($full);
        return ['ok'=>true,'savedPath'=>$full,'publicUrl'=>$public,'size'=>filesize($full)];
    }

    public function docxToHtml(string $fullPath): string
    {
        $this->assertAllowed($fullPath);
        $phpWord = IOFactory::load($fullPath, 'Word2007');

        $tempHtml = $this->tmpDir.'/html_'.uniqid().'.html';
        $writer = IOFactory::createWriter($phpWord, 'HTML');
        $writer->save($tempHtml);

        $html = @file_get_contents($tempHtml) ?: '';
        @unlink($tempHtml);

        if (preg_match('~<body[^>]*>(.*)</body>~is', $html, $m)) {
            return trim($m[1]);
        }
        return trim($html);
    }

    /* ================== helpers ================== */

    private function assertAllowed(string $path): void
    {
        $real = realpath($path) ?: $path;
        if (empty($this->allowedRoots)) return;
        foreach ($this->allowedRoots as $root) {
            $rootReal = realpath($root) ?: $root;
            if (strpos($real, rtrim($rootReal, DIRECTORY_SEPARATOR)) === 0) return;
        }
        throw new \RuntimeException('Acces interzis: path în afara root-urilor permise.');
    }

    private function sanitizeHtml(string $html): string
    {
        $html = trim((string)$html);
        if ($html === '') return '';

        // elimină taguri periculoase care pot strica parserul
        $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
        $html = preg_replace('~<style\b[^>]*>.*?</style>~is',  '', $html);

        // dacă pare text simplu, îl împachetăm
        if (!preg_match('~</?\w~', $html)) {
            $html = '<p>'.htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }
        // normalizează entități invalide
        return mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    }

    private function htmlToPlain(string $html): string
    {
        $plain = preg_replace('~<br\s*/?>~i', "\n", $html);
        $plain = strip_tags((string)$plain);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim($plain);
    }

    private function validateDocx(string $file): bool
    {
        if (!is_file($file) || filesize($file) < 100) return false;
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($file) !== true) return false;
            $ok = ($zip->locateName('[Content_Types].xml') !== false) &&
                ($zip->locateName('word/document.xml') !== false);
            $zip->close();
            return $ok;
        }
        // fără ZipArchive, acceptăm tot (PclZip nu are API de test simplu)
        return true;
    }
}
