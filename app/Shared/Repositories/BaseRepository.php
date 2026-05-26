<?php

namespace App\Shared\Repositories;

use App\Shared\Repositories\Contracts\BaseRepositoryInterface;
use App\Traits\FilterableTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

/**
 * Classe de base pour tous les repositories.
 *
 * Usage:
 * ```php
 * class ArticleRepository extends BaseRepository implements ArticleRepositoryInterface
 * {
 *     protected array $defaultRelations  = ['category'];
 *     protected array $searchableColumns = ['title', 'content'];
 *
 *     public function __construct(Article $model)
 *     {
 *         $this->model = $model;
 *     }
 * }
 * ```
 *
 * Multi-tenancy: si le modèle possède une colonne `tenant_id`, les requêtes
 * sont automatiquement scopées au tenant fourni.
 * Pour utiliser une autre colonne (ex: auto_ecole_id), redéfinir $tenantColumn.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    use FilterableTrait;

    protected Model $model;

    /** Relations eager-loadées par défaut — à définir dans la classe enfant */
    protected array $defaultRelations = [];

    /** Colonnes pour la recherche full-text */
    protected array $searchableColumns = [];

    /** Colonne de date pour les filtres date_debut / date_fin */
    protected ?string $dateFilterColumn = 'created_at';

    /** Colonne d'isolation multi-tenant */
    protected string $tenantColumn = 'tenant_id';

    public function findById(int $id, ?int $tenantId = null, array $relations = []): ?Model
    {
        $query = $this->model->newQuery();

        if ($tenantId !== null && $this->hasTenantScope()) {
            $query->where($this->tenantColumn, $tenantId);
        }

        $relationsToLoad = !empty($relations) ? $relations : $this->defaultRelations;
        if (!empty($relationsToLoad)) {
            $query->with($relationsToLoad);
        }

        return $query->find($id);
    }

    public function findByIdOrFail(int $id, ?int $tenantId = null, array $relations = []): Model
    {
        $model = $this->findById($id, $tenantId, $relations);

        if (!$model) {
            throw new ModelNotFoundException("Ressource #{$id} introuvable.");
        }

        return $model;
    }

    public function findAll(?int $tenantId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildQuery($tenantId, $filters)->paginate($perPage);
    }

    public function getAll(?int $tenantId = null, array $filters = []): Collection
    {
        return $this->buildQuery($tenantId, $filters)->get();
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data, ?int $tenantId = null): Model
    {
        $model = $this->findByIdOrFail($id, $tenantId);
        $model->update($data);

        return $model->refresh();
    }

    public function delete(int $id, ?int $tenantId = null): bool
    {
        return $this->findByIdOrFail($id, $tenantId)->delete();
    }

    public function count(?int $tenantId = null, array $filters = []): int
    {
        $query = $this->model->newQuery();

        if ($tenantId !== null && $this->hasTenantScope()) {
            $query->where($this->tenantColumn, $tenantId);
        }

        return $this->applyFilters($query, $filters)->count();
    }

    public function exists(int $id, ?int $tenantId = null): bool
    {
        $query = $this->model->newQuery()->where($this->model->getKeyName(), $id);

        if ($tenantId !== null && $this->hasTenantScope()) {
            $query->where($this->tenantColumn, $tenantId);
        }

        return $query->exists();
    }

    /**
     * Construit la requête avec filtres, tri, recherche et plage de dates.
     */
    protected function buildQuery(?int $tenantId = null, array $filters = []): Builder
    {
        $query = $this->model->newQuery();

        if ($tenantId !== null && $this->hasTenantScope()) {
            $query->where($this->tenantColumn, $tenantId);
        }

        if (!empty($this->defaultRelations)) {
            $query->with($this->defaultRelations);
        }

        $query = $this->applyFilters($query, $filters);

        $sortBy        = $filters['sort_by'] ?? $this->dateFilterColumn ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query         = $this->applySorting($query, $sortBy, $sortDirection);

        if (!empty($filters['search']) && !empty($this->searchableColumns)) {
            $query = $this->applySearch($query, $filters['search'], $this->searchableColumns);
        }

        if ($this->dateFilterColumn && (!empty($filters['date_debut']) || !empty($filters['date_fin']))) {
            $query = $this->applyDateRange(
                $query,
                $this->dateFilterColumn,
                $filters['date_debut'] ?? null,
                $filters['date_fin']   ?? null
            );
        }

        return $query;
    }

    /**
     * Vérifie si le modèle possède la colonne d'isolation tenant.
     */
    protected function hasTenantScope(): bool
    {
        return Schema::hasColumn($this->model->getTable(), $this->tenantColumn);
    }

    protected function query(): Builder
    {
        return $this->model->newQuery();
    }
}
