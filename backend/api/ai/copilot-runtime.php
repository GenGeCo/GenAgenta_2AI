<?php
/**
 * CopilotKit Runtime Endpoint
 *
 * Fornisce un'API compatibile con CopilotKit per il Dual Brain
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CopilotKit-Runtime-Client-GQL-Version');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// GET /info - Discovery endpoint per CopilotKit
if ($method === 'GET' || strpos($path, '/info') !== false) {
    // Formato richiesto da CopilotKit
    echo json_encode([
        'actions' => [
            [
                'name' => 'query_database',
                'description' => 'Esegue una query SQL di sola lettura sul database GenAgenta',
                'parameters' => [
                    [
                        'name' => 'sql',
                        'type' => 'string',
                        'description' => 'Query SQL SELECT',
                        'required' => true
                    ]
                ]
            ],
            [
                'name' => 'map_fly_to',
                'description' => 'Sposta la vista della mappa a coordinate specifiche',
                'parameters' => [
                    [
                        'name' => 'lat',
                        'type' => 'number',
                        'description' => 'Latitudine',
                        'required' => true
                    ],
                    [
                        'name' => 'lng',
                        'type' => 'number',
                        'description' => 'Longitudine',
                        'required' => true
                    ],
                    [
                        'name' => 'zoom',
                        'type' => 'number',
                        'description' => 'Livello di zoom (1-20)',
                        'required' => false
                    ]
                ]
            ],
            [
                'name' => 'map_select_entity',
                'description' => 'Seleziona un\'entità sulla mappa',
                'parameters' => [
                    [
                        'name' => 'entity_id',
                        'type' => 'string',
                        'description' => 'ID dell\'entità da selezionare',
                        'required' => true
                    ]
                ]
            ]
        ],
        'agents' => [
            [
                'name' => 'default',
                'description' => 'Agea - Assistente AI conversazionale di GenAgenta'
            ],
            [
                'name' => 'agea',
                'description' => 'Agea - Assistente AI veloce (Gemini Flash)'
            ],
            [
                'name' => 'engineer',
                'description' => 'Ingegnere - AI analitica per query database (Gemini Pro)'
            ]
        ]
    ]);
    exit;
}

// POST - Chat endpoint
if ($method === 'POST') {
    // Leggi il corpo della richiesta
    $rawInput = file_get_contents('php://input');
    error_log("CopilotRuntime POST: " . substr($rawInput, 0, 500));

    $data = json_decode($rawInput, true) ?? [];

    // CopilotKit manda {"method":"info"} per discovery
    if (isset($data['method']) && $data['method'] === 'info') {
        echo json_encode([
            'sdkVersion' => '1.50.1',
            'actions' => [
                [
                    'name' => 'query_database',
                    'description' => 'Esegue una query SQL di sola lettura sul database GenAgenta',
                    'parameters' => [
                        ['name' => 'sql', 'type' => 'string', 'description' => 'Query SQL SELECT', 'required' => true]
                    ]
                ],
                [
                    'name' => 'map_fly_to',
                    'description' => 'Sposta la vista della mappa a coordinate specifiche',
                    'parameters' => [
                        ['name' => 'lat', 'type' => 'number', 'description' => 'Latitudine', 'required' => true],
                        ['name' => 'lng', 'type' => 'number', 'description' => 'Longitudine', 'required' => true],
                        ['name' => 'zoom', 'type' => 'number', 'description' => 'Livello di zoom (1-20)', 'required' => false]
                    ]
                ],
                [
                    'name' => 'map_select_entity',
                    'description' => 'Seleziona un\'entità sulla mappa',
                    'parameters' => [
                        ['name' => 'entity_id', 'type' => 'string', 'description' => 'ID dell\'entità da selezionare', 'required' => true]
                    ]
                ]
            ],
            'agents' => [
                'default' => ['name' => 'default', 'description' => 'Agea - Assistente AI conversazionale di GenAgenta'],
                'agea' => ['name' => 'agea', 'description' => 'Agea - Assistente AI veloce (Gemini Flash)'],
                'engineer' => ['name' => 'engineer', 'description' => 'Ingegnere - AI analitica per query database (Gemini Pro)']
            ],
            'agents__unsafe_dev_only' => [
                'default' => ['name' => 'default', 'description' => 'Agea - Assistente AI conversazionale di GenAgenta'],
                'agea' => ['name' => 'agea', 'description' => 'Agea - Assistente AI veloce (Gemini Flash)'],
                'engineer' => ['name' => 'engineer', 'description' => 'Ingegnere - AI analitica per query database (Gemini Pro)']
            ]
        ]);
        exit;
    }

    // CopilotKit può inviare in formato diversi
    // Formato 1: {"messages": [...], "metadata": {...}}
    // Formato 2: GraphQL query

    // Estrai il messaggio
    $messages = $data['messages'] ?? [];
    $lastMessage = end($messages);
    $userMessage = '';

    if (is_array($lastMessage)) {
        $userMessage = $lastMessage['content'] ?? '';
    } elseif (is_string($lastMessage)) {
        $userMessage = $lastMessage;
    }

    // Se è una query GraphQL, cerca il messaggio nel query
    if (empty($userMessage) && isset($data['query'])) {
        // È una richiesta GraphQL - per ora non supportata completamente
        error_log("CopilotRuntime: GraphQL query ricevuta");
        echo json_encode([
            'data' => [
                'generateCopilotResponse' => [
                    'messages' => [
                        [
                            'role' => 'assistant',
                            'content' => 'GraphQL endpoint - per favore usa REST'
                        ]
                    ]
                ]
            ]
        ]);
        exit;
    }

    if (empty($userMessage)) {
        http_response_code(400);
        echo json_encode(['error' => 'Messaggio vuoto']);
        exit;
    }

    // Estrai contesto
    $metadata = $data['metadata'] ?? [];
    $context = $metadata['context'] ?? [];

    // Chiama dual-brain-v2 via curl interno
    $ch = curl_init('https://genagenta.gruppogea.net/api/ai/dual-brain-v2');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message' => $userMessage,
            'context' => $context
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
        error_log("Dual Brain error: HTTP $httpCode");
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

    echo json_encode([
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
    ]);
    exit;
}

// Fallback
http_response_code(404);
echo json_encode(['error' => 'Endpoint non trovato']);
