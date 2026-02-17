<?php

use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\User;

it('creates music verification with required fields', function () {
    $music = Music::factory()->create();
    $user = User::factory()->create();

    $verification = MusicVerification::create([
        'music_id' => $music->id,
        'verifier_id' => $user->id,
        'field_name' => 'title',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('music_verifications', [
        'id' => $verification->id,
        'music_id' => $music->id,
        'verifier_id' => $user->id,
        'field_name' => 'title',
        'status' => 'pending',
    ]);
});

it('belongs to music', function () {
    $music = Music::factory()->create();
    $verification = MusicVerification::factory()->create(['music_id' => $music->id]);

    expect($verification->music)->toBeInstanceOf(Music::class);
    expect($verification->music->id)->toBe($music->id);
});

it('belongs to verifier', function () {
    $user = User::factory()->create();
    $verification = MusicVerification::factory()->create(['verifier_id' => $user->id]);

    expect($verification->verifier)->toBeInstanceOf(User::class);
    expect($verification->verifier->id)->toBe($user->id);
});

it('has pending scope', function () {
    MusicVerification::factory()->count(3)->pending()->create();
    MusicVerification::factory()->count(2)->verified()->create();

    $pending = MusicVerification::pending()->get();
    expect($pending)->toHaveCount(3);
    expect($pending->pluck('status')->unique()->values()->toArray())->toEqual(['pending']);
});

it('has verified scope', function () {
    MusicVerification::factory()->count(3)->verified()->create();
    MusicVerification::factory()->count(2)->pending()->create();

    $verified = MusicVerification::verified()->get();
    expect($verified)->toHaveCount(3);
    expect($verified->pluck('status')->unique()->values()->toArray())->toEqual(['verified']);
});

it('has rejected scope', function () {
    MusicVerification::factory()->count(4)->rejected()->create();
    MusicVerification::factory()->count(1)->pending()->create();

    $rejected = MusicVerification::rejected()->get();
    expect($rejected)->toHaveCount(4);
    expect($rejected->pluck('status')->unique()->values()->toArray())->toEqual(['rejected']);
});

it('has forField scope', function () {
    MusicVerification::factory()->count(2)->forField('title')->create();
    MusicVerification::factory()->count(3)->forField('subtitle')->create();

    $titleVerifications = MusicVerification::forField('title')->get();
    expect($titleVerifications)->toHaveCount(2);
    expect($titleVerifications->pluck('field_name')->unique()->values()->toArray())->toEqual(['title']);
});

it('marks as verified', function () {
    $user = User::factory()->create();
    $verification = MusicVerification::factory()->pending()->create(['verifier_id' => null]);

    $verification->markAsVerified($user, 'Looks good');

    expect($verification->status)->toBe('verified');
    expect($verification->verifier_id)->toBe($user->id);
    expect($verification->notes)->toBe('Looks good');
    expect($verification->verified_at)->not->toBeNull();
});

it('marks as rejected', function () {
    $user = User::factory()->create();
    $verification = MusicVerification::factory()->pending()->create(['verifier_id' => null]);

    $verification->markAsRejected($user, 'Incorrect data');

    expect($verification->status)->toBe('rejected');
    expect($verification->verifier_id)->toBe($user->id);
    expect($verification->notes)->toBe('Incorrect data');
    expect($verification->verified_at)->not->toBeNull();
});

it('does not overwrite notes when not provided', function () {
    $user = User::factory()->create();
    $verification = MusicVerification::factory()->create([
        'notes' => 'Original note',
        'verifier_id' => null,
    ]);

    $verification->markAsVerified($user);

    expect($verification->notes)->toBe('Original note');
});

it('updates verifier only when provided', function () {
    $verification = MusicVerification::factory()->create(['verifier_id' => null]);

    $verification->markAsVerified();

    expect($verification->verifier_id)->toBeNull();
});

it('casts verified_at to datetime', function () {
    $verification = MusicVerification::factory()->create(['verified_at' => now()]);

    expect($verification->verified_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('casts pivot_reference to integer', function () {
    $verification = MusicVerification::factory()->create(['pivot_reference' => 123]);

    expect($verification->pivot_reference)->toBeInt();
});
