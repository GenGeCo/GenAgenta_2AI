<?php
/**
 * Set Map Style Tool
 *
 * Permette all'AI di cambiare lo stile della mappa.
 */

namespace GenAgenta\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class SetMapStyleTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'set_map_style',
            'Cambia lo stile visivo della mappa (satellite, streets, dark, light)'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'style',
                type: PropertyType::STRING,
                description: 'Lo stile della mappa: "satellite", "streets", "dark", "light"',
                required: true,
                enum: ['satellite', 'streets', 'dark', 'light']
            ),
        ];
    }

    public function __invoke(string $style): string
    {
        return json_encode([
            'action' => 'set_map_style',
            'style' => $style,
            'message' => "Cambio stile mappa a: {$style}"
        ]);
    }
}
