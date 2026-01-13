<?php
/**
 * Test Gemini API direttamente con tools
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

echo "Testing Gemini API directly with tools...\n";
echo "API Key: " . substr(GEMINI_API_KEY, 0, 15) . "...\n";

$apiKey = GEMINI_API_KEY;
$model = 'gemini-2.0-flash';

// Payload con tools
$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [['text' => 'Ciao!']]
        ]
    ],
    'systemInstruction' => [
        'parts' => [['text' => 'Sei un assistente amichevole.']]
    ],
    'tools' => [
        [
            'functionDeclarations' => [
                [
                    'name' => 'greet',
                    'description' => 'Saluta una persona',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Nome della persona'
                            ]
                        ],
                        'required' => ['name']
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1024
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

echo "Sending request to: " . str_replace($apiKey, '***', $url) . "\n";
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$start = microtime(true);
$response = curl_exec($ch);
$elapsed = round(microtime(true) - $start, 2);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Time: {$elapsed}s\n";

if ($error) {
    echo "cURL Error: {$error}\n";
} else {
    echo "Response:\n";
    $decoded = json_decode($response, true);
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
}
