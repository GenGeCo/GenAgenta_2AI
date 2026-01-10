<?php
/**
 * CopilotKit Adapter per Dual Brain
 *
 * Adatta le richieste CopilotKit al nostro backend Dual Brain.
 * Supporta sia chiamate normali che streaming SSE.
 */

// Determina se è richiesto streaming
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$isStreaming = strpos($acceptHeader, 'text/event-stream') !== false;

if ($isStreaming) {
    // Headers per SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    @ob_end_clean();
    @ob_implicit_flush(true);
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', 0);
}

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';

$config = require __DIR__ . '/../../config/config.php';
$apiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

if (!$apiKey) {
    if ($isStreaming) {
        sendSSE('error', ['message' => 'API Key mancante']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API Key mancante']);
    }
    exit;
}

/**
 * Invia evento SSE
 */
function sendSSE(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Leggi input CopilotKit
$rawInput = file_get_contents('php://input');
error_log("CopilotAdapter: Raw input: " . $rawInput);

$data = json_decode($rawInput, true) ?? [];

// CopilotKit invia messaggi con questo formato:
// {
//   "messages": [{role: "user", content: "..."}],
//   "actions": [...],
//   "metadata": {...}
// }

$messages = $data['messages'] ?? [];
$lastMessage = end($messages);
$userMessage = is_array($lastMessage) ? ($lastMessage['content'] ?? '') : '';

error_log("CopilotAdapter: User message: " . $userMessage);

// Estrai contesto da metadata
$metadata = $data['metadata'] ?? [];
$context = $metadata['context'] ?? [];

if (empty($userMessage)) {
    if ($isStreaming) {
        sendSSE('error', ['message' => 'Messaggio vuoto']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Messaggio vuoto']);
    }
    exit;
}

// Chiama il nostro Dual Brain direttamente
try {
    // Simula input JSON per dual-brain-v2 sovrascrivendo php://input
    // Usa una variabile globale per passare i dati
    $GLOBALS['_copilot_adapter_data'] = json_encode([
        'message' => $userMessage,
        'context' => $context
    ]);

    // Modifica temporaneamente getJsonBody per leggere dalla variabile globale
    function getJsonBody() {
        if (isset($GLOBALS['_copilot_adapter_data'])) {
            return json_decode($GLOBALS['_copilot_adapter_data'], true) ?? [];
        }
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    // Buffer output di dual-brain-v2
    ob_start();
    require __DIR__ . '/dual-brain-v2.php';
    $response = ob_get_clean();

    $result = json_decode($response, true);

    if (!$result) {
        throw new Exception("Errore parsing risposta Dual Brain");
    }

    if ($isStreaming) {
        // Streaming: invia eventi progressivi
        sendSSE('text_message', [
            'content' => $result['response'] ?? $result['engineer_result'] ?? '',
            'agent' => $result['agent'] ?? 'agea'
        ]);

        sendSSE('run_complete', [
            'delegated' => $result['delegated'] ?? false,
            'agent' => $result['agent'] ?? 'agea'
        ]);
    } else {
        // Non-streaming: risposta unica
        header('Content-Type: application/json');
        echo json_encode([
            'role' => 'assistant',
            'content' => $result['response'] ?? $result['engineer_result'] ?? '',
            'metadata' => [
                'agent' => $result['agent'] ?? 'agea',
                'delegated' => $result['delegated'] ?? false
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("CopilotKit Adapter Error: " . $e->getMessage());
    if ($isStreaming) {
        sendSSE('error', ['message' => $e->getMessage()]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
