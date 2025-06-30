# Bostarter
# Bostarter - Esportazione Log Eventi in JSON e MongoDB

## Istruzioni di Setup (Windows & Mac)

### 1. Navigazione alla cartella del progetto
Se hai già il progetto aperto (ad esempio con GitHub Desktop):
```bash
cd mongo
```

### 2. Installazione Node.js
- **Windows/Mac**: Scarica da: https://nodejs.org/
- **Mac con Homebrew**: `brew install node`

### 3. Installazione dipendenze Node.js
```bash
npm install
```

### 4. Esportazione log in formato JSON (opzionale)
```bash
node export_logs_to_json.js
```
Troverai il file `bostarter_logs.json` nella cartella mongo.

---

## 5. Sincronizzazione log su MongoDB (richiesto dalla consegna)

### Prerequisiti

#### Windows:
- MongoDB installato e in esecuzione
  - Scarica da: https://www.mongodb.com/try/download/community
- MongoDB Compass (opzionale, per visualizzazione dati)
  - Scarica da: https://www.mongodb.com/try/download/compass

#### Mac:
Se non hai Homebrew, installalo prima:
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
eval "$(/opt/homebrew/bin/brew shellenv)"
```

Poi installa MongoDB:
```bash
brew tap mongodb/brew
brew install mongodb-community@7.0
brew services start mongodb-community@7.0
```

### Istruzioni

1. **Avvio MongoDB**
   - Windows: Avviare il servizio "MongoDB" dal menu Start
   - Mac: `brew services start mongodb-community@7.0` (se non già avviato)

2. **Esecuzione sincronizzazione**
```bash
node events_sync.js
```

3. **Verifica con MongoDB Compass** (opzionale)
   - Connettiti a: `mongodb://localhost:27017`
   - Trova il database: `bostarter_logs`
   - Clicca sulla collezione: `eventi`
   - Visualizza tutti i log esportati dalla piattaforma

---

## Configurazione Database

### Windows (XAMPP):
Nel file `events_sync.js`, verifica che la configurazione sia:
```javascript
const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: '', // Solitamente vuota
  database: 'BOSTARTER',
  port: 3306
};
```

### Mac (MAMP):
Nel file `events_sync.js`, verifica che la configurazione sia:
```javascript
const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: '', // O 'root' a seconda della configurazione MAMP
  database: 'BOSTARTER',
  port: 8889 // Porta standard MAMP
};
```

---

## Note Tecniche
- Per esportazione solo JSON: eseguire unicamente `export_logs_to_json.js`
- Per aggiornamento dati: rieseguire lo script di sincronizzazione
- I comandi MongoDB per Mac richiedono Homebrew installato
