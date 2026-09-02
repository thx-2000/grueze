<header class="page-head">
    <p class="eyebrow">Zugang einrichten</p>
    <h1>Willkommen!</h1>
    <p class="muted">Für <strong><?= e($email) ?></strong>. Bestätige deinen Namen und wähle, wie du dich anmelden willst.</p>
</header>

<section class="panel stack auth-card">
    <form method="post" action="<?= e(url('/registrieren')) ?>" class="stack" data-register-form>
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>
            <span>Dein Name</span>
            <input type="text" name="name" value="<?= e($suggestedName) ?>" required autocomplete="name">
        </label>

        <fieldset class="register-mode">
            <legend>Anmeldung</legend>
            <label class="inline-toggle"><input type="radio" name="mode" value="password" checked data-register-mode><span>Mit Kennwort</span></label>
            <label class="inline-toggle"><input type="radio" name="mode" value="passkey" data-register-mode><span>Mit Passkey (Face&nbsp;ID, Fingerabdruck, Sicherheitsschlüssel)</span></label>
        </fieldset>

        <div data-register-password>
            <label>
                <span>Kennwort (mindestens 12 Zeichen)</span>
                <input type="password" name="password" minlength="12" autocomplete="new-password">
            </label>
            <label>
                <span>Kennwort wiederholen</span>
                <input type="password" name="password_repeat" minlength="12" autocomplete="new-password">
            </label>
        </div>

        <p class="field-hint" data-register-passkey-hint hidden>Im nächsten Schritt richtest du den Passkey über die Gerätefreigabe ein.</p>

        <button type="submit">Zugang einrichten</button>
    </form>
</section>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    const form = document.querySelector('[data-register-form]');
    if (!form) return;
    const pwBlock = form.querySelector('[data-register-password]');
    const pkHint = form.querySelector('[data-register-passkey-hint]');
    const pwInputs = pwBlock.querySelectorAll('input');
    const sync = () => {
        const passkey = form.querySelector('[data-register-mode]:checked').value === 'passkey';
        pwBlock.hidden = passkey;
        pkHint.hidden = !passkey;
        pwInputs.forEach((i) => { i.required = !passkey; i.disabled = passkey; });
    };
    form.querySelectorAll('[data-register-mode]').forEach((r) => r.addEventListener('change', sync));
    sync();
})();
</script>
