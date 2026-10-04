<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimumVersionCode = (int) config('bspos.android_update.minimum_supported_version_code', 0);
        $installedVersionCode = $request->header('X-MiCatalogo-Version-Code');

        if ($minimumVersionCode > 0 && (! is_string($installedVersionCode) || ! ctype_digit($installedVersionCode) || (int) $installedVersionCode < $minimumVersionCode)) {
            return new JsonResponse([
                'message' => 'Esta versión de MiCatalogo ya no es compatible. Actualiza la aplicación para continuar.',
                'update_required' => true,
                'minimum_supported_version_code' => $minimumVersionCode,
                'update_manifest_url' => url('/api/v1/app-updates/android'),
            ], 426, [
                'X-MiCatalogo-Minimum-Version-Code' => (string) $minimumVersionCode,
            ]);
        }

        return $next($request);
    }
}
