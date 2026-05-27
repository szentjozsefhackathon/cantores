<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

        @foreach($sections as $idx => $section)

            @if($section['type'] === 'markdown')
                <div
                    wire:key="abc-guide-markdown-{{ $idx }}"
                    class="prose prose-zinc dark:prose-invert mb-2 max-w-none
                            prose-headings:scroll-mt-8
                            prose-h1:mt-0 prose-h1:text-3xl prose-h1:font-bold
                            prose-h2:mt-10 prose-h2:border-b prose-h2:border-zinc-200 prose-h2:pb-2 prose-h2:text-xl prose-h2:font-semibold dark:prose-h2:border-zinc-700
                            prose-h3:mt-6 prose-h3:text-base prose-h3:font-semibold
                            prose-table:text-sm
                            prose-code:rounded prose-code:bg-zinc-100 prose-code:px-1 prose-code:text-sm dark:prose-code:bg-zinc-800
                            prose-pre:bg-zinc-100 prose-pre:text-sm dark:prose-pre:bg-zinc-800"
                >
                    {!! $this->toHtml($section['content']) !!}
                </div>

            @else
                @php($rows = max(4, substr_count($section['content'], "\n") + 1))

                <div
                    wire:key="abc-guide-example-{{ $idx }}"
                    x-data="abcMiniEditor({{ Js::from($section['content']) }})"
                    class="not-prose my-6 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-200 bg-emerald-100/60 px-4 py-2 dark:border-emerald-900/60 dark:bg-emerald-950/40">
                        <span class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                            <flux:icon name="musical-note" variant="mini" class="size-4" />
                            Próbáld ki - szerkeszthető ABC-példa
                        </span>
                        <flux:button
                            x-show="content !== originalContent"
                            x-on:click="reset()"
                            size="sm"
                            variant="ghost"
                            icon="arrow-uturn-left"
                        >
                            Visszaállítás
                        </flux:button>
                    </div>

                    <div class="space-y-3 p-4">
                        <flux:textarea
                            x-model="content"
                            rows="{{ $rows }}"
                            spellcheck="false"
                            class="font-mono text-sm"
                        ></flux:textarea>

                        <div
                            x-ref="preview"
                            class="abc-mini-preview min-h-16 overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"
                        ></div>
                    </div>
                </div>
            @endif

        @endforeach

    </div>
</div>
