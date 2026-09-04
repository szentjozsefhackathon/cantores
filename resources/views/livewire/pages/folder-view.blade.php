<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <x-borrowed-bar :owner-name="$ownerName" :can-keep="$canKeep" :kept="$kept" />

            <div class="mb-6">
                <flux:heading size="2xl">{{ $this->name }}</flux:heading>
                <flux:subheading>{{ __('Read-only preview') }}</flux:subheading>
            </div>

            @if($this->scores->isEmpty())
                <flux:callout variant="secondary" icon="folder-open" class="border-dashed">
                    <flux:callout.heading>{{ __('No scores in this folder') }}</flux:callout.heading>
                </flux:callout>
            @else
                <div class="space-y-3">
                    @foreach($this->scores as $score)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="{{ $score->loanUrl($this->loanToken) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ $score->title }}
                            </a>
                            <x-score-format-badge :format="$score->format" />
                        </div>
                        @if($score->music)
                        <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $score->music->title }}</div>
                        @endif
                        @if($score->hasIncipit())
                        <div class="mt-2">
                            <x-incipit-image
                                :src="$score->loanIncipitUrl($this->loanToken)"
                                :alt="$score->title"
                                img-class="block h-14 w-auto" />
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
