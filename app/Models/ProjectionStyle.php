<?php

namespace App\Models;

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectionStyleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * A slide stylesheet: how a deck is set for one screen in one church.
 *
 * A screen decides more about a slide than the score does. The huge 4:3 wall of
 * one parish and the small 16:9 television of the next want different type for
 * the same hymn, and the cantor who serves both should not have to tune every
 * score twice — least of all a score borrowed from someone else, or one sung
 * last year at the other church. So the screen's answer is written down once,
 * here, under a name, and every deck shown on that screen refers to it.
 *
 * It is a complete stylesheet: for each format, every knob a slide offers, in
 * the score's own keys and units, the face included. What it does not state is
 * the format's factory default for its ratio, so a fresh style looks exactly
 * like a deck without one.
 *
 * It does not overrule a score's author. Where a score has been laid out for
 * this ratio in the score editor, those were deliberate decisions — which line
 * goes where, how large, so that it splits where it should — and the deck shows
 * them unless the slide is told to follow the style instead. See
 * resources/js/projection-settings.js for the order the layers are read in.
 *
 * The ratio is fixed when the style is made, since every number in it was chosen
 * against that shape; a variant for another screen is a copy.
 *
 * @see \App\Observers\ProjectionStyleObserver, which carries an edit to every
 *      deck shown in the style
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property ProjectionRatio $ratio
 * @property array<string, array<string, mixed>>|null $settings
 * @property ProjectionTextTheme $text_theme
 * @property float $text_size_scale
 * @property float $text_line_height
 * @property float $min_scale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Projection> $projections
 *
 * @method static \Database\Factories\ProjectionStyleFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProjectionStyle mine(?User $user = null)
 */
class ProjectionStyle extends Model
{
    /** @use HasFactory<ProjectionStyleFactory> */
    use HasFactory;

    /** The bounds of the shrink allowance, as factors. */
    public const MIN_SCALE_FLOOR = 0.5;

    public const MIN_SCALE_CEILING = 1.0;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'ratio',
        'settings',
        'text_theme',
        'text_size_scale',
        'text_line_height',
        'min_scale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ratio' => ProjectionRatio::class,
            'settings' => 'array',
            'text_theme' => ProjectionTextTheme::class,
            'text_size_scale' => 'float',
            'text_line_height' => 'float',
            'min_scale' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projections(): HasMany
    {
        return $this->hasMany(Projection::class);
    }

    /**
     * @param  Builder<ProjectionStyle>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }

    /**
     * What a deck in this style hands the browser about it.
     *
     * The name is left out on purpose: it is a label for people, and renaming a
     * style must not have every screen engrave its deck again.
     *
     * @return array{id: int, minScale: float, settings: array<string, array<string, mixed>>}
     */
    public function geometry(): array
    {
        return [
            'id' => $this->id,
            'minScale' => $this->min_scale,
            'settings' => $this->settings ?? [],
        ];
    }

    /**
     * How the style is offered in a select: its name and the shape it is for.
     */
    public function label(): string
    {
        return $this->name.' · '.$this->ratio->label();
    }

    /**
     * A second style starting exactly where this one stands, under a free name.
     */
    public function duplicate(): self
    {
        $name = __(':name (copy)', ['name' => $this->name]);

        for ($n = 2; self::query()->where('user_id', $this->user_id)->where('name', $name)->exists(); $n++) {
            $name = __(':name (copy :n)', ['name' => $this->name, 'n' => $n]);
        }

        return self::create([
            ...$this->only(['user_id', 'ratio', 'settings', 'text_theme', 'text_size_scale', 'text_line_height', 'min_scale']),
            'name' => $name,
        ]);
    }
}
