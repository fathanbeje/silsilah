<?php

namespace App\Http\Controllers;

use App\AppSetting;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AppSettingsController extends Controller
{
    public function index()
    {
        $currentHost = $this->currentHost();

        return view('app-settings.index', [
            'currentHost' => $currentHost,
            'headerName' => app_setting('site_header_name', config('app.name')),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_header_name' => 'required|string|max:120',
        ]);

        $currentHost = $this->currentHost();

        AppSetting::query()->updateOrCreate(
            [
                'host' => $currentHost,
                'key' => 'site_header_name',
            ],
            ['value' => trim($validated['site_header_name'])]
        );

        return back()->with('status', 'Nama header berhasil diperbarui.');
    }

    private function currentHost(): ?string
    {
        $host = request()->getHost();

        return filled($host) ? Str::lower(trim($host)) : null;
    }
}
