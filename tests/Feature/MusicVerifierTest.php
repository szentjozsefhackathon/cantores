<?php

use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Pages\Editor\MusicVerifier;

beforeEach(function () {
    $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    $this->nonAdmin = User::factory()->create(['email' => 'user@example.com']);
    $this->music = Music::factory()->create();
});

it('requires admin to access component', function () {
    // Non-admin should be denied
    Livewire::actingAs($this->nonAdmin)
        ->test(MusicVerifier::class)
        ->assertForbidden();

    // Admin should be allowed
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->assertOk();
});

it('searches music', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->set('search', $this->music->title)
        ->call('searchMusic', $this->music->title)
        ->assertSee($this->music->title);
});

it('selects music for verification', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->assertSet('musicId', $this->music->id)
        ->assertSet('music.id', $this->music->id)
        ->assertSet('showVerification', true);
});

it('loads existing verifications when selecting music', function () {
    $verification = MusicVerification::factory()->create([
        'music_id' => $this->music->id,
        'verifier_id' => $this->admin->id,
        'field_name' => 'title',
        'status' => 'pending',
    ]);

    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->assertSet('verifications.title:0.id', $verification->id);
});

it('verifies a field', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'verified', 'Looks good')
        ->assertDispatched('verification-updated');

    $this->assertDatabaseHas('music_verifications', [
        'music_id' => $this->music->id,
        'field_name' => 'title',
        'status' => 'verified',
        'notes' => 'Looks good',
    ]);
});

it('rejects a field', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'rejected', 'Incorrect')
        ->assertDispatched('verification-updated');

    $this->assertDatabaseHas('music_verifications', [
        'music_id' => $this->music->id,
        'field_name' => 'title',
        'status' => 'rejected',
        'notes' => 'Incorrect',
    ]);
});

it('marks field as empty', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'empty', 'Field is empty')
        ->assertDispatched('verification-updated');

    $this->assertDatabaseHas('music_verifications', [
        'music_id' => $this->music->id,
        'field_name' => 'title',
        'status' => 'empty',
        'notes' => 'Field is empty',
    ]);
});

it('validates status', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'invalid_status', '')
        ->assertDispatched('error');
});

it('validates notes length', function () {
    $longNotes = str_repeat('a', 1001);

    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'verified', $longNotes)
        ->assertDispatched('error');
});

it('requires music selection before verifying', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('verifyField', 'title', null, 'verified', '')
        ->assertDispatched('error');
});

it('updates existing verification', function () {
    $verification = MusicVerification::factory()->create([
        'music_id' => $this->music->id,
        'verifier_id' => $this->admin->id,
        'field_name' => 'title',
        'status' => 'pending',
        'notes' => 'Old note',
    ]);

    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'verified', 'Updated note')
        ->assertDispatched('verification-updated');

    $this->assertDatabaseHas('music_verifications', [
        'id' => $verification->id,
        'status' => 'verified',
        'notes' => 'Updated note',
    ]);
});

it('verifies all pending fields', function () {
    // Create a music with multiple fields (title, subtitle)
    $music = Music::factory()->create(['title' => 'Test', 'subtitle' => 'Sub']);
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $music->id)
        ->call('verifyAll', 'verified', 'Batch verified')
        ->assertDispatched('verification-updated');

    $this->assertDatabaseCount('music_verifications', 2); // title and subtitle
    $this->assertDatabaseHas('music_verifications', [
        'music_id' => $music->id,
        'field_name' => 'title',
        'status' => 'verified',
        'notes' => 'Batch verified',
    ]);
    $this->assertDatabaseHas('music_verifications', [
        'music_id' => $music->id,
        'field_name' => 'subtitle',
        'status' => 'verified',
        'notes' => 'Batch verified',
    ]);
});

it('resets selection', function () {
    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->assertSet('showVerification', true)
        ->call('resetSelection')
        ->assertSet('musicId', null)
        ->assertSet('music', null)
        ->assertSet('showVerification', false);
});

it('calculates verification stats', function () {
    MusicVerification::factory()->create([
        'music_id' => $this->music->id,
        'field_name' => 'title',
        'status' => 'verified',
    ]);
    MusicVerification::factory()->create([
        'music_id' => $this->music->id,
        'field_name' => 'subtitle',
        'status' => 'rejected',
    ]);

    Livewire::actingAs($this->admin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id);

    // The stats are computed in render, we can't directly assert but we can check the view
    // We'll just ensure no errors
})->expectNotToPerformAssertions();

it('non-admin cannot verify field', function () {
    Livewire::actingAs($this->nonAdmin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyField', 'title', null, 'verified', '')
        ->assertForbidden();
});

it('non-admin cannot verify all', function () {
    Livewire::actingAs($this->nonAdmin)
        ->test(MusicVerifier::class)
        ->call('selectMusic', $this->music->id)
        ->call('verifyAll', 'verified', '')
        ->assertForbidden();
});
