<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PingResource;
use Illuminate\Support\Facades\Date;

/**
 * Proves the v1 group, the locale and the resource shape until step 1.4 brings real endpoints.
 */
final class PingController extends Controller
{
    public function __invoke(): PingResource
    {
        return new PingResource(Date::now());
    }
}
