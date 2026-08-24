<?php echo $this->extend('admin');?>
<?php $this->section('content'); ?>

<div class="row">
    <div class="col-md-12">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 text-gray-800 mb-0"><?= esc($page_title) ?></h1>
                <p class="text-muted small mb-0">Manage, generate, and store database backups securely using Firebase Storage and Local Storage.</p>
            </div>
            
            <form action="<?= site_url('admin/backups/create') ?>" method="POST" onsubmit="showBackupLoading(this)">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2 px-4 shadow-sm" id="btn-backup-now">
                    <span class="spinner-border spinner-border-sm d-none" id="spinner-backup" role="status" aria-hidden="true"></span>
                    <i class="bi bi-cloud-arrow-up" id="icon-backup"></i>
                    <span>Backup Database Now</span>
                </button>
            </form>
        </div>

        <!-- Feedback Messages -->
        <?php if (session()->has('message')): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= session('message') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->has('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= session('error') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Configuration Card & DB Info -->
        <div class="row mb-4">
            <!-- Firebase Storage Connection Status -->
            <div class="col-md-7 mb-3 mb-md-0">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="p-3 rounded-3 <?= $is_configured ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                            <i class="bi <?= $is_configured ? 'bi-cloud-check-fill' : 'bi-cloud-slash-fill' ?> fs-3"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-1 fw-semibold">Firebase Storage</h5>
                            <?php if ($is_configured): ?>
                                <span class="badge bg-success text-white mb-2">Connected & Ready</span>
                                <div class="text-muted small">
                                    <strong>Bucket:</strong> <code><?= esc($bucket_name) ?></code>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark mb-2">Not Configured</span>
                                <div class="text-muted small">
                                    <p class="mb-1">Backups are currently saved in the local <code>writable/backups/</code> directory only.</p>
                                    <a href="#config-instructions" class="text-decoration-none text-warning" data-bs-toggle="collapse" role="button" aria-expanded="false">
                                        <i class="bi bi-info-circle me-1"></i>How to connect Firebase Storage?
                                    </a>
                                    <div class="collapse mt-2" id="config-instructions">
                                        <div class="p-3 bg-light rounded border small">
                                            <ol class="mb-0 ps-3">
                                                <li>Place your Service Account Credentials JSON file at <code>modules/google-service-account.json</code> (or set it in environment variable <code>FIREBASE_SERVICE_ACCOUNT_JSON</code>).</li>
                                                <li>Specify your target Firebase Storage Bucket Name in your environment variables: <code>FIREBASE_BUCKET_NAME=your-project.appspot.com</code>.</li>
                                            </ol>
                                            <?php if (!empty($init_error)): ?>
                                                <div class="text-danger mt-2 font-monospace">Error details: <?= esc($init_error) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Information -->
            <div class="col-md-5">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-start gap-3">
                        <div class="p-3 bg-primary-subtle text-primary rounded-3">
                            <i class="bi bi-database-fill-check fs-3"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-1 fw-semibold">Target Database</h5>
                            <span class="badge bg-primary text-white mb-2"><?= esc(\Config\Database::connect()->DBDriver) ?></span>
                            <div class="text-muted small">
                                <div><strong>Database:</strong> <code><?= esc(\Config\Database::connect()->database) ?></code></div>
                                <div><strong>Host:</strong> <code><?= esc(\Config\Database::connect()->hostname) ?></code></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backups List Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-semibold text-gray-800">
                    <i class="bi bi-list-nested me-2 text-primary"></i>Backup Files
                </h5>
                <span class="badge bg-secondary text-white font-monospace"><?= count($backups) + count($local_backups) ?> Files Total</span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small uppercase">
                            <tr>
                                <th class="ps-4">File Name</th>
                                <th>Size</th>
                                <th>Created Date</th>
                                <th>Destination</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <!-- Firebase Storage Backups -->
                             <?php foreach ($backups as $backup): ?>
                                 <tr>
                                     <td class="ps-4 py-3">
                                         <div class="d-flex align-items-center gap-2">
                                             <i class="bi bi-file-earmark-zip-fill text-danger fs-5"></i>
                                             <span class="fw-semibold text-dark"><?= esc($backup['name']) ?></span>
                                         </div>
                                     </td>
                                     <td><?= esc($backup['size']) ?></td>
                                     <td><?= esc($backup['created_at']) ?></td>
                                     <td>
                                         <span class="badge bg-warning-subtle text-warning border border-warning-subtle d-inline-flex align-items-center gap-1">
                                             <i class="bi bi-fire"></i> Firebase Storage
                                         </span>
                                     </td>
                                     <td class="text-end pe-4">
                                         <div class="btn-group gap-2">
                                             <a href="<?= site_url('admin/backups/download/' . $backup['id']) ?>" class="btn btn-sm btn-outline-primary rounded-2" title="Download">
                                                 <i class="bi bi-download"></i> Download
                                             </a>
                                             <form action="<?= site_url('admin/backups/delete/' . $backup['id']) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this backup from Firebase Storage? This cannot be undone.')" class="d-inline">
                                                 <?= csrf_field() ?>
                                                 <button type="submit" class="btn btn-sm btn-outline-danger rounded-2" title="Delete">
                                                     <i class="bi bi-trash"></i> Delete
                                                 </button>
                                             </form>
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>

                            <!-- Local Backups -->
                            <?php foreach ($local_backups as $local): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-file-earmark-zip-fill text-secondary fs-5"></i>
                                            <span class="fw-semibold text-dark"><?= esc($local['name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= esc($local['size']) ?></td>
                                    <td><?= esc($local['created_at']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-hdd"></i> Local Storage
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group gap-2">
                                            <a href="<?= site_url('admin/backups/download/' . $local['id']) ?>" class="btn btn-sm btn-outline-primary rounded-2" title="Download">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <form action="<?= site_url('admin/backups/delete/' . $local['id']) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this local backup file?')" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-2" title="Delete">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($backups) && empty($local_backups)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-folder2-open fs-1 mb-2 d-block text-secondary opacity-50"></i>
                                        <p class="mb-0">No backup files found. Click "Backup Database Now" to generate one.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Bootstrap Icons CDN just in case it is not already in the main template -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
function showBackupLoading(form) {
    const btn = document.getElementById('btn-backup-now');
    const spinner = document.getElementById('spinner-backup');
    const icon = document.getElementById('icon-backup');
    
    if (btn && spinner && icon) {
        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        btn.querySelector('span:not(.spinner-border)').innerText = 'Exporting & Uploading...';
    }
}
</script>

<?php $this->endSection(); ?>
