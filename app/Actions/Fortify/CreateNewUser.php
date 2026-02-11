<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'first_name_id' => ['required', 'integer', 'exists:first_names,id'],
            'cf-turnstile-response' => ['required', new Turnstile],
        ])->after(function ($validator) use ($input) {
            \Illuminate\Support\Facades\Log::debug('Checking duplicate city/first name combination', ['city_id' => $input['city_id'] ?? null, 'first_name_id' => $input['first_name_id'] ?? null]);
            if (isset($input['city_id'], $input['first_name_id'])) {
                $exists = User::where('city_id', $input['city_id'])
                    ->where('first_name_id', $input['first_name_id'])
                    ->exists();
                \Illuminate\Support\Facades\Log::debug('Duplicate check result', ['exists' => $exists]);
                if ($exists) {
                    $validator->errors()->add('city_id', __('This city and first name combination is already taken.'));
                    $validator->errors()->add('first_name_id', __('This city and first name combination is already taken.'));
                }
            }
        })->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'city_id' => $input['city_id'],
            'first_name_id' => $input['first_name_id'],
        ]);
    }
}
