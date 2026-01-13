<?php
/**
 * Dual Brain CORE - Funzione includibile
 *
 * Questa versione NON legge da php://input e NON fa echo.
 * Restituisce direttamente l'array di risposta.
 *
 * Usata da copilot-runtime.php per evitare la latenza cURL.
 */

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/debug-helper.php';

/**
 * Chiama Gemini API
 */
function callGeminiCore($apiKey, $model, $conversation, $tools = [], $systemInstruction = '') {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => $conversation,
        'generationConfig' => [
            'temperature' => $model === 'gemini-2.5-flash' ? 0.7 : 0.2,
            'maxOutputTokens' => $model === 'gemini-2.5-flash' ? 2048 : 8192
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];

    if (!empty($systemInstruction)) {
        $payload['system_instruction'] = [
            'parts' => [['text' => $systemInstruction]]
        ];
    }

    if (!empty($tools)) {
        $payload['tools'] = [['functionDeclarations' => $tools]];
    }

    aiDebugLog('GEMINI_PAYLOAD_CORE', [
        'model' => $model,
        'has_system_instruction' => !empty($systemInstruction),
        'contents_count' => count($conversation),
        'tools_count' => count($tools)
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Gemini API Error: HTTP $httpCode - Response: " . substr($response, 0, 500));
        throw new Exception("Gemini error: HTTP $httpCode");
    }

    $data = json_decode($response, true);

    // Log caching stats
    $usageMetadata = $data['usageMetadata'] ?? [];
    if (!empty($usageMetadata)) {
        aiDebugLog('GEMINI_USAGE', [
            'model' => $model,
            'cached_tokens' => $usageMetadata['cachedContentTokenCount'] ?? 0,
            'prompt_tokens' => $usageMetadata['promptTokenCount'] ?? 0,
            'output_tokens' => $usageMetadata['candidatesTokenCount'] ?? 0
        ]);
    }

    if (!isset($data['candidates'][0]['content'])) {
        error_log("Gemini No Content: " . json_encode($data));
        throw new Exception("No response from Gemini");
    }

    return $data['candidates'][0]['content'];
}

/**
 * Esegue query database (SOLO SELECT)
 */
function executeQueryCore($sql) {
    if (!preg_match('/^\s*SELECT/i', trim($sql))) {
        return ['error' => 'Solo query SELECT sono permesse'];
    }

    try {
        $db = getDB();
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'rows' => $results, 'count' => count($results)];
    } catch (Exception $e) {
        error_log("DB Query Error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Funzione principale Dual Brain - Restituisce array, NON fa echo
 *
 * @param string $message - Messaggio utente
 * @param array $history - Storia conversazione
 * @param array $context - Contesto UI
 * @return array - Risposta strutturata
 */
function processDualBrain($message, $history = [], $context = []) {
    $config = require __DIR__ . '/../../config/config.php';
    $apiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

    if (!$apiKey) {
        return ['success' => false, 'error' => 'API Key mancante'];
    }

    if (empty($message)) {
        return ['success' => false, 'error' => 'Messaggio vuoto'];
    }

    aiDebugLog('DUAL_BRAIN_CORE_REQUEST', [
        'message' => substr($message, 0, 200),
        'history_count' => count($history)
    ]);

    try {
        // Carica prompt base
        $promptFile = __DIR__ . '/../../config/ai/prompt_base.txt';
        $ageaBasePrompt = file_exists($promptFile) ? file_get_contents($promptFile) : '';

        $userName = 'Genaro';
        if ($ageaBasePrompt) {
            $ageaBasePrompt = str_replace('{{user_nome}}', $userName, $ageaBasePrompt);
        }

        // Prepara contesto
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = "\n\n=== CONTESTO LIVE ===\n";
            if (!empty($context['copilotContext'])) {
                $contextStr .= $context['copilotContext'] . "\n";
            }
            if (!empty($context['selectedEntity'])) {
                $e = $context['selectedEntity'];
                $contextStr .= "ENTITÀ SELEZIONATA: " . ($e['nome'] ?? 'N/A') . " (" . ($e['tipo'] ?? 'N/A') . ")\n";
            }
            $contextStr .= "=== FINE CONTESTO ===\n";
        }

        // System prompt completo
        $fullSystemInstruction = $ageaBasePrompt . $contextStr;

        // Tools Agea
        $ageaTools = [
            [
                'name' => 'delegate_to_engineer',
                'description' => 'Delega analisi complesse all\'Ingegnere con accesso database',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['task' => ['type' => 'string', 'description' => 'Descrizione del task']],
                    'required' => ['task']
                ]
            ],
            [
                'name' => 'fly_to',
                'description' => 'Sposta la mappa verso città, regioni, nazioni o coordinate',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Località (Roma, Italia, Toscana, etc)'],
                        'zoom' => ['type' => 'number', 'description' => 'Zoom 1-20']
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'set_map_style',
                'description' => 'Cambia stile mappa (satellite, streets, dark, light)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['style' => ['type' => 'string', 'description' => 'Stile mappa']],
                    'required' => ['style']
                ]
            ],
            [
                'name' => 'select_entity',
                'description' => 'Seleziona un\'entità sulla mappa',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['entity_id' => ['type' => 'string', 'description' => 'ID entità']],
                    'required' => ['entity_id']
                ]
            ]
        ];

        // Costruisci conversation con alternanza corretta
        $ageaConversation = [];
        $lastRole = null;

        if (!empty($history)) {
            $historyCount = count($history);
            foreach ($history as $idx => $historyMsg) {
                $role = $historyMsg['role'] ?? 'user';
                $content = $historyMsg['content'] ?? '';
                if (empty($content)) continue;
                if ($idx === $historyCount - 1 && $role === 'user') continue;
                if ($role === $lastRole) continue;

                $ageaConversation[] = [
                    'role' => $role,
                    'parts' => [['text' => $content]]
                ];
                $lastRole = $role;
            }
        }

        if ($lastRole === 'user') {
            $ageaConversation[] = ['role' => 'model', 'parts' => [['text' => 'Ok.']]];
            $lastRole = 'model';
        }

        $ageaConversation[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        // Chiama Agea
        $ageaContent = callGeminiCore($apiKey, 'gemini-2.5-flash', $ageaConversation, $ageaTools, $fullSystemInstruction);
        $ageaParts = $ageaContent['parts'] ?? [];

        $ageaText = '';
        $ageaFunctionCall = null;

        foreach ($ageaParts as $part) {
            if (isset($part['text'])) $ageaText .= $part['text'];
            if (isset($part['functionCall'])) $ageaFunctionCall = $part['functionCall'];
        }

        // Se NON delega all'ingegnere
        if (!$ageaFunctionCall || $ageaFunctionCall['name'] !== 'delegate_to_engineer') {
            $responseText = $ageaText;

            // Genera messaggio di conferma se tool call senza testo
            if ($ageaFunctionCall && empty(trim($ageaText))) {
                $toolName = $ageaFunctionCall['name'];
                $args = $ageaFunctionCall['args'] ?? [];

                switch ($toolName) {
                    case 'fly_to':
                        $responseText = "Ti porto a " . ($args['query'] ?? 'destinazione') . "!";
                        break;
                    case 'set_map_style':
                        $responseText = "Cambio mappa in " . ($args['style'] ?? 'nuovo stile') . "!";
                        break;
                    default:
                        $responseText = "Fatto!";
                }
            }

            $response = [
                'success' => true,
                'agent' => 'agea',
                'response' => $responseText,
                'delegated' => false
            ];

            if ($ageaFunctionCall) {
                $response['tool_call'] = [
                    'name' => $ageaFunctionCall['name'],
                    'args' => $ageaFunctionCall['args'] ?? []
                ];
            }

            aiDebugLog('DUAL_BRAIN_CORE_RESPONSE', [
                'agent' => 'agea',
                'has_tool' => $ageaFunctionCall !== null,
                'response' => substr($responseText, 0, 100)
            ]);

            return $response;
        }

        // FASE 2: Ingegnere
        $task = $ageaFunctionCall['args']['task'] ?? $message;

        $engineerSystemPrompt = "Sei l'Ingegnere di GenAgenta. Hai accesso al database tramite query_database. " .
            "Schema: entita, tipi, connessioni, tipi_connessione, famiglie_prodotto, utenti, team. " .
            "Task: {$task}";

        $engineerTools = [[
            'name' => 'query_database',
            'description' => 'Esegue query SQL SELECT sul database',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'sql' => ['type' => 'string', 'description' => 'Query SELECT'],
                    'reason' => ['type' => 'string', 'description' => 'Motivo']
                ],
                'required' => ['sql']
            ]
        ]];

        $engineerConversation = [['role' => 'user', 'parts' => [['text' => $engineerSystemPrompt]]]];

        $maxIterations = 3;
        $finalResult = '';

        for ($i = 0; $i < $maxIterations; $i++) {
            $engineerContent = callGeminiCore($apiKey, 'gemini-2.5-pro', $engineerConversation, $engineerTools);
            $engineerParts = $engineerContent['parts'] ?? [];

            $engineerText = '';
            $engineerFunctionCall = null;

            foreach ($engineerParts as $part) {
                if (isset($part['text'])) $engineerText .= $part['text'];
                if (isset($part['functionCall'])) $engineerFunctionCall = $part['functionCall'];
            }

            if (!$engineerFunctionCall) {
                $finalResult = $engineerText;
                break;
            }

            if ($engineerFunctionCall['name'] === 'query_database') {
                $sql = $engineerFunctionCall['args']['sql'] ?? '';
                $queryResult = executeQueryCore($sql);

                $engineerConversation[] = ['role' => 'model', 'parts' => [['functionCall' => $engineerFunctionCall]]];
                $engineerConversation[] = [
                    'role' => 'function',
                    'parts' => [['functionResponse' => ['name' => 'query_database', 'response' => $queryResult]]]
                ];
            }
        }

        return [
            'success' => true,
            'agent' => 'engineer',
            'agea_message' => $ageaText,
            'engineer_result' => $finalResult,
            'delegated' => true
        ];

    } catch (Exception $e) {
        error_log("Dual Brain Core Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'response' => "Errore: " . $e->getMessage()
        ];
    }
}
