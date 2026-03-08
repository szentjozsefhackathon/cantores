<div>
    @include('partials.music-browser-filters')

    <div class="mt-4 overflow-x-auto">
        @include('partials.music-browser-table', ['mode' => 'select'])
    </div>
</div>
