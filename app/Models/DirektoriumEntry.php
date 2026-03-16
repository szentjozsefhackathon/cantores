<?php

namespace App\Models;

use App\Services\DirektoriumMarkdownParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $direktorium_edition_id
 * @property \Carbon\CarbonImmutable $entry_date
 * @property string $markdown_text
 * @property int|null $pdf_page_start
 * @property int|null $pdf_page_end
 * @property string|null $raw_ai_response
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\DirektoriumEdition $edition
 */
class DirektoriumEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'immutable_date',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(DirektoriumEdition::class, 'direktorium_edition_id');
    }

    /**
     * @return array{
     *     celebration_title: ?string,
     *     liturgical_color: ?string,
     *     funeral_mass_code: ?string,
     *     votive_mass_code: ?string,
     *     rank_code: ?string,
     *     is_pro_populo: bool,
     *     is_penitential: bool,
     *     fast_level: int,
     *     cleaned_markdown: string,
     * }
     */
    public function parsedMarkdown(): array
    {
        return app(DirektoriumMarkdownParser::class)->parse($this->markdown_text);
    }

    /** @param Builder<DirektoriumEntry> $query */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('entry_date', $date);
    }
}
