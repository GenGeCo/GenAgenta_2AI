<?php
/**
 * CopilotKit Adapter Semplificato
 *
 * Adatta le richieste CopilotKit al nostro dual-brain-v2 esistente
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Leggi input CopilotKit
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? [];

// CopilotKit format: {"messages": [{role, content}], "metadata": {...}}
$messages = $data['messages'] ?? [];
$lastMessage = end($messages);
$userMessage = is_array($lastMessage) ? ($lastMessage['content'] ?? '') : '';

if (empty($userMessage)) {
    http_response_code(400);
    echo json_encode(['error' => 'Messaggio vuoto']);
    exit;
}

// Estrai contesto
$metadata = $data['metadata'] ?? [];
$context = $metadata['context'] ?? [];

// Chiama dual-brain-v2 usando curl interno
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
    CURLOPT_SSL_VERIFYPEER => false // Ignora verifica SSL locale
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("Dual Brain error: HTTP $httpCode, curl error: $curlError");
    http_response_code(500);
    echo json_encode([
        'error' => "Dual Brain error: HTTP $httpCode"
    ]);
    exit;
}

$result = json_decode($response, true);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore parsing risposta']);
    exit;
}

// Converti formato Dual Brain → CopilotKit
$responseText = $result['delegated'] ?? false
    ? ($result['agea_message'] ?? '') . "\n\n" . ($result['engineer_result'] ?? '')
    : ($result['response'] ?? '');

echo json_encode([
    'role' => 'assistant',
    'content' => trim($responseText),
    'metadata' => [
        'agent' => $result['agent'] ?? 'agea',
        'delegated' => $result['delegated'] ?? false
    ]
]);
