<?php
/**
 * Test AgeaAgent semplificato (senza tools)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Chat\Messages\UserMessage;

// Agent semplice senza tools
class SimpleAgent extends Agent
{
    protected string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    protected function provider(): Gemini
    {
        return new Gemini(
            key: $this->key,
            model: 'gemini-2.0-flash'
        );
    }

    public function instructions(): string
    {
        return 'Sei AGEA, un assistente AI amichevole. Rispondi brevemente in italiano.';
    }
}

try {
    echo "API Key: " . substr(GEMINI_API_KEY, 0, 15) . "...\n";
    echo "Creating SimpleAgent (no tools)...\n";

    $agent = new SimpleAgent(GEMINI_API_KEY);
    echo "Agent created!\n";

    echo "Sending message...\n";
    $start = microtime(true);

    $response = $agent->chat(new UserMessage('Ciao! Dimmi solo ciao.'));

    $elapsed = round(microtime(true) - $start, 2);
    echo "Response ({$elapsed}s): " . $response->getContent() . "\n";
    echo "SUCCESS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
