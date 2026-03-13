<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ __('user.update_photo') }}</h3>
    </div>
    {{ Form::open(['route' => ['users.photo-upload', $user], 'method' => 'patch', 'files' => true]) }}
    <div class="panel-body text-center">
        {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
    </div>
    <div class="panel-body">
        {!! FormField::file('photo', ['required' => true, 'accept' => 'image/*', 'label' => __('user.reupload_photo'), 'info' => ['text' => __('user.upload_photo_notes'), 'class' => 'warning']]) !!}
        <div class="photo-paste-area" id="photo-paste-area" tabindex="0" aria-label="Tempel gambar dari clipboard">
            <strong>Tempel gambar di sini</strong>
            <div class="text-muted small">Klik area ini lalu tekan Ctrl+V, atau klik untuk memilih file.</div>
            <div class="photo-paste-status small" id="photo-paste-status">Belum ada gambar dipilih.</div>
        </div>
    </div>
    <div class="panel-footer">
        {!! Form::submit(__('user.update_photo'), ['class' => 'btn btn-success']) !!}
        {{ link_to_route('users.show', __('app.cancel'), [$user], ['class' => 'btn btn-default']) }}
    </div>
    {{ Form::close() }}
</div>

<style>
    .photo-paste-area {
        margin-top: 15px;
        padding: 14px 16px;
        border: 2px dashed #c8d2dc;
        border-radius: 10px;
        background: #f8fafc;
        text-align: center;
        cursor: pointer;
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var photoInput = document.getElementById('photo');
        var pasteArea = document.getElementById('photo-paste-area');
        var pasteStatus = document.getElementById('photo-paste-status');
        if (photoInput) {
            photoInput.addEventListener('change', function (e) {
                prepareSelectedImage(e.target.files && e.target.files[0]);
            });
        }

        if (pasteArea && photoInput) {
            pasteArea.addEventListener('click', function () {
                photoInput.click();
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

            var submitBtn = photoInput.closest('form').querySelector('input[type="submit"]');
            var originalText = submitBtn.value;
            submitBtn.value = 'Compressing...';
            submitBtn.disabled = true;
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

                    var compressedFile = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });

                    assignFileToInput(compressedFile);
                    submitBtn.disabled = false;
                    submitBtn.value = originalText;
                    setPasteStatus('Siap upload: ' + compressedFile.name + ' (' + formatFileSize(compressedFile.size) + ')');
                };

                image.onerror = function () {
                    submitBtn.disabled = false;
                    submitBtn.value = originalText;
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
</script>
