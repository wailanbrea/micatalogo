<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDownloadController extends Controller
{
    public function index(): View
    {
        $update = config('bspos.android_update', []);

        return view('downloads.index', [
            'androidReleases' => $update['version_code'] > 0 ? [$update] : [],
            'downloadCount' => $this->downloadCount($update),
        ]);
    }

    public function android(Request $request, int $versionCode): RedirectResponse
    {
        $update = config('bspos.android_update', []);

        abort_unless((int) ($update['version_code'] ?? 0) === $versionCode, 404);
        abort_unless(Str::startsWith((string) ($update['apk_url'] ?? ''), 'https://'), 404);

        $key = $this->downloadKey($versionCode);
        Cache::add($key, 0, now()->addYears(10));
        Cache::increment($key);

        return redirect()->away($update['apk_url']);
    }

    private function downloadCount(array $update): int
    {
        return (int) Cache::get($this->downloadKey((int) ($update['version_code'] ?? 0)), 0);
    }

    private function downloadKey(int $versionCode): string
    {
        return "android-downloads.{$versionCode}";
    }
}
