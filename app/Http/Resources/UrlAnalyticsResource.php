<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Url
 */
class UrlAnalyticsResource extends JsonResource
{
    public function __construct(
        \App\Models\Url $resource,
        private readonly array $analytics,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'shortCode' => $this->short_code,
            'totalClicks' => $this->analytics['totalClicks'],
            'clicksToday' => $this->analytics['clicksToday'],
            'clicksThisWeek' => $this->analytics['clicksThisWeek'],
            'lastAccessedAt' => $this->analytics['lastAccessedAt']?->toIso8601String(),
            'clicksByDate' => $this->analytics['clicksByDate'],
        ];
    }
}
