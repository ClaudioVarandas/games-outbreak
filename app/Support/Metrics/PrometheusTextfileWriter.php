<?php

declare(strict_types=1);

namespace App\Support\Metrics;

class PrometheusTextfileWriter
{
    /**
     * Atomic write for node_exporter textfile collectors: the collector must
     * never scrape a half-written file, so render to a sibling .tmp and rename.
     */
    public function write(string $path, string $contents): void
    {
        $temp = $path.'.tmp';

        file_put_contents($temp, $contents, LOCK_EX);
        chmod($temp, 0644);
        rename($temp, $path);
    }
}
