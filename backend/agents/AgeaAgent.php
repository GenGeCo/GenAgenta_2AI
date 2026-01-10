<?php
/**
 * Agea Agent - L'Amica di Interfaccia
 *
 * Usa Gemini Flash per risposte veloci, conversazione naturale e decisioni di routing.
 * Delega task complessi all'Ingegnere.
 */

namespace GenAgenta\Agents;

use Inspector\Neuron\Agent;
use Inspector\Neuron\Configuration;

class AgeaAgent extends Agent
{
    protected string $name = 'agea';
    protected string $description = 'L\'assistente AI veloce e conversazionale di GenAgenta';

    public function __construct(Configuration $configuration)
    {
        parent::__construct($configuration);

        // Configura Gemini Flash per velocità
        $this->model = 'gemini-2.5-flash';
        $this->temperature = 0.7;
        $this->maxTokens = 2048;

        // Carica il prompt base
        $this->loadSystemPrompt();
    }

    protected function loadSystemPrompt(): void
    {
        $promptFile = __DIR__ . '/../config/ai/prompt_base.txt';
        $basePrompt = file_exists($promptFile) ? file_get_contents($promptFile) : '';

        // Placeholder utente (TODO: sostituire con dati reali da auth)
        $userName = 'Genaro';
        $userEmail = 'genaro@gruppogea.net';
        $userRole = 'admin';
        $aziendaId = '1';

        if ($basePrompt) {
            $basePrompt = str_replace([
                '{{user_nome}}',
                '{{user_email}}',
                '{{user_ruolo}}',
                '{{azienda_id}}'
            ], [
                $userName,
                $userEmail,
                $userRole,
                $aziendaId
            ], $basePrompt);
        }

        $this->systemPrompt = $basePrompt . <<<PROMPT


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
- Azioni mappa (volare a coordinate, selezionare entità)
- Creazione/modifica singole entità semplici
- Domande su dati già visibili nel contesto UI
- Conferme e feedback veloci

TOOLS DISPONIBILI:
- delegate_to_engineer(task): Delega task complesso all'Ingegnere
- query_database(sql): Query SQL semplice (max 1, per controlli rapidi)
- map_fly_to(lat, lng, zoom): Sposta la vista mappa
- map_select_entity(entity_id): Seleziona un'entità sulla mappa
- create_entity(tipo, nome, indirizzo, ...): Crea nuova entità
- update_entity(entity_id, fields): Aggiorna entità esistente

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
     * Processa un messaggio utente con contesto
     */
    public function processMessage(string $message, array $context = []): array
    {
        // Inietta il contesto UI nel prompt
        if (!empty($context)) {
            $contextStr = "\n\n=== CONTESTO UI CORRENTE ===\n";
            $contextStr .= json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $contextStr .= "\n=== FINE CONTESTO ===\n";
            $this->addContextToPrompt($contextStr);
        }

        // Esegui l'agent con streaming
        $result = $this->run($message);

        return [
            'agent' => 'agea',
            'response' => $result['response'] ?? '',
            'tool_calls' => $result['tool_calls'] ?? [],
            'delegated' => $this->hasDelegated($result)
        ];
    }

    protected function hasDelegated(array $result): bool
    {
        $toolCalls = $result['tool_calls'] ?? [];
        foreach ($toolCalls as $call) {
            if ($call['name'] === 'delegate_to_engineer') {
                return true;
            }
        }
        return false;
    }
}
