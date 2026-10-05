<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Collection;

trait BuildsActivityFeed
{
    private function sortActivity(Collection ...$groups): array
    {
        return collect($groups)
            ->flatten(1)
            ->sortByDesc('sort_value')
            ->take(6)
            ->map(function (array $item): array {
                unset($item['sort_value']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function makeActivityItem(
        string $id,
        string $title,
        string $description,
        mixed $timestamp,
        string $tone,
    ): array {
        $sortValue = $timestamp?->getTimestamp() ?? 0;

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'timestamp' => $timestamp?->format('d M Y, h:i A'),
            'tone' => $tone,
            'sort_value' => $sortValue,
        ];
    }
}
