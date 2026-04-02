<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalSetting;
use Illuminate\Http\Request;

class SuperAdminSettingsController extends Controller
{
    private array $defaults = [
        'max_reports_per_hour_per_ip' => '5',
        'max_file_size_mb'            => '10',
        'max_upload_per_week_mb'      => '50',
    ];

    public function index()
    {
        $stored   = PortalSetting::all()->pluck('value', 'key')->toArray();
        $settings = array_merge($this->defaults, $stored);

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'max_reports_per_hour_per_ip' => 'required|integer|min:1|max:1000',
            'max_file_size_mb'            => 'required|integer|min:1|max:500',
            'max_upload_per_week_mb'      => 'required|integer|min:1|max:5000',
        ]);

        foreach ($validated as $key => $value) {
            PortalSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
