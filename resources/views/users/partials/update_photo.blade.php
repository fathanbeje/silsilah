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
    </div>
    <div class="panel-footer">
        {!! Form::submit(__('user.update_photo'), ['class' => 'btn btn-success']) !!}
        {{ link_to_route('users.show', __('app.cancel'), [$user], ['class' => 'btn btn-default']) }}
    </div>
    {{ Form::close() }}
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var photoInput = document.getElementById('photo');
        if (photoInput) {
            photoInput.addEventListener('change', function (e) {
                var file = e.target.files[0];
                if (!file) return;

                if (!file.type.match(/image.*/)) return;

                var submitBtn = photoInput.closest('form').querySelector('input[type="submit"]');
                var originalText = submitBtn.value;
                submitBtn.value = 'Compressing...';
                submitBtn.disabled = true;

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

                        var dataTransfer = new DataTransfer();
                        dataTransfer.items.add(compressedFile);
                        photoInput.files = dataTransfer.files;

                        submitBtn.disabled = false;
                        submitBtn.value = originalText;
                    }
                    image.src = readerEvent.target.result;
                }
                reader.readAsDataURL(file);
            });
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
