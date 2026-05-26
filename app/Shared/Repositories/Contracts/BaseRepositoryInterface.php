<?php

namespace App\Shared\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function findById(int $id, ?int $tenantId = null, array $relations = []): ?Model;

    public function findByIdOrFail(int $id, ?int $tenantId = null, array $relations = []): Model;

    public function findAll(?int $tenantId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getAll(?int $tenantId = null, array $filters = []): Collection;

    public function create(array $data): Model;

    public function update(int $id, array $data, ?int $tenantId = null): Model;

    public function delete(int $id, ?int $tenantId = null): bool;

    public function count(?int $tenantId = null, array $filters = []): int;

    public function exists(int $id, ?int $tenantId = null): bool;
}
