# UX-/IA-Review

**Stand:** GRUEZE v1.20.0 · **Datum:** 2026-09-04 · **Zweck:** Analyse als
Entscheidungsgrundlage – noch kein Umbau. Fortsetzung des Punkts
„Design-/UX-Überarbeitung" aus `TODO.md` und von `docs/REDESIGN.md`.

Beim letzten Redesign (v0.23–0.30) wurde eine bewusste Informationsarchitektur
gebaut: **Mein Eintrag · Adressbuch · Nachrichten · Termine · Verwaltung**.
Seither sind ~10 Features dazugekommen (Gruppen, Hilfe-Seiten, Archiv/Papierkorb,
Dubletten-Finder, Daten-Check-Link, Start-Widgets, PWA) und dort angedockt
worden, wo sie gerade passten. Dieses Review prüft, ob die Struktur das noch
trägt – pro Rolle.

---

## 1. Rollen

| Rolle | Kernrechte | Reale Nutzung (aus dem UX-Gespräch 2026-09-01) |
|---|---|---|
| **Admin** | alles (`Auth::isAdmin()` immer true) | Hauptnutzer. Am Schreibtisch, verwaltet alles. |
| **Orga / „Team"** | `contacts.manage`, `mail.send`, `events.manage`, `groups.manage`, `settings.manage` | Zweithäufigste. Wie Admin, ohne Benutzer-/Rollenverwaltung. |
| **Gruppenleitung** | wie Mitglied + Leitung einzelner Gruppen (`isLead`) | Bisher eher Konzept – zu klären, ob real aktiv. |
| **Mitglied / „Stufenmitglied"** | `mail.contact_single`, eigene Daten pflegen | Handy, sehr einfach: eigene Daten, andere finden, abstimmen. |
| **Betrachter** | Kontakte ansehen, E-Mails kopieren | Sehr eingeschränkt – siehe Befund B/C. |

Faustregel aus dem Gespräch: **zwei Modi.** Admin/Orga = Power-Tool am
Schreibtisch. Alle anderen = Handy, minimal.

---

## 2. Informationsarchitektur heute

**Seitenleiste (`templates/layout/app.php`), von oben:**

```
[ Mein Eintrag ]        ← immer, ganz oben, mit Avatar
  Start                 ← immer
  Adressbuch            ← immer
  Nachrichten           ← nur mit mail.send
  Termine               ← nur mit events.manage
  Gruppen               ← wenn in ≥1 Gruppe ODER eine Gruppe existiert
  ── Verwaltung ──
  Einstellungen         ← wenn users.manage | settings.manage | audit.view | mail.view_log
  Hilfe & Anleitungen   ← immer (neuer Tab)
  ─────
  Orga-Team schreiben   ← Footer, immer
  Impressum · Datenschutz
  „läuft mit GRUEZE v1.20.0"
```

**Topbar:** Menü-Umschalter · globale Suche · Blickschutz-Umschalter ·
(im Auswählen-Modus) „Mail an Auswahl" / „Auswahl aufheben".

**Was jede Rolle in der Rail sieht:**

| | Admin | Orga | Gruppenleitung | Mitglied | Betrachter |
|---|:-:|:-:|:-:|:-:|:-:|
| Mein Eintrag | ✓ | ✓ | ✓ | ✓ | ✓ |
| Start | ✓ | ✓ | ✓ | ✓ | ✓ |
| Adressbuch | ✓ | ✓ | ✓ | ✓ | ✓ |
| Nachrichten | ✓ | ✓ | – | – | – |
| Termine | ✓ | ✓ | – | – | – |
| Gruppen | ✓ | ✓ | ✓ | (wenn drin) | (wenn drin) |
| Einstellungen | ✓ | ✓ | – | – | – |

---

## 3. Befunde

Sortiert nach Schwere. `Belege:` nennt die Fundstelle.

### A · Echte Fehler

**A1 — Start-„Steht an" verlinkt für Nicht-Verwalter auf eine gesperrte Seite.**
`templates/start/index.php` baut die Aufgabenliste aus `$stats['without_email']`
/ `without_phone` und rendert den `.start-board`-Block **ungated**. Die Links
gehen auf `/vollstaendigkeit?which=…`, und `ContactController::completeness()`
erzwingt `requirePermission('contacts.manage')` → 500 für Mitglieder und
Betrachter, sobald irgendein Kontakt eine Lücke hat.
*Belege:* `templates/start/index.php` Z. 14–28, 53–82; `ContactController::completeness` Z. 1.
*Aufwand:* Minimal (Block rechtegaten). **Unabhängig von allen anderen Entscheidungen fixen.**

**A2 — Verwaiste Route `/namensliste`.** Existiert nur noch als Redirect-Shim
auf `/vollstaendigkeit`. Tot, aber harmlos. *Aufwand:* trivial.

### B · Überfrachtung & Redundanz

**B1 — Adressbuch-Werkzeugleiste: 6 Knöpfe, Tendenz steigend.**
`Rundmail an diese Liste` · `Vollständigkeit` · `CSV exportieren` ·
`vCard exportieren` · `Doppelt? (N)` · `Archiv & Papierkorb (N)`.
Davon lassen sich bündeln:
- **Exportieren ▾** → CSV + vCard (ein Knopf, Menü)
- **Datenpflege ▾** → Vollständigkeit + Doppelt? + Archiv & Papierkorb
- bleibt sichtbar: **Rundmail an diese Liste**

„Vollständigkeit" steht zusätzlich als Kachel im Verwaltungs-Hub – doppelt.
*Belege:* `templates/contacts/index.php` Z. ~289–300 (nach Zeilennummern der v1.20.0).
*Aufwand:* 1 Einheit. 6 → 3.

**B2 — Zwei Filter-Ebenen, gleicher Name.** Sichtbar: Suchfeld +
Kategorie-Dropdown + Knopf „Filtern". Direkt daneben ein aufklappbarer Drawer,
Beschriftung ebenfalls **„Filter"** (Sortierung, Tags, Gruppen, „ohne Mail/Tel").
Der Drawer sollte „Mehr Filter" oder „Erweitert" heißen.
*Belege:* `templates/contacts/index.php` `.filter-bar` / `.filter-more`.

**B3 — Verwaltungs-Hub: 18 Kacheln, Gruppierung teils schief.**
Aktuell 3 Gruppen: *Zugänge* (5) · *Erscheinungsbild* (7) · *System* (6).
- **„Vollständigkeit"** liegt unter *System*, ist aber Datenpflege-Alltag.
- **„Gruppen", „Kategorien & Tags", „Grüße-Pool"** liegen unter
  *Erscheinungsbild* – sind aber operativ, nicht Aussehen.
- **„Cronjob einrichten"** ist Einmal-Setup, keine wiederkehrende Kachel –
  besser ein Hinweisstreifen, der verschwindet, wenn der Cron läuft.

*Vorschlag für 4 Gruppen:*
| Gruppe | Kacheln |
|---|---|
| **Zugänge & Rollen** | Benutzer · Selbst-Registrierung · Rollen · Berechtigungen · Sichtbarkeit |
| **Inhalt & Struktur** | Kategorien & Tags · Gruppen · Grüße-Pool · Vollständigkeit |
| **Aussehen & Texte** | Branding · Themes · Mail-Einstellungen · Rechtliches |
| **System** | Aktualisieren · Datensicherung · Änderungsprotokoll · Versandprotokoll · (Cron nur als Hinweis) |

*Belege:* `templates/admin/hub.php`.

**B4 — Viele Wege, eine Mail zu schreiben.** Rail „Nachrichten" (nur
`mail.send`), Footer „Orga-Team schreiben", pro Kontakt, aus Adressbuch-Auswahl,
Gruppen-Mail. Für Admin/Orga nachvollziehbar. Für **Mitglieder** ist der einzige
Weg an das Team – „Orga-Team schreiben" – im **Footer** der Seitenleiste
versteckt. Das ist die wichtigste Aktion eines Mitglieds und gehört nach oben.
*Belege:* `templates/layout/app.php` `.rail-foot .rail-orga`.

### C · Rollen: Lücken

**C1 — Gruppenleitung hat kein Zuhause.** Sieht die normale Mitglieder-Start-
Seite. Leitungsaufgaben (Beitrittsanfragen beantworten, Abstimmung anlegen,
Gruppen-Mail) liegen unter `/gruppen` → Knopf „Verwalten" →
`/verwaltung/gruppen/detail?id=…`. Der Weg ist tief, und der Zielname
„Verwaltung" klingt nach Admin-Bereich, obwohl eine Leitung ohne
Admin-Rechte dort landet.
*Belege:* `templates/groups/index.php` Z. 36–41; `GroupController::requireGroupManage`.

**C2 — „Mein Eintrag" steht über „Start".** Für Admin/Orga, die ihren eigenen
Kontakt praktisch nie bearbeiten, ist der prominenteste Navigationsplatz an
eine selten genutzte Seite vergeben. Für Mitglieder ist die Position goldrichtig.
→ Reihenfolge rollenabhängig: Verwalter sehen „Start" oben, „Mein Eintrag" als
kleinen Eintrag weiter unten.

**C3 — Betrachter sieht eine fast leere App.** Start ohne Aktionsknöpfe, ohne
Widgets (kein `events.manage`; Geburtstage nur bei sichtbarem Feld), evtl. mit
kaputten „Steht an"-Links (A1). Danach nur das (redigierte) Adressbuch. Wenn
„Betrachter" eine vollwertige Rolle sein soll, braucht die Start-Seite eine
sinnvolle Ansicht. Wenn nicht: streichen.

**C4 — Start-Seite ist statisch priorisiert.** Reihenfolge fix: „Steht an"
(Datenlücken) → „Offene Rückmeldungen" → „Geburtstage". Eine Abstimmung mit
Frist morgen ist dringender als drei fehlende Telefonnummern, erscheint aber
darunter. Das Board sollte nach Dringlichkeit sortieren, nicht nach Rubrik.
*Belege:* `templates/start/index.php`.

### D · Was gut ist (zur Einordnung)

- Klare, rechtegesteuerte Seitenleiste; genau ein `<h1>` je Seite; das
  `page-head`-Muster ist konsequent durchgezogen.
- Der **Verwaltungs-Hub-Gedanke** ist genau richtig: „alles Seltene an einem
  Ort, für den Alltag reichen Start + Adressbuch" (steht so im Hub-Kopf). Nur
  die Füllung ist über die Zeit gewachsen.
- **Blickschutz-Umschalter, Auswählen-Modus, Tabelle/Karten-Umschalter,
  Hilfe im neuen Tab, Kurzanleitungs-Links auf den Lead-Karten** – durchdacht.
- Die Grund-IA aus dem Redesign trägt; es geht um Aufräumen, nicht um Neubau.

---

## 4. Empfehlungen

### Sofort, risikolos – unabhängig von der Grundsatzentscheidung

| # | Maßnahme | Aufwand |
|---|---|---|
| 1 | **A1 fixen:** „Steht an" nur für `contacts.manage` rendern (oder Links rollenabhängig). | ~0,5 Einheit |
| 2 | **B1:** Adressbuch-Leiste bündeln – „Exportieren ▾" und „Datenpflege ▾". | ~1 Einheit |
| 3 | **B2:** Filter-Drawer in „Mehr Filter" umbenennen. | trivial |
| 4 | **B4 (Teil):** „Orga-Team schreiben" für Mitglieder aus dem Footer in die Haupt-Rail hochziehen. | ~0,5 Einheit |
| 5 | **A2:** `/namensliste`-Shim entfernen. | trivial |

### Struktur – braucht eine Entscheidung von TH

| # | Maßnahme | Aufwand |
|---|---|---|
| 6 | **B3:** Verwaltungs-Hub auf 4 Gruppen umbauen (Tabelle oben). Cron als Hinweisstreifen. | ~1 Einheit |
| 7 | **C4 + C2:** Start-Seite rollenspezifisch. Admin/Orga = dynamisch sortiertes Aufgaben-Board. Mitglied = „Deine Abstimmungen" + „Deine Daten" + Suche. „Mein Eintrag" in der Rail rollenabhängig einsortieren. | ~2 Einheiten |
| 8 | **C1:** Einstieg für Gruppenleitungen. Entweder `/gruppen` wird für Leads ein kleines Dashboard (offene Anfragen, laufende Abstimmungen), oder ein Rail-Eintrag „Meine Gruppen" mit Badge. | ~1–2 Einheiten |

### Vorab mit den echten Nutzer:innen klären (kann ich nicht entscheiden)

- Die **3–5 häufigsten Aufgaben je Rolle** bestätigen – Grundlage für die
  Start-Seiten (Empfehlung 7).
- Wird **„Betrachter"** tatsächlich verwendet? (C3)
- Gibt es **aktive Gruppenleitungen**, oder ist das noch Theorie? (C1)
- Soll die **globale Suche** (Topbar) und die **große Suche auf Start**
  beides bleiben, oder auf Start nur für Mitglieder?

---

## 5. Entscheidungsvorlage

Zum Abhaken – was wird umgesetzt?

- [ ] **Sofort-Paket** (Empf. 1–5) freigeben → eigenes Release, ~1–1,5 Einheiten.
- [ ] **Verwaltungs-Hub** neu gruppieren (Empf. 6) → ja / nein / anders.
- [ ] **Start-Seiten rollenspezifisch** (Empf. 7) → ja / nein. Wenn ja: zuerst
      die Aufgabenliste je Rolle liefern.
- [ ] **Gruppenleitungs-Einstieg** (Empf. 8) → ja / nein. Abhängig davon, ob
      die Rolle real genutzt wird.
- [ ] **Betrachter-Rolle**: Start-Seite bauen / Rolle behalten / Rolle streichen.

Reihenfolge, falls alles kommt: **Sofort-Paket → Hub → Start-Seiten →
Gruppenleitung.** Die Sprach- und Kommentar-/Refactoring-Themen aus dem
`TODO.md` danach, damit sie den aufgeräumten Stand beschreiben.
