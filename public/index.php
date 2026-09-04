<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\CategoryController;
use App\Controllers\ContactController;
use App\Controllers\CronController;
use App\Controllers\EventController;
use App\Controllers\GreetingController;
use App\Controllers\GroupController;
use App\Controllers\HelpController;
use App\Controllers\GroupPollController;
use App\Controllers\LegalController;
use App\Controllers\LogController;
use App\Controllers\MailController;
use App\Controllers\OrgaController;
use App\Controllers\PasskeyController;
use App\Controllers\RegistrationController;
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
use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Repositories\PasskeyRepository;
use App\Repositories\RegistrationInviteRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TagRepository;
use App\Repositories\ThemeRepository;
use App\Repositories\UserRepository;
use App\Services\BackupService;
use App\Services\CsvExportService;
use App\Services\ContactImportService;
use App\Services\EventScheduler;
use App\Services\GreetingScheduler;
use App\Services\GroupMailService;
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
    send_security_headers();

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
    Container::factory(\App\Services\VCardService::class, static fn () => new \App\Services\VCardService());
    Container::factory(\App\Services\LinkedAccountService::class, static fn () => new \App\Services\LinkedAccountService(
        Container::get(UserRepository::class)
    ));
    Container::factory(\App\Controllers\PwaController::class, static fn () => new \App\Controllers\PwaController());
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
        Container::get(SettingRepository::class),
        Container::get(LogRepository::class)
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
        Container::get(ContactRepository::class),
        Container::get(EventRepository::class),
        Container::get(GroupRepository::class)
    ));

    Container::factory(AuthController::class, static fn () => new AuthController(
        Container::get(Auth::class),
        Container::get(LogRepository::class),
        Container::get(PasswordResetService::class),
        Container::get(PasskeyRepository::class)
    ));
    Container::factory(\App\Repositories\DataCheckRepository::class, static fn () => new \App\Repositories\DataCheckRepository(Container::get(PDO::class)));
    Container::factory(ContactController::class, static fn () => new ContactController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class),
        Container::get(UserRepository::class),
        Container::get(LogRepository::class),
        Container::get(UploadService::class),
        Container::get(GroupRepository::class),
        Container::get(\App\Repositories\DataCheckRepository::class),
        Container::get(\App\Services\LinkedAccountService::class)
    ));
    Container::factory(\App\Controllers\ContactArchiveController::class, static fn () => new \App\Controllers\ContactArchiveController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(UserRepository::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(\App\Controllers\ContactPortController::class, static fn () => new \App\Controllers\ContactPortController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(ContactImportService::class),
        Container::get(CsvExportService::class),
        Container::get(\App\Services\VCardService::class)
    ));
    Container::factory(\App\Controllers\CompletenessController::class, static fn () => new \App\Controllers\CompletenessController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class)
    ));
    Container::factory(\App\Controllers\DataCheckController::class, static fn () => new \App\Controllers\DataCheckController(
        Container::get(Auth::class),
        Container::get(ContactRepository::class),
        Container::get(\App\Repositories\DataCheckRepository::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(UserController::class, static fn () => new UserController(
        Container::get(Auth::class),
        Container::get(UserRepository::class),
        Container::get(LogRepository::class),
        Container::get(PasswordResetService::class),
        Container::get(PasskeyRepository::class),
        Container::get(EventRepository::class),
        Container::get(ContactRepository::class)
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
        Container::get(TagRepository::class),
        Container::get(GroupRepository::class)
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
    Container::factory(\App\Services\MailRecipientResolver::class, static fn () => new \App\Services\MailRecipientResolver(
        Container::get(ContactRepository::class),
        Container::get(\App\Repositories\RecipientListRepository::class)
    ));
    Container::factory(\App\Services\MailComposer::class, static fn () => new \App\Services\MailComposer(
        Container::get(Auth::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(EventRepository::class, static fn () => new EventRepository(Container::get(PDO::class)));
    Container::factory(GreetingRepository::class, static fn () => new GreetingRepository(Container::get(PDO::class)));
    Container::factory(RegistrationInviteRepository::class, static fn () => new RegistrationInviteRepository(Container::get(PDO::class)));
    Container::factory(RegistrationController::class, static fn () => new RegistrationController(
        Container::get(Auth::class),
        Container::get(RegistrationInviteRepository::class),
        Container::get(UserRepository::class),
        Container::get(ContactRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class)
    ));
    Container::factory(GreetingController::class, static fn () => new GreetingController(
        Container::get(Auth::class),
        Container::get(GreetingRepository::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(TagRepository::class),
        Container::get(SettingRepository::class)
    ));
    Container::factory(\App\Services\IcalService::class, static fn () => new \App\Services\IcalService());
    Container::factory(EventController::class, static fn () => new EventController(
        Container::get(Auth::class),
        Container::get(EventRepository::class),
        Container::get(ContactRepository::class),
        Container::get(CategoryRepository::class),
        Container::get(LogRepository::class),
        Container::get(\App\Services\IcalService::class)
    ));
    Container::factory(EventScheduler::class, static fn () => new EventScheduler(
        Container::get(EventRepository::class),
        Container::get(UserRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(GreetingScheduler::class, static fn () => new GreetingScheduler(
        Container::get(ContactRepository::class),
        Container::get(GreetingRepository::class),
        Container::get(SettingRepository::class),
        Container::get(UserRepository::class),
        Container::get(MailService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(CronController::class, static fn () => new CronController(
        Container::get(EventScheduler::class),
        Container::get(GreetingScheduler::class),
        Container::get(ContactRepository::class)
    ));
    Container::factory(GroupRepository::class, static fn () => new GroupRepository(Container::get(PDO::class)));
    Container::factory(GroupMailService::class, static fn () => new GroupMailService(
        Container::get(GroupRepository::class),
        Container::get(UserRepository::class),
        Container::get(SettingRepository::class),
        Container::get(MailService::class),
        Container::get(LogRepository::class)
    ));
    Container::factory(GroupController::class, static fn () => new GroupController(
        Container::get(Auth::class),
        Container::get(GroupRepository::class),
        Container::get(ContactRepository::class),
        Container::get(GroupMailService::class)
    ));
    Container::factory(HelpController::class, static fn () => new HelpController(Container::get(Auth::class)));
    Container::factory(GroupPollController::class, static fn () => new GroupPollController(
        Container::get(Auth::class),
        Container::get(EventRepository::class),
        Container::get(GroupRepository::class)
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
        Container::get(EventRepository::class),
        Container::get(\App\Services\MailRecipientResolver::class),
        Container::get(\App\Services\MailComposer::class)
    ));
    Container::factory(\App\Controllers\RecipientListController::class, static fn () => new \App\Controllers\RecipientListController(
        Container::get(Auth::class),
        Container::get(\App\Repositories\RecipientListRepository::class),
        Container::get(\App\Services\MailRecipientResolver::class)
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

    // Leichtgewichtige Aufbewahrungs-Bereinigung (ohne Cron): mit kleiner
    // Wahrscheinlichkeit je Request alte Protokoll-/Token-Daten löschen.
    if (random_int(1, 100) === 1) {
        try {
            $logs = Container::get(LogRepository::class);
            $logs->pruneLoginAttempts((int) config('security.login_attempts_retention_days', 30));
            $logs->pruneExpiredPasswordResets();
            Container::get(EventRepository::class)->pruneTokenHits(
                (int) config('security.token_hit_retention_days', 120)
            );
            Container::get(ContactRepository::class)->pruneTrashedContacts();
            Container::get(\App\Repositories\DataCheckRepository::class)->purgeExpired();
            Container::get(SettingRepository::class)->reencryptSecrets();
        } catch (\Throwable) {
            // Aufräumen ist unkritisch – Fehler nie an den Request weiterreichen.
        }
    }

    // Rückfallebene ohne echten Cron: selten je Request die Abstimmungs-Automatik
    // (Fristen schließen, Erinnerungen, Ergebnis-Mails) anstoßen – aber höchstens
    // einmal pro Stunde. Zuverlässiger ist ein echter Aufruf von /intern/cron.
    if (random_int(1, 20) === 1) {
        try {
            $schedulerSettings = Container::get(SettingRepository::class);
            if (time() - (int) $schedulerSettings->get('scheduler_last_run', '0') > 3600) {
                Container::get(EventScheduler::class)->run();
                Container::get(GreetingScheduler::class)->run();
            }
        } catch (\Throwable) {
            // Automatik darf den laufenden Request nie stören.
        }
    }

    $router = new Router();
    $router->get('/', [StartController::class, 'index']);
    $router->get('/manifest.webmanifest', [\App\Controllers\PwaController::class, 'manifest']);
    $router->get('/app-icon.svg', [\App\Controllers\PwaController::class, 'icon']);
    $router->get('/kontakte', [ContactController::class, 'index']);
    $router->get('/verwaltung', [AdminController::class, 'hub']);
    $router->get('/search', [SearchController::class, 'index']);
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
    $router->post('/forgot-password', [AuthController::class, 'sendReset']);
    $router->get('/passwort-neu/{token}', [AuthController::class, 'showResetPassword']);
    $router->post('/passwort-neu', [AuthController::class, 'resetPassword']);
    $router->get('/reset-password', [AuthController::class, 'showResetPassword']); // Alt-Links umleiten
    $router->get('/registrieren', [RegistrationController::class, 'form']);
    $router->post('/registrieren', [RegistrationController::class, 'submit']);
    $router->get('/verwaltung/registrierung', [RegistrationController::class, 'settingsForm']);
    $router->post('/verwaltung/registrierung', [RegistrationController::class, 'updateSettings']);
    $router->post('/verwaltung/einladung', [RegistrationController::class, 'createInvite']);
    $router->post('/verwaltung/einladung/zuruecknehmen', [RegistrationController::class, 'revokeInvite']);
    $router->post('/verwaltung/einladung/freigeben', [RegistrationController::class, 'approveRequest']);
    $router->post('/verwaltung/einladung/ablehnen', [RegistrationController::class, 'rejectRequest']);
    $router->get('/impressum', [LegalController::class, 'impressum']);
    $router->get('/datenschutz', [LegalController::class, 'datenschutz']);
    $router->get('/setup/admin', [SetupController::class, 'showAdminForm']);
    $router->post('/setup/admin', [SetupController::class, 'storeAdmin']);

    $router->get('/contacts/create', [ContactController::class, 'create']);
    $router->get('/contacts/import', [\App\Controllers\ContactPortController::class, 'importForm']);
    $router->post('/contacts/import', [\App\Controllers\ContactPortController::class, 'importXlsx']);
    $router->post('/contacts/store', [ContactController::class, 'store']);
    $router->get('/contacts/edit', [ContactController::class, 'edit']);
    $router->post('/contacts/update', [ContactController::class, 'update']);
    $router->post('/contacts/delete', [\App\Controllers\ContactArchiveController::class, 'retire']);
    $router->get('/kontakte/archiv', [\App\Controllers\ContactArchiveController::class, 'retiredList']);
    $router->post('/contacts/wiederherstellen', [\App\Controllers\ContactArchiveController::class, 'restore']);
    $router->post('/contacts/endgueltig-loeschen', [\App\Controllers\ContactArchiveController::class, 'purge']);
    $router->post('/contacts/datencheck', [\App\Controllers\DataCheckController::class, 'createLink']);
    $router->post('/contacts/datencheck/widerrufen', [\App\Controllers\DataCheckController::class, 'revokeLink']);
    $router->get('/kontakte/dubletten', [\App\Controllers\ContactArchiveController::class, 'duplicates']);
    $router->post('/contacts/zusammenfuehren', [\App\Controllers\ContactArchiveController::class, 'merge']);
    $router->post('/contacts/bulk-update', [ContactController::class, 'bulkUpdate']);
    $router->post('/contacts/gruppe-aus-auswahl', [ContactController::class, 'groupFromSelection']);
    $router->get('/contacts/export', [\App\Controllers\ContactPortController::class, 'export']);
    $router->get('/contacts/vcard', [\App\Controllers\ContactPortController::class, 'vcard']);
    $router->post('/contacts/vcard', [\App\Controllers\ContactPortController::class, 'vcard']);

    $router->post('/categories/store', [CategoryController::class, 'store']);
    $router->post('/tags/store', [TagController::class, 'store']);
    $router->get('/verwaltung/kategorien-tags', [TaxonomyController::class, 'index']);
    $router->post('/verwaltung/kategorien-tags/kategorie', [TaxonomyController::class, 'saveCategory']);
    $router->post('/verwaltung/kategorien-tags/kategorie/loeschen', [TaxonomyController::class, 'deleteCategory']);
    $router->post('/verwaltung/kategorien-tags/tag', [TaxonomyController::class, 'saveTag']);
    $router->post('/verwaltung/kategorien-tags/tag/loeschen', [TaxonomyController::class, 'deleteTag']);
    $router->post('/verwaltung/kategorien-tags/tag/als-gruppe', [TaxonomyController::class, 'tagToGroup']);
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/account', [UserController::class, 'account']);
    $router->post('/mein-eintrag', [ContactController::class, 'updateOwnProfile']);
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
    $router->post('/rundmail/liste-speichern', [\App\Controllers\RecipientListController::class, 'save']);
    $router->post('/rundmail/liste-umbenennen', [\App\Controllers\RecipientListController::class, 'rename']);
    $router->post('/rundmail/liste-loeschen', [\App\Controllers\RecipientListController::class, 'delete']);
    $router->get('/vollstaendigkeit', [\App\Controllers\CompletenessController::class, 'index']);
    $router->post('/vollstaendigkeit/teilen', [\App\Controllers\CompletenessController::class, 'share']);

    $router->get('/verwaltung/gruesse', [GreetingController::class, 'manage']);
    $router->post('/verwaltung/gruesse', [GreetingController::class, 'store']);
    $router->post('/verwaltung/gruesse/automatik', [GreetingController::class, 'saveAutoBirthday']);
    $router->post('/verwaltung/gruesse/bearbeiten', [GreetingController::class, 'update']);
    $router->post('/verwaltung/gruesse/loeschen', [GreetingController::class, 'delete']);
    $router->get('/gruesse/weihnachten', [GreetingController::class, 'christmasForm']);
    $router->post('/gruesse/weihnachten/vorschau', [GreetingController::class, 'christmasPreview']);
    $router->get('/gruesse/geburtstage', [GreetingController::class, 'birthdayForm']);
    $router->post('/gruesse/geburtstage/vorschau', [GreetingController::class, 'birthdayPreview']);
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
    $router->post('/termine/frist', [EventController::class, 'extendDeadline']);
    $router->get('/termine/termin.ics', [EventController::class, 'ical']);
    $router->get('/abstimmen', [EventController::class, 'vote']);
    $router->post('/abstimmen', [EventController::class, 'submitVote']);
    $router->get('/meine-daten', [\App\Controllers\DataCheckController::class, 'show']);
    $router->post('/meine-daten', [\App\Controllers\DataCheckController::class, 'save']);
    $router->get('/intern/cron', [CronController::class, 'run']);
    $router->post('/intern/cron', [CronController::class, 'run']);

    $router->get('/hilfe/cron', [HelpController::class, 'cron']);
    $router->get('/hilfe/cron.pdf', [HelpController::class, 'cronPdf']);

    $router->get('/gruppen', [GroupController::class, 'mine']);
    $router->post('/gruppen/beitreten', [GroupController::class, 'join']);
    $router->post('/gruppen/verlassen', [GroupController::class, 'leave']);
    $router->post('/gruppen/beitritt-anfragen', [GroupController::class, 'requestJoin']);
    $router->post('/gruppen/beitritt-zuruecknehmen', [GroupController::class, 'withdrawJoin']);
    $router->post('/verwaltung/gruppen/anfrage/annehmen', [GroupController::class, 'approveJoin']);
    $router->post('/verwaltung/gruppen/anfrage/ablehnen', [GroupController::class, 'rejectJoin']);
    $router->get('/gruppen/nachricht', [GroupController::class, 'composeMail']);
    $router->post('/gruppen/nachricht', [GroupController::class, 'sendMail']);
    $router->get('/gruppen/abstimmungen', [GroupPollController::class, 'list']);
    $router->get('/gruppen/abstimmung', [GroupPollController::class, 'show']);
    $router->get('/gruppen/abstimmung/neu', [GroupPollController::class, 'createForm']);
    $router->post('/gruppen/abstimmung', [GroupPollController::class, 'store']);
    $router->post('/gruppen/abstimmung/stimme', [GroupPollController::class, 'vote']);
    $router->post('/gruppen/abstimmung/schliessen', [GroupPollController::class, 'close']);
    $router->post('/gruppen/abstimmung/festlegen', [GroupPollController::class, 'decide']);
    $router->post('/verwaltung/gruppen/sperre', [GroupController::class, 'toggleMailLock']);
    $router->get('/verwaltung/gruppen', [GroupController::class, 'manage']);
    $router->get('/verwaltung/gruppen/detail', [GroupController::class, 'detail']);
    $router->post('/verwaltung/gruppen', [GroupController::class, 'store']);
    $router->post('/verwaltung/gruppen/speichern', [GroupController::class, 'updateGroup']);
    $router->post('/verwaltung/gruppen/mitglieder', [GroupController::class, 'updateMembers']);
    $router->post('/verwaltung/gruppen/leitung', [GroupController::class, 'setMemberRole']);
    $router->post('/verwaltung/gruppen/loeschen', [GroupController::class, 'deleteGroup']);
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
    $router->post('/settings/roles/schluessel', [\App\Controllers\RoleController::class, 'renameSlug']);
    $router->post('/settings/roles/delete', [\App\Controllers\RoleController::class, 'delete']);

    $router->dispatch(new Request());
} catch (Throwable $exception) {
    $detail = (bool) config('app.debug', false)
        ? $exception->getMessage()
        : 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.';
    render_error_page(500, 'Serverfehler', $detail);
}
