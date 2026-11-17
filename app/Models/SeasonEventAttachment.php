<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SeasonEventAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Something a choir member needs for one date: one of the leader's booklets or
 * plans, or a link to somewhere else entirely.
 *
 * A booklet or plan is opened through its own lending link, which the season
 * keeps for it in `loan_id` and recalls when the season stops being shared —
 * see SeasonSharingService. A plain link is just that: a URL and a label.
 *
 * @property int $id
 * @property int $season_event_id
 * @property string|null $lendable_type
 * @property int|null $lendable_id
 * @property int|null $loan_id
 * @property string|null $url
 * @property string|null $label
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SeasonEvent $event
 * @property-read Booklet|MusicPlan|null $lendable
 * @property-read Loan|null $loan
 *
 * @method static \Database\Factories\SeasonEventAttachmentFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SeasonEventAttachment extends Model
{
    /** @use HasFactory<SeasonEventAttachmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_event_id',
        'lendable_type',
        'lendable_id',
        'loan_id',
        'url',
        'label',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SeasonEvent::class, 'season_event_id');
    }

    public function lendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isLink(): bool
    {
        return $this->url !== null;
    }

    /**
     * What the attachment is called on the page.
     */
    public function title(): string
    {
        if ($this->label !== null && $this->label !== '') {
            return $this->label;
        }

        return match (true) {
            $this->lendable instanceof Booklet => $this->lendable->title,
            $this->lendable instanceof MusicPlan => $this->lendable->celebration_name ?? __('Music Plan'),
            $this->url !== null => (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url),
            default => __('Deleted'),
        };
    }

    public function icon(): string
    {
        return match (true) {
            $this->lendable instanceof Booklet => 'book-open',
            $this->lendable instanceof MusicPlan => 'list-music',
            default => 'link',
        };
    }

    /**
     * Where a choir member is sent: the kept loan's address for a booklet or
     * plan, while it is live, or the link itself.
     */
    public function memberUrl(): ?string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        return $this->loan instanceof Loan && $this->loan->isLive() && $this->lendable !== null
            ? $this->loan->url()
            : null;
    }

    /**
     * Where the owner edits what is attached.
     */
    public function ownerUrl(): ?string
    {
        return match (true) {
            $this->lendable instanceof Booklet => route('booklets.edit', ['booklet' => $this->lendable->id]),
            $this->lendable instanceof MusicPlan => route('music-plan-editor', ['musicPlan' => $this->lendable->id]),
            default => $this->url,
        };
    }
}
