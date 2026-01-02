@extends('layouts.app')

@section('title', ucfirst(str_replace('-', ' ', $type)))

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav :active="$type" />

    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-8">
            {{ ucfirst(str_replace('-', ' ', $type)) }}
        </h1>

        <!-- Month Navigator (for monthly and indie-games) -->
        @if(in_array($type, ['monthly', 'indie-games']))
            <x-month-navigator :year="$year" :month="$month" :type="$type" />
        @endif

        <!-- List Tabs (only for seasoned) -->
        @if($type === 'seasoned')
            <x-list-tabs :lists="$lists" :selectedList="$selectedList" :type="$type" />
        @endif

        <!-- List Viewer -->
        <x-list-viewer
            :list="$selectedList"
            :platformEnums="$platformEnums"
            :showHeader="in_array($type, ['monthly', 'indie-games'])" />
    </div>
@endsection
