<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Filtrage dynamique sur les requêtes Eloquent.
 *
 * Supporte:
 * - Tableaux          : filtre IN         → ['status' => ['active', 'inactive']]
 * - Wildcards         : filtre LIKE       → ['name' => 'john*']
 * - Opérateurs        : >, >=, <, <=, !=  → ['price' => '>=100']
 * - Égalité simple    :                   → ['status' => 'active']
 * - Recherche         : LIKE multi-col    → ['search' => 'terme']
 * - Plage de dates    :                   → ['date_debut' => '...', 'date_fin' => '...']
 * - Tri               :                   → ['sort_by' => 'created_at', 'sort_direction' => 'desc']
 */
trait FilterableTrait
{
    protected function applyFilters(Builder $query, array $filters = [], ?string $tableName = null): Builder
    {
        if (empty($filters)) {
            return $query;
        }

        $tableName ??= $query->getModel()->getTable();
        $columns     = Schema::getColumnListing($tableName);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || !in_array($key, $columns)) {
                continue;
            }

            $this->applyFilterByType($query, $key, $value);
        }

        return $query;
    }

    protected function applyFilterByType(Builder $query, string $column, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($column, $value);
            return;
        }

        if (is_string($value) && (str_contains($value, '*') || str_contains($value, '%'))) {
            $query->where($column, 'LIKE', str_replace('*', '%', $value));
            return;
        }

        if (is_bool($value)) {
            $query->where($column, $value);
            return;
        }

        if (is_string($value) && preg_match('/^([><=!]+)(.+)$/', $value, $matches)) {
            $query->where($column, $this->normalizeOperator($matches[1]), trim($matches[2]));
            return;
        }

        $query->where($column, $value);
    }

    protected function normalizeOperator(string $operator): string
    {
        return match ($operator) {
            '==', '=' => '=',
            '!=', '<>' => '!=',
            '>=' => '>=',
            '<=' => '<=',
            '>'  => '>',
            '<'  => '<',
            default  => '=',
        };
    }

    protected function applySearch(Builder $query, string $searchTerm, array $columns): Builder
    {
        if (empty($searchTerm) || empty($columns)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', "%{$searchTerm}%");
            }
        });
    }

    protected function applyDateRange(Builder $query, string $column, ?string $dateDebut, ?string $dateFin): Builder
    {
        if ($dateDebut) {
            $query->where($column, '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->where($column, '<=', $dateFin);
        }

        return $query;
    }

    protected function applySorting(Builder $query, ?string $sortBy, string $sortDirection = 'asc', ?string $tableName = null): Builder
    {
        if (!$sortBy) {
            return $query;
        }

        $tableName ??= $query->getModel()->getTable();
        $columns     = Schema::getColumnListing($tableName);

        if (!in_array($sortBy, $columns)) {
            return $query;
        }

        return $query->orderBy($sortBy, strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
    }
}
