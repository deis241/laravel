<?php

namespace App\Modules\User\Repositories\Contracts;

use App\Modules\Auth\Models\User;
use App\Shared\Repositories\Contracts\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function assignRole(int $userId, int $roleId): void;

    public function removeRole(int $userId, int $roleId): void;
}
