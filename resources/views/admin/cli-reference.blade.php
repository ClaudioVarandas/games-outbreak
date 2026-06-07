@extends('layouts.app')

@section('title', 'CLI Reference (Admin)')

@section('content')
    <div class="page-shell py-8">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100">CLI Reference</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Read-only guide to the IGDB / game-list maintenance commands — what each does, its flags, and the data it writes.
            </p>
        </div>

        <div class="mb-6 rounded-lg border-l-4 border-cyan-500 bg-white p-5 shadow dark:bg-gray-800">
            <p class="text-xs font-semibold uppercase tracking-wider text-cyan-600 dark:text-cyan-400">Mental model</p>
            <p class="mt-1 text-gray-700 dark:text-gray-200">{{ \App\Support\AdminCliReference::mentalModel() }}</p>
        </div>

        <div class="mb-8 rounded-lg bg-white p-5 shadow dark:bg-gray-800">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Operating rules</p>
            <ul class="flex flex-col gap-2">
                @foreach (\App\Support\AdminCliReference::rules() as $rule)
                    <li class="flex gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <span class="text-orange-500">•</span>
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @foreach (\App\Support\AdminCliReference::tiers() as $tier)
            <section class="mb-10">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $tier['title'] }}</h2>
                <p class="mt-1 mb-4 max-w-4xl text-sm text-gray-600 dark:text-gray-400">{{ $tier['summary'] }}</p>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($tier['commands'] as $command)
                        <div class="flex flex-col rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <code class="mb-3 inline-block self-start rounded bg-gray-900 px-3 py-1.5 font-mono text-sm text-cyan-300">
                                php artisan {{ $command['name'] }}
                            </code>

                            <p class="text-gray-700 dark:text-gray-200">{{ $command['does'] }}</p>

                            @if (! empty($command['flags']))
                                <dl class="mt-3 flex flex-col gap-1.5">
                                    @foreach ($command['flags'] as $flag => $description)
                                        <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                                            <dt class="shrink-0 font-mono text-xs text-orange-600 dark:text-orange-400 sm:w-40">{{ $flag }}</dt>
                                            <dd class="text-xs text-gray-600 dark:text-gray-400">{{ $description }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <span class="font-semibold uppercase tracking-wider">Writes:</span>
                                {{ $command['writes'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
