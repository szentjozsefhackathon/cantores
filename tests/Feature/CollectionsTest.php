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

    $collection = Collection::factory()->create(['title' => 'Original Title']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('title', 'Original Title')
        ->call('update')
        ->assertHasNoErrors();
});

it('prevents updating collection with duplicate title of another', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create(['title' => 'Existing Title']);
    $collection = Collection::factory()->create(['title' => 'Other Title']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('title', 'Existing Title')
        ->call('update')
        ->assertHasErrors(['title' => 'unique']);
});

it('prevents updating collection with duplicate abbreviation of another', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Collection::factory()->create(['abbreviation' => 'ABC']);
    $collection = Collection::factory()->create(['abbreviation' => 'DEF']);

    Livewire::test(\App\Livewire\Pages\Editor\Collections::class)
        ->call('edit', $collection)
        ->set('abbreviation', 'ABC')
        ->call('update')
        ->assertHasErrors(['abbreviation' => 'unique']);
});
