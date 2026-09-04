<?php
/**
 * @var array<string,mixed> $pickerData
 */
$announcement = null;
$audienceRows = [];
?>
<p class="detail-backlink"><a href="<?= e(url('/termine')) ?>"><?= icon('chevron-right') ?>Zurück zu den Terminen</a></p>

<header class="contact-detail-head">
    <p class="eyebrow">Neue Ankündigung</p>
    <h1>Termin ankündigen</h1>
    <p class="muted">Reine Information – keine Zu-/Absage, kein Teilnehmerkreis. Für eine Abstimmung mit Datumsfindung gibt es die <a href="<?= e(url('/abstimmungen/neu')) ?>">Abstimmungen</a>.</p>
</header>

<form method="post" action="<?= e(url('/termine')) ?>" class="contact-detail-form">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <?php view_partial('announcements/_fields', ['announcement' => $announcement, 'pickerData' => $pickerData, 'audienceRows' => $audienceRows]); ?>

    <div class="detail-save-bar" data-save-bar>
        <span class="detail-save-hint">Ankündigung veröffentlichen.</span>
        <div class="detail-save-actions">
            <a class="ghost-button" href="<?= e(url('/termine')) ?>">Zurück</a>
            <button type="submit">Veröffentlichen</button>
        </div>
    </div>
</form>
