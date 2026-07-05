<?php echo $this->extend('admin');?>
<?php $this->section('content'); ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title"><?= esc($page_title) ?></h4>
                <div>
                    <a href="<?php echo site_url('admin/auth/users') ?>" class="btn btn-primary btn-sm">Manage User Permissions</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Permission / Role</th>
                                <?php foreach ($groups as $groupKey => $groupInfo): ?>
                                    <th class="text-center">
                                        <strong><?= esc($groupInfo['title']) ?></strong>
                                        <br>
                                        <small class="text-muted" style="font-weight: normal; font-size: 11px;"><?= esc($groupInfo['description'] ?? '') ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permKey => $permDesc): ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($permKey) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= esc($permDesc) ?></small>
                                    </td>
                                    <?php foreach ($groups as $groupKey => $groupInfo): ?>
                                        <td class="text-center">
                                            <?php
                                                // Check if the permission matches the group matrix rules
                                                $hasPermission = false;
                                                $groupMatrix = $matrix[$groupKey] ?? [];
                                                foreach ($groupMatrix as $pattern) {
                                                    // Handle wildcards like users.* or admin.*
                                                    $regex = '/^' . str_replace('*', '.*', $pattern) . '$/';
                                                    if (preg_match($regex, $permKey)) {
                                                        $hasPermission = true;
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <?php if ($hasPermission): ?>
                                                <span class="badge bg-success text-white">Allowed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white">Denied</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
