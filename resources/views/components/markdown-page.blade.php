<x-layouts::shell :title="$title" :description="$description">
    <div class="max-w-5xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="prose">
            {!! $html !!}
        </div>
    </div>
</x-layouts::shell>
