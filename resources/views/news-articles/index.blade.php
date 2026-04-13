@extends('layouts.app')

@section('title', 'Notícias')

@section('content')
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-8">Notícias</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($articles as $article)
                @php $loc = $article->localizations->first(); @endphp
                @if ($loc)
                    <article class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden flex flex-col">
                        @if ($article->featured_image_url)
                            <img src="{{ $article->featured_image_url }}"
                                 alt="{{ $loc->title }}"
                                 class="w-full h-48 object-cover">
                        @endif
                        <div class="p-4 flex flex-col flex-1">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                <a href="{{ route('news-articles.show', [$locale->slugPrefix(), $locale === \App\Enums\NewsLocaleEnum::PtPt ? $article->slug_pt_pt : $article->slug_pt_br]) }}"
                                   class="hover:underline">
                                    {{ $loc->title }}
                                </a>
                            </h2>
                            @if ($loc->summary_short)
                                <p class="text-sm text-gray-600 dark:text-gray-400 flex-1">{{ $loc->summary_short }}</p>
                            @endif
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                                {{ $article->source_name }} &middot; {{ $article->published_at?->diffForHumans() }}
                            </p>
                        </div>
                    </article>
                @endif
            @empty
                <p class="col-span-3 text-center text-gray-500 dark:text-gray-400 py-12">Sem notícias disponíveis.</p>
            @endforelse
        </div>

        <div class="mt-8">{{ $articles->links() }}</div>
    </div>
@endsection
