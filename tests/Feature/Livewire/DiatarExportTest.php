<?php

use App\Livewire\DiatarExport;
use App\Livewire\DiatarMusicBindingEditor;
use App\Models\DiatarBook;
use App\Models\DiatarMusicBinding;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\DiatarSyncRun;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use App\Services\Diatar\DiatarPlanSuggestionService;
use Livewire\Livewire;

/** @return array<string, mixed> */
function livewireDiatarScenario(int $occurrences = 1): array
{
    $run = DiatarSyncRun::factory()->create(['source_revision' => 'dialog-revision']);
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->for($owner)->create(['is_private' => false]);
    $music = Music::factory()->create(['title' => 'Ismétlődő próbaének']);
    $slotPlan = MusicPlanSlotPlan::factory()->for($plan)->create(['sequence' => 1]);
    foreach (range(1, $occurrences) as $sequence) {
        $assignment = MusicPlanSlotAssignment::factory()->for($slotPlan)->for($music)->create(['music_sequence' => $sequence]);
    }
    $book = DiatarBook::factory()->for($run, 'syncRun')->create(['source_path' => 'dialog.dtx']);
    $song = DiatarSong::factory()->for($book, 'book')->create(['source_order' => 1]);
    $slides = collect([
        DiatarSlide::factory()->for($song, 'song')->create(['source_order' => 1, 'external_id' => '1111AAAA', 'verse_name' => '1']),
        DiatarSlide::factory()->for($song, 'song')->create(['source_order' => 2, 'external_id' => '2222BBBB', 'verse_name' => '2']),
    ]);
    DiatarMusicBinding::factory()->for($music)->for($song, 'song')->create();

    return compact('run', 'owner', 'plan', 'music', 'assignment', 'song', 'slides');
}

it('opens with the suggested source and all slides in source order', function () {
    $scenario = livewireDiatarScenario();

    Livewire::actingAs($scenario['owner'])
        ->test(DiatarExport::class, ['musicPlan' => $scenario['plan']])
        ->call('open')
        ->assertSet('show', true)
        ->assertSet('catalogSyncRunId', $scenario['song']->last_seen_sync_run_id)
        ->assertSet('catalogRevision', 'dialog-revision')
        ->assertSet('rows.0.selected_song_id', $scenario['song']->id)
        ->assertSet('rows.0.selected_slide_ids', $scenario['slides']->pluck('id')->all())
        ->assertSee('dialog.dtx');
});

it('supports reordering repetition and removal without persisting review state', function () {
    $scenario = livewireDiatarScenario();
    $originalUpdatedAt = $scenario['plan']->updated_at;

    Livewire::actingAs($scenario['owner'])
        ->test(DiatarExport::class, ['musicPlan' => $scenario['plan']])
        ->call('open')
        ->call('moveSlide', 0, 1, -1)
        ->call('repeatSlide', 0, 0)
        ->call('removeSlide', 0, 2)
        ->assertSet('rows.0.selected_slide_ids', [
            $scenario['slides'][1]->id,
            $scenario['slides'][1]->id,
        ]);

    expect($scenario['plan']->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue()
        ->and(DiatarMusicBinding::query()->count())->toBe(1);
});

it('keeps repeated assignment occurrences independent', function () {
    $scenario = livewireDiatarScenario(2);

    Livewire::actingAs($scenario['owner'])
        ->test(DiatarExport::class, ['musicPlan' => $scenario['plan']])
        ->call('open')
        ->call('removeSlide', 0, 1)
        ->assertSet('rows.0.selected_slide_ids', [$scenario['slides'][0]->id])
        ->assertSet('rows.1.selected_slide_ids', $scenario['slides']->pluck('id')->all());
});

it('requires a published plan before opening export review', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->for($owner)->create(['is_private' => true]);

    Livewire::actingAs($owner)
        ->test(DiatarExport::class, ['musicPlan' => $plan])
        ->call('open')
        ->assertSet('show', false)
        ->assertHasErrors('plan');
});

it('uses the refreshed catalogue token after a stale export submission', function () {
    $scenario = livewireDiatarScenario();
    $refreshedRun = DiatarSyncRun::factory()->create(['source_revision' => 'refreshed-revision']);
    $session = app('session.store');
    $session->put('_old_input', [
        'catalog_sync_run_id' => $scenario['run']->id,
        'catalog_revision' => 'dialog-revision',
        'rows' => [[
            'assignment_id' => $scenario['assignment']->id,
            'song_id' => $scenario['song']->id,
            'omitted' => false,
            'slide_ids' => [$scenario['slides'][1]->id],
        ]],
    ]);
    request()->setLaravelSession($session);

    $component = app(DiatarExport::class);
    $component->mount($scenario['plan'], app(DiatarPlanSuggestionService::class));

    expect($component->show)->toBeTrue()
        ->and($component->catalogSyncRunId)->toBe($refreshedRun->id)
        ->and($component->catalogRevision)->toBe('refreshed-revision')
        ->and($component->rows[0]['selected_song_id'])->toBe($scenario['song']->id)
        ->and($component->rows[0]['selected_slide_ids'])->toBe([$scenario['slides'][1]->id]);
});

it('lets an editor curate and repeat slides in an exceptional binding', function () {
    $scenario = livewireDiatarScenario();
    $scenario['music']->diatarBindings()->delete();
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    Livewire::actingAs($editor)
        ->test(DiatarMusicBindingEditor::class, ['music' => $scenario['music']])
        ->call('selectSong', $scenario['song']->id)
        ->call('repeatSlide', 0)
        ->set('editorNote', 'Kivételes forrás')
        ->call('save')
        ->assertHasNoErrors();

    $binding = $scenario['music']->diatarBindings()->where('is_active', true)->with('slides')->first();
    expect($binding)->not->toBeNull()
        ->and($binding->editor_note)->toBe('Kivételes forrás')
        ->and($binding->slides->pluck('diatar_slide_id')->all())->toBe([
            $scenario['slides'][0]->id,
            $scenario['slides'][0]->id,
            $scenario['slides'][1]->id,
        ]);
});
