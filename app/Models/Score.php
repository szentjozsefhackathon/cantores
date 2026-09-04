<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Enums\ScoreFormat;
use App\Services\ScoreFileStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $music_id
 * @property string $title
 * @property string|null $variation_name
 * @property \App\Enums\ScoreFormat|null $format
 * @property string|null $content
 * @property array<string, array<string, array<string, mixed>>>|null $settings
 * @property string|null $share_token
 * @property bool $public_preview
 * @property-read string $incipit_path
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreUrl> $urls
 * @property-read int|null $urls_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile> $files
 * @property-read int|null $files_count
 * @property-read \App\Models\Music|null $music
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Folder> $folders
 * @property-read int|null $folders_count
 *
 * @method static \Database\Factories\ScoreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score query()
 *
 * @mixin \Eloquent
 */
class Score extends Model
{
    /** @use HasFactory<\Database\Factories\ScoreFactory> */
    use HasFactory;

    use HasLoans;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'music_id',
        'title',
        'variation_name',
        'format',
        'content',
        'settings',
        'share_token',
        'public_preview',
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
            'public_preview' => 'boolean',
        ];
    }

    /**
     * Two things a score's own row has to do for itself.
     *
     * Deleting: the database drops score_files rows by cascade, which never
     * reaches the encrypted artifacts on disk — so they are removed here, before
     * the row that names them is gone.
     *
     * Saving: the public page renders GABC, ABC and ChordPro in the reader's
     * browser straight from `content` and `format`, so editing the typed source
     * of a published score is exactly as capable of introducing someone else's
     * work as replacing a file, and re-enters the review queue the same way.
     * `settings` is deliberately not in the list — see ScorePublicationWatcher.
     */
    protected static function booted(): void
    {
        static::deleting(function (Score $score): void {
            $storage = app(ScoreFileStorage::class);

            foreach ($score->files()->get() as $file) {
                $storage->deleteAll($file);
            }
        });

        static::saved(function (Score $score): void {
            if (! $score->wasChanged(['content', 'format'])) {
                return;
            }

            app(\App\Services\ScorePublicationWatcher::class)->scoreChanged($score);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    public function urls(): HasMany
    {
        return $this->hasMany(ScoreUrl::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ScoreFile::class);
    }

    /**
     * This score's offer to the public library, if its owner ever made one.
     */
    public function publication(): HasOne
    {
        return $this->hasOne(ScorePublication::class);
    }

    /**
     * Whether guests may reach this score's pages and files.
     *
     * Publication is a third access axis, independent of both ownership
     * (ScorePolicy) and secret links (LoanAccessService): revoking a share
     * never unpublishes, and unpublishing never revokes a share.
     */
    public function isPublished(): bool
    {
        return $this->publication?->isPublic() === true;
    }

    /**
     * The files that go out with the publication, oldest first.
     *
     * A file's own declared rights are re-checked here rather than trusted from
     * the flag alone, so a rights downgrade takes effect without a backfill.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    public function publishedFiles(): Collection
    {
        return $this->orderedFiles()
            ->filter(fn (ScoreFile $file): bool => $file->mayBeOffered())
            ->values();
    }

    /**
     * The uploaded file this score is built around, if any.
     *
     * A score can hold several — the editable source beside the PDFs cut for
     * different paper — and the one uploaded first stands for the rest.
     */
    public function primaryFile(): ?ScoreFile
    {
        return $this->orderedFiles()->first();
    }

    /**
     * Every uploaded file still part of this score, oldest first, so a listing
     * keeps a stable order.
     *
     * Superseded rows are left out: those are bytes kept alive because a published
     * version refers to them, not files the score still offers. `files()` itself
     * stays unfiltered, because deleting a score has to reach every artifact.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    public function orderedFiles(): Collection
    {
        return $this->relationLoaded('files')
            ? $this->files->filter(fn (ScoreFile $file): bool => ! $file->isSuperseded())->sortBy('id')->values()
            : $this->files()->whereNull('superseded_at')->orderBy('id')->get();
    }

    /**
     * What to call this score among the other versions of the same music —
     * "Fuvola", "Kórus", "Csak szöveg". Falls back to the title, so a score
     * named before variations had names still reads sensibly in the list.
     */
    public function variationLabel(): string
    {
        return trim((string) $this->variation_name) !== '' ? (string) $this->variation_name : $this->title;
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class, 'folder_score');
    }

    public function getIncipitPathAttribute(): string
    {
        return "incipits/{$this->id}.png";
    }

    /**
     * The score file whose crop stands in for this score's incipit.
     *
     * A file-backed score's incipit lives encrypted beside the file rather than
     * in the shared plaintext incipits/ directory, so serving it goes through
     * ScoreFileResponder instead of the storage disk.
     */
    public function incipitFile(): ?ScoreFile
    {
        return $this->orderedFiles()->first(fn (ScoreFile $file): bool => $file->has_thumbnail);
    }

    public function hasIncipit(): bool
    {
        // Disk first: listings call this in a loop, and only a score with no
        // browser-generated incipit is worth a lookup for a file-backed one.
        if (Storage::exists($this->incipit_path)) {
            return true;
        }

        return $this->incipitFile() instanceof ScoreFile;
    }

    public function incipitUrl(): string
    {
        return route('scores.incipit', $this).'?v='.($this->updated_at?->timestamp ?? 0);
    }

    public function publicIncipitUrl(): string
    {
        return route('scores.public-incipit', $this).'?v='.($this->updated_at?->timestamp ?? 0);
    }

    /**
     * The slug half of this score's public URL.
     *
     * One canonical spelling, because PublicScoreView redirects anything else
     * to it and every link that gets it wrong pays a redirect.
     */
    public function publicSlug(): string
    {
        return Str::slug($this->title) ?: 'kotta';
    }

    /**
     * This score's page in the public library.
     *
     * The URL exists whether or not the score is published — a reviewer opens
     * it to judge a nomination — but only PublicScoreAccessService decides who
     * gets an answer from it.
     */
    public function publicUrl(): string
    {
        return route('public-scores.show', ['score' => $this, 'slug' => $this->publicSlug()]);
    }

    /**
     * The read-only page for this score as reached through the given grant.
     */
    public function loanUrl(string $token): string
    {
        return route('loan.score', ['token' => $token, 'score' => $this]);
    }

    public function loanIncipitUrl(string $token): string
    {
        return route('loan.score.incipit', ['token' => $token, 'score' => $this])
            .'?v='.($this->updated_at?->timestamp ?? 0);
    }

    public function scopePublicPreview(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('public_preview', true);
    }

    /**
     * Scope to scores a guest may reach in the public library.
     */
    public function scopePublished(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereHas(
            'publication',
            fn (\Illuminate\Database\Eloquent\Builder $publication) => $publication->approved()
        );
    }

    public function scopeMine(\Illuminate\Database\Eloquent\Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
