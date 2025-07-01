// Script per esportare i log MySQL in un file JSON
const fs = require('fs');
const mysql = require('mysql2/promise');

const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: 'root',
  database: 'BOSTARTER',
  port: 3306
};

async function exportLogs() {
  let connection;
  try {
    connection = await mysql.createConnection(dbConfig);
    const [rows] = await connection.execute('SELECT * FROM LOG_EVENTI ORDER BY data DESC');
    
    // Esporta in JSON
    const json = JSON.stringify(rows, null, 2);
    fs.writeFileSync('bostarter_logs.json', json, 'utf8');
    console.log(`Esportati ${rows.length} log in bostarter_logs.json`);
  } catch (err) {
    console.error('Errore durante l\'esportazione:', err);
  } finally {
    if (connection) await connection.end();
  }
}

exportLogs(); 