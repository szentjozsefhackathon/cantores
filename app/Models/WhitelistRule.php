<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class WhitelistRule extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\WhitelistRuleFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

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
