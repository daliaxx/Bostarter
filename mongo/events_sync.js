// Script per sincronizzare i log MySQL in una collezione MongoDB
const mysql = require('mysql2/promise');
const { MongoClient } = require('mongodb');

// Configurazione MySQL
const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: 'root',
  database: 'BOSTARTER',
  port: 3306
};

// Configurazione MongoDB
const mongoUrl = 'mongodb://localhost:27017';
const mongoDbName = 'bostarter_logs';
const mongoCollection = 'eventi';

async function syncLogs() {
  let mysqlConn, mongoClient;
  try {
    // Connessione MySQL
    mysqlConn = await mysql.createConnection(dbConfig);
    const [rows] = await mysqlConn.execute('SELECT * FROM LOG_EVENTI ORDER BY data DESC');

    // Connessione MongoDB
    mongoClient = await MongoClient.connect(mongoUrl, { useUnifiedTopology: true });
    const db = mongoClient.db(mongoDbName);
    const collection = db.collection(mongoCollection);

    // Pulisci la collezione
    await collection.deleteMany({});

    // Inserimento di tutti i log
    if (rows.length > 0) {
      await collection.insertMany(rows);
      console.log(`Sincronizzati ${rows.length} log in MongoDB (${mongoDbName}.${mongoCollection})`);
    } else {
      console.log('Nessun log da sincronizzare.');
    }
  } catch (err) {
    console.error('Errore durante la sincronizzazione:', err);
  } finally {
    if (mysqlConn) await mysqlConn.end();
    if (mongoClient) await mongoClient.close();
  }
}

syncLogs(); 