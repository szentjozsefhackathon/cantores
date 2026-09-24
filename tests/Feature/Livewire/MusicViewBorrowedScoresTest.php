<?php

use App\Livewire\Pages\MusicView;
use App\Models\Loan;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\LoanKeepingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->borrower = User::factory()->create();
    $this->music = Music::factory()->create();
    $this->score = Score::factory()->abc()->create([
        'user_id' => $this->owner->id,
        'music_id' => $this->music->id,
        'title' => 'Lent arrangement',
    ]);
    $this->loan = Loan::factory()->of($this->score)->create();
});

test('a score kept out of a live loan is listed with its loan link', function () {
    app(LoanKeepingService::class)->keep($this->loan, $this->borrower);

    Livewire::actingAs($this->borrower)
        ->test(MusicView::class, ['music' => $this->music])
        ->assertSee(__('Borrowed scores'))
        ->assertSee('Lent arrangement')
        ->assertSee(route('loan.score', ['token' => $this->loan->token, 'score' => $this->score->id]), false);
});

test('a score merely opened but not kept is not listed', function () {
    app(LoanKeepingService::class)->recordOpen($this->loan, $this->borrower);

    Livewire::actingAs($this->borrower)
        ->test(MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Borrowed scores'))
        ->assertDontSee('Lent arrangement');
});

test('a borrowed score disappears once the loan is revoked', function () {
    app(LoanKeepingService::class)->keep($this->loan, $this->borrower);
    $this->loan->revoke();

    Livewire::actingAs($this->borrower)
        ->test(MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Borrowed scores'))
        ->assertDontSee('Lent arrangement');
});

test('a borrowed score that is also published is left to the library section', function () {
    app(LoanKeepingService::class)->keep($this->loan, $this->borrower);
    ScorePublication::factory()->approved()->create(['score_id' => $this->score->id]);

    Livewire::actingAs($this->borrower)
        ->test(MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Borrowed scores'));
});

test('the lender does not see their own score as borrowed', function () {
    Livewire::actingAs($this->owner)
        ->test(MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Borrowed scores'))
        ->assertSee('Lent arrangement');
});
