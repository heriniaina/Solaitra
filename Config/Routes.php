<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('admin/auth', ['namespace' => '\\Solaitra\\Base\\Controllers', 'filter' => 'group:admin,superadmin'], static function ($routes) {
    $routes->get('groups', 'AuthManagerController::groups');
    $routes->get('users', 'AuthManagerController::users');
    $routes->match(['GET', 'POST'], 'users/edit/(:num)', 'AuthManagerController::editUser/$1');
});

