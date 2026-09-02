<?php

use App\Models\Music;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public Music $music;
    public ?int $score = null;
    public ?string $scope_label = null;

    public ?int $popularity = null;

    /** @var array<int, array{label: string, points: int}> */
    public array $score_reasons = [];

    public bool $privateShare = false;

    /** @var array<int, array<string, mixed>> */
    public array $shareScores = [];

    /**
     * @param  array<int, array{label: string, points: int}>  $score_reasons
     * @param  array<int, array<string, mixed>>  $shareScores
     */
    public function mount(Music $music, ?int $score = null, ?string $scope_label = null, array $score_reasons = [], bool $privateShare = false, array $shareScores = [], ?int $popularity = null): void
    {
        $this->music = $music->load(['collections', 'tags', 'authors', 'urls', 'scriptureReferences', 'directMusicRelations.relatedMusic.collections', 'inverseMusicRelations.music.collections', 'publicPreviewScores']);
        $this->score = $score;
        $this->scope_label = $scope_label;
        $this->score_reasons = $score_reasons;
        $this->privateShare = $privateShare;
        $this->shareScores = $shareScores;
        $this->popularity = $popularity;
    }

    #[On('music-updated')]
    #[On('collection-added')]
    #[On('collection-removed')]
    #[On('collection-updated')]
    #[On('tag-added')]
    #[On('tag-removed')]
    #[On('public-preview-revoked')]
    public function refreshMusic(): void
    {
        $this->music->refresh()->load(['collections', 'tags', 'authors', 'urls', 'scriptureReferences', 'directMusicRelations.relatedMusic.collections', 'inverseMusicRelations.music.collections', 'publicPreviewScores']);
    }
}
?>

@placeholder
<flux:skeleton.group animate="shimmer" class="flex items-center gap-4">
    <flux:skeleton class="size-10 rounded-full" />
    <div class="flex-1">
        <flux:skeleton.line />
        <flux:skeleton.line class="w-1/2" />
    </div>
</flux:skeleton.group>
@endplaceholder

<div>
    <x-music.card
        :music="$music"
        :score="$score"
        :scope-label="$scope_label"
        :score-reasons="$score_reasons"
        :private-share="$privateShare"
        :share-scores="$shareScores"
        :popularity="$popularity"
    />
</div>
