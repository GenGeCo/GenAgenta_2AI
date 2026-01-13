# Comandi Deploy per Claude

Questo file contiene i comandi esatti che Claude deve usare per fare il deploy.

## SSH Config (gia' configurato in ~/.ssh/config)

```
Host 46.224.202.91
  HostName 46.224.202.91
  User root
  IdentityFile I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta
  IdentitiesOnly yes
```

## Comandi Deploy

### 1. Build Frontend

```bash
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend" && npm run build
```

### 2. Push su GitHub

```bash
cd "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa" && git add . && git commit -m "MESSAGGIO" && git push origin main
```

### 3. Deploy Frontend su Server

```bash
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/frontend/dist/"* root@46.224.202.91:/var/www/genagenta/frontend/dist/
```

### 4. Deploy Backend su Server

```bash
scp -r "I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/backend/"* root@46.224.202.91:/var/www/genagenta/backend/
```

### 5. Verifica Deploy

```bash
ssh root@46.224.202.91 "ls -la /var/www/genagenta/frontend/dist/assets/ | head -5"
```

### 6. Test API

```bash
curl -s --max-time 30 -X POST "https://genagenta.gruppogea.net/api/ai/copilot-runtime" -H "Content-Type: application/json" -d '{"method":"info"}'
```

## Info Importanti

- **Server IP:** 46.224.202.91
- **User:** root
- **Path server:** /var/www/genagenta
- **Chiave SSH:** I:/Progetti_Portable/progetti_web_portable/GenAgenta_casa/.ssh/hetzner_genagenta
- **GitHub:** https://github.com/GenGeCo/GenAgenta_2AI
- **URL produzione:** https://genagenta.gruppogea.net

## Note

- NON serve specificare -i per la chiave se ~/.ssh/config e' configurato correttamente
- Il deploy frontend richiede prima npm run build
- Il backend non richiede build, solo upload dei file PHP
