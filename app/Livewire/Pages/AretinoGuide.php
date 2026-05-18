<?php

namespace App\Livewire\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\View as IlluminateView;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Livewire\Component;

class AretinoGuide extends Component
{
    /** @var list<array{type: 'markdown'|'aretino', content: string}> */
    public array $sections = [];

    public function mount(): void
    {
        $path = base_path('docs/aretino-felhasznaloi-utmutato.md');
        $markdown = file_exists($path) ? (string) file_get_contents($path) : '';
        $this->sections = $this->parseSections($markdown);
    }

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout, ['title' => 'Aretino – felhasználói útmutató']);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.aretino-guide');
    }

    /**
     * Convert a markdown-only chunk to an HtmlString.
     */
    public function toHtml(string $markdown): HtmlString
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return new HtmlString($converter->convert($markdown));
    }

    /**
     * Split raw markdown into alternating markdown and aretino-code sections.
     *
     * @return list<array{type: 'markdown'|'aretino', content: string}>
     */
    private function parseSections(string $markdown): array
    {
        $sections = [];
        $pattern = '/^```aretino\n(.*?)^```[ \t]*$/ms';

        preg_match_all($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE);

        $lastEnd = 0;
        foreach ($matches[0] as $i => $match) {
            $offset = (int) $match[1];
            $length = strlen($match[0]);

            if ($offset > $lastEnd) {
                $chunk = trim(substr($markdown, $lastEnd, $offset - $lastEnd));
                if ($chunk !== '') {
                    $sections[] = ['type' => 'markdown', 'content' => $chunk];
                }
            }

            $sections[] = ['type' => 'aretino', 'content' => trim((string) $matches[1][$i][0])];

            $lastEnd = $offset + $length;
        }

        if ($lastEnd < strlen($markdown)) {
            $chunk = trim(substr($markdown, $lastEnd));
            if ($chunk !== '') {
                $sections[] = ['type' => 'markdown', 'content' => $chunk];
            }
        }

        return $sections;
    }
}
