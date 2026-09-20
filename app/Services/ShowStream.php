<?php

namespace App\Services;

use App\Http\Middleware\NotModifiedWhenUnchanged;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Tells a person's devices where their show has got to, the moment it moves.
 *
 * It used to say only that something had changed, because what a wall and a
 * remote were shown was worked out per device and none of it could be said
 * once for everyone. It can be now: the show is described for a *person*, and
 * the two device-shaped questions in it — which screen is this one, and which
 * of them may this one see — are answered in the browser out of the flags each
 * screen carries. So the answer itself travels, and a press of the space bar
 * costs one publish instead of a read from every device that was listening.
 *
 * The read stays the authority all the same. A frame can be lost, late or
 * never delivered, and some of what the answer says moves with a clock rather
 * than with a save — a wall that stopped being heard from — so every device
 * goes on asking, just far less often. Nothing here is ever the only way a
 * device finds something out.
 *
 * Publishing is deferred to after the response and named per person, so a
 * deck edit that saves twenty rows is one message and not twenty, and a hub
 * that is down costs a log line rather than the request that tried to use it.
 *
 * @see resources/js/projection-follow.js showStream()
 */
class ShowStream
{
    /** Where the hub is, as a browser on this site reaches it. */
    public const HUB_PATH = '/.well-known/mercure';

    /** The cookie the hub reads a subscriber's token from, by Mercure's own name. */
    public const COOKIE = 'mercureAuthorization';

    /**
     * How long a subscription is good for.
     *
     * Short, because it outlives a sign-out: a device that loses its session
     * keeps hearing "something changed" until the token lapses. It carries
     * nothing else, and a wall that is still signed in simply asks for another
     * one when the hub turns it away.
     */
    public const TOKEN_MINUTES = 60;

    /** How long a publish may take before it is given up on. The wall still polls. */
    private const PUBLISH_TIMEOUT_SECONDS = 2;

    public function enabled(): bool
    {
        return filled(config('services.mercure.publish_url'))
            && filled(config('services.mercure.publisher_jwt_key'))
            && filled(config('services.mercure.subscriber_jwt_key'));
    }

    /**
     * The topic one person's devices listen on.
     */
    public function topicFor(User|int $user): string
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return rtrim((string) config('app.url'), '/')."/show/{$userId}";
    }

    /**
     * Something in this person's show has moved.
     */
    public function changedFor(?int $userId): void
    {
        if ($userId === null || ! $this->enabled()) {
            return;
        }

        defer(fn () => $this->publish($userId), "show-stream.user.{$userId}");
    }

    /** Republish a nudge without changing the desired show. */
    public function resync(Presentation $presentation): void
    {
        Log::info('Projection screen resync requested.', [
            'presentation_id' => $presentation->id,
            'version' => $presentation->version,
        ]);

        $this->changedFor($presentation->user_id);
    }

    /**
     * A deck was edited: whoever is showing it now has to read it again.
     *
     * Found when the request is over rather than now, so that the rows saved by
     * one edit ask the question once.
     */
    public function deckChanged(?int $projectionId): void
    {
        if ($projectionId === null || ! $this->enabled()) {
            return;
        }

        defer(function () use ($projectionId): void {
            Presentation::query()
                ->live()
                ->where('projection_id', $projectionId)
                ->distinct()
                ->pluck('user_id')
                ->each(fn (int $userId) => $this->publish($userId));
        }, "show-stream.deck.{$projectionId}");
    }

    /**
     * The token a person's browser subscribes with: their own topic, and only
     * that.
     */
    public function subscriberToken(User $user): string
    {
        return $this->jwt([
            'mercure' => ['subscribe' => [$this->topicFor($user)]],
            'exp' => now()->addMinutes(self::TOKEN_MINUTES)->getTimestamp(),
        ], (string) config('services.mercure.subscriber_jwt_key'));
    }

    private function publish(int $userId): void
    {
        try {
            $user = User::query()->find($userId);

            if (! $user instanceof User) {
                return;
            }

            Http::asForm()
                ->withToken($this->jwt(
                    ['mercure' => ['publish' => [$this->topicFor($userId)]]],
                    (string) config('services.mercure.publisher_jwt_key'),
                ))
                ->timeout(self::PUBLISH_TIMEOUT_SECONDS)
                ->post((string) config('services.mercure.publish_url'), [
                    'topic' => $this->topicFor($userId),
                    'data' => $this->frame($user),
                    'private' => 'on',
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('Mercure publish failed; devices fall back to polling.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * The show as it stands, wrapped in the one thing a pushed copy needs that
     * a fetched one does not: when it was said.
     *
     * Two frames can overtake each other between here and a phone, and a device
     * that drew the later one must not then draw the earlier. A poll needs no
     * such stamp — its answer is a fresh read by definition — and must not have
     * one, because the polled body is compared with its own last version to
     * decide whether to send it at all, and a clock in it would make every
     * quiet poll a full answer.
     *
     * @see NotModifiedWhenUnchanged
     */
    private function frame(User $user): string
    {
        return json_encode([
            'at' => now()->format('YmdHisu'),
            'show' => app(ShowState::class)->forUser($user),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * An HS256 token, which is all the hub needs and not worth a dependency.
     *
     * @param  array<string, mixed>  $claims
     */
    private function jwt(array $claims, string $key): string
    {
        $encode = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $unsigned = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
            .'.'.$encode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $unsigned.'.'.$encode(hash_hmac('sha256', $unsigned, $key, true));
    }
}
