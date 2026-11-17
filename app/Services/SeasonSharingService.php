<?php

namespace App\Services;

use App\Models\Booklet;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\Season;
use App\Models\SeasonEvent;
use App\Models\SeasonEventAttachment;

/**
 * Keeps what a season's dates point to open exactly while the season is shared.
 *
 * A booklet a choir reads on the season's page is opened through the booklet's
 * own lending link — the booklet reader, its strips and its pages are all
 * addressed by that token and checked against it on every request, and that
 * machinery is not to be routed round. So the season holds one loan per
 * attached booklet or plan, labelled with the season's name, and this service
 * answers for its life: minted while the season has a live link, recalled when
 * the last one goes, when the attachment is removed, and when the date or the
 * season is deleted.
 */
class SeasonSharingService
{
    /**
     * Attach one of the season owner's booklets or plans to a date.
     */
    public function attach(SeasonEvent $event, Booklet|MusicPlan $lendable): SeasonEventAttachment
    {
        $season = $event->season;

        abort_unless($lendable->user_id === $season->user_id, 403);

        /** @var SeasonEventAttachment $attachment */
        $attachment = $event->attachments()->create([
            'lendable_type' => $lendable::class,
            'lendable_id' => $lendable->getKey(),
            'position' => $this->nextPosition($event),
        ]);

        if ($this->isShared($season)) {
            $this->mint($attachment, $season);
        }

        return $attachment;
    }

    /**
     * Attach a plain link — a Drive folder, a recording — to a date.
     */
    public function attachLink(SeasonEvent $event, string $url, ?string $label = null): SeasonEventAttachment
    {
        $label = $label === null ? null : trim($label);

        /** @var SeasonEventAttachment */
        return $event->attachments()->create([
            'url' => $url,
            'label' => $label === '' ? null : $label,
            'position' => $this->nextPosition($event),
        ]);
    }

    /**
     * Take an attachment off its date, and the loan it held with it.
     */
    public function detach(SeasonEventAttachment $attachment): void
    {
        $this->recall($attachment);

        $attachment->delete();
    }

    /**
     * Recall the loan an attachment holds, if it holds a live one.
     */
    public function recall(SeasonEventAttachment $attachment): void
    {
        $loan = $attachment->loan;

        if ($loan instanceof Loan && $loan->isLive()) {
            $loan->revoke();
        }
    }

    /**
     * Bring every attachment's loan in line with whether the season is shared.
     *
     * Called whenever one of the season's own links is handed out or recalled
     * (see LoanObserver), so the booklets go dark with the last season link and
     * come back with the next one.
     */
    public function sync(Season $season): void
    {
        $shared = $this->isShared($season);

        $season->attachments()
            ->whereNotNull('lendable_id')
            ->with(['loan', 'lendable'])
            ->get()
            ->each(function (SeasonEventAttachment $attachment) use ($shared, $season): void {
                $live = $attachment->loan instanceof Loan && $attachment->loan->isLive();

                if ($shared && ! $live && $attachment->lendable !== null) {
                    $this->mint($attachment, $season);
                }

                if (! $shared && $live) {
                    $attachment->loan->revoke();
                }
            });
    }

    /**
     * Recall every loan the season holds for its attachments, before the season
     * itself goes.
     */
    public function release(Season $season): void
    {
        $season->attachments()
            ->whereNotNull('loan_id')
            ->with('loan')
            ->get()
            ->each(fn (SeasonEventAttachment $attachment) => $this->recall($attachment));
    }

    public function isShared(Season $season): bool
    {
        return $season->liveLoans()->exists();
    }

    private function mint(SeasonEventAttachment $attachment, Season $season): void
    {
        /** @var Booklet|MusicPlan $lendable */
        $lendable = $attachment->lendable;

        $loan = $lendable->lend($season->user, mb_substr(__('Season: :name', ['name' => $season->name]), 0, 100));

        $attachment->forceFill(['loan_id' => $loan->getKey()])->save();
        $attachment->setRelation('loan', $loan);
    }

    private function nextPosition(SeasonEvent $event): int
    {
        return ((int) $event->attachments()->max('position')) + 1;
    }
}
