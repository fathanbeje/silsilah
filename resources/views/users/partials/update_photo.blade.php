<div class="panel panel-default photo-upload-panel" data-photo-upload-panel>
    <div class="panel-heading">
        <h3 class="panel-title">{{ __('user.update_photo') }}</h3>
    </div>
    {{ Form::open(['route' => ['users.photo-upload', $user], 'method' => 'patch', 'files' => true]) }}
    <div class="panel-body text-center">
        {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
    </div>
    <div class="panel-body">
        {!! FormField::file('photo', ['required' => true, 'accept' => 'image/*', 'label' => __('user.reupload_photo'), 'info' => ['text' => __('user.upload_photo_notes'), 'class' => 'warning'], 'id' => 'photo-upload-'.$user->id]) !!}
        <div class="photo-paste-area" tabindex="0" aria-label="Tempel gambar dari clipboard">
            <strong>Tempel gambar di sini</strong>
            <div class="text-muted small">Klik area ini untuk fokus, lalu tekan Ctrl+V. Jika ingin pilih file, gunakan tombol di atas.</div>
            <div class="photo-paste-status small">Belum ada gambar dipilih.</div>
        </div>
    </div>
    <div class="panel-footer">
        {!! Form::submit(__('user.update_photo'), ['class' => 'btn btn-success']) !!}
        {{ link_to_route('users.show', __('app.cancel'), [$user], ['class' => 'btn btn-default']) }}
    </div>
    {{ Form::close() }}
</div>

@once
<style>
    .photo-paste-area {
        margin-top: 15px;
        padding: 14px 16px;
        border: 2px dashed #c8d2dc;
        border-radius: 10px;
        background: #f8fafc;
        text-align: center;
        cursor: default;
        transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
    }

    .photo-paste-area:hover,
    .photo-paste-area:focus,
    .photo-paste-area.is-active {
        border-color: #3c8dbc;
        background: #eef6fb;
        box-shadow: 0 0 0 3px rgba(60, 141, 188, 0.12);
        outline: none;
    }

    .photo-paste-status {
        margin-top: 8px;
    }
</style>
@endonce

@once
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-photo-upload-panel]').forEach(function (panel) {
            if (panel.dataset.photoUploadInitialized === 'true') {
                return;
            }

            panel.dataset.photoUploadInitialized = 'true';

            var photoInput = panel.querySelector('input[type="file"]');
            var pasteArea = panel.querySelector('.photo-paste-area');
            var pasteStatus = panel.querySelector('.photo-paste-status');
            var submitBtn = panel.querySelector('button[type="submit"], input[type="submit"]');

            if (photoInput) {
                photoInput.addEventListener('change', function (e) {
                    prepareSelectedImage(e.target.files && e.target.files[0]);
                });
            }

            if (pasteArea && photoInput) {
                pasteArea.addEventListener('click', function () {
                    pasteArea.focus();
                });

                pasteArea.addEventListener('paste', function (e) {
                    var items = e.clipboardData && e.clipboardData.items;
                    if (!items) return;

                    for (var i = 0; i < items.length; i++) {
                        var item = items[i];
                        if (item.kind === 'file' && item.type.match(/image.*/)) {
                            e.preventDefault();
                            prepareSelectedImage(item.getAsFile(), 'clipboard');
                            return;
                        }
                    }

                    setPasteStatus('Clipboard tidak berisi gambar.');
                });

                pasteArea.addEventListener('dragenter', function () {
                    pasteArea.classList.add('is-active');
                });

                pasteArea.addEventListener('dragleave', function () {
                    pasteArea.classList.remove('is-active');
                });

                pasteArea.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    pasteArea.classList.add('is-active');
                });

                pasteArea.addEventListener('drop', function (e) {
                    e.preventDefault();
                    pasteArea.classList.remove('is-active');

                    var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file) {
                        prepareSelectedImage(file, 'drop');
                    }
                });
            }

            function prepareSelectedImage(file, source) {
                if (!file) return;
                if (!file.type.match(/image.*/)) {
                    setPasteStatus('File harus berupa gambar.');
                    return;
                }

                var originalText = submitBtn ? submitBtn.value || submitBtn.textContent : '';
                if (submitBtn) {
                    submitBtn.dataset.originalText = originalText;
                    if ('value' in submitBtn) {
                        submitBtn.value = 'Compressing...';
                    } else {
                        submitBtn.textContent = 'Compressing...';
                    }
                    submitBtn.disabled = true;
                }

                setPasteStatus('Memproses gambar' + (source === 'clipboard' ? ' dari clipboard' : '') + '...');

                var reader = new FileReader();
                reader.onload = function (readerEvent) {
                    var image = new Image();
                    image.onload = function () {
                        var canvas = document.createElement('canvas');
                        var size = 800;
                        var square = Math.min(image.width, image.height);
                        var sx = (image.width - square) / 2;
                        var sy = (image.height - square) / 2;
                        var quality = 0.85;
                        var dataUrl;
                        var blob;

                        canvas.width = size;
                        canvas.height = size;
                        canvas.getContext('2d').drawImage(image, sx, sy, square, square, 0, 0, size, size);

                        do {
                            dataUrl = canvas.toDataURL('image/jpeg', quality);
                            blob = dataURLToBlob(dataUrl);

                            if (blob.size <= 200 * 1024 || quality <= 0.45) {
                                break;
                            }

                            quality -= 0.05;
                        } while (quality >= 0.45);

                        var baseName = (file.name || 'clipboard-image').replace(/\.[^.]+$/, '') || 'clipboard-image';
                        var compressedFile = new File([blob], baseName + '.jpg', {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });

                        assignFileToInput(compressedFile);
                        restoreSubmitButton();
                        setPasteStatus('Siap upload: ' + compressedFile.name + ' (' + formatFileSize(compressedFile.size) + ')');
                    };

                    image.onerror = function () {
                        restoreSubmitButton();
                        setPasteStatus('Gagal membaca gambar.');
                    };
                    image.src = readerEvent.target.result;
                };
                reader.readAsDataURL(file);
            }

            function assignFileToInput(file) {
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                photoInput.files = dataTransfer.files;
            }

            function setPasteStatus(message) {
                if (pasteStatus) {
                    pasteStatus.textContent = message;
                }
            }

            function restoreSubmitButton() {
                if (!submitBtn) {
                    return;
                }

                submitBtn.disabled = false;
                var originalText = submitBtn.dataset.originalText || '{{ __('user.update_photo') }}';
                if ('value' in submitBtn) {
                    submitBtn.value = originalText;
                } else {
                    submitBtn.textContent = originalText;
                }
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
        });
    });
</script>
@endonce
