<form
    method="POST"
    action="{{ route('user-edit-requests.store', $user) }}"
    enctype="multipart/form-data"
    data-public-edit-request-form
    data-user-id="{{ $user->id }}"
    data-user-name="{{ e($user->display_name ?: $user->nickname) }}"
    data-existing-spouses="{{ e(json_encode($existingSpouseOptions)) }}"
>
    {{ csrf_field() }}
    @php
        $selectedGenderId = old('gender_id');

        if ($selectedGenderId === null || $selectedGenderId === '') {
            $selectedGenderId = $user->gender_id;
        }

        $deceasedChecked = old('is_deceased', $user->isDeceased());
    @endphp
    <div class="public-edit-request-form">
        <div class="public-edit-request-form__header">
            <div>
                <span class="public-edit-request-form__eyebrow">Usulan perubahan keluarga</span>
                <h4 class="public-edit-request-form__title">{{ $user->display_name ?: $user->nickname }}</h4>
                <p class="public-edit-request-form__lead">
                    Gunakan formulir ini untuk memperbaiki profil, menambah pasangan, atau menambah anak.
                    Semua usulan akan ditinjau admin terlebih dahulu sebelum tampil di data live.
                </p>
            </div>
            <div class="public-edit-request-form__photo">
                {{ userPhoto($user, ['style' => 'width:88px;height:88px;object-fit:cover;border-radius:18px;']) }}
            </div>
        </div>

        <div class="public-edit-request-form__restore alert alert-info" data-public-edit-restore style="display:none;"></div>
        <div class="small text-muted public-edit-request-form__required-note">
            <span class="text-danger" style="font-weight:700;">*</span> Wajib diisi
        </div>

        <div class="alert alert-danger public-edit-request-form__error-summary" data-public-edit-error style="display:none;"></div>

        <div class="public-edit-request-stepper" data-public-edit-stepper>
            <div class="public-edit-request-stepper__nav" data-public-edit-stepper-nav>
                <button type="button" class="public-edit-request-stepper__tab is-active" data-step-target="1">
                    <span class="public-edit-request-stepper__tab-index">1</span>
                    <span class="public-edit-request-stepper__tab-text">Profil Utama</span>
                </button>
                <button type="button" class="public-edit-request-stepper__tab" data-step-target="2">
                    <span class="public-edit-request-stepper__tab-index">2</span>
                    <span class="public-edit-request-stepper__tab-text">Keluarga</span>
                </button>
                <button type="button" class="public-edit-request-stepper__tab" data-step-target="3">
                    <span class="public-edit-request-stepper__tab-index">3</span>
                    <span class="public-edit-request-stepper__tab-text">Pengaju & Konfirmasi</span>
                </button>
            </div>

            <div class="public-edit-request-stepper__body" data-public-edit-stepper-body>
                <section class="public-edit-request-step is-active" data-step-panel="1">
                    <div class="public-edit-request-section-heading">
                        <h5>Perbarui profil utama</h5>
                        <p>Bagian ini dipakai jika nama, panggilan, urutan anak, tanggal lahir, atau kontak masih perlu diperbarui.</p>
                    </div>

                    <div class="public-edit-request-grid public-edit-request-grid--two">
                        <div class="public-edit-request-card">
                            <div class="public-edit-request-card__title">Profil inti</div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Nama lengkap</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}">
                                        <div class="public-edit-request-field-error" data-field-error="name"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Nama panggilan <span class="text-danger">*</span></label>
                                        <input type="text" name="nickname" class="form-control" value="{{ old('nickname', $user->nickname) }}" required>
                                        <div class="public-edit-request-field-error" data-field-error="nickname"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Jenis kelamin <span class="text-danger">*</span></label>
                                        <select name="gender_id" class="form-control" required>
                                            <option value="" {{ $selectedGenderId ? '' : 'selected' }} disabled>Pilih jenis kelamin</option>
                                            <option value="1" {{ (int) $selectedGenderId === 1 ? 'selected' : '' }}>Laki-laki</option>
                                            <option value="2" {{ (int) $selectedGenderId === 2 ? 'selected' : '' }}>Perempuan</option>
                                        </select>
                                        <div class="public-edit-request-field-error" data-field-error="gender_id"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Anak ke berapa</label>
                                        <input type="number" min="1" name="birth_order" class="form-control" value="{{ old('birth_order', $user->birth_order) }}">
                                        <div class="public-edit-request-field-help">Isi bila urutan anak di keluarga inti belum tepat.</div>
                                        <div class="public-edit-request-field-error" data-field-error="birth_order"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Tanggal lahir</label>
                                        <input type="date" name="dob" class="form-control" value="{{ old('dob', optional($user->dob)->format('Y-m-d')) }}">
                                        <div class="public-edit-request-field-help">Gunakan jika tanggal lengkap diketahui.</div>
                                        <div class="public-edit-request-field-error" data-field-error="dob"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Tahun lahir</label>
                                        <input type="text" name="yob" class="form-control" value="{{ old('yob', $user->yob) }}" placeholder="YYYY" inputmode="numeric">
                                        <div class="public-edit-request-field-help">Cukup isi tahun jika tanggal lengkap belum diketahui.</div>
                                        <div class="public-edit-request-field-error" data-field-error="yob"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="public-edit-request-card">
                            <div class="public-edit-request-card__title">Kontak & foto</div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group form-group-sm">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}">
                                        <div class="public-edit-request-field-error" data-field-error="email"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Telepon</label>
                                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                                        <div class="public-edit-request-field-error" data-field-error="phone"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Kota</label>
                                        <input type="text" name="city" class="form-control" value="{{ old('city', $user->city) }}">
                                        <div class="public-edit-request-field-error" data-field-error="city"></div>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-sm">
                                        <label>Alamat</label>
                                        <textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                                        <div class="public-edit-request-field-error" data-field-error="address"></div>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-sm">
                                        <label>Usulan foto baru</label>
                                        <input type="file" name="photo" class="form-control" accept="image/*">
                                        <p class="help-block">Foto akan dipotong persegi dan dikompres otomatis maksimal 200 KB.</p>
                                        <div class="public-edit-request-field-error" data-field-error="photo"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="public-edit-request-card public-edit-request-card--compact">
                        <label class="public-edit-request-toggle">
                            <input type="checkbox" name="is_deceased" value="1" {{ $deceasedChecked ? 'checked' : '' }} data-toggle-deceased>
                            <span>Anggota keluarga ini sudah meninggal</span>
                        </label>
                        <div class="public-edit-request-grid public-edit-request-grid--death {{ $deceasedChecked ? 'is-visible' : '' }}" data-deceased-fields>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Tanggal wafat</label>
                                        <input type="date" name="dod" class="form-control" value="{{ old('dod', optional($user->dod)->format('Y-m-d')) }}">
                                        <div class="public-edit-request-field-help">Gunakan jika tanggal lengkap diketahui.</div>
                                        <div class="public-edit-request-field-error" data-field-error="dod"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Tahun wafat</label>
                                        <input type="text" name="yod" class="form-control" value="{{ old('yod', $user->yod) }}" placeholder="YYYY" inputmode="numeric">
                                        <div class="public-edit-request-field-help">Cukup isi tahun jika tanggal lengkap belum diketahui.</div>
                                        <div class="public-edit-request-field-error" data-field-error="yod"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="public-edit-request-card public-edit-request-card--advanced">
                        <button
                            type="button"
                            class="public-edit-request-card__advanced-toggle"
                            data-advanced-toggle
                            aria-expanded="{{ $deceasedChecked ? 'true' : 'false' }}"
                        >
                            <span>Tambahkan detail lain</span>
                            <span class="public-edit-request-card__advanced-toggle-icon">+</span>
                        </button>
                        <div class="public-edit-request-card__advanced-body {{ $deceasedChecked ? 'is-open' : '' }}" data-advanced-body>
                            <div class="public-edit-request-card__title">Lokasi makam (opsional)</div>
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
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group form-group-sm">
                                        <label>Nama lokasi makam</label>
                                        <input type="text" name="cemetery_location_name" class="form-control" value="{{ old('cemetery_location_name', $user->getMetadata('cemetery_location_name')) }}">
                                        <div class="public-edit-request-field-error" data-field-error="cemetery_location_name"></div>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group form-group-sm">
                                        <label>Alamat makam</label>
                                        <textarea name="cemetery_location_address" class="form-control" rows="2">{{ old('cemetery_location_address', $user->getMetadata('cemetery_location_address')) }}</textarea>
                                        <div class="public-edit-request-field-error" data-field-error="cemetery_location_address"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Latitude</label>
                                        <input type="text" name="cemetery_location_latitude" class="form-control" value="{{ old('cemetery_location_latitude', $user->getMetadata('cemetery_location_latitude')) }}">
                                        <div class="public-edit-request-field-error" data-field-error="cemetery_location_latitude"></div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group form-group-sm">
                                        <label>Longitude</label>
                                        <input type="text" name="cemetery_location_longitude" class="form-control" value="{{ old('cemetery_location_longitude', $user->getMetadata('cemetery_location_longitude')) }}">
                                        <div class="public-edit-request-field-error" data-field-error="cemetery_location_longitude"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="public-edit-request-step" data-step-panel="2">
                    <div class="public-edit-request-section-heading">
                        <h5>Tambah relasi keluarga</h5>
                        <p>Isi bagian ini jika pasangan atau anak belum tercatat. Tambahkan hanya yang benar-benar perlu diusulkan.</p>
                    </div>

                    <div class="public-edit-request-card">
                        <div class="public-edit-request-card__header">
                            <div>
                                <div class="public-edit-request-card__title">Tambah pasangan</div>
                                <p class="public-edit-request-card__hint">Gunakan bagian ini jika pasangan belum ada di data keluarga.</p>
                            </div>
                            <button type="button" class="btn btn-default public-edit-request-card__action" data-add-spouse>
                                + Tambah pasangan
                            </button>
                        </div>
                        <div data-spouse-list></div>
                    </div>

                    <div class="public-edit-request-card">
                        <div class="public-edit-request-card__header">
                            <div>
                                <div class="public-edit-request-card__title">Tambah anak</div>
                                <p class="public-edit-request-card__hint">Setelah anak ditambahkan, pilih pasangan yang terkait jika datanya memang sudah ada atau sedang Anda usulkan di sini.</p>
                            </div>
                            <button type="button" class="btn btn-default public-edit-request-card__action" data-add-child>
                                + Tambah anak
                            </button>
                        </div>
                        <div data-child-list></div>
                    </div>
                </section>

                <section class="public-edit-request-step" data-step-panel="3">
                    <div class="public-edit-request-section-heading">
                        <h5>Identitas pengaju</h5>
                        <p>Isi identitas Anda agar admin mudah menghubungi jika ada data yang perlu dikonfirmasi.</p>
                    </div>

                    <div class="public-edit-request-grid public-edit-request-grid--two">
                        <div class="public-edit-request-card">
                            <div class="public-edit-request-card__title">Pengaju</div>
                            <div class="form-group form-group-sm">
                                <label>Nama pengaju <span class="text-danger">*</span></label>
                                <input type="text" name="requester_name" class="form-control" value="{{ old('requester_name') }}" required>
                                <div class="public-edit-request-field-error" data-field-error="requester_name"></div>
                            </div>
                            <div class="form-group form-group-sm">
                                <label>Nomor WA aktif <span class="text-danger">*</span></label>
                                <input type="text" name="requester_whatsapp" class="form-control" value="{{ old('requester_whatsapp') }}" required>
                                <div class="public-edit-request-field-help">Gunakan nomor yang aktif agar admin bisa menghubungi Anda bila perlu.</div>
                                <div class="public-edit-request-field-error" data-field-error="requester_whatsapp"></div>
                            </div>
                        </div>

                        <div class="public-edit-request-card public-edit-request-card--summary">
                            <div class="public-edit-request-card__title">Ringkasan usulan</div>
                            <ul class="public-edit-request-summary" data-public-edit-summary>
                                <li>Belum ada perubahan yang terdeteksi.</li>
                            </ul>
                            <div class="public-edit-request-summary__footnote">
                                Setelah dikirim, usulan akan menunggu peninjauan admin sebelum tampil pada data live.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="public-edit-request-form__footer" data-public-edit-footer>
            <div class="public-edit-request-form__scroll-hint" data-public-edit-scroll-hint>
                Geser ke bawah untuk melihat seluruh isi langkah ini.
            </div>
            <div class="public-edit-request-form__footer-actions">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-default" data-step-back style="display:none;">Kembali</button>
                <button type="button" class="btn btn-primary" data-step-next>Lanjut</button>
                <button type="submit" class="btn btn-success" data-step-submit style="display:none;">Kirim untuk Ditinjau Admin</button>
            </div>
        </div>
    </div>
</form>
