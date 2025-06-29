# 📦 Bostarter - Esportazione Log Eventi in JSON e MongoDB

## 🚀 Istruzioni rapide per i compagni (Windows & Mac)

### 1️⃣ Vai nella cartella mongo del progetto

Se hai già il progetto aperto (ad esempio con GitHub Desktop):
```bash
cd mongo
```

### 2️⃣ Installa Node.js (se non lo hai già)
- Scarica da: https://nodejs.org/

### 3️⃣ Installa le dipendenze Node.js
```bash
npm install
```

### 4️⃣ Esporta i log in JSON (opzionale per la consegna)
```bash
node export_logs_to_json.js
```
Troverai il file `bostarter_logs.json` nella cartella mongo.

---

## 5️⃣ Sincronizza i log su MongoDB (richiesto dalla consegna)

### **Prerequisiti**
- **MongoDB installato e in esecuzione**
  - Scarica da: https://www.mongodb.com/try/download/community
- **MongoDB Compass** (opzionale, per visualizzare i dati)
  - Scarica da: https://www.mongodb.com/try/download/compass

### **Istruzioni**

1. **Avvia MongoDB**
   - Windows: Avvia il servizio "MongoDB" dal menu Start
   - Mac: `brew services start mongodb-community` oppure `mongod`

2. **Esegui la sincronizzazione**
```bash
node events_sync.js
```

3. **Apri MongoDB Compass**
   - Connettiti a: `mongodb://localhost:27017`
   - Trova il database: `bostarter_logs`
   - Clicca sulla collezione: `eventi`
   - Visualizza tutti i log esportati dalla piattaforma

---

## ℹ️ Note
- Se vuoi solo il file JSON, esegui solo `export_logs_to_json.js`
- Se vuoi aggiornare i dati, rilancia lo script di sincronizzazione
---
