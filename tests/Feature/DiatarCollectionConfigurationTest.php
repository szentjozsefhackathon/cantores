<?php

use App\Livewire\Pages\Editor\CollectionEditModal;
use App\Models\Collection;
use App\Models\DiatarBook;
use App\Models\User;
use App\Services\Diatar\CollectionDiatarBookService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('associates several books and atomically changes the single default', function () {
    $collection = Collection::factory()->create();
    $first = DiatarBook::factory()->create();
    $second = DiatarBook::factory()->create();
    $service = app(CollectionDiatarBookService::class);

    $service->sync($collection, [$first->id, $second->id], $first->id);
    $service->sync($collection, [$first->id, $second->id], $second->id);

    expect($collection->diatarBooks()->count())->toBe(2)
        ->and($collection->diatarBooks()->wherePivot('is_default', true)->pluck('diatar_books.id')->all())
        ->toBe([$second->id]);
});

it('allows one Diatár book to be the default for several collections', function () {
    $book = DiatarBook::factory()->create();
    $collections = Collection::factory()->count(2)->create();
    $service = app(CollectionDiatarBookService::class);

    $collections->each(fn (Collection $collection) => $service->sync($collection, [$book->id], $book->id));

    expect($book->collections()->wherePivot('is_default', true)->count())->toBe(2);
});

it('rejects an unavailable book as a new default', function () {
    $collection = Collection::factory()->create();
    $book = DiatarBook::factory()->unavailable()->create();

    expect(fn () => app(CollectionDiatarBookService::class)->sync($collection, [$book->id], $book->id))
        ->toThrow(ValidationException::class);
});

it('lets an authorized collection owner save Diatár associations', function () {
    $owner = User::factory()->create();
    $collection = Collection::factory()->for($owner)->create(['is_verified' => false]);
    $book = DiatarBook::factory()->create();

    Livewire::actingAs($owner)
        ->test(CollectionEditModal::class)
        ->call('open', $collection->id)
        ->set('selectedDiatarBookIds', [$book->id])
        ->set('defaultDiatarBookId', $book->id)
        ->call('update')
        ->assertHasNoErrors();

    expect($collection->diatarBooks()->wherePivot('is_default', true)->value('diatar_books.id'))->toBe($book->id);
});
