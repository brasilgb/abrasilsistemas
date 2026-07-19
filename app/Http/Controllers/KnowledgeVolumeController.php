<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeVolumeController extends Controller
{
    public function __invoke(string $volume): Response
    {
        $configuredVolumes = config('knowledge.volumes');
        abort_unless(is_array($configuredVolumes), 500);

        $volumeData = [];

        foreach ($configuredVolumes as $configuredVolume) {
            if (is_array($configuredVolume)) {
                $volumeData[] = $configuredVolume;
            }
        }

        $volumes = new Collection($volumeData);
        $index = $volumes->search(fn (array $item) => $item['slug'] === $volume);

        abort_if($index === false, 404);

        return Inertia::render('knowledge-volume', [
            'collection' => config('knowledge.collection'),
            'volume' => [
                ...$volumes->get($index),
                'number' => $index + 1,
            ],
            'previousVolume' => $index > 0 ? $this->reference($volumes->get($index - 1), $index) : null,
            'nextVolume' => $index < $volumes->count() - 1 ? $this->reference($volumes->get($index + 1), $index + 2) : null,
            'ebook' => Ebook::query()
                ->where('volume_slug', $volume)
                ->where('status', 'published')
                ->first(['id', 'title', 'price_cents', 'currency']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $volume
     * @return array<string, mixed>
     */
    private function reference(array $volume, int $number): array
    {
        return [
            'number' => $number,
            'slug' => $volume['slug'],
            'title' => $volume['title'],
        ];
    }
}
