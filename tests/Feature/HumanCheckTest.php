<?php

use App\Models\Folder;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\User;
use App\Services\HumanVerificationService;
use Illuminate\Support\Carbon;
use RyanChandler\LaravelCloudflareTurnstile\Facades\Turnstile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Turnstile::fake();
});

it('sends a guest opening a score link to the human check', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))
        ->assertRedirect(route('human-check'));
});

it('sends a guest opening a folder link to the human check', function () {
    $folder = Folder::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($folder)->create();

    get(route('folder.loan', ['token' => $loan->token]))
        ->assertRedirect(route('human-check'));
});

it('sends a guest opening a plan link to the human check', function () {
    $plan = MusicPlan::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($plan)->create();

    get(route('music-plan.loan', ['token' => $loan->token]))
        ->assertRedirect(route('human-check'));
});

it('guards the files a lending link reaches, not only its front page', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $scoreFile = ScoreFile::factory()->ready(1)->create(['score_id' => $score->id]);
    $loan = Loan::factory()->of($score)->create(['allow_download' => true]);

    get(route('loan.score.file.download', [
        'token' => $loan->token,
        'score' => $score,
        'scoreFile' => $scoreFile,
    ]))->assertRedirect(route('human-check'));

    get(route('loan.score.file.page', [
        'token' => $loan->token,
        'score' => $score,
        'scoreFile' => $scoreFile,
        'page' => 1,
    ]))->assertRedirect(route('human-check'));

    get(route('score.loan.incipit', ['token' => $loan->token]))
        ->assertRedirect(route('human-check'));
});

it('keeps the wanted link out of the redirect URL', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($score)->create();

    $response = get(route('score.loan', ['token' => $loan->token]));

    expect($response->headers->get('Location'))->not->toContain($loan->token);
});

it('shows the challenge to a guest', function () {
    get(route('human-check'))
        ->assertOk()
        ->assertSee(__('Just checking you are a person'))
        ->assertSeeHtml('cf-turnstile')
        ->assertSeeHtml('name="robots" content="noindex, nofollow"');
});

it('carries the guest on to the link they asked for once they pass', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($score)->create();
    $wanted = route('score.loan', ['token' => $loan->token]);

    get($wanted)->assertRedirect(route('human-check'));

    post(route('human-check.store'), ['cf-turnstile-response' => Turnstile::dummy()])
        ->assertSessionHasNoErrors()
        ->assertRedirect($wanted);

    get($wanted)->assertOk();
});

it('asks only once per session', function () {
    $owner = User::factory()->create();
    $first = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();
    $second = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();

    post(route('human-check.store'), ['cf-turnstile-response' => Turnstile::dummy()]);

    get(route('score.loan', ['token' => $first->token]))->assertOk();
    get(route('score.loan', ['token' => $second->token]))->assertOk();
});

it('keeps a failed challenge on the check page', function () {
    Turnstile::fake()->fail();

    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($score)->create();
    $wanted = route('score.loan', ['token' => $loan->token]);

    get($wanted);

    post(route('human-check.store'), ['cf-turnstile-response' => 'a-forged-token'])
        ->assertSessionHasErrors('cf-turnstile-response');

    get($wanted)->assertRedirect(route('human-check'));
});

it('rejects an answer with no challenge token at all', function () {
    post(route('human-check.store'), [])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(app(HumanVerificationService::class)->isVerified())->toBeFalse();
});

it('never asks a signed-in reader', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs(User::factory()->create());

    get(route('score.loan', ['token' => $loan->token]))->assertOk();
});

it('turns a signed-in visitor away from the challenge page', function () {
    actingAs(User::factory()->create());

    get(route('human-check'))->assertRedirect(route('home'));
});

it('does not ask again someone who has already passed', function () {
    passHumanCheck();

    get(route('human-check'))->assertRedirect(route('home'));
});

it('asks again once the verification has aged out', function () {
    $score = Score::factory()->create(['user_id' => User::factory()->create()->id]);
    $loan = Loan::factory()->of($score)->create();

    passHumanCheck();

    Carbon::setTestNow(Carbon::now()->addHours(25));

    get(route('score.loan', ['token' => $loan->token]))
        ->assertRedirect(route('human-check'));

    Carbon::setTestNow();
});
