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

Der interne Spaltenname `contacts.geschlecht` und die Codes `m`/`w` sind
Altbestand – im UI heißt das Feld nur „Anrede" und meint die Briefanrede,
nicht das Geschlecht. Ein sauberes Feld `anrede` ist im `TODO.md` als
Aufräumaufgabe notiert.

## Etablierte Fachbegriffe, die bleiben

„Absender" / „Absenderadresse" (Formfeld), „Benutzername" (SMTP-/IMAP-Login),
„Admin", „Account". Diese sind als technische Begriffe fest und werden nicht
umformuliert.
