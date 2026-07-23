<?php

declare(strict_types=1);

/**
 * Mirror pentru app/Backend/config/optional_modules.php (sursă canonică,
 * folosită efectiv de ModuleGate::optionalMap()). Acest fișier nu mai
 * duplică array-ul — doar îl reexportă, ca să nu diverjeze cele două copii.
 *
 * @return array<string, array{
 *   slugs: list<string>,
 *   paths: list<string>,
 *   crud_key: ?string,
 *   api_scripts?: list<string>,
 *   quick_actions?: list<string>,
 *   folder?: string
 * }>
 */
return require __DIR__ . '/../../app/Backend/config/optional_modules.php';
