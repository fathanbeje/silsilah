@extends('layouts.app')

@section('content')
@if (request('action') == 'delete' && $user)
    @can('delete', $user)
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                @include('users.partials.delete_confirmation')
            </div>
        </div>
    @endcan
@else
    <div class="pull-right">
        {{ link_to_route('users.show', __('app.show_profile').' '.$user->display_name, [$user->id], ['class' => 'btn btn-default']) }}
    </div>
    <h2 class="page-header">
        {{ __('user.edit') }} {{ $user->profileLink() }}
    </h2>
    <style>
        .edit-page-summary {
            margin-bottom: 20px;
            padding: 18px 20px;
            border: 1px solid #e4e7eb;
            border-radius: 12px;
            background: linear-gradient(135deg, #fcf7e8 0%, #f7fbff 100%);
        }

        .edit-page-summary p:last-child {
            margin-bottom: 0;
        }

        .edit-section-nav {
            position: sticky;
            top: 90px;
        }

        .edit-section-nav .list-group-item {
            border-radius: 10px;
            margin-bottom: 8px;
            border: 1px solid #e4e7eb;
            padding: 12px 14px;
        }

        .edit-section-nav .list-group-item small {
            display: block;
            margin-top: 4px;
            color: #7a8694;
        }

        .edit-section-block {
            scroll-margin-top: 100px;
            margin-bottom: 18px;
        }

        .edit-actions-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border: 1px solid #e4e7eb;
            border-radius: 12px;
            background: #fff;
        }

        .edit-actions-bar.is-sticky {
            position: sticky;
            top: 70px;
            z-index: 20;
            box-shadow: 0 12px 28px rgba(16, 24, 40, 0.08);
        }

        .edit-actions-note {
            color: #667085;
            font-size: 13px;
        }

        .edit-actions-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 991px) {
            .edit-section-nav {
                position: static;
                margin-bottom: 18px;
            }

            .edit-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .edit-actions-buttons .btn {
                width: 100%;
            }
        }
    </style>
    <div class="edit-page-summary">
        <h4 style="margin-top: 0; margin-bottom: 8px;">Edit profil dalam satu halaman</h4>
        <p>Isi bagian yang perlu diubah, lalu simpan sekali di akhir. Anda tidak perlu pindah tab lagi.</p>
    </div>
    <div class="row">
        <div class="col-md-3">@include('users.partials.edit_nav_tabs')</div>
        <div class="col-md-9">
            <div class="row">
                {{ Form::model($user, ['route' => ['users.update', $user->id], 'method' =>'patch', 'autocomplete' => 'off', 'id' => 'user-edit-form']) }}
                <div class="col-md-7">
                    <div class="edit-actions-bar is-sticky">
                        <div>
                            <strong>Simpan perubahan</strong>
                            <div class="edit-actions-note" id="edit-actions-note">Belum ada perubahan baru.</div>
                        </div>
                        <div class="edit-actions-buttons">
                            {{ Form::submit(__('app.update'), ['class' => 'btn btn-primary', 'id' => 'edit-submit-button']) }}
                            {{ link_to_route('users.show', __('app.cancel'), [$user->id], ['class' => 'btn btn-default']) }}
                        </div>
                    </div>

                    <div class="edit-section-block" id="section-profile">
                        @include('users.partials.edit_profile')
                    </div>

                    <div class="edit-section-block" id="section-contact-address">
                        @include('users.partials.edit_contact_address')
                    </div>

                    <div class="edit-section-block" id="section-login-account">
                        @include('users.partials.edit_login_account')
                    </div>

                    <div class="edit-section-block" id="section-death">
                        @include('users.partials.edit_death')
                    </div>

                    <div class="edit-actions-bar">
                        <div>
                            <strong>Sudah selesai?</strong>
                            <div class="edit-actions-note">Simpan semua perubahan profil dari halaman ini.</div>
                        </div>
                        <div class="edit-actions-buttons">
                            {{ Form::submit(__('app.update'), ['class' => 'btn btn-primary']) }}
                            {{ link_to_route('users.show', __('app.cancel'), [$user->id], ['class' => 'btn btn-default']) }}
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
                <div class="col-md-5">
                    <div class="edit-section-block" id="section-photo">
                        @include('users.partials.update_photo')
                    </div>
                    <div class="panel panel-default edit-section-block" id="section-cemetery-map">
                        <div class="panel-heading"><h3 class="panel-title">{{ __('user.cemetery_location') }}</h3></div>
                        <div class="panel-body">
                            <p class="text-muted">Peta ini membantu mengisi lokasi makam bila data wafat sudah tersedia.</p>
                            <div id="mapid"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('ext_css')
<link href="{{ secure_asset('css/plugins/jquery.datetimepicker.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ secure_asset('css/plugins/select2.min.css') }}">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
  integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
  crossorigin=""/>
<style>
    #mapid { height: 320px; }
</style>
@endsection

@section('script')
<script src="{{ secure_asset('js/plugins/jquery.datetimepicker.js') }}"></script>
<script src="{{ secure_asset('js/plugins/select2.min.js') }}"></script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
  integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
  crossorigin=""></script>

<script>
    (function() {
        var form = document.getElementById('user-edit-form');
        var actionsNote = document.getElementById('edit-actions-note');
        var isDirty = false;
        var tabSectionMap = {
            contact_address: 'section-contact-address',
            login_account: 'section-login-account',
            death: 'section-death'
        };

        var matcher = function(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }

            if (typeof data.text === 'undefined') {
                return null;
            }

            var searchTerms = params.term.toLowerCase().split(/\s+/).filter(Boolean);
            var candidateText = data.text.toLowerCase();
            var isMatch = searchTerms.every(function(term) {
                return candidateText.indexOf(term) > -1;
            });

            return isMatch ? data : null;
        };

        $('select').select2({
            matcher: matcher
        });

        var applyCemeteryLocation = function(payload) {
            if (!payload) {
                return;
            }

            $('#cemetery_location_name').val(payload.name || '');
            $('#cemetery_location_address').val(payload.address || '');
            $('#cemetery_location_latitude').val(payload.latitude || '');
            $('#cemetery_location_longitude').val(payload.longitude || '');
        };

        $('.js-cemetery-location-select').on('change', function() {
            var selected = this.options[this.selectedIndex];
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
            @if (request('tab') == 'death')
            if (payload) {
                updateMarker($('#cemetery_location_latitude').val(), $('#cemetery_location_longitude').val());
            }
            @endif
        });

        $('#dob,#dod').datetimepicker({
            timepicker:false,
            format:'Y-m-d',
            closeOnDateSelect: true,
            scrollInput: false
        });

        if (form) {
            form.addEventListener('change', markDirty);
            form.addEventListener('input', markDirty);
            form.addEventListener('submit', function() {
                isDirty = false;
                if (actionsNote) {
                    actionsNote.textContent = 'Menyimpan perubahan...';
                }
            });
        }

        window.addEventListener('beforeunload', function (event) {
            if (!isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        var requestedTab = '{{ request('tab') }}';
        if (requestedTab && tabSectionMap[requestedTab]) {
            var targetSection = document.getElementById(tabSectionMap[requestedTab]);
            if (targetSection) {
                setTimeout(function () {
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 150);
            }
        }

        var mapCenter = [{{ $mapCenterLatitude }}, {{ $mapCenterLongitude }}];
        var map = L.map('mapid').setView(mapCenter, {{ $mapZoomLevel }});

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = L.marker(mapCenter).addTo(map);
        function updateMarker(lat, lng) {
            marker
            .setLatLng([lat, lng])
            .bindPopup("Your location :  " + marker.getLatLng().toString())
            .openPopup();
            return false;
        };

        map.on('click', function(e) {
            let latitude = e.latlng.lat.toString().substring(0, 15);
            let longitude = e.latlng.lng.toString().substring(0, 15);
            $('#cemetery_location_latitude').val(latitude);
            $('#cemetery_location_longitude').val(longitude);
            updateMarker(latitude, longitude);
        });

        var updateMarkerByInputs = function() {
            return updateMarker( $('#cemetery_location_latitude').val() , $('#cemetery_location_longitude').val());
        }
        $('#cemetery_location_latitude').on('input', updateMarkerByInputs);
        $('#cemetery_location_longitude').on('input', updateMarkerByInputs);

        function markDirty() {
            isDirty = true;
            if (actionsNote) {
                actionsNote.textContent = 'Ada perubahan yang belum disimpan.';
            }
        }
    })();
</script>
@endsection
