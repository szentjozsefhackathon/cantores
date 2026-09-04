<?php

namespace App\Livewire\Pages;

use App\Enums\ScoreFileRights;
use App\Enums\ScoreFormat;
use App\Enums\ScoreLicense;
use App\Models\Folder;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\ScoreUrl;
use App\Models\Share;
use App\MusicUrlLabel;
use App\Services\ScoreDuplicator;
use App\Services\ScoreFileUploader;
use App\Services\ScorePublicationService;
use App\Services\ShareAccessService;
use App\Support\ScorePublicationRules;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ScoreEditor extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    /**
     * What an uploaded sheet music file may be. A PDF is accepted beside the
     * editable formats: it is what a choir actually prints from, and poppler
     * cuts it into pages the same way an engraved score is cut.
     *
     * @var list<string>
     */
    private const UPLOAD_RULES = ['nullable', 'file', 'extensions:mscz,musicxml,mxl,mid,midi,pdf', 'max:25600'];

    public ?Score $score = null;

    public ?int $musicId = null;

    public string $title = '';

    /**
     * What this score is called among the other versions of the same music —
     * "Fuvola", "Kórus", "Csak szöveg".
     */
    public string $variationName = '';

    public string $format = 'abc';

    public string $content = '';

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $settings = [];

    public bool $publicPreview = false;

    public bool $linksOnly = false;

    /** @var array<int, array{url: string, label: ?string, comment: ?string}> */
    public array $pendingUrls = [];

    /**
     * The sheet music file staged for upload. On a score that already exists it
     * is added by addFile(); on a new one save() persists it, in the same way
     * $pendingUrls is.
     */
    #[Validate(self::UPLOAD_RULES)]
    public $pendingFile = null;

    public string $fileRights = ScoreFileRights::OwnWork->value;

    /**
     * The nomination form. Empty until the owner opens it, and never a source
     * of truth: the publication row is.
     *
     * @var array<string, mixed>
     */
    public array $publicationForm = [
        'license' => '',
        'outbound_license' => '',
        'source_url' => '',
        'source_title' => '',
        'composer_death_year' => '',
        'edition_is_free' => false,
        'rights_note' => '',
        'permission_evidence' => '',
        'attribution_line' => '',
    ];

    /**
     * What to call the staged file in the file list — "A4", "A5 booklet" — so a
     * score carrying several tells them apart.
     */
    public string $fileLabel = '';

    /** The file whose row the edit dialog is open on, if any. */
    public ?int $editingFileId = null;

    public string $editingLabel = '';

    public string $editingRights = ScoreFileRights::OwnWork->value;

    /** New bytes for the file being edited, replacing what it holds now. */
    public $replacementFile = null;

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
            $this->variationName = $score->variation_name ?? '';
            $this->linksOnly = $score->format === null;
            $this->format = $score->format?->value ?? 'abc';
            $this->content = $score->content ?? '';
            $this->settings = $score->settings ?? [];
            $this->publicPreview = (bool) $score->public_preview;
            $shareToken = $score->shareToken();
            $this->secretLinkUrl = $shareToken !== null
                ? route('score.share', ['token' => $shareToken])
                : null;
            $this->folderIds = $score->folders()->pluck('folder_id')->toArray();
            $this->fillPublicationForm($score);

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

        if (request()->routeIs('scores.create')) {
            $this->openDraft();
        }
    }

    /**
     * Give the editor a row to work on before a single note is typed, the way a
     * cloud document editor does: uploads, share links and autosave all need a
     * score that exists, and forgetting to press Save can then lose nothing.
     */
    private function openDraft(): void
    {
        $title = $this->title !== '' ? $this->title : __('Untitled score');

        $draft = $this->untouchedDraft($title) ?? Score::create([
            'user_id' => Auth::id(),
            'music_id' => $this->musicId,
            'title' => $title,
            'format' => $this->format,
            'content' => null,
        ]);

        $this->redirectRoute('scores.edit', ['score' => $draft->id], navigate: true);
    }

    /**
     * A draft the user opened before and left exactly as it was found. Opening
     * the editor again hands back that same row rather than littering the
     * library with a new "Untitled score" per visit.
     */
    private function untouchedDraft(string $title): ?Score
    {
        return Score::query()
            ->where('user_id', Auth::id())
            ->where('title', $title)
            ->when(
                $this->musicId === null,
                fn ($query) => $query->whereNull('music_id'),
                fn ($query) => $query->where('music_id', $this->musicId),
            )
            ->where(fn ($query) => $query->whereNull('content')->orWhere('content', ''))
            ->whereDoesntHave('files')
            ->whereDoesntHave('urls')
            ->latest('id')
            ->first();
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
     * @param  array<string, array<string, mixed>>|null  $allRatioSettings  Map of ratio key to settings
     */
    public function save(?array $allRatioSettings = null, ?string $incipitDataUrl = null): void
    {
        $isNew = $this->score === null;

        $this->authorize($isNew ? 'create' : 'update', $this->score ?? Score::class);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'variationName' => ['nullable', 'string', 'max:120'],
            'musicId' => ['nullable', 'integer'],
            'publicPreview' => ['boolean'],
        ];

        if (! $this->linksOnly) {
            $rules['format'] = ['required', Rule::enum(ScoreFormat::class)];
            $rules['content'] = ['required', 'string'];
        }

        if ($this->pendingFile !== null) {
            $rules['pendingFile'] = self::UPLOAD_RULES;
            $rules['fileRights'] = ['required', Rule::enum(ScoreFileRights::class)];
            $rules['fileLabel'] = ['nullable', 'string', 'max:120'];
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
                'variation_name' => $this->variationNameForStorage(),
                'format' => null,
                'content' => null,
                'settings' => null,
                'public_preview' => false,
            ]);
        } else {
            $settings = $this->settingsMergedWith($allRatioSettings, $validated['format']);

            $score->fill([
                'music_id' => $musicId,
                'title' => $validated['title'],
                'variation_name' => $this->variationNameForStorage(),
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

        $this->storePendingFile($score);

        if (! $this->linksOnly) {
            $this->storeIncipit($score, $incipitDataUrl);
            $this->settings = $score->settings ?? [];
        }

        $this->dispatch('toast', message: $isNew ? __('Score created.') : __('Score updated.'), type: 'success');
        if ($isNew) {
            $this->redirectRoute('scores.edit', ['score' => $score->id], navigate: true);
        }
    }

    /**
     * Write what is on screen back to the row without saying a word: no toast,
     * no redirect and no validation errors, so an unfinished score is kept
     * without the editor nagging about what is still missing.
     *
     * It never clears anything the explicit save would clear — a blank title or
     * a links-only switch leaves the stored value alone — so an autosave can
     * only ever cost the user less work, never more.
     *
     * @param  array<string, array<string, mixed>>|null  $allRatioSettings  Map of ratio key to settings
     */
    #[Renderless]
    public function autosave(?array $allRatioSettings = null, ?string $incipitDataUrl = null): void
    {
        if (! $this->score instanceof Score || $this->linksOnly) {
            return;
        }

        $this->authorize('update', $this->score);

        if (ScoreFormat::tryFrom($this->format) === null) {
            return;
        }

        $score = $this->score;
        $musicId = $this->resolveMusicId($this->musicId);
        $settings = $this->settingsMergedWith($allRatioSettings, $this->format);

        if (trim($this->title) !== '') {
            $score->title = $this->title;
        }

        $score->fill([
            'music_id' => $musicId,
            'variation_name' => $this->variationNameForStorage(),
            'format' => $this->format,
            'content' => $this->content,
            'settings' => $settings ?: null,
            'public_preview' => $musicId !== null && $this->publicPreview,
        ]);

        $score->save();

        $this->storeIncipit($score, $incipitDataUrl);
        $this->settings = $score->settings ?? [];

        $this->dispatch('score-autosaved');
    }

    /**
     * The public-preview box is the one editor control that changes the score
     * without touching the preview, so the browser-side timer never hears about
     * it; the tick is written back here instead.
     */
    public function updatedPublicPreview(): void
    {
        $this->autosave();
    }

    /**
     * Start another variation of the same music as a copy of this one, so the
     * new score arrives with the music, the source, the render settings, the
     * links, the folders and the files already in place instead of an empty
     * editor. What is on screen is written back first, so the copy is of the
     * score as the owner sees it rather than of the last autosave.
     *
     * Nothing that exposes a score is copied: the new variation is not
     * previewable to guests, is not nominated to the public library and
     * inherits no share link.
     *
     * @param  array<string, array<string, mixed>>|null  $allRatioSettings  Map of ratio key to settings
     */
    public function addVariation(?array $allRatioSettings = null, ?string $incipitDataUrl = null): void
    {
        $this->authorize('create', Score::class);

        if (! $this->score instanceof Score) {
            $this->redirectRoute('scores.create', ['music' => $this->musicId], navigate: true);

            return;
        }

        $this->authorize('update', $this->score);

        $this->autosave($allRatioSettings, $incipitDataUrl);

        $copy = app(ScoreDuplicator::class)->duplicate($this->score->fresh() ?? $this->score);

        $this->dispatch('toast', message: __('Variation created as a copy.'), type: 'success');
        $this->redirectRoute('scores.edit', ['score' => $copy->id], navigate: true);
    }

    /**
     * The score's settings with this session's per-ratio edits laid over them.
     *
     * @param  array<string, array<string, mixed>>|null  $allRatioSettings  Map of ratio key to settings
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function settingsMergedWith(?array $allRatioSettings, string $format): array
    {
        $settings = $this->settings;

        if (is_array($allRatioSettings)) {
            foreach ($allRatioSettings as $ratio => $ratioSettings) {
                if (is_string($ratio) && $ratio !== '' && is_array($ratioSettings)) {
                    $settings[$format][$ratio] = $ratioSettings;
                }
            }
        }

        return $settings;
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

        $this->dispatch('toast', message: __('Saved as your default for this ratio.'), type: 'success');
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

    /**
     * The score's standing offer to the public library, if any.
     */
    #[Computed]
    public function publication(): ?ScorePublication
    {
        return $this->score?->publication;
    }

    /**
     * Whether the nomination form should be reachable at all.
     */
    #[Computed]
    public function canNominate(): bool
    {
        return $this->score instanceof Score
            && Gate::allows('nominate', $this->score);
    }

    /**
     * Flag or unflag one file for publication.
     *
     * Only the owner's own files, and only ones whose declared rights permit
     * it — the review queue shows the result, so it must not be forgeable here.
     */
    public function togglePublishedFile(int $scoreFileId): void
    {
        $scoreFile = $this->ownedFile($scoreFileId);

        if (! $scoreFile->is_published && ! $scoreFile->rights->mayBePublished()) {
            $this->dispatch(
                'toast',
                message: __('That file cannot be published with the rights you declared for it.'),
                type: 'error',
            );

            return;
        }

        $scoreFile->update(['is_published' => ! $scoreFile->is_published]);

        $this->forgetFiles();
        unset($this->publication);
    }

    public function submitForPublication(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('nominate', $this->score);

        $license = ScoreLicense::tryFrom((string) ($this->publicationForm['license'] ?? ''));

        $rules = [];
        foreach (ScorePublicationRules::for($license) as $field => $fieldRules) {
            $rules["publicationForm.{$field}"] = $fieldRules;
        }

        $messages = [];
        foreach (ScorePublicationRules::messages() as $key => $message) {
            $messages["publicationForm.{$key}"] = $message;
        }

        $this->validate($rules, $messages);

        try {
            app(ScorePublicationService::class)->submit(
                $this->score,
                Auth::user(),
                $this->publicationAttributes(),
            );
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->publication);

        $this->dispatch('score-publication-submitted');
        $this->dispatch(
            'toast',
            message: __('Sent for review. An editor will check the licence before it goes public.'),
            type: 'success',
        );
    }

    public function withdrawPublication(): void
    {
        $publication = $this->publication;

        abort_if($publication === null, 404);
        $this->authorize('withdraw', $publication);

        app(ScorePublicationService::class)->withdraw($publication);

        unset($this->publication);

        $this->dispatch('toast', message: __('Withdrawn from the public library.'), type: 'success');
    }

    /**
     * Load a standing nomination back into the form, so an owner fixing a
     * rejection edits what they submitted rather than starting again.
     */
    private function fillPublicationForm(Score $score): void
    {
        $publication = $score->publication;

        if (! $publication instanceof ScorePublication) {
            return;
        }

        $this->publicationForm = [
            'license' => $publication->license->value,
            'outbound_license' => $publication->outbound_license?->value ?? '',
            'source_url' => $publication->source_url ?? '',
            'source_title' => $publication->source_title ?? '',
            'composer_death_year' => (string) ($publication->composer_death_year ?? ''),
            'edition_is_free' => $publication->edition_is_free,
            'rights_note' => $publication->rights_note ?? '',
            'permission_evidence' => $publication->permission_evidence ?? '',
            'attribution_line' => $publication->attribution_line ?? '',
        ];
    }

    /**
     * The nomination form, normalised for storage.
     *
     * @return array<string, mixed>
     */
    private function publicationAttributes(): array
    {
        $blankToNull = fn (string $key): ?string => trim((string) ($this->publicationForm[$key] ?? '')) !== ''
            ? trim((string) $this->publicationForm[$key])
            : null;

        $license = ScoreLicense::tryFrom((string) ($this->publicationForm['license'] ?? ''));

        return [
            'license' => $this->publicationForm['license'],
            'outbound_license' => $blankToNull('outbound_license'),
            'source_url' => $blankToNull('source_url'),
            'source_title' => $blankToNull('source_title'),
            'composer_death_year' => $blankToNull('composer_death_year'),
            // Only meaningful where the licence asks the question, so a tick
            // left behind by a licence the owner tried and abandoned does not
            // reach the reviewer as an assertion they never made.
            'edition_is_free' => $license?->requiresEditionAffirmation()
                && (bool) ($this->publicationForm['edition_is_free'] ?? false),
            'rights_note' => $blankToNull('rights_note'),
            'permission_evidence' => $blankToNull('permission_evidence'),
            'attribution_line' => $blankToNull('attribution_line'),
        ];
    }

    public function generateSecretLink(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $share = $this->score->mintShare();

        $this->secretLinkUrl = route('score.share', ['token' => $share->token]);
    }

    public function deleteSecretLink(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $this->score->revokeShares();

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

        $this->resetUrlForm();

        unset($this->scoreUrls);

        $this->dispatch('score-url-added');
    }

    /**
     * Empty the add-link dialog when it is dismissed, so reopening it does not
     * offer the half-typed link from last time.
     */
    public function cancelUrlAdd(): void
    {
        $this->resetUrlForm();
        $this->resetErrorBag(['newUrl', 'newUrlLabel', 'newUrlComment']);
    }

    private function resetUrlForm(): void
    {
        $this->newUrl = '';
        $this->newUrlLabel = null;
        $this->newUrlComment = '';
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

    /**
     * Whether a links-only score has anything to point at. An uploaded file is
     * the sheet music itself, so it satisfies the requirement the way a link does.
     */
    private function hasAnyLink(): bool
    {
        if ($this->pendingUrls !== [] || $this->pendingFile !== null) {
            return true;
        }

        if (! $this->score instanceof Score) {
            return false;
        }

        return $this->score->urls()->exists() || $this->score->files()->exists();
    }

    /**
     * Prefill from the file the moment it is staged: a .mscz knows its own title,
     * so the cantor does not retype it, and the embedded thumbnail gives the score
     * a preview before the render job has run.
     */
    public function updatedPendingFile(): void
    {
        $this->validateOnly('pendingFile');

        if ($this->pendingFile === null) {
            return;
        }

        $metadata = app(ScoreFileUploader::class)->inspect($this->pendingFile);

        if ($this->title === '' && is_string($metadata['title'])) {
            $this->title = $metadata['title'];
        }

        // The file is the sheet music, so the score has no editor format — but
        // not at the cost of the source someone has already typed.
        if ($this->content === '') {
            $this->linksOnly = true;
        }
    }

    public function removePendingFile(): void
    {
        $this->pendingFile = null;
        $this->fileLabel = '';
        $this->resetErrorBag('pendingFile');
    }

    /**
     * Add the staged file to a score that already exists, without waiting for a
     * save — the list it lands in is the point of staging it.
     */
    public function addFile(): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $this->validate([
            'pendingFile' => ['required', ...self::UPLOAD_RULES],
            'fileRights' => ['required', Rule::enum(ScoreFileRights::class)],
            'fileLabel' => ['nullable', 'string', 'max:120'],
        ]);

        app(ScoreFileUploader::class)->store(
            $this->score,
            $this->pendingFile,
            ScoreFileRights::from($this->fileRights),
            $this->fileLabel,
        );

        $this->pendingFile = null;
        $this->fileLabel = '';
        $this->forgetFiles();

        $this->dispatch('score-file-added');
        $this->dispatch('toast', message: __('File added.'), type: 'success');
    }

    /**
     * Load a file's details into the edit dialog the row's button opens.
     */
    public function editFile(int $scoreFileId): void
    {
        $scoreFile = $this->ownedFile($scoreFileId);

        $this->editingFileId = $scoreFile->id;
        $this->editingLabel = $scoreFile->label ?? '';
        $this->editingRights = $scoreFile->rights->value;
        $this->replacementFile = null;
        $this->resetErrorBag(['editingLabel', 'editingRights', 'replacementFile']);
    }

    /**
     * Save the edited details, and the new bytes when the dialog was used to
     * re-upload — the row keeps its identity either way, so a link handed out
     * for this file still reaches it.
     */
    public function updateFile(): void
    {
        $scoreFile = $this->ownedFile((int) $this->editingFileId);

        $rules = [
            'editingLabel' => ['nullable', 'string', 'max:120'],
            'editingRights' => ['required', Rule::enum(ScoreFileRights::class)],
        ];

        if ($this->replacementFile !== null) {
            $rules['replacementFile'] = self::UPLOAD_RULES;
        }

        $this->validate($rules);

        $scoreFile->update([
            'label' => trim($this->editingLabel) !== '' ? trim($this->editingLabel) : null,
            'rights' => ScoreFileRights::from($this->editingRights),
        ]);

        if ($this->replacementFile !== null) {
            app(ScoreFileUploader::class)->replace($scoreFile, $this->replacementFile);
        }

        $this->cancelFileEdit();
        $this->forgetFiles();

        $this->dispatch('score-file-saved');
        $this->dispatch('toast', message: __('File updated.'), type: 'success');
    }

    public function cancelFileEdit(): void
    {
        $this->editingFileId = null;
        $this->editingLabel = '';
        $this->replacementFile = null;
        $this->resetErrorBag(['editingLabel', 'editingRights', 'replacementFile']);
    }

    public function deleteFile(int $scoreFileId): void
    {
        $scoreFile = $this->ownedFile($scoreFileId);

        app(ScoreFileUploader::class)->delete($scoreFile);

        if ($this->editingFileId === $scoreFileId) {
            $this->cancelFileEdit();
        }

        $this->forgetFiles();
    }

    /**
     * The score's file with this id, or a 404 — a file id from another score is
     * not this editor's to touch.
     */
    private function ownedFile(int $scoreFileId): ScoreFile
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        $scoreFile = $this->score->files()->find($scoreFileId);
        abort_if($scoreFile === null, 404);

        return $scoreFile;
    }

    /**
     * Every file uploaded to this score, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreFile>
     */
    #[Computed]
    public function scoreFiles(): EloquentCollection
    {
        return $this->score instanceof Score ? $this->score->orderedFiles() : new EloquentCollection;
    }

    /**
     * Whether the queue still owes this score a preview, so the page keeps
     * polling until it lands.
     */
    #[Computed]
    public function filesRendering(): bool
    {
        return $this->scoreFiles->contains(fn (ScoreFile $scoreFile): bool => $scoreFile->isRendering());
    }

    /**
     * URLs of every rendered page, in page order, keyed by score file id.
     *
     * @return array<int, list<string>>
     */
    #[Computed]
    public function filePageUrls(): array
    {
        $urls = [];

        foreach ($this->scoreFiles as $scoreFile) {
            $urls[$scoreFile->id] = array_map(
                fn (int $page): string => route('scores.file.page', [
                    'score' => $this->score,
                    'scoreFile' => $scoreFile,
                    'page' => $page,
                ]),
                $scoreFile->pageNumbers(),
            );
        }

        return $urls;
    }

    private function forgetFiles(): void
    {
        $this->score?->unsetRelation('files');

        unset($this->scoreFiles, $this->filesRendering, $this->filePageUrls);
    }

    public function delete(): void
    {
        abort_unless($this->score instanceof Score, 404);

        $this->authorize('delete', $this->score);
        $this->score->delete();

        $this->redirectRoute('scores', navigate: true);
    }

    /**
     * Grants that reach this score through a shared folder or music plan.
     *
     * Sharing a folder or an énekrend also opens the scores underneath it, so the
     * owner needs to see those here rather than assume the score is private just
     * because it has no secret link of its own.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, revoke_id: int}>
     */
    #[Computed]
    public function indirectShares(): \Illuminate\Support\Collection
    {
        if (! $this->score instanceof Score) {
            return collect();
        }

        return app(ShareAccessService::class)
            ->grantsReaching($this->score)
            ->reject(fn (Share $share) => $share->shareable instanceof Score)
            ->map(fn (Share $share) => [
                'label' => $share->shareable instanceof Folder
                    ? __('Folder: :name', ['name' => $share->shareable->name])
                    : __('Music plan: :name', ['name' => $share->shareable->celebration_name ?? __('Music Plan')]),
                'revoke_id' => $share->id,
            ])
            ->values();
    }

    #[Renderless]
    public function revokeIndirectShare(int $shareId): void
    {
        abort_unless($this->score instanceof Score, 404);
        $this->authorize('update', $this->score);

        Share::query()->mine(Auth::user())->findOrFail($shareId)->revoke();

        unset($this->indirectShares);
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
        $this->autosave();
    }

    public function clearMusic(): void
    {
        $this->musicId = null;
        $this->autosave();
    }

    public function selectFormat(string $format): void
    {
        if (! ScoreFormat::tryFrom($format) instanceof ScoreFormat) {
            return;
        }

        $this->format = $format;
        $this->linksOnly = false;
        $this->autosave();
    }

    /**
     * Pick the fifth format: the score is the links and the files hung on it,
     * with nothing typed in an editor.
     */
    public function selectLinksOnly(): void
    {
        $this->linksOnly = true;
    }

    /**
     * The variation name as the column wants it: a blank box means unnamed, and
     * an over-long one is cut rather than allowed to break a silent autosave.
     */
    private function variationNameForStorage(): ?string
    {
        $name = trim($this->variationName);

        return $name !== '' ? mb_substr($name, 0, 120) : null;
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.pages.score-editor', [
            'formats' => ScoreFormat::cases(),
            'urlLabels' => MusicUrlLabel::cases(),
            'rightsOptions' => ScoreFileRights::cases(),
            'licenseOptions' => ScoreLicense::cases(),
            'outboundLicenseOptions' => ScoreLicense::redistributableCases(),
            'editionFreeBefore' => ScorePublicationRules::editionFreeBefore(),
            'userDefaults' => $user instanceof \App\Models\User ? ($user->score_settings ?? []) : [],
            'isSharedLink' => $this->isSharedLink,
            'isGuest' => ! Auth::check(),
        ]);
    }

    private function storePendingFile(Score $score): void
    {
        if ($this->pendingFile === null) {
            return;
        }

        app(ScoreFileUploader::class)->store(
            $score,
            $this->pendingFile,
            ScoreFileRights::from($this->fileRights),
            $this->fileLabel,
        );

        $this->pendingFile = null;
        $this->fileLabel = '';
        $this->forgetFiles();
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
