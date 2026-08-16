<?php
/**
 * Simple IP-based rate limiter using temporary files.
 * No external dependencies required.
 */

function rateLimit(string $action, string $identifier, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $key  = $action . '_' . md5($identifier);
    $file = sys_get_temp_dir() . '/rl_' . $key . '.json';
    $now  = time();

    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($file)) {
        $saved = json_decode(@file_get_contents($file), true);
        if (is_array($saved) && ($now - $saved['window_start']) < $windowSeconds) {
            $data = $saved;
        }
    }

    $blocked    = $data['count'] >= $maxAttempts;
    $retryAfter = $blocked ? ($data['window_start'] + $windowSeconds - $now) : 0;
    $remaining  = max(0, $maxAttempts - $data['count']);

    return [
        'blocked'     => $blocked,
        'remaining'   => $remaining,
        'retry_after' => $retryAfter,
    ];
}

function rateLimitIncrement(string $action, string $identifier, int $windowSeconds = 900): void {
    $key  = $action . '_' . md5($identifier);
    $file = sys_get_temp_dir() . '/rl_' . $key . '.json';
    $now  = time();

    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($file)) {
        $saved = json_decode(@file_get_contents($file), true);
        if (is_array($saved) && ($now - $saved['window_start']) < $windowSeconds) {
            $data = $saved;
        }
    }
    $data['count']++;
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function rateLimitReset(string $action, string $identifier): void {
    $key  = $action . '_' . md5($identifier);
    $file = sys_get_temp_dir() . '/rl_' . $key . '.json';
    if (file_exists($file)) @unlink($file);
}
