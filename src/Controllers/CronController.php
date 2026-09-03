<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\EventScheduler;
use App\Services\GreetingScheduler;

/**
 * Einstiegspunkt für zeitgesteuerte Aufgaben (Abstimmungs-Automatik).
 * Aufruf per URL mit geheimem Schlüssel:
 *
 *     curl -s "https://…/intern/cron?key=SCHLUESSEL"
 *
 * Der Schlüssel steht in `config/config.php` unter `app.cron_key`. Ohne
 * gesetzten Schlüssel oder bei falschem Schlüssel antwortet die Route mit 404,
 * damit die Existenz des Endpunkts nicht verrät.
 */
final class CronController
{
    public function __construct(
        private EventScheduler $scheduler,
        private GreetingScheduler $greetings,
    ) {
    }

    public function run(Request $request): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');

        $expected = trim((string) config('app.cron_key', ''));
        $given = trim((string) $request->input('key', ''));

        if ($expected === '' || strlen($given) < 8 || !hash_equals($expected, $given)) {
            http_response_code(404);
            echo "Nicht gefunden.\n";

            return;
        }

        try {
            $stats = $this->scheduler->run();
            foreach ($this->greetings->run() as $key => $value) {
                $stats['greet_' . $key] = $value;
            }
            echo "ok\n";
            foreach ($stats as $key => $value) {
                echo $key . '=' . (int) $value . "\n";
            }
        } catch (\Throwable $exception) {
            http_response_code(500);
            echo "Fehler bei der Ausführung.\n";
            if ((bool) config('app.debug', false)) {
                echo $exception->getMessage() . "\n";
            }
        }
    }
}
