@php
    $mobileContextUser = isset($user) && $user instanceof \App\User ? $user : (auth()->check() ? auth()->user() : null);
    $canEditMobileContextUser = auth()->check() && $mobileContextUser && auth()->user()->can('edit', $mobileContextUser);
    $profileUrl = null;

    if (auth()->check() && $mobileContextUser) {
        $profileUrl = Route::currentRouteName() === 'profile' && auth()->id() === $mobileContextUser->id
            ? route('profile')
            : route('users.show', $mobileContextUser);
    }
@endphp

<style>
@media (max-width: 767px) {
    body {
        padding-bottom: 84px;
    }

    .family-mobile-launcher {
        position: fixed;
        left: 12px;
        right: 12px;
        bottom: 12px;
        z-index: 1040;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 16px;
        background: rgba(34, 34, 34, 0.96);
        color: #fff;
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.28);
    }

    .family-mobile-launcher__copy {
        min-width: 0;
    }

    .family-mobile-launcher__title {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.2;
    }

    .family-mobile-launcher__hint {
        font-size: 12px;
        opacity: 0.78;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .family-mobile-launcher__button {
        border: 0;
        border-radius: 999px;
        padding: 10px 14px;
        background: #d4af37;
        color: #1f1f1f;
        font-weight: 700;
    }

    .family-mobile-sheet {
        position: fixed;
        inset: 0;
        z-index: 1050;
        display: none;
    }

    .family-mobile-sheet.is-open {
        display: block;
    }

    .family-mobile-sheet__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
    }

    .family-mobile-sheet__panel {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        background: #fff;
        border-radius: 22px 22px 0 0;
        box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.18);
        padding: 14px 14px 20px;
    }

    .family-mobile-sheet__handle {
        width: 46px;
        height: 5px;
        border-radius: 999px;
        background: #d0d0d0;
        margin: 0 auto 14px;
    }

    .family-mobile-sheet__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .family-mobile-sheet__title {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .family-mobile-sheet__close {
        border: 0;
        background: transparent;
        font-size: 24px;
        line-height: 1;
        color: #666;
    }

    .family-mobile-sheet__search {
        position: relative;
        margin-bottom: 16px;
    }

    .family-mobile-sheet__search .form-control {
        border-radius: 12px;
        height: 44px;
        padding-right: 44px;
    }

    .family-mobile-sheet__search-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
    }

    .family-mobile-sheet__results {
        display: none;
        margin-bottom: 16px;
        border: 1px solid #e7e7e7;
        border-radius: 14px;
        overflow: hidden;
    }

    .family-mobile-sheet__results .list-group-item {
        border-left: 0;
        border-right: 0;
    }

    .family-mobile-sheet__section-label {
        margin: 0 0 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #888;
    }

    .family-mobile-sheet__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .family-mobile-sheet__link {
        display: block;
        padding: 12px;
        border: 1px solid #ececec;
        border-radius: 14px;
        color: #222;
        text-decoration: none;
        background: #fafafa;
        min-height: 72px;
    }

    .family-mobile-sheet__link strong {
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
    }

    .family-mobile-sheet__link span {
        display: block;
        font-size: 12px;
        color: #777;
    }

    .family-mobile-sheet__helper {
        margin: 0;
        padding: 12px;
        border-radius: 14px;
        background: #f8f5ea;
        font-size: 12px;
        color: #6b5b19;
    }
}

@media (min-width: 768px) {
    .family-mobile-launcher,
    .family-mobile-sheet {
        display: none !important;
    }
}
</style>

<div class="family-mobile-launcher" data-family-mobile-launcher>
    <div class="family-mobile-launcher__copy">
        <div class="family-mobile-launcher__title">Navigasi Keluarga</div>
        <div class="family-mobile-launcher__hint">Cari anggota, buka bagan, dan aksi cepat</div>
    </div>
    <button type="button" class="family-mobile-launcher__button" data-family-mobile-open>Buka</button>
</div>

<div class="family-mobile-sheet" data-family-mobile-sheet aria-hidden="true">
    <div class="family-mobile-sheet__backdrop" data-family-mobile-close></div>
    <div class="family-mobile-sheet__panel" role="dialog" aria-modal="true" aria-label="Navigasi keluarga mobile">
        <div class="family-mobile-sheet__handle"></div>
        <div class="family-mobile-sheet__header">
            <h3 class="family-mobile-sheet__title">Akses Cepat</h3>
            <button type="button" class="family-mobile-sheet__close" aria-label="Tutup" data-family-mobile-close>&times;</button>
        </div>

        <div class="family-mobile-sheet__search">
            <input
                type="search"
                class="form-control"
                id="family-mobile-search-input"
                placeholder="{{ trans('app.search_your_family_placeholder') }}"
                autocomplete="off"
            >
            <span class="family-mobile-sheet__search-icon glyphicon glyphicon-search" aria-hidden="true"></span>
        </div>
        <div id="family-mobile-search-results" class="family-mobile-sheet__results list-group"></div>

        <p class="family-mobile-sheet__section-label">Navigasi Utama</p>
        <div class="family-mobile-sheet__grid">
            <a class="family-mobile-sheet__link" href="{{ route('users.search') }}">
                <strong>{{ __('app.search') }}</strong>
                <span>Masuk ke pencarian keluarga</span>
            </a>

            @if (auth()->check())
            <a class="family-mobile-sheet__link" href="{{ route('profile') }}">
                <strong>{{ __('app.my_profile') }}</strong>
                <span>Buka data profil utama Anda</span>
            </a>
            @endif

            @if ($mobileContextUser)
            <a class="family-mobile-sheet__link" href="{{ route('users.chart', $mobileContextUser) }}">
                <strong>{{ __('app.family_chart') }}</strong>
                <span>Lihat cabang keluarga saat ini</span>
            </a>
            @endif

            @if (auth()->check() && $mobileContextUser)
            <a class="family-mobile-sheet__link" href="{{ route('users.tree', $mobileContextUser) }}">
                <strong>{{ __('app.family_tree') }}</strong>
                <span>Buka tampilan pohon keluarga</span>
            </a>
            @endif

            @if ($profileUrl)
            <a class="family-mobile-sheet__link" href="{{ $profileUrl }}#family-panel">
                <strong>Keluarga Inti</strong>
                <span>Loncat ke panel ayah, ibu, pasangan, dan anak</span>
            </a>
            @endif
        </div>

        @if ($canEditMobileContextUser)
        <p class="family-mobile-sheet__section-label">Aksi Keluarga</p>
        <div class="family-mobile-sheet__grid">
            <a class="family-mobile-sheet__link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'add_child']) }}">
                <strong>{{ __('user.add_child') }}</strong>
                <span>Tambah anak dari profil ini</span>
            </a>
            <a class="family-mobile-sheet__link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'set_father']) }}">
                <strong>{{ __('user.set_father') }}</strong>
                <span>Hubungkan ayah lalu sinkron otomatis</span>
            </a>
            <a class="family-mobile-sheet__link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'set_mother']) }}">
                <strong>{{ __('user.set_mother') }}</strong>
                <span>Hubungkan ibu lalu sinkron otomatis</span>
            </a>
            <a class="family-mobile-sheet__link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'add_spouse']) }}">
                <strong>{{ __('user.add_spouse') }}</strong>
                <span>Tambahkan pasangan yang relevan</span>
            </a>
        </div>
        @endif

        <p class="family-mobile-sheet__helper">
            Jika ayah dan ibu sudah terhubung, pasangan orang tua akan dicocokkan otomatis agar jumlah anak dan urutan keluarga tetap konsisten.
        </p>
    </div>
</div>

<script>
    (function () {
        var launcher = document.querySelector('[data-family-mobile-launcher]');
        var sheet = document.querySelector('[data-family-mobile-sheet]');
        var openButton = document.querySelector('[data-family-mobile-open]');
        var closeButtons = document.querySelectorAll('[data-family-mobile-close]');
        var input = document.getElementById('family-mobile-search-input');
        var results = document.getElementById('family-mobile-search-results');
        var timer = null;

        if (!launcher || !sheet || !openButton || !input || !results) {
            return;
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

        function openSheet() {
            sheet.classList.add('is-open');
            sheet.setAttribute('aria-hidden', 'false');
            window.setTimeout(function () {
                input.focus();
            }, 60);
        }

        function closeSheet() {
            sheet.classList.remove('is-open');
            sheet.setAttribute('aria-hidden', 'true');
        }

        function hideResults() {
            results.style.display = 'none';
            results.innerHTML = '';
        }

        function renderResults(items) {
            if (!items.length) {
                hideResults();
                return;
            }

            results.innerHTML = items.map(function (item) {
                var secondaryActions = [
                    item.profile_url ? '<a class="btn btn-default btn-xs" href="' + item.profile_url + '">Profil</a>' : '',
                    item.chart_url ? '<a class="btn btn-primary btn-xs" href="' + item.chart_url + '">Bagan</a>' : '',
                    item.tree_url ? '<a class="btn btn-default btn-xs" href="' + item.tree_url + '">Pohon</a>' : ''
                ].filter(Boolean).join(' ');

                return '' +
                    '<div class="list-group-item">' +
                        '<div style="margin-bottom:6px;">' +
                            '<strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.gender) + ')' +
                        '</div>' +
                        '<div class="small">' + escapeHtml(item.nickname) + '</div>' +
                        (item.parents ? '<div class="small text-muted" style="margin:4px 0 8px;">' + escapeHtml(item.parents) + '</div>' : '') +
                        '<div>' + secondaryActions + '</div>' +
                    '</div>';
            }).join('');

            results.style.display = 'block';
        }

        openButton.addEventListener('click', openSheet);
        Array.prototype.forEach.call(closeButtons, function (button) {
            button.addEventListener('click', closeSheet);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSheet();
            }
        });

        input.addEventListener('input', function () {
            var value = input.value.trim();
            window.clearTimeout(timer);

            if (value.length < 2) {
                hideResults();
                return;
            }

            timer = window.setTimeout(function () {
                window.fetch('{{ route('users.autocomplete') }}?q=' + encodeURIComponent(value), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.json(); })
                    .then(renderResults)
                    .catch(hideResults);
            }, 180);
        });

        results.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeSheet();
            }
        });
    })();
</script>
