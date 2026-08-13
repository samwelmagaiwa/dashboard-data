<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker for the remote HIS API.
 *
 * States:
 *   CLOSED    — normal operation, all requests pass through.
 *   OPEN      — API is failing; requests are rejected immediately without
 *               hitting the network. Transitions to HALF_OPEN after OPEN_TTL seconds.
 *   HALF_OPEN — one probe request is allowed through. On success → CLOSED.
 *               On failure → OPEN again.
 *
 * Thresholds (all configurable via dashboard.circuit_breaker config):
 *   failure_threshold — consecutive failures needed to open the circuit (default 2).
 *   open_ttl          — seconds the circuit stays OPEN before trying HALF_OPEN (default 300).
 */
class CircuitBreaker
{
    const STATE_CLOSED    = 'closed';
    const STATE_OPEN      = 'open';
    const STATE_HALF_OPEN = 'half_open';

    private string $name;
    private int    $failureThreshold;
    private int    $openTtl;

    public function __construct(string $name = 'his_api')
    {
        $this->name             = $name;
        $this->failureThreshold = config('dashboard.circuit_breaker.failure_threshold', 2);
        $this->openTtl          = config('dashboard.circuit_breaker.open_ttl', 300);
    }

    /**
     * Returns true if a request should be allowed through.
     * Handles the OPEN → HALF_OPEN transition automatically.
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            $openedAt = (int) Cache::get($this->key('opened_at'), 0);
            if ($openedAt && (now()->timestamp - $openedAt) >= $this->openTtl) {
                $this->setState(self::STATE_HALF_OPEN);
                Log::info("[CircuitBreaker:{$this->name}] Transitioning OPEN → HALF_OPEN for probe.");
                return true;
            }
            return false;
        }

        // STATE_HALF_OPEN — allow exactly one probe through
        return true;
    }

    /**
     * Call after every successful API response.
     */
    public function recordSuccess(): void
    {
        $previous = $this->getState();
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('opened_at'));
        $this->setState(self::STATE_CLOSED);

        if ($previous !== self::STATE_CLOSED) {
            Log::info("[CircuitBreaker:{$this->name}] Circuit CLOSED — API recovered.");
        }
    }

    /**
     * Call after every failed or timed-out API request.
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            Log::warning("[CircuitBreaker:{$this->name}] Probe failed — circuit re-OPENED.");
            $this->open();
            return;
        }

        $failures = (int) Cache::get($this->key('failures'), 0) + 1;
        Cache::put($this->key('failures'), $failures, $this->openTtl + 60);

        Log::warning("[CircuitBreaker:{$this->name}] Failure #{$failures} recorded.");

        if ($failures >= $this->failureThreshold) {
            Log::error("[CircuitBreaker:{$this->name}] Threshold reached ({$failures}/{$this->failureThreshold}) — circuit OPENED for {$this->openTtl}s.");
            $this->open();
        }
    }

    public function getState(): string
    {
        return Cache::get($this->key('state'), self::STATE_CLOSED);
    }

    /** Seconds remaining before the circuit transitions to HALF_OPEN. */
    public function remainingOpenSeconds(): int
    {
        $openedAt = (int) Cache::get($this->key('opened_at'), 0);
        if (!$openedAt) {
            return 0;
        }
        return max(0, $this->openTtl - (now()->timestamp - $openedAt));
    }

    /** Force-reset to CLOSED (e.g. from an admin action). */
    public function reset(): void
    {
        Cache::forget($this->key('state'));
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('opened_at'));
        Log::info("[CircuitBreaker:{$this->name}] Manually reset to CLOSED.");
    }

    private function open(): void
    {
        Cache::put($this->key('opened_at'), now()->timestamp, $this->openTtl + 60);
        $this->setState(self::STATE_OPEN);
    }

    private function setState(string $state): void
    {
        Cache::put($this->key('state'), $state, $this->openTtl + 60);
    }

    private function key(string $suffix): string
    {
        return "circuit_breaker:{$this->name}:{$suffix}";
    }
}
