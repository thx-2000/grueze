const toast = document.getElementById('toast');
const mobileContactsLayout = window.matchMedia('(max-width: 900px)');
const contactsViewRoot = document.querySelector('[data-contacts-view-root]');
const contactsViewStorageKey = 'grueze_contacts_view_mode';

function showToast(message) {
    if (!toast) return;
    // Erst einblenden, dann Text setzen – so meldet die aria-live-Region
    // die Änderung zuverlässig an Screenreader.
    toast.hidden = false;
    toast.textContent = '';
    window.requestAnimationFrame(() => {
        toast.textContent = message;
    });
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
        toast.hidden = true;
        toast.textContent = '';
    }, 2400);
}

function activeView() {
    if (!contactsViewRoot) {
        return mobileContactsLayout.matches ? 'mobile' : 'desktop';
    }

    return contactsViewRoot.dataset.activeView || 'desktop';
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
const bulkSelectionHint = document.getElementById('bulkSelectionHint');
const signalSelectionStatus = document.getElementById('signalSelectionStatus');
const signalComposeSelection = document.getElementById('signalComposeSelection');
const signalClearSelection = document.getElementById('signalClearSelection');

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

    if (signalSelectionStatus) {
        signalSelectionStatus.hidden = selectedCount === 0;
        signalSelectionStatus.textContent = `${selectedCount} Kontakt${selectedCount === 1 ? '' : 'e'} markiert`;
    }

    if (signalComposeSelection) {
        signalComposeSelection.hidden = selectedCount === 0;
    }

    if (signalClearSelection) {
        signalClearSelection.hidden = selectedCount === 0;
    }

    if (bulkSelectionHint) {
        bulkSelectionHint.textContent = selectedCount === 0
            ? 'Keine Kontakte ausgewählt.'
            : `${selectedCount} Kontakt${selectedCount === 1 ? '' : 'e'} werden für die Sammeländerung verwendet.`;
    }
}

function applyContactsView(view, persist = true) {
    if (!contactsViewRoot) {
        return;
    }

    const normalizedView = view === 'mobile' ? 'mobile' : 'desktop';
    contactsViewRoot.dataset.activeView = normalizedView;
    contactsViewRoot.classList.toggle('is-table', normalizedView === 'desktop');
    contactsViewRoot.classList.toggle('is-cards', normalizedView === 'mobile');

    document.querySelectorAll('[data-view-toggle]').forEach((button) => {
        const isActive = button.dataset.viewToggle === normalizedView;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (persist) {
        try {
            window.localStorage.setItem(contactsViewStorageKey, normalizedView);
        } catch (error) {
            // Ignore storage failures and keep the current in-memory state.
        }
    }

    updateSelectionUI();
}

document.querySelectorAll('[data-view-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        applyContactsView(button.dataset.viewToggle);
    });
});

// „Auswählen"-Modus: blendet Checkbox-Spalte und Aktionsleiste ein. Beim
// Verlassen wird die Auswahl geleert, damit nichts unbemerkt gesendet wird.
const selectModeToggle = document.querySelector('[data-select-mode-toggle]');
if (selectModeToggle && contactsViewRoot) {
    const setSelectMode = (on) => {
        contactsViewRoot.classList.toggle('is-selecting', on);
        selectModeToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (!on) {
            document.querySelectorAll('[data-contact-checkbox]').forEach((checkbox) => {
                checkbox.checked = false;
            });
            updateSelectionUI();
        } else {
            const firstCheckbox = document.querySelector(
                `[data-contact-selectable][data-view="${activeView()}"] [data-contact-checkbox]`
            );
            firstCheckbox?.focus();
        }
    };

    selectModeToggle.addEventListener('click', () => {
        setSelectMode(!contactsViewRoot.classList.contains('is-selecting'));
    });

    document.querySelectorAll('[data-select-mode-exit]').forEach((button) => {
        button.addEventListener('click', () => setSelectMode(false));
    });
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

if (contactsViewRoot) {
    document.querySelectorAll('[data-contact-checkbox]').forEach((checkbox) => {
        checkbox.checked = false;
    });

    let savedView = null;
    try {
        savedView = window.localStorage.getItem(contactsViewStorageKey);
    } catch (error) {
        savedView = null;
    }

    // Ohne gespeicherte Wahl richtet sich die Ansicht nach dem Gerät (am Handy
    // Karten, sonst Tabelle) – ohne diese Automatik gleich zu speichern.
    const hasSavedView = savedView === 'mobile' || savedView === 'desktop';
    const initialView = hasSavedView
        ? savedView
        : (mobileContactsLayout.matches ? 'mobile' : 'desktop');

    applyContactsView(initialView, hasSavedView);
} else {
    updateSelectionUI();
}

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

// Ganze Zeile / Karte im Adressbuch klickbar: öffnet die Kontakt-Detailseite.
// Klicks auf echte Bedienelemente (Links, Buttons, Checkboxen, Menüs) und
// Textmarkierungen bleiben unberührt. Im Auswahl-Modus schaltet ein Klick auf
// die Zeile stattdessen die Auswahl um. Die Detailseite bleibt zusätzlich über
// den Pfeil/„Bearbeiten"-Link per Tastatur erreichbar.
const interactiveInRow = 'a, button, input, select, textarea, label, summary, [contenteditable]';
document.querySelectorAll('[data-row-link]').forEach((row) => {
    const openDetail = (newTab) => {
        const href = row.dataset.rowLink;
        if (!href) return;
        if (newTab) {
            window.open(href, '_blank', 'noopener');
        } else {
            window.location.href = href;
        }
    };

    row.addEventListener('click', (event) => {
        if (event.target.closest(interactiveInRow)) return;
        const selection = window.getSelection ? String(window.getSelection()) : '';
        if (selection.trim() !== '') return;

        if (contactsViewRoot && contactsViewRoot.classList.contains('is-selecting')) {
            const checkbox = row.querySelector('[data-contact-checkbox]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateSelectionUI();
            }
            return;
        }

        openDetail(event.metaKey || event.ctrlKey || event.shiftKey);
    });

    // Mittelklick → neuer Tab, wie bei einem echten Link.
    row.addEventListener('auxclick', (event) => {
        if (event.button !== 1 || event.target.closest(interactiveInRow)) return;
        event.preventDefault();
        openDetail(true);
    });
});

// Kontakt-Detail: „Speichern"-Leiste erscheint, sobald etwas geändert wurde;
// eine leichte Rückfrage schützt vor versehentlichem Verlassen.
const detailForm = document.querySelector('[data-detail-form]');
if (detailForm) {
    const saveBar = detailForm.querySelector('[data-save-bar]');
    let dirty = false;

    const markDirty = () => {
        if (dirty) return;
        dirty = true;
        if (saveBar) saveBar.hidden = false;
    };

    detailForm.addEventListener('input', markDirty);
    detailForm.addEventListener('change', markDirty);
    detailForm.querySelectorAll('[data-add-row], [data-remove-row]').forEach((button) => {
        button.addEventListener('click', markDirty);
    });

    detailForm.addEventListener('submit', () => {
        dirty = false;
    });

    const resetButton = detailForm.querySelector('[data-detail-reset]');
    if (resetButton) {
        resetButton.addEventListener('click', () => {
            dirty = false;
            window.location.reload();
        });
    }

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

// Termine: Datumsvorschläge hinzufügen / entfernen.
document.querySelectorAll('[data-date-options]').forEach((container) => {
    const template = document.getElementById('dateOptionTemplate');
    const addButton = container.parentElement.querySelector('[data-add-date]');

    const bindRemove = (row) => {
        row.querySelector('[data-remove-date]')?.addEventListener('click', () => {
            const rows = container.querySelectorAll('.date-option-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            }
        });
    };

    container.querySelectorAll('.date-option-row').forEach(bindRemove);
    addButton?.addEventListener('click', () => {
        if (!template) return;
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.date-option-row');
        container.appendChild(fragment);
        bindRemove(container.lastElementChild || row);
    });
});

// Termine: Freitext-Antwortoptionen hinzufügen / entfernen.
document.querySelectorAll('[data-text-options]').forEach((container) => {
    const template = document.getElementById('textOptionTemplate');
    const addButton = container.parentElement.querySelector('[data-add-text]');

    const bindRemove = (row) => {
        row.querySelector('[data-remove-text]')?.addEventListener('click', () => {
            const rows = container.querySelectorAll('.text-option-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelector('input').value = '';
            }
        });
    };

    container.querySelectorAll('.text-option-row').forEach(bindRemove);
    addButton?.addEventListener('click', () => {
        if (!template) return;
        container.appendChild(template.content.cloneNode(true));
        bindRemove(container.lastElementChild);
    });
});

// Ankündigungen: Link-Zeilen hinzufügen / entfernen, passendes Feld je nach
// gewählter Art (extern/Dokument/Abstimmung) ein-/ausblenden.
document.querySelectorAll('[data-link-options]').forEach((container) => {
    const template = document.getElementById('linkRowTemplate');
    const addButton = container.parentElement.querySelector('[data-add-link]');

    const syncFields = (row) => {
        const kind = row.querySelector('[data-link-kind]')?.value;
        row.querySelectorAll('[data-link-field]').forEach((field) => {
            field.hidden = field.dataset.linkField !== kind;
        });
    };

    const bindRow = (row) => {
        syncFields(row);
        row.querySelector('[data-link-kind]')?.addEventListener('change', () => syncFields(row));
        row.querySelector('[data-remove-link]')?.addEventListener('click', () => {
            const rows = container.querySelectorAll('.link-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input[type="text"], input[type="url"]').forEach((input) => { input.value = ''; });
                row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
                syncFields(row);
            }
        });
    };

    container.querySelectorAll('.link-row').forEach(bindRow);
    addButton?.addEventListener('click', () => {
        if (!template) return;
        container.appendChild(template.content.cloneNode(true));
        bindRow(container.lastElementChild);
    });
});

// Termine: Teilnehmer-Schnellauswahl.
document.querySelectorAll('[data-participant-picker]').forEach((form) => {
    const boxes = [...form.querySelectorAll('.participant-option input[type="checkbox"]')];
    form.querySelector('[data-pick="all"]')?.addEventListener('click', () => {
        boxes.forEach((box) => { box.checked = true; });
    });
    form.querySelector('[data-pick="none"]')?.addEventListener('click', () => {
        boxes.forEach((box) => { box.checked = false; });
    });
    form.querySelectorAll('[data-pick-category]').forEach((button) => {
        button.addEventListener('click', () => {
            const category = button.dataset.pickCategory;
            form.querySelectorAll(`.participant-option[data-category="${CSS.escape(category)}"] input[type="checkbox"]`).forEach((box) => {
                box.checked = true;
            });
        });
    });
    // Auswahl um alle aus einem Tag / einer Gruppe ergänzen.
    form.querySelector('[data-pick-select]')?.addEventListener('change', (event) => {
        const value = event.target.value;
        if (!value) return;
        const [kind, id] = value.split(':');
        const attr = kind === 'group' ? 'groups' : 'tags';
        form.querySelectorAll('.participant-option').forEach((option) => {
            const ids = (option.dataset[attr] || '').split(',').filter(Boolean);
            if (ids.includes(id)) {
                const box = option.querySelector('input[type="checkbox"]');
                if (box) box.checked = true;
            }
        });
        event.target.value = '';
    });
});

// Nachrichten-Seite: Empfängerkreis + Text auf einem Screen.
const messageForm = document.querySelector('[data-message-form]');
if (messageForm) {
    const options = [...messageForm.querySelectorAll('.recipient-option')];
    const countTargets = [...messageForm.querySelectorAll('[data-recipient-count]')];
    const countUrl = messageForm.dataset.countUrl;

    const syncOptions = () => {
        options.forEach((option) => {
            const radio = option.querySelector('input[type="radio"]');
            const active = radio.checked;
            option.classList.toggle('is-active', active);
            option.querySelectorAll('.recipient-option-sub select, .recipient-option-sub input').forEach((field) => {
                field.disabled = !active;
            });
        });
    };

    let countTimer = null;
    const refreshCount = () => {
        if (!countUrl) return;
        window.clearTimeout(countTimer);
        countTimer = window.setTimeout(async () => {
            const params = new URLSearchParams();
            const data = new FormData(messageForm);
            for (const [key, value] of data.entries()) {
                if (['recipient_mode', 'category_id', 'list_id'].includes(key)) params.set(key, value);
                if (key === 'tag_ids[]') params.append('tag_ids[]', value);
                if (key === 'contact_ids[]') params.append('contact_ids[]', value);
            }
            try {
                const res = await fetch(`${countUrl}?${params.toString()}`, { headers: { 'X-Requested-With': 'fetch' } });
                const json = await res.json();
                const n = Number(json.count) || 0;
                countTargets.forEach((el) => { el.textContent = String(n); });
            } catch (error) {
                countTargets.forEach((el) => { el.textContent = '?'; });
            }
        }, 200);
    };

    messageForm.querySelectorAll('input[name="recipient_mode"]').forEach((radio) => {
        radio.addEventListener('change', () => { syncOptions(); refreshCount(); });
    });
    messageForm.querySelectorAll('.recipient-option-sub select, .recipient-option-sub input').forEach((field) => {
        field.addEventListener('change', () => {
            const radio = field.closest('.recipient-option').querySelector('input[type="radio"]');
            radio.checked = true;
            syncOptions();
            refreshCount();
        });
    });

    // Betreff-Vorschau (Präfix + Betreff) live.
    const prefixField = messageForm.querySelector('#subjectPrefixField');
    const subjectField = messageForm.querySelector('#subjectField');
    const subjectPreview = document.getElementById('subjectPreview');
    const updateSubjectPreview = () => {
        if (!subjectPreview) return;
        const prefix = prefixField ? prefixField.value.trim() : '';
        const subject = subjectField ? subjectField.value.trim() : '';
        subjectPreview.textContent = `${prefix ? prefix + ' ' : ''}${subject || 'Dein Betreff'}`.trim();
    };
    prefixField?.addEventListener('change', updateSubjectPreview);
    subjectField?.addEventListener('input', updateSubjectPreview);

    // „Als Liste speichern" – nutzt denselben Empfängerkreis.
    const saveBox = document.getElementById('saveRecipientList');
    if (saveBox) {
        const nameInput = saveBox.querySelector('#saveListName');
        const saveButton = saveBox.querySelector('button');
        const feedback = saveBox.querySelector('.save-list-feedback');
        const show = (message, ok) => {
            feedback.textContent = message;
            feedback.hidden = false;
            feedback.classList.toggle('is-error', !ok);
        };
        saveButton.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (name === '') { nameInput.focus(); return; }
            const body = new URLSearchParams();
            const data = new FormData(messageForm);
            body.set('_csrf', messageForm.querySelector('input[name="_csrf"]').value);
            body.set('name', name);
            for (const [key, value] of data.entries()) {
                if (['recipient_mode', 'category_id', 'list_id'].includes(key)) body.set(key, value);
                if (key === 'tag_ids[]') body.append('tag_ids[]', value);
                if (key === 'contact_ids[]') body.append('contact_ids[]', value);
            }
            saveButton.disabled = true;
            try {
                const res = await fetch(saveBox.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body });
                const json = await res.json().catch(() => ({}));
                if (res.ok && json.ok) {
                    show(`Liste „${json.name}“ gespeichert (${json.count} Kontakte).`, true);
                    nameInput.value = '';
                } else {
                    show(json.error || 'Speichern fehlgeschlagen.', false);
                }
            } catch (error) {
                show('Speichern fehlgeschlagen (Netzwerk).', false);
            } finally {
                saveButton.disabled = false;
            }
        });
    }

    syncOptions();
    refreshCount();
}

const contactsTable = document.querySelector('.contacts-table');
if (contactsTable) {
    const storageKey = 'grueze_visible_contact_columns';
    const availableColumns = [...document.querySelectorAll('[data-column-toggle]')]
        .map((toggle) => toggle.dataset.columnToggle)
        .filter(Boolean);
    // Standard: keine Zusatzspalten – die Tabelle bleibt schlank
    // (Name · Kategorie · Status). Zuschalten merkt sich das Gerät.
    const savedColumns = (() => {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey) || 'null');
        } catch (error) {
            return null;
        }
    })();

    const visibleColumns = Array.isArray(savedColumns)
        ? savedColumns
            .map((column) => column === 'ort' ? 'adresse' : column)
            .filter((column) => availableColumns.includes(column))
        : [];

    function applyVisibleColumns() {
        document.querySelectorAll('[data-column-toggle]').forEach((toggle) => {
            toggle.checked = visibleColumns.includes(toggle.dataset.columnToggle);
        });

        document.querySelectorAll('.contacts-table [data-col]').forEach((cell) => {
            const shouldShow = visibleColumns.includes(cell.dataset.col);
            cell.classList.toggle('is-hidden-column', !shouldShow);
        });

        // Erst nach dem ersten Anwenden dürfen Zusatzspalten überhaupt
        // sichtbar werden – so blitzen sie vor dem Skript nicht kurz auf.
        contactsTable.classList.add('columns-managed');
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
        progressBar.parentElement?.setAttribute('aria-valuenow', String(Math.round(percent)));
        progressText.textContent = `${data.processed} von ${data.total} gesendet`;
        if (statusBadge) {
            statusBadge.textContent = `${data.processed} / ${data.total} verarbeitet`;
        }
        // Kontaktnamen/Fehlertexte per textContent setzen – nie als HTML,
        // sonst schlägt ein "<" im Namen als Markup durch.
        results.replaceChildren(...data.results.map((entry) => {
            const line = document.createElement('div');
            const status = entry.ok ? 'OK' : 'Fehler';
            line.textContent = `${status}: ${entry.name}${entry.error ? ` (${entry.error})` : ''}`;
            return line;
        }));

        if (!data.done) {
            await runBatch();
        } else {
            showToast('Versand abgeschlossen.');
        }
    }

    runBatch();
}

const subjectField = document.getElementById('subjectField');
const subjectPrefixField = document.getElementById('subjectPrefixField');
const subjectPreview = document.getElementById('subjectPreview');

if (subjectPreview && subjectField && subjectPrefixField) {
    const updateSubjectPreview = () => {
        const prefix = subjectPrefixField.value.trim();
        const subject = subjectField.value.trim() || 'Dein Betreff';
        subjectPreview.textContent = prefix ? `${prefix} ${subject}` : subject;
    };

    subjectField.addEventListener('input', updateSubjectPreview);
    subjectPrefixField.addEventListener('change', updateSubjectPreview);
    updateSubjectPreview();
}

document.querySelectorAll('[data-user-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const targetId = button.dataset.userToggle;
        const targetRow = document.getElementById(`user-actions-${targetId}`);
        if (!targetRow) {
            return;
        }

        const willOpen = targetRow.hidden;

        document.querySelectorAll('.user-detail-row').forEach((row) => {
            row.hidden = true;
        });
        document.querySelectorAll('[data-user-toggle]').forEach((toggleButton) => {
            toggleButton.setAttribute('aria-expanded', 'false');
        });

        targetRow.hidden = !willOpen;
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
});

const usersTableBody = document.querySelector('[data-users-table-body]');
if (usersTableBody) {
    let activeUserSort = { key: 'name', direction: 'asc' };

    const readUserSortValue = (row, key) => row.dataset[`sort${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '';

    const compareUserRows = (rowA, rowB, key, direction) => {
        const rawA = readUserSortValue(rowA, key);
        const rawB = readUserSortValue(rowB, key);

        let result = 0;
        if (key === 'passkeys' || key === 'status') {
            result = Number(rawA) - Number(rawB);
        } else if (key === 'login') {
            const timeA = rawA ? Date.parse(rawA) : 0;
            const timeB = rawB ? Date.parse(rawB) : 0;
            result = timeA - timeB;
        } else {
            result = rawA.localeCompare(rawB, 'de', { sensitivity: 'base', numeric: true });
        }

        return direction === 'asc' ? result : result * -1;
    };

    const applyUserSortIndicators = () => {
        document.querySelectorAll('[data-user-sort]').forEach((button) => {
            button.classList.remove('is-asc', 'is-desc');
            const isActive = button.dataset.userSort === activeUserSort.key;
            if (isActive) {
                button.classList.add(activeUserSort.direction === 'asc' ? 'is-asc' : 'is-desc');
            }
            button.closest('th')?.setAttribute(
                'aria-sort',
                isActive ? (activeUserSort.direction === 'asc' ? 'ascending' : 'descending') : 'none',
            );
        });
    };

    const sortUsersTable = () => {
        const pairs = [...usersTableBody.querySelectorAll('[data-user-row]')].map((row) => ({
            row,
            detail: document.getElementById(`user-actions-${row.id.replace('user-', '')}`),
        }));

        pairs.sort((pairA, pairB) => compareUserRows(pairA.row, pairB.row, activeUserSort.key, activeUserSort.direction));

        pairs.forEach(({ row, detail }) => {
            usersTableBody.appendChild(row);
            if (detail) {
                detail.hidden = true;
                usersTableBody.appendChild(detail);
            }
        });

        document.querySelectorAll('[data-user-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
        });

        applyUserSortIndicators();
    };

    document.querySelectorAll('[data-user-sort]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.userSort;
            if (!key) {
                return;
            }

            activeUserSort = {
                key,
                direction: activeUserSort.key === key && activeUserSort.direction === 'asc' ? 'desc' : 'asc',
            };

            sortUsersTable();
        });
    });

    sortUsersTable();
}

function base64urlToBytes(value) {
    const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalized + '='.repeat((4 - (normalized.length % 4 || 4)) % 4);
    const binary = window.atob(padded);
    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

function bytesToBase64url(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let binary = '';
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function decodePublicKeyOptions(options) {
    const decoded = { ...options };
    decoded.challenge = base64urlToBytes(options.challenge);

    if (options.user) {
        decoded.user = {
            ...options.user,
            id: base64urlToBytes(options.user.id),
        };
    }

    if (Array.isArray(options.excludeCredentials)) {
        decoded.excludeCredentials = options.excludeCredentials.map((credential) => ({
            ...credential,
            id: base64urlToBytes(credential.id),
        }));
    }

    if (Array.isArray(options.allowCredentials)) {
        decoded.allowCredentials = options.allowCredentials.map((credential) => ({
            ...credential,
            id: base64urlToBytes(credential.id),
        }));
    }

    return decoded;
}

async function fetchJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Die Anfrage konnte nicht verarbeitet werden.');
    }

    return data;
}

function credentialToPayload(credential) {
    const response = credential.response || {};
    const payload = {
        id: credential.id,
        rawId: bytesToBase64url(credential.rawId),
        type: credential.type,
        response: {},
    };

    if (response.clientDataJSON) {
        payload.response.clientDataJSON = bytesToBase64url(response.clientDataJSON);
    }
    if (response.attestationObject) {
        payload.response.attestationObject = bytesToBase64url(response.attestationObject);
    }
    if (response.authenticatorData) {
        payload.response.authenticatorData = bytesToBase64url(response.authenticatorData);
    }
    if (response.signature) {
        payload.response.signature = bytesToBase64url(response.signature);
    }
    if (response.userHandle) {
        payload.response.userHandle = bytesToBase64url(response.userHandle);
    }
    if (typeof response.getTransports === 'function') {
        payload.response.transports = response.getTransports();
    }

    return payload;
}

const passkeyRegisterButton = document.querySelector('[data-passkey-register]');
if (passkeyRegisterButton) {
    passkeyRegisterButton.addEventListener('click', async () => {
        if (!window.PublicKeyCredential || !navigator.credentials) {
            showToast('Dieser Browser unterstützt Passkeys nicht.');
            return;
        }

        const labelField = document.getElementById('passkeyLabel');
        passkeyRegisterButton.disabled = true;

        try {
            const optionsResponse = await fetchJson(passkeyRegisterButton.dataset.optionsUrl, {
                _csrf: window.APP.csrfToken,
            });
            const options = decodePublicKeyOptions(optionsResponse.options);
            const credential = await navigator.credentials.create({ publicKey: options });
            if (!credential) {
                throw new Error('Der Passkey wurde nicht erstellt.');
            }

            await fetchJson(passkeyRegisterButton.dataset.registerUrl, {
                _csrf: window.APP.csrfToken,
                label: labelField?.value || '',
                ...credentialToPayload(credential),
            });

            showToast('Passkey gespeichert.');
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Passkey konnte nicht gespeichert werden.');
        } finally {
            passkeyRegisterButton.disabled = false;
        }
    });
}

const passkeyLoginButton = document.querySelector('[data-passkey-login]');
if (passkeyLoginButton) {
    passkeyLoginButton.addEventListener('click', async () => {
        if (!window.PublicKeyCredential || !navigator.credentials) {
            showToast('Dieser Browser unterstützt Passkeys nicht.');
            return;
        }

        passkeyLoginButton.disabled = true;

        try {
            const optionsResponse = await fetchJson(passkeyLoginButton.dataset.optionsUrl, {
                _csrf: window.APP.csrfToken,
            });
            const options = decodePublicKeyOptions(optionsResponse.options);
            const credential = await navigator.credentials.get({ publicKey: options });
            if (!credential) {
                throw new Error('Die Passkey-Anmeldung wurde abgebrochen.');
            }

            const authResponse = await fetchJson(passkeyLoginButton.dataset.authUrl, {
                _csrf: window.APP.csrfToken,
                ...credentialToPayload(credential),
            });

            window.location.href = authResponse.redirect || '/';
        } catch (error) {
            showToast(error.message || 'Passkey-Anmeldung fehlgeschlagen.');
        } finally {
            passkeyLoginButton.disabled = false;
        }
    });
}

// Blickschutz: clientseitiger Toggle, der personenbezogene Kontaktdaten weichzeichnet.
// Der Zustand wird pro Gerät gemerkt; das Setzen von data-privacy-guard passiert
// bereits im <head>, hier wird nur der Button synchron gehalten.
const privacyGuardToggle = document.getElementById('privacyGuardToggle');
if (privacyGuardToggle) {
    const privacyGuardKey = 'grueze_privacy_guard';
    const guardLabel = privacyGuardToggle.querySelector('[data-privacy-guard-label]');

    const syncGuardButton = (isOn) => {
        privacyGuardToggle.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        privacyGuardToggle.classList.toggle('is-active', isOn);
        if (guardLabel) {
            guardLabel.textContent = isOn ? 'Blickschutz an' : 'Blickschutz';
        }
    };

    syncGuardButton(document.documentElement.dataset.privacyGuard === 'on');

    privacyGuardToggle.addEventListener('click', () => {
        const nextOn = document.documentElement.dataset.privacyGuard !== 'on';

        if (nextOn) {
            document.documentElement.dataset.privacyGuard = 'on';
        } else {
            delete document.documentElement.dataset.privacyGuard;
        }

        try {
            window.localStorage.setItem(privacyGuardKey, nextOn ? 'on' : 'off');
        } catch (error) {
            // Ohne persistenten Speicher wirkt der Toggle nur für die aktuelle Ansicht.
        }

        syncGuardButton(nextOn);
        showToast(nextOn
            ? 'Blickschutz aktiv – Kontaktdaten sind unkenntlich gemacht.'
            : 'Blickschutz aus – Kontaktdaten sind wieder sichtbar.');
    });
}

// Mobile-Navigation: Hamburger-Menue oeffnet/schliesst die Seitenleiste.
const navToggle = document.querySelector('.nav-toggle');
const pageSidebar = document.getElementById('pageSidebar');
const navBackdrop = document.querySelector('.nav-backdrop');
if (navToggle && pageSidebar) {
    const setNavOpen = (open, { moveFocus = false } = {}) => {
        const wasOpen = document.body.classList.contains('nav-open');
        document.body.classList.toggle('nav-open', open);
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (navBackdrop) {
            navBackdrop.hidden = !open;
        }
        if (open && moveFocus) {
            pageSidebar.querySelector('a, button')?.focus();
        } else if (!open && wasOpen && moveFocus) {
            navToggle.focus();
        }
    };

    navToggle.addEventListener('click', () => {
        setNavOpen(!document.body.classList.contains('nav-open'), { moveFocus: true });
    });

    if (navBackdrop) {
        navBackdrop.addEventListener('click', () => setNavOpen(false));
    }

    pageSidebar.querySelectorAll('a, button[type="submit"]').forEach((el) => {
        el.addEventListener('click', () => setNavOpen(false));
    });

    // Fokus im aufgeklappten Mobil-Menü halten (Tab-Falle). Der Hamburger-Knopf
    // gehört mit zum Kreis, damit man ihn nicht „verliert".
    const trapNavFocus = (event) => {
        if (event.key !== 'Tab' || !document.body.classList.contains('nav-open')) {
            return;
        }

        const focusables = [
            navToggle,
            ...pageSidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'),
        ].filter((el) => el.offsetParent !== null || el === navToggle);

        if (focusables.length === 0) {
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || !focusables.includes(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
            setNavOpen(false);
            navToggle.focus();
        }
        trapNavFocus(event);
    });

    // Beim Wechsel auf Desktop-Breite den Overlay-Zustand zuruecksetzen.
    window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
        if (event.matches) {
            setNavOpen(false);
        }
    });
}

// Nach einer fehlgeschlagenen Aktion den Fehlerhinweis in den Fokus holen,
// damit Screenreader ihn ansagen und Tastaturnutzer direkt dort sind.
const errorFlash = document.querySelector('.content .flash-error');
if (errorFlash) {
    errorFlash.setAttribute('tabindex', '-1');
    errorFlash.focus({ preventScroll: false });
}

// Generischer Kopieren-Knopf: [data-copy="#ziel"] kopiert den Wert/Text des Ziels.
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const target = document.querySelector(button.dataset.copy);
        if (!target) return;
        const text = 'value' in target ? target.value : target.textContent;
        try {
            await navigator.clipboard.writeText(text);
            showToast('In die Zwischenablage kopiert.');
        } catch (error) {
            if ('select' in target) {
                target.focus();
                target.select();
                document.execCommand('copy');
                showToast('In die Zwischenablage kopiert.');
            }
        }
    });
});

// Bestätigungs-Rückfrage vor dem Absenden – ersetzt inline onsubmit/onclick
// "return confirm(...)", damit die Content-Security-Policy ohne 'unsafe-inline'
// auskommt. data-confirm kann auf dem <form> oder auf einem Button/Link stehen.
document.querySelectorAll('[data-confirm]').forEach((el) => {
    const message = el.dataset.confirm;
    if (el.tagName === 'FORM') {
        el.addEventListener('submit', (event) => {
            if (!window.confirm(message)) event.preventDefault();
        });
    } else {
        el.addEventListener('click', (event) => {
            if (!window.confirm(message)) event.preventDefault();
        });
    }
});

// „Zum Home-Bildschirm hinzufügen": schlanker Service Worker (cacht nur
// statische Assets, nie Seiten). Nur in sicheren Kontexten registrieren.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

// Aufklappmenüs in der Werkzeugleiste: bei Klick nach außen oder Esc schließen.
document.addEventListener('click', (event) => {
    document.querySelectorAll('details.tool-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('details.tool-menu[open]').forEach((menu) => menu.removeAttribute('open'));
});
