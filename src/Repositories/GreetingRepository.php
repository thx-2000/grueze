<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Grüße-Pool: Standardtexte für Geburtstag und Weihnachten. Beim Versand wird
 * je Empfänger zufällig einer gezogen (Bag-Verfahren – erst wenn der Vorrat
 * leer ist, wird neu gemischt), damit die Texte gut streuen.
 */
final class GreetingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT * FROM greetings ORDER BY occasion ASC, sort_order ASC, id ASC'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function byOccasion(string $occasion): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM greetings WHERE occasion = :o ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['o' => $occasion]);

        return $stmt->fetchAll();
    }

    /** @return list<string> aktive Texte einer Anlass-Gruppe */
    public function activeTexts(string $occasion): array
    {
        $stmt = $this->pdo->prepare('SELECT text FROM greetings WHERE occasion = :o AND is_active = 1 ORDER BY id ASC');
        $stmt->execute(['o' => $occasion]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM greetings WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(string $occasion, string $text): void
    {
        $next = (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM greetings')->fetchColumn();
        $stmt = $this->pdo->prepare('INSERT INTO greetings (occasion, text, sort_order) VALUES (:o, :t, :s)');
        $stmt->execute(['o' => $occasion, 't' => $text, 's' => $next]);
    }

    public function update(int $id, string $text, bool $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE greetings SET text = :t, is_active = :a WHERE id = :id');
        $stmt->execute(['t' => $text, 'a' => $isActive ? 1 : 0, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM greetings WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Ordnet jedem Schlüssel einen zufälligen aktiven Text zu (Bag-Verfahren).
     *
     * @param list<int|string> $keys
     * @return array<int|string, string>
     */
    public function assign(array $keys, string $occasion): array
    {
        $texts = $this->activeTexts($occasion);
        if ($texts === []) {
            return [];
        }

        $out = [];
        $bag = [];
        foreach ($keys as $key) {
            if ($bag === []) {
                $bag = $texts;
                shuffle($bag);
            }
            $out[$key] = array_pop($bag);
        }

        return $out;
    }
}
