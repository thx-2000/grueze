<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\CategoryController;
use App\Controllers\ContactController;
use App\Controllers\EventController;
use App\Controllers\GreetingController;
use App\Controllers\LegalController;
use App\Controllers\LogController;
use App\Controllers\MailController;
use App\Controllers\OrgaController;
use App\Controllers\PasskeyController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\SetupController;
use App\Controllers\StartController;
use App\Controllers\TagController;
use App\Controllers\TaxonomyController;
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
use App\Repositories\EventRepository;
use App\Repositories\GreetingRepository;
use App\Repositories\LogRepository;
use App\Repositories\PasskeyRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TagRepository;
use App\Repositories\ThemeRepository;
use App\Repositories\UserRepository;
use App\Services\BackupService;
use App\Services\CsvExportService;
use App\Services\ContactImportService;
use App\Services\MailService;
use App\Services\MigrationService;
use App\Services\PasswordResetService;
use App\Services\ThemeService;
use App\Services\UpdateService;
use App\Services\UploadService;
use App\Services\WebAuthnService;
use App\Services\XlsxReader;

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    http_response_code(500);
    if ((bool) (config('app.debug', false))) {
        echo '<h1>PHP-Fehler</h1>';
        echo '<p><strong>' . htmlspecialchars($error['message'] ?? 'Unbekannter Fehler', ENT_QUOTES, 'UTF-8') . '</strong></p>';
        echo '<p>Datei: ' . htmlspecialchars($error['file'] ?? '-', ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>Zeile: ' . htmlspecialchars((string) ($error['line'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</p>';
    } else {
        echo '<h1>Serverfehler</h1><p>Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.</p>';
    }
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
    if ((bool) config('app.debug', false)) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    }
    Session::start();

    Container::factory(PDO::class, static fn () => Database::connect());
    Container::factory(UserRepository::class, static fn () => new UserRepository(Container::get(PDO::class)));
    Container::factory(CategoryRepository::class, static fn () => new CategoryRepository(Container::get(PDO::class)));
    Container::factory(TagRepository::class, static fn () => new TagRepository(Container::get(PDO::class)));
    Container::factory(ContactRepository::class, static fn () => new ContactRepository(Container::get(PDO::class)));
    Container::factory(LogRepository::class, static fn () => new LogRepository(Container::get(PDO::class)));
    Container::factory(SettingRepository::class, static fn () => new SettingRepository(Container::get(PDO::class)));
    Container::factory(RoleRepository::class, static fn () => new RoleRepository(Container::get(PDO::class)));
    Container::factory(ThemeRepository::class, static fn () => new ThemeRepository(Container::get(PDO::class)));
    Container::factory(ThemeService::class, static fn () => new ThemeService(
        Container::get(SettingRepository::class),
        Container::get(ThemeRepository::class)
    ));
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
    Container::factory(MigrationService::class, static fn () => new MigrationService(Container::get(PDO::class)));
    Container::factory(BackupService::class, static fn () => new BackupService(
        Container::get(PDO::class),
        Container::get(ContactRepository::class)
    ));
    Container::factory(UpdateService::class, static fn () => new UpdateService(
        Container::get(SettingRepository::class),
        Container::get(MigrationService::class),
        Container::get(BackupService::class)
    ));
    Container::factory(AdminController::class, static fn () => new AdminController(
        Container::get(Auth::class),
        Container::get(MigrationService::class),
        Container::get(UpdateService::class)
    ));
    Container::factory(BackupController::class, static fn () => new BackupController(
        Container::get(Auth::class),
        Container::get(BackupService::class)
    ));
    Container::factory(StartController::class, static fn () => new StartController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class)
    ));

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
        Container::get(PasskeyRepository::class),
        Container::get(EventRepository::class)
    ));
    Container::factory(PasskeyController::class, static fn () => new PasskeyController(
        Container::get(Auth::class),
        Container::get(PasskeyRepository::class),
        Container::get(WebAuthnService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(SetupController::class, static fn () => new SetupController(
        Container::get(Auth::class),
        Container::get(UserRepository::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(CategoryController::class, static fn () => new CategoryController(
        Container::get(Auth::class),
        Container::get(CategoryRepository::class)
    ));
    Container::factory(TagController::class, static fn () => new TagController(
        Container::get(Auth::class),
        Container::get(TagRepository::class)
    ));
    Container::factory(TaxonomyController::class, static fn () => new TaxonomyController(
        Container::get(Auth::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class)
    ));
    Container::factory(LogController::class, static fn () => new LogController(
        Container::get(Auth::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(LegalController::class, static fn () => new LegalController(
        Container::get(Auth::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(OrgaController::class, static fn () => new OrgaController(
        Container::get(Auth::class),
        Container::get(UserRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(\App\Repositories\RecipientListRepository::class, static fn () => new \App\Repositories\RecipientListRepository(Container::get(PDO::class)));
    Container::factory(EventRepository::class, static fn () => new EventRepository(Container::get(PDO::class)));
    Container::factory(GreetingRepository::class, static fn () => new GreetingRepository(Container::get(PDO::class)));
    Container::factory(GreetingController::class, static fn () => new GreetingController(
        Container::get(Auth::class),
        Container::get(GreetingRepository::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(EventController::class, static fn () => new EventController(
        Container::get(Auth::class),
        Container::get(EventRepository::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(MailController::class, static fn () => new MailController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(LogRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class),
        Container::get(UploadService::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class),
        Container::get(\App\Repositories\RecipientListRepository::class),
        Container::get(EventRepository::class)
    ));
    Container::factory(SettingsController::class, static fn () => new SettingsController(
        Container::get(Auth::class),
        Container::get(SettingRepository::class),
        Container::get(UploadService::class),
        Container::get(RoleRepository::class)
    ));
    Container::factory(\App\Controllers\RoleController::class, static fn () => new \App\Controllers\RoleController(
        Container::get(Auth::class),
        Container::get(RoleRepository::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(\App\Controllers\ThemeController::class, static fn () => new \App\Controllers\ThemeController(
        Container::get(Auth::class),
        Container::get(ThemeService::class),
        Container::get(ThemeRepository::class)
    ));
    Container::factory(SearchController::class, static fn () => new SearchController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(UserRepository::class)
    ));

    // Optional: offene Migrationen beim ersten Request nach einem Upload selbst
    // anwenden. Standard aus – im Normalfall macht das ein Admin bewusst über
    // "Verwaltung → Aktualisieren" (mit Backup).
    if ((bool) config('app.auto_migrate', false)) {
        $updateService = Container::get(UpdateService::class);
        if ($updateService->updatePending() && !$updateService->locked()) {
            $updateService->run(false);
        }
    }

    $router = new Router();
    $router->get('/', [StartController::class, 'index']);
    $router->get('/kontakte', [ContactController::class, 'index']);
    $router->get('/verwaltung', [AdminController::class, 'hub']);
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
    $router->get('/verwaltung/kategorien-tags', [TaxonomyController::class, 'index']);
    $router->post('/verwaltung/kategorien-tags/kategorie', [TaxonomyController::class, 'saveCategory']);
    $router->post('/verwaltung/kategorien-tags/kategorie/loeschen', [TaxonomyController::class, 'deleteCategory']);
    $router->post('/verwaltung/kategorien-tags/tag', [TaxonomyController::class, 'saveTag']);
    $router->post('/verwaltung/kategorien-tags/tag/loeschen', [TaxonomyController::class, 'deleteTag']);
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/account', [UserController::class, 'account']);
    $router->get('/orga-team', [OrgaController::class, 'form']);
    $router->post('/orga-team', [OrgaController::class, 'send']);
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

    $router->get('/rundmail', [MailController::class, 'rundmail']);
    $router->get('/rundmail/anzahl', [MailController::class, 'recipientCount']);
    $router->post('/rundmail/liste-speichern', [MailController::class, 'saveRecipientList']);
    $router->post('/rundmail/liste-umbenennen', [MailController::class, 'renameRecipientList']);
    $router->post('/rundmail/liste-loeschen', [MailController::class, 'deleteRecipientList']);
    $router->get('/vollstaendigkeit', [ContactController::class, 'completeness']);
    $router->post('/vollstaendigkeit/teilen', [ContactController::class, 'shareCompleteness']);
    $router->get('/namensliste', [ContactController::class, 'namenslisteMoved']);

    $router->get('/verwaltung/gruesse', [GreetingController::class, 'manage']);
    $router->post('/verwaltung/gruesse', [GreetingController::class, 'store']);
    $router->post('/verwaltung/gruesse/bearbeiten', [GreetingController::class, 'update']);
    $router->post('/verwaltung/gruesse/loeschen', [GreetingController::class, 'delete']);
    $router->get('/gruesse/weihnachten', [GreetingController::class, 'christmasForm']);
    $router->post('/gruesse/weihnachten/vorschau', [GreetingController::class, 'christmasPreview']);
    $router->post('/mail/gruesse-senden', [MailController::class, 'sendGreetings']);

    $router->get('/termine', [EventController::class, 'index']);
    $router->get('/termine/neu', [EventController::class, 'createForm']);
    $router->post('/termine', [EventController::class, 'store']);
    $router->get('/termine/detail', [EventController::class, 'detail']);
    $router->post('/termine/speichern', [EventController::class, 'updateDetails']);
    $router->post('/termine/teilnehmer', [EventController::class, 'updateParticipants']);
    $router->post('/termine/ergebnis', [EventController::class, 'decide']);
    $router->post('/termine/status', [EventController::class, 'setStatus']);
    $router->post('/termine/loeschen', [EventController::class, 'delete']);
    $router->post('/termine/nachricht', [EventController::class, 'messageParticipants']);
    $router->get('/abstimmen', [EventController::class, 'vote']);
    $router->post('/abstimmen', [EventController::class, 'submitVote']);
    $router->post('/mail/compose', [MailController::class, 'compose']);
    $router->get('/mail/compose', [MailController::class, 'compose']);
    $router->post('/mail/compose-all', [MailController::class, 'composeAll']);
    $router->post('/mail/test', [MailController::class, 'test']);
    $router->post('/mail/start', [MailController::class, 'start']);
    $router->get('/mail/status', [MailController::class, 'status']);
    $router->post('/mail/batch', [MailController::class, 'batch']);

    $router->get('/admin/aktualisieren', [AdminController::class, 'update']);
    $router->post('/admin/aktualisieren', [AdminController::class, 'runUpdate']);
    $router->get('/admin/migrations', [AdminController::class, 'migrations']);
    $router->post('/admin/migrations/apply', [AdminController::class, 'applyMigration']);
    $router->get('/admin/backup', [BackupController::class, 'index']);
    $router->post('/admin/backup/export', [BackupController::class, 'export']);
    $router->post('/admin/backup/restore', [BackupController::class, 'restore']);
    $router->get('/admin/legal/impressum', [LegalController::class, 'editImpressum']);
    $router->post('/admin/legal/impressum', [LegalController::class, 'updateImpressum']);
    $router->get('/admin/legal/datenschutz', [LegalController::class, 'editDatenschutz']);
    $router->post('/admin/legal/datenschutz', [LegalController::class, 'updateDatenschutz']);
    $router->get('/logs/audit', [LogController::class, 'audit']);
    $router->get('/logs/mail', [LogController::class, 'mail']);
    $router->get('/settings/branding', [SettingsController::class, 'branding']);
    $router->post('/settings/branding', [SettingsController::class, 'updateBranding']);
    $router->get('/settings/themes', [\App\Controllers\ThemeController::class, 'index']);
    $router->post('/settings/themes/aktivieren', [\App\Controllers\ThemeController::class, 'activate']);
    $router->post('/settings/themes/duplizieren', [\App\Controllers\ThemeController::class, 'duplicate']);
    $router->post('/settings/themes/umbenennen', [\App\Controllers\ThemeController::class, 'rename']);
    $router->get('/settings/themes/bearbeiten', [\App\Controllers\ThemeController::class, 'edit']);
    $router->post('/settings/themes/speichern', [\App\Controllers\ThemeController::class, 'save']);
    $router->post('/settings/themes/loeschen', [\App\Controllers\ThemeController::class, 'delete']);
    $router->get('/settings/mail-footer', [SettingsController::class, 'mailFooter']);
    $router->post('/settings/mail-footer', [SettingsController::class, 'updateMailFooter']);
    $router->get('/settings/visibility', [SettingsController::class, 'visibility']);
    $router->post('/settings/visibility', [SettingsController::class, 'updateVisibility']);
    $router->get('/settings/permissions', [SettingsController::class, 'permissions']);
    $router->post('/settings/permissions', [SettingsController::class, 'updatePermissions']);
    $router->get('/settings/roles', [\App\Controllers\RoleController::class, 'index']);
    $router->post('/settings/roles/store', [\App\Controllers\RoleController::class, 'store']);
    $router->post('/settings/roles/update', [\App\Controllers\RoleController::class, 'update']);
    $router->post('/settings/roles/delete', [\App\Controllers\RoleController::class, 'delete']);

    $router->dispatch(new Request());
} catch (Throwable $exception) {
    $detail = (bool) config('app.debug', false)
        ? $exception->getMessage()
        : 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.';
    render_error_page(500, 'Serverfehler', $detail);
}
