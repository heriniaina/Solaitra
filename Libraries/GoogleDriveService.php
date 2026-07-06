<?php

namespace Solaitra\Base\Libraries;

use Google\Client;
use Google\Service\Drive;
use RuntimeException;
use Exception;

class GoogleDriveService
{
    protected ?Client $client = null;
    protected ?Drive $service = null;
    protected string $folderId = '';
    protected bool $initialized = false;
    protected ?string $initError = null;

    public function __construct()
    {
        try {
            $config = config('Solaitra\Base\Config\GoogleDrive');
            $this->folderId = env('GOOGLE_DRIVE_FOLDER_ID') ?: ($config->folderId ?? '');
            
            $credentialsPath = env('GOOGLE_SERVICE_ACCOUNT_JSON') ?: ($config->credentialsPath ?? WRITEPATH . 'google-service-account.json');

            $this->client = new Client();
            $this->client->setScopes([Drive::DRIVE]);

            $authConfigured = false;

            if (file_exists($credentialsPath)) {
                $this->client->setAuthConfig($credentialsPath);
                $authConfigured = true;
            } else {
                $jsonContent = env('GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT');
                if (!empty($jsonContent)) {
                    $decoded = json_decode($jsonContent, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                        $this->client->setAuthConfig($decoded);
                        $authConfigured = true;
                    }
                }
            }

            if ($authConfigured) {
                $this->service = new Drive($this->client);
                $this->initialized = true;
            } else {
                $this->initError = 'Credentials file not found and GOOGLE_SERVICE_ACCOUNT_JSON_CONTENT environment variable is empty or invalid.';
            }
        } catch (Exception $e) {
            $this->initError = $e->getMessage();
            log_message('error', 'GoogleDriveService Initialization Failed: ' . $e->getMessage());
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

    public function getFolderId(): string
    {
        return $this->folderId;
    }

    public function listBackups(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $optParams = [
            'pageSize' => 50,
            'fields'   => 'nextPageToken, files(id, name, mimeType, createdTime, size)',
            'orderBy'  => 'createdTime desc',
        ];

        $queries = ["trashed = false"];
        
        if (!empty($this->folderId)) {
            $queries[] = "'" . $this->folderId . "' in parents";
        }
        
        // Only return .sql or .zip or .gz files, or files with database backup in the name
        $queries[] = "name contains 'backup' or mimeType = 'application/x-gzip' or mimeType = 'application/zip' or name contains '.sql'";
        
        $optParams['q'] = implode(' and ', $queries);

        try {
            $results = $this->service->files->listFiles($optParams);
            return $results->getFiles();
        } catch (Exception $e) {
            log_message('error', 'Google Drive List Error: ' . $e->getMessage());
            return [];
        }
    }

    public function uploadBackup(string $filePath, string $filename): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Drive API is not configured. Details: ' . $this->initError);
        }

        $fileMetadata = new Drive\DriveFile([
            'name' => $filename,
        ]);

        if (!empty($this->folderId)) {
            $fileMetadata->setParents([$this->folderId]);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException('Failed to read backup file at: ' . $filePath);
        }

        $file = $this->service->files->create($fileMetadata, [
            'data'       => $content,
            'mimeType'   => 'application/x-gzip',
            'uploadType' => 'multipart',
            'fields'     => 'id'
        ]);

        return $file->id;
    }

    public function downloadBackup(string $fileId): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Drive API is not configured. Details: ' . $this->initError);
        }

        try {
            $response = $this->service->files->get($fileId, ['alt' => 'media']);
            return $response->getBody()->getContents();
        } catch (Exception $e) {
            log_message('error', 'Google Drive Download Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getFileInfo(string $fileId)
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Drive API is not configured. Details: ' . $this->initError);
        }

        try {
            return $this->service->files->get($fileId, ['fields' => 'id, name, mimeType, size']);
        } catch (Exception $e) {
            log_message('error', 'Google Drive Get Info Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteBackup(string $fileId): bool
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Drive API is not configured. Details: ' . $this->initError);
        }

        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            log_message('error', 'Google Drive Delete Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
