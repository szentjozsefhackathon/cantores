<?php

namespace App\Observers;

use App\Models\DeviceName;
use App\Models\DevicePairing;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Models\ProjectionSlide;
use App\Models\Screen;
use App\Services\ShowStream;
use Illuminate\Database\Eloquent\Model;

/**
 * Nudges a person's devices when a save changes what their show looks like.
 *
 * Two kinds of save. One belongs to a person — where the service is, which
 * walls they have, what those are called — and goes to that person's topic.
 * The other is an edit to a deck, which belongs to whoever is showing it.
 *
 * `updated` rather than `saved`, and with the heartbeat columns taken out,
 * because the wall reports every ten seconds whether anything moved or not: a
 * save that only says "still here" must not send every device back to the
 * server to find that out.
 *
 * @see ShowStream
 */
class ShowStreamObserver
{
    /**
     * Columns that move without the show moving.
     *
     * @var list<string>
     */
    private const HEARTBEAT_COLUMNS = ['last_seen_at', 'updated_at'];

    public function __construct(private ShowStream $stream) {}

    public function created(Model $model): void
    {
        $this->nudge($model);
    }

    public function updated(Model $model): void
    {
        if (array_diff(array_keys($model->getChanges()), self::HEARTBEAT_COLUMNS) === []) {
            return;
        }

        $this->nudge($model);
    }

    public function deleted(Model $model): void
    {
        $this->nudge($model);
    }

    private function nudge(Model $model): void
    {
        match (true) {
            $model instanceof Presentation,
            $model instanceof Screen,
            $model instanceof DeviceName,
            $model instanceof DevicePairing => $this->stream->changedFor($model->user_id),
            $model instanceof Projection => $this->stream->deckChanged($model->getKey()),
            $model instanceof ProjectionSlide,
            $model instanceof ProjectionMusic => $this->stream->deckChanged($model->projection_id),
            default => null,
        };
    }
}
