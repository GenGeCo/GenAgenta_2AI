<?php
/**
 * Map Fly To Tool
 *
 * Permette all'AI di spostare la vista della mappa.
 */

namespace GenAgenta\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class MapFlyToTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'fly_to',
            'Sposta la vista della mappa a una località specifica (città, indirizzo, coordinate)'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Nome della località (es: "Roma", "Milano Centro", "Via Roma 1, Napoli")',
                required: true
            ),
        ];
    }

    public function __invoke(string $query): string
    {
        return json_encode([
            'action' => 'fly_to',
            'query' => $query,
            'message' => "Spostamento mappa a: {$query}"
        ]);
    }
}
