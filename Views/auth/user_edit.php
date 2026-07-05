<?php echo $this->extend('admin');?>
<?php $this->section('content'); ?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><?= esc($page_title) ?></h4>
            </div>
            <div class="card-body">
                <form action="<?= site_url('admin/auth/users/edit/' . $user->id) ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Groups Selection -->
                    <div class="mb-4">
                        <label class="form-label d-block font-weight-bold"><strong>User Groups / Roles:</strong></label>
                        <p class="text-muted small">Groups define the base permissions for this user.</p>
                        <div class="row">
                            <?php foreach ($all_groups as $groupKey => $groupInfo): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="groups[]" value="<?= esc($groupKey) ?>" id="group_<?= esc($groupKey) ?>" <?= in_array($groupKey, $user_groups) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="group_<?= esc($groupKey) ?>">
                                            <strong><?= esc($groupInfo['title']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= esc($groupInfo['description'] ?? '') ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Direct Permissions Selection -->
                    <div class="mb-4">
                        <label class="form-label d-block font-weight-bold"><strong>Direct / Custom Permissions:</strong></label>
                        <p class="text-muted small">Select permissions to grant directly to this user, independent of their group roles.</p>
                        <div class="row">
                            <?php foreach ($all_permissions as $permKey => $permDesc): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= esc($permKey) ?>" id="perm_<?= esc($permKey) ?>" <?= in_array($permKey, $user_permissions) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="perm_<?= esc($permKey) ?>">
                                            <code><?= esc($permKey) ?></code>
                                            <br>
                                            <small class="text-muted"><?= esc($permDesc) ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="<?= site_url('admin/auth/users') ?>" class="btn btn-secondary">Back to List</a>
                        <button type="submit" class="btn btn-primary">Save Permissions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
