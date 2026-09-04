<?php

use App\Models\Music;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;

function editorUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('lets an editor approve someone else’s nomination', function () {
    $publication = ScorePublication::factory()->submitted()->create();

    expect(editorUser()->can('approve', $publication))->toBeTrue();
});

it('does not let a contributor approve anything', function () {
    $publication = ScorePublication::factory()->submitted()->create();
    $contributor = User::factory()->create();
    $contributor->assignRole('contributor');

    expect($contributor->can('approve', $publication))->toBeFalse()
        ->and($contributor->can('viewAny', ScorePublication::class))->toBeFalse();
});

it('does not let an editor approve their own score', function () {
    $editor = editorUser();
    $score = Score::factory()->create(['user_id' => $editor->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    expect($editor->can('approve', $publication))->toBeFalse();
});

it('does not let an editor approve a nomination they submitted', function () {
    $editor = editorUser();
    $publication = ScorePublication::factory()->submitted()->create(['submitted_by' => $editor->id]);

    expect($editor->can('approve', $publication))->toBeFalse();
});

it('offers an admin the recorded self-approval override', function () {
    $admin = adminUser();
    $score = Score::factory()->create(['user_id' => $admin->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    expect($admin->can('approve', $publication))->toBeFalse()
        ->and($admin->can('selfApprove', $publication))->toBeTrue();

    $editor = editorUser();
    expect($editor->can('selfApprove', $publication))->toBeFalse();
});

it('lets the owner withdraw but not review', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->approved()->create();

    expect($score->user->can('withdraw', $publication))->toBeTrue()
        ->and($score->user->can('takeDown', $publication))->toBeFalse()
        ->and($score->user->can('restore', $publication))->toBeFalse();
});

it('requires the review permission to restore a taken down score', function () {
    $publication = ScorePublication::factory()->takenDown()->create();

    expect(editorUser()->can('restore', $publication))->toBeTrue()
        ->and($publication->score->user->can('restore', $publication))->toBeFalse();
});

it('lets an owner nominate their own score', function () {
    $score = Score::factory()->create();

    expect($score->user->can('nominate', $score))->toBeTrue()
        ->and(User::factory()->create()->can('nominate', $score))->toBeFalse();
});

it('refuses to nominate a score with no music attached', function () {
    $score = Score::factory()->unattached()->create();

    expect($score->user->can('nominate', $score))->toBeFalse();
});

it('refuses to nominate a score whose music is private', function () {
    $music = Music::factory()->create(['is_private' => true]);
    $score = Score::factory()->create(['music_id' => $music->id]);

    expect($score->user->can('nominate', $score))->toBeFalse();
});

it('refuses to nominate a links-only score with no files', function () {
    $score = Score::factory()->linksOnly()->create();

    expect($score->user->can('nominate', $score))->toBeFalse();
});
