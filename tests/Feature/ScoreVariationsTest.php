<?php

use App\Livewire\Pages\ScoreEditor;
use App\Models\Music;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('relatedScores returns empty when no music is attached', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    $related = Livewire::test(ScoreEditor::class, ['score' => $score])
        ->instance()->relatedScores;

    expect($related)->toBeEmpty();
});

it('relatedScores returns empty in shared-link mode', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    $component = Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('isSharedLink', true);

    expect($component->instance()->relatedScores)->toBeEmpty();
});

it('relatedScores returns siblings sharing the same music_id', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    $sibling = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id, 'title' => 'Choir Version']);

    actingAs($user);

    $related = Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->instance()->relatedScores;

    expect($related)->toHaveCount(1)
        ->and($related->first()->id)->toBe($sibling->id);
});

it('relatedScores excludes the score currently being edited', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    $related = Livewire::test(ScoreEditor::class, ['score' => $score])
        ->instance()->relatedScores;

    expect($related)->toBeEmpty();
});

it('relatedScores excludes scores owned by other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $music = Music::factory()->create();

    $myScore = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    Score::factory()->create(['user_id' => $other->id, 'music_id' => $music->id, 'title' => 'Not Mine']);

    actingAs($user);

    $related = Livewire::test(ScoreEditor::class, ['score' => $myScore])
        ->instance()->relatedScores;

    expect($related)->toBeEmpty();
});

it('relatedScores is ordered by updated_at descending', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    $older = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id, 'title' => 'Older', 'updated_at' => now()->subDay()]);
    $newer = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id, 'title' => 'Newer', 'updated_at' => now()]);

    actingAs($user);

    $related = Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->instance()->relatedScores;

    expect($related->first()->id)->toBe($newer->id)
        ->and($related->last()->id)->toBe($older->id);
});

it('variations panel is not rendered when no music is attached', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertDontSee(__('Variations'));
});

it('variations panel offers Add variation even when there are no siblings', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Variations'))
        ->assertSee(__('Add variation'))
        ->assertSeeHtml('addVariation()');
});

it('names a variation and stores it on save', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('variationName', 'Fuvola')
        ->call('save')
        ->assertHasNoErrors();

    expect($score->fresh()->variation_name)->toBe('Fuvola');
});

it('stores a blank variation name as null', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'variation_name' => 'Kórus',
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSet('variationName', 'Kórus')
        ->set('variationName', '   ')
        ->call('save')
        ->assertHasNoErrors();

    expect($score->fresh()->variation_name)->toBeNull();
});

it('rejects a variation name longer than the column holds', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->set('variationName', str_repeat('a', 121))
        ->call('save')
        ->assertHasErrors(['variationName']);
});

it('labels a sibling by its variation name rather than its title', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    Score::factory()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Ave Maria',
        'variation_name' => 'Csak szöveg',
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->assertSee('Csak szöveg');
});

it('falls back to the title for a sibling with no variation name', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    Score::factory()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Unnamed Sibling',
        'variation_name' => null,
    ]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->assertSee('Unnamed Sibling');
});

it('variations panel includes link to sibling edit page', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    $sibling = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->assertSeeHtml(route('scores.edit', $sibling));
});

it('variations panel includes Add variation link', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create();

    $editing = Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);
    Score::factory()->create(['user_id' => $user->id, 'music_id' => $music->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $editing])
        ->assertSee(__('Add variation'))
        ->assertSeeHtml('addVariation()');
});

it('variations panel is not rendered for a guest on the preview page', function () {
    Livewire::test(ScoreEditor::class)
        ->assertDontSee(__('Variations'));
});
