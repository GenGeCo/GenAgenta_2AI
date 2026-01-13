<?php
/**
 * Map Select Entity Tool
 *
 * Permette all'AI di selezionare un'entità sulla mappa.
 */

namespace GenAgenta\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class MapSelectEntityTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'select_entity',
            'Seleziona un\'entità sulla mappa (cantiere, cliente, fornitore, ecc.)'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'entity_id',
                type: PropertyType::STRING,
                description: 'ID univoco dell\'entità da selezionare',
                required: true
            ),
        ];
    }

    public function __invoke(string $entity_id): string
    {
        return json_encode([
            'action' => 'select_entity',
            'entity_id' => $entity_id,
            'message' => "Selezione entità {$entity_id}"
        ]);
    }
}
