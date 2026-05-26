<?php

namespace App\Modules\User\Policies;

use App\Shared\Policies\BasePolicy;

class UserPolicy extends BasePolicy
{
    protected function getPermissionPrefix(): string
    {
        return 'users';
    }
}
