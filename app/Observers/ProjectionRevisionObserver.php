<?php

namespace App\Observers;

use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Models\ProjectionSlide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Forgets a deck's cached revision the moment the deck is edited.
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
 * @see Projection::revision()
 */
class ProjectionRevisionObserver
{
    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
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
    }
}
