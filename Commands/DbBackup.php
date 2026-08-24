<?php

namespace Solaitra\Base\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Solaitra\Base\Libraries\FirebaseStorageService;
use Exception;

class DbBackup extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Database';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'db:backup-firebase';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Creates a database backup (.sql.gz) and uploads it to Firebase Storage.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'db:backup-firebase';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Actually runs the command.
     */
    public function run(array $params)
    {
        CLI::write('Starting database backup process...', 'cyan');

        $db = \Config\Database::connect();
        $hostname = $db->hostname;
        $username = $db->username;
        $password = $db->password;
        $database = $db->database;
        $port     = $db->port ?: 3306;
        $driver   = $db->DBDriver;

        if ($driver !== 'MySQLi') {
            CLI::error("Database driver '{$driver}' is not supported. This tool requires MySQLi.");
            return;
        }

        // Determine mysqldump path
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

        CLI::write("Exporting database '{$database}' to temporary file...", 'yellow');

        // Check if password has special chars and handle securely
        // Using -p without space. If password is empty, omit.
        $passArg = !empty($password) ? '-p' . escapeshellarg($password) : '';
        
        // Use shell_exec to run mysqldump
        $cmd = sprintf(
            '%s --skip-ssl -h %s -P %s -u %s %s %s > %s 2>&1',
            escapeshellarg($mysqldumpPath),
            escapeshellarg($hostname),
            escapeshellarg($port),
            escapeshellarg($username),
            $passArg,
            escapeshellarg($database),
            escapeshellarg($tempPath)
        );

        $output = shell_exec($cmd);

        // Verify file was created and is not empty or containing mysql errors
        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            CLI::error("Backup failed. Temporary file was not created or is empty.");
            if (!empty($output)) {
                CLI::write("Error output: " . trim($output), 'red');
            }
            return;
        }

        // If file starts with an error like "mysqldump: [Warning]" or similar errors, check it
        $firstLine = fgets(fopen($tempPath, 'r'));
        if (
            (str_contains($firstLine, 'mysqldump:') && !str_contains(strtolower($firstLine), 'warning'))
            || str_contains($firstLine, 'Access denied')
            || stripos($firstLine, 'error') !== false
        ) {
            CLI::error("Backup failed. mysqldump returned an error:");
            CLI::write(file_get_contents($tempPath), 'red');
            unlink($tempPath);
            return;
        }

        CLI::write("Database exported successfully. Compressing file...", 'yellow');

        // Gzip compression
        $gzipPath = $tempPath . '.gz';
        $fp = fopen($tempPath, 'r');
        $gp = gzopen($gzipPath, 'w9');

        if ($fp && $gp) {
            while (!feof($fp)) {
                gzwrite($gp, fread($fp, 65536));
            }
            fclose($fp);
            gzclose($gp);
            unlink($tempPath); // Delete the uncompressed SQL file
            
            $finalPath = $gzipPath;
            $finalFilename = $filename . '.gz';
        } else {
            CLI::warning("Failed to compress file. Proceeding with uncompressed SQL file.");
            $finalPath = $tempPath;
            $finalFilename = $filename;
        }

        CLI::write("File ready: " . basename($finalPath) . " (" . number_format(filesize($finalPath) / 1024, 2) . " KB)", 'green');
        CLI::write("Uploading to Firebase Storage...", 'yellow');

        try {
            $firebaseService = new FirebaseStorageService();
            if (!$firebaseService->isConfigured()) {
                throw new Exception("Firebase Storage is not configured: " . $firebaseService->getInitError());
            }

            $fileId = $firebaseService->uploadBackup($finalPath, $finalFilename);
            CLI::write("Backup successfully uploaded to Firebase Storage! File ID: " . $fileId, 'green');
            
            // Delete local temp file
            unlink($finalPath);
            CLI::write("Temporary local backup file cleaned up.", 'cyan');
        } catch (Exception $e) {
            CLI::error("Upload failed: " . $e->getMessage());
            // Keep local backup file so user does not lose it
            CLI::write("Local backup retained at: " . $finalPath, 'yellow');
        }
    }
}
