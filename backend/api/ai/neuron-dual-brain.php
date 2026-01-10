<?php
/**
 * POST /api/ai/neuron-dual-brain
 *
 * Dual Brain Architecture con Neuron AI + SSE Streaming
 * - Agea (Flash): conversazione veloce, decisioni routing
 * - Ingegnere (Pro): analisi profonde, query DB complesse
 * - Streaming real-time con Server-Sent Events
 */

// Headers per SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx: disabilita buffering

// Flush immediato
@ob_end_clean();
@ob_implicit_flush(true);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);

// Carica autoloader e config
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/helpers.php';

use Inspector\Neuron\Configuration;
use GenAgenta\Agents\AgeaAgent;
use GenAgenta\Agents\IngegnereAgent;
use GenAgenta\Tools\QueryDatabaseTool;
use GenAgenta\Tools\DelegateToEngineerTool;
use GenAgenta\Tools\MapFlyToTool;
use GenAgenta\Tools\MapSelectEntityTool;

/**
 * Invia evento SSE
 */
function sendSSE(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

/**
 * Invia messaggio di testo (può essere streamato token by token in futuro)
 */
function sendMessage(string $text, string $source = 'agea'): void
{
    sendSSE('message', [
        'type' => 'text',
        'content' => $text,
        'source' => $source,
        'timestamp' => time()
    ]);
}

/**
 * Invia tool call
 */
function sendToolCall(string $toolName, array $args, string $result = null): void
{
    sendSSE('tool_call', [
        'tool' => $toolName,
        'arguments' => $args,
        'result' => $result,
        'timestamp' => time()
    ]);
}

try {
    // Leggi input
    $data = getJsonBody();
    $message = $data['message'] ?? '';
    $context = $data['context'] ?? [];

    if (empty($message)) {
        sendSSE('error', ['message' => 'Messaggio vuoto']);
        exit;
    }

    // Carica config Gemini
    $config = require __DIR__ . '/../../config/config.php';
    $apiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

    if (!$apiKey) {
        sendSSE('error', ['message' => 'API Key mancante']);
        exit;
    }

    // Configura Neuron AI
    $neuronConfig = new Configuration();
    $neuronConfig->setApiKey($apiKey);
    $neuronConfig->setProvider('gemini'); // Gemini API

    sendSSE('run_started', ['agent' => 'agea', 'message' => $message]);

    // ============================================
    // FASE 1: AGEA valuta la richiesta
    // ============================================

    $agea = new AgeaAgent($neuronConfig);

    // Registra tools per Agea
    $agea->registerTool(new DelegateToEngineerTool());
    $agea->registerTool(new QueryDatabaseTool());
    $agea->registerTool(new MapFlyToTool());
    $agea->registerTool(new MapSelectEntityTool());

    sendSSE('agent_thinking', ['agent' => 'agea']);

    // Processa con Agea
    $ageaResult = $agea->processMessage($message, $context);

    // Se Agea ha chiamato tools, invia gli eventi
    if (!empty($ageaResult['tool_calls'])) {
        foreach ($ageaResult['tool_calls'] as $toolCall) {
            sendToolCall(
                $toolCall['name'],
                $toolCall['arguments'],
                json_encode($toolCall['result'] ?? null)
            );
        }
    }

    // Se Agea NON ha delegato, risposta diretta
    if (!$ageaResult['delegated']) {
        sendMessage($ageaResult['response'], 'agea');
        sendSSE('run_finished', [
            'agent' => 'agea',
            'delegated' => false
        ]);
        exit;
    }

    // ============================================
    // FASE 2: INGEGNERE con analisi profonda
    // ============================================

    sendSSE('delegation', ['from' => 'agea', 'to' => 'engineer']);
    sendSSE('agent_thinking', ['agent' => 'engineer']);

    // Trova il task delegato
    $delegatedTask = '';
    foreach ($ageaResult['tool_calls'] as $toolCall) {
        if ($toolCall['name'] === 'delegate_to_engineer') {
            $delegatedTask = $toolCall['arguments']['task'] ?? $message;
            break;
        }
    }

    $engineer = new IngegnereAgent($neuronConfig);

    // Registra tools per Ingegnere
    $engineer->registerTool(new QueryDatabaseTool());

    // Processa con Ingegnere
    $engineerResult = $engineer->processTask($delegatedTask, $message, $context);

    // Invia tool calls dell'Ingegnere
    if (!empty($engineerResult['tool_calls'])) {
        foreach ($engineerResult['tool_calls'] as $toolCall) {
            sendToolCall(
                $toolCall['name'],
                $toolCall['arguments'],
                json_encode($toolCall['result'] ?? null)
            );
        }
    }

    // Risposta finale dell'Ingegnere
    sendMessage($engineerResult['response'], 'engineer');

    sendSSE('run_finished', [
        'agent' => 'engineer',
        'delegated' => true,
        'queries_executed' => $engineerResult['queries_executed'] ?? 0
    ]);

} catch (Exception $e) {
    error_log("Neuron Dual Brain Error: " . $e->getMessage());
    sendSSE('error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
