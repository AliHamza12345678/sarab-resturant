<?php
/**
 * Lightweight file-based cache.
 *
 * Most shared PHP hosting doesn't have Redis/Memcached available, so this
 * uses the filesystem — still a real, meaningful win for expensive
 * aggregate queries (dashboard stats, chart data) that don't need to be
 * accurate to the second. Falls back to APCu automatically if it's
 * available (faster, in-memory) without changing the call site.
 */

const CACHE_DIR = __DIR__ . '/../cache';

function cache_get(string $key)
{
    if (function_exists('apcu_fetch')) {
        $val = apcu_fetch($key, $ok);
        return $ok ? $val : null;
    }

    $file = CACHE_DIR . '/' . sha1($key) . '.cache';
    if (!is_file($file)) return null;

    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = @unserialize($raw);
    if (!is_array($data) || !isset($data['expires'], $data['value'])) return null;
    if (time() > $data['expires']) {
        @unlink($file);
        return null;
    }
    return $data['value'];
}

function cache_set(string $key, $value, int $ttlSeconds = 300): void
{
    if (function_exists('apcu_store')) {
        apcu_store($key, $value, $ttlSeconds);
        return;
    }

    if (!is_dir(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    $file = CACHE_DIR . '/' . sha1($key) . '.cache';
    @file_put_contents($file, serialize(['expires' => time() + $ttlSeconds, 'value' => $value]));
}

function cache_forget(string $key): void
{
    if (function_exists('apcu_delete')) {
        apcu_delete($key);
        return;
    }
    $file = CACHE_DIR . '/' . sha1($key) . '.cache';
    if (is_file($file)) @unlink($file);
}

/**
 * Clear every cached entry — call this after any write that would make
 * cached dashboard/reporting data stale (new order, status change, etc.).
 */
function cache_flush_all(): void
{
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }
    if (is_dir(CACHE_DIR)) {
        foreach (glob(CACHE_DIR . '/*.cache') as $file) {
            @unlink($file);
        }
    }
}

/**
 * Convenience wrapper: get from cache, or compute + store if missing.
 */
function cache_remember(string $key, int $ttlSeconds, callable $compute)
{
    $cached = cache_get($key);
    if ($cached !== null) return $cached;
    $value = $compute();
    cache_set($key, $value, $ttlSeconds);
    return $value;
}
