<?php

return [
    'active' => [
        3, // linux
        14, // Macos
        6,   // PC
        167, // PS5
        169, // Xbox Series X/S
        130, // Switch
        508, // Switch2
        34, // Android
        39, // IOS
        48, // PS4
        59,// Xbox One
        // Add more platform IGDB IDs here to make them active
        // Platforms defined in PlatformEnum but not listed here will be inactive
    ],

    // Platform display priority (lower number = higher priority, shown first)
    // Order: PC first, then consoles, then Linux/macOS
    'priority' => [
        6 => 1,   // PC (first)
        167 => 2, // PS5
        169 => 3, // Xbox Series X/S
        130 => 4, // Switch
        508 => 5, // Switch 2
        48 => 6,  // PS4
        49 => 7,  // Xbox One
        3 => 8,   // Linux
        14 => 9,  // macOS
        34 => 10, // Android
        39 => 11, // iOS
    ],
];

