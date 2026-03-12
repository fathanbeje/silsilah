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

    .family-desktop-dock {
        display: none !important;
    }

    .family-desktop-toggle {
        display: none !important;
    }

    .family-mobile-toggle {
        position: fixed;
        right: 12px;
        bottom: 12px;
        z-index: 1041;
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 999px;
        background: rgba(34, 34, 34, 0.96);
        color: #fff;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.25);
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .family-mobile-toggle.is-visible {
        display: inline-flex;
    }

    .family-mobile-launcher {
        position: fixed;
        left: 12px;
        right: 64px;
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
    body {
        padding-bottom: 82px;
    }

    .family-mobile-launcher,
    .family-mobile-sheet {
        display: none !important;
    }

    .family-mobile-toggle {
        display: none !important;
    }

    .family-desktop-dock {
        position: fixed;
        left: 50%;
        bottom: 18px;
        z-index: 1035;
        transform: translateX(-50%);
        width: min(1100px, calc(100vw - 40px));
        padding: 12px 14px;
        border-radius: 20px;
        background:
            radial-gradient(circle at left center, rgba(212, 175, 55, 0.16), transparent 26%),
            linear-gradient(135deg, rgba(28, 28, 28, 0.96), rgba(46, 46, 46, 0.94));
        color: #fff;
        box-shadow: 0 22px 42px rgba(0, 0, 0, 0.28);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .family-desktop-toggle {
        position: fixed;
        right: 18px;
        bottom: 18px;
        z-index: 1036;
        width: 46px;
        height: 46px;
        border: 0;
        border-radius: 16px;
        background: rgba(28, 28, 28, 0.96);
        color: #fff;
        box-shadow: 0 18px 32px rgba(0, 0, 0, 0.24);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .family-desktop-dock__row {
        display: grid;
        grid-template-columns: auto minmax(280px, 1.1fr) minmax(320px, 1fr) auto;
        gap: 12px;
        align-items: center;
    }

    .family-desktop-dock__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        min-height: 48px;
        padding: 0 14px;
        border-radius: 999px;
        background: rgba(212, 175, 55, 0.14);
        color: #f3d46f;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .family-desktop-dock__search {
        position: relative;
    }

    .family-desktop-dock__search .form-control {
        height: 46px;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        padding-left: 42px;
        padding-right: 110px;
        box-shadow: none;
    }

    .family-desktop-dock__search .form-control::placeholder {
        color: rgba(255, 255, 255, 0.55);
    }

    .family-desktop-dock__search-icon {
        position: absolute;
        top: 50%;
        left: 15px;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.62);
    }

    .family-desktop-dock__search-submit {
        position: absolute;
        top: 50%;
        right: 8px;
        transform: translateY(-50%);
        border: 0;
        border-radius: 12px;
        padding: 8px 12px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    .family-desktop-dock__results {
        position: absolute;
        left: 0;
        right: 0;
        bottom: calc(100% + 12px);
        display: none;
        max-height: 360px;
        overflow-y: auto;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 22px 34px rgba(0, 0, 0, 0.2);
        border: 1px solid #ececec;
    }

    .family-desktop-dock__results .list-group-item {
        border-left: 0;
        border-right: 0;
        color: #1f2a2a;
    }

    .family-desktop-dock__results .list-group-item strong {
        color: #1f2a2a;
    }

    .family-desktop-dock__results .list-group-item .small {
        color: #526060;
    }

    .family-desktop-dock__results .list-group-item .text-muted {
        color: #6f7a7a;
    }

    .family-desktop-dock__links {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .family-desktop-dock__links::-webkit-scrollbar {
        height: 6px;
    }

    .family-desktop-dock__links::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.18);
        border-radius: 999px;
    }

    .family-desktop-dock__link {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        padding: 9px 13px;
        border-radius: 13px;
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
        text-decoration: none;
        white-space: nowrap;
        transition: transform 0.2s ease, background 0.2s ease;
    }

    .family-desktop-dock__link:hover,
    .family-desktop-dock__link:focus {
        color: #fff;
        text-decoration: none;
        transform: translateY(-1px);
        background: rgba(255, 255, 255, 0.12);
    }

    .family-desktop-dock__link strong {
        font-size: 12px;
        font-weight: 700;
    }

    .family-desktop-dock__actions {
        position: relative;
    }

    .family-desktop-dock__actions-toggle {
        border: 0;
        border-radius: 14px;
        padding: 11px 15px;
        min-width: 118px;
        background: #d4af37;
        color: #1f1f1f;
        font-weight: 700;
        box-shadow: 0 10px 20px rgba(212, 175, 55, 0.22);
    }

    .family-desktop-dock__actions-panel {
        position: absolute;
        right: 0;
        bottom: calc(100% + 12px);
        width: 320px;
        padding: 14px;
        border-radius: 20px;
        background: #fff;
        color: #222;
        box-shadow: 0 22px 36px rgba(0, 0, 0, 0.22);
        display: none;
    }

    .family-desktop-dock__actions-panel.is-open {
        display: block;
    }

    .family-desktop-dock__actions-title {
        margin: 0 0 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #888;
    }

    .family-desktop-dock__actions-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .family-desktop-dock__action-link {
        display: block;
        padding: 12px;
        border-radius: 16px;
        border: 1px solid #ececec;
        background: #fafafa;
        color: #222;
        text-decoration: none;
        min-height: 86px;
    }

    .family-desktop-dock__action-link:hover,
    .family-desktop-dock__action-link:focus {
        color: #222;
        text-decoration: none;
        background: #f2f2f2;
    }

    .family-desktop-dock__action-link strong {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
    }

    .family-desktop-dock__action-link span {
        display: block;
        font-size: 12px;
        color: #666;
        line-height: 1.4;
    }

    .family-desktop-dock__helper {
        margin: 10px 0 0;
        padding: 12px;
        border-radius: 16px;
        background: #f8f5ea;
        color: #6b5b19;
        font-size: 12px;
        line-height: 1.5;
    }
}

@media (min-width: 768px) and (max-width: 1100px) {
    .family-desktop-dock__row {
        grid-template-columns: auto minmax(220px, 1fr) auto;
    }

    .family-desktop-dock__links {
        grid-column: 1 / -1;
    }
}
</style>

<button type="button" class="family-mobile-toggle" data-family-mobile-toggle aria-label="Tampilkan atau sembunyikan dock mobile">
    <span data-family-mobile-toggle-icon>&lsaquo;</span>
</button>

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
            Gunakan pencarian cepat untuk loncat ke profil, bagan, atau pohon keluarga tanpa kehilangan konteks halaman saat ini.
        </p>
    </div>
</div>

<button type="button" class="family-desktop-toggle" data-family-desktop-toggle aria-label="Tampilkan atau sembunyikan family dock desktop">
    <span data-family-desktop-toggle-icon>&lsaquo;</span>
</button>

<div class="family-desktop-dock" data-family-desktop-dock>
    <div class="family-desktop-dock__row">
        <div class="family-desktop-dock__eyebrow">Family Dock</div>

        <div class="family-desktop-dock__search">
            <span class="family-desktop-dock__search-icon glyphicon glyphicon-search" aria-hidden="true"></span>
            <input
                type="search"
                class="form-control"
                id="family-desktop-search-input"
                placeholder="Masukkan nama/panggilan lalu pilih hasilnya"
                autocomplete="off"
            >
            <button type="button" class="family-desktop-dock__search-submit" data-family-desktop-search-focus>Cari</button>
            <div id="family-desktop-search-results" class="family-desktop-dock__results list-group"></div>
        </div>

        <div class="family-desktop-dock__links">
            <a class="family-desktop-dock__link" href="{{ route('users.search') }}"><strong>{{ __('app.search') }}</strong></a>

            @if (auth()->check())
            <a class="family-desktop-dock__link" href="{{ route('profile') }}"><strong>{{ __('app.my_profile') }}</strong></a>
            @endif

            @if ($mobileContextUser)
            <a class="family-desktop-dock__link" href="{{ route('users.chart', $mobileContextUser) }}"><strong>{{ __('app.family_chart') }}</strong></a>
            @endif

            @if (auth()->check() && $mobileContextUser)
            <a class="family-desktop-dock__link" href="{{ route('users.tree', $mobileContextUser) }}"><strong>{{ __('app.family_tree') }}</strong></a>
            @endif

            @if ($profileUrl)
            <a class="family-desktop-dock__link" href="{{ $profileUrl }}#family-panel"><strong>Keluarga Inti</strong></a>
            @endif
        </div>

        @if ($canEditMobileContextUser)
        <div class="family-desktop-dock__actions">
            <button type="button" class="family-desktop-dock__actions-toggle" data-family-desktop-actions-toggle>Aksi Cepat</button>
            <div class="family-desktop-dock__actions-panel" data-family-desktop-actions-panel>
                <p class="family-desktop-dock__actions-title">Aksi Keluarga</p>
                <div class="family-desktop-dock__actions-list">
                    <a class="family-desktop-dock__action-link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'add_child']) }}">
                        <strong>{{ __('user.add_child') }}</strong>
                        <span>Tambah anak dari konteks profil saat ini.</span>
                    </a>
                    <a class="family-desktop-dock__action-link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'set_father']) }}">
                        <strong>{{ __('user.set_father') }}</strong>
                        <span>Hubungkan ayah tanpa meninggalkan alur kerja.</span>
                    </a>
                    <a class="family-desktop-dock__action-link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'set_mother']) }}">
                        <strong>{{ __('user.set_mother') }}</strong>
                        <span>Hubungkan ibu dan rapikan relasi keluarga.</span>
                    </a>
                    <a class="family-desktop-dock__action-link" href="{{ route('users.show', [$mobileContextUser->id, 'action' => 'add_spouse']) }}">
                        <strong>{{ __('user.add_spouse') }}</strong>
                        <span>Tambahkan pasangan untuk cabang keluarga aktif.</span>
                    </a>
                </div>
                <p class="family-desktop-dock__helper">Aksi keluarga tetap dekat dengan pointer tanpa membuat area bawah halaman terasa berat.</p>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
    (function () {
        var launcher = document.querySelector('[data-family-mobile-launcher]');
        var sheet = document.querySelector('[data-family-mobile-sheet]');
        var openButton = document.querySelector('[data-family-mobile-open]');
        var closeButtons = document.querySelectorAll('[data-family-mobile-close]');
        var mobileToggle = document.querySelector('[data-family-mobile-toggle]');
        var mobileToggleIcon = document.querySelector('[data-family-mobile-toggle-icon]');
        var input = document.getElementById('family-mobile-search-input');
        var results = document.getElementById('family-mobile-search-results');
        var desktopDock = document.querySelector('[data-family-desktop-dock]');
        var desktopToggle = document.querySelector('[data-family-desktop-toggle]');
        var desktopToggleIcon = document.querySelector('[data-family-desktop-toggle-icon]');
        var desktopInput = document.getElementById('family-desktop-search-input');
        var desktopResults = document.getElementById('family-desktop-search-results');
        var desktopSearchFocus = document.querySelector('[data-family-desktop-search-focus]');
        var desktopActionsToggle = document.querySelector('[data-family-desktop-actions-toggle]');
        var desktopActionsPanel = document.querySelector('[data-family-desktop-actions-panel]');
        var mobileDockStorageKey = 'family-mobile-dock-collapsed';
        var desktopDockStorageKey = 'family-desktop-dock-collapsed';

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

        function setMobileDockCollapsed(collapsed) {
            launcher.style.display = collapsed ? 'none' : 'flex';

            if (mobileToggle) {
                mobileToggle.classList.add('is-visible');
            }

            if (mobileToggleIcon) {
                mobileToggleIcon.innerHTML = collapsed ? '&rsaquo;' : '&lsaquo;';
            }

            try {
                window.sessionStorage.setItem(mobileDockStorageKey, collapsed ? '1' : '0');
            } catch (error) {}
        }

        function setDesktopDockCollapsed(collapsed) {
            if (!desktopDock) {
                return;
            }

            desktopDock.style.display = collapsed ? 'none' : 'block';

            if (desktopToggleIcon) {
                desktopToggleIcon.innerHTML = collapsed ? '&lsaquo;' : '&rsaquo;';
            }

            try {
                window.sessionStorage.setItem(desktopDockStorageKey, collapsed ? '1' : '0');
            } catch (error) {}
        }

        function hideResults(container) {
            if (!container) {
                return;
            }

            container.style.display = 'none';
            container.innerHTML = '';
        }

        function renderResults(container, items) {
            if (!items.length) {
                hideResults(container);
                return;
            }

            container.innerHTML = items.map(function (item) {
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

            container.style.display = 'block';
        }

        function bindAutocomplete(searchInput, output) {
            var timer = null;

            if (!searchInput || !output) {
                return;
            }

            searchInput.addEventListener('input', function () {
                var value = searchInput.value.trim();
                window.clearTimeout(timer);

                if (value.length < 2) {
                    hideResults(output);
                    return;
                }

                timer = window.setTimeout(function () {
                    window.fetch('{{ route('users.autocomplete') }}?q=' + encodeURIComponent(value), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (items) {
                            renderResults(output, items);
                        })
                        .catch(function () {
                            hideResults(output);
                        });
                }, 180);
            });

            output.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    closeSheet();
                    hideResults(output);
                }
            });
        }

        openButton.addEventListener('click', openSheet);
        Array.prototype.forEach.call(closeButtons, function (button) {
            button.addEventListener('click', closeSheet);
        });

        if (mobileToggle) {
            mobileToggle.addEventListener('click', function () {
                var collapsed = launcher.style.display !== 'none';
                setMobileDockCollapsed(collapsed);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSheet();
                hideResults(desktopResults);

                if (desktopActionsPanel) {
                    desktopActionsPanel.classList.remove('is-open');
                }
            }
        });

        bindAutocomplete(input, results);
        bindAutocomplete(desktopInput, desktopResults);

        if (desktopSearchFocus && desktopInput) {
            desktopSearchFocus.addEventListener('click', function () {
                desktopInput.focus();
            });
        }

        if (desktopToggle && desktopDock) {
            desktopToggle.addEventListener('click', function () {
                var collapsed = desktopDock.style.display !== 'none';
                setDesktopDockCollapsed(collapsed);
            });
        }

        if (desktopActionsToggle && desktopActionsPanel) {
            desktopActionsToggle.addEventListener('click', function () {
                desktopActionsPanel.classList.toggle('is-open');
            });

            document.addEventListener('click', function (event) {
                if (
                    !event.target.closest('[data-family-desktop-actions-toggle]') &&
                    !event.target.closest('[data-family-desktop-actions-panel]')
                ) {
                    desktopActionsPanel.classList.remove('is-open');
                }

                if (!event.target.closest('.family-desktop-dock__search')) {
                    hideResults(desktopResults);
                }
            });
        }

        try {
            setMobileDockCollapsed(window.sessionStorage.getItem(mobileDockStorageKey) === '1');
            setDesktopDockCollapsed(window.sessionStorage.getItem(desktopDockStorageKey) === '1');
        } catch (error) {
            setMobileDockCollapsed(false);
            setDesktopDockCollapsed(false);
        }
    })();
</script>
