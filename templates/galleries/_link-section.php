<?php
/**
 * Weitergabe-Links (QR) zum Beisteuern ohne Login.
 *
 * @var int|null $galleryId          null = Auffangraum
 * @var list<array<string,mixed>> $links   aktive Links (nur Metadaten)
 * @var array<string,mixed>|null $freshLink  gerade erstellter Link (mit url)
 * @var string $csrfToken
 * @var int $linkDays
 */
$targetField = $galleryId !== null
    ? '<input type="hidden" name="gallery_id" value="' . (int) $galleryId . '">'
    : '';
?>
<details class="panel gallery-links" <?= $freshLink !== null ? 'open' : '' ?>>
    <summary>Upload-Link zum Weitergeben<?= $links !== [] ? ' (' . count($links) . ')' : '' ?></summary>

    <p class="muted">Ein Link, über den Leute <strong>ohne Login</strong> Fotos/Videos beisteuern können –
        per Messenger/Mail verschicken oder den QR-Code ausdrucken und aushängen.
        <?= $galleryId !== null ? 'Die Uploads landen direkt in dieser Galerie.' : 'Die Uploads landen im Auffangraum.' ?></p>

    <?php if ($freshLink !== null): ?>
        <div class="fresh-link" data-qr-block>
            <p class="fresh-link-head"><?= icon('check') ?> <strong>Link erstellt.</strong> Jetzt kopieren oder QR speichern – er wird später nicht noch einmal angezeigt.</p>
            <div class="copy-field">
                <label class="visually-hidden" for="uploadLinkField">Upload-Link</label>
                <input type="text" id="uploadLinkField" value="<?= e((string) $freshLink['url']) ?>" readonly spellcheck="false">
                <button type="button" class="ghost-button" data-copy="#uploadLinkField"><?= icon('copy') ?><span>Kopieren</span></button>
            </div>
            <div class="qr-holder" data-qr="<?= e((string) $freshLink['url']) ?>" data-qr-label="QR-Code für den Upload-Link"></div>
            <button type="button" class="ghost-button compact-action" data-qr-save><?= icon('download') ?><span>QR-Code speichern</span></button>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/galerien/link')) ?>" class="stack link-create-form">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?= $targetField ?>
        <div class="form-grid">
            <label>
                <span>Bezeichnung (optional)</span>
                <input type="text" name="label" maxlength="120" placeholder="z. B. „Aushang Festzelt“">
            </label>
            <label>
                <span>Gültig für … Tage</span>
                <input type="number" name="days" min="1" max="365" value="<?= (int) $linkDays ?>">
            </label>
            <label>
                <span>Max. Uploads (optional)</span>
                <input type="number" name="max_uploads" min="1" max="5000" placeholder="unbegrenzt">
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="button-link"><?= icon('link') ?><span>Upload-Link erstellen</span></button>
        </div>
    </form>

    <?php if ($links !== []): ?>
        <p class="field-hint">Der QR-Code eines Links lässt sich aus Sicherheitsgründen nicht nachträglich anzeigen – „Neuer QR-Code" zieht den bestehenden Link zurück und erstellt sofort einen neuen mit denselben Eckdaten.</p>
        <ul class="link-list">
            <?php foreach ($links as $l): ?>
                <li>
                    <div>
                        <strong><?= e(trim((string) ($l['label'] ?? '')) !== '' ? $l['label'] : 'Upload-Link') ?></strong>
                        <span class="muted">
                            <?php if ($galleryId === null && trim((string) ($l['gallery_title'] ?? '')) !== ''): ?>
                                → <?= e($l['gallery_title']) ?> ·
                            <?php endif; ?>
                            <?= (int) $l['upload_count'] ?> Uploads<?php if ($l['max_uploads'] !== null): ?> / <?= (int) $l['max_uploads'] ?><?php endif; ?>
                            <?php if ($l['expires_at'] !== null): ?> · bis <?= e(format_date(substr((string) $l['expires_at'], 0, 10))) ?><?php endif; ?>
                        </span>
                    </div>
                    <div class="link-row-actions">
                        <form method="post" action="<?= e(url('/galerien/link/erneuern')) ?>" data-confirm="Neuen QR-Code erzeugen? Der bisherige Link „<?= e(trim((string) ($l['label'] ?? '')) !== '' ? $l['label'] : 'Upload-Link') ?>“ wird dabei ungültig.">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $l['id']) ?>">
                            <button type="submit" class="ghost-button compact-action"><?= icon('link') ?><span>Neuer QR-Code</span></button>
                        </form>
                        <form method="post" action="<?= e(url('/galerien/link/widerrufen')) ?>" data-confirm="Diesen Upload-Link ungültig machen?">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= e((string) $l['id']) ?>">
                            <button type="submit" class="ghost-button compact-action">Zurückziehen</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</details>
