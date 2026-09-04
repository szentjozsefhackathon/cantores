<?php

namespace App\Models;

use App\Enums\ScoreFileRenderStatus;
use App\Enums\ScoreFileRights;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded sheet music file belonging to a score.
 *
 * The bytes live on the `private` disk under `score-files/{id}/`, encrypted at
 * rest by ScoreFileStorage. This row holds everything needed to serve them
 * without decrypting: the checksum backs the ETag and `rendered_at` the
 * Last-Modified, so a conditional request never touches the ciphertext.
 *
 * @property int $id
 * @property int $score_id
 * @property string $path
 * @property string $original_name
 * @property string|null $label
 * @property string|null $mime
 * @property int $size_bytes
 * @property string $checksum
 * @property \App\Enums\ScoreFileRights $rights
 * @property bool $is_published
 * @property \App\Enums\ScoreFileRenderStatus $render_status
 * @property string|null $render_error
 * @property bool $has_thumbnail
 * @property int|null $page_count
 * @property \Carbon\CarbonImmutable|null $rendered_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 *
 * @method static \Database\Factories\ScoreFileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreFile query()
 *
 * @mixin \Eloquent
 */
class ScoreFile extends Model
{
    /** @use HasFactory<\Database\Factories\ScoreFileFactory> */
    use HasFactory;

    /**
     * Formats the renderer accepts, mapped to the upload's expected extension.
     *
     * @var list<string>
     */
    public const RENDERABLE_EXTENSIONS = ['mscz', 'mscx', 'musicxml', 'mxl', 'xml', 'mid', 'midi', 'pdf'];

    /**
     * Formats that are already engraved, so the renderer only has to cut them
     * into page images.
     *
     * @var list<string>
     */
    public const PRERENDERED_EXTENSIONS = ['pdf'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'score_id',
        'path',
        'original_name',
        'label',
        'mime',
        'size_bytes',
        'checksum',
        'rights',
        'is_published',
        'render_status',
        'render_error',
        'has_thumbnail',
        'page_count',
        'rendered_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_thumbnail' => 'boolean',
            'is_published' => 'boolean',
            'rights' => ScoreFileRights::class,
            'render_status' => ScoreFileRenderStatus::class,
            'rendered_at' => 'datetime',
        ];
    }

    /**
     * ScoreFileUploader::replace() keeps this row and swaps the bytes behind it,
     * so an approved publication has to be re-checked whenever a file's
     * contents or publication flag change. Without this, review is bypassable
     * in one click.
     */
    protected static function booted(): void
    {
        static::saved(function (ScoreFile $scoreFile): void {
            if (! $scoreFile->wasChanged(['checksum', 'is_published', 'rights'])) {
                return;
            }

            $publication = $scoreFile->score?->publication;

            if ($publication === null || ! $publication->status->isPublic()) {
                return;
            }

            if ($publication->matchesApprovedFingerprint()) {
                return;
            }

            app(\App\Services\ScorePublicationService::class)->invalidateApproval($publication);
        });
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    /**
     * The directory holding every artifact derived from this file.
     */
    public function directory(): string
    {
        return "score-files/{$this->id}";
    }

    public function renderPath(): string
    {
        return $this->directory().'/render.pdf';
    }

    public function pagePath(int $page): string
    {
        return $this->directory()."/page-{$page}.png";
    }

    public function thumbPath(): string
    {
        return $this->directory().'/thumb.png';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isRenderable(): bool
    {
        return in_array($this->extension(), self::RENDERABLE_EXTENSIONS, true);
    }

    /**
     * Whether the upload is already an engraved document, so the renderer takes
     * it as the PDF instead of producing one.
     */
    public function isPrerendered(): bool
    {
        return in_array($this->extension(), self::PRERENDERED_EXTENSIONS, true);
    }

    /**
     * What to call this file in a listing: the name its owner gave it, falling
     * back to the name it was uploaded under.
     */
    public function displayName(): string
    {
        return $this->label !== null && $this->label !== '' ? $this->label : $this->original_name;
    }

    /**
     * Whether the renderer still owes this file a preview, so the page showing
     * it should keep polling.
     */
    public function isRendering(): bool
    {
        return ! $this->render_status->isFinal();
    }

    public function isReady(): bool
    {
        return $this->render_status === ScoreFileRenderStatus::Ready;
    }

    /**
     * Whether this file is part of what its score offers the public.
     *
     * Says nothing about the score's own standing: an offered file of an
     * unapproved score is what a reviewer looks at, and what nobody else may.
     * Both conditions are checked at read time rather than trusted from the
     * flag, so a rights downgrade takes effect without a backfill.
     */
    public function mayBeOffered(): bool
    {
        return $this->is_published && $this->rights->mayBePublished();
    }

    /**
     * Whether this file may be served to a guest right now.
     *
     * All three conditions are checked at serve time rather than trusted from
     * the flag: the owner flagged it, its declared rights still permit it, and
     * the score it belongs to still carries an approved publication.
     */
    public function isPubliclyAvailable(): bool
    {
        return $this->mayBeOffered() && $this->score->isPublished();
    }

    /**
     * The 1-based page numbers of the render, empty while nothing is rendered.
     *
     * @return list<int>
     */
    public function pageNumbers(): array
    {
        return $this->isReady() && $this->page_count ? range(1, $this->page_count) : [];
    }

    public function hasPage(int $page): bool
    {
        return $page >= 1 && $page <= ($this->page_count ?? 0);
    }
}
