<?php

namespace App\Services\Diatar;

use App\Models\Collection;
use App\Models\DiatarBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionDiatarBookService
{
    /**
     * @param  list<int>  $bookIds
     */
    public function sync(Collection $collection, array $bookIds, ?int $defaultBookId): void
    {
        $bookIds = collect($bookIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($defaultBookId !== null && ! $bookIds->contains($defaultBookId)) {
            throw ValidationException::withMessages([
                'defaultDiatarBookId' => __('The default Diatár source must also be associated with the collection.'),
            ]);
        }

        $books = DiatarBook::query()->whereKey($bookIds)->get()->keyBy('id');
        if ($books->count() !== $bookIds->count()) {
            throw ValidationException::withMessages([
                'selectedDiatarBookIds' => __('One of the selected Diatár sources no longer exists.'),
            ]);
        }

        if ($defaultBookId !== null && ! $books->get($defaultBookId)?->available) {
            throw ValidationException::withMessages([
                'defaultDiatarBookId' => __('An unavailable Diatár source cannot be selected as the default.'),
            ]);
        }

        DB::transaction(function () use ($collection, $bookIds, $defaultBookId): void {
            Collection::query()->whereKey($collection->getKey())->lockForUpdate()->firstOrFail();

            $collection->diatarBooks()->sync($bookIds->mapWithKeys(fn (int $bookId): array => [
                $bookId => ['is_default' => $bookId === $defaultBookId],
            ])->all());
        }, attempts: 3);
    }
}
