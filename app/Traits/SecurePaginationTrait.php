<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait SecurePaginationTrait
{
    protected int $maxPerPage     = 100;
    protected int $defaultPerPage = 15;

    protected function getPerPage(Request $request, ?int $default = null, ?int $max = null): int
    {
        $perPage    = (int) $request->query('per_page', $default ?? $this->defaultPerPage);
        $maxAllowed = $max ?? $this->maxPerPage;

        return max(1, min($perPage, $maxAllowed));
    }

    protected function getPaginationParams(Request $request): array
    {
        return [
            'per_page' => $this->getPerPage($request),
            'page'     => max(1, (int) $request->query('page', 1)),
        ];
    }
}
