<?php

namespace App\Livewire\Pages;

use App\Enums\ScoreFormat;
use App\Models\Folder;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScoreUrl;
use App\MusicUrlLabel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class ScoreEditor extends Component
{
    use AuthorizesRequests;

    public ?Score $score = null;

    public ?int $musicId = null;

    public string $title = '';

    public string $format = 'abc';

    public string $content = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    public bool $publicPreview = false;

    public bool $linksOnly = false;

    /** @var array<int, array{url: string, label: ?string, comment: ?string}> */
    public array $pendingUrls = [];

    public bool $isSharedLink = false;

    public ?string $secretLinkUrl = null;

    /** @var array<int> */
    public array $folderIds = [];

    public string $newUrl = '';

    public ?string $newUrlLabel = null;

    public string $newUrlComment = '';

    public function mount(mixed $score = null, mixed $music = null): void
    {
        $d = (string) request()->query('d', '');

        if ($d !== '') {
            $this->isSharedLink = true;
            $this->loadFromSharedData($d);
            if (Auth::check()) {
                $this->authorize('create', Score::class);
            }

            return;
        }

        if ($score instanceof Score) {
            $this->authorize('update', $score);
            $this->score = $score->load('music');
            $this->musicId = $score->music_id;
            $this->title = $score->title;
            $this->linksOnly = $score->format === null;
            $this->format = $score->format?->value ?? 'abc';
            $this->content = $score->content ?? '';
            $this->settings = $score->settings ?? [];
            $this->publicPreview = (bool) $score->public_preview;
            $this->secretLinkUrl = $score->share_token !== null
                ? route('score.share', ['token' => $score->share_token])
                : null;
            $this->folderIds = $score->folders()->pluck('folder_id')->toArray();

            return;
        }

        if (! Auth::check()) {
            return;
        }

        $this->authorize('create', Score::class);

        if (is_numeric($music)) {
            $music = Music::query()->find((int) $music);
        }

        if ($music instanceof Music) {
            abort_unless(Gate::allows('view', $music), 403);

            $this->musicId = $music->id;
            $this->title = $music->title;
        }
    }

    public function rendering(IlluminateView $view): void
    {
        $isGuest = ! Auth::check();

        $title = match (true) {
            $this->score instanceof Score => __('Edit Score'),
            $isGuest && $this->isSharedLink => __('Score Preview'),
            $isGuest => __('Score Editor'),
            default => __('Create Score'),
        };

        $layout = $isGuest ? 'layouts::app.main' : 'layouts::app';
        $view->layout($layout, ['title' => $title, 'noindex' => $this->isSharedLink]);
    }

    /**
     * @param  array<string, mixed>|null  $ratioSettings
     */
    public function save(?array $ratioSettings = null, ?string $ratio = null, ?string $incipitDataUrl = null): void
    {
        $this->authorize($this->score ? 'update' : 'create', $this->score ?? Score::class);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'musicId' => ['nullable', 'integer'],
            'publicPreview' => ['boolean'],
        ];

        if (! $this->linksOnly) {
            $rules['format'] = ['required', Rule::enum(ScoreFormat::class)];
            $rules['content'] = ['required', 'string'];
        }

        $validated = $this->validate($rules);

        if ($this->linksOnly && ! $this->hasAnyLink()) {
            $this->addError('newUrl', __('Add at least one link.'));

            return;
        }

        $musicId = $this->resolveMusicId($validated['musicId']);

        $score = $this->score ?? new Score(['user_id' => Auth::id()]);

        if ($this->linksOnly) {
            $score->fill([
                'music_id' => $musicId,
                'title' => $validated['title'],
                'format' => null,
                'content' => null,
                'settings' => null,
                'public_preview' => false,
            ]);
        } else {
            $settings = $this->settings;
            if (is_string($ratio) && $ratio !== '' && is_array($ratioSettings)) {
                $settings[$validated['format']][$ratio] = $ratioSettings;
            }

            $score->fill([
                'music_id' => $musicId,
                'title' => $validated['title'],
                'format' => $validated['format'],
                'content' => $validated['content'],
                'settings' => $settings ?: null,
                'public_preview' => $musicId !== null && ($validated['publicPreview'] ?? false),
            ]);
        }

        $score->user_id = $score->user_id ?: Auth::id();
        $score->save();

        foreach ($this->pendingUrls as $pendingUrl) {
            $score->urls()->create([
                'url' => $pendingUrl['url'],
                'label' => $pendingUrl['label'] ?: null,
                'comment' => $pendingUrl['comment'] ?: null,
            ]);
        }
        $this->pendingUrls = [];

        if (! $this->linksOnly) {
            $this->storeIncipit($score, $incipitDataUrl);
            $this->settings = $score->settings ?? [];
        }

        $this->dispatch($this->score ? 'score-updated' : 'score-created');
        $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);
    }

    /**
     * @param  array<string, mixed>  $ratioSettings
     */
    public function saveAsDefault(array $ratioSettings, string $ratio, string $format): void
    {
        if (! ScoreFormat::tryFrom($format) instanceof ScoreFormat) {
            return;
        }

        if ($ratio === '') {
            return;
        }

        $user = Auth::user();
        abort_unless($user instanceof \App\Models\User, 403);

        $defaults = $user->score_settings ?? [];
        $defaults[$format][$ratio] = $ratioSettings;
        $user->score_settings = $defaults;
        $user->save();

        $this->dispatch('score-defaults-saved');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Renderless]
    public function createShareUrl(array $data): string
    {
        $title = is_string($data['title'] ?? null) ? $data['title'] : '';
        $format = ScoreFormat::tryFrom(is_string($data['format'] ?? null) ? $data['format'] : '');
        $content = is_string($data['content'] ?? null) ? $data['content'] : '';
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        $json = (string) json_encode([
            'title' => $title,
            'format' => $format instanceof ScoreFormat ? $format->value : ScoreFormat::Abc->value,
            'content' => $content,
            'settings' => $settings,
        ]);

        $compressed = gzdeflate($json, 9);
        $encoded = rtrim(strtr(base64_encode((string) $compressed), '+/', '-_'), '=');

        return route('score.preview', ['d' => $encoded]);
    }

    #[Renderless]
    public function generateSecretLink(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        do {
            $token = Str::random(32);
        } while (Score::query()->where('share_token', $token)->exists());

        $this->score->share_token = $token;
        $this->score->save();

        $this->secretLinkUrl = route('score.share', ['token' => $token]);
    }

    #[Renderless]
    public function deleteSecretLink(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $this->score->share_token = null;
        $this->score->save();

        $this->secretLinkUrl = null;
    }

    public function toggleFolder(int $folderId): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        Folder::query()->where('id', $folderId)->where('user_id', Auth::id())->firstOrFail();

        if (in_array($folderId, $this->folderIds, true)) {
            $this->score->folders()->detach($folderId);
            $this->folderIds = array_values(array_filter($this->folderIds, fn ($id) => $id !== $folderId));
        } else {
            $this->score->folders()->attach($folderId);
            $this->folderIds[] = $folderId;
        }
    }

    public function createFolderAndAdd(string $newFolderName): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $name = trim($newFolderName);
        if ($name === '') {
            return;
        }

        $folder = Folder::query()->create([
            'user_id' => Auth::id(),
            'name' => $name,
        ]);

        $this->score->folders()->attach($folder);
        $this->folderIds[] = $folder->id;

        unset($this->userFolders);
    }

    public function addUrl(): void
    {
        $this->authorize($this->score instanceof Score ? 'update' : 'create', $this->score ?? Score::class);

        $this->validate([
            'newUrl' => ['required', 'string', 'url', 'max:2048'],
            'newUrlLabel' => ['nullable', Rule::enum(MusicUrlLabel::class)],
            'newUrlComment' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->score instanceof Score) {
            $this->score->urls()->create([
                'url' => $this->newUrl,
                'label' => $this->newUrlLabel ?: null,
                'comment' => $this->newUrlComment ?: null,
            ]);
        } else {
            $this->pendingUrls[] = [
                'url' => $this->newUrl,
                'label' => $this->newUrlLabel ?: null,
                'comment' => $this->newUrlComment ?: null,
            ];
        }

        $this->newUrl = '';
        $this->newUrlLabel = null;
        $this->newUrlComment = '';

        unset($this->scoreUrls);
    }

    public function deleteUrl(int $urlId): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $url = ScoreUrl::query()->where('score_id', $this->score->id)->find($urlId);
        abort_if($url === null, 404);
        $url->delete();

        unset($this->scoreUrls);
    }

    public function removePendingUrl(int $index): void
    {
        unset($this->pendingUrls[$index]);
        $this->pendingUrls = array_values($this->pendingUrls);

        unset($this->scoreUrls);
    }

    private function hasAnyLink(): bool
    {
        if ($this->pendingUrls !== []) {
            return true;
        }

        return $this->score instanceof Score && $this->score->urls()->exists();
    }

    public function delete(): void
    {
        abort_unless($this->score instanceof Score, 404);

        $this->authorize('delete', $this->score);
        $this->score->delete();

        $this->redirectRoute('scores', navigate: true);
    }

    #[Computed]
    /** @return \Illuminate\Support\Collection<int, \App\Models\ScoreUrl> */
    public function scoreUrls(): \Illuminate\Support\Collection
    {
        $persisted = $this->score instanceof Score
            ? $this->score->urls()->orderBy('id')->get()
            : collect();

        $pending = collect($this->pendingUrls)->map(function (array $pendingUrl, int $index): ScoreUrl {
            $url = new ScoreUrl([
                'url' => $pendingUrl['url'],
                'label' => $pendingUrl['label'] ?: null,
                'comment' => $pendingUrl['comment'] ?: null,
            ]);
            $url->pending_index = $index;

            return $url;
        });

        return collect($persisted)->concat($pending)->values();
    }

    #[Computed]
    /** @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Folder> */
    public function userFolders(): \Illuminate\Database\Eloquent\Collection
    {
        if (! Auth::check()) {
            return Folder::query()->whereNull('id')->get();
        }

        return Folder::query()->mine(Auth::user())->orderBy('name')->get();
    }

    #[Computed]
    public function cheatsheetHtml(): HtmlString
    {
        return $this->markdownDocToHtml('docs/aretino-cheatsheet.md');
    }

    #[Computed]
    public function abcCheatsheetHtml(): HtmlString
    {
        return $this->markdownDocToHtml('docs/abc-cheatsheet.md');
    }

    #[Computed]
    public function chordproCheatsheetHtml(): HtmlString
    {
        return $this->markdownDocToHtml('docs/chordpro-cheatsheet.md');
    }

    #[Computed]
    public function gabcCheatsheetHtml(): HtmlString
    {
        return $this->markdownDocToHtml('docs/gabc-cheatsheet.md');
    }

    private function markdownDocToHtml(string $relativePath): HtmlString
    {
        $path = base_path($relativePath);
        $markdown = file_exists($path) ? (string) file_get_contents($path) : '';

        $environment = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        return new HtmlString((new MarkdownConverter($environment))->convert($markdown));
    }

    #[Computed]
    public function selectedMusic(): ?Music
    {
        if ($this->musicId === null) {
            return null;
        }

        return Music::query()->find($this->musicId);
    }

    #[Computed]
    /** @return \Illuminate\Database\Eloquent\Collection<int, Score> */
    public function relatedScores(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->isSharedLink || $this->musicId === null || ! Auth::check()) {
            return Score::query()->whereNull('id')->get();
        }

        $query = Score::query()
            ->where('music_id', $this->musicId)
            ->where('user_id', Auth::id());

        if ($this->score instanceof Score) {
            $query->where('id', '!=', $this->score->id);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    #[On('music-selected.score')]
    public function onMusicSelected(int $musicId): void
    {
        $music = Music::query()->findOrFail($musicId);
        abort_unless(Gate::allows('view', $music), 403);

        $this->musicId = $music->id;
        $this->title = $music->title;
        $this->js("Flux.modal('score-music-search').close()");
    }

    public function clearMusic(): void
    {
        $this->musicId = null;
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.pages.score-editor', [
            'formats' => ScoreFormat::cases(),
            'urlLabels' => MusicUrlLabel::cases(),
            'userDefaults' => $user instanceof \App\Models\User ? ($user->score_settings ?? []) : [],
            'isSharedLink' => $this->isSharedLink,
            'isGuest' => ! Auth::check(),
        ]);
    }

    private function storeIncipit(Score $score, ?string $incipitDataUrl): void
    {
        $prefix = 'data:image/png;base64,';

        if (! is_string($incipitDataUrl) || ! str_starts_with($incipitDataUrl, $prefix)) {
            return;
        }

        $bytes = base64_decode(substr($incipitDataUrl, strlen($prefix)), strict: true);

        if ($bytes === false || strlen($bytes) > 2_000_000) {
            return;
        }

        Storage::put($score->incipit_path, $bytes);
    }

    private function loadFromSharedData(string $d): void
    {
        $padded = str_replace(['-', '_'], ['+', '/'], $d);
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded = str_pad($padded, strlen($padded) + (4 - $remainder), '=');
        }

        $decoded = base64_decode($padded, strict: true);
        if ($decoded === false) {
            return;
        }

        // Try deflate decompression (new format), fall back to raw JSON (legacy format)
        $inflated = @gzinflate($decoded);
        $json = $inflated !== false ? $inflated : $decoded;

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return;
        }

        $this->title = is_string($data['title'] ?? null) ? $data['title'] : '';
        $format = ScoreFormat::tryFrom(is_string($data['format'] ?? null) ? $data['format'] : '');
        $this->format = $format instanceof ScoreFormat ? $format->value : ScoreFormat::Abc->value;
        $this->content = is_string($data['content'] ?? null) ? $data['content'] : '';
        $this->settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
    }

    private function resolveMusicId(?int $musicId): ?int
    {
        if ($musicId === null) {
            return null;
        }

        $music = Music::query()->findOrFail($musicId);
        abort_unless(Gate::allows('view', $music), 403);

        return $music->id;
    }
}
