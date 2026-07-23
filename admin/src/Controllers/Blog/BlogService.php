<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Blog;

/**
 * @deprecated Mutat în Besoiu\Services\BlogService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Blog\BlogService', false)) {
    class_alias(\Besoiu\Services\BlogService::class, 'Besoiu\Controllers\Blog\BlogService');
}
