# CopilotKit + AG-UI Protocol Reference
## GenAgenta - Gennaio 2026

Questo documento contiene la documentazione di riferimento per l'integrazione CopilotKit con backend PHP custom tramite AG-UI Protocol.

---

## Architettura Dual Brain GenAgenta

```
┌─────────────────────────────────────────────────────────────┐
│                     FRONTEND (React)                         │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │  CopilotKit     │    │  useCopilotAction hooks         │ │
│  │  CopilotChat    │    │  - fly_to                       │ │
│  │  CopilotPopup   │    │  - set_map_style                │ │
│  └────────┬────────┘    │  - select_entity                │ │
│           │             │  - search_entities              │ │
│           │             └─────────────────────────────────┘ │
│           │ SSE (AG-UI Protocol)                            │
└───────────┼─────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────┐
│                   BACKEND (PHP)                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │              copilot-runtime.php                         ││
│  │  - Riceve richieste CopilotKit                          ││
│  │  - Gestisce discovery (info)                            ││
│  │  - Streaming SSE eventi AG-UI                           ││
│  └────────────────────┬────────────────────────────────────┘│
│                       │                                      │
│  ┌────────────────────▼────────────────────────────────────┐│
│  │              dual-brain-v2.php                           ││
│  │  ┌─────────────┐    ┌─────────────────────────────────┐ ││
│  │  │  AGEA       │    │  INGEGNERE                      │ ││
│  │  │  (Flash)    │───▶│  (Pro)                          │ ││
│  │  │  Veloce     │    │  Analitico                      │ ││
│  │  │  Tool Calls │    │  Query DB                       │ ││
│  │  └─────────────┘    └─────────────────────────────────┘ ││
│  └─────────────────────────────────────────────────────────┘│
│                       │                                      │
│                       ▼                                      │
│              Gemini API (Google)                            │
└─────────────────────────────────────────────────────────────┘
```

---

## AG-UI Protocol - Tipi di Evento

### I 17 Tipi di Evento Ufficiali

| Categoria | Evento | Descrizione |
|-----------|--------|-------------|
| **Lifecycle** | `RUN_STARTED` | Inizio esecuzione agente |
| | `RUN_FINISHED` | Fine esecuzione (successo) |
| | `RUN_ERROR` | Errore durante esecuzione |
| | `STEP_STARTED` | Inizio fase di elaborazione |
| | `STEP_FINISHED` | Fine fase di elaborazione |
| **Text** | `TEXT_MESSAGE_START` | Inizio messaggio testuale |
| | `TEXT_MESSAGE_CONTENT` | Chunk di testo (streaming) |
| | `TEXT_MESSAGE_END` | Fine messaggio testuale |
| **Tool Call** | `TOOL_CALL_START` | Inizio chiamata tool |
| | `TOOL_CALL_ARGS` | Argomenti tool (streaming) |
| | `TOOL_CALL_END` | Fine specifica tool |
| | `TOOL_CALL_RESULT` | Risultato esecuzione tool |
| **State** | `STATE_SNAPSHOT` | Stato completo |
| | `STATE_DELTA` | Aggiornamenti incrementali (JSON Patch) |
| | `MESSAGES_SNAPSHOT` | Storico conversazione |
| **Special** | `RAW` | Passthrough sistema esterno |
| | `CUSTOM` | Eventi custom applicazione |

---

## Formato Eventi SSE

### Struttura Base
Ogni evento SSE ha questa struttura:
```
data: {"type": "EVENT_TYPE", ...altri_campi}

```
(nota: doppio newline alla fine!)

### Lifecycle Events

**RUN_STARTED**
```json
{
  "type": "RUN_STARTED",
  "threadId": "thread_abc123",
  "runId": "run_xyz789"
}
```

**RUN_FINISHED**
```json
{
  "type": "RUN_FINISHED",
  "threadId": "thread_abc123",
  "runId": "run_xyz789"
}
```

### Text Message Events

**TEXT_MESSAGE_START**
```json
{
  "type": "TEXT_MESSAGE_START",
  "messageId": "msg_123",
  "role": "assistant"
}
```

**TEXT_MESSAGE_CONTENT**
```json
{
  "type": "TEXT_MESSAGE_CONTENT",
  "messageId": "msg_123",
  "delta": "Ciao! "
}
```
NOTA: `delta` non può essere stringa vuota!

**TEXT_MESSAGE_END**
```json
{
  "type": "TEXT_MESSAGE_END",
  "messageId": "msg_123"
}
```

### Tool Call Events

**TOOL_CALL_START**
```json
{
  "type": "TOOL_CALL_START",
  "toolCallId": "tc_123",
  "toolCallName": "fly_to"
}
```

**TOOL_CALL_ARGS**
```json
{
  "type": "TOOL_CALL_ARGS",
  "toolCallId": "tc_123",
  "delta": "{\"query\": \"Roma\"}"
}
```
NOTA: `delta` è una stringa JSON che contiene gli argomenti. Può essere inviata in parti o tutta insieme.

**TOOL_CALL_END**
```json
{
  "type": "TOOL_CALL_END",
  "toolCallId": "tc_123"
}
```

---

## Flusso Completo Chat con Tool Call

### Sequenza Eventi
```
1. RUN_STARTED
2. TEXT_MESSAGE_START
3. TOOL_CALL_START          (se serve chiamare un tool)
4. TOOL_CALL_ARGS           (argomenti del tool)
5. TOOL_CALL_END
6. TEXT_MESSAGE_CONTENT     (messaggio dopo tool call)
7. TEXT_MESSAGE_END
8. RUN_FINISHED
```

### Cosa Succede Lato Frontend
1. Frontend riceve `TOOL_CALL_START` + `TOOL_CALL_ARGS` + `TOOL_CALL_END`
2. Frontend accumula i `delta` di `TOOL_CALL_ARGS`
3. Frontend parsa il JSON accumulato
4. Frontend esegue l'handler di `useCopilotAction` con il nome corrispondente
5. Frontend invia il risultato del tool al backend come nuovo messaggio

---

## useCopilotAction - Frontend

### Definizione Action
```typescript
useCopilotAction({
  name: "fly_to",
  description: "Sposta la mappa verso una località",
  parameters: [
    {
      name: "query",       // Nome del parametro
      type: "string",      // Tipo: string, number, boolean, object, array
      description: "Nome della località",
      required: true
    },
    {
      name: "zoom",
      type: "number",
      description: "Livello di zoom (1-20)",
      required: false
    }
  ],
  handler: async ({ query, zoom }) => {
    // Il handler riceve i parametri direttamente destrutturati
    console.log('Query:', query);  // "Roma"
    console.log('Zoom:', zoom);    // undefined o numero

    // Esegui azione e ritorna risultato
    return `Spostato a ${query}`;
  }
});
```

### Come Arrivano gli Argomenti al Handler

CopilotKit parsa automaticamente il JSON da `TOOL_CALL_ARGS.delta` e passa i parametri direttamente al handler come oggetto destrutturato.

Se backend invia:
```json
{"type": "TOOL_CALL_ARGS", "toolCallId": "tc_1", "delta": "{\"query\":\"Roma\",\"zoom\":15}"}
```

Il handler riceve:
```javascript
{ query: "Roma", zoom: 15 }
```

### IMPORTANTE: Corrispondenza Nomi Parametri

I nomi dei parametri nel frontend (`useCopilotAction.parameters[].name`) DEVONO corrispondere esattamente ai nomi nel JSON degli argomenti inviati dal backend.

❌ SBAGLIATO:
- Backend invia: `{"location": "Roma"}`
- Frontend aspetta: `name: "query"`
- Risultato: `query = undefined`

✅ CORRETTO:
- Backend invia: `{"query": "Roma"}`
- Frontend aspetta: `name: "query"`
- Risultato: `query = "Roma"`

---

## Backend PHP - copilot-runtime.php

### Headers Richiesti per SSE
```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Per nginx

// Disabilita buffering PHP
if (ob_get_level()) ob_end_clean();
```

### Funzione Helper per Inviare Eventi
```php
$sendEvent = function($type, $payload) {
    $event = array_merge(['type' => $type], $payload);
    echo "data: " . json_encode($event) . "\n\n";
    flush();
};
```

### Esempio Completo Tool Call
```php
// Tool call start
$toolCallId = uniqid('tc_');
$sendEvent('TOOL_CALL_START', [
    'toolCallId' => $toolCallId,
    'toolCallName' => 'fly_to'
]);

// Tool call args - IMPORTANTE: delta è stringa JSON
$sendEvent('TOOL_CALL_ARGS', [
    'toolCallId' => $toolCallId,
    'delta' => json_encode(['query' => 'Roma', 'zoom' => 15])
]);

// Tool call end
$sendEvent('TOOL_CALL_END', [
    'toolCallId' => $toolCallId
]);
```

---

## Formato Richieste CopilotKit

### Discovery (method: "info")
```json
{
  "method": "info"
}
```

Risposta:
```json
{
  "actions": [...],
  "agents": {...}
}
```

### Agent Run (method: "agent/run")
```json
{
  "method": "agent/run",
  "params": {
    "agentId": "default"
  },
  "body": {
    "threadId": "uuid",
    "runId": "uuid",
    "tools": [...],           // Tools definiti nel frontend
    "context": [...],         // Contesto da useCopilotReadable
    "messages": [             // Storico conversazione
      {
        "id": "uuid",
        "role": "user",
        "content": "vai a Roma"
      }
    ]
  }
}
```

### Tool Result (messaggio dopo esecuzione tool frontend)
```json
{
  "id": "uuid",
  "role": "tool",
  "toolCallId": "tc_123",
  "content": "Risultato del tool"
}
```

---

## Naming Convention

### AG-UI ufficiale usa camelCase per i campi:
- `threadId`
- `runId`
- `messageId`
- `toolCallId`
- `toolCallName`

### Tipi evento in SCREAMING_SNAKE_CASE:
- `RUN_STARTED`
- `TEXT_MESSAGE_START`
- `TOOL_CALL_START`

---

## Troubleshooting

### "Delta must not be an empty string"
Il campo `delta` in `TEXT_MESSAGE_CONTENT` non può essere vuoto. Controlla sempre:
```php
$responseText = trim($responseText);
if (!empty($responseText)) {
    // Invia TEXT_MESSAGE_CONTENT solo se c'è testo
}
```

### "query/location undefined nel handler"
I nomi parametri non corrispondono tra backend e frontend. Verifica che:
1. Backend usi lo stesso nome nel JSON args
2. Frontend usi lo stesso nome in `parameters[].name`

### Tool call non eseguito
Verifica che il nome del tool in `TOOL_CALL_START.toolCallName` corrisponda esattamente al `name` definito in `useCopilotAction`.

---

## Links Utili

- AG-UI Docs: https://docs.ag-ui.com
- CopilotKit Docs: https://docs.copilotkit.ai
- AG-UI PHP Package: https://packagist.org/packages/swisnl/ag-ui-server
- CopilotKit GitHub: https://github.com/CopilotKit/CopilotKit

---

## Changelog Documento

| Data | Modifica |
|------|----------|
| 2026-01-13 | Creazione documento - formato eventi AG-UI, troubleshooting |
