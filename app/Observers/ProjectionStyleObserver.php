<?php

namespace App\Observers;

use App\Models\Projection;
use App\Models\ProjectionStyle;

/**
 * An edit to a style is an edit to every deck shown in it.
 *
 * Each deck is touched rather than its revision recomputed, for the reason
 * ProjectionRevisionObserver gives for a deleted row: the deck did change, it
 * changed just now, and a stamp only ever moves forward. Touching through the
 * model rather than in one query is what lets the deck's own observers forget
 * its cached revision and tell the show stream, so the wall and the phone redraw
 * as they would for any other edit. A style is shared by a handful of decks, not
 * thousands.
 */
class ProjectionStyleObserver
{
    public function updated(ProjectionStyle $style): void
    {
        if (! $style->wasChanged(['ratio', 'settings', 'text_theme', 'text_size_scale', 'text_line_height', 'min_scale'])) {
            return;
        }

        $style->projections()->each(function (Projection $projection) use ($style): void {
            // A style's ratio is fixed once made, but a deck's is read by
            // every screen, so it is restated here rather than trusted.
            $projection->forceFill(['ratio' => $style->ratio])->touch();
        });
    }

    /**
     * Its decks go back to the factory defaults, and are stamped so the screens
     * showing them notice. The foreign key would let go of them too, but
     * without moving a single revision.
     */
    public function deleting(ProjectionStyle $style): void
    {
        $style->projections()->each(function (Projection $projection): void {
            $projection->forceFill(['projection_style_id' => null])->touch();
        });
    }
}
