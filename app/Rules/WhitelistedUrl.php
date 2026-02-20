<?php

namespace App\Rules;

use App\Services\UrlWhitelistValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class WhitelistedUrl implements ValidationRule
{
    /**
     * The URL whitelist validator instance.
     */
    protected UrlWhitelistValidator $validator;

    /**
     * Custom error message.
     */
    protected ?string $customMessage = null;

    /**
     * Create a new rule instance.
     */
    public function __construct(?string $message = null)
    {
        $this->validator = app(UrlWhitelistValidator::class);
        $this->customMessage = $message;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if value is empty (let required rule handle that)
        if (empty($value)) {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        try {
            $isValid = $this->validator->validate($value);
        } catch (\InvalidArgumentException $e) {
            // Log malformed URL errors for debugging
            Log::debug('URL validation failed due to malformed URL', [
                'url' => $value,
                'error' => $e->getMessage(),
                'attribute' => $attribute,
            ]);

            $fail('The :attribute is not a valid URL.');

            return;
        }

        if (! $isValid) {
            $errorMessage = $this->customMessage ?? $this->validator->getErrorMessage($value);
            $fail($errorMessage);
        }
    }

    /**
     * Set a custom error message.
     */
    public function withMessage(string $message): self
    {
        $this->customMessage = $message;

        return $this;
    }
}
