const toast = document.getElementById('toast');
const mobileContactsLayout = window.matchMedia('(max-width: 900px)');

function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
        toast.hidden = true;
    }, 2400);
}

function activeView() {
    return mobileContactsLayout.matches ? 'mobile' : 'desktop';
}

function selectableItemsForCurrentView() {
    return [...document.querySelectorAll(`[data-contact-selectable][data-view="${activeView()}"]`)];
}

function selectedContactItems() {
    return [...document.querySelectorAll(`[data-contact-selectable][data-view="${activeView()}"] [data-contact-checkbox]:checked`)]
        .map((checkbox) => checkbox.closest('[data-contact-selectable]'))
        .filter(Boolean);
}

const copyButton = document.getElementById('copyEmailsButton');
const selectionStatus = document.getElementById('selectionStatus');

function updateSelectionUI() {
    const items = document.querySelectorAll('[data-contact-selectable]');
    const currentView = activeView();
    let selectedCount = 0;

    items.forEach((item) => {
        const checkbox = item.querySelector('[data-contact-checkbox]');
        const isActiveView = item.dataset.view === currentView;
        const isSelected = Boolean(checkbox?.checked);
        item.classList.toggle('has-selection', isActiveView && isSelected);
        if (isActiveView && isSelected) {
            selectedCount += 1;
        }
    });

    if (selectionStatus) {
        selectionStatus.textContent = selectedCount === 0
            ? 'Noch nichts ausgewählt'
            : `${selectedCount} Kontakt${selectedCount === 1 ? '' : 'e'} ausgewählt`;
    }
}

if (copyButton) {
    copyButton.addEventListener('click', async () => {
        const emails = selectedContactItems()
            .flatMap((item) => [...item.querySelectorAll('[data-email]')].map((entry) => entry.dataset.email))
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
        selectableItemsForCurrentView().forEach((item) => {
            const checkbox = item.querySelector('[data-contact-checkbox]');
            checkbox.checked = true;
        });
        updateSelectionUI();
    });
});

document.querySelectorAll('[data-select="none"]').forEach((button) => {
    button.addEventListener('click', () => {
        selectableItemsForCurrentView().forEach((item) => {
            const checkbox = item.querySelector('[data-contact-checkbox]');
            checkbox.checked = false;
        });
        updateSelectionUI();
    });
});

document.querySelectorAll('[data-select-category]').forEach((button) => {
    button.addEventListener('click', () => {
        const categoryId = button.dataset.selectCategory;
        selectableItemsForCurrentView().forEach((item) => {
            const checkbox = item.querySelector('[data-contact-checkbox]');
            checkbox.checked = item.dataset.categoryId === categoryId;
        });
        updateSelectionUI();
    });
});

document.querySelectorAll('[data-select-tag]').forEach((button) => {
    button.addEventListener('click', () => {
        const tagId = button.dataset.selectTag;
        selectableItemsForCurrentView().forEach((item) => {
            const checkbox = item.querySelector('[data-contact-checkbox]');
            const tagIds = (item.dataset.tagIds || '').split(',').filter(Boolean);
            checkbox.checked = tagIds.includes(tagId);
        });
        updateSelectionUI();
    });
});

document.querySelectorAll('[data-contact-checkbox]').forEach((checkbox) => {
    checkbox.addEventListener('change', updateSelectionUI);
});

mobileContactsLayout.addEventListener('change', updateSelectionUI);

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
updateSelectionUI();

const selectionForm = document.getElementById('contactSelectionForm');
if (selectionForm) {
    selectionForm.addEventListener('submit', () => {
        const currentView = activeView();
        document.querySelectorAll('[data-contact-selectable]').forEach((item) => {
            const checkbox = item.querySelector('[data-contact-checkbox]');
            if (!checkbox) return;
            checkbox.disabled = item.dataset.view !== currentView;
        });
    });
}

const contactsTable = document.querySelector('.contacts-table');
if (contactsTable) {
    const storageKey = 'grueze_visible_contact_columns';
    const defaultColumns = ['category', 'tags', 'ort', 'geburtstag', 'emails', 'phones', 'login'];
    const savedColumns = (() => {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey) || 'null');
        } catch (error) {
            return null;
        }
    })();

    const visibleColumns = Array.isArray(savedColumns) && savedColumns.length > 0 ? savedColumns : defaultColumns;

    function applyVisibleColumns() {
        document.querySelectorAll('[data-column-toggle]').forEach((toggle) => {
            toggle.checked = visibleColumns.includes(toggle.dataset.columnToggle);
        });

        document.querySelectorAll('.contacts-table [data-col]').forEach((cell) => {
            const shouldShow = visibleColumns.includes(cell.dataset.col);
            cell.classList.toggle('is-hidden-column', !shouldShow);
        });
    }

    document.querySelectorAll('[data-column-toggle]').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const column = toggle.dataset.columnToggle;
            if (toggle.checked) {
                if (!visibleColumns.includes(column)) {
                    visibleColumns.push(column);
                }
            } else {
                const index = visibleColumns.indexOf(column);
                if (index >= 0) {
                    visibleColumns.splice(index, 1);
                }
            }

            window.localStorage.setItem(storageKey, JSON.stringify(visibleColumns));
            applyVisibleColumns();
        });
    });

    applyVisibleColumns();
}

const statusPage = document.querySelector('[data-mail-status-page]');
if (statusPage) {
    const progressBar = document.getElementById('mailProgressBar');
    const progressText = document.getElementById('mailProgressText');
    const results = document.getElementById('mailResults');
    const statusBadge = document.getElementById('mailStatusBadge');

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
        if (statusBadge) {
            statusBadge.textContent = `${data.processed} / ${data.total} verarbeitet`;
        }
        results.innerHTML = data.results.map((entry) => `
            <div>${entry.ok ? 'OK' : 'Fehler'}: ${entry.name}${entry.error ? ` (${entry.error})` : ''}</div>
        `).join('');

        if (!data.done) {
            await runBatch();
        } else {
            showToast('Versand abgeschlossen.');
        }
    }

    runBatch();
}
