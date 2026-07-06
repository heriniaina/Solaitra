<?php

namespace Solaitra\Base\Config;

use CodeIgniter\Config\BaseConfig;

class GoogleDrive extends BaseConfig
{
    /**
     * Path to the Google Service Account JSON credentials file.
     * Can be absolute or relative to the writeable directory.
     */
    public string $credentialsPath = ROOTPATH . 'modules/google-service-account.json';

    /**
     * Google Drive Folder ID where backups will be stored.
     */
    public string $folderId = '1qkPuggOk458PhAU9MnrqOVNFilQt8IWq';
}
