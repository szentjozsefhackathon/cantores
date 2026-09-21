<?php

namespace App\Observers;

use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Models\ProjectionSlide;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Forgets what is cached about a deck the moment the deck is edited.
 *
 * Projection::revision() is the one join on the polling path, and it is asked
 * about twice a second for every service going on. It is therefore cached — but
 * the half hour before a Mass is exactly when a deck changes, and a cantor who
 * retypes a stanza and looks up at the wall must not be made to wait for an
 * entry to age out. So the two saves that *are* an edit to a deck forget it by
 * name, and the entry's short life is left to cover only the case no save can
 * name: a score corrected in another window, which belongs to every deck that
 * has it in a row.
 *
 * The same save forgets the deck's row order, which is the other thing the
 * polling path reads and the other thing only an edit can move.
 *
 * @see Projection::revision()
 * @see Projection::entryOrder()
 */
class ProjectionRevisionObserver
{
    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    /**
     * A row taken out of a deck is the one edit the revision cannot see by
     * itself.
     *
     * The revision is the newest stamp among the deck, its rows and their
     * scores, so removing the newest of them moves it *backwards* — and a
     * screen that has already drawn the higher one is then stuck for good,
     * because nothing it can report of itself is newer than what the server
     * remembers it drew. Stamping the deck instead is both true and monotonic:
     * the deck did change, and it changed just now.
     *
     * The stamp goes in before the cache is forgotten, so no poll in between
     * can cache the lower revision the deletion briefly leaves behind.
     *
     * @see Screen::acknowledge()
     */
    public function deleted(Model $model): void
    {
        if ($model instanceof ProjectionSlide || $model instanceof ProjectionMusic) {
            $model->projection?->touch();
        }

        $this->forget($model);
    }

    /**
     * Which deck this save was an edit to — the deck itself, or the deck the
     * row belongs to.
     */
    private function forget(Model $model): void
    {
        $projectionId = match (true) {
            $model instanceof Projection => $model->getKey(),
            $model instanceof ProjectionSlide, $model instanceof ProjectionMusic => $model->projection_id,
            default => null,
        };

        if ($projectionId === null) {
            return;
        }

        Cache::forget(Projection::revisionKey((int) $projectionId));
        Cache::forget(Projection::entryOrderKey((int) $projectionId));
    }
}
