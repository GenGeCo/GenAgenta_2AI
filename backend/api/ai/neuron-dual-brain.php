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

use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\AgentInterface;
use GenAgenta\Agents\AgeaAgent;
use GenAgenta\Agents\IngegnereAgent;

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
 * Invia messaggio di testo
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
function sendToolCall(string $toolName, array $args, ?string $result = null): void
{
    sendSSE('tool_call', [
        'tool' => $toolName,
        'arguments' => $args,
        'result' => $result,
        'timestamp' => time()
    ]);
}

/**
 * Observer per catturare i tool calls
 */
class ToolObserver implements \SplObserver
{
    private array $toolCalls = [];
    private bool $delegated = false;
    private string $delegatedTask = '';

    public function update(\SplSubject $subject, ?string $event = null, mixed $data = null): void
    {
        if ($event === 'tool-calling') {
            // Tool sta per essere chiamato
            $tool = $data->tool;
            $toolName = $tool->getName();
            $args = $tool->getArguments();

            sendToolCall($toolName, $args);

            // Verifica se è una delegazione
            if ($toolName === 'delegate_to_engineer') {
                $this->delegated = true;
                $this->delegatedTask = $args['task'] ?? '';
            }
        } elseif ($event === 'tool-called') {
            // Tool è stato eseguito
            $tool = $data->tool;
            $this->toolCalls[] = [
                'name' => $tool->getName(),
                'result' => $tool->getResult()
            ];
        }
    }

    public function isDelegated(): bool
    {
        return $this->delegated;
    }

    public function getDelegatedTask(): string
    {
        return $this->delegatedTask;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }
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
    require_once __DIR__ . '/../../config/config.php';
    $apiKey = GEMINI_API_KEY;

    if (!$apiKey) {
        sendSSE('error', ['message' => 'GEMINI_API_KEY mancante']);
        exit;
    }

    sendSSE('run_started', ['agent' => 'agea', 'message' => $message]);

    // ============================================
    // FASE 1: AGEA valuta la richiesta
    // ============================================

    $agea = new AgeaAgent($apiKey);

    // Aggiungi observer per catturare tool calls
    $observer = new ToolObserver();
    $agea->observe($observer);

    sendSSE('agent_thinking', ['agent' => 'agea']);

    // Prepara il messaggio con contesto
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = "\n\n=== CONTESTO UI ===\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n===\n\n";
    }

    $userMessage = new UserMessage($contextStr . $message);

    // Chat con Agea
    $ageaResponse = $agea->chat($userMessage);
    $ageaContent = $ageaResponse->getContent();

    // Verifica se è stata fatta una delegazione tramite tool
    $delegated = $observer->isDelegated();
    $delegatedTask = $observer->getDelegatedTask();

    // Se Agea NON ha delegato, risposta diretta
    if (!$delegated) {
        sendMessage($ageaContent, 'agea');
        sendSSE('run_finished', [
            'agent' => 'agea',
            'delegated' => false,
            'tool_calls' => $observer->getToolCalls()
        ]);
        exit;
    }

    // ============================================
    // FASE 2: INGEGNERE con analisi profonda
    // ============================================

    sendSSE('delegation', ['from' => 'agea', 'to' => 'engineer', 'task' => $delegatedTask]);
    sendSSE('agent_thinking', ['agent' => 'engineer']);

    $engineer = new IngegnereAgent($apiKey);

    // Observer per l'ingegnere
    $engineerObserver = new ToolObserver();
    $engineer->observe($engineerObserver);

    // Prepara messaggio per l'ingegnere
    $engineerMessage = new UserMessage(
        "TASK DELEGATO DA AGEA: {$delegatedTask}\n" .
        "RICHIESTA ORIGINALE UTENTE: {$message}\n\n" .
        "Esegui il task utilizzando i tools disponibili."
    );

    // Chat con Ingegnere
    $engineerResponse = $engineer->chat($engineerMessage);
    $engineerContent = $engineerResponse->getContent();

    // Risposta finale dell'Ingegnere
    sendMessage($engineerContent, 'engineer');

    sendSSE('run_finished', [
        'agent' => 'engineer',
        'delegated' => true,
        'tool_calls' => $engineerObserver->getToolCalls()
    ]);

} catch (Exception $e) {
    error_log("Neuron Dual Brain Error: " . $e->getMessage());
    sendSSE('error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
