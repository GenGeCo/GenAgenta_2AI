<?php
/**
 * Delegate To Engineer Tool
 *
 * Permette ad Agea di delegare task complessi all'Ingegnere.
 */

namespace GenAgenta\Tools;

use Inspector\Neuron\Tool;

class DelegateToEngineerTool extends Tool
{
    protected string $name = 'delegate_to_engineer';
    protected string $description = 'Delega una richiesta complessa all\'Ingegnere (Gemini Pro) che ha accesso al database';

    protected array $parameters = [
        'type' => 'object',
        'properties' => [
            'task' => [
                'type' => 'string',
                'description' => 'Descrizione chiara e dettagliata del task da eseguire'
            ]
        ],
        'required' => ['task']
    ];

    public function execute(array $arguments): array
    {
        $task = $arguments['task'] ?? '';

        // Questo tool non esegue direttamente, ma segnala la necessità di delegazione
        // La gestione vera sarà fatta nel controller
        return [
            'delegated' => true,
            'task' => $task,
            'message' => 'Task delegato all\'Ingegnere'
        ];
    }
}
