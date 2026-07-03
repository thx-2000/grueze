<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ContactController;
use App\Controllers\LegalController;
use App\Controllers\LogController;
use App\Controllers\MailController;
use App\Controllers\PasskeyController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;
use App\Controllers\TagController;
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
use App\Repositories\PasskeyRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TagRepository;
use App\Repositories\UserRepository;
use App\Services\CsvExportService;
use App\Services\ContactImportService;
use App\Services\MailService;
use App\Services\PasswordResetService;
use App\Services\UploadService;
use App\Services\WebAuthnService;
use App\Services\XlsxReader;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    http_response_code(500);
    echo '<h1>PHP-Fehler</h1>';
    echo '<p><strong>' . htmlspecialchars($error['message'] ?? 'Unbekannter Fehler', ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p>Datei: ' . htmlspecialchars($error['file'] ?? '-', ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Zeile: ' . htmlspecialchars((string) ($error['line'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</p>';
});

require dirname(__DIR__) . '/src/Core/Autoloader.php';
require dirname(__DIR__) . '/src/Support/helpers.php';
require dirname(__DIR__) . '/src/Support/Redirect.php';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

try {
    Autoloader::register();
    Config::load(dirname(__DIR__));
    Session::start();

    Container::factory(PDO::class, static fn () => Database::connect());
    Container::factory(UserRepository::class, static fn () => new UserRepository(Container::get(PDO::class)));
    Container::factory(CategoryRepository::class, static fn () => new CategoryRepository(Container::get(PDO::class)));
    Container::factory(TagRepository::class, static fn () => new TagRepository(Container::get(PDO::class)));
    Container::factory(ContactRepository::class, static fn () => new ContactRepository(Container::get(PDO::class)));
    Container::factory(LogRepository::class, static fn () => new LogRepository(Container::get(PDO::class)));
    Container::factory(SettingRepository::class, static fn () => new SettingRepository(Container::get(PDO::class)));
    Container::factory(PasskeyRepository::class, static fn () => new PasskeyRepository(Container::get(PDO::class)));
    Container::factory(Auth::class, static fn () => new Auth(
        Container::get(UserRepository::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(UploadService::class, static fn () => new UploadService());
    Container::factory(CsvExportService::class, static fn () => new CsvExportService());
    Container::factory(XlsxReader::class, static fn () => new XlsxReader());
    Container::factory(MailService::class, static fn () => new MailService(Container::get(LogRepository::class)));
    Container::factory(ContactImportService::class, static fn () => new ContactImportService(
        Container::get(PDO::class),
        Container::get(ContactRepository::class),
        Container::get(LogRepository::class),
        Container::get(XlsxReader::class)
    ));
    Container::factory(PasswordResetService::class, static fn () => new PasswordResetService(
        Container::get(PDO::class),
        Container::get(UserRepository::class),
        Container::get(MailService::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(WebAuthnService::class, static fn () => new WebAuthnService());

    Container::factory(AuthController::class, static fn () => new AuthController(
        Container::get(Auth::class),
        Container::get(LogRepository::class),
        Container::get(PasswordResetService::class),
        Container::get(PasskeyRepository::class)
    ));
    Container::factory(ContactController::class, static fn () => new ContactController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class),
        Container::get(UserRepository::class),
        Container::get(LogRepository::class),
        Container::get(UploadService::class),
        Container::get(CsvExportService::class),
        Container::get(ContactImportService::class)
    ));
    Container::factory(UserController::class, static fn () => new UserController(
        Container::get(Auth::class),
        Container::get(UserRepository::class),
        Container::get(LogRepository::class),
        Container::get(PasswordResetService::class),
        Container::get(PasskeyRepository::class)
    ));
    Container::factory(PasskeyController::class, static fn () => new PasskeyController(
        Container::get(Auth::class),
        Container::get(PasskeyRepository::class),
        Container::get(WebAuthnService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(SetupController::class, static fn () => new SetupController(
        Container::get(Auth::class),
        Container::get(UserRepository::class)
    ));
    Container::factory(CategoryController::class, static fn () => new CategoryController(
        Container::get(Auth::class),
        Container::get(CategoryRepository::class)
    ));
    Container::factory(TagController::class, static fn () => new TagController(
        Container::get(Auth::class),
        Container::get(TagRepository::class)
    ));
    Container::factory(LogController::class, static fn () => new LogController(
        Container::get(Auth::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(LegalController::class, static fn () => new LegalController(
        Container::get(Auth::class)
    ));
    Container::factory(MailController::class, static fn () => new MailController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(LogRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class),
        Container::get(UploadService::class)
    ));
    Container::factory(SettingsController::class, static fn () => new SettingsController(
        Container::get(Auth::class),
        Container::get(SettingRepository::class),
        Container::get(UploadService::class)
    ));
    Container::factory(SearchController::class, static fn () => new SearchController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(UserRepository::class)
    ));

    $router = new Router();
    $router->get('/', [ContactController::class, 'index']);
    $router->get('/search', [SearchController::class, 'index']);
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
    $router->post('/forgot-password', [AuthController::class, 'sendReset']);
    $router->get('/reset-password', [AuthController::class, 'showResetPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
    $router->get('/impressum', [LegalController::class, 'impressum']);
    $router->get('/datenschutz', [LegalController::class, 'datenschutz']);
    $router->get('/setup/admin', [SetupController::class, 'showAdminForm']);
    $router->post('/setup/admin', [SetupController::class, 'storeAdmin']);

    $router->get('/contacts/create', [ContactController::class, 'create']);
    $router->get('/contacts/import', [ContactController::class, 'importForm']);
    $router->post('/contacts/import', [ContactController::class, 'importXlsx']);
    $router->post('/contacts/store', [ContactController::class, 'store']);
    $router->get('/contacts/edit', [ContactController::class, 'edit']);
    $router->post('/contacts/update', [ContactController::class, 'update']);
    $router->post('/contacts/delete', [ContactController::class, 'delete']);
    $router->post('/contacts/bulk-update', [ContactController::class, 'bulkUpdate']);
    $router->get('/contacts/export', [ContactController::class, 'export']);

    $router->post('/categories/store', [CategoryController::class, 'store']);
    $router->post('/tags/store', [TagController::class, 'store']);
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/account', [UserController::class, 'account']);
    $router->post('/account/password', [UserController::class, 'updateOwnPassword']);
    $router->post('/users/store', [UserController::class, 'store']);
    $router->post('/users/set-password', [UserController::class, 'setPassword']);
    $router->post('/users/send-reset', [UserController::class, 'sendReset']);
    $router->post('/users/toggle-active', [UserController::class, 'toggleActive']);
    $router->post('/users/passkeys/reset', [UserController::class, 'resetPasskeys']);
    $router->post('/users/impersonate', [UserController::class, 'impersonate']);
    $router->post('/users/impersonate/stop', [UserController::class, 'stopImpersonation']);

    $router->get('/security/passkeys', [PasskeyController::class, 'index']);
    $router->post('/passkeys/register/options', [PasskeyController::class, 'registrationOptions']);
    $router->post('/passkeys/register', [PasskeyController::class, 'register']);
    $router->post('/passkeys/auth/options', [PasskeyController::class, 'authenticationOptions']);
    $router->post('/passkeys/authenticate', [PasskeyController::class, 'authenticate']);
    $router->post('/passkeys/delete', [PasskeyController::class, 'delete']);

    $router->post('/mail/compose', [MailController::class, 'compose']);
    $router->get('/mail/compose', [MailController::class, 'compose']);
    $router->post('/mail/compose-all', [MailController::class, 'composeAll']);
    $router->post('/mail/test', [MailController::class, 'test']);
    $router->post('/mail/start', [MailController::class, 'start']);
    $router->get('/mail/status', [MailController::class, 'status']);
    $router->post('/mail/batch', [MailController::class, 'batch']);

    $router->get('/logs/audit', [LogController::class, 'audit']);
    $router->get('/logs/mail', [LogController::class, 'mail']);
    $router->get('/settings/branding', [SettingsController::class, 'branding']);
    $router->post('/settings/branding', [SettingsController::class, 'updateBranding']);
    $router->get('/settings/mail-footer', [SettingsController::class, 'mailFooter']);
    $router->post('/settings/mail-footer', [SettingsController::class, 'updateMailFooter']);
    $router->get('/settings/visibility', [SettingsController::class, 'visibility']);
    $router->post('/settings/visibility', [SettingsController::class, 'updateVisibility']);

    $router->dispatch(new Request());
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<h1>Fehler</h1><p>' . e($exception->getMessage()) . '</p>';
}
