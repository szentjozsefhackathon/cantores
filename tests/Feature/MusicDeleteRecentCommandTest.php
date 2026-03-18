<?php

use App\Models\Music;
use App\Models\User;

use function Pest\Laravel\artisan;

it('rejects invalid minutes values', function () {
    $user = User::factory()->create();

    artisan('cantores:music-delete-recent', ['minutes' => 10, 'user' => $user->email])
        ->expectsOutput('Invalid minutes value. Allowed values are: 1, 5, 60.')
        ->assertFailed();
});

it('reports no records when none exist in timeframe for the given user', function () {
    $user = User::factory()->create();
    Music::factory()->create(['created_at' => now()->subHours(2), 'user_id' => $user->id]);

    artisan('cantores:music-delete-recent', ['minutes' => 1, 'user' => $user->email, '--force' => true])
        ->expectsOutput('No music records found in the specified time range.')
        ->assertSuccessful();
});

it('deletes music created within the given minutes', function () {
    $user = User::factory()->create();
    $recent = Music::factory()->create(['created_at' => now()->subSeconds(30), 'user_id' => $user->id]);
    $old = Music::factory()->create(['created_at' => now()->subHours(2), 'user_id' => $user->id]);

    artisan('cantores:music-delete-recent', ['minutes' => 1, 'user' => $user->email, '--force' => true])
        ->assertSuccessful();

    expect(Music::find($recent->id))->toBeNull();
    expect(Music::find($old->id))->not->toBeNull();
});

it('dry run does not delete records', function () {
    $user = User::factory()->create();
    Music::factory()->create(['created_at' => now()->subSeconds(30), 'user_id' => $user->id]);

    artisan('cantores:music-delete-recent', ['minutes' => 1, 'user' => $user->email, '--dry-run' => true])
        ->expectsOutputToContain('[DRY RUN]')
        ->assertSuccessful();

    expect(Music::where('user_id', $user->id)->count())->toBe(1);
});

it('only deletes music belonging to the specified user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $userMusic = Music::factory()->create(['created_at' => now()->subSeconds(30), 'user_id' => $user->id]);
    $otherMusic = Music::factory()->create(['created_at' => now()->subSeconds(30), 'user_id' => $other->id]);

    artisan('cantores:music-delete-recent', ['minutes' => 1, 'user' => $user->email, '--force' => true])
        ->assertSuccessful();

    expect(Music::find($userMusic->id))->toBeNull();
    expect(Music::find($otherMusic->id))->not->toBeNull();
});

it('returns failure when user is not found', function () {
    artisan('cantores:music-delete-recent', ['minutes' => 1, 'user' => 'nonexistent@example.com'])
        ->expectsOutput('User not found: nonexistent@example.com')
        ->assertFailed();
});
