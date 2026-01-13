<?php
/**
 * Test AgeaAgent con debug verbose
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

// Tool semplice con callback inline
$testTool = Tool::make(
    name: 'greet',
    description: 'Saluta una persona'
)->addProperty(
    ToolProperty::make('name', PropertyType::STRING, 'Nome persona', true)
)->setCallback(function(string $name): string {
    error_log("Tool greet called with: $name");
    return "Ciao $name!";
});

// Agent semplice
class DebugAgent extends Agent
{
    protected string $key;
    protected $tool;

    public function __construct(string $key, $tool)
    {
        $this->key = $key;
        $this->tool = $tool;
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
        return 'Sei un assistente. Se ti chiedono di salutare usa il tool greet.';
    }

    protected function tools(): array
    {
        return [$this->tool];
    }
}

try {
    echo "Starting debug test...\n";
    echo "API Key: " . substr(GEMINI_API_KEY, 0, 15) . "...\n";

    echo "Creating tool...\n";
    var_dump($testTool->getName());

    echo "Creating agent...\n";
    $agent = new DebugAgent(GEMINI_API_KEY, $testTool);

    echo "Getting tools from agent...\n";
    $tools = $agent->getTools();
    echo "Tools count: " . count($tools) . "\n";

    echo "Bootstrapping tools...\n";
    $bootstrapped = $agent->bootstrapTools();
    echo "Bootstrapped count: " . count($bootstrapped) . "\n";

    echo "Sending chat message...\n";
    $start = microtime(true);

    // Aggiungi observer per debug
    $agent->observe(new class implements \SplObserver {
        public function update(\SplSubject $subject, ?string $event = null, mixed $data = null): void {
            echo "Event: $event\n";
            if ($data) {
                echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        }
    });

    $response = $agent->chat(new UserMessage('Ciao!'));

    $elapsed = round(microtime(true) - $start, 2);
    echo "Response ({$elapsed}s): " . $response->getContent() . "\n";
    echo "SUCCESS!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
