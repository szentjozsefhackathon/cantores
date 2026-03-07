<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class MarkdownPage extends Component
{
    public HtmlString $html;

    public function __construct(public string $file)
    {
        $path = resource_path("markdown/{$file}.md");
        $markdown = file_exists($path) ? file_get_contents($path) : '';

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $this->html = new HtmlString($converter->convert($markdown));
    }

    public function render(): View
    {
        return view('components.markdown-page');
    }
}
