<?php

namespace App\Http\Controllers;

use App\AppSetting;
use Illuminate\Http\Request;

class AppSettingsController extends Controller
{
    public function index()
    {
        return view('app-settings.index', [
            'headerName' => app_setting('site_header_name', config('app.name')),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_header_name' => 'required|string|max:120',
        ]);

        AppSetting::query()->updateOrCreate(
            ['key' => 'site_header_name'],
            ['value' => trim($validated['site_header_name'])]
        );

        return back()->with('status', 'Nama header berhasil diperbarui.');
    }
}
