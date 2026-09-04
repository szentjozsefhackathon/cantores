@props(['publication' => null, 'reportable' => false])

@if($publication instanceof \App\Models\ScorePublication)
@php
    $license = $publication->effectiveLicense();
    $builder = app(\App\Services\ScoreAttributionBuilder::class);
@endphp
<div class="space-y-3 text-sm">
    <div>
        <flux:heading size="sm">{{ __('How you may use this') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
            {!! $builder->html($publication) !!}
        </flux:text>
    </div>

    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
        <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Licence') }}</dt>
            <dd class="text-zinc-700 dark:text-zinc-300">
                @if($license->deedUrl())
                <a href="{{ $license->deedUrl() }}" rel="license noopener" target="_blank" class="underline">{{ $license->label() }}</a>
                @else
                {{ $license->label() }}
                @endif
            </dd>
        </div>

        @if($publication->source_title || $publication->source_url)
        <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Source') }}</dt>
            <dd class="text-zinc-700 dark:text-zinc-300">
                @if($publication->source_url)
                <a href="{{ $publication->source_url }}" rel="noopener nofollow" target="_blank" class="underline">
                    {{ $publication->source_title ?: $publication->source_url }}
                </a>
                @else
                {{ $publication->source_title }}
                @endif
            </dd>
        </div>
        @endif

        @if($publication->composer_death_year)
        <div>
            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Composer died') }}</dt>
            <dd class="text-zinc-700 dark:text-zinc-300">{{ $publication->composer_death_year }}</dd>
        </div>
        @endif
    </dl>

    @if(! $license->allowsCommercialUse())
    <flux:text class="text-xs text-zinc-500">{{ __('Non-commercial use only.') }}</flux:text>
    @endif

    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <flux:text class="text-xs text-zinc-500">
            {{ __('Think this should not be here?') }}
            <a href="{{ route('score-rights') }}" class="underline">{{ __('Read what we publish and why.') }}</a>
        </flux:text>
        @if($reportable)
        <livewire:score-rights-report-modal :score="$publication->score" :key="'rights-report-'.$publication->id" />
        @endif
    </div>
</div>
@endif
