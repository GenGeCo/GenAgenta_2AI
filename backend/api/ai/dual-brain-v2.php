<?php
/**
 * POST /api/ai/dual-brain-v2
 *
 * Dual Brain COMPLETO con query database reali
 * - Agea (Flash): valuta e delega
 * - Ingegnere (Pro): analizza con accesso DB
 */

// Carica config e helpers
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/debug-helper.php';

$config = require __DIR__ . '/../../config/config.php';
$apiKey = $config['gemini_api_key'] ?? getenv('GEMINI_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'API Key mancante']);
    exit;
}

// Leggi input
$data = getJsonBody();
$message = $data['message'] ?? '';
$context = $data['context'] ?? [];

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Messaggio vuoto']);
    exit;
}

header('Content-Type: application/json');

// Log messaggio in ingresso con dettagli contesto
aiDebugLog('DUAL_BRAIN_REQUEST', [
    'message' => substr($message, 0, 200),
    'has_context' => !empty($context),
    'has_copilotContext' => !empty($context['copilotContext']),
    'has_selectedEntity' => !empty($context['selectedEntity']),
    'has_uiState' => !empty($context['uiState']),
    'context_keys' => !empty($context) ? array_keys($context) : []
]);

/**
 * Chiama Gemini API
 */
function callGemini($apiKey, $model, $conversation, $tools = []) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $payload = [
        'contents' => $conversation,
        'generationConfig' => [
            'temperature' => $model === 'gemini-2.5-flash' ? 0.7 : 0.2,
            'maxOutputTokens' => $model === 'gemini-2.5-flash' ? 2048 : 8192
        ]
    ];

    if (!empty($tools)) {
        $payload['tools'] = [['functionDeclarations' => $tools]];
    }

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

    if (!isset($data['candidates'][0]['content'])) {
        error_log("Gemini No Content: " . json_encode($data));
        throw new Exception("No response from Gemini");
    }

    return $data['candidates'][0]['content'];
}

/**
 * Esegue query database (SOLO SELECT)
 */
function executeQuery($sql) {
    // Sicurezza: SOLO SELECT
    if (!preg_match('/^\s*SELECT/i', trim($sql))) {
        return ['error' => 'Solo query SELECT sono permesse'];
    }

    try {
        $db = getDB();
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'rows' => $results,
            'count' => count($results)
        ];
    } catch (Exception $e) {
        error_log("DB Query Error: " . $e->getMessage() . " SQL: " . $sql);
        return ['error' => $e->getMessage()];
    }
}

try {
    // Carica il prompt base di Agea
    $promptFile = __DIR__ . '/../../config/ai/prompt_base.txt';
    $ageaBasePrompt = file_exists($promptFile) ? file_get_contents($promptFile) : '';

    // Placeholder utente (da sostituire con dati reali quando c'è auth)
    $userName = 'Genaro';
    $userEmail = 'genaro@gruppogea.net';
    $userRole = 'admin';
    $aziendaId = '1';

    if ($ageaBasePrompt) {
        $ageaBasePrompt = str_replace([
            '{{user_nome}}',
            '{{user_email}}',
            '{{user_ruolo}}',
            '{{azienda_id}}'
        ], [
            $userName,
            $userEmail,
            $userRole,
            $aziendaId
        ], $ageaBasePrompt);
    }

    // ============================================
    // FASE 1: AGEA valuta la richiesta
    // ============================================

    // Prepara il context UI per Agea in formato leggibile
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = "\n\n=== CONTESTO LIVE DELL'APPLICAZIONE ===\n";

        // Contesto Copilot (stato app formattato)
        if (!empty($context['copilotContext'])) {
            $contextStr .= "\n" . $context['copilotContext'] . "\n";
        }

        // Entità selezionata
        if (!empty($context['selectedEntity'])) {
            $e = $context['selectedEntity'];
            $contextStr .= "\nENTITÀ ATTUALMENTE SELEZIONATA:\n";
            $contextStr .= "  Nome: " . ($e['nome'] ?? 'N/A') . "\n";
            $contextStr .= "  Tipo: " . ($e['tipo'] ?? 'N/A') . "\n";
            if (!empty($e['indirizzo'])) {
                $contextStr .= "  Indirizzo: " . $e['indirizzo'] . "\n";
            }
            $contextStr .= "  (Quando l'utente dice 'questo' o 'questa' si riferisce a questa entità)\n";
        }

        // Marker AI sulla mappa
        if (!empty($context['aiMarkers'])) {
            $contextStr .= "\nMARKER AI SULLA MAPPA: " . count($context['aiMarkers']) . " segnaposti piazzati\n";
        }

        // Stato UI (accessibilità + azioni)
        if (!empty($context['uiState'])) {
            $contextStr .= "\n" . $context['uiState'] . "\n";
        }

        $contextStr .= "\n=== FINE CONTESTO ===\n";
    }

    $ageaSystemPrompt = $ageaBasePrompt . <<<PROMPT

=== LE TUE CAPACITÀ (TOOL) ===

Hai questi strumenti a disposizione per interagire con l'interfaccia:

1. **fly_to** - Sposta la vista della mappa
   - query: nome località ("Roma", "Milano centro", "Via Roma 1, Torino")
   - zoom: livello zoom opzionale (1-20, default 14)
   Esempio: Se utente dice "vai a Roma" → usa fly_to(query: "Roma")

2. **set_map_style** - Cambia lo stile della mappa
   - style: "streets" (stradale), "satellite" (satellitare), "dark" (scuro), "light" (chiaro)
   Esempio: Se utente dice "metti satellite" → usa set_map_style(style: "satellite")

3. **select_entity** - Seleziona un'entità sulla mappa
   - entity_id: ID dell'entità da selezionare
   Usa questo quando l'utente vuole vedere i dettagli di un'entità specifica

4. **create_entity** - Crea una nuova entità
   - nome: nome dell'entità (obbligatorio)
   - tipo: cantiere, cliente, fornitore, tecnico, rivendita (obbligatorio)
   - indirizzo: indirizzo completo (opzionale)

5. **delegate_to_engineer** - Delega al cervello analitico
   - task: descrizione del compito da svolgere
   Usa questo per analisi complesse che richiedono query database

=== QUANDO USARE COSA ===

USA I TUOI TOOL per:
- Navigazione mappa: fly_to
- Cambiare visualizzazione: set_map_style ("satellite", "streets", "dark", "light")
- Selezionare entità: select_entity
- Creare entità: create_entity
- Chat normale: rispondi direttamente

DELEGA ALL'INGEGNERE per:
- Analisi dati: "analizza vendite", "trend cantieri"
- Query complesse: "quali clienti hanno speso più di X"
- Statistiche: "confronta 2024 vs 2025"

IMPORTANTE: Quando l'utente chiede di cambiare visualizzazione/stile mappa,
USA SEMPRE set_map_style con il parametro style corretto!
PROMPT;

    // Aggiungi il context al prompt
    $ageaSystemPrompt .= $contextStr;

    $ageaTools = [
        [
            'name' => 'delegate_to_engineer',
            'description' => 'Delega una richiesta complessa all\'Ingegnere (Gemini Pro) che ha accesso al database',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'task' => [
                        'type' => 'string',
                        'description' => 'Descrizione chiara del task'
                    ]
                ],
                'required' => ['task']
            ]
        ],
        [
            'name' => 'fly_to',
            'description' => 'Sposta la vista della mappa verso una località o coordinate specifiche',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Nome della località (es: "Roma", "Milano centro", "Via Roma 1, Torino") oppure coordinate nel formato "lat,lng"'
                    ],
                    'zoom' => [
                        'type' => 'number',
                        'description' => 'Livello di zoom opzionale (1-20, default 14)'
                    ]
                ],
                'required' => ['query']
            ]
        ],
        [
            'name' => 'create_entity',
            'description' => 'Crea una nuova entità sulla mappa (cantiere, cliente, fornitore, ecc)',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'nome' => [
                        'type' => 'string',
                        'description' => 'Nome dell\'entità'
                    ],
                    'tipo' => [
                        'type' => 'string',
                        'description' => 'Tipo: cantiere, cliente, fornitore, tecnico, rivendita'
                    ],
                    'indirizzo' => [
                        'type' => 'string',
                        'description' => 'Indirizzo completo'
                    ]
                ],
                'required' => ['nome', 'tipo']
            ]
        ],
        [
            'name' => 'select_entity',
            'description' => 'Seleziona e evidenzia un\'entità sulla mappa',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'entity_id' => [
                        'type' => 'string',
                        'description' => 'ID dell\'entità da selezionare'
                    ]
                ],
                'required' => ['entity_id']
            ]
        ],
        [
            'name' => 'set_map_style',
            'description' => 'Cambia lo stile/visualizzazione della mappa',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'style' => [
                        'type' => 'string',
                        'description' => 'Stile mappa: "streets" (stradale), "satellite" (satellitare), "dark" (scuro), "light" (chiaro)'
                    ]
                ],
                'required' => ['style']
            ]
        ]
    ];

    $ageaConversation = [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $ageaSystemPrompt],
                ['text' => "\n\nRICHIESTA UTENTE: {$message}"]
            ]
        ]
    ];

    $ageaContent = callGemini($apiKey, 'gemini-2.5-flash', $ageaConversation, $ageaTools);
    $ageaParts = $ageaContent['parts'] ?? [];

    // DEBUG: Log della risposta Agea
    error_log("AGEA RESPONSE: " . json_encode($ageaContent));

    // Estrai testo e function calls
    $ageaText = '';
    $ageaFunctionCall = null;

    foreach ($ageaParts as $part) {
        if (isset($part['text'])) {
            $ageaText .= $part['text'];
        }
        if (isset($part['functionCall'])) {
            $ageaFunctionCall = $part['functionCall'];
        }
    }

    // Se Agea NON delega all'ingegnere
    if (!$ageaFunctionCall || $ageaFunctionCall['name'] !== 'delegate_to_engineer') {

        // Prepara risposta base
        $response = [
            'success' => true,
            'agent' => 'agea',
            'response' => $ageaText,
            'delegated' => false
        ];

        // Se c'è un tool call (fly_to, create_entity, ecc.), aggiungilo alla risposta
        if ($ageaFunctionCall) {
            $response['tool_call'] = [
                'name' => $ageaFunctionCall['name'],
                'args' => $ageaFunctionCall['args'] ?? []
            ];

            // Log dettagliato con tool call
            aiDebugLog('AGEA_TOOL_CALL', [
                'tool' => $ageaFunctionCall['name'],
                'args' => $ageaFunctionCall['args'] ?? [],
                'text' => substr($ageaText, 0, 100)
            ]);
        }

        // Log risposta
        aiDebugLog('DUAL_BRAIN_RESPONSE', [
            'agent' => 'agea',
            'delegated' => false,
            'has_tool_call' => $ageaFunctionCall !== null,
            'tool_name' => $ageaFunctionCall['name'] ?? null,
            'response' => substr($ageaText, 0, 200)
        ]);

        echo json_encode($response);
        exit;
    }

    // ============================================
    // FASE 2: INGEGNERE con accesso DB
    // ============================================

    $task = $ageaFunctionCall['args']['task'] ?? $message;

    $engineerSystemPrompt = <<<PROMPT
Sei l'Ingegnere, il cervello analitico di GenAgenta.

HAI ACCESSO AL DATABASE tramite il tool query_database.

DATABASE SCHEMA REALE:
- entita: id, tipo_id, nome, indirizzo, lat, lng, user_id, team_id
- tipi: id, nome (es: 'cantiere', 'fornitore', 'cliente')
- connessioni: id, entita_a_id, entita_b_id, tipo_id
- tipi_connessione: id, nome
- famiglie_prodotto: id, nome, colore
- utenti: id, nome, email, azienda_id
- team: id, nome, azienda_id

NOTA: Non esiste tabella "vendite" - i dati commerciali sono in altre tabelle o future.

ISTRUZIONI:
1. Se ti servono dati, USA query_database con SQL valido
2. ATTENDI il risultato della query
3. Analizza i dati REALI ricevuti
4. NON inventare numeri - se non ci sono dati, dillo chiaramente

TASK: {$task}
RICHIESTA ORIGINALE: {$message}
PROMPT;

    $engineerTools = [[
        'name' => 'query_database',
        'description' => 'Esegue una query SQL di SOLA LETTURA sul database MySQL',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => 'Query SQL SELECT (no INSERT/UPDATE/DELETE)'
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Perché serve questa query'
                ]
            ],
            'required' => ['sql']
        ]
    ]];

    $engineerConversation = [
        [
            'role' => 'user',
            'parts' => [['text' => $engineerSystemPrompt]]
        ]
    ];

    // Loop per gestire chiamate multiple a tool
    $maxIterations = 3;
    $iteration = 0;
    $finalResult = '';

    while ($iteration < $maxIterations) {
        $iteration++;

        $engineerContent = callGemini($apiKey, 'gemini-2.5-pro', $engineerConversation, $engineerTools);
        $engineerParts = $engineerContent['parts'] ?? [];

        $engineerText = '';
        $engineerFunctionCall = null;

        foreach ($engineerParts as $part) {
            if (isset($part['text'])) {
                $engineerText .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $engineerFunctionCall = $part['functionCall'];
            }
        }

        // Se non chiama tool, ha finito
        if (!$engineerFunctionCall) {
            $finalResult = $engineerText;
            break;
        }

        // Esegui il tool chiamato
        if ($engineerFunctionCall['name'] === 'query_database') {
            $sql = $engineerFunctionCall['args']['sql'] ?? '';
            $queryResult = executeQuery($sql);

            // Aggiungi la risposta dell'Ingegnere con function call
            $engineerConversation[] = [
                'role' => 'model',
                'parts' => [['functionCall' => $engineerFunctionCall]]
            ];

            // Aggiungi il risultato del tool
            $engineerConversation[] = [
                'role' => 'function',
                'parts' => [[
                    'functionResponse' => [
                        'name' => 'query_database',
                        'response' => $queryResult
                    ]
                ]]
            ];

            // Continua il loop per far analizzare il risultato a Gemini
        }
    }

    // Risposta finale
    echo json_encode([
        'success' => true,
        'agent' => 'engineer',
        'agea_message' => $ageaText,
        'engineer_result' => $finalResult,
        'delegated' => true,
        'iterations' => $iteration
    ]);

} catch (Exception $e) {
    error_log("Dual Brain Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'response' => "Ops! Problema: " . $e->getMessage()
    ]);
}
