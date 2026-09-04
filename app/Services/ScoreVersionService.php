<?php

namespace App\Services;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\ScoreUrl;
use App\Models\ScoreVersion;
use Illuminate\Support\Facades\DB;

/**
 * Freezes the published surface of a score.
 *
 * A version exists so the public library has a fixed thing to have approved. It
 * is made when a nomination enters the queue and refreshed while it waits there,
 * never on an ordinary save and never on a timer — a handful of rows per score,
 * no background machinery.
 *
 * Versioning is publication-only on purpose. The private axis is exempt: a
 * borrower wants the newest reading, always, so there is no version history in
 * the score editor and no version numbers anywhere in the interface.
 */
class ScoreVersionService
{
    /**
     * The version a nomination should be judged on.
     *
     * While a nomination is waiting, its version is refreshed in place rather than
     * added to: the reviewer must read the current offer, and the row the public is
     * reading is a different one that this never touches.
     */
    public function snapshotFor(ScorePublication $publication): ScoreVersion
    {
        $score = $publication->score;
        $pending = $publication->submitted_version_id !== null
            && $publication->submitted_version_id !== $publication->approved_version_id
            ? $publication->submittedVersion
            : null;

        return $pending instanceof ScoreVersion
            ? $this->refresh($pending, $score)
            : $this->snapshot($score);
    }

    /**
     * A new frozen copy of everything the public page draws from.
     */
    public function snapshot(Score $score): ScoreVersion
    {
        return DB::transaction(function () use ($score): ScoreVersion {
            $version = ScoreVersion::query()->create([
                'score_id' => $score->getKey(),
                ...$this->attributesOf($score),
            ]);

            $version->files()->sync($this->publishableFileIds($score));

            return $version;
        });
    }

    /**
     * Bring a version that is still in the queue up to date with the live score.
     */
    public function refresh(ScoreVersion $version, Score $score): ScoreVersion
    {
        return DB::transaction(function () use ($version, $score): ScoreVersion {
            $version->update($this->attributesOf($score));
            $version->files()->sync($this->publishableFileIds($score));

            return $version->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesOf(Score $score): array
    {
        return [
            'content' => $score->content,
            'format' => $score->format?->value,
            'settings' => $score->settings,
            'urls' => ScoreUrl::query()
                ->where('score_id', $score->getKey())
                ->orderBy('id')
                ->get()
                ->map(fn (ScoreUrl $url): array => [
                    'url' => $url->url,
                    'label' => $url->label?->value,
                    'comment' => $url->comment,
                ])
                ->all(),
        ];
    }

    /**
     * @return list<int>
     */
    private function publishableFileIds(Score $score): array
    {
        return ScoreFile::query()
            ->where('score_id', $score->getKey())
            ->whereNull('superseded_at')
            ->where('is_published', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
