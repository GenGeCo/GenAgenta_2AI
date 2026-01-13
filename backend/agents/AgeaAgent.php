<?php
/**
 * Agea Agent - L'Amica di Interfaccia
 *
 * Usa Gemini Flash per risposte veloci, conversazione naturale e decisioni di routing.
 * Delega task complessi all'Ingegnere.
 */

namespace GenAgenta\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use GenAgenta\Tools\DelegateToEngineerTool;
use GenAgenta\Tools\MapFlyToTool;
use GenAgenta\Tools\MapSelectEntityTool;
use GenAgenta\Tools\SetMapStyleTool;

class AgeaAgent extends Agent
{
    protected string $geminiApiKey;

    public function __construct(string $geminiApiKey)
    {
        $this->geminiApiKey = $geminiApiKey;
    }

    protected function provider(): Gemini
    {
        return new Gemini(
            key: $this->geminiApiKey,
            model: 'gemini-2.0-flash',
            parameters: [
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048
                ]
            ]
        );
    }

    public function instructions(): string
    {
        $promptFile = __DIR__ . '/../config/ai/prompt_base.txt';
        $basePrompt = file_exists($promptFile) ? file_get_contents($promptFile) : '';

        return $basePrompt . <<<PROMPT


=== DUAL BRAIN ARCHITECTURE ===

Hai un collega specializzato: l'INGEGNERE (Gemini Pro).
Lui è il CERVELLO ANALITICO con accesso diretto al database e analisi profonde.

QUANDO DELEGARE ALL'INGEGNERE:
- Analisi complesse: "analizza vendite ultimi 6 mesi", "trend cantieri"
- Query database multiple o aggregate
- Statistiche e confronti: "confronta performance 2024 vs 2025"
- Ragionamento profondo su molti dati
- Debug e analisi codice

TU GESTISCI DIRETTAMENTE:
- Chat normale, saluti, conversazione
- Azioni mappa (volare a coordinate, selezionare entità, cambiare stile)
- Domande su dati già visibili nel contesto UI
- Conferme e feedback veloci

TOOLS DISPONIBILI:
- delegate_to_engineer(task): Delega task complesso all'Ingegnere
- fly_to(query): Sposta la vista mappa a una località
- select_entity(entity_id): Seleziona un'entità sulla mappa
- set_map_style(style): Cambia lo stile della mappa

WORKFLOW:
1. Ricevi richiesta utente + contesto UI
2. Valuta complessità
3. Se semplice → Rispondi direttamente (usa tools se serve)
4. Se complesso → delegate_to_engineer(descrizione chiara del task)
5. Se deleghi, l'Ingegnere ti torna il risultato
6. Tu lo presenti all'utente in modo amichevole e colloquiale
PROMPT;
    }

    /**
     * @return \NeuronAI\Tools\ToolInterface[]
     */
    protected function tools(): array
    {
        return [
            new DelegateToEngineerTool(),
            new MapFlyToTool(),
            new MapSelectEntityTool(),
            new SetMapStyleTool(),
        ];
    }
}
