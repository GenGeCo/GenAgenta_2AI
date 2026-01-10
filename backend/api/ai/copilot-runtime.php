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
            ['name' => 'location', 'type' => 'string', 'description' => 'Nome della località', 'required' => true],
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

    aiDebugLog('COPILOT_RUNTIME_POST', [
        'data_keys' => array_keys($data),
        'has_messages' => isset($data['messages']),
        'has_query' => isset($data['query'])
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

    // Estrai il messaggio
    $messages = $data['messages'] ?? [];
    $lastMessage = end($messages);
    $userMessage = '';

    if (is_array($lastMessage)) {
        $userMessage = $lastMessage['content'] ?? '';
    } elseif (is_string($lastMessage)) {
        $userMessage = $lastMessage;
    }

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
        echo json_encode(['error' => 'Messaggio vuoto']);
        exit;
    }

    // Estrai contesto CopilotKit
    $metadata = $data['metadata'] ?? [];
    $copilotContext = $metadata['context'] ?? [];

    // Anche le readables di CopilotKit
    $readables = $data['readables'] ?? [];

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

        // Disabilita buffering PHP
        if (ob_get_level()) ob_end_clean();

        // Funzione helper per inviare eventi SSE
        $sendEvent = function($event, $data) {
            echo "event: {$event}\n";
            echo "data: " . json_encode($data) . "\n\n";
            flush();
        };

        // RUN_STARTED
        $runId = uniqid('run_');
        $sendEvent('RUN_STARTED', ['runId' => $runId]);

        // TEXT_MESSAGE_START
        $messageId = uniqid('msg_');
        $sendEvent('TEXT_MESSAGE_START', ['messageId' => $messageId]);

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
            $sendEvent('TEXT_MESSAGE_CONTENT', ['content' => 'Errore comunicazione con Dual Brain']);
            $sendEvent('TEXT_MESSAGE_END', ['messageId' => $messageId]);
            $sendEvent('RUN_FINISHED', ['runId' => $runId]);
            exit;
        }

        $result = json_decode($response, true);
        $responseText = $result['delegated'] ?? false
            ? trim(($result['agea_message'] ?? '') . "\n\n" . ($result['engineer_result'] ?? ''))
            : ($result['response'] ?? 'Nessuna risposta');

        // Simula streaming token by token (per effetto "typing")
        $words = explode(' ', $responseText);
        foreach ($words as $i => $word) {
            $sendEvent('TEXT_MESSAGE_CONTENT', ['content' => ($i > 0 ? ' ' : '') . $word]);
            usleep(30000); // 30ms tra le parole
        }

        // Se c'è un tool_call, invialo
        if (isset($result['tool_call'])) {
            $toolCallId = uniqid('tc_');
            $sendEvent('TOOL_CALL_START', [
                'toolCallId' => $toolCallId,
                'name' => $result['tool_call']['name']
            ]);
            $sendEvent('TOOL_CALL_ARGS', [
                'toolCallId' => $toolCallId,
                'args' => json_encode($result['tool_call']['args'] ?? [])
            ]);
            $sendEvent('TOOL_CALL_END', ['toolCallId' => $toolCallId]);
        }

        // TEXT_MESSAGE_END
        $sendEvent('TEXT_MESSAGE_END', ['messageId' => $messageId]);

        // STATE_SNAPSHOT (opzionale - stato aggiornato)
        if ($result['delegated'] ?? false) {
            $sendEvent('STATE_SNAPSHOT', [
                'agent' => $result['agent'] ?? 'agea',
                'delegated' => true,
                'engineer_worked' => true
            ]);
        }

        // RUN_FINISHED
        $sendEvent('RUN_FINISHED', ['runId' => $runId]);
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
