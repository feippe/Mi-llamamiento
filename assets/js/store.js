import { all, bulkPut, get, put, remove, uuid, nowIso } from './db.js';
import { tryApi } from './api.js';

const demoUser = {
  id: '00000000-0000-4000-8000-000000000001',
  nombre: 'Usuario Demo',
  email: 'demo@mi-llamamiento.local',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Buenos_Aires'
};

const demoArea = {
  id: '00000000-0000-4000-8000-000000000101',
  nombre: 'Personal',
  es_personal: 1,
  estado: 'activo',
  rol: 'propietario',
  origen: 'invitacion'
};

const quorumArea = {
  id: '00000000-0000-4000-8000-000000000102',
  nombre: 'Cuórum de Élderes',
  es_personal: 0,
  estado: 'activo',
  rol: 'propietario',
  origen: 'llamamiento'
};

export async function bootstrap() {
  const users = await all('users');
  if (!users.length) {
    await bulkPut('users', [demoUser]);
    await bulkPut('service_area_instances', [demoArea, quorumArea]);
    await bulkPut('area_members', [
      { id: uuid(), area_instance_id: demoArea.id, user_id: demoUser.id, rol: 'propietario', estado: 'activo', origen: 'invitacion' },
      { id: uuid(), area_instance_id: quorumArea.id, user_id: demoUser.id, rol: 'propietario', estado: 'activo', origen: 'llamamiento' }
    ]);
    await bulkPut('tasks', [
      taskFixture('Preparar agenda de consejo', quorumArea.id, 'en_progreso', 7),
      taskFixture('Revisar seguimiento de ministración', quorumArea.id, 'pendiente', 3),
      taskFixture('Lista personal de la semana', demoArea.id, 'pendiente', null)
    ]);
    await bulkPut('ministering_pairs', [
      pairFixture('Juan Pérez', 'Andrés Gómez', quorumArea.id),
      pairFixture('Miguel Silva', 'Carlos Rojas', quorumArea.id)
    ]);
    await put('notifications', { id: uuid(), tipo: 'sistema', titulo: 'Modo demo activo', cuerpo: 'Puedes trabajar offline. Cuando configures PHP y MySQL, la app usará la API.', leida: false, created_at: nowIso() });
  }
}

export async function loginDev({ nombre, email }) {
  const remote = await tryApi('/auth/google', {
    method: 'POST',
    body: JSON.stringify({ nombre, email, timezone: demoUser.timezone })
  });
  if (remote?.token) {
    await put('meta', { id: 'token', value: remote.token });
    await put('users', remote.user);
    return remote.user;
  }
  const user = { ...demoUser, nombre, email };
  await put('meta', { id: 'token', value: 'offline-demo-token' });
  await put('users', user);
  return user;
}

export async function sessionUser() {
  const token = (await all('meta')).find(row => row.id === 'token');
  if (!token) return null;
  return (await all('users'))[0] || null;
}

export async function areas() {
  const remote = await tryApi('/areas');
  if (remote?.length) await bulkPut('service_area_instances', remote);
  return (await all('service_area_instances')).filter(area => !area.deleted_at);
}

export async function tasks(areaId) {
  const remote = await tryApi(`/areas/${areaId}/tasks`);
  if (remote?.length) await bulkPut('tasks', remote);
  return (await all('tasks')).filter(task => task.area_instance_id === areaId && !task.deleted_at);
}

export async function saveTask(areaId, data) {
  const existing = data.id ? await get('tasks', data.id) : null;
  const record = {
    id: data.id || uuid(),
    area_instance_id: areaId,
    creador_id: demoUser.id,
    responsable_id: null,
    titulo: data.titulo.trim(),
    descripcion: data.descripcion?.trim() || '',
    fecha_inicio: data.fecha_inicio || null,
    fecha_vencimiento: data.fecha_vencimiento || null,
    estado: data.estado || 'pendiente',
    created_at: existing?.created_at || nowIso(),
    updated_at: nowIso(),
    deleted_at: null
  };
  await put('tasks', record);
  await enqueue(existing ? 'editar' : 'crear', 'tasks', record.id, record);
  const remote = await tryApi(existing ? `/tasks/${record.id}` : `/areas/${areaId}/tasks`, {
    method: existing ? 'PUT' : 'POST',
    body: JSON.stringify(record)
  });
  if (remote) await put('tasks', remote);
  return remote || record;
}

export async function deleteTask(id) {
  const task = await get('tasks', id);
  if (!task) return;
  const deleted = { ...task, deleted_at: nowIso(), updated_at: nowIso() };
  await put('tasks', deleted);
  await enqueue('eliminar', 'tasks', id, deleted);
  await tryApi(`/tasks/${id}`, { method: 'DELETE' });
}

export async function subtasks(taskId) {
  return (await all('subtasks')).filter(subtask => subtask.task_id === taskId && !subtask.deleted_at);
}

export async function saveSubtasks(taskId, rows) {
  const current = await subtasks(taskId);
  const nextIds = rows.filter(row => row.titulo.trim()).map(row => row.id || uuid());
  for (const row of rows) {
    if (!row.titulo.trim()) continue;
    const existing = row.id ? await get('subtasks', row.id) : null;
    const record = {
      id: row.id || nextIds.shift() || uuid(),
      task_id: taskId,
      titulo: row.titulo.trim(),
      descripcion: '',
      fecha_inicio: null,
      fecha_fin: null,
      estado: row.estado || 'pendiente',
      created_at: existing?.created_at || nowIso(),
      updated_at: nowIso(),
      deleted_at: null
    };
    await put('subtasks', record);
    await enqueue(existing ? 'editar' : 'crear', 'subtasks', record.id, record);
  }
  for (const subtask of current) {
    if (!rows.some(row => row.id === subtask.id)) {
      await put('subtasks', { ...subtask, deleted_at: nowIso(), updated_at: nowIso() });
      await enqueue('eliminar', 'subtasks', subtask.id, subtask);
    }
  }
}

export async function pairs(areaId) {
  const remote = await tryApi(`/areas/${areaId}/pairs`);
  if (remote?.length) await bulkPut('ministering_pairs', remote);
  return (await all('ministering_pairs')).filter(pair => pair.area_instance_id === areaId && !pair.deleted_at);
}

export async function savePair(areaId, data) {
  const existing = data.id ? await get('ministering_pairs', data.id) : null;
  const record = {
    id: data.id || uuid(),
    area_instance_id: areaId,
    integrante_1: data.integrante_1.trim(),
    integrante_2: data.integrante_2.trim(),
    responsable_id: null,
    created_at: existing?.created_at || nowIso(),
    updated_at: nowIso(),
    deleted_at: null
  };
  await put('ministering_pairs', record);
  await enqueue(existing ? 'editar' : 'crear', 'ministering_pairs', record.id, record);
  const remote = await tryApi(existing ? `/pairs/${record.id}` : `/areas/${areaId}/pairs`, {
    method: existing ? 'PUT' : 'POST',
    body: JSON.stringify(record)
  });
  if (data.agendada_para) await schedulePair(record.id, data.agendada_para);
  return remote || record;
}

export async function deletePair(id) {
  const pair = await get('ministering_pairs', id);
  if (!pair) return;
  await put('ministering_pairs', { ...pair, deleted_at: nowIso(), updated_at: nowIso() });
  await enqueue('eliminar', 'ministering_pairs', id, pair);
  await tryApi(`/pairs/${id}`, { method: 'DELETE' });
}

export async function schedulePair(pairId, value) {
  const record = { id: uuid(), pair_id: pairId, estado: 'agendada', agendada_para: new Date(value).toISOString(), created_at: nowIso(), updated_at: nowIso(), deleted_at: null };
  await put('ministering_interviews', record);
  await enqueue('crear', 'ministering_interviews', record.id, record);
  await tryApi(`/pairs/${pairId}/schedule`, { method: 'POST', body: JSON.stringify(record) });
}

export async function markPairDone(pairId) {
  const today = new Date().toISOString().slice(0, 10);
  const record = { id: uuid(), pair_id: pairId, estado: 'realizada', fecha_realizada: today, trimestre: quarterLabel(new Date()), created_at: nowIso(), updated_at: nowIso(), deleted_at: null };
  await put('ministering_interviews', record);
  await enqueue('crear', 'ministering_interviews', record.id, record);
  await tryApi(`/pairs/${pairId}/interview`, { method: 'POST', body: JSON.stringify(record) });
}

export async function pairStatus(pairId) {
  const currentQuarter = quarterLabel(new Date());
  const interviews = (await all('ministering_interviews')).filter(item => item.pair_id === pairId && !item.deleted_at);
  const done = interviews.find(item => item.estado === 'realizada' && item.trimestre === currentQuarter);
  if (done) return 'verde';
  if (interviews.some(item => item.estado === 'agendada')) return 'amarillo';
  return 'rojo';
}

export async function leadership(areaId) {
  const remote = await tryApi(`/areas/${areaId}/leadership-interviews`);
  if (remote?.length) await bulkPut('leadership_interviews', remote);
  return (await all('leadership_interviews')).filter(item => item.area_instance_id === areaId && !item.deleted_at);
}

export async function saveLeadership(areaId, data) {
  const record = { id: uuid(), area_instance_id: areaId, creador_id: demoUser.id, persona: data.persona.trim(), motivo: data.motivo?.trim() || '', fecha_objetivo: data.fecha_objetivo ? new Date(data.fecha_objetivo).toISOString() : null, estado: 'pendiente', created_at: nowIso(), updated_at: nowIso(), deleted_at: null };
  await put('leadership_interviews', record);
  await enqueue('crear', 'leadership_interviews', record.id, record);
  await tryApi(`/areas/${areaId}/leadership-interviews`, { method: 'POST', body: JSON.stringify(record) });
}

export async function alerts() {
  return (await all('notifications')).sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)));
}

async function enqueue(operacion, entidad, entidadId, payload) {
  const change = { id: uuid(), entidad, entidad_id: entidadId, operacion, payload, client_timestamp: nowIso(), estado: 'pendiente', intentos: 0 };
  await put('sync_queue', change);
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    const registration = await navigator.serviceWorker.ready;
    await registration.sync.register('sync-queue');
  }
}

function taskFixture(titulo, areaId, estado, dueInDays) {
  const due = dueInDays === null ? null : new Date(Date.now() + dueInDays * 86400000).toISOString().slice(0, 10);
  return { id: uuid(), area_instance_id: areaId, creador_id: demoUser.id, responsable_id: null, titulo, descripcion: '', fecha_inicio: null, fecha_vencimiento: due, estado, created_at: nowIso(), updated_at: nowIso(), deleted_at: null };
}

function pairFixture(integrante1, integrante2, areaId) {
  return { id: uuid(), area_instance_id: areaId, integrante_1: integrante1, integrante_2: integrante2, responsable_id: null, created_at: nowIso(), updated_at: nowIso(), deleted_at: null };
}

function quarterLabel(date) {
  return `${date.getUTCFullYear()}-Q${Math.ceil((date.getUTCMonth() + 1) / 3)}`;
}
