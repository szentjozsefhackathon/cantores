<?php

namespace App\Models;

use App\Support\DeviceDescription;
use Carbon\CarbonImmutable;
use Database\Factories\DevicePairingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One borrowed screen, signed in from a phone that was trusted already.
 *
 * A row lives two lives. Before `claimed_at` it is an invitation: a token good
 * for a couple of minutes, rotated in place for as long as nobody scans it, and
 * spendable exactly once. After `claimed_at` it is a signed-in device, and the
 * only record by which a phone can end a session on a laptop it has walked away
 * from.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $token
 * @property string $confirmation_code
 * @property string $requesting_session_id
 * @property string|null $session_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $scanned_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Database\Factories\DevicePairingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DevicePairing liveDevices()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DevicePairing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DevicePairing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DevicePairing query()
 *
 * @mixin \Eloquent
 */
class DevicePairing extends Model
{
    /** @use HasFactory<DevicePairingFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 32;

    /**
     * Where the borrowed screen remembers the invitation it is waiting on.
     *
     * In its own session, not in the URL: the session cookie is what makes a
     * browser that browser, so a token read off the screen by somebody else
     * buys them nothing.
     */
    public const PENDING_SESSION_KEY = 'qr_pending_pairing_id';

    /**
     * Where it remembers, afterwards, which device it is.
     */
    public const DEVICE_SESSION_KEY = 'qr_pairing_id';

    /**
     * How long an unscanned code is worth showing. Short, because it is on a
     * screen anyone in the room can photograph, and free to replace.
     */
    public const TOKEN_MINUTES = 2;

    /**
     * How long a scanned code waits for the tap that approves it.
     */
    public const SCANNED_MINUTES = 5;

    /**
     * The alphabet of the confirmation code, minus the characters that are read
     * wrongly off a screen across a room: O and 0, I and 1, S and 5.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'confirmation_code',
        'requesting_session_id',
        'session_id',
        'ip_address',
        'user_agent',
        'expires_at',
        'scanned_at',
        'approved_at',
        'claimed_at',
        'revoked_at',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'scanned_at' => 'datetime',
            'approved_at' => 'datetime',
            'claimed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Whoever approved the pairing. Null while it is still an invitation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a token that no existing pairing uses.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * The four characters shown under the QR and read back off the other screen.
     */
    public static function generateConfirmationCode(): string
    {
        $alphabet = self::CODE_ALPHABET;

        return collect(range(1, 4))
            ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Still an invitation: nobody has approved it, spent it or called it off.
     */
    public function isPending(): bool
    {
        return $this->approved_at === null
            && $this->claimed_at === null
            && $this->revoked_at === null;
    }

    /**
     * Waiting for the laptop to come and collect the session it was granted.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null
            && $this->claimed_at === null
            && $this->revoked_at === null;
    }

    /**
     * A device that is signed in right now.
     */
    public function isLiveDevice(): bool
    {
        return $this->claimed_at !== null && $this->revoked_at === null;
    }

    /**
     * Replace the code with a new one, on the same row.
     *
     * One laptop waiting for someone to arrive is one row however long it waits;
     * minting a fresh row every two minutes would leave the table to be swept up
     * after a screen that was simply left on.
     */
    public function rotate(): void
    {
        $this->forceFill([
            'token' => self::generateToken(),
            'confirmation_code' => self::generateConfirmationCode(),
            'expires_at' => Carbon::now()->addMinutes(self::TOKEN_MINUTES),
        ])->save();
    }

    /**
     * Record that a phone has the code open, and stop it rotating away from
     * under them.
     */
    public function markScanned(): void
    {
        $this->forceFill([
            'scanned_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(self::SCANNED_MINUTES),
        ])->save();
    }

    /**
     * Grant this pairing the account signed in on the phone.
     */
    public function approveFor(User $user): void
    {
        $this->forceFill([
            'user_id' => $user->id,
            'approved_at' => Carbon::now(),
        ])->save();
    }

    /**
     * End the pairing: the invitation is called off, or the device is signed out.
     */
    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => Carbon::now()])->save();
    }

    /**
     * Note that the paired device is still being used, without dirtying
     * `updated_at` — this runs on requests the device did not ask us to record.
     */
    public function touchLastSeen(): void
    {
        $now = Carbon::now();

        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['last_seen_at' => $now]);

        $this->setAttribute('last_seen_at', $now)->syncOriginalAttribute('last_seen_at');
    }

    /**
     * Something a person can recognise their own laptop by.
     *
     * Shared with Screen, which asks the same question of the same string: the
     * phone listing signed-in devices and the phone choosing which screen to
     * drive are the same glance at the same laptop.
     */
    public function describeDevice(): string
    {
        return DeviceDescription::of($this->user_agent);
    }

    /**
     * Scope to devices that are signed in right now.
     *
     * @param  Builder<DevicePairing>  $query
     */
    public function scopeLiveDevices(Builder $query): void
    {
        $query->whereNotNull('claimed_at')->whereNull('revoked_at');
    }
}
