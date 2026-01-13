<?php
/**
 * Test AgeaAgent con tools corretti (properties + __invoke)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

// Tool semplice con la sintassi corretta (properties + __invoke)
class GreetTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'greet',
            'Saluta una persona per nome'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'name',
                type: PropertyType::STRING,
                description: 'Nome della persona da salutare',
                required: true
            ),
        ];
    }

    public function __invoke(string $name): string
    {
        return "Ciao $name! Benvenuto!";
    }
}

// Agent con tool
class TestAgent extends Agent
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
        return 'Sei un assistente. Se ti chiedono di salutare qualcuno, usa il tool greet.';
    }

    protected function tools(): array
    {
        return [
            new GreetTool(),
        ];
    }
}

try {
    echo "API Key: " . substr(GEMINI_API_KEY, 0, 15) . "...\n";
    echo "Creating TestAgent with GreetTool...\n";

    $agent = new TestAgent(GEMINI_API_KEY);
    echo "Agent created!\n";

    echo "\n=== Test 1: Messaggio semplice (no tool) ===\n";
    $start = microtime(true);
    $response = $agent->chat(new UserMessage('Ciao!'));
    $elapsed = round(microtime(true) - $start, 2);
    echo "Response ({$elapsed}s): " . $response->getContent() . "\n";

    echo "\n=== Test 2: Messaggio che dovrebbe usare tool ===\n";
    $start = microtime(true);
    $response = $agent->chat(new UserMessage('Saluta Mario'));
    $elapsed = round(microtime(true) - $start, 2);
    echo "Response ({$elapsed}s): " . $response->getContent() . "\n";

    echo "\nSUCCESS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
