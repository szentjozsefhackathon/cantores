<?php

namespace App\Livewire\Projection;

use App\Models\DeviceName;
use App\Models\Screen;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * What this person calls this device, and whether it is a screen at all.
 *
 * Two answers nothing can work out for itself. Every screen in a picker belongs
 * to the same person by construction, describes itself with the same crude
 * user-agent string, and is genuinely live while its tab is open — so the laptop
 * at home and the laptop in the church are indistinguishable, and the only thing
 * that tells them apart is somebody saying which is which.
 *
 * Asked once and right forever, which is the whole reason it is a stored answer
 * rather than an inference. Both live on the device and not on the screen row,
 * so a laptop coming back next Sunday under a new session is already called what
 * it was called.
 *
 * Reachable from either end: at the laptop while it waits for a deck, and from
 * the phone, which is the end with a keyboard and both hands free.
 */
class ScreenSettings extends Component
{
    use AuthorizesRequests;

    public Screen $screen;

    /**
     * Light chrome for the phone, and pale-on-black for the presenter, which is
     * a black page whose whole point is that nothing on it is bright.
     */
    public bool $onBlack = false;

    /**
     * Whether to say the screen's name beside the pencil.
     *
     * Where the name is a line of its own — at the laptop, and above the deck
     * list on the phone — it is said here rather than by the page around it, so
     * that saving a new one is visible the moment it is saved. A page that says
     * it for itself has no reason to be re-rendered by a rename, and the
     * presenter in particular must never be.
     */
    public bool $showLabel = false;

    public bool $open = false;

    public ?string $name = null;

    /**
     * Whether to offer this device as somewhere a deck can be sent. Off is the
     * laptop at home saying so, once.
     */
    public bool $offered = true;

    public function mount(Screen $screen, bool $onBlack = false, bool $showLabel = false): void
    {
        $this->authorize('update', $screen);

        $this->screen = $screen;
        $this->onBlack = $onBlack;
        $this->showLabel = $showLabel;

        $this->readDevice();
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:'.DeviceName::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.max' => __('A screen name is at most :count characters.', ['count' => DeviceName::MAX_LENGTH]),
        ];
    }

    public function save(): void
    {
        $this->authorize('update', $this->screen);

        $this->validate();

        $name = trim((string) $this->name);

        $device = DeviceName::forDevice(Auth::user(), $this->screen->device_id);

        $device->forceFill([
            'name' => $name === '' ? null : $name,
            'offered' => $this->offered,
        ])->save();

        // A device that has just said it is not a screen is not to be left with
        // the room's deck still on it. Clearing ends the presentation, as
        // clearing always does — this is the deliberate version of walking away
        // from a laptop, which until now had no gesture at all.
        if (! $this->offered) {
            $this->screen->point(null);
        }

        $this->screen->setRelation('deviceName', $device);

        $this->open = false;

        $this->dispatch('screen-renamed');
    }

    /**
     * Reopening after a change made elsewhere shows what is there now rather
     * than what was there when this page loaded.
     */
    public function openSettings(): void
    {
        $this->readDevice();

        $this->resetValidation();

        $this->open = true;
    }

    private function readDevice(): void
    {
        $device = DeviceName::query()
            ->where('user_id', $this->screen->user_id)
            ->where('device_id', $this->screen->device_id)
            ->first();

        $this->name = $device?->name;
        $this->offered = $device?->offered ?? true;
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.screen-settings');
    }
}
