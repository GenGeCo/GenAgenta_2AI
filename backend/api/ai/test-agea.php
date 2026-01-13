<?php
/**
 * Test AgeaAgent
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use GenAgenta\Agents\AgeaAgent;
use NeuronAI\Chat\Messages\UserMessage;

try {
    echo "API Key: " . substr(GEMINI_API_KEY, 0, 15) . "...\n";
    echo "Creating AgeaAgent...\n";

    $agent = new AgeaAgent(GEMINI_API_KEY);
    echo "Agent created!\n";

    echo "Sending message...\n";
    $response = $agent->chat(new UserMessage('Ciao, dimmi solo "Ciao!"'));

    echo "Response: " . $response->getContent() . "\n";
    echo "SUCCESS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
