<?php echo $this->extend('admin');?>
<?php $this->section('content'); ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title"><?= esc($page_title) ?></h4>
                <div>
                    <a href="<?php echo site_url('admin/auth/groups') ?>" class="btn btn-info btn-sm text-white">Groups & Permissions Matrix</a>
                    <a href="<?php echo site_url('admin/users/create') ?>" class="btn btn-success btn-sm">Create User</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (session()->has('message')): ?>
                    <div class="alert alert-success">
                        <?= session('message') ?>
                    </div>
                <?php endif; ?>
                <?php if (session()->has('error')): ?>
                    <div class="alert alert-danger">
                        <?= session('error') ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Groups (Roles)</th>
                                <th>Direct Permissions</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user->id ?></td>
                                    <td><strong><?= esc($user->username) ?></strong></td>
                                    <td><?= esc($user->email) ?></td>
                                    <td>
                                        <?php 
                                        $userGroups = $user->getGroups();
                                        if (!empty($userGroups)): 
                                            foreach ($userGroups as $group):
                                        ?>
                                            <span class="badge bg-primary text-white me-1"><?= esc($group) ?></span>
                                        <?php 
                                            endforeach;
                                        else:
                                        ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $userPermissions = $user->getPermissions();
                                        if (!empty($userPermissions)): 
                                            foreach ($userPermissions as $perm):
                                        ?>
                                            <span class="badge bg-warning text-dark me-1"><?= esc($perm) ?></span>
                                        <?php 
                                            endforeach;
                                        else:
                                        ?>
                                            <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= site_url('admin/auth/users/edit/' . $user->id) ?>" class="btn btn-sm btn-outline-primary">Manage Access</a>
                                    </td>
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
