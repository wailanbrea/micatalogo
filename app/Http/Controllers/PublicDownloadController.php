<?php

namespace App\Http\Controllers;

use App\Services\QrCodeSvgService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDownloadController extends Controller
{
    public function index(QrCodeSvgService $qrCodeService): View
    {
        $update = config('bspos.android_update', []);
        $androidReleases = $update['version_code'] > 0 ? [$update] : [];
        $downloadUrl = $androidReleases !== []
            ? route('downloads.android', $update['version_code'])
            : url('/descargas');

        return view('downloads.index', [
            'androidReleases' => $androidReleases,
            'downloadCount' => $this->downloadCount($update),
            'qrSvg' => $qrCodeService->generateSvg($downloadUrl, 280),
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
