<header class="page-head page-head--split">
    <div>
        <p class="eyebrow">Import</p>
        <h1>Kontakte aus XLSX einspielen</h1>
        <p class="muted">Gedacht für die vorhandene Namens- und Adressliste. Bestehende Kontakte werden am Namen abgeglichen und bei Bedarf ergänzt.</p>
    </div>
    <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Zurück zum Adressbuch</a>
</header>

<section class="panel stack narrow">
    <div class="subsection-card stack">
        <div>
            <h3>Erwartete Spalten</h3>
            <p class="muted">Die Kopfzeile muss diese Felder enthalten:</p>
        </div>
        <div class="tag-cluster">
            <span class="table-pill">Vorname</span>
            <span class="table-pill">Geburtsname</span>
            <span class="table-pill">Nachname akt.</span>
            <span class="table-pill">Mail</span>
            <span class="table-pill">Ort</span>
            <span class="table-pill">Handy</span>
        </div>
        <p class="detail-hint">Wenn <strong>Nachname akt.</strong> leer ist, wird zuerst eine optionale Spalte <strong>Nachname</strong> verwendet und sonst der <strong>Geburtsname</strong> als Nachname übernommen.</p>
        <p class="detail-hint">Kontakte ohne Mailadresse werden trotzdem importiert und später in der Übersicht mit einem Symbol markiert.</p>
    </div>

    <form method="post" action="<?= e(url('/contacts/import')) ?>" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="full-width">
            <span>XLSX-Datei</span>
            <input type="file" name="import_file" accept=".xlsx" required>
        </label>
        <div class="form-actions">
            <button type="submit"><?= icon('upload') ?><span>Import starten</span></button>
            <a class="ghost-button" href="<?= e(url('/kontakte')) ?>">Abbrechen</a>
        </div>
    </form>
</section>
