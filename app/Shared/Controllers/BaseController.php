<?php

namespace App\Shared\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Traits\SecurePaginationTrait;
use App\Traits\SecureValidationTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;
    use SecurePaginationTrait;
    use SecureValidationTrait;

    protected const DEFAULT_ERROR_MESSAGE = 'Une erreur est survenue';

    /**
     * Enveloppe une action avec gestion centralisée des erreurs.
     * Élimine le besoin de try-catch dans chaque controller.
     */
    protected function execute(callable $action, ?string $errorMessage = null): JsonResponse
    {
        try {
            return $action();
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée.',
            ], 403);
        } catch (ModelNotFoundException $e) {
            Log::warning("Ressource non trouvée: {$e->getMessage()}", [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return $this->responseEmpty('Ressource non trouvée');
        } catch (ValidationException $e) {
            Log::warning("Erreur de validation: {$e->getMessage()}", [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            $message = $errorMessage ?? static::DEFAULT_ERROR_MESSAGE;

            Log::error("{$message}: {$th->getMessage()}", [
                'exception' => $th::class,
                'file'      => $th->getFile(),
                'line'      => $th->getLine(),
                'trace'     => $th->getTraceAsString(),
            ]);

            return $this->responseError($message);
        }
    }

    /**
     * Récupère le nombre d'éléments par page depuis la requête (limité à $max).
     */
    protected function getPerPage(Request $request, int $default = 15, int $max = 100): int
    {
        return min((int) $request->input('per_page', $default), $max);
    }

    /**
     * Extrait uniquement les filtres autorisés depuis la requête.
     */
    protected function getFilters(Request $request, array $allowedFilters = []): array
    {
        if (empty($allowedFilters)) {
            return $request->all();
        }

        return $request->only($allowedFilters);
    }

    /**
     * Retourne filters + per_page en une seule ligne dans le controller.
     */
    protected function getPaginationAndFilters(Request $request, array $allowedFilters = [], int $defaultPerPage = 15): array
    {
        return [
            'filters'  => $this->getFilters($request, $allowedFilters),
            'per_page' => $this->getPerPage($request, $defaultPerPage),
        ];
    }

    /**
     * Retourne le tenant_id de l'utilisateur authentifié.
     * Personnaliser selon la colonne utilisée dans votre projet.
     */
    protected function getTenantId(): ?int
    {
        $user = Auth::user();
        return $user?->tenant_id ? (int) $user->tenant_id : null;
    }
}
