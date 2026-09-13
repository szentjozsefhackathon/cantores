<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a music plan is turned into: a booklet, a projection.
 *
 * The two are the same document seen from opposite sides of the service — the
 * one in the hands of the people singing, the one on the wall in front of the
 * people being sung to — and they are chosen in exactly the same way: on the
 * plan itself, slot by slot, music by music. That choosing is a good deal of
 * careful tree-walking (see App\Services\PlanOutline), and this is what lets
 * there be one copy of it rather than one per document.
 *
 * Deliberately tiny. Everything the outline needs to know is which plan the
 * document was built from and what it has taken; everything else about a
 * booklet — its paper, its margins, the size it unifies its scores to — is the
 * booklet's own business and none of the outline's.
 *
 * @property-read \App\Models\MusicPlan|null $musicPlan
 */
interface PlanDocument
{
    /**
     * The plan this document was built from, or none.
     *
     * @return BelongsTo<\App\Models\MusicPlan, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function musicPlan(): BelongsTo;

    /**
     * What the document has taken from the plan, in the order it is read.
     *
     * @return HasMany<covariant \Illuminate\Database\Eloquent\Model, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function entries(): HasMany;
}
