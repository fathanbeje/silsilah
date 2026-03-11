<div class="user-edit-request-detail">
    <div class="user-edit-request-detail__meta">
        <div>
            <strong>{{ optional($item->targetUser)->display_name ?: '-' }}</strong>
            <div class="text-muted">{{ optional($item->targetUser)->nickname ?: '-' }}</div>
        </div>
        <div class="text-right">
            <span class="label label-{{ $item->status === 'pending' ? 'warning' : ($item->status === 'approved' ? 'success' : 'default') }}">{{ strtoupper($item->status) }}</span>
            <div class="small text-muted">{{ optional($item->submitted_at ?: $item->created_at)->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Pengaju</strong></div>
        <div class="panel-body">
            <div><strong>{{ $item->requester_name }}</strong></div>
            <div>WA: {{ $item->requester_whatsapp }}</div>
            @if ($item->review_notes)
            <hr>
            <div><strong>Catatan reviewer:</strong></div>
            <div>{{ $item->review_notes }}</div>
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Perubahan Profil</strong></div>
        <div class="panel-body">
            @if ($profileDiffs->isEmpty())
                <div class="text-muted">Tidak ada perubahan profil.</div>
            @else
                <table class="table table-condensed">
                    <thead><tr><th>Field</th><th>Data live</th><th>Usulan baru</th></tr></thead>
                    <tbody>
                        @foreach ($profileDiffs as $diff)
                        <tr>
                            <td>{{ $diff['label'] }}</td>
                            <td>{{ $diff['current'] }}</td>
                            <td><strong>{{ $diff['proposed'] }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Metadata Makam</strong></div>
        <div class="panel-body">
            @if ($metadataDiffs->isEmpty())
                <div class="text-muted">Tidak ada perubahan metadata makam.</div>
            @else
                <table class="table table-condensed">
                    <thead><tr><th>Field</th><th>Data live</th><th>Usulan baru</th></tr></thead>
                    <tbody>
                        @foreach ($metadataDiffs as $diff)
                        <tr>
                            <td>{{ $diff['label'] }}</td>
                            <td>{{ $diff['current'] }}</td>
                            <td><strong>{{ $diff['proposed'] }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Foto</strong></div>
        <div class="panel-body">
            <div class="row">
                <div class="col-xs-6 text-center">
                    <div class="small text-muted">Foto live</div>
                    {{ userPhoto($item->targetUser, ['style' => 'width:100%;max-width:160px;border-radius:16px;']) }}
                </div>
                <div class="col-xs-6 text-center">
                    <div class="small text-muted">Foto usulan</div>
                    @if ($item->proposed_photo_path && storedPublicFileUrl($item->proposed_photo_path))
                    <img src="{{ storedPublicFileUrl($item->proposed_photo_path) }}" alt="Foto usulan" style="width:100%;max-width:160px;border-radius:16px;">
                    @else
                    <div class="text-muted" style="padding-top:60px;">Tidak ada foto baru.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Pasangan Baru</strong></div>
        <div class="panel-body">
            @if ($newSpouses->isEmpty())
                <div class="text-muted">Tidak ada usulan pasangan baru.</div>
            @else
                @foreach ($newSpouses as $spouse)
                <div class="request-repeat-card request-repeat-card--static">
                    <strong>{{ $spouse['name'] }}</strong>
                    <div>Nama panggilan: {{ $spouse['nickname'] }}</div>
                    <div>Tgl nikah: {{ $spouse['marriage_date'] ?: '-' }}</div>
                    <div>Tgl/Tahun lahir: {{ $spouse['dob'] ?: '-' }} / {{ $spouse['yob'] ?: '-' }}</div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Anak Baru</strong></div>
        <div class="panel-body">
            @if ($newChildren->isEmpty())
                <div class="text-muted">Tidak ada usulan anak baru.</div>
            @else
                @foreach ($newChildren as $child)
                <div class="request-repeat-card request-repeat-card--static">
                    <strong>{{ $child['name'] }}</strong>
                    <div>Nama panggilan: {{ $child['nickname'] }}</div>
                    <div>Jenis kelamin: {{ (int) $child['gender_id'] === 1 ? 'Laki-laki' : 'Perempuan' }}</div>
                    <div>Urutan lahir: {{ $child['birth_order'] ?: '-' }}</div>
                    <div>Tgl/Tahun lahir: {{ $child['dob'] ?: '-' }} / {{ $child['yob'] ?: '-' }}</div>
                    <div>Konteks pasangan: {{ $child['spouse_context_label'] }}</div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    @if ($item->isPending())
    <form method="POST" action="{{ route('user-edit-requests.update', $item) }}">
        {{ csrf_field() }}
        {{ method_field('PATCH') }}
        <div class="form-group">
            <label>Catatan reviewer</label>
            <textarea name="review_notes" class="form-control" rows="3"></textarea>
        </div>
        <div class="text-right">
            <button type="submit" name="action" value="reject" class="btn btn-default">Tolak</button>
            <button type="submit" name="action" value="approve" class="btn btn-success">Setujui dan Terapkan</button>
        </div>
    </form>
    @endif
</div>
