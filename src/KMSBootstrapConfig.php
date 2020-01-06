<?php

namespace Mathrix\GoogleCloud\KMS\Bootstrap;

use InvalidArgumentException;

/**
 * @property-read string $basePath
 * @property-read string $env
 * @property-read string $secretsDir
 * @property-read string $project
 * @property-read string $location
 * @property-read string $keyRing
 * @property-read string $cryptoKey
 * @property-read array  $mappings
 */
class KMSBootstrapConfig
{
    private const WHITELIST = [
        'basePath',
        'env',
        'secretsDir',
        'project',
        'location',
        'keyRing',
        'cryptoKey',
        'mappings',
    ];
    private array $internal;

    public function __construct(string $basePath, $config)
    {
        $this->internal['basePath'] = $basePath;

        if (is_string($config)) {
            // Assuming this is a file path
            if (file_exists($config)) {
                $this->loadFromFile($config);
            } else {
                throw new InvalidArgumentException("Configuration file $config does not exist");
            }
        } elseif (is_array($config)) {
            $this->mergeConfig($config);
        } else {
            throw new InvalidArgumentException('Configuration has to be a string or an array');
        }
    }

    private function loadFromFile(string $filePath): void
    {
        $this->mergeConfig(require $filePath);
    }

    private function mergeConfig(array $config): void
    {
        $this->internal = array_merge($this->internal, $config);
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __get($name)
    {
        if (!in_array($name, self::WHITELIST, true)) {
            return null;
        }

        $val = $this->internal[$name] ?? null;

        if (is_string($val)) {
            $val = str_replace('{env}', $this->env, $val);
        }

        return $val;
    }
}
