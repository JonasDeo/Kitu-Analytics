const DB_NAME = 'kitu-offline';
const STORE_NAME = 'queue';

function openDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = (e) => {
      (e.target as IDBOpenDBRequest).result
        .createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
    };
    request.onsuccess = (e) => resolve((e.target as IDBOpenDBRequest).result);
    request.onerror = reject;
  });
}

export async function queueTransaction(businessId: number, smsText: string, token: string) {
  const db = await openDB();
  return new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    tx.objectStore(STORE_NAME).add({
      url: `http://localhost:8000/api/v1/businesses/${businessId}/transactions/parse-sms`,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({ sms_text: smsText }),
      queued_at: new Date().toISOString(),
    });
    tx.oncomplete = () => resolve();
    tx.onerror = reject;
  });
}

export async function getQueueCount(): Promise<number> {
  const db = await openDB();
  return new Promise((resolve) => {
    const tx = db.transaction(STORE_NAME, 'readonly');
    const count = tx.objectStore(STORE_NAME).count();
    count.onsuccess = () => resolve(count.result);
    count.onerror = () => resolve(0);
  });
}

export function triggerBackgroundSync() {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    navigator.serviceWorker.ready.then((reg) => {
      (reg as any).sync.register('sync-transactions');
    });
  }
}