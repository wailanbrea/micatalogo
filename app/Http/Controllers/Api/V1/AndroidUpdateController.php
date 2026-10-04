<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AndroidUpdateController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(config('bspos.android_update'));
    }
}
