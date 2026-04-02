<?php

namespace App\Services;

use App\Models\PortalSetting;

class PortalSettings
{
    private static array $defaults = [
        'max_reports_per_hour_per_ip' => 5,
        'max_file_size_mb'            => 10,
        'max_upload_per_week_mb'      => 50,
    ];

    public static function getInt(string $key): int
    {
        try {
            $setting = PortalSetting::find($key);
            return $setting ? (int) $setting->value : (int) (self::$defaults[$key] ?? 0);
        } catch (\Exception) {
            return (int) (self::$defaults[$key] ?? 0);
        }
    }
}
