<div class="edit-section-nav">
    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Bagian Edit</h3></div>
        <div class="list-group">
            <a class="list-group-item" href="#section-profile">
                Data utama
                <small>Nama, panggilan, gender, urutan lahir, tanggal lahir.</small>
            </a>
            <a class="list-group-item" href="#section-contact-address">
                Alamat &amp; kontak
                <small>Alamat, kota, dan nomor telepon.</small>
            </a>
            <a class="list-group-item" href="#section-login-account">
                Akun login
                <small>Email dan password login pengguna ini.</small>
            </a>
            <a class="list-group-item" href="#section-death">
                Data wafat
                <small>Status wafat, tanggal wafat, dan lokasi makam.</small>
            </a>
            <a class="list-group-item" href="#section-photo">
                Foto profil
                <small>Upload, paste clipboard, atau drag-and-drop foto.</small>
            </a>
        </div>
    </div>
</div>

@can('delete', $user)
{{ link_to_route('users.edit', __('user.delete'), [$user, 'action' => 'delete'], ['class' => 'btn btn-danger', 'id' => 'del-user-'.$user->id]) }}
@endcan
