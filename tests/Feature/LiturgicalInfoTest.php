<?php

use App\Services\LiturgicalInfoService;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    app()->instance(LiturgicalInfoService::class, Mockery::mock(LiturgicalInfoService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getCelebrations')->andReturn([]);
    }));
});

test('liturgical info can move to previous day', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-14')
        ->call('previousDay')
        ->assertSet('date', '2026-03-13');
});

test('liturgical info can move to previous sunday from a weekday', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-18')
        ->call('previousSunday')
        ->assertSet('date', '2026-03-15');
});

test('liturgical info moves one full week back when already on sunday', function () {
    Livewire::test('liturgical-info')
        ->set('date', '2026-03-15')
        ->call('previousSunday')
        ->assertSet('date', '2026-03-08');
});

test('liturgical info renders compact grouped navigation controls', function () {
    Livewire::test('liturgical-info')
        ->assertSeeInOrder([
            'Előző/következő nap',
            'Előző/következő vasárnap',
        ])
        ->assertSeeHtml('aria-label="Előző nap"')
        ->assertSeeHtml('aria-label="Következő nap"')
        ->assertSeeHtml('aria-label="Előző vasárnap"')
        ->assertSeeHtml('aria-label="Következő vasárnap"');
});
