<?php

namespace Solaitra\Base\Libraries;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\Bucket;
use Exception;
use RuntimeException;

class FirebaseStorageService
{
    protected ?StorageClient $storageClient = null;
    protected ?Bucket $bucket = null;
    protected string $bucketName = '';
    protected bool $initialized = false;
    protected ?string $initError = null;

    public function __construct()
    {
        try {
            $config = config('Solaitra\Base\Config\FirebaseStorage');
            $this->bucketName = env('FIREBASE_BUCKET_NAME') ?: ($config->bucketName ?? '');
            
            $credentialsPath = env('FIREBASE_SERVICE_ACCOUNT_JSON') 
                ?: env('GOOGLE_SERVICE_ACCOUNT_JSON') 
                ?: ($config->credentialsPath ?? ROOTPATH . 'modules/google-service-account.json');

            $options = [];
            $authConfigured = false;

            if (file_exists($credentialsPath)) {
                $options['keyFilePath'] = $credentialsPath;
                $authConfigured = true;
            } else {
                $jsonContent = env('FIREBASE_SERVICE_ACCOUNT_JSON_CONTENT') ?: env('GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT');
                if (!empty($jsonContent)) {
                    $decoded = json_decode($jsonContent, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                        $options['keyFile'] = $decoded;
                        $authConfigured = true;
                    }
                }
            }

            if ($authConfigured) {
                if (empty($this->bucketName)) {
                    $this->initError = 'Firebase Storage Bucket Name is not configured. Please set FIREBASE_BUCKET_NAME in your environment.';
                } else {
                    $this->storageClient = new StorageClient($options);
                    $this->bucket = $this->storageClient->bucket($this->bucketName);
                    $this->initialized = true;
                }
            } else {
                $this->initError = 'Service Account JSON credentials file not found and environment variables are empty or invalid.';
            }
        } catch (Exception $e) {
            $this->initError = $e->getMessage();
            log_message('error', 'FirebaseStorageService Initialization Failed: ' . $e->getMessage());
        }
    }

    public function isConfigured(): bool
    {
        return $this->initialized;
    }

    public function getInitError(): ?string
    {
        return $this->initError;
    }

    public function getBucketName(): string
    {
        return $this->bucketName;
    }

    public function listBackups(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $objects = $this->bucket->objects();
            $backups = [];
            foreach ($objects as $object) {
                $name = $object->name();
                // Filter files that seem to be backups
                if (str_contains($name, 'backup') || str_ends_with($name, '.gz') || str_ends_with($name, '.zip') || str_ends_with($name, '.sql')) {
                    $backups[] = $object;
                }
            }
            return $backups;
        } catch (Exception $e) {
            log_message('error', 'Firebase Storage List Error: ' . $e->getMessage());
            return [];
        }
    }

    public function uploadBackup(string $filePath, string $filename): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Firebase Storage is not configured. Details: ' . $this->initError);
        }

        $fileStream = fopen($filePath, 'r');
        if ($fileStream === false) {
            throw new RuntimeException('Failed to read backup file at: ' . $filePath);
        }

        try {
            $this->bucket->upload($fileStream, [
                'name' => $filename,
                'metadata' => [
                    'contentType' => 'application/x-gzip'
                ]
            ]);
            return $filename;
        } catch (Exception $e) {
            throw new RuntimeException('Firebase Storage Upload Failed: ' . $e->getMessage());
        } finally {
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
        }
    }

    public function downloadBackup(string $filename): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Firebase Storage is not configured. Details: ' . $this->initError);
        }

        try {
            $object = $this->bucket->object($filename);
            return $object->downloadAsString();
        } catch (Exception $e) {
            log_message('error', 'Firebase Storage Download Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getFileInfo(string $filename): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Firebase Storage is not configured. Details: ' . $this->initError);
        }

        try {
            $object = $this->bucket->object($filename);
            $info = $object->info();
            return [
                'name' => $object->name(),
                'size' => $info['size'] ?? 0,
                'contentType' => $info['contentType'] ?? 'application/octet-stream'
            ];
        } catch (Exception $e) {
            log_message('error', 'Firebase Storage Get Info Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteBackup(string $filename): bool
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Firebase Storage is not configured. Details: ' . $this->initError);
        }

        try {
            $object = $this->bucket->object($filename);
            $object->delete();
            return true;
        } catch (Exception $e) {
            log_message('error', 'Firebase Storage Delete Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
