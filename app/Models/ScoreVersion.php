<?php

namespace App\Models;

use App\Enums\ScoreFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A score exactly as it was offered to the public, frozen at submission.
 *
 * The public library reads this rather than the live score, so an owner fixing a
 * typo does not take their own score off the shelf while the correction waits in
 * the queue, and a reviewer judges a stable artefact rather than whatever exists
 * at the instant they press approve.
 *
 * Render settings live here because the page cannot draw without them, but they
 * are deliberately outside the re-review trigger: a transpose changes how the same
 * notes look and cannot introduce anyone else's work.
 *
 * @property int $id
 * @property int $score_id
 * @property string|null $content
 * @property \App\Enums\ScoreFormat|null $format
 * @property array<string, mixed>|null $settings
 * @property array<int, array<string, mixed>>|null $urls
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Score $score
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile> $files
 *
 * @method static \Database\Factories\ScoreVersionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreVersion query()
 *
 * @mixin \Eloquent
 */
class ScoreVersion extends Model
{
    /** @use HasFactory<\Database\Factories\ScoreVersionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'score_id',
        'content',
        'format',
        'settings',
        'urls',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ScoreFormat::class,
            'settings' => 'array',
            'urls' => 'array',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    /**
     * The files this version was submitted with.
     *
     * A file referenced here is never hard-deleted: ScoreFileUploader supersedes it
     * instead, so the bytes behind an approved snapshot survive a replacement.
     */
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(ScoreFile::class, 'score_version_file')->withTimestamps();
    }

    /**
     * The files this version offers the public, in the order they were uploaded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    public function publishedFiles(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->files()->orderBy('score_files.id')->get();
    }

    /**
     * A digest of everything a reviewer had to judge in this version.
     *
     * Deliberately not the render settings: they change how the same notes look and
     * cannot introduce anyone else's work.
     */
    public function fingerprint(): string
    {
        return ScorePublication::fingerprintOf(
            $this->content,
            $this->format?->value,
            $this->urls ?? [],
            $this->files()->orderBy('score_files.id')->pluck('checksum')->all(),
        );
    }
}
