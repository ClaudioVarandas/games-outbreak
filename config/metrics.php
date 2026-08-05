<?php

return [
    'queues' => ['default', 'low'],

    'textfile_path' => env(
        'METRICS_TEXTFILE_PATH',
        '/var/lib/prometheus/node-exporter/games_outbreak_queue.prom'
    ),

    'igdb_textfile_path' => env(
        'METRICS_IGDB_TEXTFILE_PATH',
        '/var/lib/prometheus/node-exporter/games_outbreak_igdb.prom'
    ),
];
