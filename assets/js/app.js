import { all } from './db.js';
import { pullRemote, syncQueue } from './api.js';
import {
  alerts,
  areas,
  bootstrap,
  deletePair,
  deleteTask,
  leadership,
  loginDev,
  markPairDone,
  pairStatus,
  pairs,
  saveLeadership,
  savePair,
  saveSubtasks,
  saveTask,
  sessionUser,
  subtasks,
  tasks
} from './store.js';

const state = {
  user: null,
  areas: [],
  activeAreaId: null,
  view: 'areas',
  taskFilter: 'todas',
  selectedTaskId: null,
  selectedPairId: null
};

const $ = selector => document.querySelector(selector);
const $$ = selector => Array.from(document.querySelectorAll(selector));

document.addEventListener('DOMContentLoaded', init);

async function init() {
  await bootstrap();
  await registerServiceWorker();
  wireEvents();
  state.user = await sessionUser();
  if (!state.user) {
    showLogin();
    return;
  }
  await hydrate();
}

function wireEvents() {
  $('#loginForm').addEventListener('submit', async event => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget));
    state.user = await loginDev(data);
    await hydrate();
  });

  $$('.nav-button').forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
  $('#areaSelect').addEventListener('change', async event => {
    state.activeAreaId = event.target.value;
    state.selectedTaskId = null;
    state.selectedPairId = null;
    await renderAll();
  });
  $('#newTaskBtn').addEventListener('click', () => editTask(null));
  $('#taskForm').addEventListener('submit', onTaskSubmit);
  $('#deleteTaskBtn').addEventListener('click', onTaskDelete);
  $('#addSubtaskBtn').addEventListener('click', () => addSubtaskRow());
  $$('.filter').forEach(button => button.addEventListener('click', async () => {
    state.taskFilter = button.dataset.taskFilter;
    $$('.filter').forEach(item => item.classList.toggle('active', item === button));
    await renderTasks();
  }));

  $('#newPairBtn').addEventListener('click', () => editPair(null));
  $('#pairForm').addEventListener('submit', onPairSubmit);
  $('#deletePairBtn').addEventListener('click', onPairDelete);
  $('#markInterviewBtn').addEventListener('click', onPairDone);
  $('#leadershipForm').addEventListener('submit', onLeadershipSubmit);
  $('#syncBtn').addEventListener('click', syncNow);

  window.addEventListener('online', syncNow);
  navigator.serviceWorker?.addEventListener('message', event => {
    if (event.data?.type === 'SYNC_REQUESTED') syncNow();
  });
  setupInstallPrompt();
}

async function hydrate() {
  $('#loginView').classList.add('hidden');
  setView(state.view || 'areas');
  await pullRemote();
  state.areas = await areas();
  state.activeAreaId = state.activeAreaId || state.areas[0]?.id || null;
  await renderAll();
  await syncNow();
}

function showLogin() {
  $('#loginView').classList.remove('hidden');
  $('.workspace.active')?.classList.remove('active');
}

async function renderAll() {
  renderAreas();
  await renderTasks();
  await renderPairs();
  await renderLeadership();
  await renderAlerts();
  updateSyncLabel();
}

function renderAreas() {
  const select = $('#areaSelect');
  select.innerHTML = state.areas.map(area => `<option value="${area.id}" ${area.id === state.activeAreaId ? 'selected' : ''}>${escapeHtml(area.nombre)}${area.estado === 'pendiente' ? ' (pendiente)' : ''}</option>`).join('');
  $('#areaRail').innerHTML = state.areas.map(area => `
    <button class="area-chip ${area.id === state.activeAreaId ? 'active' : ''}" data-area-id="${area.id}">
      ${escapeHtml(area.nombre)}
      <small>${area.rol || 'miembro'} · ${area.estado || 'activo'}</small>
    </button>
  `).join('');
  $$('.area-chip').forEach(button => button.addEventListener('click', async () => {
    state.activeAreaId = button.dataset.areaId;
    await renderAll();
  }));
}

async function renderTasks() {
  if (!state.activeAreaId) return;
  const list = await tasks(state.activeAreaId);
  const filtered = state.taskFilter === 'todas' ? list : list.filter(task => task.estado === state.taskFilter);
  $('#taskCount').textContent = `${list.filter(task => task.estado !== 'completada').length} pendientes`;
  $('#taskList').innerHTML = filtered.map(task => `
    <button class="item-card ${task.id === state.selectedTaskId ? 'active' : ''}" data-task-id="${task.id}">
      <header><strong>${escapeHtml(task.titulo)}</strong><span class="badge">${labelState(task.estado)}</span></header>
      <p>${task.fecha_vencimiento ? `Vence ${task.fecha_vencimiento}` : 'Sin vencimiento'}</p>
    </button>
  `).join('') || emptyState('No hay tareas en esta área.');
  $$('#taskList .item-card').forEach(card => card.addEventListener('click', () => editTask(card.dataset.taskId)));
  if (!state.selectedTaskId && filtered[0]) await editTask(filtered[0].id, false);
}

async function editTask(id, focus = true) {
  state.selectedTaskId = id;
  const form = $('#taskForm');
  form.reset();
  $('#subtaskList').innerHTML = '';
  if (!id) {
    form.elements.id.value = '';
    $('#taskFormTitle').textContent = 'Nueva tarea';
    $('#taskMeta').textContent = 'Se guardará en el área activa';
    if (focus) form.titulo.focus();
    return;
  }
  const task = (await tasks(state.activeAreaId)).find(item => item.id === id);
  if (!task) return;
  form.elements.id.value = task.id;
  form.titulo.value = task.titulo;
  form.descripcion.value = task.descripcion || '';
  form.fecha_inicio.value = task.fecha_inicio || '';
  form.fecha_vencimiento.value = task.fecha_vencimiento || '';
  form.estado.value = task.estado || 'pendiente';
  $('#taskFormTitle').textContent = 'Detalle';
  $('#taskMeta').textContent = task.updated_at ? `Actualizada ${formatDateTime(task.updated_at)}` : '';
  for (const subtask of await subtasks(id)) addSubtaskRow(subtask);
  await renderTasks();
}

async function onTaskSubmit(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = Object.fromEntries(new FormData(form));
  if (!data.titulo.trim()) return;
  const saved = await saveTask(state.activeAreaId, data);
  const rows = $$('#subtaskList .subtask-row').map(row => ({
    id: row.dataset.id || '',
    titulo: row.querySelector('[name="titulo"]').value,
    estado: row.querySelector('[name="estado"]').value
  }));
  await saveSubtasks(saved.id, rows);
  state.selectedTaskId = saved.id;
  await renderTasks();
  await editTask(saved.id, false);
  updateSyncLabel();
}

async function onTaskDelete() {
  if (!state.selectedTaskId) return;
  await deleteTask(state.selectedTaskId);
  state.selectedTaskId = null;
  await renderTasks();
  editTask(null, false);
  updateSyncLabel();
}

function addSubtaskRow(subtask = {}) {
  const fragment = $('#subtaskTemplate').content.cloneNode(true);
  const row = fragment.querySelector('.subtask-row');
  row.dataset.id = subtask.id || '';
  row.querySelector('[name="titulo"]').value = subtask.titulo || '';
  row.querySelector('[name="estado"]').value = subtask.estado || 'pendiente';
  row.querySelector('button').addEventListener('click', () => row.remove());
  $('#subtaskList').appendChild(fragment);
}

async function renderPairs() {
  if (!state.activeAreaId) return;
  const list = await pairs(state.activeAreaId);
  const statuses = await Promise.all(list.map(async pair => ({ pair, status: await pairStatus(pair.id) })));
  const done = statuses.filter(item => item.status === 'verde').length;
  $('#complianceSummary').textContent = `${done}/${list.length} al día este trimestre`;
  $('#pairList').innerHTML = statuses.map(({ pair, status }) => `
    <button class="item-card ${pair.id === state.selectedPairId ? 'active' : ''}" data-pair-id="${pair.id}">
      <header><strong>${escapeHtml(pair.integrante_1)} / ${escapeHtml(pair.integrante_2)}</strong><span class="badge ${statusColor(status)}">${statusLabel(status)}</span></header>
      <p>${pair.responsable_nombre || 'Sin entrevistador asignado'}</p>
    </button>
  `).join('') || emptyState('No hay parejas registradas.');
  $$('#pairList .item-card').forEach(card => card.addEventListener('click', () => editPair(card.dataset.pairId)));
  if (!state.selectedPairId && list[0]) editPair(list[0].id, false);
}

async function editPair(id, focus = true) {
  state.selectedPairId = id;
  const form = $('#pairForm');
  form.reset();
  if (!id) {
    form.elements.id.value = '';
    if (focus) form.integrante_1.focus();
    return;
  }
  const pair = (await pairs(state.activeAreaId)).find(item => item.id === id);
  if (!pair) return;
  form.elements.id.value = pair.id;
  form.integrante_1.value = pair.integrante_1;
  form.integrante_2.value = pair.integrante_2;
  await renderPairs();
}

async function onPairSubmit(event) {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(event.currentTarget));
  if (!data.integrante_1.trim() || !data.integrante_2.trim()) return;
  const saved = await savePair(state.activeAreaId, data);
  state.selectedPairId = saved.id;
  await renderPairs();
  await editPair(saved.id, false);
  updateSyncLabel();
}

async function onPairDelete() {
  if (!state.selectedPairId) return;
  await deletePair(state.selectedPairId);
  state.selectedPairId = null;
  await renderPairs();
  editPair(null, false);
  updateSyncLabel();
}

async function onPairDone() {
  if (!state.selectedPairId) return;
  await markPairDone(state.selectedPairId);
  await renderPairs();
  updateSyncLabel();
}

async function renderLeadership() {
  if (!state.activeAreaId) return;
  const list = await leadership(state.activeAreaId);
  $('#leadershipList').innerHTML = list.map(item => `
    <div class="item-card">
      <header><strong>${escapeHtml(item.persona)}</strong><span class="badge">${item.estado === 'realizada' ? 'Realizada' : 'Pendiente'}</span></header>
      <p>${escapeHtml(item.motivo || 'Sin motivo')} ${item.fecha_objetivo ? `· ${formatDateTime(item.fecha_objetivo)}` : ''}</p>
    </div>
  `).join('') || emptyState('Sin entrevistas de liderazgo.');
}

async function onLeadershipSubmit(event) {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(event.currentTarget));
  if (!data.persona.trim()) return;
  await saveLeadership(state.activeAreaId, data);
  event.currentTarget.reset();
  await renderLeadership();
  updateSyncLabel();
}

async function renderAlerts() {
  const list = await alerts();
  const pending = (await all('sync_queue')).filter(change => change.estado === 'pendiente').length;
  const syncCard = pending ? [{ id: 'sync', titulo: 'Cambios pendientes', cuerpo: `${pending} cambios esperan sincronización.`, tipo: 'sync' }] : [];
  $('#alertList').innerHTML = [...syncCard, ...list].map(item => `
    <div class="item-card">
      <header><strong>${escapeHtml(item.titulo)}</strong><span class="badge">${escapeHtml(item.tipo)}</span></header>
      <p>${escapeHtml(item.cuerpo || '')}</p>
    </div>
  `).join('') || emptyState('No hay avisos.');
}

function setView(view) {
  state.view = view;
  $$('.view').forEach(panel => panel.classList.toggle('active', panel.id === `${view}View`));
  $$('.nav-button').forEach(button => button.classList.toggle('active', button.dataset.view === view));
}

async function syncNow() {
  $('#syncLabel').textContent = navigator.onLine ? 'Sincronizando' : 'Sin conexión';
  await syncQueue();
  await pullRemote();
  state.areas = await areas();
  updateSyncLabel();
  await renderAlerts();
}

async function updateSyncLabel() {
  const pending = (await all('sync_queue')).filter(change => change.estado === 'pendiente').length;
  $('#syncLabel').textContent = navigator.onLine ? (pending ? `${pending} pendientes` : 'Sincronizado') : `${pending} pendientes offline`;
}

async function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    await navigator.serviceWorker.register('./service-worker.js');
  }
}

function setupInstallPrompt() {
  let promptEvent = null;
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    promptEvent = event;
    $('#installBtn').classList.remove('hidden');
  });
  $('#installBtn').addEventListener('click', async () => {
    if (!promptEvent) return;
    await promptEvent.prompt();
    promptEvent = null;
    $('#installBtn').classList.add('hidden');
  });
}

function labelState(stateValue) {
  return ({ pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' })[stateValue] || stateValue;
}

function statusLabel(status) {
  return ({ verde: 'Al día', amarillo: 'Agendada', rojo: 'Pendiente' })[status] || status;
}

function statusColor(status) {
  return ({ verde: 'green', amarillo: 'yellow', rojo: 'red' })[status] || '';
}

function formatDateTime(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function emptyState(text) {
  return `<div class="item-card"><p>${escapeHtml(text)}</p></div>`;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
}
