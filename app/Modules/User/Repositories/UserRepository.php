<?php

namespace App\Modules\User\Repositories;

use App\Modules\Auth\Models\User;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected array $defaultRelations  = ['roles'];
    protected array $searchableColumns = ['name', 'email'];

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $user = $this->findByIdOrFail($userId);
        $user->roles()->syncWithoutDetaching([$roleId]);
    }

    public function removeRole(int $userId, int $roleId): void
    {
        $user = $this->findByIdOrFail($userId);
        $user->roles()->detach($roleId);
    }

    protected function buildQuery(?int $tenantId = null, array $filters = []): Builder
    {
        $roleCode = $filters['role'] ?? null;
        unset($filters['role']);

        $query = parent::buildQuery($tenantId, $filters);

        if ($roleCode) {
            $query->whereHas('roles', fn ($q) => $q->where('code', $roleCode));
        }

        return $query;
    }
}
