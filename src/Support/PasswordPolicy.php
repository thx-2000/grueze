<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zentrale Passwort-Regeln für Account-Anlage, Passwort-Reset, Passwort
 * ändern und Registrierung – bisher an fünf Stellen einzeln geprüft (nur
 * Länge). Bewusst KEINE Zeichenklassen-Pflicht (TH-Entscheidung 2026-09):
 * die nervt oft mehr, als sie bringt – eine lange Passphrase ist ohnehin
 * sicherer als ein kurzes Passwort mit Sonderzeichen. Stattdessen zusätzlich
 * zur Länge eine kleine Blockliste häufig geratener Wörter/Muster.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Wörter/Silben, die (klein geschrieben) im Passwort nicht vorkommen
     * dürfen – als Teilstring, damit auch aufgeblähte Varianten wie
     * „MeinPasswort2026!" abgefangen werden.
     */
    private const WEAK_SUBSTRINGS = [
        'passwort', 'password', 'kennwort', 'geheim', 'willkommen', 'welcome',
        'letmein', 'dragon', 'monkey', 'football', 'baseball', 'sonnenschein',
        'qwertz', 'qwerty', 'asdfgh', 'zxcvbn', 'azerty',
        '123456', '234567', '345678', '456789', '098765', '987654',
        'abcdefg', 'hijklmn', 'nopqrst',
    ];

    /** @return string|null Fehlertext für die Anzeige, oder null wenn das Passwort ok ist. */
    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Das Passwort muss mindestens ' . self::MIN_LENGTH . ' Zeichen lang sein.';
        }
        if (self::isTooWeak($password)) {
            return 'Dieses Passwort ist zu leicht zu erraten. Bitte etwas Individuelleres wählen – '
                . 'z. B. eine kurze, für dich einprägsame Wortfolge.';
        }

        return null;
    }

    private static function isTooWeak(string $password): bool
    {
        $lower = mb_strtolower($password);
        foreach (self::WEAK_SUBSTRINGS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return self::isMostlyOneCharacter($password) || self::isSequential($password);
    }

    /** „aaaaaaaaaaaa", „abababababab" & Ähnliches. */
    private static function isMostlyOneCharacter(string $password): bool
    {
        return count(array_unique(mb_str_split($password))) <= 2;
    }

    /** Durchgehend auf-/absteigende Zeichenfolge, z. B. „abcdefghijkl" oder „987654321098". */
    private static function isSequential(string $password): bool
    {
        $chars = mb_str_split($password);
        $ascending = true;
        $descending = true;
        for ($i = 1; $i < count($chars); $i++) {
            $diff = mb_ord($chars[$i]) - mb_ord($chars[$i - 1]);
            if ($diff !== 1) {
                $ascending = false;
            }
            if ($diff !== -1) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }
}
