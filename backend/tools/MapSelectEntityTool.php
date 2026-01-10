<?php
/**
 * Map Select Entity Tool
 *
 * Permette all'AI di selezionare un'entità sulla mappa.
 */

namespace GenAgenta\Tools;

use Inspector\Neuron\Tool;

class MapSelectEntityTool extends Tool
{
    protected string $name = 'map_select_entity';
    protected string $description = 'Seleziona un\'entità sulla mappa (cantiere, cliente, fornitore, ecc.)';

    protected array $parameters = [
        'type' => 'object',
        'properties' => [
            'entity_id' => [
                'type' => 'string',
                'description' => 'ID univoco dell\'entità da selezionare'
            ]
        ],
        'required' => ['entity_id']
    ];

    public function execute(array $arguments): array
    {
        $entityId = $arguments['entity_id'] ?? null;

        return [
            'action' => 'map_select_entity',
            'entity_id' => $entityId,
            'message' => "Selezione entità {$entityId}"
        ];
    }
}
