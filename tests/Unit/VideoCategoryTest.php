<?php

use App\Models\VideoCategory;

it('casts is_active to bool', function () {
    $cat = new VideoCategory(['name' => 'T', 'slug' => 't', 'is_active' => 1]);

    expect($cat->is_active)->toBeTrue();
});

it('has the expected fillable columns', function () {
    expect((new VideoCategory)->getFillable())
        ->toEqual(['name', 'slug', 'color', 'icon', 'is_active']);
});
