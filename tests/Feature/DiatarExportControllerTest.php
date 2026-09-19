<?php

use App\Models\Collection;
use App\Models\DiatarBook;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\DiatarSyncRun;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;

/** @return array<string, mixed> */
function diatarExportScenario(): array
{
    $run = DiatarSyncRun::factory()->create(['source_revision' => 'export-revision']);
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->for($owner)->create(['is_private' => false]);
    $music = Music::factory()->create(['title' => 'Cantores próbaének']);
    $slotPlan = MusicPlanSlotPlan::factory()->for($plan)->create(['sequence' => 1]);
    $assignment = MusicPlanSlotAssignment::factory()->for($slotPlan)->for($music)->create(['music_sequence' => 1]);
    $collection = Collection::factory()->create();
    $music->collections()->attach($collection, ['order_number' => '230']);
    $book = DiatarBook::factory()->for($run, 'syncRun')->create([
        'title' => 'Próba énekeskönyv',
        'source_path' => 'proba.dtx',
    ]);
    $collection->diatarBooks()->attach($book, ['is_default' => true]);
    $song = DiatarSong::factory()->for($book, 'book')->create([
        'title' => '230 Örömünk forrása',
        'reference' => '230',
        'source_order' => 1,
        'last_seen_sync_run_id' => $run->id,
    ]);
    $slide = DiatarSlide::factory()->for($song, 'song')->create([
        'source_order' => 1,
        'external_id' => 'abcdef12',
        'verse_name' => '1. versszak',
        'last_seen_sync_run_id' => $run->id,
    ]);

    return compact('run', 'owner', 'plan', 'assignment', 'song', 'slide');
}

it('downloads a byte-exact DIA file for the authorized owner', function () {
    $scenario = diatarExportScenario();

    $response = $this->actingAs($scenario['owner'])->post(route('music-plans.diatar-export', $scenario['plan']), [
        'catalog_sync_run_id' => (string) $scenario['run']->id,
        'catalog_revision' => 'export-revision',
        'rows' => [[
            'assignment_id' => (string) $scenario['assignment']->id,
            'song_id' => (string) $scenario['song']->id,
            'omitted' => '0',
            'slide_ids' => [(string) $scenario['slide']->id, (string) $scenario['slide']->id],
        ]],
    ]);

    $response->assertDownload('music-plan.dia');
    expect($response->streamedContent())
        ->toContain("diaszam=2\n")
        ->toContain("id=ABCDEF12\n")
        ->toContain("kotet=Próba énekeskönyv\n")
        ->toContain("enek=230 Örömünk forrása\n");
});

it('forbids export by a user who does not own the plan', function () {
    $scenario = diatarExportScenario();

    $this->actingAs(User::factory()->create())
        ->post(route('music-plans.diatar-export', $scenario['plan']), [])
        ->assertForbidden();
});

it('rejects a stale catalogue revision without producing a file', function () {
    $scenario = diatarExportScenario();

    $this->actingAs($scenario['owner'])
        ->from(route('music-plan-editor', $scenario['plan']))
        ->post(route('music-plans.diatar-export', $scenario['plan']), [
            'catalog_sync_run_id' => $scenario['run']->id,
            'catalog_revision' => 'stale-revision',
            'rows' => [[
                'assignment_id' => $scenario['assignment']->id,
                'song_id' => $scenario['song']->id,
                'omitted' => false,
                'slide_ids' => [$scenario['slide']->id],
            ]],
        ])
        ->assertRedirect(route('music-plan-editor', $scenario['plan']))
        ->assertSessionHasErrors('catalog_revision');
});

it('rejects a selection from an older sync run even when the revision is unchanged', function () {
    $scenario = diatarExportScenario();
    DiatarSyncRun::factory()->create(['source_revision' => 'export-revision']);

    $this->actingAs($scenario['owner'])
        ->post(route('music-plans.diatar-export', $scenario['plan']), [
            'catalog_sync_run_id' => $scenario['run']->id,
            'catalog_revision' => 'export-revision',
            'rows' => [[
                'assignment_id' => $scenario['assignment']->id,
                'song_id' => $scenario['song']->id,
                'omitted' => false,
                'slide_ids' => [$scenario['slide']->id],
            ]],
        ])
        ->assertSessionHasErrors('catalog_revision');
});

it('rejects a slide selected from another song', function () {
    $scenario = diatarExportScenario();
    $otherSong = DiatarSong::factory()->for($scenario['song']->book, 'book')->create(['source_order' => 2]);
    $otherSlide = DiatarSlide::factory()->for($otherSong, 'song')->create(['source_order' => 1]);

    $this->actingAs($scenario['owner'])
        ->post(route('music-plans.diatar-export', $scenario['plan']), [
            'catalog_sync_run_id' => $scenario['run']->id,
            'catalog_revision' => 'export-revision',
            'rows' => [[
                'assignment_id' => $scenario['assignment']->id,
                'song_id' => $scenario['song']->id,
                'omitted' => false,
                'slide_ids' => [$otherSlide->id],
            ]],
        ])
        ->assertSessionHasErrors('rows.0.slide_ids');
});

it('allows an assignment to be deliberately omitted', function () {
    $scenario = diatarExportScenario();

    $response = $this->actingAs($scenario['owner'])->post(route('music-plans.diatar-export', $scenario['plan']), [
        'catalog_sync_run_id' => $scenario['run']->id,
        'catalog_revision' => 'export-revision',
        'rows' => [[
            'assignment_id' => $scenario['assignment']->id,
            'song_id' => null,
            'omitted' => true,
            'slide_ids' => [],
        ]],
    ]);

    $response->assertDownload();
    expect($response->streamedContent())->toContain("diaszam=0\n");
});
