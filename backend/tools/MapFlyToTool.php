<?php
/**
 * Map Fly To Tool
 *
 * Permette all'AI di spostare la vista della mappa.
 */

namespace GenAgenta\Tools;

use Inspector\Neuron\Tool;

class MapFlyToTool extends Tool
{
    protected string $name = 'map_fly_to';
    protected string $description = 'Sposta la vista della mappa a coordinate specifiche con animazione';

    protected array $parameters = [
        'type' => 'object',
        'properties' => [
            'lat' => [
                'type' => 'number',
                'description' => 'Latitudine (es: 45.4642 per Milano)'
            ],
            'lng' => [
                'type' => 'number',
                'description' => 'Longitudine (es: 9.1900 per Milano)'
            ],
            'zoom' => [
                'type' => 'number',
                'description' => 'Livello di zoom (1-20, default 12)',
                'default' => 12
            ]
        ],
        'required' => ['lat', 'lng']
    ];

    public function execute(array $arguments): array
    {
        $lat = $arguments['lat'] ?? null;
        $lng = $arguments['lng'] ?? null;
        $zoom = $arguments['zoom'] ?? 12;

        return [
            'action' => 'map_fly_to',
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => $zoom,
            'message' => "Spostamento mappa a {$lat}, {$lng} (zoom {$zoom})"
        ];
    }
}
