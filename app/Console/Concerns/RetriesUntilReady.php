<?php

namespace App\Console\Concerns;

use OpenAI\Exceptions\ErrorException;
use RuntimeException;

trait RetriesUntilReady
{
    /**
     * Retry a read until it is ready, because reads that follow a write are eventually consistent.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $read
     * @param  (callable(TResult): bool)|null  $ready
     * @param  (callable(ErrorException): bool)|null  $retryIf
     * @return TResult
     */
    protected function eventually(
        callable $read,
        ?callable $ready = null,
        int $attempts = 10,
        int $seconds = 2,
        ?callable $retryIf = null,
    ): mixed {
        $ready ??= static fn (): bool => true;
        $retryIf ??= static fn (): bool => true;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $read();

                if ($ready($result)) {
                    return $result;
                }

                $status = $result->status ?? null;
            } catch (ErrorException $e) {
                if ($attempt === $attempts || ! $retryIf($e)) {
                    throw $e;
                }

                $status = $e->getMessage();
            }

            $this->comment("Waiting (attempt {$attempt} of {$attempts})...".($status !== null ? " [{$status}]" : ''));
            sleep($seconds);
        }

        throw new RuntimeException('The resource never became ready.');
    }
}
