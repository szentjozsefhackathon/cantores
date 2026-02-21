<?php

namespace App\Livewire\Components;

use App\Models\Music;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MusicTagSelector extends Component
{
    public Music $music;

    public ?int $selectedTagId = null;

    /**
     * Mount the component.
     */
    public function mount(Music $music): void
    {
        $this->music = $music->load('tags');
    }

    /**
     * Get all available tags.
     */
    public function availableTags()
    {
        return MusicTag::whereNotIn('id', $this->music->tags->pluck('id'))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Add a tag to the music.
     */
    public function addTag(): void
    {
        if (! $this->selectedTagId) {
            return;
        }

        $tag = MusicTag::find($this->selectedTagId);

        if (! $tag || $this->music->tags()->where('music_tag_id', $this->selectedTagId)->exists()) {
            return;
        }

        $this->music->tags()->attach($this->selectedTagId);
        $this->music->load('tags');

        $this->selectedTagId = null;

        $this->dispatch('tag-added');
    }

    /**
     * Remove a tag from the music.
     */
    public function removeTag(int $tagId): void
    {
        $this->music->tags()->detach($tagId);
        $this->music->load('tags');

        $this->dispatch('tag-removed');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('components.music-tag-selector', [
            'availableTags' => $this->availableTags(),
        ]);
    }
}
