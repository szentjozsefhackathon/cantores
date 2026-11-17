<?php

namespace App\Observers;

use App\Models\Loan;
use App\Models\Season;
use App\Services\SeasonSharingService;

/**
 * A season's link handed out or recalled — from the season's own page or from
 * the lending centre — brings the loans on its attached booklets and plans
 * along with it.
 */
class LoanObserver
{
    public function saved(Loan $loan): void
    {
        if ($loan->lendable_type !== Season::class) {
            return;
        }

        $season = $loan->lendable;

        if ($season instanceof Season) {
            app(SeasonSharingService::class)->sync($season);
        }
    }
}
