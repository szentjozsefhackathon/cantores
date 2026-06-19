<?php

use App\Livewire\Pages\Editor\ExternalLinks;
use App\Models\ExternalLink;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\get;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'masterdata.maintain', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web'])
        ->givePermissionTo('masterdata.maintain');

    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');

    $this->contributor = User::factory()->create();
});

test('editor can view the external links manager', function () {
    Livewire::actingAs($this->editor)
        ->test(ExternalLinks::class)
        ->assertOk();
});

test('non-editor cannot view the external links manager', function () {
    Livewire::actingAs($this->contributor)
        ->test(ExternalLinks::class)
        ->assertForbidden();
});

test('editor can create an external link', function () {
    Livewire::actingAs($this->editor)
        ->test(ExternalLinks::class)
        ->call('create')
        ->set('title', 'Villanyhárfa')
        ->set('description', 'A Villanyhárfa egy katolikus liturgikus zenei portál.')
        ->set('url', 'https://www.villanyharfa.hu')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect(ExternalLink::where('title', 'Villanyhárfa')->exists())->toBeTrue();
});

test('creating a link validates required fields and url format', function () {
    Livewire::actingAs($this->editor)
        ->test(ExternalLinks::class)
        ->call('create')
        ->set('title', '')
        ->set('description', '')
        ->set('url', 'not-a-url')
        ->call('save')
        ->assertHasErrors([
            'title' => 'required',
            'description' => 'required',
            'url' => 'url',
        ]);
});

test('editor can update an external link', function () {
    $link = ExternalLink::factory()->create(['title' => 'Old title']);

    Livewire::actingAs($this->editor)
        ->test(ExternalLinks::class)
        ->call('edit', $link->id)
        ->assertSet('title', 'Old title')
        ->set('title', 'New title')
        ->call('save')
        ->assertHasNoErrors();

    expect($link->fresh()->title)->toBe('New title');
});

test('editor can delete an external link', function () {
    $link = ExternalLink::factory()->create();

    Livewire::actingAs($this->editor)
        ->test(ExternalLinks::class)
        ->call('delete', $link->id);

    expect(ExternalLink::find($link->id))->toBeNull();
});

test('external links are shown on the public home page', function () {
    ExternalLink::factory()->create([
        'title' => 'Villanyhárfa',
        'description' => 'Katolikus liturgikus zenei portál.',
        'url' => 'https://www.villanyharfa.hu',
    ]);

    get(route('home'))
        ->assertOk()
        ->assertSee('Villanyhárfa')
        ->assertSee('https://www.villanyharfa.hu');
});

test('external links manager requires authentication', function () {
    get(route('external-links'))->assertRedirect(route('login'));
});
