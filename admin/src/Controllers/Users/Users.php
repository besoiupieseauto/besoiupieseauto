<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Users;

use Besoiu\Modules\Users\Controller\UsersController as ModuleUsersController;
use Besoiu\Modules\Users\Service\UsersService as ModuleUsersService;

/**
 * Bridge compatibilitate — delegă către modulul modules/users/.
 */
class Users extends ModuleUsersController
{
    public function __construct(?ModuleUsersService $usersService = null)
    {
        parent::__construct($usersService ?? new ModuleUsersService());
    }

    /** @param array<string, mixed> $postData */
    public function setArrayAdd(array $postData = [], array $additionalData = []): void
    {
        $this->setPayload($postData, $additionalData);
    }

    /** @return array<string, mixed> */
    public function getArrayAdd(): array
    {
        return $this->getPayload();
    }
}
