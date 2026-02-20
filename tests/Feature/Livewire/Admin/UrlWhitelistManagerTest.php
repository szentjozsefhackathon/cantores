<?php

use App\Models\User;
use App\Models\WhitelistRule;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin->assignRole($adminRole);

    $this->user = User::factory()->create();
});

test('admin can access url whitelist manager', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->assertStatus(200)
        ->assertSee('URL Whitelist Manager');
});

test('non-admin cannot access url whitelist manager', function () {
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->assertForbidden();
});

test('guest cannot access url whitelist manager', function () {
    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->assertForbidden();
});

test('lists whitelist rules', function () {
    $this->actingAs($this->admin);
    $rule = WhitelistRule::factory()->create();

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->assertSee($rule->hostname)
        ->assertSee($rule->path_prefix);
});

test('filters rules by search', function () {
    $this->actingAs($this->admin);
    WhitelistRule::factory()->create(['hostname' => 'example.com']);
    WhitelistRule::factory()->create(['hostname' => 'test.com']);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('search', 'example')
        ->assertSee('example.com')
        ->assertDontSee('test.com');
});

test('filters rules by status', function () {
    $this->actingAs($this->admin);
    WhitelistRule::factory()->create(['hostname' => 'active.com', 'is_active' => true]);
    WhitelistRule::factory()->create(['hostname' => 'inactive.com', 'is_active' => false]);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('statusFilter', 'active')
        ->assertSee('active.com')
        ->assertDontSee('inactive.com');
});

test('creates new whitelist rule', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('create')
        ->assertSet('showCreateModal', true)
        ->set('form.hostname', 'new-example.com')
        ->set('form.path_prefix', '/api')
        ->set('form.scheme', 'https')
        ->set('form.description', 'Test rule')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('showCreateModal', false);

    $this->assertDatabaseHas('whitelist_rules', [
        'hostname' => 'new-example.com',
        'path_prefix' => '/api',
        'scheme' => 'https',
        'description' => 'Test rule',
        'is_active' => true,
    ]);
});

test('validates required fields when creating rule', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('create')
        ->set('form.hostname', '')
        ->call('save')
        ->assertHasErrors(['form.hostname' => 'required']);
});

test('validates hostname format', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('create')
        ->set('form.hostname', 'invalid host!')
        ->call('save')
        ->assertHasErrors(['form.hostname' => 'regex']);
});

test('validates path prefix format', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('create')
        ->set('form.path_prefix', 'invalid path')
        ->call('save')
        ->assertHasErrors(['form.path_prefix' => 'regex']);
});

test('edits existing whitelist rule', function () {
    $this->actingAs($this->admin);
    $rule = WhitelistRule::factory()->create(['description' => 'Old description']);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('edit', $rule->id)
        ->assertSet('editingId', $rule->id)
        ->assertSet('form.hostname', $rule->hostname)
        ->set('form.description', 'Updated description')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $this->assertDatabaseHas('whitelist_rules', [
        'id' => $rule->id,
        'description' => 'Updated description',
    ]);
});

test('prevents duplicate rule creation', function () {
    $this->actingAs($this->admin);
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/api',
        'scheme' => 'https',
    ]);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('create')
        ->set('form.hostname', 'example.com')
        ->set('form.path_prefix', '/api')
        ->set('form.scheme', 'https')
        ->call('save')
        ->assertHasErrors(['form.hostname']);
});

test('deletes whitelist rule', function () {
    $this->actingAs($this->admin);
    $rule = WhitelistRule::factory()->create();

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->call('confirmDelete', $rule->id)
        ->assertSet('deletingId', $rule->id)
        ->assertSet('showDeleteModal', true)
        ->call('delete')
        ->assertSet('showDeleteModal', false)
        ->assertDispatched('notify');

    $this->assertSoftDeleted('whitelist_rules', ['id' => $rule->id]);
});

test('bulk activates selected rules', function () {
    $this->actingAs($this->admin);
    $rule1 = WhitelistRule::factory()->create(['is_active' => false]);
    $rule2 = WhitelistRule::factory()->create(['is_active' => false]);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('selectedRules', [$rule1->id, $rule2->id])
        ->call('bulkActivate')
        ->assertDispatched('notify')
        ->assertSet('selectedRules', []);

    $this->assertDatabaseHas('whitelist_rules', ['id' => $rule1->id, 'is_active' => true]);
    $this->assertDatabaseHas('whitelist_rules', ['id' => $rule2->id, 'is_active' => true]);
});

test('bulk deactivates selected rules', function () {
    $this->actingAs($this->admin);
    $rule1 = WhitelistRule::factory()->create(['is_active' => true]);
    $rule2 = WhitelistRule::factory()->create(['is_active' => true]);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('selectedRules', [$rule1->id, $rule2->id])
        ->call('bulkDeactivate')
        ->assertDispatched('notify')
        ->assertSet('selectedRules', []);

    $this->assertDatabaseHas('whitelist_rules', ['id' => $rule1->id, 'is_active' => false]);
    $this->assertDatabaseHas('whitelist_rules', ['id' => $rule2->id, 'is_active' => false]);
});

test('bulk deletes selected rules', function () {
    $this->actingAs($this->admin);
    $rule1 = WhitelistRule::factory()->create();
    $rule2 = WhitelistRule::factory()->create();

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('selectedRules', [$rule1->id, $rule2->id])
        ->call('bulkDelete')
        ->assertDispatched('notify')
        ->assertSet('selectedRules', []);

    $this->assertSoftDeleted('whitelist_rules', ['id' => $rule1->id]);
    $this->assertSoftDeleted('whitelist_rules', ['id' => $rule2->id]);
});

test('tests URL against whitelist rules', function () {
    $this->actingAs($this->admin);
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('testUrl', 'https://example.com/music/song')
        ->call('testRule')
        ->assertSet('testResult.matches', true)
        ->assertSet('testResult.message', 'URL matches whitelist rules.');
});

test('test URL shows failure for non-whitelisted URL', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('testUrl', 'https://not-whitelisted.com/path')
        ->call('testRule')
        ->assertSet('testResult.matches', false)
        ->assertSet('testResult.message', 'URL does NOT match any whitelist rule.');
});

test('test URL validates URL format', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('testUrl', 'not-a-url')
        ->call('testRule')
        ->assertHasErrors(['testUrl' => 'url']);
});

test('test URL handles invalid URL gracefully', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('testUrl', 'https://user:pass@example.com/')
        ->call('testRule')
        ->assertSet('testResult.matches', false)
        ->assertSet('testResult.message', 'Invalid URL: URL must not contain userinfo');
});

test('shows matching URLs count', function () {
    $this->actingAs($this->admin);
    $rule = WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'scheme' => 'https',
    ]);

    // Create some music URLs that would match
    \App\Models\MusicUrl::factory()->create(['url' => 'https://example.com/music/song1']);
    \App\Models\MusicUrl::factory()->create(['url' => 'https://example.com/music/song2']);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->assertSet('matchingUrlsCount.'.$rule->id, 2);
});

test('resets form correctly', function () {
    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Pages\Admin\UrlWhitelistManager::class)
        ->set('form.hostname', 'test.com')
        ->set('form.description', 'Test')
        ->call('resetForm')
        ->assertSet('form.hostname', '')
        ->assertSet('form.description', '')
        ->assertSet('editingId', null);
});
