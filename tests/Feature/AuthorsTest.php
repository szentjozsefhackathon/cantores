<?php

use App\Models\Author;
use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

it('requires unique name for authors', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Author::factory()->create(['name' => 'Existing Author']);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('name', 'Existing Author')
        ->set('isPrivate', false)
        ->call('store')
        ->assertHasErrors(['name' => 'unique']);
});

it('creates author with name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('name', 'New Author')
        ->set('isPrivate', false)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('authors', [
        'name' => 'New Author',
        'user_id' => $user->id,
        'is_private' => false,
    ]);
});

it('creates private author', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('name', 'Private Author')
        ->set('isPrivate', true)
        ->call('store')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('authors', [
        'name' => 'Private Author',
        'is_private' => true,
    ]);
});

it('prevents deleting author with music assigned', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $author = Author::factory()->create(['user_id' => $user->id]);
    $music = Music::factory()->create(['user_id' => $user->id]);
    $author->music()->attach($music->id);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->call('delete', $author)
        ->assertDispatched('error');

    $this->assertDatabaseHas('authors', ['id' => $author->id]);
});

it('allows deleting author without assignments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $author = Author::factory()->create(['user_id' => $user->id]);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->call('delete', $author)
        ->assertDispatched('author-deleted');

    $this->assertDatabaseMissing('authors', ['id' => $author->id]);
});

it('shows audit log modal for author', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $author = Author::factory()->create(['user_id' => $user->id]);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->call('showAuditLog', $author)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingAuthor.id', $author->id)
        ->assertCount('audits', 0);
});

it('loads audits for author', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $author = Author::factory()->create(['user_id' => $user->id]);
    // Manually create an audit entry since auditing may be disabled in tests
    \OwenIt\Auditing\Models\Audit::create([
        'auditable_type' => $author->getMorphClass(),
        'auditable_id' => $author->id,
        'event' => 'updated',
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->id,
        'old_values' => [],
        'new_values' => ['name' => 'Updated Name'],
        'url' => 'http://localhost',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'tags' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->call('showAuditLog', $author)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingAuthor.id', $author->id)
        ->assertCount('audits', 1);
});

it('searches authors using full-text search', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Author::factory()->create([
        'name' => 'John Doe',
        'user_id' => $user->id,
        'is_private' => false,
    ]);
    Author::factory()->create([
        'name' => 'Jane Smith',
        'user_id' => $user->id,
        'is_private' => false,
    ]);

    // Search for "John"
    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('search', 'John')
        ->assertSee('John Doe')
        ->assertDontSee('Jane Smith');
});

it('filters authors by visibility', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $publicAuthor = Author::factory()->create(['user_id' => $otherUser->id, 'is_private' => false]);
    $privateAuthor = Author::factory()->create(['user_id' => $otherUser->id, 'is_private' => true]);
    $myPrivateAuthor = Author::factory()->create(['user_id' => $user->id, 'is_private' => true]);

    // Default filter 'visible' should show public and my private
    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->assertSee($publicAuthor->name)
        ->assertSee($myPrivateAuthor->name)
        ->assertDontSee($privateAuthor->name);

    // Filter 'public' should only show public
    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('filter', 'public')
        ->assertSee($publicAuthor->name)
        ->assertDontSee($privateAuthor->name)
        ->assertDontSee($myPrivateAuthor->name);

    // Filter 'private' should only show private (including mine)
    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('filter', 'private')
        ->assertSee($myPrivateAuthor->name)
        ->assertDontSee($privateAuthor->name)
        ->assertDontSee($publicAuthor->name);

    // Filter 'mine' should only show mine
    Livewire::test(\App\Livewire\Pages\Editor\Authors::class)
        ->set('filter', 'mine')
        ->assertSee($myPrivateAuthor->name)
        ->assertDontSee($privateAuthor->name)
        ->assertDontSee($publicAuthor->name);
});
