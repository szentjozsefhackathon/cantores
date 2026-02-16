<?php

use App\Models\Collection;
use App\Models\User;
use Livewire\Livewire;

it('requires unique title for collections', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create(['title' => 'Existing Title']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->set('title', 'Existing Title')
        ->set('abbreviation', 'ABC')
        ->call('store')
        ->assertHasErrors(['title' => 'unique']);
});

it('requires unique abbreviation for collections', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create(['abbreviation' => 'ABC']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->set('title', 'New Title')
        ->set('abbreviation', 'ABC')
        ->call('store')
        ->assertHasErrors(['abbreviation' => 'unique']);
});

it('allows duplicate abbreviation when null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create(['abbreviation' => null]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->set('title', 'New Title')
        ->set('abbreviation', null)
        ->call('store')
        ->assertHasNoErrors();
});

it('allows updating collection with same title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $collection = Collection::factory()->create([
        'title' => 'Original Title',
        'user_id' => $user->id,
    ]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('title', 'Original Title')
        ->call('update')
        ->assertHasNoErrors();
});

it('prevents updating collection with duplicate title of another', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create([
        'title' => 'Existing Title',
        'user_id' => $user->id,
    ]);
    $collection = Collection::factory()->create([
        'title' => 'Other Title',
        'user_id' => $user->id,
    ]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('title', 'Existing Title')
        ->call('update')
        ->assertHasErrors(['title' => 'unique']);
});

it('prevents updating collection with duplicate abbreviation of another', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create([
        'abbreviation' => 'ABC',
        'user_id' => $user->id,
    ]);
    $collection = Collection::factory()->create([
        'abbreviation' => 'DEF',
        'user_id' => $user->id,
    ]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('abbreviation', 'ABC')
        ->call('update')
        ->assertHasErrors(['abbreviation' => 'unique']);
});

it('shows audit log modal for collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $collection = Collection::factory()->create(['user_id' => $user->id]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('showAuditLog', $collection)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingCollection.id', $collection->id)
        ->assertCount('audits', 0);
});

it('loads audits for collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $collection = Collection::factory()->create(['user_id' => $user->id]);
    // Manually create an audit entry since auditing may be disabled in tests
    \OwenIt\Auditing\Models\Audit::create([
        'auditable_type' => $collection->getMorphClass(),
        'auditable_id' => $collection->id,
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

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('showAuditLog', $collection)
        ->assertSet('showAuditModal', true)
        ->assertSet('auditingCollection.id', $collection->id)
        ->assertCount('audits', 1);
});

it('attaches selected genres when creating a collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $genre1 = \App\Models\Genre::factory()->create(['name' => 'organist']);
    $genre2 = \App\Models\Genre::factory()->create(['name' => 'guitarist']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->set('title', 'New Collection')
        ->set('selectedGenres', [$genre1->id, $genre2->id])
        ->call('store')
        ->assertHasNoErrors();

    $collection = Collection::where('title', 'New Collection')->first();
    expect($collection)->not->toBeNull()
        ->and($collection->genres)->toHaveCount(2)
        ->and($collection->genres->pluck('id')->toArray())->toMatchArray([$genre1->id, $genre2->id]);
});

it('syncs genres when updating a collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $collection = Collection::factory()->create(['user_id' => $user->id]);
    $genre1 = \App\Models\Genre::factory()->create(['name' => 'organist']);
    $genre2 = \App\Models\Genre::factory()->create(['name' => 'guitarist']);
    $genre3 = \App\Models\Genre::factory()->create(['name' => 'other']);

    // Attach initial genres
    $collection->genres()->attach([$genre1->id, $genre2->id]);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->assertSet('selectedGenres', [$genre1->id, $genre2->id])
        ->set('selectedGenres', [$genre2->id, $genre3->id])
        ->call('update')
        ->assertHasNoErrors();

    $collection->refresh();
    expect($collection->genres)->toHaveCount(2)
        ->and($collection->genres->pluck('id')->toArray())->toMatchArray([$genre2->id, $genre3->id]);
});
