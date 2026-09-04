<?php

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public string $sortBy = 'total';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('system.maintain');
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * One row per user: public/private counts per content type, kept separate
     * so the table can total "content added" without ever showing what a
     * private score or plan actually contains.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getRowsProperty(): \Illuminate\Support\Collection
    {
        $users = User::query()
            ->withCount([
                'musics as public_musics_count' => fn ($query) => $query->public(),
                'musics as private_musics_count' => fn ($query) => $query->private(),
                'authors as public_authors_count' => fn ($query) => $query->public(),
                'authors as private_authors_count' => fn ($query) => $query->private(),
                'collections as public_collections_count' => fn ($query) => $query->public(),
                'collections as private_collections_count' => fn ($query) => $query->private(),
                'scores as published_scores_count' => fn ($query) => $query->published(),
                'scores as private_scores_count' => fn ($query) => $query->whereDoesntHave(
                    'publication',
                    fn ($publication) => $publication->approved()
                ),
                'musicPlans as public_music_plans_count' => fn ($query) => $query->public(),
                'musicPlans as private_music_plans_count' => fn ($query) => $query->private(),
            ])
            ->get();

        $rows = $users->map(function (User $user): array {
            $public = $user->public_musics_count + $user->public_authors_count
                + $user->public_collections_count + $user->published_scores_count
                + $user->public_music_plans_count;

            $private = $user->private_musics_count + $user->private_authors_count
                + $user->private_collections_count + $user->private_scores_count
                + $user->private_music_plans_count;

            return [
                'id' => $user->id,
                'display_name' => $user->display_name ?: $user->name,
                'public_musics_count' => $user->public_musics_count,
                'private_musics_count' => $user->private_musics_count,
                'public_authors_count' => $user->public_authors_count,
                'private_authors_count' => $user->private_authors_count,
                'public_collections_count' => $user->public_collections_count,
                'private_collections_count' => $user->private_collections_count,
                'published_scores_count' => $user->published_scores_count,
                'private_scores_count' => $user->private_scores_count,
                'public_music_plans_count' => $user->public_music_plans_count,
                'private_music_plans_count' => $user->private_music_plans_count,
                'public' => $public,
                'private' => $private,
                'total' => $public + $private,
            ];
        });

        return $rows->sortBy($this->sortBy, SORT_REGULAR, $this->sortDirection === 'desc')->values();
    }
};
?>

<x-pages::admin.layout :heading="__('Content Statistics')" :subheading="__('How much content each nickname has added. Private scores and plans are counted, never shown.')">
    <div class="mt-5 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'display_name'" :direction="$sortDirection" wire:click="sort('display_name')">{{ __('Nickname') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'public_musics_count'" :direction="$sortDirection" wire:click="sort('public_musics_count')">{{ __('Music') }} ({{ __('Public') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'private_musics_count'" :direction="$sortDirection" wire:click="sort('private_musics_count')">{{ __('Music') }} ({{ __('Private') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'public_authors_count'" :direction="$sortDirection" wire:click="sort('public_authors_count')">{{ __('Authors') }} ({{ __('Public') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'private_authors_count'" :direction="$sortDirection" wire:click="sort('private_authors_count')">{{ __('Authors') }} ({{ __('Private') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'public_collections_count'" :direction="$sortDirection" wire:click="sort('public_collections_count')">{{ __('Collections') }} ({{ __('Public') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'private_collections_count'" :direction="$sortDirection" wire:click="sort('private_collections_count')">{{ __('Collections') }} ({{ __('Private') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'published_scores_count'" :direction="$sortDirection" wire:click="sort('published_scores_count')">{{ __('Scores') }} ({{ __('Published') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'private_scores_count'" :direction="$sortDirection" wire:click="sort('private_scores_count')">{{ __('Scores') }} ({{ __('Private') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'public_music_plans_count'" :direction="$sortDirection" wire:click="sort('public_music_plans_count')">{{ __('Music Plans') }} ({{ __('Public') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'private_music_plans_count'" :direction="$sortDirection" wire:click="sort('private_music_plans_count')">{{ __('Music Plans') }} ({{ __('Private') }})</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'total'" :direction="$sortDirection" wire:click="sort('total')">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->rows as $row)
                    <flux:table.row wire:key="user-{{ $row['id'] }}">
                        <flux:table.cell>{{ $row['display_name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['public_musics_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['private_musics_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['public_authors_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['private_authors_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['public_collections_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['private_collections_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['published_scores_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['private_scores_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['public_music_plans_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['private_music_plans_count'] }}</flux:table.cell>
                        <flux:table.cell>{{ $row['total'] }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="12" class="text-center py-8 text-gray-500">
                            {{ __('No users found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-pages::admin.layout>
