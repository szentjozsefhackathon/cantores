<?php

use App\Models\Author;
use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\Notification;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['content.edit.published', 'content.edit.own', 'content.edit.verified'] as $permission) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }
    $this->actingAs($this->user);

    $this->left = Music::factory()->create(['title' => 'Left', 'user_id' => $this->user->id]);
    $this->right = Music::factory()->create(['title' => 'Right', 'user_id' => $this->user->id]);
});

function performMerge(Music $left, Music $right): void
{
    Livewire::test('editor.music-merger')
        ->call('assignToLeftMusic', $left->id)
        ->call('assignToRightMusic', $right->id)
        ->call('saveMerge')
        ->assertRedirect(route('music-editor', ['music' => $left->id]));
}

it('repoints all scores from the deleted music onto the survivor', function () {
    $rightScore = Score::factory()->create(['music_id' => $this->right->id, 'user_id' => $this->user->id]);
    $leftScore = Score::factory()->create(['music_id' => $this->left->id, 'user_id' => $this->user->id]);

    performMerge($this->left, $this->right);

    expect($rightScore->fresh()->music_id)->toBe($this->left->id)
        ->and($leftScore->fresh()->music_id)->toBe($this->left->id);
    $this->assertDatabaseMissing('musics', ['id' => $this->right->id]);
});

it('unions authors from the deleted music without duplicating shared authors', function () {
    $sharedAuthor = Author::factory()->create();
    $rightOnlyAuthor = Author::factory()->create();

    $this->left->authors()->attach($sharedAuthor, ['user_id' => $this->user->id]);
    $this->right->authors()->attach($sharedAuthor, ['user_id' => $this->user->id]);
    $this->right->authors()->attach($rightOnlyAuthor, ['user_id' => $this->user->id]);

    performMerge($this->left, $this->right);

    $authorIds = $this->left->fresh()->authors->pluck('id')->sort()->values()->all();
    expect($authorIds)->toEqual(collect([$sharedAuthor->id, $rightOnlyAuthor->id])->sort()->values()->all());
});

it('repoints verifications from the deleted music onto the survivor', function () {
    $verification = MusicVerification::factory()->pending()->create(['music_id' => $this->right->id]);

    performMerge($this->left, $this->right);

    expect($verification->fresh()->music_id)->toBe($this->left->id);
});

it('repoints notifications that point at the deleted music', function () {
    $notification = Notification::factory()->forMusic($this->right)->create();

    performMerge($this->left, $this->right);

    expect($notification->fresh()->notifiable_id)->toBe($this->left->id)
        ->and($notification->fresh()->notifiable_type)->toBe(Music::class);
});

it('repoints audit history from the deleted music onto the survivor', function () {
    $audit = Audit::create([
        'auditable_type' => Music::class,
        'auditable_id' => $this->right->id,
        'event' => 'updated',
        'old_values' => ['title' => 'Old'],
        'new_values' => ['title' => 'Right'],
    ]);

    performMerge($this->left, $this->right);

    expect($audit->fresh()->auditable_id)->toBe($this->left->id);
});
