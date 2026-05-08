<?php
namespace Portal;

class App
{
    public static function boot(): void
    {
        spl_autoload_register(function ($class) {
            if (!str_starts_with($class, 'Portal\\')) return;
            $relative = substr($class, strlen('Portal\\'));
            $path = PORTAL_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($path)) require $path;
        });
        require_once PORTAL_ROOT . '/src/helpers.php';
        if (getenv('PORTAL_DEBUG') !== 'false') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
        Auth::start();
    }

    public static function routes(Router $r): void
    {
        $r->get('/',          'AuthController@index');
        $r->get('/login',     'AuthController@showLogin');
        $r->post('/login',    'AuthController@doLogin');
        $r->post('/logout',   'AuthController@doLogout');

        $r->get('/dashboard', 'DashboardController@index');

        $r->get('/orders',           'OrderController@index');
        $r->get('/orders/{id}',      'OrderController@show');

        // Repro flow (AEC blueprint orders — upload PDF, auto-analyze)
        $r->get('/repro',            'ReproController@start');
        $r->post('/repro/upload',    'ReproController@upload');
        $r->post('/repro/update',    'ReproController@updateFile');
        $r->post('/repro/remove',    'ReproController@removeFile');
        $r->post('/repro/clear',     'ReproController@clearDraft');
        $r->post('/repro/place',     'ReproController@place');

        // Reprint flow (re-order an old order)
        $r->get('/reprint',                'ReprintController@index');
        $r->get('/reprint/{id}',           'ReprintController@show');
        $r->post('/reprint/{id}/place',    'ReprintController@place');

        // Catalog flow
        $r->get('/catalog',                 'CatalogController@index');
        $r->get('/catalog/{id}',            'CatalogController@show');
        $r->post('/catalog/{id}/upload',    'CatalogController@upload');
        $r->post('/catalog/{id}/update',    'CatalogController@update');
        $r->post('/catalog/{id}/place',     'CatalogController@place');
        $r->post('/catalog/{id}/clear',     'CatalogController@clearDraft');

        // Admin
        $r->get('/admin',                                          'AdminController@index');
        $r->get('/admin/customers/new',                            'AdminController@newCustomerForm');
        $r->post('/admin/customers/new',                           'AdminController@createCustomer');
        $r->get('/admin/customers/{id}',                           'AdminController@showCustomer');
        $r->post('/admin/customers/{id}/save',                     'AdminController@saveCustomer');
        $r->post('/admin/customers/{id}/users/new',                'AdminController@addUser');
        $r->post('/admin/customers/{id}/addresses/new',            'AdminController@addAddress');
        $r->post('/admin/customers/{id}/products/new',             'AdminController@addProduct');
        $r->post('/admin/customers/{id}/products/{pid}/save',      'AdminController@saveProduct');
        $r->post('/admin/customers/{id}/rules/save',               'AdminController@saveRules');
        // Admin: impersonation + admin user management
        $r->post('/admin/impersonate/stop',                       'AdminController@stopImpersonation');
        $r->post('/admin/impersonate/{id}',                       'AdminController@impersonate');
        $r->post('/admin/admins/new',                             'AdminController@addAdminUser');
    }
}
