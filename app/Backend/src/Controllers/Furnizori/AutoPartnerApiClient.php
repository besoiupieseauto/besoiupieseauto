<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/** @deprecated Folosiți Besoiu\Core\Supplier\AutoPartnerApiClient */
if (!class_exists(AutoPartnerApiClient::class, false)) {
    class_alias(\Besoiu\Core\Supplier\AutoPartnerApiClient::class, AutoPartnerApiClient::class);
}
