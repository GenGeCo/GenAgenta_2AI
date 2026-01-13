<?php
/**
 * Delegate To Engineer Tool
 *
 * Permette ad Agea di delegare task complessi all'Ingegnere.
 */

namespace GenAgenta\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class DelegateToEngineerTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'delegate_to_engineer',
            'Delega una richiesta complessa all\'Ingegnere (Gemini Pro) che ha accesso al database'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'task',
                type: PropertyType::STRING,
                description: 'Descrizione chiara e dettagliata del task da eseguire',
                required: true
            ),
        ];
    }

    public function __invoke(string $task): string
    {
        return json_encode([
            'delegated' => true,
            'task' => $task,
            'message' => 'Task delegato all\'Ingegnere'
        ]);
    }
}
