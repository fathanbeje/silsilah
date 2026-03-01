<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ __('user.update_photo') }}</h3>
    </div>
    {{ Form::open(['route' => ['users.photo-upload', $user], 'method' => 'patch', 'files' => true]) }}
    <div class="panel-body text-center">
        {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
    </div>
    <div class="panel-body">
        {!! FormField::file('photo', ['required' => true, 'label' => __('user.reupload_photo'), 'info' => ['text' => __('user.upload_photo_notes'), 'class' => 'warning']]) !!}
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
                        var canvas = document.createElement('canvas'),
                            max_size = 800,
                            width = image.width,
                            height = image.height;

                        if (width > height) {
                            if (width > max_size) {
                                height *= max_size / width;
                                width = max_size;
                            }
                        } else {
                            if (height > max_size) {
                                width *= max_size / height;
                                height = max_size;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        canvas.getContext('2d').drawImage(image, 0, 0, width, height);

                        var dataUrl = canvas.toDataURL('image/jpeg', 0.8);

                        fetch(dataUrl)
                            .then(function (res) { return res.blob(); })
                            .then(function (blob) {
                                var compressedFile = new File([blob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });

                                var dataTransfer = new DataTransfer();
                                dataTransfer.items.add(compressedFile);
                                photoInput.files = dataTransfer.files;

                                submitBtn.disabled = false;
                                submitBtn.value = originalText;
                            });
                    }
                    image.src = readerEvent.target.result;
                }
                reader.readAsDataURL(file);
            });
        }
    });
</script>