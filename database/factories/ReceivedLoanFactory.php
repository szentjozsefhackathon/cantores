<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReceivedLoan>
 */
class ReceivedLoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'loan_id' => Loan::factory(),
            'score_id' => null,
            'first_opened_at' => now(),
            'last_opened_at' => now(),
        ];
    }

    public function kept(): static
    {
        return $this->state(['kept_at' => now()]);
    }

    public function hidden(): static
    {
        return $this->state(['hidden_at' => now()]);
    }

    /**
     * Keep one score out of a folder or plan loan, rather than the whole loan.
     */
    public function ofScore(Score $score): static
    {
        return $this->state(['score_id' => $score->getKey()]);
    }
}
