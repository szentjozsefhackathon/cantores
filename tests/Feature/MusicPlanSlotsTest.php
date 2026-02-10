<?php

use App\Models\MusicPlanSlot;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->city1 = City::firstOrCreate(['name' => 'Test City Slots A']);
    $this->city2 = City::firstOrCreate(['name' => 'Test City Slots B']);
    $this->firstName1 = FirstName::firstOrCreate(['name' => 'Test Slots First A'], ['gender' => 'male']);
    $this->firstName2 = FirstName::firstOrCreate(['name' => 'Test Slots First B'], ['gender' => 'female']);

    $this->admin = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,,
        'email' => 'admin@example.com'
    ]);

    $this->user = User::factory()->create(
            'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,,
    
    ['email' => 'user@example.com']);
});

test('guests cannot access music plan slots admin page', function () {
    $response = $this->get(route('admin.music-plan-slots'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access music plan slots admin page', function () {
    $this->actingAs($this->user);
    $response = $this->get(route('admin.music-plan-slots'));
    $response->assertForbidden();
});

test('admin users can access music plan slots admin page', function () {
    $this->actingAs($this->admin);
    $response = $this->get(route('admin.music-plan-slots'));
    $response->assertSuccessful();
});

test('admin can view music plan slots', function () {
    $slot = MusicPlanSlot::factory()->create();

    $this->actingAs($this->admin);
    $response = $this->get(route('admin.music-plan-slots'));
    $response->assertSee($slot->name);
});

test('admin can create a music plan slot', function () {
    $data = [
        'name' => 'Entrance Procession',
        'description' => 'The opening song for Mass',
    ];

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanSlots::class)
        ->call('showCreate')
        ->set('name', $data['name'])
        ->set('description', $data['description'])
        ->call('create');

    $this->assertDatabaseHas('music_plan_slots', $data);
});

test('admin can update a music plan slot', function () {
    $slot = MusicPlanSlot::factory()->create();
    $newName = 'Updated Slot Name';

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanSlots::class)
        ->call('showEdit', $slot)
        ->set('name', $newName)
        ->call('update');

    $this->assertDatabaseHas('music_plan_slots', [
        'id' => $slot->id,
        'name' => $newName,
    ]);
});

test('admin can delete a music plan slot', function () {
    $slot = MusicPlanSlot::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanSlots::class)
        ->call('delete', $slot);

    $this->assertSoftDeleted('music_plan_slots', ['id' => $slot->id]);
});

test('slot name must be unique', function () {
    $existingSlot = MusicPlanSlot::factory()->create(['name' => 'Kyrie']);

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanSlots::class)
        ->call('showCreate')
        ->set('name', 'Kyrie') // Same name
        ->set('description', 'Different description')
        ->call('create')
        ->assertHasErrors(['name']);
});

test('soft deleted slots are not shown in active list', function () {
    $activeSlot = MusicPlanSlot::factory()->create();
    $deletedSlot = MusicPlanSlot::factory()->create();
    $deletedSlot->delete();

    $this->actingAs($this->admin);
    $response = $this->get(route('admin.music-plan-slots'));
    $response->assertSee($activeSlot->name);
    $response->assertDontSee($deletedSlot->name);
});

test('search functionality works', function () {
    $slot1 = MusicPlanSlot::factory()->create(['name' => 'Entrance Procession']);
    $slot2 = MusicPlanSlot::factory()->create(['name' => 'Kyrie']);

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Pages\Admin\MusicPlanSlots::class)
        ->set('search', 'Entrance')
        ->assertSee($slot1->name)
        ->assertDontSee($slot2->name);
});
