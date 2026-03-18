<?php

use App\Models\MusicPlanTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin->assignRole($adminRole);
});

test('priority is stored when creating a template', function () {
    $template = MusicPlanTemplate::factory()->withPriority(3)->create();

    expect($template->priority)->toBe(3);
});

test('priority defaults to null', function () {
    $template = MusicPlanTemplate::factory()->create();

    expect($template->priority)->toBeNull();
});

test('admin can set priority when creating a template via livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class)
        ->set('name', 'Test Template')
        ->set('priority', 5)
        ->call('create')
        ->assertHasNoErrors();

    $template = MusicPlanTemplate::where('name', 'Test Template')->first();
    expect($template)->not->toBeNull();
    expect($template->priority)->toBe(5);
});

test('admin can update priority on an existing template via livewire', function () {
    $template = MusicPlanTemplate::factory()->withPriority(10)->create();

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class)
        ->call('showEdit', $template->id)
        ->set('priority', 2)
        ->call('update')
        ->assertHasNoErrors();

    expect($template->fresh()->priority)->toBe(2);
});

test('admin can clear priority on a template via livewire', function () {
    $template = MusicPlanTemplate::factory()->withPriority(10)->create();

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class)
        ->call('showEdit', $template->id)
        ->set('priority', null)
        ->call('update')
        ->assertHasNoErrors();

    expect($template->fresh()->priority)->toBeNull();
});

test('priority must be at least 1', function () {
    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class)
        ->set('name', 'Test Template')
        ->set('priority', 0)
        ->call('create')
        ->assertHasErrors(['priority']);
});

test('templates are sorted by priority then name in admin list', function () {
    MusicPlanTemplate::factory()->withPriority(3)->create(['name' => 'C Template']);
    MusicPlanTemplate::factory()->withPriority(1)->create(['name' => 'A Template']);
    MusicPlanTemplate::factory()->withPriority(2)->create(['name' => 'B Template']);
    MusicPlanTemplate::factory()->create(['name' => 'Z No Priority']);

    $component = Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class);

    $templates = $component->viewData('templates');
    $names = $templates->pluck('name')->values()->toArray();

    expect($names[0])->toBe('A Template');
    expect($names[1])->toBe('B Template');
    expect($names[2])->toBe('C Template');
    expect($names[3])->toBe('Z No Priority');
});

test('templates without priority are sorted after those with priority', function () {
    MusicPlanTemplate::factory()->create(['name' => 'A No Priority']);
    MusicPlanTemplate::factory()->withPriority(99)->create(['name' => 'Z With Priority']);

    $component = Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanTemplates::class);

    $templates = $component->viewData('templates');
    $names = $templates->pluck('name')->values()->toArray();

    expect($names[0])->toBe('Z With Priority');
    expect($names[1])->toBe('A No Priority');
});
