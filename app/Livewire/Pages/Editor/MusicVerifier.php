<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Music;
use App\Models\MusicVerification;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class MusicVerifier extends Component
{
    use AuthorizesRequests;

    // Music selection
    #[Url]
    public ?int $musicId = null;

    public string $search = '';

    // Loaded music model
    public ?Music $music = null;

    // Verification state
    public array $verifications = [];

    public array $fieldNotes = [];

    public array $fieldStatuses = [];

    // UI state
    public bool $showVerification = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', MusicVerification::class);

        if ($this->musicId) {
            $this->loadMusicById($this->musicId);
        }
    }

    /**
     * Load a music piece by ID.
     */
    private function loadMusicById(int $musicId): void
    {
        $music = Music::with([
            'collections',
            'genres',
            'urls',
            'authors',
            'relatedMusic',
            'verifications' => function ($query) {
                $query->where('verifier_id', Auth::id())->orWhere('status', 'pending');
            },
        ])
            ->visibleTo(Auth::user())
            ->findOrFail($musicId);

        $this->authorize('view', $music);

        $this->musicId = $musicId;
        $this->music = $music;
        $this->loadExistingVerifications();
        $this->showVerification = true;
    }

    /**
     * Load existing verifications for the selected music.
     */
    private function loadExistingVerifications(): void
    {
        $this->verifications = [];
        $this->fieldStatuses = [];
        $this->fieldNotes = [];

        if (! $this->music) {
            return;
        }

        // Load existing verifications
        foreach ($this->music->verifications as $verification) {
            $key = $this->getVerificationKey($verification->field_name, $verification->pivot_reference);
            $this->verifications[$key] = $verification;
            $this->fieldStatuses[$key] = $verification->status;
            $this->fieldNotes[$key] = $verification->notes ?? '';
        }

        // Initialize missing fields with pending status
        $this->initializeMissingFields();
    }

    /**
     * Initialize verification status for fields that don't have a verification record.
     */
    private function initializeMissingFields(): void
    {
        $fields = $this->getVerifiableFields();

        foreach ($fields as $field) {
            $key = $this->getVerificationKey($field['name'], $field['pivot_reference'] ?? null);
            if (! isset($this->verifications[$key])) {
                $this->fieldStatuses[$key] = 'pending';
                $this->fieldNotes[$key] = '';
            }
        }
    }

    /**
     * Get all verifiable fields and relations for the current music.
     */
    private function getVerifiableFields(): array
    {
        if (! $this->music) {
            return [];
        }

        $fields = [];

        // Direct fields
        $directFields = ['title', 'subtitle', 'custom_id'];
        foreach ($directFields as $field) {
            $fields[] = [
                'type' => 'field',
                'name' => $field,
                'label' => __(ucfirst(str_replace('_', ' ', $field))),
                'value' => $this->music->$field,
                'pivot_reference' => null,
            ];
        }

        // Authors (relations)
        foreach ($this->music->authors as $index => $author) {
            $fields[] = [
                'type' => 'relation',
                'name' => 'author',
                'label' => __('Author').' '.($index + 1),
                'value' => $author->name,
                'pivot_reference' => $author->id,
            ];
        }

        // Collections (relations with pivot)
        foreach ($this->music->collections as $collection) {
            $fields[] = [
                'type' => 'relation',
                'name' => 'collection',
                'label' => __('Collection').': '.$collection->title,
                'value' => $collection->pivot ? [
                    'page_number' => $collection->pivot->page_number,
                    'order_number' => $collection->pivot->order_number,
                ] : null,
                'pivot_reference' => $collection->id,
            ];
        }

        // URLs
        foreach ($this->music->urls as $index => $url) {
            $fields[] = [
                'type' => 'relation',
                'name' => 'url',
                'label' => __('URL').' '.($index + 1),
                'value' => $url->url.($url->label ? ' ('.$url->label.')' : ''),
                'pivot_reference' => $url->id,
            ];
        }

        // Genres
        foreach ($this->music->genres as $index => $genre) {
            $fields[] = [
                'type' => 'relation',
                'name' => 'genre',
                'label' => __('Genre').' '.($index + 1),
                'value' => $genre->name,
                'pivot_reference' => $genre->id,
            ];
        }

        // Related music
        foreach ($this->music->relatedMusic as $index => $related) {
            $fields[] = [
                'type' => 'relation',
                'name' => 'related_music',
                'label' => __('Related Music').' '.($index + 1),
                'value' => $related->title,
                'pivot_reference' => $related->id,
            ];
        }

        return $fields;
    }

    /**
     * Generate a unique key for a verification.
     */
    private function getVerificationKey(string $fieldName, ?int $pivotReference = null): string
    {
        return $fieldName.':'.($pivotReference ?? '0');
    }

    /**
     * Search for music based on search term.
     */
    public function searchMusic(string $search): array
    {
        return Music::visibleTo(Auth::user())
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->forCurrentGenre()
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(fn ($music) => [
                'id' => $music->id,
                'title' => $music->title,
                'subtitle' => $music->subtitle,
                'custom_id' => $music->custom_id,
                'collections_count' => $music->collections_count ?? $music->collections()->count(),
                'verifications_count' => $music->verifications()->count(),
            ])
            ->toArray();
    }

    /**
     * Select music for verification.
     */
    #[On('music-selected.verifyMusic')]
    public function selectMusic(int $musicId): void
    {
        $music = Music::with(['collections', 'genres', 'urls', 'authors', 'relatedMusic'])
            ->visibleTo(Auth::user())
            ->findOrFail($musicId);

        $this->authorize('view', $music);

        $this->musicId = $musicId;
        $this->music = $music;
        $this->loadExistingVerifications();
        $this->showVerification = true;
    }

    /**
     * Verify a field.
     */
    public function verifyField(string $fieldName, ?int $pivotReference, string $status, ?string $notes = null): void
    {
        $this->authorize('create', MusicVerification::class);

        // Validate status
        $allowedStatuses = ['verified', 'rejected', 'empty'];
        if (! in_array($status, $allowedStatuses)) {
            $this->dispatch('error', message: __('Invalid verification status.'));

            return;
        }

        // Validate notes length
        if ($notes && strlen($notes) > 1000) {
            $this->dispatch('error', message: __('Notes must be less than 1000 characters.'));

            return;
        }

        // Ensure music is selected
        if (! $this->musicId || ! $this->music) {
            $this->dispatch('error', message: __('No music selected.'));

            return;
        }

        $key = $this->getVerificationKey($fieldName, $pivotReference);

        // Find existing verification or create new
        $verification = $this->verifications[$key] ?? new MusicVerification([
            'music_id' => $this->musicId,
            'field_name' => $fieldName,
            'pivot_reference' => $pivotReference,
            'verifier_id' => Auth::id(),
        ]);

        // Update verification
        $verification->status = $status;
        $verification->notes = $notes ?? $verification->notes;
        $verification->verified_at = now();
        $verification->save();

        // Update local state
        $this->verifications[$key] = $verification;
        $this->fieldStatuses[$key] = $status;
        $this->fieldNotes[$key] = $notes ?? '';

        $this->dispatch('verification-updated', message: __('Verification saved.'));
    }

    /**
     * Batch verify all pending fields.
     */
    public function verifyAll(string $status, ?string $notes = null): void
    {
        $this->authorize('create', MusicVerification::class);

        $fields = $this->getVerifiableFields();
        $count = 0;

        foreach ($fields as $field) {
            $key = $this->getVerificationKey($field['name'], $field['pivot_reference'] ?? null);
            if (($this->fieldStatuses[$key] ?? 'pending') === 'pending') {
                $this->verifyField($field['name'], $field['pivot_reference'] ?? null, $status, $notes);
                $count++;
            }
        }

        $this->dispatch('verification-updated', message: __('Verified :count fields.', ['count' => $count]));
    }

    /**
     * Reset the component state.
     */
    public function resetSelection(): void
    {
        $this->musicId = null;
        $this->music = null;
        $this->showVerification = false;
        $this->verifications = [];
        $this->fieldStatuses = [];
        $this->fieldNotes = [];
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $searchResults = $this->search ? $this->searchMusic($this->search) : [];
        $verifiableFields = $this->music ? $this->getVerifiableFields() : [];
        $verificationStats = $this->music ? $this->calculateVerificationStats() : [];

        return view('livewire.pages.editor.music-verifier', [
            'searchResults' => $searchResults,
            'verifiableFields' => $verifiableFields,
            'verificationStats' => $verificationStats,
        ]);
    }

    /**
     * Calculate verification statistics.
     */
    private function calculateVerificationStats(): array
    {
        $total = 0;
        $verified = 0;
        $rejected = 0;
        $pending = 0;
        $empty = 0;

        foreach ($this->fieldStatuses as $status) {
            $total++;
            switch ($status) {
                case 'verified':
                    $verified++;
                    break;
                case 'rejected':
                    $rejected++;
                    break;
                case 'empty':
                    $empty++;
                    break;
                default:
                    $pending++;
                    break;
            }
        }

        return [
            'total' => $total,
            'verified' => $verified,
            'rejected' => $rejected,
            'pending' => $pending,
            'empty' => $empty,
            'progress' => $total > 0 ? round(($verified + $rejected + $empty) / $total * 100) : 0,
        ];
    }
}
