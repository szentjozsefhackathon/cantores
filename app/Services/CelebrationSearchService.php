<?php

namespace App\Services;

use App\Models\Celebration;
use Illuminate\Database\Eloquent\Collection;

class CelebrationSearchService
{
    /**
     * Memoized collection of all celebrations, loaded once per service instance.
     *
     * @var Collection<int, Celebration>|null
     */
    private ?Collection $allCelebrations = null;

    /**
     * Find celebrations related to the given criteria, scoring each match.
     *
     * The criteria can include any of the celebration attributes:
     * - celebration_key
     * - actual_date
     * - name
     * - season
     * - season_text
     * - week
     * - day
     * - readings_code
     * - year_letter
     * - year_parity
     *
     * Returns a collection of Celebration models with an added 'score' attribute,
     * sorted by score descending.
     *
     * Scoring rules (points are additive):
     * 1. readings_code is exactly the same (10 points)
     * 2. name is exactly the same (5 points)
     * 3. day is 0 and the (season, week, day, year_letter) group is exactly the same (2 points)
     * 4. day is not 0 and the (season, week, day, parity) group is exactly the same (2 points)
     * 5. (season, week, day) group is exactly the same (1 point)
     *
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, Celebration>
     */
    public function findRelated(array $criteria): Collection
    {
        // Fetch all celebrations (including custom celebrations) once per instance.
        // Subsequent calls within the same render reuse the memoized collection.
        $allCelebrations = $this->allCelebrations ??= Celebration::query()->get();

        $criteria = $this->castCriteria($criteria);

        $scored = $allCelebrations->map(function (Celebration $celebration) use ($criteria) {
            $score = $this->computeScore($celebration, $criteria);
            $celebration->score = $score;

            return $celebration;
        })->filter(fn (Celebration $celebration) => $celebration->score > 0)
            ->sortByDesc('score')
            ->values();

        return $scored;
    }

    /**
     * Cast numeric criteria to integers for proper type comparison.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function castCriteria(array $criteria): array
    {
        foreach (['season', 'week', 'day'] as $key) {
            if (isset($criteria[$key])) {
                $criteria[$key] = (int) $criteria[$key];
            }
        }

        return $criteria;
    }

    /**
     * Compute the relevance score for a single celebration against the given criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    protected function computeScore(Celebration $celebration, array $criteria): int
    {
        return array_sum(array_column($this->scoreBreakdown($celebration, $criteria), 'points'));
    }

    /**
     * Explain the relevance score for a single celebration as a list of matched rules.
     *
     * Each entry contains a human-readable label and the points it contributed,
     * mirroring the additive rules documented on findRelated().
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, array{label: string, points: int}>
     */
    public function scoreBreakdown(Celebration $celebration, array $criteria): array
    {
        $criteria = $this->castCriteria($criteria);
        $reasons = [];

        // Rule 1: readings_code exactly the same (5 points)
        if (isset($criteria['readings_code']) && $criteria['readings_code'] !== null) {
            if ($celebration->readings_code === $criteria['readings_code']) {
                $reasons[] = ['label' => __('Same readings'), 'points' => 5];
            }
        }

        // Rule 2: name exactly the same (10 points)
        if (isset($criteria['name']) && $criteria['name'] !== null) {
            if ($celebration->name === $criteria['name']) {
                $reasons[] = ['label' => __('Same celebration name'), 'points' => 10];
            }
        }

        // Rule 3: day is 0 and the (season, week, day, year_letter) group exactly the same (2 points)
        if (isset($criteria['day']) && $criteria['day'] === 0) {
            $match = isset($criteria['season'], $criteria['week'], $criteria['day'], $criteria['year_letter'])
                && $celebration->season === $criteria['season']
                && $celebration->week === $criteria['week']
                && $celebration->day === $criteria['day']
                && $celebration->year_letter === $criteria['year_letter'];
            if ($match) {
                $reasons[] = ['label' => __('Same liturgical day and year cycle'), 'points' => 2];
            }
        }

        // Rule 4: day is not 0 and the (season, week, day, parity) group exactly the same (2 points)
        if (isset($criteria['day']) && $criteria['day'] !== 0) {
            $match = isset($criteria['season'], $criteria['week'], $criteria['day'], $criteria['year_parity'])
                && $celebration->season === $criteria['season']
                && $celebration->week === $criteria['week']
                && $celebration->day === $criteria['day']
                && $celebration->year_parity === $criteria['year_parity'];
            if ($match) {
                $reasons[] = ['label' => __('Same liturgical day and year parity'), 'points' => 2];
            }
        }

        // Rule 5: (season, week, day) group exactly the same (1 point)
        if (isset($criteria['season'], $criteria['week'], $criteria['day'])) {
            if ($celebration->season === $criteria['season']
                && $celebration->week === $criteria['week']
                && $celebration->day === $criteria['day']) {
                $reasons[] = ['label' => __('Same liturgical day'), 'points' => 1];
            }
        }

        return $reasons;
    }

    /**
     * Find related celebrations for a given Celebration model.
     *
     * This is a convenience method that extracts the relevant attributes
     * from the model and calls findRelated().
     */
    public function findRelatedForCelebration(Celebration $celebration): Collection
    {
        $criteria = [
            'celebration_key' => $celebration->celebration_key,
            'actual_date' => $celebration->actual_date,
            'name' => $celebration->name,
            'season' => $celebration->season,
            'season_text' => $celebration->season_text,
            'week' => $celebration->week,
            'day' => $celebration->day,
            'readings_code' => $celebration->readings_code,
            'year_letter' => $celebration->year_letter,
            'year_parity' => $celebration->year_parity,
        ];

        // Remove null values to match the behavior of empty fields in the query
        $criteria = array_filter($criteria, fn ($value) => $value !== null);

        return $this->findRelated($criteria);
    }

    /**
     * Get related celebrations as a simple array with score.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, array{celebration: Celebration, score: int}>
     */
    public function findRelatedWithScore(array $criteria): array
    {
        $collection = $this->findRelated($criteria);

        return $collection->map(fn (Celebration $celebration) => [
            'celebration' => $celebration,
            'score' => $celebration->score,
        ])->all();
    }
}
