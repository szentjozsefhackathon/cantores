<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $hostname
 * @property string $path_prefix
 * @property string $scheme
 * @property bool $allow_any_port
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $deleted_at
 * @property-read string $pattern
 *
 * @method static Builder<static>|WhitelistRule active()
 * @method static \Database\Factories\WhitelistRuleFactory factory($count = null, $state = [])
 * @method static Builder<static>|WhitelistRule forHostname(string $hostname)
 * @method static Builder<static>|WhitelistRule newModelQuery()
 * @method static Builder<static>|WhitelistRule newQuery()
 * @method static Builder<static>|WhitelistRule query()
 * @method static Builder<static>|WhitelistRule whereAllowAnyPort($value)
 * @method static Builder<static>|WhitelistRule whereCreatedAt($value)
 * @method static Builder<static>|WhitelistRule whereDeletedAt($value)
 * @method static Builder<static>|WhitelistRule whereDescription($value)
 * @method static Builder<static>|WhitelistRule whereHostname($value)
 * @method static Builder<static>|WhitelistRule whereId($value)
 * @method static Builder<static>|WhitelistRule whereIsActive($value)
 * @method static Builder<static>|WhitelistRule wherePathPrefix($value)
 * @method static Builder<static>|WhitelistRule whereScheme($value)
 * @method static Builder<static>|WhitelistRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class WhitelistRule extends Model
{
    /** @use HasFactory<\Database\Factories\WhitelistRuleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'hostname',
        'path_prefix',
        'scheme',
        'allow_any_port',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_any_port' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active rules.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by hostname.
     */
    public function scopeForHostname(Builder $query, string $hostname): void
    {
        $query->where('hostname', $hostname);
    }

    public function getPatternAttribute(): string
    {
        $portPart = $this->allow_any_port ? ':*' : '';

        return "{$this->scheme}://{$this->hostname}{$portPart}{$this->path_prefix}";
    }
}
