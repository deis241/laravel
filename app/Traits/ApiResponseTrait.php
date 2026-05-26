<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponseTrait
{
    public function response(mixed $data, int $status = Response::HTTP_OK, string $message = 'Données disponibles'): JsonResponse
    {
        return response()->json([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    public function responseCreated(mixed $data, int $status = Response::HTTP_CREATED, string $message = 'Ressource créée avec succès'): JsonResponse
    {
        return response()->json([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    public function responseUpdated(mixed $data, int $status = Response::HTTP_ACCEPTED, string $message = 'Ressource mise à jour avec succès'): JsonResponse
    {
        return response()->json([
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    public function responseError(string $message = 'Une erreur est survenue. Contactez l\'administrateur.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }

    public function responseEmpty(string $message = 'Aucune donnée trouvée'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => [],
        ], Response::HTTP_NOT_FOUND);
    }
}
