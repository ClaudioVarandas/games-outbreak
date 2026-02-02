@extends('layouts.app')

@section('title', 'Seasoned Lists')

@section('content')
    <!-- Releases Navigation Menu -->
    <x-releases-nav active="seasoned" />

    <div class="container mx-auto px-4 py-8">
        <!-- Page Header -->
        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-8">
            Seasoned Lists
        </h1>

        <!-- List Tabs -->
        <x-list-tabs :lists="$lists" :selectedList="$selectedList" :type="$type" />

        <!-- List Viewer -->
        <x-list-viewer
            :list="$selectedList"
            :platformEnums="$platformEnums"
            :showHeader="false" />
    </div>
@endsection
