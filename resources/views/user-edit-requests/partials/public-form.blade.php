<form
    method="POST"
    action="{{ route('user-edit-requests.store', $user) }}"
    enctype="multipart/form-data"
    data-public-edit-request-form
    data-existing-spouses="{{ e(json_encode($existingSpouseOptions)) }}"
>
    {{ csrf_field() }}
    <div class="public-edit-request-form">
        <div class="public-edit-request-form__header">
            <div>
                <h4 class="public-edit-request-form__title">{{ $user->display_name ?: $user->nickname }}</h4>
                <div class="text-muted">Usulan perubahan akan ditinjau admin sebelum tampil di data live.</div>
            </div>
            <div class="public-edit-request-form__photo">
                {{ userPhoto($user, ['style' => 'width:88px;height:88px;object-fit:cover;border-radius:16px;']) }}
            </div>
        </div>
        <div class="small text-muted" style="margin: 12px 0 18px;">
            <span class="text-danger" style="font-weight:700;">*</span> Wajib diisi
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Profil Utama</strong></div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama</label><input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Nama panggilan <span class="text-danger">*</span></label><input type="text" name="nickname" class="form-control" value="{{ old('nickname', $user->nickname) }}" required></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Jenis kelamin <span class="text-danger">*</span></label><select name="gender_id" class="form-control" required><option value="1" @selected(old('gender_id', $user->gender_id) == 1)>Laki-laki</option><option value="2" @selected(old('gender_id', $user->gender_id) == 2)>Perempuan</option></select></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Urutan lahir</label><input type="number" min="1" name="birth_order" class="form-control" value="{{ old('birth_order', $user->birth_order) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Tanggal lahir</label><input type="date" name="dob" class="form-control" value="{{ old('dob', optional($user->dob)->format('Y-m-d')) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Tahun lahir</label><input type="text" name="yob" class="form-control" value="{{ old('yob', $user->yob) }}" placeholder="YYYY"></div></div>
                            <div class="col-sm-12"><div class="checkbox" style="margin-top:0;"><label><input type="checkbox" name="is_deceased" value="1" {{ old('is_deceased', $user->isDeceased()) ? 'checked' : '' }}> Sudah meninggal</label></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Tanggal wafat</label><input type="date" name="dod" class="form-control" value="{{ old('dod', optional($user->dod)->format('Y-m-d')) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Tahun wafat</label><input type="text" name="yod" class="form-control" value="{{ old('yod', $user->yod) }}" placeholder="YYYY"></div></div>
                            <div class="col-sm-12"><div class="form-group form-group-sm"><label>Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}"></div></div>
                            <div class="col-sm-12"><div class="form-group form-group-sm"><label>Usulan foto baru</label><input type="file" name="photo" class="form-control" accept="image/*"><p class="help-block">Foto akan dipotong persegi dan dikompres otomatis maksimal 200 KB.</p></div></div>
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Alamat & Kontak</strong></div>
                    <div class="panel-body">
                        <div class="form-group form-group-sm"><label>Alamat</label><textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea></div>
                        <div class="row">
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Kota</label><input type="text" name="city" class="form-control" value="{{ old('city', $user->city) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Telepon</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Lokasi Makam</strong></div>
                    <div class="panel-body">
                        <div class="form-group form-group-sm">
                            <label>Pilih lokasi makam yang sudah ada</label>
                            <select class="form-control js-cemetery-location-select">
                                <option value="">Pilih lokasi makam yang sudah ada</option>
                                @foreach ($cemeteryLocationOptions as $location)
                                <option
                                    value="{{ $location['id'] }}"
                                    data-name="{{ e($location['name']) }}"
                                    data-address="{{ e($location['address']) }}"
                                    data-latitude="{{ e($location['latitude']) }}"
                                    data-longitude="{{ e($location['longitude']) }}"
                                >{{ $location['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group form-group-sm"><label>Nama lokasi makam</label><input type="text" name="cemetery_location_name" class="form-control" value="{{ old('cemetery_location_name', $user->getMetadata('cemetery_location_name')) }}"></div>
                        <div class="form-group form-group-sm"><label>Alamat makam</label><textarea name="cemetery_location_address" class="form-control" rows="2">{{ old('cemetery_location_address', $user->getMetadata('cemetery_location_address')) }}</textarea></div>
                        <div class="row">
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Latitude</label><input type="text" name="cemetery_location_latitude" class="form-control" value="{{ old('cemetery_location_latitude', $user->getMetadata('cemetery_location_latitude')) }}"></div></div>
                            <div class="col-sm-6"><div class="form-group form-group-sm"><label>Longitude</label><input type="text" name="cemetery_location_longitude" class="form-control" value="{{ old('cemetery_location_longitude', $user->getMetadata('cemetery_location_longitude')) }}"></div></div>
                        </div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading public-edit-request-form__panel-heading">
                        <strong>Tambah Pasangan</strong>
                        <button type="button" class="btn btn-default btn-xs" data-add-spouse>Tambah</button>
                    </div>
                    <div class="panel-body">
                        <div data-spouse-list></div>
                        <div class="text-muted small">Gunakan section ini hanya jika data pasangan belum tercatat di keluarga ini.</div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading public-edit-request-form__panel-heading">
                        <strong>Tambah Anak</strong>
                        <button type="button" class="btn btn-default btn-xs" data-add-child>Tambah</button>
                    </div>
                    <div class="panel-body">
                        <div data-child-list></div>
                        <div class="text-muted small">Anak baru bisa dikaitkan ke pasangan existing, pasangan baru di usulan ini, atau tanpa pasangan tercatat.</div>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Identitas Pengaju</strong></div>
                    <div class="panel-body">
                        <div class="form-group form-group-sm"><label>Nama pengaju <span class="text-danger">*</span></label><input type="text" name="requester_name" class="form-control" value="{{ old('requester_name') }}" required></div>
                        <div class="form-group form-group-sm"><label>Nomor WA aktif <span class="text-danger">*</span></label><input type="text" name="requester_whatsapp" class="form-control" value="{{ old('requester_whatsapp') }}" required></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-danger" data-public-edit-error style="display:none;"></div>

        <div class="text-right">
            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Kirim untuk Ditinjau Admin</button>
        </div>
    </div>
</form>
