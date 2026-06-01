const DB_NAME = 'mi_llamamiento';
const DB_VERSION = 1;

const STORES = [
  'meta',
  'users',
  'service_area_instances',
  'area_members',
  'tasks',
  'subtasks',
  'ministering_pairs',
  'ministering_interviews',
  'leadership_interviews',
  'notifications',
  'sync_queue'
];

let connection;

export function db() {
  if (connection) return connection;
  connection = new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      const database = request.result;
      for (const store of STORES) {
        if (!database.objectStoreNames.contains(store)) {
          database.createObjectStore(store, { keyPath: 'id' });
        }
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
  return connection;
}

export async function all(storeName) {
  const database = await db();
  return new Promise((resolve, reject) => {
    const request = database.transaction(storeName, 'readonly').objectStore(storeName).getAll();
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });
}

export async function get(storeName, id) {
  const database = await db();
  return new Promise((resolve, reject) => {
    const request = database.transaction(storeName, 'readonly').objectStore(storeName).get(id);
    request.onsuccess = () => resolve(request.result || null);
    request.onerror = () => reject(request.error);
  });
}

export async function put(storeName, value) {
  const database = await db();
  return new Promise((resolve, reject) => {
    const tx = database.transaction(storeName, 'readwrite');
    tx.objectStore(storeName).put(value);
    tx.oncomplete = () => resolve(value);
    tx.onerror = () => reject(tx.error);
  });
}

export async function remove(storeName, id) {
  const database = await db();
  return new Promise((resolve, reject) => {
    const tx = database.transaction(storeName, 'readwrite');
    tx.objectStore(storeName).delete(id);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

export async function bulkPut(storeName, values) {
  const database = await db();
  return new Promise((resolve, reject) => {
    const tx = database.transaction(storeName, 'readwrite');
    const store = tx.objectStore(storeName);
    values.forEach(value => store.put(value));
    tx.oncomplete = () => resolve(values);
    tx.onerror = () => reject(tx.error);
  });
}

export function uuid() {
  return crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

export function nowIso() {
  return new Date().toISOString();
}
