<?php

namespace App\Livewire\Pages;

use App\Enums\InitiumSetting;
use App\Enums\ScoreFormat;
use App\Services\PsalmTone;
use App\Services\PsalmToneLibrary;
use App\Services\PsalmToneSetter;
use Illuminate\View\View as IlluminateView;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Sets psalm verses to a tone from one of the shipped collections, or to a
 * formula typed in, and shows the result as Aretino.
 */
class PsalmTones extends Component
{
    /** The most verse text one setting takes, in characters. */
    private const MAX_VERSES_LENGTH = 20000;

    /**
     * The longest custom formula, in characters. The fitter weighs every slot
     * against every syllable, so a formula far longer than any tone would let
     * one request take the memory of a worker.
     */
    private const MAX_FORMULA_LENGTH = 120;

    public string $collection = '';

    public string $tone = '';

    public string $customFormula = '';

    public string $verses = "Dicsőség az Atyának és Fiúnak * és Szentlélek Istennek,\nMiképpen kezdetben, most és mindenkor * és mindörökkön örökké. Ámen.";

    public InitiumSetting $initiumSetting = InitiumSetting::FirstVerse;

    /** The format the underlined text is shown in: aretino, chordpro or html. */
    public string $textFormat = 'html';

    public function mount(PsalmToneLibrary $library): void
    {
        $this->collection = (string) array_key_first($library->collections());
        $this->tone = (string) array_key_first($library->collections()[$this->collection]['tones'] ?? []);
    }

    public function updatedCollection(): void
    {
        $this->tone = (string) array_key_first($this->collections()[$this->collection]['tones'] ?? []);
    }

    /**
     * @return array<string, array{name: string, tones: array<string, PsalmTone>}>
     */
    #[Computed]
    public function collections(): array
    {
        return app(PsalmToneLibrary::class)->collections();
    }

    /**
     * The tone the verses are set to, and what is wrong with the custom
     * formula when it is not a tone.
     *
     * @return array{tone: PsalmTone|null, error: string|null}
     */
    #[Computed]
    public function selectedTone(): array
    {
        if (trim($this->customFormula) !== '') {
            if (mb_strlen($this->customFormula) > self::MAX_FORMULA_LENGTH) {
                return ['tone' => null, 'error' => __('The formula can be at most :max characters long.', ['max' => self::MAX_FORMULA_LENGTH])];
            }

            try {
                return ['tone' => PsalmTone::fromLine(__('Custom formula'), $this->customFormula), 'error' => null];
            } catch (InvalidArgumentException $exception) {
                return ['tone' => null, 'error' => $exception->getMessage()];
            }
        }

        return ['tone' => app(PsalmToneLibrary::class)->tone($this->collection, $this->tone), 'error' => null];
    }

    /**
     * The tone's formula drawn as a score of its own.
     */
    #[Computed]
    public function formulaSource(): string
    {
        $tone = $this->selectedTone()['tone'];

        return $tone?->preview() ?? '';
    }

    /**
     * The verses set to the tone, and what is wrong with them when they cannot be.
     *
     * @return array{source: string, html: string, aretinoText: string, chordProText: string, unsure: list<array{verse: int, syllable: string}>, error: string|null}
     */
    #[Computed]
    public function setting(): array
    {
        $tone = $this->selectedTone()['tone'];
        $empty = ['source' => '', 'html' => '', 'aretinoText' => '', 'chordProText' => '', 'unsure' => [], 'error' => null];

        if ($tone === null || trim($this->verses) === '') {
            return $empty;
        }

        if (mb_strlen($this->verses) > self::MAX_VERSES_LENGTH) {
            return [...$empty, 'error' => __('The verses can be at most :max characters long.', ['max' => self::MAX_VERSES_LENGTH])];
        }

        try {
            return [...app(PsalmToneSetter::class)->set($tone, $this->verses, $this->initiumSetting), 'error' => null];
        } catch (InvalidArgumentException $exception) {
            return [...$empty, 'error' => $exception->getMessage()];
        }
    }

    #[Computed]
    public function editorUrl(): ?string
    {
        $source = $this->setting()['source'];

        return $source === '' ? null : ScoreEditor::shareUrl(__('Psalm'), ScoreFormat::Aretino, $source);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::shell', ['title' => __('Psalm tones')]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.psalm-tones');
    }
}
