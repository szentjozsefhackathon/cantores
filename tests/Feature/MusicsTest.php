<?php

use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

it('requires title for music', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->set('title', '')
        ->set('customId', 'ABC123')
        ->call('store')
        ->assertHasErrors(['title' => 'required']);
});

it('creates music with custom id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->set('title', 'New Music Piece')
        ->set('customId', 'BWV 232')
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('musics', [
        'title' => 'New Music Piece',
        'custom_id' => 'BWV 232',
    ]);
});

it('creates music without custom id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->set('title', 'Untitled Music')
        ->set('customId', null)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('musics', [
        'title' => 'Untitled Music',
        'custom_id' => null,
    ]);
});

it('allows updating music with same title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $music = Music::factory()->create(['title' => 'Original Title']);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->call('edit', $music)
        ->set('title', 'Original Title')
        ->set('customId', 'Updated ID')
        ->call('update')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('musics', [
        'id' => $music->id,
        'title' => 'Original Title',
        'custom_id' => 'Updated ID',
    ]);
});

it('prevents deleting music with collections assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $music = Music::factory()->create();
    $collection = \App\Models\Collection::factory()->create();
    $music->collections()->attach($collection->id);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->call('delete', $music)
        ->assertDispatched('error');

    $this->assertDatabaseHas('musics', ['id' => $music->id]);
});

it('allows deleting music without assignments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $music = Music::factory()->create();

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->call('delete', $music)
        ->assertDispatched('music-deleted');

    $this->assertDatabaseMissing('musics', ['id' => $music->id]);
});

it('shows audit log modal for music', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $music = Music::factory()->create();

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->call('showAuditLog', $music)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingMusic.id', $music->id)
        ->assertCount('audits', 0);
});

it('loads audits for music', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $music = Music::factory()->create();
    // Manually create an audit entry since auditing may be disabled in tests
    \OwenIt\Auditing\Models\Audit::create([
        'auditable_type' => $music->getMorphClass(),
        'auditable_id' => $music->id,
        'event' => 'updated',
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->id,
        'old_values' => [],
        'new_values' => ['title' => 'Updated Title'],
        'url' => 'http://localhost',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'tags' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(\App\Livewire\Pages\Editor\Musics::class)
        ->call('showAuditLog', $music)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingMusic.id', $music->id)
        ->assertCount('audits', 1);
});