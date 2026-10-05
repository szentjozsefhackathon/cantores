<?php

namespace App\Livewire\Projection;

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Models\Projection;
use App\Models\ProjectionStyle;
use App\Support\ProjectionSettingFields;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Where a cantor writes down how a screen should be set: the slide styles.
 *
 * Opened beside a deck rather than on a page of its own, because a style is
 * judged by looking at slides — the deck stays in view behind it, and every
 * change is drawn there as it is made.
 *
 * The scalar half of a style — its name, its words, how far it may shrink — is
 * plain Livewire state. The per-format half is the knobs a slide offers, and is
 * edited by the browser, which is the only end that knows each format's factory
 * defaults and so what a knob reads before the style has said anything; it
 * saves one knob at a time through saveSetting().
 */
class StyleEditor extends Component
{
    use AuthorizesRequests;

    public Projection $projection;

    public bool $open = false;

    /** The style being edited, or null while a new one is being named. */
    public ?int $editingId = null;

    public string $name = '';

    public string $ratio = '16/9';

    public string $textTheme = 'dark';

    public float $textSizeScale = 1.0;

    public float $textLineHeight = 1.45;

    /** The shrink allowance, as the percentage a slide may be set down to. */
    public int $minPercent = 85;

    /**
     * What the style says per format, as stored.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $settings = [];

    public function mount(Projection $projection): void
    {
        $this->authorize('update', $projection);

        $this->projection = $projection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('projection_styles', 'name')->where('user_id', Auth::id())->ignore($this->editingId),
            ],
            'ratio' => ['required', 'string', Rule::in(array_keys(ProjectionRatio::options()))],
            'textTheme' => ['required', 'string', Rule::in(array_keys(ProjectionTextTheme::options()))],
            'textSizeScale' => ['required', 'numeric', 'min:0.3', 'max:4'],
            'textLineHeight' => ['required', 'numeric', 'min:0.8', 'max:3'],
            'minPercent' => ['required', 'integer', 'min:'.(int) round(ProjectionStyle::MIN_SCALE_FLOOR * 100), 'max:'.(int) round(ProjectionStyle::MIN_SCALE_CEILING * 100)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.unique' => __('You already have a style with this name.'),
        ];
    }

    /**
     * Open on a style, or on a new one for the screen this deck is for.
     */
    #[On('open-projection-style-editor')]
    public function openEditor(?int $styleId = null): void
    {
        $this->resetValidation();

        $style = $styleId === null ? null : ProjectionStyle::query()->find($styleId);

        if ($style instanceof ProjectionStyle) {
            $this->authorize('update', $style);
            $this->read($style);
        } else {
            $this->editingId = null;
            $this->name = '';
            $this->ratio = $this->projection->ratio->value;
            $this->textTheme = $this->projection->text_theme->value;
            $this->textSizeScale = $this->projection->text_size_scale;
            $this->textLineHeight = $this->projection->text_line_height;
            $this->minPercent = 85;
            $this->settings = [];
        }

        $this->open = true;
    }

    /**
     * The cantor's styles, for switching between them without closing.
     *
     * @return Collection<int, ProjectionStyle>
     */
    #[Computed]
    public function styles(): Collection
    {
        return ProjectionStyle::query()->mine()->orderBy('name')->get();
    }

    /** Switch the editor to another of the cantor's styles. */
    public function updatedEditingId(?int $styleId): void
    {
        $this->openEditor($styleId);
    }

    /**
     * A field of an existing style saves itself as it changes; a new style waits
     * for its name.
     */
    public function updated(string $property): void
    {
        if ($this->editingId === null || ! in_array($property, ['name', 'textTheme', 'textSizeScale', 'textLineHeight', 'minPercent'], true)) {
            return;
        }

        $style = $this->editing();
        $this->validate();

        $style->update([
            'name' => trim($this->name),
            'text_theme' => ProjectionTextTheme::from($this->textTheme),
            'text_size_scale' => $this->textSizeScale,
            'text_line_height' => $this->textLineHeight,
            'min_scale' => $this->minPercent / 100,
        ]);

        unset($this->styles);

        $this->dispatch('projection-style-saved', styleId: $style->id);
    }

    /**
     * Make the new style, at the factory defaults for its shape, and put this
     * deck in it — making a style from a deck is asking for the deck to be in
     * it. The editor stays open on it, since that is where it gets tuned.
     */
    public function create(): void
    {
        $this->authorize('create', ProjectionStyle::class);
        $this->validate();

        $style = ProjectionStyle::create([
            'user_id' => Auth::id(),
            'name' => trim($this->name),
            'ratio' => ProjectionRatio::from($this->ratio),
            'text_theme' => ProjectionTextTheme::from($this->textTheme),
            'text_size_scale' => $this->textSizeScale,
            'text_line_height' => $this->textLineHeight,
            'min_scale' => $this->minPercent / 100,
        ]);

        $this->read($style);
        unset($this->styles);

        $this->dispatch('projection-style-saved', styleId: $style->id, attach: true);
    }

    /**
     * One knob of one format, or with a null value, that knob given back to the
     * factory default.
     */
    public function saveSetting(string $format, string $key, mixed $value = null): void
    {
        $style = $this->editing();
        $settings = $style->settings ?? [];
        $bucket = $settings[$format] ?? [];

        unset($bucket[$key]);

        if ($value !== null) {
            $bucket = [...$bucket, ...(ProjectionSettingFields::sanitizeStyle([$format => [$key => $value]])[$format] ?? [])];
        }

        $settings[$format] = $bucket;
        $settings = ProjectionSettingFields::sanitizeStyle($settings);

        $style->update(['settings' => $settings === [] ? null : $settings]);
        $this->settings = $settings;

        $this->dispatch('projection-style-saved', styleId: $style->id);
    }

    /** A copy, for the other screen of the same shape — opened at once. */
    public function duplicate(): void
    {
        $this->authorize('create', ProjectionStyle::class);

        $copy = $this->editing()->duplicate();

        $this->read($copy);
        unset($this->styles);

        $this->dispatch('projection-style-saved', styleId: $copy->id);
    }

    /** Gone; every deck that was in it goes back to the factory defaults. */
    public function delete(): void
    {
        $style = $this->editing();

        $this->authorize('delete', $style);

        $id = $style->id;
        $style->delete();

        unset($this->styles);
        $this->open = false;
        $this->editingId = null;

        $this->dispatch('projection-style-deleted', styleId: $id);
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.style-editor', [
            'formats' => collect(ProjectionSettingFields::STYLED_FORMATS)
                ->mapWithKeys(fn (string $format): array => [$format => ProjectionSettingFields::stylePanelFor($format)]),
        ]);
    }

    private function editing(): ProjectionStyle
    {
        $style = ProjectionStyle::query()->findOrFail($this->editingId);

        $this->authorize('update', $style);

        return $style;
    }

    private function read(ProjectionStyle $style): void
    {
        $this->editingId = $style->id;
        $this->name = $style->name;
        $this->ratio = $style->ratio->value;
        $this->textTheme = $style->text_theme->value;
        $this->textSizeScale = $style->text_size_scale;
        $this->textLineHeight = $style->text_line_height;
        $this->minPercent = (int) round($style->min_scale * 100);
        $this->settings = $style->settings ?? [];
    }
}
