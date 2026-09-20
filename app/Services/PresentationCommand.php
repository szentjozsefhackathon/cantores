<?php

namespace App\Services;

use App\Models\Presentation;
use App\Models\PresentationSource;
use App\Models\ProjectionSlide;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PresentationCommand
{
    /**
     * Apply one source's next partial command under the presentation row lock.
     *
     * @param  array{entryId?: int|null, slideIndex?: int, blanked?: bool, splash?: string, reveals?: array<int, list<int>>}  $changes
     */
    public function apply(Presentation $presentation, string $sourceId, int $sequence, array $changes): Presentation
    {
        return DB::transaction(function () use ($presentation, $sourceId, $sequence, $changes): Presentation {
            $locked = Presentation::query()->lockForUpdate()->findOrFail($presentation->getKey());

            $source = PresentationSource::query()
                ->whereBelongsTo($locked)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if (! $source instanceof PresentationSource) {
                $source = new PresentationSource([
                    'source_id' => $sourceId,
                    'last_sequence' => 0,
                    'applied_version' => 0,
                ]);
                $source->presentation()->associate($locked);
            }

            if ($sequence <= $source->last_sequence) {
                Log::info('Presentation command ignored as duplicate or stale.', [
                    'presentation_id' => $locked->id,
                    'source_id' => $sourceId,
                    'sequence' => $sequence,
                    'last_sequence' => $source->last_sequence,
                    'version' => $locked->version,
                ]);

                return $locked;
            }

            $oldVersion = $locked->version;
            $entry = $this->entryFor($locked, $changes);
            $locked->applyState($changes, $entry);

            $source->forceFill([
                'last_sequence' => $sequence,
                'applied_version' => $locked->version,
            ])->save();

            Log::info('Presentation command accepted.', [
                'presentation_id' => $locked->id,
                'source_id' => $sourceId,
                'sequence' => $sequence,
                'old_version' => $oldVersion,
                'new_version' => $locked->version,
                'fields' => array_keys($changes),
            ]);

            return $locked;
        });
    }

    /**
     * Atomically apply the old flat request shape during the rollout window.
     *
     * @param  array{entryId?: int|null, slideIndex?: int, blanked?: bool, splash?: string, reveals?: array<int, list<int>>}  $changes
     */
    public function applyLegacy(Presentation $presentation, array $changes): Presentation
    {
        return DB::transaction(function () use ($presentation, $changes): Presentation {
            $locked = Presentation::query()->lockForUpdate()->findOrFail($presentation->getKey());
            $locked->applyState($changes, $this->entryFor($locked, $changes));

            return $locked;
        });
    }

    /**
     * @param  array{entryId?: int|null}  $changes
     */
    private function entryFor(Presentation $presentation, array $changes): ?ProjectionSlide
    {
        if (! array_key_exists('entryId', $changes) || $changes['entryId'] === null) {
            return null;
        }

        $entry = ProjectionSlide::query()
            ->where('projection_id', $presentation->projection_id)
            ->find($changes['entryId']);

        if (! $entry instanceof ProjectionSlide) {
            throw ValidationException::withMessages([
                'changes.entryId' => __('The selected slide is not in this projection.'),
            ]);
        }

        return $entry;
    }
}
