<div class="row">
    <div class="col-md-12">
        <div class="checkbox">
            <label>
                <input type="checkbox" name="is_deceased" value="1" {{ old('is_deceased', $user->isDeceased()) ? 'checked' : '' }}>
                {{ __('user.is_deceased') }}
            </label>
        </div>
    </div>
    <div class="col-md-6">{!! FormField::text('yod', ['label' => __('user.yod'), 'placeholder' => __('app.example').' 2003']) !!}</div>
    <div class="col-md-6">{!! FormField::text('dod', ['label' => __('user.dod'), 'placeholder' => __('app.example').' 2003-10-17', 'value' => old('dod', optional($user->dod)->format('Y-m-d'))]) !!}</div>
</div>

<fieldset>
    <legend>{{ __('user.cemetery_location') }}</legend>
    <div class="form-group">
        <label for="cemetery_location_select">{{ __('user.select_existing_cemetery_location') }}</label>
        <select id="cemetery_location_select" class="form-control js-cemetery-location-select">
            <option value="">{{ __('user.select_existing_cemetery_location') }}</option>
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
    {!! FormField::text('cemetery_location_name', ['label' => __('address.location_name'), 'value' => old('cemetery_location_name', $user->getMetadata('cemetery_location_name'))]) !!}
    {!! FormField::textarea('cemetery_location_address', ['label' => __('address.address'), 'value' => old('cemetery_location_address', $user->getMetadata('cemetery_location_address'))]) !!}
    <div class="row">
        <div class="col-md-6">{!! FormField::text('cemetery_location_latitude', ['label' => __('address.latitude'), 'value' => old('cemetery_location_latitude', $user->getMetadata('cemetery_location_latitude'))]) !!}</div>
        <div class="col-md-6">{!! FormField::text('cemetery_location_longitude', ['label' => __('address.longitude'), 'value' => old('cemetery_location_longitude', $user->getMetadata('cemetery_location_longitude'))]) !!}</div>
    </div>
</fieldset>
