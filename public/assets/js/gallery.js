/**
 * Galerie-Detailseite: Upload (Drag-and-drop, mehrere gleichzeitig, Fortschritt),
 * Bildunterschriften, Löschen, Titelbild, manuelles Sortieren und Lightbox.
 * Selbstständig – braucht nur window.APP.csrfToken.
 */
(function () {
    'use strict';

    var csrf = (window.APP && window.APP.csrfToken) || '';
    var uploadPanel = document.querySelector('[data-gallery-upload]');
    var grid = document.querySelector('[data-media-grid]');
    if (!grid && !uploadPanel) {
        return;
    }

    var galleryId = uploadPanel ? uploadPanel.getAttribute('data-gallery-id') : '';
    var countEl = document.querySelector('[data-media-count]');
    var emptyEl = document.querySelector('[data-media-empty]');

    function post(url, formData) {
        formData.append('_csrf', csrf);
        return fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Serverfehler.' }; }); });
    }

    function updateCount(delta) {
        if (!countEl) return;
        var n = Math.max(0, parseInt(countEl.textContent, 10) + delta);
        countEl.textContent = String(n);
        if (emptyEl) emptyEl.classList.toggle('is-hidden', n > 0);
    }

    // ---------------------------------------------------------------- Upload

    var dropzone = uploadPanel && uploadPanel.querySelector('[data-dropzone]');
    var fileInput = uploadPanel && uploadPanel.querySelector('[data-file-input]');
    var queueEl = uploadPanel && uploadPanel.querySelector('[data-upload-queue]');
    var maxImage = uploadPanel ? parseInt(uploadPanel.getAttribute('data-max-image'), 10) || 0 : 0;
    var maxVideo = uploadPanel ? parseInt(uploadPanel.getAttribute('data-max-video'), 10) || 0 : 0;
    var uploadUrl = uploadPanel ? uploadPanel.getAttribute('data-upload-url') : '';
    var pending = [];
    var running = false;

    function humanBytes(b) {
        if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
        if (b >= 1048576) return Math.round(b / 1048576) + ' MB';
        return Math.max(1, Math.round(b / 1024)) + ' KB';
    }

    function queueRow(name) {
        var li = document.createElement('li');
        li.className = 'upload-row';
        li.innerHTML = '<span class="upload-row-name"></span>'
            + '<span class="upload-row-bar"><span class="upload-row-fill"></span></span>'
            + '<span class="upload-row-state">wartet …</span>';
        li.querySelector('.upload-row-name').textContent = name;
        queueEl.appendChild(li);
        queueEl.hidden = false;
        return li;
    }

    function videoPoster(file) {
        return new Promise(function (resolve) {
            try {
                var url = URL.createObjectURL(file);
                var v = document.createElement('video');
                v.preload = 'metadata';
                v.muted = true;
                v.src = url;
                var done = function (blob) { URL.revokeObjectURL(url); resolve(blob); };
                v.addEventListener('error', function () { done(null); });
                v.addEventListener('loadeddata', function () {
                    try { v.currentTime = Math.min(0.5, (v.duration || 1) / 2); }
                    catch (e) { done(null); }
                });
                v.addEventListener('seeked', function () {
                    try {
                        var c = document.createElement('canvas');
                        c.width = v.videoWidth || 640;
                        c.height = v.videoHeight || 360;
                        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
                        c.toBlob(function (b) { done(b); }, 'image/jpeg', 0.8);
                    } catch (e) { done(null); }
                });
                setTimeout(function () { done(null); }, 8000);
            } catch (e) { resolve(null); }
        });
    }

    function enqueue(files) {
        Array.prototype.forEach.call(files, function (file) {
            var isVideo = file.type.indexOf('video/') === 0;
            var isImage = file.type.indexOf('image/') === 0
                || /\.(heic|heif)$/i.test(file.name);
            if (!isVideo && !isImage) { return; }
            var limit = isVideo ? maxVideo : maxImage;
            var row = queueRow(file.name);
            if (limit && file.size > limit) {
                row.querySelector('.upload-row-state').textContent = 'zu groß (max. ' + humanBytes(limit) + ')';
                row.classList.add('is-error');
                return;
            }
            pending.push({ file: file, row: row, isVideo: isVideo });
        });
        pump();
    }

    function pump() {
        if (running) return;
        var job = pending.shift();
        if (!job) return;
        running = true;

        var state = job.row.querySelector('.upload-row-state');
        var fill = job.row.querySelector('.upload-row-fill');
        state.textContent = 'lädt …';

        var send = function (poster) {
            var fd = new FormData();
            fd.append('gallery_id', galleryId);
            fd.append('file', job.file);
            if (poster) fd.append('poster', poster, 'poster.jpg');
            fd.append('_csrf', csrf);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl);
            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) fill.style.width = Math.round((e.loaded / e.total) * 100) + '%';
            });
            xhr.addEventListener('load', function () {
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = { ok: false }; }
                if (xhr.status >= 200 && xhr.status < 300 && res.ok) {
                    job.row.classList.add('is-done');
                    state.textContent = 'fertig';
                    fill.style.width = '100%';
                    addTile(res.media);
                    updateCount(1);
                    setTimeout(function () { job.row.remove(); if (!queueEl.children.length) queueEl.hidden = true; }, 1500);
                } else {
                    job.row.classList.add('is-error');
                    state.textContent = (res && res.error) || ('Fehler ' + xhr.status);
                }
                running = false;
                pump();
            });
            xhr.addEventListener('error', function () {
                job.row.classList.add('is-error');
                state.textContent = 'Netzwerkfehler';
                running = false;
                pump();
            });
            xhr.send(fd);
        };

        if (job.isVideo) {
            videoPoster(job.file).then(send);
        } else {
            send(null);
        }
    }

    function addTile(media) {
        if (!grid || !media) return;
        var li = document.createElement('li');
        li.className = 'media-item';
        li.setAttribute('data-media-item', '');
        li.setAttribute('data-media-id', media.id);
        li.setAttribute('data-kind', media.kind);
        li.setAttribute('data-full', media.full_url);
        li.setAttribute('data-download', media.download_url);
        var ic = function (d) { return '<span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24">' + d + '</svg></span>'; };
        var playD = '<path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l10.29-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z"/>';
        var imgD = '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v9.6l4.3-4.3a1 1 0 0 1 1.4 0l2.8 2.8l3.3-3.3a1 1 0 0 1 1.4 0L20 15V6H4Zm4.5 1a1.5 1.5 0 1 1 0 3a1.5 1.5 0 0 1 0-3Z"/>';
        var playIcon = media.kind === 'video' ? '<span class="media-play" aria-hidden="true">' + ic(playD) + '</span>' : '';
        var thumbInner = media.has_thumb
            ? '<img loading="lazy" alt="" src="' + media.thumb_url + '">'
            : '<span class="media-thumb-fallback">' + ic(media.kind === 'video' ? playD : imgD) + '</span>';
        li.innerHTML =
            '<button type="button" class="media-thumb" data-open-lightbox>'
            + thumbInner + playIcon + '</button>'
            + '<div class="media-meta">'
            + '<input type="text" class="media-caption" data-caption placeholder="Bildunterschrift …" maxlength="500" value="" aria-label="Bildunterschrift">'
            + '</div>'
            + '<div class="media-actions">'
            + '<button type="button" class="icon-button" data-set-cover title="Als Titelbild">' + ic('<path d="m12 3l2.8 5.7l6.2.9l-4.5 4.4l1.1 6.2L12 17.3L6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3Z"/>') + '</button>'
            + '<a class="icon-button" href="' + media.download_url + '" title="Herunterladen">' + ic('<path d="M11 3h2v8h3l-4 4l-4-4h3V3Zm-6 13h2v3h10v-3h2v3.5A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5V16Z"/>') + '</a>'
            + '<button type="button" class="icon-button is-danger" data-delete-media title="In den Papierkorb">' + ic('<path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm1 7h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2v8H7v-8Z"/>') + '</button>'
            + '</div>';
        grid.appendChild(li);
    }

    if (dropzone && fileInput) {
        var pick = uploadPanel.querySelector('[data-pick]');
        if (pick) pick.addEventListener('click', function () { fileInput.click(); });
        dropzone.addEventListener('click', function (e) {
            if (e.target === dropzone || e.target.tagName === 'P' || e.target.tagName === 'STRONG' || e.target.closest('svg')) {
                fileInput.click();
            }
        });
        dropzone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) enqueue(fileInput.files);
            fileInput.value = '';
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('is-over'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('is-over'); });
        });
        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) enqueue(e.dataTransfer.files);
        });
    }

    // ------------------------------------------------------- Kachel-Aktionen

    if (grid) {
        var captionUrl = grid.getAttribute('data-caption-url');
        var deleteUrl = grid.getAttribute('data-delete-url');
        var coverUrl = grid.getAttribute('data-cover-url');
        var reorderUrl = grid.getAttribute('data-reorder-url');

        var captionTimers = new WeakMap();
        grid.addEventListener('input', function (e) {
            var input = e.target.closest('[data-caption]');
            if (!input) return;
            var item = input.closest('[data-media-item]');
            clearTimeout(captionTimers.get(input));
            captionTimers.set(input, setTimeout(function () { saveCaption(item, input); }, 700));
        });
        grid.addEventListener('focusout', function (e) {
            var input = e.target.closest('[data-caption]');
            if (!input) return;
            clearTimeout(captionTimers.get(input));
            saveCaption(input.closest('[data-media-item]'), input);
        });

        function saveCaption(item, input) {
            if (!item) return;
            var fd = new FormData();
            fd.append('media_id', item.getAttribute('data-media-id'));
            fd.append('caption', input.value);
            input.classList.add('is-saving');
            post(captionUrl, fd).then(function () { input.classList.remove('is-saving'); input.classList.add('is-saved'); setTimeout(function () { input.classList.remove('is-saved'); }, 1200); });
        }

        grid.addEventListener('click', function (e) {
            var item = e.target.closest('[data-media-item]');
            if (!item) return;

            if (e.target.closest('[data-delete-media]')) {
                if (!window.confirm('Dieses Medium in den Papierkorb legen?')) return;
                var fd = new FormData();
                fd.append('media_id', item.getAttribute('data-media-id'));
                post(deleteUrl, fd).then(function (res) {
                    if (res.ok) { item.remove(); updateCount(-1); }
                    else window.alert(res.error || 'Löschen fehlgeschlagen.');
                });
                return;
            }
            if (e.target.closest('[data-set-cover]')) {
                var cfd = new FormData();
                cfd.append('media_id', item.getAttribute('data-media-id'));
                post(coverUrl, cfd).then(function (res) {
                    if (res.ok) {
                        grid.querySelectorAll('.media-item.is-cover').forEach(function (n) { n.classList.remove('is-cover'); });
                        item.classList.add('is-cover');
                    }
                });
                return;
            }
            if (e.target.closest('[data-open-lightbox]')) {
                openLightbox(item);
            }
        });

        // -------------------------------------------------- Drag-Sortierung
        if (grid.classList.contains('is-sortable')) {
            var dragEl = null;
            grid.addEventListener('dragstart', function (e) {
                dragEl = e.target.closest('[data-media-item]');
                if (dragEl) dragEl.classList.add('is-dragging');
            });
            grid.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
                persistOrder();
            });
            grid.addEventListener('dragover', function (e) {
                e.preventDefault();
                var over = e.target.closest('[data-media-item]');
                if (!over || over === dragEl || !dragEl) return;
                var rect = over.getBoundingClientRect();
                var after = (e.clientY - rect.top) / rect.height > 0.5;
                grid.insertBefore(dragEl, after ? over.nextSibling : over);
            });
            function persistOrder() {
                var ids = Array.prototype.map.call(grid.querySelectorAll('[data-media-item]'), function (n) { return n.getAttribute('data-media-id'); });
                var fd = new FormData();
                fd.append('gallery_id', galleryId);
                ids.forEach(function (id) { fd.append('order[]', id); });
                post(reorderUrl, fd);
            }
        }
    }

    // -------------------------------------------------------------- Lightbox

    var lb = document.querySelector('[data-lightbox]');
    var lbStage = lb && lb.querySelector('[data-lb-stage]');
    var lbCaption = lb && lb.querySelector('[data-lb-caption]');
    var lbList = [];
    var lbIndex = 0;

    function openLightbox(item) {
        lbList = Array.prototype.slice.call(grid.querySelectorAll('[data-media-item]'));
        lbIndex = lbList.indexOf(item);
        renderLightbox();
        lb.hidden = false;
        document.body.classList.add('lightbox-open');
    }
    function closeLightbox() {
        lb.hidden = true;
        lbStage.innerHTML = '';
        document.body.classList.remove('lightbox-open');
    }
    function renderLightbox() {
        var item = lbList[lbIndex];
        if (!item) return;
        var kind = item.getAttribute('data-kind');
        var full = item.getAttribute('data-full');
        var dl = item.getAttribute('data-download');
        var caption = (item.querySelector('[data-caption]') || {}).value || '';
        lbStage.innerHTML = '';
        if (kind === 'video') {
            var v = document.createElement('video');
            v.src = full; v.controls = true; v.autoplay = true; v.playsInline = true;
            lbStage.appendChild(v);
        } else {
            var img = document.createElement('img');
            img.src = full; img.alt = caption;
            lbStage.appendChild(img);
        }
        lbCaption.innerHTML = '';
        if (caption) { var s = document.createElement('span'); s.textContent = caption; lbCaption.appendChild(s); }
        var a = document.createElement('a');
        a.href = dl; a.className = 'lightbox-download'; a.textContent = 'Herunterladen';
        lbCaption.appendChild(a);
    }
    function step(d) {
        if (!lbList.length) return;
        lbIndex = (lbIndex + d + lbList.length) % lbList.length;
        renderLightbox();
    }
    if (lb) {
        lb.querySelector('[data-lb-close]').addEventListener('click', closeLightbox);
        lb.querySelector('[data-lb-prev]').addEventListener('click', function () { step(-1); });
        lb.querySelector('[data-lb-next]').addEventListener('click', function () { step(1); });
        lb.addEventListener('click', function (e) { if (e.target === lb) closeLightbox(); });
        document.addEventListener('keydown', function (e) {
            if (lb.hidden) return;
            if (e.key === 'Escape') closeLightbox();
            else if (e.key === 'ArrowLeft') step(-1);
            else if (e.key === 'ArrowRight') step(1);
        });
    }
})();
