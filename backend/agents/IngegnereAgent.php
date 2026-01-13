<?php
/**
 * Ingegnere Agent - Il Cervello Analitico
 *
 * Usa Gemini Pro per analisi profonde, query database complesse, reasoning.
 */

namespace GenAgenta\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use GenAgenta\Tools\QueryDatabaseTool;

class IngegnereAgent extends Agent
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
            model: 'gemini-2.0-flash-thinking-exp',  // Pro per ragionamento profondo
            parameters: [
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 8192
                ]
            ]
        );
    }

    public function instructions(): string
    {
        return <<<PROMPT
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

OUTPUT FORMAT:
- Inizia con un riassunto esecutivo (1-2 frasi)
- Poi dettagli numerici e analisi
- Concludi con insight o suggerimenti actionable
PROMPT;
    }

    /**
     * @return \NeuronAI\Tools\ToolInterface[]
     */
    protected function tools(): array
    {
        return [
            new QueryDatabaseTool(),
        ];
    }
}
