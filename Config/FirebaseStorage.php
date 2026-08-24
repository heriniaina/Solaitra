<?php

namespace Solaitra\Base\Config;

use CodeIgniter\Config\BaseConfig;

class FirebaseStorage extends BaseConfig
{
    /**
     * Path to the Firebase / Google Cloud Service Account JSON credentials file.
     * Can be absolute or relative to the writeable directory.
     */
    public string $credentialsPath = ROOTPATH . 'modules/google-service-account.json';

    /**
     * Firebase Storage bucket name (e.g. project-id.appspot.com)
     */
    public string $bucketName = '';
}
