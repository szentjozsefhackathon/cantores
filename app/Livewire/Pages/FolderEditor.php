<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Score;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class FolderEditor extends Component
{
    use AuthorizesRequests;

    public ?Folder $folder = null;

    public string $name = '';

    public ?string $secretLinkUrl = null;

    /** @var array<int> */
    public array $scoreIds = [];

    public bool $showModal = false;

    public string $modalSearch = '';

    public int $modalPage = 1;

    public function mount(?Folder $folder = null): void
    {
        if ($folder instanceof Folder) {
            $this->authorize('update', $folder);
            $this->folder = $folder;
            $this->name = $folder->name;
            $this->secretLinkUrl = $folder->share_token !== null
                ? route('folder.share', ['token' => $folder->share_token])
                : null;
            $this->scoreIds = $folder->scores()->pluck('scores.id')->toArray();
        } else {
            $this->authorize('create', Folder::class);
        }
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => $this->folder instanceof Folder ? __('Edit Folder') : __('Create Folder'),
        ]);
    }

    public function save(): void
    {
        $this->authorize($this->folder ? 'update' : 'create', $this->folder ?? Folder::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folder = $this->folder ?? new Folder(['user_id' => Auth::id()]);
        $folder->fill([
            'name' => $validated['name'],
        ]);
        $folder->user_id = $folder->user_id ?: Auth::id();
        $folder->save();

        $folder->scores()->sync($this->scoreIds);

        $this->folder = $folder;

        $this->redirectRoute('folders.edit', ['folder' => $folder->id], navigate: true);
    }

    public function updatedModalSearch(): void
    {
        $this->modalPage = 1;
    }

    public function previousModalPage(): void
    {
        if ($this->modalPage > 1) {
            $this->modalPage--;
        }
    }

    public function nextModalPage(int $lastPage): void
    {
        if ($this->modalPage < $lastPage) {
            $this->modalPage++;
        }
    }

    #[Renderless]
    public function generateSecretLink(): void
    {
        abort_unless($this->folder instanceof Folder, 404);
        $this->authorize('update', $this->folder);

        do {
            $token = Str::random(32);
        } while (Folder::query()->where('share_token', $token)->exists());

        $this->folder->share_token = $token;
        $this->folder->save();

        $this->secretLinkUrl = route('folder.share', ['token' => $token]);
    }

    #[Renderless]
    public function deleteSecretLink(): void
    {
        abort_unless($this->folder instanceof Folder, 404);
        $this->authorize('update', $this->folder);

        $this->folder->share_token = null;
        $this->folder->save();

        $this->secretLinkUrl = null;
    }

    public function toggleScore(int $scoreId): void
    {
        abort_unless($this->folder instanceof Folder, 404);
        $this->authorize('update', $this->folder);

        Score::query()->mine(Auth::user())->findOrFail($scoreId);

        if (in_array($scoreId, $this->scoreIds, true)) {
            $this->scoreIds = array_values(array_filter($this->scoreIds, fn ($id) => $id !== $scoreId));
        } else {
            $this->scoreIds[] = $scoreId;
        }

        $this->folder->scores()->sync($this->scoreIds);
    }

    public function delete(): void
    {
        abort_unless($this->folder instanceof Folder, 404);
        $this->authorize('delete', $this->folder);

        $this->folder->delete();

        $this->redirectRoute('folders', navigate: true);
    }

    public function render(): IlluminateView
    {
        $addedScores = collect();
        $modalScores = collect();

        if ($this->folder instanceof Folder) {
            if (! empty($this->scoreIds)) {
                $addedScores = Score::query()
                    ->mine(Auth::user())
                    ->with(['music'])
                    ->whereIn('id', $this->scoreIds)
                    ->orderBy('title')
                    ->get();
            }

            $modalScores = Score::query()
                ->mine(Auth::user())
                ->with(['music'])
                ->when($this->modalSearch !== '', function ($q) {
                    $q->where(function ($q) {
                        $q->where('title', 'ilike', "%{$this->modalSearch}%")
                            ->orWhereHas('music', fn ($q) => $q->where('title', 'ilike', "%{$this->modalSearch}%"));
                    });
                })
                ->orderBy('title')
                ->paginate(8, ['*'], 'modal_page', $this->modalPage);
        }

        return view('livewire.pages.folder-editor', [
            'addedScores' => $addedScores,
            'modalScores' => $modalScores,
        ]);
    }
}
