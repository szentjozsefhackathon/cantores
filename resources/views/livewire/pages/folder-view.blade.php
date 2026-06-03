<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6">
                <flux:heading size="2xl">{{ $this->name }}</flux:heading>
                <flux:subheading>{{ __('Read-only preview') }}</flux:subheading>
            </div>

            @if($this->scores->isEmpty())
                <flux:callout variant="secondary" icon="folder-open" class="border-dashed">
                    <flux:callout.heading>{{ __('No scores in this folder') }}</flux:callout.heading>
                </flux:callout>
            @else
                <div class="space-y-2">
                    @foreach($this->scores as $score)
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="min-w-0 flex-1">
                            @if($score->share_token)
                                <a href="{{ route('score.share', ['token' => $score->share_token]) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $score->title }}
                                </a>
                            @else
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $score->title }}</span>
                            @endif
                        </div>
                        <flux:badge color="zinc" size="sm">{{ $score->format->label() }}</flux:badge>
                    </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
