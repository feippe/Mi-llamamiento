import { all, put, remove } from './db.js';

const API_BASE = 'api';

export async function api(path, options = {}) {
  const tokenRow = (await all('meta')).find(row => row.id === 'token');
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (tokenRow?.value) headers.Authorization = `Bearer ${tokenRow.value}`;
  const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
  if (!response.ok) throw new Error(`API ${response.status}`);
  if (response.status === 204) return null;
  const body = await response.json();
  return body.data;
}

export async function tryApi(path, options = {}) {
  if (!navigator.onLine) return null;
  try {
    return await api(path, options);
  } catch {
    return null;
  }
}

export async function syncQueue() {
  if (!navigator.onLine) return { status: 'offline' };
  const changes = (await all('sync_queue')).filter(change => change.estado === 'pendiente');
  if (!changes.length) return { status: 'clean' };
  const result = await tryApi('/sync/push', {
    method: 'POST',
    body: JSON.stringify({ changes })
  });
  if (!result) return { status: 'failed' };
  for (const id of result.aplicados || []) {
    const change = changes.find(item => item.id === id);
    if (change) await put('sync_queue', { ...change, estado: 'aplicado' });
  }
  return { status: 'synced', applied: result.aplicados?.length || 0 };
}

export async function pullRemote() {
  if (!navigator.onLine) return;
  const meta = await all('meta');
  const since = meta.find(row => row.id === 'last_pull')?.value || '';
  const result = await tryApi(`/sync/pull${since ? `?since=${encodeURIComponent(since)}` : ''}`);
  if (!result) return;
  for (const change of result.changes || []) {
    if (change.operacion === 'eliminar') {
      await remove(change.entidad, change.entidad_id);
    } else {
      await put(change.entidad, change.payload);
    }
  }
  await put('meta', { id: 'last_pull', value: result.server_time });
}
