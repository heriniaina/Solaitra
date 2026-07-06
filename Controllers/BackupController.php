<?php

namespace Solaitra\Base\Controllers;

use Solaitra\Base\Libraries\GoogleDriveService;
use Exception;

class BackupController extends BaseController
{
    protected GoogleDriveService $driveService;

    public function __construct()
    {
        $this->driveService = new GoogleDriveService();
        $this->data['page_title'] = 'Database Backup Manager';
    }

    /**
     * List backups from Google Drive (or local backups if Drive is not configured).
     */
    public function index()
    {
        $this->data['is_configured'] = $this->driveService->isConfigured();
        $this->data['init_error'] = $this->driveService->getInitError();
        $this->data['folder_id'] = $this->driveService->getFolderId();
        $this->data['backups'] = [];
        $this->data['local_backups'] = $this->getLocalBackups();

        if ($this->data['is_configured']) {
            $driveFiles = $this->driveService->listBackups();
            foreach ($driveFiles as $file) {
                $this->data['backups'][] = [
                    'id'          => $file->getId(),
                    'name'        => $file->getName(),
                    'size'        => $file->getSize() ? number_format($file->getSize() / 1024, 2) . ' KB' : 'Unknown',
                    'created_at'  => date('Y-m-d H:i:s', strtotime($file->getCreatedTime())),
                    'source'      => 'google_drive'
                ];
            }
        }

        return view('\Solaitra\Base\Views\backup\index', $this->data);
    }

    /**
     * Trigger a new database backup.
     */
    public function create()
    {
        $db = \Config\Database::connect();
        $hostname = $db->hostname;
        $username = $db->username;
        $password = $db->password;
        $database = $db->database;
        $port     = $db->port ?: 3306;
        $driver   = $db->DBDriver;

        if ($driver !== 'MySQLi') {
            return redirect()->to('admin/backups')->with('error', 'Database driver is not supported. MySQLi is required.');
        }

        $mysqldumpPath = 'mysqldump';
        if (file_exists('/opt/homebrew/bin/mysqldump')) {
            $mysqldumpPath = '/opt/homebrew/bin/mysqldump';
        } elseif (file_exists('/usr/local/bin/mysqldump')) {
            $mysqldumpPath = '/usr/local/bin/mysqldump';
        } elseif (file_exists('/usr/bin/mysqldump')) {
            $mysqldumpPath = '/usr/bin/mysqldump';
        }

        $filename = $database . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $tempDir = WRITEPATH . 'backups';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        $passArg = !empty($password) ? '-p' . escapeshellarg($password) : '';
        $cmd = sprintf(
            '%s -h %s -P %s -u %s %s %s > %s 2>&1',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($hostname),
            escapeshellarg($port),
            escapeshellarg($username),
            $passArg,
            escapeshellarg($database),
            escapeshellarg($tempPath)
        );

        $output = shell_exec($cmd);

        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            $errorMsg = 'Backup failed: Temporary file was not created or is empty.';
            if (!empty($output)) {
                $errorMsg .= ' Details: ' . trim($output);
            }
            return redirect()->to('admin/backups')->with('error', $errorMsg);
        }

        // Check if there was an error in export file itself
        $firstLine = fgets(fopen($tempPath, 'r'));
        if (str_contains($firstLine, 'mysqldump: error') || str_contains($firstLine, 'Access denied')) {
            $errorMsg = 'Backup failed: ' . trim(file_get_contents($tempPath));
            unlink($tempPath);
            return redirect()->to('admin/backups')->with('error', $errorMsg);
        }

        // Compress file
        $gzipPath = $tempPath . '.gz';
        $fp = fopen($tempPath, 'r');
        $gp = gzopen($gzipPath, 'w9');
        $isCompressed = false;

        if ($fp && $gp) {
            while (!feof($fp)) {
                gzwrite($gp, fread($fp, 65536));
            }
            fclose($fp);
            gzclose($gp);
            unlink($tempPath);
            $finalPath = $gzipPath;
            $finalFilename = $filename . '.gz';
            $isCompressed = true;
        } else {
            $finalPath = $tempPath;
            $finalFilename = $filename;
        }

        // Upload if Google Drive is configured
        if ($this->driveService->isConfigured()) {
            try {
                $fileId = $this->driveService->uploadBackup($finalPath, $finalFilename);
                unlink($finalPath); // Clean up local file after successful upload
                return redirect()->to('admin/backups')->with('message', 'Database backup created and uploaded to Google Drive successfully. ID: ' . $fileId);
            } catch (Exception $e) {
                return redirect()->to('admin/backups')->with('error', 'Backup created but upload to Google Drive failed: ' . $e->getMessage() . '. Local copy saved as: ' . $finalFilename);
            }
        }

        // Retain local backup if Google Drive is not configured
        return redirect()->to('admin/backups')->with('message', 'Database backup created successfully (saved locally because Google Drive is not configured). File: ' . $finalFilename);
    }

    /**
     * Download a backup.
     */
    public function download(string $id)
    {
        // 1. Check if it's a local backup file
        $localPath = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . basename($id);
        if (file_exists($localPath)) {
            return $this->response->download($localPath, null);
        }

        // 2. Otherwise download from Google Drive
        if ($this->driveService->isConfigured()) {
            try {
                $fileInfo = $this->driveService->getFileInfo($id);
                $content = $this->driveService->downloadBackup($id);

                return $this->response
                    ->setHeader('Content-Type', $fileInfo->getMimeType() ?: 'application/octet-stream')
                    ->setHeader('Content-Disposition', 'attachment; filename="' . $fileInfo->getName() . '"')
                    ->setBody($content);
            } catch (Exception $e) {
                return redirect()->to('admin/backups')->with('error', 'Failed to download backup from Google Drive: ' . $e->getMessage());
            }
        }

        return redirect()->to('admin/backups')->with('error', 'Backup file not found.');
    }

    /**
     * Delete a backup.
     */
    public function delete(string $id)
    {
        // 1. Check if it's local
        $localPath = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . basename($id);
        if (file_exists($localPath)) {
            unlink($localPath);
            return redirect()->to('admin/backups')->with('message', 'Local backup file deleted successfully.');
        }

        // 2. Otherwise delete from Google Drive
        if ($this->driveService->isConfigured()) {
            try {
                $this->driveService->deleteBackup($id);
                return redirect()->to('admin/backups')->with('message', 'Backup deleted from Google Drive successfully.');
            } catch (Exception $e) {
                return redirect()->to('admin/backups')->with('error', 'Failed to delete backup from Google Drive: ' . $e->getMessage());
            }
        }

        return redirect()->to('admin/backups')->with('error', 'Backup file not found.');
    }

    /**
     * Get list of local backups.
     */
    protected function getLocalBackups(): array
    {
        $backups = [];
        $dir = WRITEPATH . 'backups';

        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.gitignore') {
                    continue;
                }
                
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path)) {
                    $backups[] = [
                        'id'          => $file,
                        'name'        => $file,
                        'size'        => number_format(filesize($path) / 1024, 2) . ' KB',
                        'created_at'  => date('Y-m-d H:i:s', filemtime($path)),
                        'source'      => 'local'
                    ];
                }
            }
        }

        // Sort descending by creation date
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $backups;
    }
}
