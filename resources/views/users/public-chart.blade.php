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

        if (!modal || !modalBody) {
            return;
        }

        function bindDynamicForm(container) {
            var existingSpouses = [];
            var form = container.querySelector('form[data-public-edit-request-form]');
            var errorBox = container.querySelector('[data-public-edit-error]');
            if (!form) {
                return;
            }

            try {
                existingSpouses = JSON.parse(form.getAttribute('data-existing-spouses') || '[]');
            } catch (error) {
                existingSpouses = [];
            }

            var spouseList = form.querySelector('[data-spouse-list]');
            var childList = form.querySelector('[data-child-list]');
            var spouseIndex = spouseList ? spouseList.children.length : 0;
            var childIndex = childList ? childList.children.length : 0;

            function initializeSelectEnhancements() {
                if (!window.jQuery || !window.jQuery.fn.select2) {
                    return;
                }

                window.jQuery(form).find('.js-cemetery-location-select').select2({
                    width: '100%',
                    placeholder: 'Pilih lokasi makam yang sudah ada',
                    allowClear: true
                });
            }

            function applyCemeteryLocation(payload) {
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

            function currentNewSpouses() {
                return Array.prototype.slice.call(form.querySelectorAll('[data-spouse-item]')).map(function (item) {
                    return {
                        value: 'new:' + item.getAttribute('data-request-key'),
                        label: item.querySelector('[data-spouse-name-input]') && item.querySelector('[data-spouse-name-input]').value.trim()
                    };
                }).filter(function (item) {
                    return item.label;
                });
            }

            function buildSpouseOptions(selectedValue) {
                var options = ['<option value="none">Tanpa pasangan tercatat</option>'];

                existingSpouses.concat(currentNewSpouses()).forEach(function (item) {
                    var selected = item.value === selectedValue ? ' selected' : '';
                    options.push('<option value="' + item.value + '"' + selected + '>' + item.label + '</option>');
                });

                return options.join('');
            }

            function syncChildSpouseOptions() {
                Array.prototype.forEach.call(form.querySelectorAll('[data-child-spouse-context]'), function (select) {
                    var current = select.value || 'none';
                    select.innerHTML = buildSpouseOptions(current);
                });
            }

            function showErrors(errors) {
                if (!errorBox) {
                    return;
                }

                var messages = [];
                Object.keys(errors || {}).forEach(function (field) {
                    (errors[field] || []).forEach(function (message) {
                        messages.push(message);
                    });
                });

                if (!messages.length) {
                    errorBox.style.display = 'none';
                    errorBox.innerHTML = '';
                    return;
                }

                errorBox.innerHTML = messages.join('<br>');
                errorBox.style.display = 'block';
            }

            function spouseTemplate(index) {
                var key = 'spouse_' + index;
                return '' +
                    '<div class="request-repeat-card" data-spouse-item data-request-key="' + key + '">' +
                        '<input type="hidden" name="new_spouses[' + index + '][request_key]" value="' + key + '">' +
                        '<button type="button" class="btn btn-link btn-xs request-repeat-card__remove" data-remove-repeat>&times;</button>' +
                        '<div class="row">' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama pasangan</label><input data-spouse-name-input type="text" name="new_spouses[' + index + '][name]" class="form-control"></div></div>' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama panggilan</label><input type="text" name="new_spouses[' + index + '][nickname]" class="form-control"></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tgl nikah</label><input type="date" name="new_spouses[' + index + '][marriage_date]" class="form-control"></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tgl lahir</label><input type="date" name="new_spouses[' + index + '][dob]" class="form-control"></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Tahun lahir</label><input type="text" name="new_spouses[' + index + '][yob]" class="form-control" placeholder="YYYY"></div></div>' +
                        '</div>' +
                    '</div>';
            }

            function childTemplate(index) {
                return '' +
                    '<div class="request-repeat-card" data-child-item>' +
                        '<button type="button" class="btn btn-link btn-xs request-repeat-card__remove" data-remove-repeat>&times;</button>' +
                        '<div class="row">' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama anak</label><input type="text" name="new_children[' + index + '][name]" class="form-control"></div></div>' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama panggilan</label><input type="text" name="new_children[' + index + '][nickname]" class="form-control"></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Jenis kelamin</label><select name="new_children[' + index + '][gender_id]" class="form-control"><option value="">Pilih</option><option value="1">Laki-laki</option><option value="2">Perempuan</option></select></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Urutan lahir</label><input type="number" min="1" name="new_children[' + index + '][birth_order]" class="form-control"></div></div>' +
                            '<div class="col-sm-4"><div class="form-group form-group-sm"><label>Pasangan konteks</label><select data-child-spouse-context name="new_children[' + index + '][spouse_context]" class="form-control">' + buildSpouseOptions('none') + '</select></div></div>' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Tgl lahir</label><input type="date" name="new_children[' + index + '][dob]" class="form-control"></div></div>' +
                            '<div class="col-sm-6"><div class="form-group form-group-sm"><label>Tahun lahir</label><input type="text" name="new_children[' + index + '][yob]" class="form-control" placeholder="YYYY"></div></div>' +
                        '</div>' +
                    '</div>';
            }

            if (spouseList) {
                form.querySelector('[data-add-spouse]').addEventListener('click', function () {
                    spouseList.insertAdjacentHTML('beforeend', spouseTemplate(spouseIndex++));
                    syncChildSpouseOptions();
                });

                spouseList.addEventListener('input', function (event) {
                    if (event.target.matches('[data-spouse-name-input]')) {
                        syncChildSpouseOptions();
                    }
                });
            }

            if (childList) {
                form.querySelector('[data-add-child]').addEventListener('click', function () {
                    childList.insertAdjacentHTML('beforeend', childTemplate(childIndex++));
                    syncChildSpouseOptions();
                });
            }

            form.addEventListener('click', function (event) {
                var removeButton = event.target.closest('[data-remove-repeat]');
                if (!removeButton) {
                    return;
                }

                event.preventDefault();
                var card = removeButton.closest('.request-repeat-card');
                if (card) {
                    card.remove();
                    syncChildSpouseOptions();
                }
            });

            form.addEventListener('change', function (event) {
                if (!event.target.matches('.js-cemetery-location-select')) {
                    return;
                }

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

                applyCemeteryLocation(payload);
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                showErrors({});

                var submitButton = form.querySelector('button[type="submit"]');
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
                                showErrors(payload.errors || {});
                                throw new Error('validation');
                            });
                        }

                        return response.json();
                    })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (error) {
                        if (error.message !== 'validation') {
                            showErrors({ request: ['Usulan perubahan belum bisa dikirim. Silakan coba lagi.'] });
                        }
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    });
            });

            syncChildSpouseOptions();
            initializeSelectEnhancements();
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.js-public-edit-trigger');
            if (!trigger) {
                return;
            }

            event.preventDefault();
            modalBody.innerHTML = '<div class="text-center text-muted" style="padding:30px 0;">Memuat formulir...</div>';
            $('#public-edit-request-modal').modal('show');

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
                    modalBody.innerHTML = '<div class="alert alert-danger">Formulir tidak bisa dimuat. Silakan coba lagi.</div>';
                });
        });
    })();
</script>
@endif
@endsection
