<?php

declare(strict_types=1);

namespace App\Services\GoogleCloud\KMS;

use Google\Cloud\Kms\V1\KeyManagementServiceClient;
use Illuminate\Support\Str;
use function array_filter;
use function array_merge;
use function basename;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function glob;
use function putenv;
use function str_replace;
use function substr;

/**
 * Setup the environment before loading the actual Laravel/Lumen application.
 * Useful when deploy to server-less platforms.
 */
class SetupEnvironment
{
    private const KMS_CONFIG = [
        'project'    => 'mathrix-education',
        'location'   => 'global',
        'key_ring'   => 'mathrix-drive-keyring',
        'crypto_key' => 'mathrix-drive-api-{env}',
    ];
    private const MAPPINGS   = [
        '.env'          => '.env',
        'jwt_auth.json' => 'storage/keychain/jwt_auth.json',
    ];

    /** @var string The application environment which will determine which files to decrypt */
    private $env;
    /** @var string The application base path */
    private $basePath;

    public function __construct(string $basePath)
    {
        putenv('SUPPRESS_GCLOUD_CREDS_WARNING=true');
        $this->basePath = $basePath;
        $this->env      = getenv('APP_ENV');
    }

    public function bootstrap(): void
    {
        if (getenv('APP_SKIP_SETUP') !== false || file_exists("$this->basePath/.env")) {
            // Environment has already been setup
            return;
        }

        $client = new KeyManagementServiceClient();

        foreach ($this->getEncryptedKeychain() as $encryptedFilePath) {
            $plainFilePath = substr($encryptedFilePath, 0, -4);
            $fileKey       = basename($plainFilePath); // Filename with .enc

            if (isset(self::MAPPINGS[$fileKey])) {
                $destination = $this->basePath . '/' . self::MAPPINGS[$fileKey];
            } else {
                $destination = $plainFilePath;
            }

            $response = $client->decrypt($this->getKeyName(), file_get_contents($encryptedFilePath));
            file_put_contents($destination, $response->getPlaintext());
        }
    }

    /**
     * Get the encryption key name.
     *
     * @return string
     */
    private function getKeyName(): string
    {
        return KeyManagementServiceClient::cryptoKeyName(
            self::KMS_CONFIG['project'],
            self::KMS_CONFIG['location'],
            self::KMS_CONFIG['key_ring'],
            str_replace('{env}', $this->env, self::KMS_CONFIG['crypto_key'])
        );
    }

    /**
     * Get the files in the environment keychain.
     *
     * @return array
     */
    private function getKeychain(): array
    {
        return array_merge(
            glob("$this->basePath/environments/$this->env/*"),
            glob("$this->basePath/environments/$this->env/.[!.]*")
        );
    }

    /**
     * Get the encoded files in the environment keychain.
     *
     * @return array
     */
    private function getEncryptedKeychain(): array
    {
        return array_filter($this->getKeychain(), static function (string $file) {
            return Str::endsWith($file, '.enc');
        });
    }
}
