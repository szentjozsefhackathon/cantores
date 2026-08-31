<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\Share;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves secret links and decides what a link reaches.
 *
 * Access to a score held through a folder or plan grant is derived here on every
 * request rather than minted onto the score, so revoking the grant revokes every
 * URL beneath it and nothing is left behind to garbage-collect.
 */
class ShareAccessService
{
    /**
     * The live grant for a token, or null when the token is unknown, revoked or expired.
     */
    public function resolve(?string $token): ?Share
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return Share::query()
            ->live()
            ->with('shareable')
            ->where('token', $token)
            ->first();
    }

    /**
     * The live grant for a token, but only when it shares the given model class.
     *
     * The entry-point routes are typed — /f/{token} is a folder link — so a token
     * that resolves to a different kind of grant must not be honoured there.
     *
     * @param  class-string  $shareableType
     */
    public function resolveOfType(?string $token, string $shareableType): ?Share
    {
        $share = $this->resolve($token);

        return $share?->shareable instanceof $shareableType ? $share : null;
    }

    /**
     * Whether a grant reaches the given score.
     */
    public function grantsScore(Share $share, Score $score): bool
    {
        $shareable = $share->shareable;

        return match (true) {
            $shareable instanceof Score => $shareable->getKey() === $score->getKey(),
            $shareable instanceof Folder => $shareable->scores()->whereKey($score->getKey())->exists(),
            $shareable instanceof MusicPlan => $shareable->reachableScores()->whereKey($score->getKey())->exists(),
            default => false,
        };
    }

    /**
     * Every score a grant reaches, for the folder and plan listing views.
     *
     * @return Collection<int, Score>
     */
    public function scoresFor(Share $share): Collection
    {
        $shareable = $share->shareable;

        return match (true) {
            $shareable instanceof Score => Score::query()->whereKey($shareable->getKey())->get(),
            $shareable instanceof Folder => $shareable->scores()->orderBy('title')->get(),
            $shareable instanceof MusicPlan => $shareable->reachableScores()->orderBy('title')->get(),
            default => Score::query()->whereRaw('1 = 0')->get(),
        };
    }

    /**
     * Constrain a score query to what a grant reaches.
     *
     * @param  Builder<Score>  $query
     * @return Builder<Score>
     */
    public function scopeToShare(Builder $query, Share $share): Builder
    {
        return $query->whereIn('id', $this->scoresFor($share)->modelKeys());
    }

    /**
     * Every live grant that reaches a score — its own, plus any folder or plan grant
     * that leads to it. This is what an owner needs to see before assuming a score is
     * private: a folder or plan they shared once still opens it.
     *
     * @return Collection<int, Share>
     */
    public function grantsReaching(Score $score): Collection
    {
        $folderIds = $score->folders()->pluck('folders.id');

        $planIds = MusicPlan::query()
            ->where('user_id', $score->user_id)
            ->when(
                $score->music_id !== null,
                fn (Builder $query) => $query->whereHas(
                    'musicAssignments',
                    fn (Builder $assignments) => $assignments->where('music_id', $score->music_id)
                ),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->pluck('id');

        return Share::query()
            ->live()
            ->with('shareable')
            ->where(function (Builder $query) use ($score, $folderIds, $planIds): void {
                $query->where(fn (Builder $q) => $q->where('shareable_type', Score::class)->where('shareable_id', $score->getKey()))
                    ->orWhere(fn (Builder $q) => $q->where('shareable_type', Folder::class)->whereIn('shareable_id', $folderIds))
                    ->orWhere(fn (Builder $q) => $q->where('shareable_type', MusicPlan::class)->whereIn('shareable_id', $planIds));
            })
            ->latest('id')
            ->get();
    }
}
