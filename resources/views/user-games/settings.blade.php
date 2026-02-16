@extends('layouts.app')

@section('title', 'Collection Settings')

@section('content')
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('user.games', $user->username) }}" class="text-orange-500 hover:text-orange-400 text-sm">
                &larr; Back to My Games
            </a>
        </div>

        <h1 class="text-3xl font-bold text-gray-100 mb-8">Collection Settings</h1>

        <form action="{{ route('user.games.settings.update', $user->username) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            <div class="space-y-6">
                <!-- Collection Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Collection Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $collection->name) }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-gray-100 focus:border-orange-500 focus:ring-orange-500"
                           required maxlength="255">
                    @error('name')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Bio / Description</label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-gray-100 focus:border-orange-500 focus:ring-orange-500"
                              maxlength="1000">{{ old('description', $collection->description) }}</textarea>
                    @error('description')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Avatar -->
                <div>
                    <label for="avatar" class="block text-sm font-medium text-gray-300 mb-1">Avatar</label>
                    @if($user->avatar_url)
                        <div class="mb-3">
                            <img src="{{ $user->avatar_url }}" alt="Current avatar" class="w-24 h-24 rounded-full object-cover">
                        </div>
                    @endif
                    <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp"
                           class="w-full text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-700 file:text-gray-200 hover:file:bg-gray-600">
                    <p class="text-xs text-gray-500 mt-1">JPEG, PNG, or WebP. Max 10MB. Will be resized to 200x200.</p>
                    @error('avatar')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Cover Image -->
                <div>
                    <label for="cover_image" class="block text-sm font-medium text-gray-300 mb-1">Cover Image</label>
                    @if($collection->cover_image_url)
                        <div class="mb-3">
                            <img src="{{ $collection->cover_image_url }}" alt="Current cover" class="h-24 rounded-lg object-cover w-full max-w-md">
                        </div>
                    @endif
                    <input type="file" name="cover_image" id="cover_image" accept="image/jpeg,image/png,image/webp"
                           class="w-full text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-700 file:text-gray-200 hover:file:bg-gray-600">
                    <p class="text-xs text-gray-500 mt-1">JPEG, PNG, or WebP. Max 10MB. Will be displayed as a banner (16:5 ratio).</p>
                    @error('cover_image')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Privacy Settings -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-200 mb-3">Privacy</h3>
                    <p class="text-sm text-gray-400 mb-4">Choose which sections are visible to other users.</p>

                    <div class="space-y-3">
                        @foreach(['playing' => 'Playing', 'played' => 'Played', 'backlog' => 'Backlog', 'wishlist' => 'Wishlist'] as $key => $label)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="privacy_{{ $key }}"
                                       {{ old('privacy_' . $key, $collection->{'privacy_' . $key}) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded border-gray-600 bg-gray-700 text-orange-500 focus:ring-orange-500">
                                <span class="text-gray-300">{{ $label }} (public)</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit"
                            class="bg-orange-600 hover:bg-orange-700 text-white font-medium px-6 py-2 rounded-lg transition">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection