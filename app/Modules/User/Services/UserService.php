<?php

namespace App\Modules\User\Services;

use App\Modules\Auth\Models\User;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Shared\Services\BaseService;
use App\Traits\PasswordGeneratorServiceTrait;
use Illuminate\Database\Eloquent\Model;

class UserService extends BaseService
{
    public function __construct(
        protected UserRepositoryInterface $repository
    ) {}

    public function create(array $data, ?int $tenantId = null): Model
    {
        $data['password'] = $data['password'] ?? PasswordGeneratorServiceTrait::generate(12);
        $data['status']   = $data['status']   ?? 'active';

        return parent::create($data, $tenantId);
    }

    public function getUserWithRelations(int $id): User
    {
        /** @var User $user */
        $user = $this->getById($id, null, ['roles.permissions']);
        return $user;
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $this->repository->assignRole($userId, $roleId);
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $this->repository->removeRole($userId, $roleId);
    }
}
