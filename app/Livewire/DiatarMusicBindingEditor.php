<?php

namespace App\Livewire;

use App\Models\DiatarMusicBinding;
use App\Models\DiatarSong;
use App\Models\Music;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class DiatarMusicBindingEditor extends Component
{
    use AuthorizesRequests;

    public Music $music;

    public string $search = '';

    public ?int $selectedSongId = null;

    /** @var list<int> */
    public array $selectedSlideIds = [];

    public string $editorNote = '';

    public function mount(Music $music): void
    {
        $this->authorize('updateVerified', $music);
        $this->music = $music;
        $this->loadBinding();
    }

    public function selectSong(int $songId): void
    {
        $song = $this->availableSong($songId);
        $this->selectedSongId = $song->id;
        $this->selectedSlideIds = $song->slides->modelKeys();
    }

    public function addSlide(int $slideId): void
    {
        if ($this->selectedSong()?->slides->contains('id', $slideId)) {
            $this->selectedSlideIds[] = $slideId;
        }
    }

    public function removeSlide(int $index): void
    {
        array_splice($this->selectedSlideIds, $index, 1);
    }

    public function repeatSlide(int $index): void
    {
        $slideId = $this->selectedSlideIds[$index] ?? null;
        if ($slideId !== null) {
            array_splice($this->selectedSlideIds, $index + 1, 0, [$slideId]);
        }
    }

    public function moveSlide(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->selectedSlideIds[$index]) || $target < 0 || $target >= count($this->selectedSlideIds)) {
            return;
        }

        [$this->selectedSlideIds[$index], $this->selectedSlideIds[$target]] = [$this->selectedSlideIds[$target], $this->selectedSlideIds[$index]];
    }

    public function save(): void
    {
        $this->authorize('updateVerified', $this->music);
        $validated = $this->validate([
            'selectedSongId' => ['required', 'integer', Rule::exists('diatar_songs', 'id')],
            'selectedSlideIds' => ['required', 'array', 'min:1'],
            'selectedSlideIds.*' => ['integer', Rule::exists('diatar_slides', 'id')],
            'editorNote' => ['nullable', 'string', 'max:500'],
        ]);
        $song = $this->availableSong((int) $validated['selectedSongId']);
        $allowedSlideIds = $song->slides->modelKeys();

        if (collect($validated['selectedSlideIds'])->contains(fn (int $id): bool => ! in_array($id, $allowedSlideIds, true))) {
            $this->addError('selectedSlideIds', __('Every selected slide must belong to the selected Diatár song.'));

            return;
        }

        DB::transaction(function () use ($song, $validated): void {
            $this->music->diatarBindings()->where('is_active', true)->update(['is_active' => false]);
            $binding = $this->music->diatarBindings()->updateOrCreate(
                ['diatar_song_id' => $song->id],
                [
                    'is_active' => true,
                    'editor_note' => $validated['editorNote'] ?: null,
                ],
            );
            $binding->slides()->delete();

            if ($validated['selectedSlideIds'] !== $song->slides->modelKeys()) {
                foreach ($validated['selectedSlideIds'] as $index => $slideId) {
                    $binding->slides()->create([
                        'diatar_slide_id' => $slideId,
                        'sequence' => $index + 1,
                    ]);
                }
            }
        }, attempts: 3);

        $this->dispatch('toast', message: __('Exceptional Diatár binding saved.'), type: 'success');
        $this->loadBinding();
    }

    public function clear(): void
    {
        $this->authorize('updateVerified', $this->music);
        $this->music->diatarBindings()->where('is_active', true)->update(['is_active' => false]);
        $this->reset(['selectedSongId', 'selectedSlideIds', 'editorNote', 'search']);
        $this->dispatch('toast', message: __('Exceptional Diatár binding disabled.'), type: 'success');
    }

    public function selectedSong(): ?DiatarSong
    {
        if ($this->selectedSongId === null) {
            return null;
        }

        return $this->searchResults()->firstWhere('id', $this->selectedSongId)
            ?? DiatarSong::query()->with(['book', 'slides' => fn ($query) => $query->exportable()])->find($this->selectedSongId);
    }

    public function searchResults(): Collection
    {
        return DiatarSong::query()
            ->with(['book', 'slides' => fn ($query) => $query->exportable()])
            ->available()
            ->whereHas('book', fn ($query) => $query->available())
            ->whereHas('slides', fn ($query) => $query->exportable())
            ->when($this->search !== '', function ($query): void {
                $search = '%'.$this->search.'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'ilike', $search)
                        ->orWhere('reference', 'ilike', $search)
                        ->orWhereHas('book', fn ($bookQuery) => $bookQuery
                            ->where('title', 'ilike', $search)
                            ->orWhere('source_path', 'ilike', $search));
                });
            })
            ->orderBy('title')
            ->limit(30)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.diatar-music-binding-editor', [
            'songs' => $this->searchResults(),
            'selectedSong' => $this->selectedSong(),
        ]);
    }

    private function loadBinding(): void
    {
        $binding = $this->music->diatarBindings()
            ->where('is_active', true)
            ->with(['song.slides' => fn ($query) => $query->exportable(), 'slides'])
            ->first();

        if (! $binding instanceof DiatarMusicBinding) {
            return;
        }

        $this->selectedSongId = $binding->diatar_song_id;
        $this->selectedSlideIds = $binding->slides->isNotEmpty()
            ? $binding->slides->pluck('diatar_slide_id')->all()
            : $binding->song->slides->modelKeys();
        $this->editorNote = $binding->editor_note ?? '';
    }

    private function availableSong(int $songId): DiatarSong
    {
        return DiatarSong::query()
            ->with(['book', 'slides' => fn ($query) => $query->exportable()])
            ->available()
            ->whereHas('book', fn ($query) => $query->available())
            ->whereHas('slides', fn ($query) => $query->exportable())
            ->findOrFail($songId);
    }
}
