<?php
/**
 * Test Neuron AI connection
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Chat\Messages\UserMessage;

// Test Gemini connection
try {
    require_once __DIR__ . '/../../config/config.php';
    $apiKey = GEMINI_API_KEY;

    if (!$apiKey) {
        die('GEMINI_API_KEY missing');
    }

    echo "API Key found: " . substr($apiKey, 0, 10) . "...\n";

    // Create simple agent
    class TestAgent extends Agent {
        protected string $key;

        public function __construct(string $key) {
            $this->key = $key;
        }

        protected function provider(): Gemini {
            return new Gemini(
                key: $this->key,
                model: 'gemini-2.0-flash'
            );
        }

        public function instructions(): string {
            return 'You are a helpful assistant. Respond briefly in Italian.';
        }
    }

    echo "Creating agent...\n";
    $agent = new TestAgent($apiKey);

    echo "Sending message...\n";
    $response = $agent->chat(new UserMessage('Ciao, dimmi ciao in una parola'));

    echo "Response: " . $response->getContent() . "\n";
    echo "SUCCESS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
