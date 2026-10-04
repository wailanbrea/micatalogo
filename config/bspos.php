<?php

return [
    'android_update' => [
        'version_code' => (int) env('BSPOS_ANDROID_VERSION_CODE', 0),
        'version_name' => env('BSPOS_ANDROID_VERSION_NAME', ''),
        'minimum_supported_version_code' => (int) env('BSPOS_ANDROID_MINIMUM_SUPPORTED_VERSION_CODE', 0),
        'apk_url' => env('BSPOS_ANDROID_APK_URL', ''),
        'apk_sha256' => env('BSPOS_ANDROID_APK_SHA256', ''),
        'release_notes' => env('BSPOS_ANDROID_RELEASE_NOTES', ''),
    ],
];
