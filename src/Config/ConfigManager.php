<?php

declare(strict_types=1);

namespace App\Config;

// Ensure loader function is available
require_once __DIR__ . '/../../config/loader.php';

/**
 * Simple singleton config manager.
 *
 * Provides a central place to access the merged config. Internally relies on
 * the existing loadConfig() loader (which already implements a function-level
 * cache) but wraps it into an object to make usage consistent across the codebase.
 */
class ConfigManager
{
    private static ?ConfigManager $instance = null;

    /** @var array<string,mixed> */
    private array $config;

    private function __construct()
    {
        // use the loader to read config/config.default.php and config.local.php
        $this->config = \loadConfig('config');
    }

    public static function getInstance(): ConfigManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return array<string,mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Convenience getter for nested keys using dot notation: 'db.host'
     * If key not found, returns the provided default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->config;
        }

        $parts = explode('.', $key);
        $cursor = $this->config;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$part];
        }
        return $cursor;
    }
}
