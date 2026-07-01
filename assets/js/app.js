const toast = document.getElementById('toast');

function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
        toast.hidden = true;
    }, 2400);
}

function selectedContactCards() {
    return [...document.querySelectorAll('[data-contact-checkbox]:checked')].map((checkbox) => checkbox.closest('.contact-card'));
}

const copyButton = document.getElementById('copyEmailsButton');
if (copyButton) {
    copyButton.addEventListener('click', async () => {
        const emails = selectedContactCards()
            .flatMap((card) => [...card.querySelectorAll('[data-email]')].map((item) => item.dataset.email))
            .filter(Boolean);

        if (emails.length === 0) {
            showToast('Bitte zuerst Kontakte auswählen.');
            return;
        }

        const unique = [...new Set(emails)].join(', ');

        try {
            await navigator.clipboard.writeText(unique);
            showToast('E-Mail-Adressen wurden kopiert.');
        } catch (error) {
            const field = document.createElement('textarea');
            field.value = unique;
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            field.remove();
            showToast('E-Mail-Adressen wurden kopiert.');
        }
    });
}

document.querySelectorAll('[data-select="all"]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-contact-checkbox]').forEach((checkbox) => {
            checkbox.checked = true;
        });
    });
});

document.querySelectorAll('[data-select-category]').forEach((button) => {
    button.addEventListener('click', () => {
        const categoryId = button.dataset.selectCategory;
        document.querySelectorAll('.contact-card').forEach((card) => {
            const checkbox = card.querySelector('[data-contact-checkbox]');
            checkbox.checked = card.dataset.categoryId === categoryId;
        });
    });
});

function attachRemoveHandlers(scope = document) {
    scope.querySelectorAll('[data-remove-row]').forEach((button) => {
        button.onclick = () => button.closest('.repeater-row')?.remove();
    });
}

function addRepeaterRow(type) {
    const template = document.getElementById(type === 'emails' ? 'emailRowTemplate' : 'phoneRowTemplate');
    const container = document.getElementById(type === 'emails' ? 'emailsRepeater' : 'phonesRepeater');
    if (!template || !container) return;

    const index = container.querySelectorAll('.repeater-row').length;
    const fragment = template.content.cloneNode(true);
    fragment.querySelectorAll('[data-name]').forEach((field) => {
        field.name = `${type}[${index}][${field.dataset.name}]`;
    });
    container.appendChild(fragment);
    attachRemoveHandlers(container);
}

document.querySelectorAll('[data-add-row]').forEach((button) => {
    button.addEventListener('click', () => addRepeaterRow(button.dataset.addRow));
});

attachRemoveHandlers();

const composeForm = document.getElementById('mailComposeForm');
if (composeForm) {
    composeForm.addEventListener('submit', async (event) => {
        if (event.submitter && event.submitter.formAction && !event.submitter.formAction.endsWith('/mail/start')) {
            return;
        }

        event.preventDefault();
        const formData = new FormData(composeForm);
        const progressPanel = document.getElementById('mailProgress');
        const progressBar = document.getElementById('mailProgressBar');
        const progressText = document.getElementById('mailProgressText');
        const results = document.getElementById('mailResults');

        progressPanel.hidden = false;
        results.innerHTML = '';

        const startResponse = await fetch(composeForm.action, {
            method: 'POST',
            body: formData,
        });
        const startData = await startResponse.json();
        if (!startData.ok) {
            showToast(startData.message || 'Versand konnte nicht gestartet werden.');
            return;
        }

        async function runBatch() {
            const response = await fetch(window.APP.batchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({ _csrf: window.APP.csrfToken }),
            });
            const data = await response.json();

            if (!data.ok) {
                showToast(data.message || 'Batch-Versand fehlgeschlagen.');
                return;
            }

            const percent = data.total > 0 ? (data.processed / data.total) * 100 : 0;
            progressBar.style.width = `${percent}%`;
            progressText.textContent = `${data.processed} von ${data.total} gesendet`;
            results.innerHTML = data.results.map((entry) => `
                <div>${entry.ok ? 'OK' : 'Fehler'}: ${entry.name}${entry.error ? ` (${entry.error})` : ''}</div>
            `).join('');

            if (!data.done) {
                await runBatch();
            } else {
                showToast('Versand abgeschlossen.');
            }
        }

        await runBatch();
    });
}
