<?php

use App\Livewire\Projection\ScreenSettings;
use App\Livewire\Projection\ShowStatus;
use App\Models\DeviceName;
use App\Models\Presentation;
use App\Models\Screen;
use App\Models\User;
use App\Services\ShowState;
use App\Support\DeviceId;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * Which of my screens is which.
 *
 * Everything a picker shows belongs to one person, describes itself with the
 * same crude user-agent string, and is live for as long as its tab is open — so
 * the laptop in the church and the laptop at home are the same row to look at.
 * What is checked here is the pair of answers nothing can work out for itself:
 * what this person calls a device, and whether it is a screen at all.
 */

it('keeps one screen for a browser whose session has rotated', function () {
    $user = User::factory()->create();
    $device = (string) Str::uuid();

    $first = Screen::claimFor($user, $device, 'session-before-sunday');
    $second = Screen::claimFor($user, $device, 'session-next-sunday');

    expect(Screen::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->session_id)->toBe('session-next-sunday');
});

it('keeps a different screen for a different browser on the same session', function () {
    $user = User::factory()->create();

    Screen::claimFor($user, (string) Str::uuid(), 'one-session');
    Screen::claimFor($user, (string) Str::uuid(), 'one-session');

    expect(Screen::query()->count())->toBe(2);
});

// Naming is an override, never a step: a screen nobody has named must read
// exactly as it did before any of this existed.
it('falls back to the device description when nothing was typed', function () {
    $screen = Screen::factory()->create([
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
    ]);

    expect($screen->label())->toBe($screen->describeDevice());
});

it('calls a screen what its owner calls it', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    DeviceName::factory()->create([
        'user_id' => $user->id,
        'device_id' => $screen->device_id,
        'name' => 'Parish laptop',
    ]);

    actingAs($user);

    $listed = Screen::query()->withDeviceName()->find($screen->id);

    expect($listed->label())->toBe('Parish laptop');
});

// The name belongs to the pair, not to the machine. Two cantors sharing the
// parish laptop each keep their own answer.
it('does not show one person the name another gave the same device', function () {
    $cantor = User::factory()->create();
    $organist = User::factory()->create();
    $device = (string) Str::uuid();

    $screen = Screen::factory()->create(['user_id' => $organist->id, 'device_id' => $device]);

    DeviceName::factory()->create([
        'user_id' => $cantor->id,
        'device_id' => $device,
        'name' => 'Parish laptop',
    ]);

    actingAs($organist);

    $listed = Screen::query()->withDeviceName()->find($screen->id);

    expect($listed->label())->toBe($screen->describeDevice());
});

// The name outlives the row, which is the whole reason it is not a column on it.
it('remembers the name after the screen it was given on has aged away', function () {
    $user = User::factory()->create();
    $device = (string) Str::uuid();

    $screen = Screen::claimFor($user, $device, 'first-session');

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen])
        ->set('name', 'Parish laptop')
        ->call('save')
        ->assertHasNoErrors();

    Screen::query()->whereKey($screen->id)->forceDelete();

    $returned = Screen::query()->withDeviceName()->find(
        Screen::claimFor($user, $device, 'next-sunday')->id
    );

    expect($returned->label())->toBe('Parish laptop');
});

it('refuses a name too long for a row to keep its shape', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen])
        ->set('name', str_repeat('a', DeviceName::MAX_LENGTH + 1))
        ->call('save')
        ->assertHasErrors(['name' => 'max']);

    expect(DeviceName::query()->count())->toBe(0);
});

it('will not let somebody name a screen that is not theirs', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $stranger->id]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen])
        ->assertForbidden();
});

/*
 * The laptop at home: same account, same device string, genuinely live. Asked
 * once, and right forever afterwards.
 */

it('stops offering a device its owner says is not a screen', function () {
    $user = User::factory()->create();

    $church = Screen::factory()->create(['user_id' => $user->id]);
    $home = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $home])
        ->set('offered', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(ShowState::screensFor($user)->pluck('id')->all())->toBe([$church->id]);
});

/*
 * But saying so takes nothing off the wall. The show belongs to the person, and
 * ending it here would end it on every screen they have — which is not what
 * "the laptop at home is not a screen" means.
 */
it('leaves the show up when a device says it is not a screen', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);
    $home = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $home])
        ->set('offered', false)
        ->call('save');

    expect($presentation->refresh()->isLive())->toBeTrue();
});

// Still, a wall needs its own fit back whatever it has been called.
it('still gives a device that is not offered its own fit', function () {
    $user = User::factory()->create();
    $here = Screen::factory()->create(['user_id' => $user->id, 'device_id' => DeviceId::current()]);

    DeviceName::factory()->notAScreen()->create([
        'user_id' => $user->id,
        'device_id' => $here->device_id,
    ]);

    actingAs($user);

    expect(ShowState::screensFor($user)->pluck('id')->all())->toBe([$here->id]);
});

it('offers a device again when its owner changes their mind', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    DeviceName::factory()->notAScreen()->create([
        'user_id' => $user->id,
        'device_id' => $screen->device_id,
    ]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen])
        ->call('openSettings')
        ->assertSet('offered', false)
        ->set('offered', true)
        ->call('save');

    expect(ShowState::screensFor($user)->pluck('id')->all())->toBe([$screen->id]);
});

// The presenter holds only its own screen and loads no labels, so the lazy path
// has to carry the same constraint the eager one does.
it('does not leak another person\'s name to a screen loaded on its own', function () {
    $cantor = User::factory()->create();
    $organist = User::factory()->create();
    $device = (string) Str::uuid();

    $screen = Screen::factory()->create(['user_id' => $organist->id, 'device_id' => $device]);

    DeviceName::factory()->create([
        'user_id' => $cantor->id,
        'device_id' => $device,
        'name' => 'Parish laptop',
    ]);

    expect(Screen::query()->find($screen->id)->label())->toBe($screen->describeDevice());
});

/*
 * A name typed and then not shown is a name that looks like it was not saved.
 *
 * Where the name is a line of its own — at the laptop, and above the deck list
 * on the phone — the pencil's own component says it, so that saving one is
 * visible without the page around it being re-rendered. The presenter must never
 * be re-rendered; that is what the whole stage is built around.
 */

it('says the new name the moment it is saved', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen, 'showLabel' => true])
        ->assertSee($screen->describeDevice())
        ->set('name', 'Parish laptop')
        ->call('save')
        ->assertSee('Parish laptop');
});

// And says nothing where the row around it already carries the name.
it('leaves the name to the row that is already showing it', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    DeviceName::factory()->create([
        'user_id' => $user->id,
        'device_id' => $screen->device_id,
        'name' => 'Parish laptop',
    ]);

    actingAs($user);

    Livewire::test(ScreenSettings::class, ['screen' => $screen])
        ->assertDontSee('Parish laptop');
});

// The remote's status line is where the name is said inside a line of its own,
// so a rename there does have to reach the line around the pencil.
it('reads the status line again when a screen in it is renamed', function () {
    $user = User::factory()->create();

    $church = Screen::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $status = Livewire::test(ShowStatus::class)
        ->assertDontSee('Parish laptop');

    DeviceName::factory()->create([
        'user_id' => $user->id,
        'device_id' => $church->device_id,
        'name' => 'Parish laptop',
    ]);

    $status->dispatch('screen-renamed')->assertSee('Parish laptop');
});
