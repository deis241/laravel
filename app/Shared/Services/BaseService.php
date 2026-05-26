<?php

namespace App\Shared\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Classe de base pour tous les services.
 *
 * Usage:
 * ```php
 * class ArticleService extends BaseService
 * {
 *     public function __construct(
 *         protected ArticleRepositoryInterface $repository
 *     ) {}
 * }
 * ```
 *
 * @property \App\Shared\Repositories\Contracts\BaseRepositoryInterface $repository
 */
abstract class BaseService
{
    /**
     * Récupère toutes les entités avec pagination.
     */
    public function getAll(?int $tenantId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->findAll($tenantId, $filters, $perPage);
    }

    /**
     * Récupère toutes les entités sans pagination.
     */
    public function getAllWithoutPagination(?int $tenantId = null, array $filters = []): Collection
    {
        return $this->repository->getAll($tenantId, $filters);
    }

    /**
     * Récupère une entité par son ID.
     *
     * @throws ModelNotFoundException
     */
    public function getById(int $id, ?int $tenantId = null, array $relations = []): Model
    {
        $model = $this->repository->findById($id, $tenantId, $relations);

        if (!$model) {
            throw new ModelNotFoundException("Ressource #{$id} introuvable ou accès refusé.");
        }

        return $model;
    }

    /**
     * Crée une nouvelle entité.
     * Injecte automatiquement le tenant_id si fourni.
     */
    public function create(array $data, ?int $tenantId = null): Model
    {
        if ($tenantId !== null && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $tenantId;
        }

        return $this->repository->create($data);
    }

    /**
     * Met à jour une entité.
     * Empêche la modification du tenant_id.
     */
    public function update(int $id, array $data, ?int $tenantId = null): Model
    {
        unset($data['tenant_id']);

        return $this->repository->update($id, $data, $tenantId);
    }

    /**
     * Supprime une entité.
     */
    public function delete(int $id, ?int $tenantId = null): bool
    {
        return $this->repository->delete($id, $tenantId);
    }

    /**
     * Compte le nombre d'entités.
     */
    public function count(?int $tenantId = null, array $filters = []): int
    {
        return $this->repository->count($tenantId, $filters);
    }

    /**
     * Vérifie si une entité existe.
     */
    public function exists(int $id, ?int $tenantId = null): bool
    {
        return $this->repository->exists($id, $tenantId);
    }

    /**
     * Vérifie l'autorisation via Gate. Lance AuthorizationException si refusée.
     *
     * @throws AuthorizationException
     */
    protected function authorize(string $ability, mixed ...$arguments): void
    {
        Gate::authorize($ability, $arguments);
    }

    /**
     * Exécute un callback dans une transaction DB.
     */
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Crée plusieurs entités en batch (dans une transaction).
     */
    protected function createMany(array $items, ?int $tenantId = null): Collection
    {
        return $this->transaction(function () use ($items, $tenantId) {
            return collect($items)->map(fn ($data) => $this->create($data, $tenantId));
        });
    }

    /**
     * Met à jour plusieurs entités en batch (dans une transaction).
     * Format $updates: [['id' => 1, 'data' => [...]], ...]
     */
    protected function updateMany(array $updates, ?int $tenantId = null): Collection
    {
        return $this->transaction(function () use ($updates, $tenantId) {
            return collect($updates)->map(fn ($item) => $this->update($item['id'], $item['data'], $tenantId));
        });
    }
}
