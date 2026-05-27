<?php

namespace App\Livewire\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\View as IlluminateView;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use Livewire\Component;

class AbcGuide extends Component
{
    /** @var list<array{type: 'markdown'|'abc', content: string}> */
    public array $sections = [];

    public function mount(): void
    {
        $path = base_path('docs/abc-felhasznaloi-utmutato.md');
        $markdown = file_exists($path) ? (string) file_get_contents($path) : '';
        $this->sections = $this->parseSections($markdown);
    }

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout, ['title' => 'ABC - felhasználói útmutató']);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.abc-guide');
    }

    public function toHtml(string $markdown): HtmlString
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'insert' => 'none',
                'id_prefix' => '',
                'apply_id_to_heading' => true,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $converter = new MarkdownConverter($environment);

        return new HtmlString($converter->convert($markdown));
    }

    /**
     * @return list<array{type: 'markdown'|'abc', content: string}>
     */
    private function parseSections(string $markdown): array
    {
        $sections = [];
        $pattern = '/^```abc\n(.*?)^```[ \t]*$/ms';

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

            $sections[] = ['type' => 'abc', 'content' => trim((string) $matches[1][$i][0])];

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
