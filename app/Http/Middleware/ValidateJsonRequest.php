<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateJsonRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && $request->getContentTypeFormat() === 'json') {
            $content = $request->getContent();

            if (!empty($content)) {
                json_decode($content);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'message' => 'JSON invalide',
                        'error'   => json_last_error_msg(),
                    ], 400);
                }
            }
        }

        $maxSize = (int) env('MAX_REQUEST_SIZE_MB', 10) * 1024 * 1024;
        if ($request->header('Content-Length') > $maxSize) {
            return response()->json([
                'message' => 'Requête trop volumineuse',
                'error'   => 'La taille de la requête dépasse la limite autorisée',
            ], 413);
        }

        return $next($request);
    }
}
