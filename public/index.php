<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ContactController;
use App\Controllers\LogController;
use App\Controllers\MailController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\UserRepository;
use App\Services\CsvExportService;
use App\Services\MailService;
use App\Services\PasswordResetService;
use App\Services\UploadService;

require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Redirect.php';

Autoloader::register();
Config::load(dirname(__DIR__));
Session::start();

Container::factory(PDO::class, static fn () => Database::connect());
Container::factory(UserRepository::class, static fn () => new UserRepository(Container::get(PDO::class)));
Container::factory(CategoryRepository::class, static fn () => new CategoryRepository(Container::get(PDO::class)));
Container::factory(ContactRepository::class, static fn () => new ContactRepository(Container::get(PDO::class)));
Container::factory(LogRepository::class, static fn () => new LogRepository(Container::get(PDO::class)));
Container::factory(Auth::class, static fn () => new Auth(Container::get(UserRepository::class)));
Container::factory(UploadService::class, static fn () => new UploadService());
Container::factory(CsvExportService::class, static fn () => new CsvExportService());
Container::factory(MailService::class, static fn () => new MailService(Container::get(LogRepository::class)));
Container::factory(PasswordResetService::class, static fn () => new PasswordResetService(
    Container::get(PDO::class),
    Container::get(UserRepository::class),
    Container::get(MailService::class)
));

Container::factory(AuthController::class, static fn () => new AuthController(
    Container::get(Auth::class),
    Container::get(LogRepository::class),
    Container::get(PasswordResetService::class)
));
Container::factory(ContactController::class, static fn () => new ContactController(
    Container::get(Auth::class),
    Container::get(ContactRepository::class),
    Container::get(CategoryRepository::class),
    Container::get(LogRepository::class),
    Container::get(UploadService::class),
    Container::get(CsvExportService::class)
));
Container::factory(UserController::class, static fn () => new UserController(
    Container::get(Auth::class),
    Container::get(UserRepository::class)
));
Container::factory(CategoryController::class, static fn () => new CategoryController(
    Container::get(Auth::class),
    Container::get(CategoryRepository::class)
));
Container::factory(LogController::class, static fn () => new LogController(
    Container::get(Auth::class),
    Container::get(LogRepository::class)
));
Container::factory(MailController::class, static fn () => new MailController(
    Container::get(Auth::class),
    Container::get(ContactRepository::class),
    Container::get(MailService::class),
    Container::get(UploadService::class)
));

$router = new Router();
$router->get('/', [ContactController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendReset']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

$router->get('/contacts/create', [ContactController::class, 'create']);
$router->post('/contacts/store', [ContactController::class, 'store']);
$router->get('/contacts/edit', [ContactController::class, 'edit']);
$router->post('/contacts/update', [ContactController::class, 'update']);
$router->post('/contacts/delete', [ContactController::class, 'delete']);
$router->get('/contacts/export', [ContactController::class, 'export']);

$router->post('/categories/store', [CategoryController::class, 'store']);
$router->get('/users', [UserController::class, 'index']);
$router->post('/users/store', [UserController::class, 'store']);

$router->post('/mail/compose', [MailController::class, 'compose']);
$router->get('/mail/compose', [MailController::class, 'compose']);
$router->post('/mail/test', [MailController::class, 'test']);
$router->post('/mail/start', [MailController::class, 'start']);
$router->post('/mail/batch', [MailController::class, 'batch']);

$router->get('/logs/audit', [LogController::class, 'audit']);
$router->get('/logs/mail', [LogController::class, 'mail']);

try {
    $router->dispatch(new Request());
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<h1>Fehler</h1><p>' . e($exception->getMessage()) . '</p>';
}
