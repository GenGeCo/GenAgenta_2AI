Ecco cosa ho trovato di MOLTO interessante per noi:
1. Thinking Mode - Vedere cosa pensa Agea

// Aggiungere alla config:
'thinking_config' => [
    'include_thoughts' => true,
    'thinking_level' => 'medium'  // low/medium/high per Gemini 3
]
Benefici per noi:
Vediamo il ragionamento di Agea prima della risposta
Vediamo il ragionamento di Agea prima della risposta
Possiamo mostrare all'utente "Sto ragionando su..." con pensieri VERI
Debug: capiamo PERCHÉ fa certe scelte sbagliate
thinking_level: low = veloce/economico, high = ragionamento profondo
Costo: I thinking tokens si pagano come output tokens
2. Interactions API - Questo è GROSSO!
Il problema attuale:
Mandiamo TUTTA la history ad ogni messaggio
Facciamo compaction manuale
Limitiamo a 30 messaggi per non sforare
Con Interactions API:
Server-side state: Google tiene la conversazione, noi mandiamo solo previous_interaction_id
55 giorni di memoria (tier a pagamento)
Tool orchestration automatica: gestisce lui i loop tool call → result
Background execution: task lunghi in asincrono

# Invece di mandare tutta la history:
interaction = client.interactions.create(
    previous_interaction_id="abc123",  # Riferimento!
    input="Connetti Mario a Giovanni",
    tools=[...]
)
Per GenAgenta propongo:
Feature	Valore per noi	Difficoltà	Costo
Thinking Mode	Alto (debug + UX)	Bassa	+tokens
Interactions API	ALTISSIMO	Media	Da verificare
Gemini 3	Alto (agentic)	Bassa	Da verificare
Domanda chiave: L'Interactions API potrebbe eliminare tutto il nostro codice di gestione history/compaction. Vuoi che verifichi i costi?

---

## 3. BEST PRACTICE Multi-Turn Conversation (Scoperto 13/01/2026)

**Fonte ufficiale:** https://ai.google.dev/gemini-api/docs/text-generation

### Struttura Corretta del Payload

```json
{
  "system_instruction": {
    "parts": [{"text": "Tu sei Agea, assistente AI..."}]
  },
  "contents": [
    {"role": "user", "parts": [{"text": "ciao"}]},
    {"role": "model", "parts": [{"text": "Ciao! Come posso aiutarti?"}]},
    {"role": "user", "parts": [{"text": "vai a Roma"}]}
  ],
  "tools": [...],
  "generationConfig": {...}
}
```

### REGOLE FONDAMENTALI

1. **`system_instruction` SEPARATA** - Non nel contents! Viene cachata automaticamente (75% sconto)
2. **`contents` DEVE alternare user/model** - MAI due user consecutivi o Gemini restituisce risposta vuota
3. **L'ultimo messaggio deve essere sempre `user`**
4. **Se history ha due user consecutivi, salta uno** - Previene errori

### Implicit Caching (Gemini 2.5)

- **Automatico** - Non serve configurare nulla
- **Sconto 75%** sui token ripetuti (stesso prefisso)
- **Minimo:** 1024 token per Flash, 2048 per Pro
- **Verifica:** `usageMetadata.cachedContentTokenCount` nella risposta

### Codice PHP Implementato

```php
// System instruction separata
$payload['system_instruction'] = [
    'parts' => [['text' => $systemPrompt]]
];

// Contents con alternanza garantita
$contents = [];
$lastRole = null;
foreach ($history as $msg) {
    if ($msg['role'] === $lastRole) continue; // Salta duplicati
    $contents[] = ['role' => $msg['role'], 'parts' => [['text' => $msg['content']]]];
    $lastRole = $msg['role'];
}
// Ultimo messaggio sempre user
$contents[] = ['role' => 'user', 'parts' => [['text' => $currentMessage]]];
```

### Errori Comuni da Evitare

| Errore | Sintomo | Soluzione |
|--------|---------|-----------|
| Due user consecutivi | `output_tokens: 0`, risposta vuota | Salta duplicati o aggiungi model placeholder |
| System prompt nel contents | Caching non funziona | Usa `system_instruction` separata |
| History include messaggio corrente | Messaggio duplicato | Filtra ultimo se è user |

---

## 4. Interactions API (TODO - Da Valutare)

Potrebbe semplificare TUTTO:
- Server-side state: Google gestisce la history
- Noi mandiamo solo `previous_interaction_id`
- 55 giorni di memoria

**Status:** Da verificare costi e disponibilità per PHP