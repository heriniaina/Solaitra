<?php

namespace Solaitra\Base\Controllers;

use CodeIgniter\Shield\Entities\User;
use Config\AuthGroups;

class AuthManagerController extends BaseController
{
    protected $authGroups;

    public function __construct()
    {
        $this->authGroups = config('AuthGroups');
        $this->data['page_title'] = 'Auth Manager';
    }

    // List all groups and their permissions matrix
    public function groups()
    {
        $this->data['page_title'] = 'Groups & Permissions Matrix';
        $this->data['groups'] = $this->authGroups->groups;
        $this->data['permissions'] = $this->authGroups->permissions;
        $this->data['matrix'] = $this->authGroups->matrix;

        return view('\Solaitra\Base\Views\auth\groups', $this->data);
    }

    // List users with their groups and custom permissions
    public function users()
    {
        $this->data['page_title'] = 'User Permissions Manager';
        $userProvider = auth()->getProvider();
        $this->data['users'] = $userProvider->findAll();

        return view('\Solaitra\Base\Views\auth\users', $this->data);
    }

    // Edit user's groups and direct permissions
    public function editUser($id)
    {
        $userProvider = auth()->getProvider();
        $user = $userProvider->find($id);

        if (!$user) {
            return redirect()->to('admin/auth/users')->with('error', 'User not found.');
        }

        if ($this->request->is('post')) {
            // Get selected groups and permissions
            $selectedGroups = $this->request->getPost('groups') ?? [];
            $selectedPermissions = $this->request->getPost('permissions') ?? [];

            // Sync groups
            $user->syncGroups(...$selectedGroups);

            // Sync direct permissions
            $user->syncPermissions(...$selectedPermissions);

            return redirect()->to('admin/auth/users')->with('message', 'User groups and permissions updated successfully.');
        }

        $this->data['page_title'] = 'Edit User Roles: ' . esc($user->username);
        $this->data['user'] = $user;
        $this->data['all_groups'] = $this->authGroups->groups;
        $this->data['all_permissions'] = $this->authGroups->permissions;
        
        // Get user's current groups and direct permissions
        $this->data['user_groups'] = $user->getGroups();
        $this->data['user_permissions'] = $user->getPermissions();

        return view('\Solaitra\Base\Views\auth\user_edit', $this->data);
    }
}
