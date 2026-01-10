# NUOVO GenAgenTa SISTEM
## Piattaforma per l'Edilizia - Documento di Progettazione

---

## 1. VISIONE GENERALE

### 1.1 Cosa è GenAgenTa
Una piattaforma grafica basata su **neuroni (entità) e connessioni (relazioni)** specializzata nel settore edilizio. Ogni utente ha il proprio "mondo" - il suo grafo personale di cantieri, collaboratori, fornitori e relazioni.

### 1.2 Modello di Business
- **Totalmente gratuita** per gli utenti finali
- Obiettivo: **massima diffusione** nel settore edilizio
- **Modello "Social"**: servizio gratis in cambio di dati

### 1.3 Chi Siamo (Admin)
> **I proprietari della piattaforma sono una rete di rivendite edili** con sedi in Italia.

Obiettivi strategici:
- Sapere **dove** sono i cantieri (opportunità di vendita)
- Sapere **chi** lavora con chi (rete di influenza)
- Sapere **cosa** serve (capitolati = lista materiali)
- Identificare **professionisti affidabili** (connessioni = reputazione)

**Non invasivi**: gli utenti non sanno che dietro c'è una rivendita. Vedono solo un'app utile.

### 1.4 Architettura a Due Livelli
| Livello | Piattaforma | Utenti |
|---------|-------------|--------|
| **Admin** | Web App (questa) | Proprietari del sistema - vedono TUTTO |
| **Utenti** | App Mobile (Android/iOS) | Muratori, Tecnici, Architetti, Fornitori, ecc. |

---

## 2. IL CONCETTO DI "MONDO PERSONALE"

### 2.1 Ogni Utente = Un Mondo
Quando un utente si logga, vede **solo il suo grafo**:
- I suoi cantieri
- I suoi collaboratori
- I suoi fornitori
- Le sue connessioni

**Solo gli Admin** (noi) possono filtrare e vedere tutti i mondi aggregati.

### 2.2 Neuroni (Entità)
Ogni "neurone" rappresenta un'entità nel mondo edilizio:

| Tipo Entità | Descrizione | Chi la crea |
|-------------|-------------|-------------|
| **Cantiere** | Luogo di lavoro con coordinate GPS | Muratori, Tecnici, chiunque |
| **Studio Tecnico** | Ufficio di architetti/ingegneri | Tecnici |
| **Rivendita** | Fornitore materiali | Rivenditori o altri che li aggiungono |
| **Impresa** | Azienda edile | Titolari impresa |
| **Persona** | Professionista singolo | Auto-registrazione |

### 2.3 Connessioni (Relazioni)
Le connessioni hanno un **tipo** che descrive la relazione:

| Tipo Connessione | Esempio |
|------------------|---------|
| `progetta` | Tecnico → Cantiere |
| `lavora_in` | Muratore → Cantiere |
| `fornisce` | Rivendita → Cantiere |
| `consulenza` | Architetto → Impresa |
| `compra_da` | Impresa → Rivendita |
| `collabora` | Muratore ↔ Elettricista |

### 2.4 Cronologia Temporale
**IMPORTANTE**: Ogni connessione ha una dimensione temporale.
- Data inizio connessione
- Data fine connessione (opzionale)
- Storico: "Nel 2024 lavoravano insieme, nel 2025 si sono disconnessi"

---

## 3. SISTEMA DI REGISTRAZIONE E DISCOVERY

### 3.1 Due Modi per Entrare nel Sistema

#### A) Auto-registrazione
L'utente scarica l'app, si registra, crea il suo profilo e inizia a costruire il suo mondo.

#### B) Invito/Aggiunta
Un utente esistente aggiunge un collaboratore:
- Se esiste già → propone di connettersi
- Se non esiste → crea l'entità (in attesa che quella persona si registri)

### 3.2 Flusso di Connessione (Discovery)

```
MURATORE cerca "Studio Vincenzo"
        ↓
    [Ricerca nel DB]
        ↓
    ┌─────────────────────────────────┐
    │ Trovato "Studio Vincenzo"       │
    │ Via Roma 15, Napoli             │
    │                                 │
    │ [È questo?]  [No, è diverso]    │
    └─────────────────────────────────┘
        ↓ (È questo)
    Richiesta connessione inviata
        ↓
    VINCENZO riceve notifica:
    "Mario Rossi (Muratore) vuole connettersi"
        ↓
    [Accetta] [Rifiuta]
        ↓ (Accetta)
    Connessione attiva con timestamp
```

### 3.3 Disconnessione
- Ogni parte può disconnettersi
- La connessione rimane nello storico con data fine
- Utile per: "Chi ha lavorato con chi e quando"

---

## 4. INTEGRAZIONE ACCA PRIMUS

### 4.1 Cos'è Primus
Software standard nel settore per:
- Computi metrici
- Preventivi
- Capitolati
- Contabilità lavori

### 4.2 Formati Supportati
| Formato | Tipo | Priorità |
|---------|------|----------|
| **DCF** | Proprietario ACCA (ma libero) | 🔴 ALTA |
| **XML** | Export standard | 🟡 MEDIA |
| **Excel** | Export tabellare | 🟢 BASE |

### 4.3 Cosa Importiamo da Primus
- **Capitolati**: lista lavorazioni con prezzi
- **Cicli**: sequenze di lavorazioni da eseguire
- **Schede tecniche**: specifiche materiali

### 4.4 Filosofia di Integrazione
> **Affianchiamo, non sostituiamo**

Primus resta lo strumento per fare preventivi. GenAgenTa offre:
- Mappa visiva dei cantieri
- Associazione capitolati ↔ cantieri
- Tracking cicli e avanzamento
- Rete di collaboratori

---

## 5. STRUTTURA DATI CANTIERE

### 5.1 Entità Cantiere
```
Cantiere {
    id
    nome
    coordinate_gps (lat, lng)
    indirizzo
    proprietario_id (chi l'ha creato)
    data_creazione
    data_inizio_lavori
    data_fine_prevista
    stato (pianificato, in_corso, completato, sospeso)
}
```

### 5.2 Documenti Associabili
- Capitolati (import da Primus)
- Cicli lavorazione
- Schede tecniche materiali
- Foto avanzamento
- Note

### 5.3 Connessioni Cantiere
Un cantiere può avere connessioni con:
- Tecnici (progetta, dirige_lavori)
- Muratori (lavora_in)
- Fornitori (fornisce)
- Committente (commissiona)

---

## 6. PANNELLO ADMIN (WEB APP)

### 6.1 Funzionalità Admin
Noi proprietari vediamo:
- **Mappa globale** con tutti i cantieri di tutti
- **Tutti gli attori** registrati
- **Tutte le connessioni** tra entità
- **Filtri avanzati**: per zona, tipo, periodo, ecc.
- **Statistiche aggregate**: quanti cantieri, dove, chi lavora di più, ecc.

### 6.2 Cosa NON vediamo (da decidere)
- [ ] Prezzi dei preventivi?
- [ ] Dettagli economici?
- [ ] Solo struttura (chi, dove, cosa)?

---

## 7. APP MOBILE (Android/iOS)

### 7.1 Funzionalità Core

#### Per tutti gli utenti:
- Registrazione/Login
- Creazione entità (cantiere, studio, ecc.)
- Ricerca e connessione con altri
- Mappa del proprio mondo
- Notifiche (richieste connessione, aggiornamenti)

#### Per Muratori/Applicatori:
- Creazione cantiere con **un clic sulla mappa** (dove sono)
- Import capitolati da tecnico
- Checklist cicli da eseguire
- Foto avanzamento

#### Per Tecnici/Architetti:
- Creazione cantieri e studi
- Export/Import da Primus (DCF, XML, Excel)
- Condivisione capitolati con muratori
- Gestione rete collaboratori

### 7.2 Funzionalità Offline (DA DEFINIRE)
- [ ] L'app deve funzionare offline?
- [ ] Sincronizzazione quando torna connessione?
- [ ] Quali dati disponibili offline?

---

## 8. DECISIONI PRESE

### 8.1 Dati Sensibili
> I prezzi dei preventivi/capitolati sono visibili a voi admin?

**RISPOSTA**: ✅ Sì, admin vede TUTTO (prezzi, quantità, dettagli)

### 8.2 Offline Mode
> L'app deve funzionare offline?

**RISPOSTA**: ✅ Sì, modalità "promemoria"
- Offline: salva in locale (nuova lavorazione, note, foto)
- Online: sincronizza e carica
- Modello Shazam: "non trovo ora, cerco dopo"

### 8.3 Priorità Integrazione Primus
> Partiamo con import Excel o DCF?

**RISPOSTA**: ⏸️ Per ora solo progettazione, implementazione dopo

### 8.4 Notifiche Push
> Servono notifiche push?

**RISPOSTA**: ✅ Sì, bidirezionali
- "Mario vuole connettersi a te"
- "Hai una nuova richiesta su Cantiere X"
- ecc.

### 8.5 Multi-lingua
> Solo italiano?

**RISPOSTA**: 🌍 Multilingua
- Italiano (principale)
- Rumeno (priorità alta - molti operai)
- Albanese (priorità alta)
- Polacco (priorità alta)

### 8.6 Rivendite nell'App
> Le rivendite sono visibili agli utenti?

**RISPOSTA**: ⚠️ NO - Le rivendite NON appaiono nell'app utenti
- Gli utenti vedono solo: cantieri, tecnici, muratori, imprese
- Le rivendite esistono SOLO nel pannello admin
- Motivo: privacy commerciale + non svelare che dietro ci sono rivendite

## 9. ALTRE DECISIONI

### 9.1 Stack Mobile
> React Native, Flutter, o PWA?

**RISPOSTA**: ✅ **Flutter**
- Una codebase → Android + iOS
- Hardware disponibile: Android Studio + Mac
- Offline-first nativo
- Notifiche push funzionano bene su entrambi
- Codice generabile in modo prevedibile e stabile

### 9.2 Branding
> L'app si chiamerà sempre GenAgenTa o nuovo nome per il mercato edilizia?

**RISPOSTA**: ⏸️ Da decidere in seguito

### 9.3 Tempistiche
> Prima fix web app o sviluppo app mobile?

**RISPOSTA**: ✅ Prima fix web app admin
- Altra istanza Claude sta sistemando i bug
- Senza connettori funzionanti non possiamo testare l'integrazione con app
- Sequenza: Web App funzionante → API stabili → App Mobile

---

## 10. ASSISTENTE AI INTEGRATO

### 10.1 Ruolo dell'AI
> **L'AI è un TRADUTTORE, non un autore**

L'AI traduce il linguaggio "da cantiere" in linguaggio tecnico per guidare l'utente verso i cicli corretti.

**NON fa:**
- ❌ Scrivere capitolati
- ❌ Decidere al posto dell'utente
- ❌ Inventare soluzioni

**FA:**
- ✅ Tradurre descrizioni informali → termini tecnici
- ✅ Fare domande per capire meglio il problema
- ✅ Proporre cicli esistenti tra cui scegliere
- ✅ Spiegare differenze tra opzioni

### 10.2 Esempio di Conversazione

```
MURATORE: "Ho un muro tutto crepato, fa schifo"

AI: "Capisco, parliamo di una parete verticale con fessurazioni.
     Per consigliarti il ciclo giusto ho bisogno di capire:

     Le crepe sono:
     □ Sottili (< 1mm) - tipo capello
     □ Medie (1-3mm) - ci passa un foglio
     □ Larghe (> 3mm) - ci passa una moneta
     □ Strutturali - passanti, si vede dall'altra parte"

MURATORE: "Medie, ci passa un foglio"

AI: "Ok, fessurazioni medio-piccole su parete.
     Ti consiglio questi cicli:

     1. CICLO RASATURA BASE
        - Stuccatura + rasante + pittura
        - Per fessure non attive

     2. CICLO CON RETE
        - Stuccatura + rete in fibra + rasante
        - Se le fessure potrebbero riaprirsi

     3. CICLO ELASTOMERICO
        - Prodotti elastici che seguono il movimento
        - Se il muro "lavora"

     Quale situazione ti sembra più simile?"

MURATORE: sceglie → ciclo selezionato nel cantiere
```

### 10.3 Tecnologia

| Aspetto | Scelta |
|---------|--------|
| **Provider** | Gemini API (Google, stesso ecosistema Flutter) |
| **Offline** | ❌ No - richiede connessione |
| **Lingua** | Italiano + lingue dell'app (RO, AL, PL) |
| **Costo** | Basso/gratuito per volumi iniziali |

### 10.4 Database Cicli
L'AI propone cicli da un **database predefinito**:
- Cicli creati da voi (esperti)
- Categorizzati per tipo di intervento
- Con materiali, fasi, note tecniche
- L'AI NON inventa cicli, li cerca e propone

### 10.5 Valore Aggiunto
- **Muratori stranieri**: AI traduce anche la lingua, non solo il gergo
- **Muratori esperti**: saltano l'AI, scelgono direttamente
- **Apprendisti**: l'AI diventa formativa
- **Voi admin**: vedete quali problemi sono più frequenti (dati!)

---

## 11. VALORE STRATEGICO DEI DATI

### 11.1 Cosa Saprete (Intelligence di Mercato)

| Dato | Valore per la Rivendita |
|------|-------------------------|
| **Mappa cantieri** | Dove mandare gli agenti commerciali |
| **Capitolati importati** | Lista esatta materiali necessari per ogni cantiere |
| **Chi lavora dove** | Quali muratori/imprese sono attivi nella zona |
| **Rete connessioni** | Chi influenza chi (il tecnico consiglia, il muratore esegue) |
| **Cronologia** | Chi lavora di più, chi è affidabile (molte connessioni stabili) |
| **Trend** | Zone in crescita, nuovi cantieri, stagionalità |

### 11.2 Come Usare i Dati (Esempi)

**Scenario 1 - Nuovo Cantiere**
```
Sistema: "Nuovo cantiere a Napoli Nord"
        + Capitolato importato: 500mq piastrelle, 200mq intonaco
        ↓
Azione: Agente vi contatta prima della concorrenza
```

**Scenario 2 - Muratore Affidabile**
```
Sistema: "Mario Rossi - 15 cantieri completati, 8 tecnici connessi"
        ↓
Azione: Proporre convenzione/sconto fedeltà
```

**Scenario 3 - Zona Calda**
```
Sistema: "Zona X: +40% cantieri ultimi 3 mesi"
        ↓
Azione: Valutare apertura nuovo punto vendita
```

### 11.3 Privacy e GDPR
- [ ] Informativa privacy chiara (dati usati per "migliorare il servizio")
- [ ] Dati aggregati, mai venduti a terzi
- [ ] Utente può cancellare account e dati
- [ ] Conformità GDPR obbligatoria

---

## 11. FASI DI SVILUPPO (PROPOSTA)

### Fase 1: Fondamenta
- [ ] Fix bug migrazione attuale
- [ ] Stabilizzare sistema neuroni/connessioni
- [ ] Aggiungere tipi entità specifici edilizia
- [ ] Aggiungere tipi connessione specifici

### Fase 2: App Mobile MVP
- [ ] App base Android/iOS
- [ ] Registrazione e login
- [ ] Creazione cantiere da GPS
- [ ] Mappa personale
- [ ] Sistema connessioni

### Fase 3: Integrazione Primus
- [ ] Parser formato DCF
- [ ] Import capitolati
- [ ] Associazione a cantieri
- [ ] Visualizzazione cicli

### Fase 4: Pannello Admin Avanzato
- [ ] Filtri avanzati
- [ ] Statistiche
- [ ] Export dati
- [ ] Dashboard analytics

---

## 12. NOTE TECNICHE

### 12.1 Stack Attuale
- **Frontend**: React + TypeScript + Vite
- **Backend**: PHP
- **Database**: MySQL (presumo)
- **Server**: Hetzner (migrato da Netsons)

### 12.2 Problemi Noti (migrazione)
- Creazione neuroni non funziona
- Creazione entità non funziona
- Creazione connessioni non funziona
- (altro collega sta lavorando su questi)

### 12.3 Stack App Mobile
**Decisione: Flutter**
- Linguaggio: Dart
- IDE: Android Studio (già disponibile)
- Build iOS: Mac (già disponibile)
- Una codebase → due piattaforme

---

## CHANGELOG DOCUMENTO

| Data | Modifica |
|------|----------|
| 2025-01-10 | Creazione documento iniziale |

---

*Documento in evoluzione - continuare con domande e risposte*
