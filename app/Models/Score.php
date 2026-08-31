<?php

namespace App\Models;

use App\Concerns\HasShares;
use App\Enums\ScoreFormat;
use App\Services\ScoreFileStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $music_id
 * @property string $title
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

    use HasShares;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'music_id',
        'title',
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
     * The database drops score_files rows by cascade, which never reaches the
     * encrypted artifacts on disk — so they are removed here, before the row
     * that names them is gone.
     */
    protected static function booted(): void
    {
        static::deleting(function (Score $score): void {
            $storage = app(ScoreFileStorage::class);

            foreach ($score->files()->get() as $file) {
                $storage->deleteAll($file);
            }
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
     * Every uploaded file, oldest first, so a listing keeps a stable order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    public function orderedFiles(): Collection
    {
        return $this->relationLoaded('files')
            ? $this->files->sortBy('id')->values()
            : $this->files()->orderBy('id')->get();
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
     * The read-only page for this score as reached through the given grant.
     */
    public function shareUrl(string $token): string
    {
        return route('share.score', ['token' => $token, 'score' => $this]);
    }

    public function shareIncipitUrl(string $token): string
    {
        return route('share.score.incipit', ['token' => $token, 'score' => $this])
            .'?v='.($this->updated_at?->timestamp ?? 0);
    }

    public function scopePublicPreview(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('public_preview', true);
    }

    public function scopeMine(\Illuminate\Database\Eloquent\Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
