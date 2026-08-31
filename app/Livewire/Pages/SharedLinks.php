<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\Share;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every secret link the user has handed out, and a way to take one back.
 *
 * Folder and plan sharing used to mint permanent tokens on the scores underneath,
 * which nothing listed and nothing could revoke. Those grants were carried over when
 * links moved into the `shares` table, so this screen is where they become visible.
 */
class SharedLinks extends Component
{
    use AuthorizesRequests, WithPagination;

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('My Secret Links'),
        ]);
    }

    public function revoke(int $shareId): void
    {
        $share = Share::query()->mine(Auth::user())->findOrFail($shareId);

        $share->revoke();

        $this->dispatch('toast', message: __('Secret link revoked.'), type: 'success');
    }

    /**
     * A label and edit URL for whatever a grant points at.
     *
     * @return array{type: string, title: string, url: string|null}
     */
    public function describe(Share $share): array
    {
        $shareable = $share->shareable;

        return match (true) {
            $shareable instanceof Score => [
                'type' => __('Score'),
                'title' => $shareable->title,
                'url' => route('scores.edit', ['score' => $shareable->id]),
            ],
            $shareable instanceof Folder => [
                'type' => __('Folder'),
                'title' => $shareable->name,
                'url' => route('folders.edit', ['folder' => $shareable->id]),
            ],
            $shareable instanceof MusicPlan => [
                'type' => __('Music Plan'),
                'title' => $shareable->celebration_name ?? __('Music Plan'),
                'url' => null,
            ],
            default => ['type' => __('Unknown'), 'title' => __('Deleted'), 'url' => null],
        };
    }

    /**
     * The public URL a grant resolves to.
     */
    public function linkFor(Share $share): string
    {
        return match (true) {
            $share->shareable instanceof Folder => route('folder.share', ['token' => $share->token]),
            $share->shareable instanceof MusicPlan => route('music-plan.share', ['token' => $share->token]),
            default => route('score.share', ['token' => $share->token]),
        };
    }

    public function render(): IlluminateView
    {
        $shares = Share::query()
            ->mine(Auth::user())
            ->live()
            ->with('shareable')
            ->latest('id')
            ->paginate(15);

        return view('livewire.pages.shared-links', [
            'shares' => $shares,
        ]);
    }
}
