<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeviceNameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one person calls one of their devices.
 *
 * The name belongs to the pair, not to the machine, which is why this is a row
 * of its own and not a column on `screens`: a screen is a browser that is here
 * now and goes stale five minutes after a laptop is shut, while the name has to
 * survive that, survive the session rotating between Sundays, and be settable
 * from the phone without anyone walking over to the laptop.
 *
 * It carries the other thing a person knows about their own devices and no
 * inference does: whether this one is ever to be offered as a screen. The laptop
 * at home is the same account, the same `Edge on Windows`, and genuinely live
 * while its tab is open. Asked once, it is right forever.
 *
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property string|null $name
 * @property bool $offered
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 *
 * @method static \Database\Factories\DeviceNameFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceName newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceName newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceName query()
 *
 * @mixin \Eloquent
 */
class DeviceName extends Model
{
    /** @use HasFactory<DeviceNameFactory> */
    use HasFactory;

    /**
     * Long enough for "Parish laptop" and short enough that a dropdown row keeps
     * its shape. The column agrees, so a name that would be cut is refused
     * rather than silently trimmed.
     */
    public const MAX_LENGTH = 40;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'name',
        'offered',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The row for this person and this device, made if it is not there yet.
     *
     * Made rather than found, because both things it holds are written by
     * someone deciding something — naming a device, or saying it is not a
     * screen — and neither is worth a separate existence check at every caller.
     */
    public static function forDevice(User $user, string $deviceId): self
    {
        return self::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'device_id' => $deviceId,
        ]);
    }
}
