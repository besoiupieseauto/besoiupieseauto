<?php
namespace Evasystem\Controllers;

use Evasystem\Services\AIAnalyzer;

class FormController {
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'denumire' => $_POST['denumire'] ?? '',
                'idno'     => $_POST['idno'] ?? '',
                'website'  => $_POST['website'] ?? '',
                'domeniu'  => $_POST['domeniu'] ?? '',
            ];

            $ai = new AIAnalyzer();
            $report = $ai->generateReport($data);
            echo $report;

        } else {
            $form = __DIR__ . '/../../Templates/form.html';
            if (is_string($form) && file_exists($form)) {
                include $form;
            } else {
                http_response_code(500);
                echo 'Form template missing.';
            }
        }
    }
}

