<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

        @foreach($sections as $idx => $section)

            @if($section['type'] === 'markdown')
                <div class="prose prose-zinc dark:prose-invert max-w-none
                            prose-headings:scroll-mt-8
                            prose-h1:text-3xl prose-h1:font-bold prose-h1:mt-0
                            prose-h2:text-xl prose-h2:font-semibold prose-h2:mt-10 prose-h2:border-b prose-h2:border-zinc-200 prose-h2:pb-2 dark:prose-h2:border-zinc-700
                            prose-h3:text-base prose-h3:font-semibold prose-h3:mt-6
                            prose-table:text-sm
                            prose-code:bg-zinc-100 prose-code:px-1 prose-code:rounded prose-code:text-sm dark:prose-code:bg-zinc-800
                            prose-pre:bg-zinc-100 prose-pre:text-sm dark:prose-pre:bg-zinc-800
                            mb-2">
                    {!! $this->toHtml($section['content']) !!}
                </div>

            @else
                <div
                    x-data="aretinoMiniEditor({{ Js::from($section['content']) }})"
                    class="not-prose my-6 overflow-hidden rounded-xl border border-blue-200 bg-blue-50/40 dark:border-blue-900/60 dark:bg-blue-950/20"
                >
                    <div class="flex items-center justify-between border-b border-blue-200 bg-blue-100/60 px-4 py-2 dark:border-blue-900/60 dark:bg-blue-950/40">
                        <span class="flex items-center gap-1.5 text-xs font-semibold text-blue-700 dark:text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                <path d="M8 1a.75.75 0 0 1 .75.75V6h4.5a.75.75 0 0 1 0 1.5h-4.5v4.25a.75.75 0 0 1-1.5 0V7.5H2.75a.75.75 0 0 1 0-1.5h4.5V1.75A.75.75 0 0 1 8 1Z" />
                            </svg>
                            Próbáld ki – szerkeszthető kottapélda
                        </span>
                        <button
                            x-show="content !== originalContent"
                            x-on:click="reset()"
                            class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200 transition-colors"
                        >
                            ↩ Visszaállítás
                        </button>
                    </div>

                    <div class="p-4 space-y-3">
                        <textarea
                            x-model="content"
                            rows="{{ max(2, substr_count($section['content'], "\n") + 1) }}"
                            spellcheck="false"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-sm leading-relaxed text-zinc-800 shadow-xs focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-300/40 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:focus:border-blue-600 dark:focus:ring-blue-700/30 resize-y"
                        ></textarea>

                        <div
                            x-ref="preview"
                            class="min-h-10 overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"
                        ></div>
                    </div>
                </div>
            @endif

        @endforeach

    </div>
</div>
