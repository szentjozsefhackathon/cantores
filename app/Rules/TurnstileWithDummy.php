<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile as OriginalTurnstile;

class TurnstileWithDummy implements ValidationRule
{
    /**
     * The original Turnstile validator instance.
     */
    protected OriginalTurnstile $originalValidator;

    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        $this->originalValidator = new OriginalTurnstile;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // If the token starts with "XXXX", treat it as a dummy token and pass validation
        if (is_string($value) && str_starts_with($value, 'XXXX')) {
            return;
        }

        // Otherwise, delegate to the original Turnstile validator
        $this->originalValidator->validate($attribute, $value, $fail);
    }
}
