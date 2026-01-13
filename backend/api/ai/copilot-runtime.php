<?php
/**
 * CopilotKit Runtime Endpoint con supporto AG-UI Streaming
 *
 * Implementa:
 * - Discovery endpoint per actions/agents
 * - Chat endpoint con streaming SSE (AG-UI Protocol)
 * - Integrazione con Dual Brain
 */

require_once __DIR__ . '/debug-helper.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CopilotKit-Runtime-Client-GQL-Version, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Log richiesta
aiDebugLog('COPILOT_RUNTIME_REQUEST', [
    'method' => $method,
    'path' => $path,
    'accept' => $accept
]);

// ============================================
// ACTIONS disponibili per CopilotKit
// ============================================
$availableActions = [
    [
        'name' => 'fly_to',
        'description' => 'Sposta la vista della mappa verso una località. Usa questo quando l\'utente chiede di andare in un posto.',
        'parameters' => [
            ['name' => 'query', 'type' => 'string', 'description' => 'Nome della località (es: Roma, Milano, Via Dante 1)', 'required' => true],
            ['name' => 'zoom', 'type' => 'number', 'description' => 'Livello di zoom (1-20)', 'required' => false]
        ]
    ],
    [
        'name' => 'set_map_style',
        'description' => 'Cambia lo stile della mappa. Stili: satellite-v9, streets-v12, dark-v11, light-v11, outdoors-v12',
        'parameters' => [
            ['name' => 'style', 'type' => 'string', 'description' => 'Stile mappa', 'required' => true]
        ]
    ],
    [
        'name' => 'select_entity',
        'description' => 'Seleziona un\'entità sulla mappa per mostrare i dettagli',
        'parameters' => [
            ['name' => 'entity_id', 'type' => 'string', 'description' => 'ID dell\'entità', 'required' => true]
        ]
    ],
    [
        'name' => 'search_entities',
        'description' => 'Cerca entità per nome o tipo',
        'parameters' => [
            ['name' => 'query', 'type' => 'string', 'description' => 'Testo da cercare', 'required' => false],
            ['name' => 'type', 'type' => 'string', 'description' => 'Tipo entità', 'required' => false]
        ]
    ],
    [
        'name' => 'delegate_to_engineer',
        'description' => 'Delega analisi complesse all\'Ingegnere (Gemini Pro) che ha accesso al database',
        'parameters' => [
            ['name' => 'task', 'type' => 'string', 'description' => 'Descrizione del task', 'required' => true]
        ]
    ]
];

$availableAgents = [
    'default' => ['name' => 'default', 'description' => 'Agea - Assistente AI conversazionale'],
    'agea' => ['name' => 'agea', 'description' => 'Agea - AI veloce (Gemini Flash)'],
    'engineer' => ['name' => 'engineer', 'description' => 'Ingegnere - AI analitica (Gemini Pro)']
];

// ============================================
// GET /info - Discovery endpoint
// ============================================
if ($method === 'GET' || strpos($path, '/info') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'actions' => $availableActions,
        'agents' => array_values($availableAgents)
    ]);
    exit;
}

// ============================================
// POST - Chat endpoint
// ============================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];

    aiDebugLog('COPILOT_RUNTIME_POST_FULL', [
        'data_keys' => array_keys($data),
        'raw_preview' => substr($rawInput, 0, 2000),
        'data_structure' => $data
    ]);

    // CopilotKit manda {"method":"info"} per discovery
    if (isset($data['method']) && $data['method'] === 'info') {
        header('Content-Type: application/json');
        echo json_encode([
            'sdkVersion' => '1.50.1',
            'actions' => $availableActions,
            'agents' => $availableAgents,
            'agents__unsafe_dev_only' => $availableAgents
        ]);
        exit;
    }

    // CopilotKit agent/connect - connessione iniziale (messaggi vuoti = OK)
    if (isset($data['method']) && $data['method'] === 'agent/connect') {
        aiDebugLog('COPILOT_AGENT_CONNECT', ['status' => 'connected']);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'connected',
            'agent' => 'agea',
            'capabilities' => ['streaming', 'tools']
        ]);
        exit;
    }

    // Estrai il messaggio - supporta diversi formati CopilotKit
    // IMPORTANTE: prendi solo l'ultimo messaggio con role="user", ignora tool results
    $userMessage = '';

    // Helper per trovare l'ultimo messaggio utente
    $findLastUserMessage = function($messages) {
        if (!is_array($messages)) return '';
        // Scorri al contrario per trovare l'ultimo messaggio user
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if (is_array($msg) && ($msg['role'] ?? '') === 'user') {
                return $msg['content'] ?? '';
            }
        }
        return '';
    };

    // Formato 1: messages array diretto
    if (isset($data['messages']) && is_array($data['messages'])) {
        $userMessage = $findLastUserMessage($data['messages']);
    }

    // Formato 2: body.messages (CopilotKit runAgent)
    if (empty($userMessage) && isset($data['body']['messages'])) {
        $userMessage = $findLastUserMessage($data['body']['messages']);
    }

    // Formato 3: params.messages
    if (empty($userMessage) && isset($data['params']['messages'])) {
        $userMessage = $findLastUserMessage($data['params']['messages']);
    }

    // Formato 4: body.input (alcuni CopilotKit)
    if (empty($userMessage) && isset($data['body']['input'])) {
        $userMessage = $data['body']['input'];
    }

    // Formato 5: input diretto
    if (empty($userMessage) && isset($data['input'])) {
        $userMessage = $data['input'];
    }

    aiDebugLog('COPILOT_MESSAGE_EXTRACTED', [
        'userMessage' => $userMessage,
        'empty' => empty($userMessage)
    ]);

    // GraphQL non supportato
    if (empty($userMessage) && isset($data['query'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'data' => [
                'generateCopilotResponse' => [
                    'messages' => [['role' => 'assistant', 'content' => 'Usa REST API']]
                ]
            ]
        ]);
        exit;
    }

    if (empty($userMessage)) {
        http_response_code(400);
        header('Content-Type: application/json');
        aiDebugLog('COPILOT_ERROR_EMPTY', ['data' => $data]);
        echo json_encode(['error' => 'Messaggio vuoto']);
        exit;
    }

    // Estrai contesto CopilotKit
    $metadata = $data['metadata'] ?? [];
    $copilotContext = $metadata['context'] ?? [];

    // Anche le readables di CopilotKit
    $readables = $data['readables'] ?? [];

    // Estrai threadId e runId dalla richiesta CopilotKit (body.threadId, body.runId)
    $threadId = $data['body']['threadId'] ?? uniqid('thread_');
    $runId = $data['body']['runId'] ?? uniqid('run_');

    // ============================================
    // STREAMING SSE (AG-UI Protocol)
    // ============================================
    $wantsStream = strpos($accept, 'text/event-stream') !== false;

    if ($wantsStream) {
        // AG-UI Streaming response
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disabilita buffering nginx

        // Disabilita TUTTI i livelli di buffering PHP (critico per SSE)
        while (ob_get_level()) ob_end_flush();

        // Disabilita implicit flush
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);

        // Forza flush immediato
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        // Funzione helper per inviare eventi SSE
        // CopilotKit vuole SCREAMING_SNAKE_CASE per type + camelCase per campi
        $sendEvent = function($type, $payload) {
            $event = array_merge(['type' => $type], $payload);
            echo "data: " . json_encode($event) . "\n\n";
            flush();
        };

        // RUN_STARTED
        $sendEvent('RUN_STARTED', ['threadId' => $threadId, 'runId' => $runId]);

        // TEXT_MESSAGE_START - richiede messageId e role
        $messageId = uniqid('msg_');
        $sendEvent('TEXT_MESSAGE_START', ['messageId' => $messageId, 'role' => 'assistant']);

        // Prepara history per dual-brain (ultimi 20 messaggi per contesto)
        $history = [];
        $allMessages = $data['body']['messages'] ?? $data['messages'] ?? [];
        $recentMessages = array_slice($allMessages, -20);
        foreach ($recentMessages as $msg) {
            if (!is_array($msg)) continue;
            $role = $msg['role'] ?? '';
            $content = $msg['content'] ?? '';
            // Salta messaggi vuoti o tool results
            if (empty($content) || $role === 'tool') continue;
            // Mappa ruoli per Gemini
            $history[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'content' => is_string($content) ? $content : json_encode($content)
            ];
        }

        // Chiama dual-brain-v2
        $ch = curl_init('https://genagenta.gruppogea.net/api/ai/dual-brain-v2');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'message' => $userMessage,
                'history' => $history,
                'context' => array_merge($copilotContext, ['readables' => $readables])
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $sendEvent('TEXT_MESSAGE_CONTENT', ['messageId' => $messageId, 'delta' => 'Errore comunicazione con Dual Brain']);
            $sendEvent('TEXT_MESSAGE_END', ['messageId' => $messageId]);
            $sendEvent('RUN_FINISHED', ['threadId' => $threadId, 'runId' => $runId]);
            exit;
        }

        $result = json_decode($response, true);
        $responseText = $result['delegated'] ?? false
            ? trim(($result['agea_message'] ?? '') . "\n\n" . ($result['engineer_result'] ?? ''))
            : ($result['response'] ?? '');

        // Se c'è un tool_call, invialo DOPO TEXT_MESSAGE_START ma con parentMessageId
        if (isset($result['tool_call'])) {
            $toolCallId = uniqid('tc_');
            $toolArgs = $result['tool_call']['args'] ?? [];
            $toolArgsJson = json_encode($toolArgs);

            // Log per debug
            aiDebugLog('SSE_TOOL_CALL', [
                'toolCallId' => $toolCallId,
                'toolName' => $result['tool_call']['name'],
                'args' => $toolArgs,
                'argsJson' => $toolArgsJson,
                'parentMessageId' => $messageId
            ]);

            $sendEvent('TOOL_CALL_START', [
                'toolCallId' => $toolCallId,
                'toolCallName' => $result['tool_call']['name'],
                'parentMessageId' => $messageId  // Lega il tool call al messaggio
            ]);
            $sendEvent('TOOL_CALL_ARGS', [
                'toolCallId' => $toolCallId,
                'delta' => $toolArgsJson
            ]);
            $sendEvent('TOOL_CALL_END', ['toolCallId' => $toolCallId]);
        }

        // Streaming token by token (per effetto "typing")
        // IMPORTANTE: delta non può essere vuoto, quindi skippa se non c'è testo
        $responseText = trim($responseText);
        if (!empty($responseText)) {
            $words = explode(' ', $responseText);
            foreach ($words as $i => $word) {
                if (empty(trim($word))) continue; // Skip parole vuote
                $sendEvent('TEXT_MESSAGE_CONTENT', [
                    'messageId' => $messageId,
                    'delta' => ($i > 0 ? ' ' : '') . $word
                ]);
                usleep(20000); // 20ms tra le parole
            }
        }

        // TEXT_MESSAGE_END
        $sendEvent('TEXT_MESSAGE_END', ['messageId' => $messageId]);

        // STATE_DELTA rimosso - causava errori "path does not exist"
        // Lo stato viene gestito internamente da CopilotKit

        // RUN_FINISHED
        $sendEvent('RUN_FINISHED', ['threadId' => $threadId, 'runId' => $runId]);
        exit;
    }

    // ============================================
    // NON-STREAMING (JSON response)
    // ============================================
    header('Content-Type: application/json');

    // Chiama dual-brain-v2
    $ch = curl_init('https://genagenta.gruppogea.net/api/ai/dual-brain-v2');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message' => $userMessage,
            'context' => array_merge($copilotContext, ['readables' => $readables])
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        http_response_code(500);
        echo json_encode(['error' => "Dual Brain error: HTTP $httpCode"]);
        exit;
    }

    $result = json_decode($response, true);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore parsing risposta']);
        exit;
    }

    // Formato risposta CopilotKit
    $responseText = $result['delegated'] ?? false
        ? trim(($result['agea_message'] ?? '') . "\n\n" . ($result['engineer_result'] ?? ''))
        : ($result['response'] ?? '');

    $responseData = [
        'messages' => [
            [
                'role' => 'assistant',
                'content' => $responseText
            ]
        ],
        'metadata' => [
            'agent' => $result['agent'] ?? 'agea',
            'delegated' => $result['delegated'] ?? false
        ]
    ];

    // Aggiungi tool_call se presente
    if (isset($result['tool_call'])) {
        $responseData['toolCalls'] = [
            [
                'id' => uniqid('tc_'),
                'name' => $result['tool_call']['name'],
                'args' => $result['tool_call']['args'] ?? []
            ]
        ];
    }

    echo json_encode($responseData);
    exit;
}

// Fallback
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Endpoint non trovato']);
