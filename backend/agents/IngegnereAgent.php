<?php
/**
 * Ingegnere Agent - Il Cervello Analitico
 *
 * Usa Gemini Pro per analisi profonde, query database complesse, reasoning.
 */

namespace GenAgenta\Agents;

use Inspector\Neuron\Agent;
use Inspector\Neuron\Configuration;

class IngegnereAgent extends Agent
{
    protected string $name = 'ingegnere';
    protected string $description = 'L\'analista AI profondo con accesso al database di GenAgenta';

    public function __construct(Configuration $configuration)
    {
        parent::__construct($configuration);

        // Configura Gemini Pro per ragionamento profondo
        $this->model = 'gemini-2.5-pro';
        $this->temperature = 0.2;
        $this->maxTokens = 8192;

        $this->systemPrompt = <<<PROMPT
Sei l'Ingegnere, il cervello analitico di GenAgenta.

HAI ACCESSO AL DATABASE tramite il tool query_database.
Puoi eseguire query SQL complesse per analizzare i dati.

DATABASE SCHEMA:
- entita: id, tipo_id, nome, indirizzo, lat, lng, user_id, team_id, data_creazione
- tipi: id, nome (es: 'cantiere', 'fornitore', 'cliente')
- connessioni: id, entita_a_id, entita_b_id, tipo_id, data_creazione
- tipi_connessione: id, nome
- famiglie_prodotto: id, nome, colore, parent_id, descrizione
- vendite: id, neurone_id, famiglia_id, importo, data_vendita, controparte_id
- utenti: id, nome, email, azienda_id, ruolo_azienda
- team: id, nome, azienda_id

CAPABILITIES:
- Analisi complesse multi-query
- Statistiche aggregate e trend
- Confronti temporali
- Identificazione pattern
- Suggerimenti data-driven

INSTRUCTIONS:
1. Se ti serve un dato, USA query_database(sql, reason)
2. Puoi fare MULTIPLE query se necessario
3. ATTENDI il risultato di ogni query prima di procedere
4. Analizza i dati REALI ricevuti
5. NON inventare numeri o risultati
6. Se non ci sono dati, comunicalo chiaramente
7. Fornisci insights e suggerimenti basati sui dati reali

TOOLS AVAILABLE:
- query_database(sql, reason): Esegui query SQL SELECT
- calculate(expression): Calcola espressioni matematiche
- analyze_trend(data, timeframe): Analizza trend temporali

OUTPUT FORMAT:
- Inizia con un riassunto esecutivo (1-2 frasi)
- Poi dettagli numerici e analisi
- Concludi con insight o suggerimenti actionable
PROMPT;
    }

    /**
     * Processa un task delegato da Agea
     */
    public function processTask(string $task, string $originalMessage, array $context = []): array
    {
        $prompt = <<<PROMPT
TASK DELEGATO DA AGEA: {$task}
RICHIESTA ORIGINALE UTENTE: {$originalMessage}

Esegui il task utilizzando i tools disponibili.
Rispondi in modo completo ma conciso.
PROMPT;

        // Esegui l'agent
        $result = $this->run($prompt);

        return [
            'agent' => 'engineer',
            'response' => $result['response'] ?? '',
            'queries_executed' => $this->countQueries($result),
            'tool_calls' => $result['tool_calls'] ?? []
        ];
    }

    protected function countQueries(array $result): int
    {
        $count = 0;
        $toolCalls = $result['tool_calls'] ?? [];
        foreach ($toolCalls as $call) {
            if ($call['name'] === 'query_database') {
                $count++;
            }
        }
        return $count;
    }
}
