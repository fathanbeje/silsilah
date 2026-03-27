@extends('layouts.app')

@section('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/family-display.css') }}">
<link rel="stylesheet" href="{{ secure_asset('css/user-edit-requests.css') }}">
@if ($allowPublicEditSuggestions)
<link rel="stylesheet" href="{{ secure_asset('css/plugins/select2.min.css') }}">
@endif
@endsection

@section('content')
@php($hasPublicFamilyScope = app(\App\Services\FamilyScopeResolver::class)->hasActiveScope())
<h2 class="page-header">
    {{ $user->display_name }} <small>{{ trans('app.family_chart') }}</small>
    <span class="pull-right">
        @if ($hasPublicFamilyScope)
        <a href="{{ route('deaths.index') }}" class="btn btn-default">
            Database Wafat
        </a>
        @endif
        <a href="{{ route('users.tree', $user) }}" class="btn btn-default">
            {{ trans('app.show_family_tree') }}
        </a>
    </span>
</h2>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

@guest
    @include('users.partials.public-claim-card')
@endguest

@if ($allowPublicEditSuggestions)
<section class="public-chart-hero">
    <div>
        <div class="public-chart-hero__title">Perlu koreksi data keluarga?</div>
        <p class="public-chart-hero__lead">
            Gunakan tombol ini untuk mengusulkan perubahan profil, menambah pasangan, atau menambah anak.
            Anda juga bisa langsung klik nama anggota keluarga yang tampil bergaris bawah.
        </p>
        <div class="public-chart-helper">
            <span class="public-chart-helper__dot"></span>
            Semua usulan akan ditinjau admin sebelum tampil di data live.
        </div>
    </div>
    <div class="public-chart-hero__actions">
        <a
            href="{{ route('user-edit-requests.create', $user) }}"
            class="public-chart-edit-cta js-public-edit-trigger"
            data-edit-form-url="{{ route('user-edit-requests.create', $user) }}"
            data-user-name="{{ $user->display_name }}"
        >
            <span>Usulkan Perubahan Data</span>
        </a>
    </div>
</section>
@endif

@include('users.partials.chart-content')

@if ($allowPublicEditSuggestions)
<div class="modal fade" id="public-edit-request-modal" tabindex="-1" role="dialog" aria-labelledby="public-edit-request-modal-label">
    <div class="modal-dialog modal-lg public-edit-request-modal" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="public-edit-request-modal-label">Usulkan Perubahan Data</h4>
            </div>
            <div class="modal-body" id="public-edit-request-modal-body">
                <div class="text-center text-muted" style="padding:30px 0;">Memuat formulir...</div>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@section('script')
@if ($allowPublicEditSuggestions)
<script src="{{ secure_asset('js/plugins/select2.min.js') }}"></script>
<script>
    (function () {
        var modal = document.getElementById('public-edit-request-modal');
        var modalBody = document.getElementById('public-edit-request-modal-body');
        var modalState = {
            activeTrigger: null,
            form: null,
            dirty: false,
            allowClose: false,
            saveDraftTimer: null,
            currentStep: 1,
            initialSnapshot: '',
            photoPreviewUrl: null,
            photoCropObjectUrl: null,
            photoCrop: null
        };

        if (!modal || !modalBody) {
            return;
        }

        function escapeSelector(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }

            return String(value).replace(/["\\]/g, '\\$&');
        }

        function createDraftKey(form) {
            return ['public-edit-request-draft', window.location.host, form.getAttribute('data-user-id') || 'unknown'].join(':');
        }

        function normalizeFieldName(name) {
            return String(name || '')
                .replace(/\.(\d+)\./g, '[$1][')
                .replace(/\.(\d+)$/g, '[$1]')
                .replace(/\./g, '][') + (String(name || '').indexOf('[') === -1 && String(name || '').indexOf('.') > -1 ? ']' : '');
        }

        function fieldNameCandidates(name) {
            if (!name) {
                return [];
            }

            var normalized = normalizeFieldName(name);
            var shortName = String(name).replace(/\.\d+\./g, '.').replace(/\.\d+$/g, '');
            return [name, normalized, shortName].filter(function (value, index, list) {
                return value && list.indexOf(value) === index;
            });
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[char];
            });
        }

        function stepPanels(form) {
            return Array.prototype.slice.call(form.querySelectorAll('[data-step-panel]'));
        }

        function stepTabs(form) {
            return Array.prototype.slice.call(form.querySelectorAll('[data-step-target]'));
        }

        function focusFirstInput(container) {
            var field = container && container.querySelector('input:not([type="hidden"]):not([type="checkbox"]), select, textarea');
            if (field) {
                window.setTimeout(function () {
                    field.focus();
                }, 60);
            }
        }

        function flashCard(card) {
            if (!card) {
                return;
            }

            card.classList.add('is-highlighted');
            window.setTimeout(function () {
                card.classList.remove('is-highlighted');
            }, 1400);
        }

        function scrollIntoViewWithin(container, target) {
            if (!container || !target) {
                return;
            }

            var containerRect = container.getBoundingClientRect();
            var targetRect = target.getBoundingClientRect();

            container.scrollTo({
                top: Math.max(0, container.scrollTop + (targetRect.top - containerRect.top) - 18),
                behavior: 'smooth'
            });
        }

        function getStepScrollContainer(form) {
            return modalBody || (form && form.querySelector('[data-public-edit-stepper-body]'));
        }

        function collectDraftData(form) {
            var payload = {};

            Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (field) {
                if (!field.name || field.type === 'file' || field.disabled) {
                    return;
                }

                if (field.type === 'checkbox') {
                    payload[field.name] = field.checked ? field.value : '';
                    return;
                }

                if ((field.type === 'radio' && field.checked) || field.type !== 'radio') {
                    payload[field.name] = field.value;
                }
            });

            return payload;
        }

        function serializeDraft(form) {
            return JSON.stringify(collectDraftData(form));
        }

        function saveDraft(form) {
            try {
                window.localStorage.setItem(createDraftKey(form), serializeDraft(form));
            } catch (error) {}
        }

        function clearDraft(form) {
            try {
                window.localStorage.removeItem(createDraftKey(form));
            } catch (error) {}
        }

        function revokeObjectUrl(key) {
            if (modalState[key]) {
                window.URL.revokeObjectURL(modalState[key]);
                modalState[key] = null;
            }
        }

        function loadDraft(form) {
            try {
                return JSON.parse(window.localStorage.getItem(createDraftKey(form)) || 'null');
            } catch (error) {
                return null;
            }
        }

        function applyDraft(form, draft) {
            if (!draft || typeof draft !== 'object') {
                return;
            }

            Object.keys(draft).forEach(function (name) {
                var value = draft[name];
                var fields = form.querySelectorAll('[name="' + escapeSelector(name) + '"]');

                Array.prototype.forEach.call(fields, function (field) {
                    if (field.type === 'checkbox') {
                        field.checked = String(value || '') === String(field.value);
                        return;
                    }

                    if (field.type === 'radio') {
                        field.checked = field.value === value;
                        return;
                    }

                    field.value = value;
                });
            });
        }

        function setDirty(form) {
            var snapshot = serializeDraft(form);
            var photoField = form.querySelector('input[type="file"][name="photo"]');
            modalState.dirty = snapshot !== modalState.initialSnapshot || !!(photoField && photoField.value);
        }

        function scheduleDraftSave(form) {
            window.clearTimeout(modalState.saveDraftTimer);
            modalState.saveDraftTimer = window.setTimeout(function () {
                saveDraft(form);
            }, 180);
        }

        function clearFieldErrors(form) {
            Array.prototype.forEach.call(form.querySelectorAll('.form-group.has-error'), function (group) {
                group.classList.remove('has-error');
            });

            Array.prototype.forEach.call(form.querySelectorAll('[data-field-error]'), function (node) {
                node.classList.remove('is-visible');
                node.textContent = '';
            });
        }

        function markFieldError(form, key, messages) {
            fieldNameCandidates(key).forEach(function (candidate) {
                var field = form.querySelector('[name="' + escapeSelector(candidate) + '"]');
                var errorBox = form.querySelector('[data-field-error="' + candidate + '"]');

                if (!field || !errorBox) {
                    return;
                }

                var group = field.closest('.form-group');
                if (group) {
                    group.classList.add('has-error');
                }

                errorBox.classList.add('is-visible');
                errorBox.textContent = (messages || []).join(' ');
            });
        }

        function fieldStepForKey(key) {
            if (!key) {
                return 1;
            }

            if (/^requester_/.test(key)) {
                return 3;
            }

            if (/^(new_spouses|new_children)/.test(key)) {
                return 2;
            }

            return 1;
        }

        function showErrors(form, errors) {
            var errorBox = form.querySelector('[data-public-edit-error]');
            var messages = [];
            var firstStep = null;
            var firstField = null;

            clearFieldErrors(form);

            Object.keys(errors || {}).forEach(function (field) {
                var fieldMessages = errors[field] || [];
                fieldMessages.forEach(function (message) {
                    messages.push(message);
                });

                markFieldError(form, field, fieldMessages);

                if (!firstStep) {
                    firstStep = fieldStepForKey(field);
                }

                if (!firstField) {
                    fieldNameCandidates(field).some(function (candidate) {
                        var target = form.querySelector('[name="' + escapeSelector(candidate) + '"]');
                        if (target) {
                            firstField = target;
                            return true;
                        }

                        return false;
                    });
                }
            });

            if (!messages.length) {
                errorBox.style.display = 'none';
                errorBox.innerHTML = '';
                return;
            }

            errorBox.innerHTML = '<strong>Masih ada bagian yang perlu diperbaiki.</strong><br>' + messages.map(escapeHtml).join('<br>');
            errorBox.style.display = 'block';

            if (firstStep) {
                setStep(form, firstStep);
            }

            if (firstField) {
                window.setTimeout(function () {
                    firstField.focus();
                    scrollIntoViewWithin(getStepScrollContainer(form), firstField.closest('.form-group') || firstField);
                }, 120);
            }
        }

        function updateSummary(form) {
            var list = form.querySelector('[data-public-edit-summary]');
            if (!list) {
                return;
            }

            var items = [];
            var nickname = (form.querySelector('[name="nickname"]') || {}).value || '';
            var spouseCount = form.querySelectorAll('[data-spouse-item]').length;
            var childCount = form.querySelectorAll('[data-child-item]').length;
            var requesterName = (form.querySelector('[name="requester_name"]') || {}).value || '';

            if (nickname && nickname !== @json($user->nickname)) {
                items.push('Nama panggilan diusulkan menjadi ' + nickname + '.');
            }

            if (spouseCount) {
                items.push(spouseCount + ' pasangan baru ditambahkan pada usulan ini.');
            }

            if (childCount) {
                items.push(childCount + ' anak baru ditambahkan pada usulan ini.');
            }

            if ((form.querySelector('[name="is_deceased"]') || {}).checked) {
                items.push('Status wafat turut diperbarui atau dikonfirmasi.');
            }

            if (requesterName) {
                items.push('Admin dapat menghubungi pengaju melalui identitas yang diisi di langkah ini.');
            }

            list.innerHTML = items.length
                ? items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('')
                : '<li>Belum ada perubahan yang terdeteksi.</li>';
        }

        function updateScrollHint(form) {
            var body = getStepScrollContainer(form);
            var hint = form.querySelector('[data-public-edit-scroll-hint]');

            if (!body || !hint) {
                return;
            }

            var needsScroll = body.scrollHeight > body.clientHeight + 12;
            var nearBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 16;
            hint.classList.toggle('is-hidden', !needsScroll || nearBottom);
        }

        function renderStepUi(form) {
            stepTabs(form).forEach(function (tab) {
                var target = Number(tab.getAttribute('data-step-target'));
                tab.classList.toggle('is-active', target === modalState.currentStep);
                tab.classList.toggle('is-complete', target < modalState.currentStep);
                tab.setAttribute('aria-current', target === modalState.currentStep ? 'step' : 'false');
            });

            stepPanels(form).forEach(function (panel) {
                panel.classList.toggle('is-active', Number(panel.getAttribute('data-step-panel')) === modalState.currentStep);
            });

            var backButton = form.querySelector('[data-step-back]');
            var nextButton = form.querySelector('[data-step-next]');
            var submitButton = form.querySelector('[data-step-submit]');
            if (backButton) backButton.style.display = modalState.currentStep > 1 ? '' : 'none';
            if (nextButton) nextButton.style.display = modalState.currentStep < 3 ? '' : 'none';
            if (submitButton) submitButton.style.display = modalState.currentStep === 3 ? '' : 'none';

            updateSummary(form);
            var body = getStepScrollContainer(form);
            if (body) {
                body.scrollTop = 0;
            }
            updateScrollHint(form);
        }

        function setStep(form, step) {
            modalState.currentStep = Math.max(1, Math.min(3, Number(step) || 1));
            renderStepUi(form);
        }

        function currentNewSpouses(form) {
            return Array.prototype.slice.call(form.querySelectorAll('[data-spouse-item]')).map(function (item) {
                var nameInput = item.querySelector('[data-spouse-name-input]');
                return {
                    value: 'new:' + item.getAttribute('data-request-key'),
                    label: nameInput ? nameInput.value.trim() : ''
                };
            }).filter(function (item) {
                return item.label;
            });
        }

        function readExistingSpouseOptions(form) {
            var jsonNode = form.querySelector('[data-existing-spouses-json]');
            if (!jsonNode) {
                return [];
            }

            try {
                return JSON.parse(jsonNode.textContent || '[]');
            } catch (error) {
                return [];
            }
        }

        function buildSpouseOptions(form, selectedValue) {
            var existing = readExistingSpouseOptions(form);

            var options = ['<option value="none">Belum / tanpa pasangan tercatat</option>'];
            existing.concat(currentNewSpouses(form)).forEach(function (item) {
                var selected = item.value === selectedValue ? ' selected' : '';
                options.push('<option value="' + escapeHtml(item.value) + '"' + selected + '>' + escapeHtml(item.label) + '</option>');
            });

            return options.join('');
        }

        function syncChildSpouseOptions(form) {
            Array.prototype.forEach.call(form.querySelectorAll('[data-child-spouse-context]'), function (select) {
                var current = select.value || 'none';
                select.innerHTML = buildSpouseOptions(form, current);
            });
        }

        function toggleAdvanced(form, forceOpen) {
            var body = form.querySelector('[data-advanced-body]');
            var button = form.querySelector('[data-advanced-toggle]');
            if (!body || !button) {
                return;
            }

            var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !body.classList.contains('is-open');
            body.classList.toggle('is-open', shouldOpen);
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            var icon = button.querySelector('.public-edit-request-card__advanced-toggle-icon');
            if (icon) {
                icon.textContent = shouldOpen ? '−' : '+';
            }
        }

        function setDeceasedVisibility(form, checked) {
            var body = form.querySelector('[data-deceased-fields]');
            if (body) {
                body.classList.toggle('is-visible', checked);
            }

            if (checked) {
                toggleAdvanced(form, true);
            }
        }

        function initializeSelectEnhancements(form) {
            if (!window.jQuery || !window.jQuery.fn.select2) {
                return;
            }

            window.jQuery(form).find('.js-cemetery-location-select').select2({
                width: '100%',
                placeholder: 'Pilih lokasi makam yang sudah ada',
                allowClear: true,
                dropdownParent: window.jQuery(modal)
            });
        }

        function applyCemeteryLocation(form, payload) {
            var fields = {
                name: form.querySelector('[name="cemetery_location_name"]'),
                address: form.querySelector('[name="cemetery_location_address"]'),
                latitude: form.querySelector('[name="cemetery_location_latitude"]'),
                longitude: form.querySelector('[name="cemetery_location_longitude"]')
            };

            fields.name && (fields.name.value = payload && payload.name ? payload.name : '');
            fields.address && (fields.address.value = payload && payload.address ? payload.address : '');
            fields.latitude && (fields.latitude.value = payload && payload.latitude ? payload.latitude : '');
            fields.longitude && (fields.longitude.value = payload && payload.longitude ? payload.longitude : '');
        }

        function formatFileSize(size) {
            if (size < 1024) return size + ' B';
            if (size < 1024 * 1024) return Math.round(size / 1024) + ' KB';
            return (size / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function dataURLToBlob(dataUrl) {
            var parts = dataUrl.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var binary = atob(parts[1]);
            var length = binary.length;
            var bytes = new Uint8Array(length);

            for (var i = 0; i < length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            return new Blob([bytes], { type: mime });
        }

        function showPhotoPreview(form, file) {
            var preview = form.querySelector('[data-photo-preview]');
            var image = form.querySelector('[data-photo-preview-image]');
            var meta = form.querySelector('[data-photo-preview-meta]');
            if (!preview || !image || !meta || !file) {
                return;
            }

            revokeObjectUrl('photoPreviewUrl');
            modalState.photoPreviewUrl = window.URL.createObjectURL(file);
            image.src = modalState.photoPreviewUrl;
            meta.innerHTML = '<strong>' + escapeHtml(file.name) + '</strong><br>Ukuran siap kirim: ' + escapeHtml(formatFileSize(file.size));
            preview.style.display = 'flex';
        }

        function hidePhotoPreview(form) {
            var preview = form.querySelector('[data-photo-preview]');
            var image = form.querySelector('[data-photo-preview-image]');
            if (preview) {
                preview.style.display = 'none';
            }
            if (image) {
                image.removeAttribute('src');
            }
            revokeObjectUrl('photoPreviewUrl');
        }

        function renderPhotoCropCanvas(form) {
            var cropper = form.querySelector('[data-photo-cropper]');
            var canvas = form.querySelector('[data-photo-crop-canvas]');
            if (!cropper || !canvas || !modalState.photoCrop || !modalState.photoCrop.image) {
                return;
            }

            var context = canvas.getContext('2d');
            var state = modalState.photoCrop;
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = '#eaf1f6';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(
                state.image,
                state.offsetX,
                state.offsetY,
                state.image.width * state.scale,
                state.image.height * state.scale
            );
        }

        function resetPhotoCropper(form, clearInput) {
            var cropper = form.querySelector('[data-photo-cropper]');
            var input = form.querySelector('[data-photo-input]');
            var zoom = form.querySelector('[data-photo-crop-zoom]');
            var canvas = form.querySelector('[data-photo-crop-canvas]');

            if (cropper) {
                cropper.style.display = 'none';
            }
            if (zoom) {
                zoom.value = 100;
            }
            if (canvas) {
                canvas.classList.remove('is-dragging');
            }
            modalState.photoCrop = null;
            revokeObjectUrl('photoCropObjectUrl');

            if (clearInput && input) {
                input.value = '';
                hidePhotoPreview(form);
            }
        }

        function openPhotoCropper(form, file) {
            var cropper = form.querySelector('[data-photo-cropper]');
            var canvas = form.querySelector('[data-photo-crop-canvas]');
            var zoom = form.querySelector('[data-photo-crop-zoom]');
            if (!cropper || !canvas || !zoom || !file) {
                return;
            }

            revokeObjectUrl('photoCropObjectUrl');
            modalState.photoCropObjectUrl = window.URL.createObjectURL(file);

            var image = new Image();
            image.onload = function () {
                var baseScale = Math.max(canvas.width / image.width, canvas.height / image.height);

                modalState.photoCrop = {
                    fileName: file.name || 'foto-usulan',
                    image: image,
                    scale: baseScale,
                    minScale: baseScale,
                    maxScale: baseScale * 4,
                    offsetX: (canvas.width - (image.width * baseScale)) / 2,
                    offsetY: (canvas.height - (image.height * baseScale)) / 2,
                    dragStartX: 0,
                    dragStartY: 0,
                    originX: 0,
                    originY: 0
                };

                zoom.value = 100;
                cropper.style.display = 'block';
                renderPhotoCropCanvas(form);
                scrollIntoViewWithin(getStepScrollContainer(form), cropper);
            };
            image.onerror = function () {
                resetPhotoCropper(form, true);
            };
            image.src = modalState.photoCropObjectUrl;
        }

        function applyPhotoCrop(form) {
            var input = form.querySelector('[data-photo-input]');
            var cropper = form.querySelector('[data-photo-cropper]');
            var canvas = form.querySelector('[data-photo-crop-canvas]');
            if (!input || !cropper || !canvas || !modalState.photoCrop || !modalState.photoCrop.image) {
                return;
            }

            var outputCanvas = document.createElement('canvas');
            var outputSize = 800;
            var state = modalState.photoCrop;
            outputCanvas.width = outputSize;
            outputCanvas.height = outputSize;

            var outputContext = outputCanvas.getContext('2d');
            outputContext.fillStyle = '#ffffff';
            outputContext.fillRect(0, 0, outputSize, outputSize);

            var scaleRatio = outputSize / canvas.width;
            outputContext.drawImage(
                state.image,
                state.offsetX * scaleRatio,
                state.offsetY * scaleRatio,
                state.image.width * state.scale * scaleRatio,
                state.image.height * state.scale * scaleRatio
            );

            var quality = 0.85;
            var blob = null;
            do {
                blob = dataURLToBlob(outputCanvas.toDataURL('image/jpeg', quality));
                if (blob.size <= 200 * 1024 || quality <= 0.45) {
                    break;
                }
                quality -= 0.05;
            } while (quality >= 0.45);

            var baseName = (state.fileName || 'foto-usulan').replace(/\.[^.]+$/, '') || 'foto-usulan';
            var croppedFile = new File([blob], baseName + '.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now()
            });

            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            input.files = dataTransfer.files;

            showPhotoPreview(form, croppedFile);
            cropper.style.display = 'none';
            revokeObjectUrl('photoCropObjectUrl');
            modalState.photoCrop = null;
            setDirty(form);
        }

        function spouseTemplate(index) {
            var key = 'spouse_' + index;
            return '' +
                '<div class="request-repeat-card" data-spouse-item data-request-key="' + key + '">' +
                    '<input type="hidden" name="new_spouses[' + index + '][request_key]" value="' + key + '">' +
                    '<button type="button" class="btn btn-link btn-xs request-repeat-card__remove" data-remove-repeat aria-label="Hapus pasangan">&times;</button>' +
                    '<div class="row">' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama pasangan <span class="text-danger">*</span></label><input data-spouse-name-input type="text" name="new_spouses[' + index + '][name]" class="form-control"><div class="public-edit-request-field-error" data-field-error="new_spouses[' + index + '][name]"></div></div></div>' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama panggilan</label><input type="text" name="new_spouses[' + index + '][nickname]" class="form-control"><div class="public-edit-request-field-error" data-field-error="new_spouses[' + index + '][nickname]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tanggal nikah</label><input type="date" name="new_spouses[' + index + '][marriage_date]" class="form-control"><div class="public-edit-request-field-help">Isi jika tanggal lengkap diketahui.</div><div class="public-edit-request-field-error" data-field-error="new_spouses[' + index + '][marriage_date]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tanggal lahir</label><input type="date" name="new_spouses[' + index + '][dob]" class="form-control"><div class="public-edit-request-field-help">Isi jika tanggal lengkap diketahui.</div><div class="public-edit-request-field-error" data-field-error="new_spouses[' + index + '][dob]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tahun lahir</label><input type="text" name="new_spouses[' + index + '][yob]" class="form-control" placeholder="YYYY" inputmode="numeric"><div class="public-edit-request-field-error" data-field-error="new_spouses[' + index + '][yob]"></div></div></div>' +
                    '</div>' +
                '</div>';
        }

        function childTemplate(form, index) {
            return '' +
                '<div class="request-repeat-card" data-child-item>' +
                    '<button type="button" class="btn btn-link btn-xs request-repeat-card__remove" data-remove-repeat aria-label="Hapus anak">&times;</button>' +
                    '<div class="row">' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama anak <span class="text-danger">*</span></label><input type="text" name="new_children[' + index + '][name]" class="form-control"><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][name]"></div></div></div>' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama panggilan</label><input type="text" name="new_children[' + index + '][nickname]" class="form-control"><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][nickname]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Jenis kelamin <span class="text-danger">*</span></label><select name="new_children[' + index + '][gender_id]" class="form-control"><option value="">Pilih jenis kelamin</option><option value="1">Laki-laki</option><option value="2">Perempuan</option></select><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][gender_id]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Anak ke berapa</label><input type="number" min="1" name="new_children[' + index + '][birth_order]" class="form-control"><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][birth_order]"></div></div></div>' +
                        '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Anak ini merupakan anak dari pasangan</label><select data-child-spouse-context name="new_children[' + index + '][spouse_context]" class="form-control">' + buildSpouseOptions(form, 'none') + '</select><div class="public-edit-request-field-help">Pilih pasangan yang terkait dengan anak ini. Jika pasangan belum tercatat, pilih opsi tanpa pasangan.</div><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][spouse_context]"></div></div></div>' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Tanggal lahir</label><input type="date" name="new_children[' + index + '][dob]" class="form-control"><div class="public-edit-request-field-help">Isi jika tanggal lengkap diketahui.</div><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][dob]"></div></div></div>' +
                        '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Tahun lahir</label><input type="text" name="new_children[' + index + '][yob]" class="form-control" placeholder="YYYY" inputmode="numeric"><div class="public-edit-request-field-error" data-field-error="new_children[' + index + '][yob]"></div></div></div>' +
                    '</div>' +
                '</div>';
        }

        function maybeRestoreDraft(form) {
            var banner = form.querySelector('[data-public-edit-restore]');
            var draft = loadDraft(form);
            if (!draft || JSON.stringify(draft) === modalState.initialSnapshot) {
                if (banner) {
                    banner.style.display = 'none';
                    banner.innerHTML = '';
                }
                return;
            }

            if (window.confirm('Draft usulan sebelumnya ditemukan. Pulihkan isi yang belum sempat dikirim?')) {
                applyDraft(form, draft);
                syncChildSpouseOptions(form);
                setDeceasedVisibility(form, !!(form.querySelector('[name="is_deceased"]') || {}).checked);
                updateSummary(form);
                setDirty(form);
                if (banner) {
                    banner.style.display = 'block';
                    banner.innerHTML = 'Draft sebelumnya dipulihkan untuk ' + escapeHtml(form.getAttribute('data-user-name')) + '.';
                }
            } else {
                clearDraft(form);
            }
        }

        function renderSuccess(message) {
            modalBody.innerHTML = '' +
                '<div class="public-edit-request-success">' +
                    '<div class="public-edit-request-success__badge">✓</div>' +
                    '<h4>Usulan berhasil dikirim</h4>' +
                    '<p>' + escapeHtml(message || 'Usulan perubahan berhasil dikirim dan sedang menunggu peninjauan admin.') + '</p>' +
                    '<div class="public-edit-request-success__actions">' +
                        '<button type="button" class="btn btn-primary" data-success-close>Tutup dan muat ulang</button>' +
                    '</div>' +
                '</div>';

            var closeButton = modalBody.querySelector('[data-success-close]');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    modalState.allowClose = true;
                    window.location.reload();
                });
            }
        }

        function bindDynamicForm(container) {
            var form = container.querySelector('form[data-public-edit-request-form]');
            if (!form) {
                return;
            }

            modalState.form = form;
            modalState.currentStep = 1;
            modalState.allowClose = false;

            var stepBody = getStepScrollContainer(form);
            var spouseList = form.querySelector('[data-spouse-list]');
            var childList = form.querySelector('[data-child-list]');
            var spouseIndex = spouseList ? spouseList.children.length : 0;
            var childIndex = childList ? childList.children.length : 0;
            var addSpouseButton = form.querySelector('[data-add-spouse]');
            var addChildButton = form.querySelector('[data-add-child]');
            var deceasedToggle = form.querySelector('[data-toggle-deceased]');
            var photoInput = form.querySelector('[data-photo-input]');
            var photoCanvas = form.querySelector('[data-photo-crop-canvas]');
            var photoZoom = form.querySelector('[data-photo-crop-zoom]');

            initializeSelectEnhancements(form);
            modalState.initialSnapshot = serializeDraft(form);
            maybeRestoreDraft(form);
            modalState.initialSnapshot = serializeDraft(form);
            renderStepUi(form);
            if (deceasedToggle) {
                setDeceasedVisibility(form, deceasedToggle.checked);
            }

            if (addSpouseButton && spouseList) {
                addSpouseButton.addEventListener('click', function () {
                    spouseList.insertAdjacentHTML('beforeend', spouseTemplate(spouseIndex++));
                    var card = spouseList.lastElementChild;
                    syncChildSpouseOptions(form);
                    flashCard(card);
                    scrollIntoViewWithin(stepBody, card);
                    focusFirstInput(card);
                    setDirty(form);
                    scheduleDraftSave(form);
                    updateSummary(form);
                });

                spouseList.addEventListener('input', function (event) {
                    if (event.target.matches('[data-spouse-name-input]')) {
                        syncChildSpouseOptions(form);
                    }
                });
            }

            if (addChildButton && childList) {
                addChildButton.addEventListener('click', function () {
                    childList.insertAdjacentHTML('beforeend', childTemplate(form, childIndex++));
                    var card = childList.lastElementChild;
                    syncChildSpouseOptions(form);
                    flashCard(card);
                    scrollIntoViewWithin(stepBody, card);
                    focusFirstInput(card);
                    setDirty(form);
                    scheduleDraftSave(form);
                    updateSummary(form);
                });
            }

            if (photoInput) {
                photoInput.addEventListener('change', function (event) {
                    var file = event.target.files && event.target.files[0];
                    hidePhotoPreview(form);

                    if (!file) {
                        resetPhotoCropper(form, false);
                        setDirty(form);
                        return;
                    }

                    if (!/^image\//.test(file.type || '')) {
                        resetPhotoCropper(form, true);
                        return;
                    }

                    openPhotoCropper(form, file);
                    setDirty(form);
                });
            }

            if (photoZoom) {
                photoZoom.addEventListener('input', function (event) {
                    if (!modalState.photoCrop) {
                        return;
                    }

                    var state = modalState.photoCrop;
                    var nextScale = state.minScale * (Number(event.target.value || 100) / 100);
                    var centerX = photoCanvas.width / 2;
                    var centerY = photoCanvas.height / 2;
                    var imageCenterX = (centerX - state.offsetX) / state.scale;
                    var imageCenterY = (centerY - state.offsetY) / state.scale;

                    state.scale = Math.min(state.maxScale, Math.max(state.minScale, nextScale));
                    state.offsetX = centerX - (imageCenterX * state.scale);
                    state.offsetY = centerY - (imageCenterY * state.scale);
                    renderPhotoCropCanvas(form);
                });
            }

            if (photoCanvas) {
                var startDrag = function (pointX, pointY) {
                    if (!modalState.photoCrop) {
                        return;
                    }

                    modalState.photoCrop.dragStartX = pointX;
                    modalState.photoCrop.dragStartY = pointY;
                    modalState.photoCrop.originX = modalState.photoCrop.offsetX;
                    modalState.photoCrop.originY = modalState.photoCrop.offsetY;
                    photoCanvas.classList.add('is-dragging');
                };

                var moveDrag = function (pointX, pointY) {
                    if (!modalState.photoCrop || !photoCanvas.classList.contains('is-dragging')) {
                        return;
                    }

                    modalState.photoCrop.offsetX = modalState.photoCrop.originX + (pointX - modalState.photoCrop.dragStartX);
                    modalState.photoCrop.offsetY = modalState.photoCrop.originY + (pointY - modalState.photoCrop.dragStartY);
                    renderPhotoCropCanvas(form);
                };

                var endDrag = function () {
                    photoCanvas.classList.remove('is-dragging');
                };

                photoCanvas.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    startDrag(event.clientX, event.clientY);
                });

                photoCanvas.addEventListener('mousemove', function (event) {
                    moveDrag(event.clientX, event.clientY);
                });

                photoCanvas.addEventListener('mouseup', endDrag);
                photoCanvas.addEventListener('mouseleave', endDrag);

                photoCanvas.addEventListener('touchstart', function (event) {
                    if (!event.touches.length) {
                        return;
                    }
                    startDrag(event.touches[0].clientX, event.touches[0].clientY);
                }, { passive: true });

                photoCanvas.addEventListener('touchmove', function (event) {
                    if (!event.touches.length) {
                        return;
                    }
                    moveDrag(event.touches[0].clientX, event.touches[0].clientY);
                }, { passive: true });

                photoCanvas.addEventListener('touchend', endDrag, { passive: true });
            }

            form.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-remove-repeat]');
                var stepButton = event.target.closest('[data-step-target]');
                var nextButton = event.target.closest('[data-step-next]');
                var backButton = event.target.closest('[data-step-back]');
                var advancedButton = event.target.closest('[data-advanced-toggle]');
                var photoCropCancel = event.target.closest('[data-photo-crop-cancel]');
                var photoCropApply = event.target.closest('[data-photo-crop-apply]');

                if (removeButton) {
                    event.preventDefault();
                    var card = removeButton.closest('.request-repeat-card');
                    if (card) {
                        card.remove();
                        syncChildSpouseOptions(form);
                        setDirty(form);
                        scheduleDraftSave(form);
                        updateSummary(form);
                    }
                    return;
                }

                if (stepButton) {
                    event.preventDefault();
                    setStep(form, stepButton.getAttribute('data-step-target'));
                    return;
                }

                if (nextButton) {
                    event.preventDefault();
                    setStep(form, modalState.currentStep + 1);
                    return;
                }

                if (backButton) {
                    event.preventDefault();
                    setStep(form, modalState.currentStep - 1);
                    return;
                }

                if (advancedButton) {
                    event.preventDefault();
                    toggleAdvanced(form);
                    return;
                }

                if (photoCropCancel) {
                    event.preventDefault();
                    resetPhotoCropper(form, true);
                    setDirty(form);
                    return;
                }

                if (photoCropApply) {
                    event.preventDefault();
                    applyPhotoCrop(form);
                    scheduleDraftSave(form);
                }
            });

            form.addEventListener('change', function (event) {
                if (event.target.matches('.js-cemetery-location-select')) {
                    var selected = event.target.options[event.target.selectedIndex];
                    var payload = selected ? {
                        name: selected.getAttribute('data-name') || '',
                        address: selected.getAttribute('data-address') || '',
                        latitude: selected.getAttribute('data-latitude') || '',
                        longitude: selected.getAttribute('data-longitude') || ''
                    } : null;

                    if (payload && !payload.name && !payload.address && !payload.latitude && !payload.longitude) {
                        payload = null;
                    }

                    applyCemeteryLocation(form, payload);
                }

                if (event.target === deceasedToggle) {
                    setDeceasedVisibility(form, deceasedToggle.checked);
                }

                clearFieldErrors(form);
                form.querySelector('[data-public-edit-error]').style.display = 'none';
                updateSummary(form);
                setDirty(form);
                scheduleDraftSave(form);
            });

            form.addEventListener('input', function () {
                clearFieldErrors(form);
                form.querySelector('[data-public-edit-error]').style.display = 'none';
                updateSummary(form);
                setDirty(form);
                scheduleDraftSave(form);
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearFieldErrors(form);
                showErrors(form, {});

                var submitButton = form.querySelector('[data-step-submit]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                window.fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        if (response.status === 422) {
                            return response.json().then(function (payload) {
                                showErrors(form, payload.errors || {});
                                throw new Error('validation');
                            });
                        }

                        return response.json();
                    })
                    .then(function (payload) {
                        clearDraft(form);
                        modalState.dirty = false;
                        modalState.allowClose = true;
                        renderSuccess(payload.message);
                    })
                    .catch(function (error) {
                        if (error.message !== 'validation') {
                            showErrors(form, { request: ['Usulan perubahan belum bisa dikirim. Silakan coba lagi.'] });
                        }
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    });
            });

            if (stepBody) {
                stepBody.addEventListener('scroll', function () {
                    updateScrollHint(form);
                });
            }

            updateSummary(form);
            syncChildSpouseOptions(form);
        }

        window.jQuery(modal).on('hide.bs.modal', function (event) {
            if (!modalState.form || modalState.allowClose) {
                return;
            }

            setDirty(modalState.form);
            if (modalState.dirty && !window.confirm('Formulir ini belum dikirim. Tutup dan buang semua perubahan yang belum tersimpan?')) {
                event.preventDefault();
                return false;
            }
        });

        window.jQuery(modal).on('hidden.bs.modal', function () {
            revokeObjectUrl('photoPreviewUrl');
            revokeObjectUrl('photoCropObjectUrl');
            modalState.activeTrigger = null;
            modalState.form = null;
            modalState.dirty = false;
            modalState.allowClose = false;
            modalState.currentStep = 1;
            modalState.initialSnapshot = '';
            modalState.photoCrop = null;
            modalBody.innerHTML = '<div class="text-center text-muted" style="padding:30px 0;">Memuat formulir...</div>';
        });

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.js-public-edit-trigger');
            if (!trigger) {
                return;
            }

            event.preventDefault();
            modalState.activeTrigger = trigger;
            modalBody.innerHTML = '<div class="text-center text-muted" style="padding:30px 0;">Memuat formulir...</div>';
            window.jQuery('#public-edit-request-modal').modal('show');

            window.fetch(trigger.getAttribute('data-edit-form-url'), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    modalBody.innerHTML = html;
                    bindDynamicForm(modalBody);
                })
                .catch(function () {
                    modalBody.innerHTML = '<div class="alert alert-danger" style="margin: 20px;">Formulir tidak bisa dimuat. Silakan coba lagi.</div>';
                });
        });
    })();
</script>
@endif
@endsection
