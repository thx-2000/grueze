<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Leert personenbezogene Feldwerte aus Kontaktlisten, die die aktuelle Rolle
 * nicht sehen darf – bevor die Daten an eine View gehen. Zeilen bleiben
 * erhalten (Status „Mail/Tel. fehlt" braucht die Anzahl), nur die Werte
 * werden geleert. Der eigene verknüpfte Kontakt bleibt unberührt (die Seite
 * zeigt ihn separat, Notizen ausgenommen).
 *
 * Gemeinsam genutzt von der Kontaktliste und dem Dubletten-Finder.
 */
final class ContactFieldRedactor
{
    /**
     * @param array<int,array<string,mixed>> $contacts
     */
    public static function apply(array &$contacts, int $ownContactId): void
    {
        $globallyVisible = [
            'address'  => can_view_contact_field('address'),
            'birthday' => can_view_contact_field('birthday'),
            'emails'   => can_view_contact_field('emails'),
            'phones'   => can_view_contact_field('phones'),
            'login'    => can_view_contact_field('login'),
            'notes'    => can_view_contact_field('notes'),
        ];
        // Früher Ausstieg nur, wenn die Rolle schon global alles sieht UND
        // die Per-Kontakt-Einschränkung „nur Orga-Team" für sie ohnehin nie
        // greift (siehe Auth::canViewContactField – die gilt nie für
        // `contacts.manage`). Sonst muss jeder Kontakt einzeln geprüft
        // werden, da diese Einschränkung pro Person gilt.
        if (!in_array(false, $globallyVisible, true) && can('contacts.manage')) {
            return;
        }

        foreach ($contacts as &$contact) {
            if ((int) ($contact['id'] ?? 0) === $ownContactId && $ownContactId > 0) {
                continue;
            }
            $show = [
                'address'  => can_view_contact_field('address', $contact),
                'birthday' => can_view_contact_field('birthday', $contact),
                'emails'   => can_view_contact_field('emails', $contact),
                'phones'   => can_view_contact_field('phones', $contact),
                'login'    => can_view_contact_field('login', $contact),
                'notes'    => can_view_contact_field('notes', $contact),
            ];
            if (!$show['emails']) {
                foreach (($contact['emails'] ?? []) as $i => $_) {
                    $contact['emails'][$i] = ['email' => '', 'label' => ''];
                }
            }
            if (!$show['phones']) {
                foreach (($contact['phones'] ?? []) as $i => $_) {
                    $contact['phones'][$i] = ['phone' => '', 'label' => ''];
                }
            }
            if (!$show['address']) {
                $contact['strasse'] = $contact['plz'] = $contact['ort'] = $contact['land'] = '';
            }
            if (!$show['birthday']) {
                $contact['geburtstag'] = null;
                $contact['geburtstag_jahr_unbekannt'] = false;
            }
            if (!$show['notes']) {
                $contact['notizen'] = '';
            }
            if (!$show['login']) {
                $contact['linked_user'] = null;
            }
        }
        unset($contact);
    }

    /**
     * Wie apply(), aber über die Cluster-Struktur des Dubletten-Finders.
     *
     * @param array<int,array{reason?:string,contacts:array<int,array<string,mixed>>}> $clusters
     */
    public static function applyToClusters(array &$clusters, int $ownContactId): void
    {
        foreach ($clusters as &$cluster) {
            self::apply($cluster['contacts'], $ownContactId);
        }
        unset($cluster);
    }
}
