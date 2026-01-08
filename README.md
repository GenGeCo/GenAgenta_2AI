# GenAgenTa 2AI

**Rete Neurale Temporale delle Relazioni Commerciali con Dual Brain AI**

Sistema CRM evoluto basato su metafora neurale con assistente AI intelligente a doppio cervello:
- **Gemini Flash** per interazioni veloci e frequenti (gratuito)
- **Claude Sonnet/GPT-4** tramite OpenRouter per analisi complesse (quando serve potenza)

---

## 🚀 Architettura

### Stack Tecnologico
- **Frontend:** React + TypeScript + Vite + Leaflet (mappe)
- **Backend:** PHP 8.2 + MySQL 8.0
- **Server:** Hetzner CX23 (Ubuntu 24.04 + Nginx)
- **AI:** Gemini Flash 1.5 (primary) + OpenRouter (advanced)
- **SSL:** Let's Encrypt (Certbot)

### Server Produzione
- **IP:** 46.224.202.91
- **URL:** http://46.224.202.91 (TODO: configurare dominio)
- **Costo:** €3.49/mese

---

## 🧠 Dual Brain AI Architecture

L'assistente AI usa un approccio a doppio cervello:

1. **Fast Brain (Gemini Flash 1.5)**
   - Risponde alle domande comuni
   - Suggerimenti rapidi durante la navigazione
   - Completamento automatico
   - Gratuito e illimitato

2. **Deep Brain (Claude Sonnet / GPT-4)**
   - Analisi complesse dei dati
   - Generazione report dettagliati
   - Strategie commerciali avanzate
   - A pagamento, usato solo quando necessario

---

## 📦 Setup Locale per Sviluppo

### 1. Prerequisiti

- PHP 8.x con estensione PDO_MySQL
- MySQL/MariaDB
- Node.js 18+
- npm

### 2. Database

Crea database MySQL:

```sql
CREATE DATABASE genagenta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importa schema:

```bash
mysql -u root genagenta < database/schema.sql
mysql -u root genagenta < database/mock_data.sql
```

### 3. Configurazione Backend

Copia `.env.example` in `.env`:

```bash
cp .env.example .env
```

Configura `.env`:

```env
ENVIRONMENT=development
DB_HOST=localhost
DB_NAME=genagenta
DB_USER=root
DB_PASS=your_password
JWT_SECRET=generate_random_string_min_32_chars
GEMINI_API_KEY=your_gemini_key_from_google_ai_studio
OPENROUTER_API_KEY=your_openrouter_key_optional
```

### 4. Avvia Backend

```bash
cd backend
php -S localhost:8000
```

### 5. Installa e Avvia Frontend

```bash
cd frontend
npm install
npm run dev
```

### 6. Accedi

Apri http://localhost:5173

**Credenziali demo:**
- Email: `admin@gruppogea.net`
- Password: `admin123`

---

## 🌐 Deploy su Server Hetzner

### Setup SSH

La chiave SSH è in `.ssh/hetzner_genagenta` (esclusa da git).

Connessione:
```bash
ssh -i .ssh/hetzner_genagenta root@46.224.202.91
```

### Deploy Frontend

```bash
cd frontend
npm run build
scp -i ../.ssh/hetzner_genagenta -r dist/* root@46.224.202.91:/var/www/genagenta/dist/
ssh -i .ssh/hetzner_genagenta root@46.224.202.91 "chown -R www-data:www-data /var/www/genagenta/dist"
```

### Deploy Backend

```bash
scp -i .ssh/hetzner_genagenta -r backend/* root@46.224.202.91:/var/www/genagenta/backend/
ssh -i .ssh/hetzner_genagenta root@46.224.202.91 "chown -R www-data:www-data /var/www/genagenta/backend"
```

### Deploy Database

```bash
# Importa schema su server
mysql -h localhost -u genagenta -p genagenta < database/schema.sql
```

Password DB: vedi file `.env` sul server

---

## 📁 Struttura Progetto

```
GenAgenTa_2AI/
├── backend/
│   ├── api/              # Endpoint REST
│   │   ├── auth/         # Login, registrazione, PIN
│   │   ├── neuroni/      # CRUD entità/neuroni
│   │   ├── sinapsi/      # CRUD relazioni/sinapsi
│   │   ├── ai/           # Chat AI e tools
│   │   ├── note/         # Note personali
│   │   └── stats/        # Dashboard e statistiche
│   ├── config/           # Configurazione e .env loader
│   └── includes/         # Helper functions
│
├── frontend/
│   ├── src/
│   │   ├── components/   # Componenti React
│   │   ├── pages/        # Login, Register, Dashboard
│   │   ├── hooks/        # useAuth, useData, useCopilot
│   │   ├── types/        # TypeScript types
│   │   └── utils/        # API client
│   └── dist/             # Build (deployato su server)
│
├── database/
│   ├── schema.sql        # Struttura completa DB
│   ├── migrations/       # Migrazioni incrementali
│   └── mock_data.sql     # Dati di test
│
├── .ssh/                 # Chiave SSH server (esclusa da git)
├── .env.example          # Template configurazione
├── .gitignore            # File esclusi da git
└── README.md             # Questa documentazione
```

---

## 🔐 Sicurezza

### File Sensibili (esclusi da git)

- `.env` - Credenziali database e API keys
- `.ssh/` - Chiavi SSH per accesso server
- `chiave.md` - Documentazione chiavi
- `SESSIONE_*.md` - Log sessioni di lavoro

### API Keys

- **Gemini:** Google AI Studio (gratuita) - https://aistudio.google.com/apikey
- **OpenRouter:** (opzionale) - https://openrouter.ai/keys

---

## 🎯 Roadmap

### ✅ Completato
- Server Hetzner configurato
- Database MySQL funzionante
- Frontend e Backend deployati
- Registrazione/Login operativi
- Mappa e visualizzazione dati

### 🚧 In Corso
- Configurazione SSL (Let's Encrypt)
- Implementazione Dual Brain AI
- Integrazione Gemini Flash

### 📋 TODO
- [ ] Configurare dominio DNS
- [ ] Abilitare HTTPS con certbot
- [ ] Implementare AI chat completa
- [ ] Sistema di deploy automatico (GitHub Actions)
- [ ] Backup automatico database

---

## 📞 Contatti

**Progetto:** GenAgenTa 2AI
**Repository:** https://github.com/GenGeCo/GenAgenta_2AI
**Server:** Hetzner CX23 - 46.224.202.91

---

## 📄 Licenza

Proprietario - Gruppo GeA
