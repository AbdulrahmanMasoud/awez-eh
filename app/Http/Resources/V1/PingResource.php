<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * @property CarbonImmutable $resource the moment the API answered
 */
final class PingResource extends JsonResource
{
    /**
     * @return array{status: string, locale: string, time: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'ok',
            'locale' => App::getLocale(),
            'time' => $this->resource->utc()->toIso8601ZuluString(),
        ];
    }
}
