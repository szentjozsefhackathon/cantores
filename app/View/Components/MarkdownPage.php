<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownPage extends Component
{
    public HtmlString $html;

    public function __construct(
        public string $file,
        public ?string $title = null,
        public ?string $description = null,
    ) {
        $path = resource_path("markdown/{$file}.md");
        $markdown = file_exists($path) ? file_get_contents($path) : '';

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'id_prefix' => '',
                'fragment_prefix' => '',
                'symbol' => '',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $converter = new MarkdownConverter($environment);

        $this->html = new HtmlString($converter->convert($markdown));
    }

    public function render(): View
    {
        return view('components.markdown-page');
    }
}
