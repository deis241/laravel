<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait SecureValidationTrait
{
    protected function getSecurityRules(): array
    {
        return [
            'no_sql_injection' => 'regex:/^[^;\'"\\\\]*$/',
            'no_script_tags'   => 'regex:/^(?!.*<script).*$/i',
            'secure_email'     => 'email:rfc,dns',
            'secure_url'       => 'url|active_url',
            'phone'            => 'regex:/^[\d\s\+\-\(\)]+$/',
            'money'            => 'numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function secureValidate(array $data, array $rules, array $messages = []): array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    protected function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(chr(0), '', $value);

        return trim($value);
    }

    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitized[$key] = match (true) {
                is_array($value)  => $this->sanitizeArray($value),
                is_string($value) => $this->sanitizeString($value),
                default           => $value,
            };
        }

        return $sanitized;
    }

    protected function isSafeString(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $dangerousPatterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/on\w+\s*=\s*["\'].*?["\']/is',
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return false;
            }
        }

        return true;
    }

    protected function logSecurityEvent(string $event, array $context = []): void
    {
        Log::warning("Événement de sécurité: {$event}", array_merge([
            'user_id'    => auth()->id(),
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp'  => now(),
        ], $context));
    }
}
