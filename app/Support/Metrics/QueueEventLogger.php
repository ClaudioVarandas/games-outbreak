<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;

class QueueEventLogger
{
    /** @var array<string, float> microtime keyed by job id */
    private array $startedAt = [];

    public function processing(JobProcessing $event): void
    {
        $this->startedAt[$this->key($event->job)] = microtime(true);

        $this->log($event->job, $event->connectionName, 'processing');
    }

    public function processed(JobProcessed $event): void
    {
        $this->log($event->job, $event->connectionName, 'processed', $this->popDuration($event->job));
    }

    public function failed(JobFailed $event): void
    {
        $this->log($event->job, $event->connectionName, 'failed', $this->popDuration($event->job), $event->exception);
    }

    public function exceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->log($event->job, $event->connectionName, 'exception', $this->duration($event->job), $event->exception);
    }

    private function log(Job $job, string $connection, string $status, ?int $durationMs = null, ?\Throwable $exception = null): void
    {
        Log::channel('queue')->info('queue.job.'.$status, array_filter([
            'app' => config('app.name'),
            'env' => app()->environment(),
            'queue' => $job->getQueue(),
            'connection' => $connection,
            'job_class' => $job->resolveName(),
            'job_id' => $job->getJobId(),
            'uuid' => $job->uuid(),
            'attempts' => $job->attempts(),
            'duration_ms' => $durationMs,
            'status' => $status,
            'exception_class' => $exception ? $exception::class : null,
            'exception_message' => $exception ? str($exception->getMessage())->limit(500)->value() : null,
        ], fn ($value) => $value !== null));
    }

    private function key(Job $job): string
    {
        return $job->uuid() ?? (string) $job->getJobId();
    }

    private function duration(Job $job): ?int
    {
        $start = $this->startedAt[$this->key($job)] ?? null;

        return $start === null ? null : (int) round((microtime(true) - $start) * 1000);
    }

    private function popDuration(Job $job): ?int
    {
        $duration = $this->duration($job);
        unset($this->startedAt[$this->key($job)]);

        return $duration;
    }
}
