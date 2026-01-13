# Deploy GenAgenta v2

## Prerequisiti

### Configurazione SSH (da fare UNA SOLA VOLTA)

Per evitare che venga chiesta la password ogni volta, devi configurare SSH per usare le chiavi del progetto.

#### Windows - Crea/Modifica il file `C:\Users\edilk\.ssh\config`

```
# GitHub GenAgenta
Host github.com
  HostName github.com
  User git
  IdentityFile E:/Progetti_Portable/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/github_genagenta
  IdentitiesOnly yes

# Hetzner GenAgenta Server
Host hetzner-genagenta
  HostName 46.224.202.91
  User root
  IdentityFile E:/Progetti_Portable/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta
  IdentitiesOnly yes

# Alias diretto per IP (per scp e altri comandi)
Host 46.224.202.91
  HostName 46.224.202.91
  User root
  IdentityFile E:/Progetti_Portable/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta
  IdentitiesOnly yes
```

#### Verifica permessi chiave (importante su Windows con Git Bash)

```bash
# In Git Bash, imposta i permessi corretti sulla chiave
chmod 600 "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta"
chmod 600 "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/github_genagenta"
```

#### Test connessione (dopo configurazione)

```bash
# Test GitHub
ssh -T git@github.com

# Test Hetzner (dovrebbe connettersi senza password)
ssh hetzner-genagenta "echo 'Connessione OK!'"
```

---

## DEPLOY COMPLETO (2 step)

### STEP 1: Push su GitHub

```bash
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa"

# Verifica stato
git status

# Aggiungi modifiche
git add .

# Commit (cambia il messaggio)
git commit -m "Descrizione delle modifiche"

# Push
git push origin main
```

### STEP 2: Deploy su Server Hetzner

#### Opzione A: Deploy Frontend (solo file statici)

```bash
# Build frontend
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend"
npm run build

# Upload su server
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend/dist/"* root@46.224.202.91:/var/www/genagenta/frontend/dist/
```

#### Opzione B: Deploy Backend (file PHP)

```bash
# Upload singolo file
scp "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/backend/api/ai/copilot-runtime.php" root@46.224.202.91:/var/www/genagenta/backend/api/ai/

# Upload intera cartella backend
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/backend/"* root@46.224.202.91:/var/www/genagenta/backend/
```

#### Opzione C: Deploy Completo (Frontend + Backend)

```bash
# 1. Build frontend
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend"
npm run build

# 2. Upload frontend
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend/dist/"* root@46.224.202.91:/var/www/genagenta/frontend/dist/

# 3. Upload backend
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/backend/"* root@46.224.202.91:/var/www/genagenta/backend/
```

---

## SCRIPT RAPIDO (copia-incolla)

### Deploy completo in un comando

```bash
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa" && \
cd frontend && npm run build && cd .. && \
git add . && git commit -m "Deploy $(date +%Y-%m-%d_%H-%M)" && git push origin main && \
scp -r frontend/dist/* root@46.224.202.91:/var/www/genagenta/frontend/dist/ && \
scp -r backend/* root@46.224.202.91:/var/www/genagenta/backend/ && \
echo "Deploy completato!"
```

---

## Verifica Deploy

### Test API Backend

```bash
curl -s "https://genagenta.gruppogea.net/api/ai/copilot-runtime" -X POST \
  -H "Content-Type: application/json" \
  -d '{"method":"info"}'
```

### Test Sito

Apri nel browser: https://genagenta.gruppogea.net

---

## Connessione diretta al server

```bash
# Connetti al server
ssh hetzner-genagenta

# Una volta connesso, comandi utili:
cd /var/www/genagenta
ls -la
cat backend/api/ai/copilot-runtime.php
tail -f /var/log/apache2/error.log
```

---

## Troubleshooting

### "Permission denied (publickey,password)"

La chiave SSH non viene trovata. Verifica:
1. Il file `~/.ssh/config` esiste e ha la configurazione corretta
2. I permessi della chiave sono 600: `chmod 600 path/to/key`
3. La chiave pubblica e' sul server in `/root/.ssh/authorized_keys`

### "Host key verification failed"

```bash
ssh-keygen -R 46.224.202.91
```

### Chiede ancora la password

Forza l'uso della chiave specificandola esplicitamente:

```bash
ssh -i "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta" root@46.224.202.91
```

Se funziona con -i ma non senza, il problema e' nel file config.

---

## Info Server

- **IP:** 46.224.202.91
- **User:** root
- **Path sito:** /var/www/genagenta
- **URL:** https://genagenta.gruppogea.net
- **GitHub:** https://github.com/GenGeCo/GenAgenta_2AI

---

Creato: 10 Gennaio 2026
