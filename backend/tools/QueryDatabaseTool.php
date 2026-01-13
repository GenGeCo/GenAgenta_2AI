<?php
/**
 * Query Database Tool
 *
 * Permette agli Agent di eseguire query SQL SELECT sul database.
 */

namespace GenAgenta\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use PDO;
use Exception;

class QueryDatabaseTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'query_database',
            'Esegue una query SQL di SOLA LETTURA sul database MySQL'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'sql',
                type: PropertyType::STRING,
                description: 'Query SQL SELECT (no INSERT/UPDATE/DELETE)',
                required: true
            ),
            new ToolProperty(
                name: 'reason',
                type: PropertyType::STRING,
                description: 'Breve spiegazione del perché serve questa query',
                required: false
            ),
        ];
    }

    public function __invoke(string $sql, ?string $reason = null): string
    {
        error_log("QueryDatabaseTool: {$reason} | SQL: {$sql}");

        if (!preg_match('/^\s*SELECT/i', trim($sql))) {
            return json_encode([
                'success' => false,
                'error' => 'Solo query SELECT sono permesse per motivi di sicurezza'
            ]);
        }

        try {
            $db = $this->getDB();
            $stmt = $db->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'success' => true,
                'rows' => $results,
                'count' => count($results),
                'query' => $sql
            ]);
        } catch (Exception $e) {
            error_log("QueryDatabaseTool Error: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $sql
            ]);
        }
    }

    protected function getDB(): PDO
    {
        require_once __DIR__ . '/../config/database.php';
        return getDB();
    }
}
