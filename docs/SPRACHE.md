# Sichtbare Texte – Stil & inklusive Ansprache

Gilt für alle für Nutzende sichtbaren deutschen Texte (Templates, Flash-
Meldungen, Fehlermeldungen, Mail-Vorlagen). Code-Kommentare und interne
Bezeichner sind hier nicht gemeint.

## Grundhaltung

- **Du-Ansprache**, aktiv, knapp. Eine Aktion sagt, was passiert
  („Speichern", danach „Gespeichert").
- **Fehlermeldungen** erklären, was schiefging und wie es weitergeht – keine
  Entschuldigung, kein Fachjargon.

## Inklusive Formen – Rangfolge

1. **Neutralformulierung bevorzugen.** Ein sauberes geschlechtsneutrales
   Wort ist immer die beste Wahl:
   - „Teilnehmer" → **„Teilnehmende"**
   - „Benutzer" (Login-Konto) → **„Zugang" / „Konto"** (die App nutzt
     durchgehend „Zugang/Zugänge")
   - „jede:r stimmt ab" → **„abgestimmt wird" / „alle stimmen ab"**
   - „Verantwortliche:r" → Rolle ausschreiben: „weil du die Gruppe leitest"
   - Analog: „Studierende" statt „Student:innen", „Mitwirkende",
     „die Leitung", „wer teilnimmt".
2. **Doppelpunkt**, wenn keine gute Neutralform existiert: „Nutzer:innen".
   Nur als Ausweichlösung.
3. **Kein Gendersternchen.** `*` wird in diesem Projekt nicht verwendet.

## Eine bekannte Person ansprechen

Beim direkten Anschreiben einer namentlich bekannten Person braucht es keine
Gender-Markierung: **„Hallo Maria"** ist vollständig.

Die Mail-Anrede `{Anrede}` richtet sich nach dem Kontaktfeld **„Anrede"**:

| Feldwert | Anrede |
|---|---|
| leer (Standard) | „Hallo" |
| „Liebe …" (intern `w`) | „Liebe" |
| „Lieber …" (intern `m`) | „Lieber" |

Die Spalte heißt `contacts.anrede` (seit v1.34.x; vorher `geschlecht`). Sie
meint die Briefanrede, nicht das Geschlecht. Die Codes `m`/`w`/leer sind rein
intern und steuern nur „Lieber"/„Liebe"/„Hallo".

## Etablierte Fachbegriffe, die bleiben

„Absender" / „Absenderadresse" (Formfeld), „Benutzername" (SMTP-/IMAP-Login),
„Admin", „Account". Diese sind als technische Begriffe fest und werden nicht
umformuliert.
