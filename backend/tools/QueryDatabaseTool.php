<?php
/**
 * Query Database Tool
 *
 * Permette agli Agent di eseguire query SQL SELECT sul database.
 */

namespace GenAgenta\Tools;

use Inspector\Neuron\Tool;
use PDO;
use Exception;

class QueryDatabaseTool extends Tool
{
    protected string $name = 'query_database';
    protected string $description = 'Esegue una query SQL di SOLA LETTURA sul database MySQL';

    protected array $parameters = [
        'type' => 'object',
        'properties' => [
            'sql' => [
                'type' => 'string',
                'description' => 'Query SQL SELECT (no INSERT/UPDATE/DELETE)'
            ],
            'reason' => [
                'type' => 'string',
                'description' => 'Breve spiegazione del perché serve questa query'
            ]
        ],
        'required' => ['sql']
    ];

    public function execute(array $arguments): array
    {
        $sql = $arguments['sql'] ?? '';
        $reason = $arguments['reason'] ?? 'N/A';

        // Log della query per debugging
        error_log("QueryDatabaseTool: {$reason} | SQL: {$sql}");

        // Sicurezza: SOLO SELECT
        if (!preg_match('/^\s*SELECT/i', trim($sql))) {
            return [
                'success' => false,
                'error' => 'Solo query SELECT sono permesse per motivi di sicurezza'
            ];
        }

        try {
            $db = $this->getDB();
            $stmt = $db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'rows' => $results,
                'count' => count($results),
                'query' => $sql
            ];
        } catch (Exception $e) {
            error_log("QueryDatabaseTool Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $sql
            ];
        }
    }

    protected function getDB(): PDO
    {
        // Usa la stessa funzione del backend
        require_once __DIR__ . '/../config/database.php';
        return getDB();
    }
}
