let csrf = '';
let currentUser = null;
let canManageSettings = false;
let adminCatalog = null;

let state = {
  units: [],
  organizations: [],
  callings: [],
  userCallings: [],
  areas: [],
  activeArea: null,
  members: [],
  tasks: [],
  interviews: [],
  pairs: [],
  pairQuarter: (() => { const d = new Date(); return `${d.getFullYear()}-Q${Math.ceil((d.getMonth()+1)/3)}`; })(),
  minFilter: 'upcoming',
  myMinisteringOnly: false,
  activeAreaId: null,
  activeView: 'dashboard',
  dashTab: 'tasks',
  dashboard: { tasks: [], interviews: [] },
  taskFilter: 'open',
  myTasksOnly: true,
  interviewFilter: 'upcoming',
  myInterviewsOnly: false,
};

const $ = selector => document.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

function haptic() { navigator.vibrate?.(10); }

// ── Web Audio micro-sounds ────────────────────────────────────────────────────
let _ac = null;
function _audioCtx() {
  if (!_ac || _ac.state === 'closed') _ac = new (window.AudioContext || window.webkitAudioContext)();
  if (_ac.state === 'suspended') _ac.resume();
  return _ac;
}

function _tone(freq, duration, { type = 'sine', gainStart = 0.18, gainEnd = 0, freqEnd = null, delay = 0 } = {}) {
  try {
    const ac = _audioCtx();
    const t0 = ac.currentTime + delay;
    const t1 = t0 + duration;
    const osc = ac.createOscillator();
    const gain = ac.createGain();
    osc.connect(gain);
    gain.connect(ac.destination);
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    if (freqEnd !== null) osc.frequency.linearRampToValueAtTime(freqEnd, t1);
    gain.gain.setValueAtTime(gainStart, t0);
    gain.gain.linearRampToValueAtTime(gainEnd, t1);
    osc.start(t0);
    osc.stop(t1);
  } catch (_) {}
}

function soundTap()    { _tone(880, 0.05, { gainStart: 0.10, freqEnd: 700 }); }
function soundNav()    { _tone(660, 0.07, { gainStart: 0.12, freqEnd: 520 }); }
function soundToggle() { _tone(580, 0.09, { type: 'triangle', gainStart: 0.14, freqEnd: 440 }); }
function soundOpen()   { _tone(320, 0.12, { gainStart: 0.13, freqEnd: 680 }); }
function soundClose()  { _tone(560, 0.10, { gainStart: 0.12, freqEnd: 300 }); }
function soundSave() {
  _tone(523, 0.10, { gainStart: 0.15, gainEnd: 0.02 });
  _tone(784, 0.14, { gainStart: 0.15, gainEnd: 0, delay: 0.09 });
}
function soundDelete() {
  _tone(440, 0.07, { type: 'triangle', gainStart: 0.14 });
  _tone(330, 0.10, { type: 'triangle', gainStart: 0.12, gainEnd: 0, delay: 0.07 });
}
// ─────────────────────────────────────────────────────────────────────────────

function hideSplash() {
  const s = $('#splashScreen');
  if (!s) return;
  s.classList.add('splash-hiding');
  setTimeout(() => s.remove(), 420);
}

function showLoader() { $('#contentLoader')?.classList.remove('hidden'); }
function hideLoader() { $('#contentLoader')?.classList.add('hidden'); }

let _modalCount = 0;
function openModal(el) {
  if (++_modalCount === 1) document.documentElement.classList.add('modal-open');
  soundOpen();
  el.showModal();
}
function closeModal(el) {
  el.close();
  soundClose();
  if (--_modalCount <= 0) { _modalCount = 0; document.documentElement.classList.remove('modal-open'); }
}

document.addEventListener('DOMContentLoaded', init);

async function init() {
  wireEvents();
  loadAppVersion();
  if ('serviceWorker' in navigator) {
    const hadController = !!navigator.serviceWorker.controller;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (hadController) window.location.reload();
    });
    navigator.serviceWorker.register('/sw-gen.php').then(reg => {
      setInterval(() => reg.update(), 12 * 60 * 60 * 1000);
    }).catch(e => console.warn('SW:', e));
  }
  await initSession();
}

function wireEvents() {
  $('#forceUpdateBtn').addEventListener('click', forceUpdate);
  $('#showRegister').addEventListener('click', () => toggleAuth('register'));
  $('#showLogin').addEventListener('click', () => toggleAuth('login'));
  $('#loginForm').addEventListener('submit', login);
  $('#registerForm').addEventListener('submit', register);
  $('#logoutBtn').addEventListener('click', logout);
  $('#iosInstallBannerClose').addEventListener('click', () => {
    localStorage.setItem('iosInstallDismissed', '1');
    $('#iosInstallBanner').classList.add('hidden');
  });
  $$('[data-nav]').forEach(button => button.addEventListener('click', () => showView(button.dataset.nav)));

  $('#profileForm').addEventListener('submit', saveProfile);
  $('#showCallingFormBtn').addEventListener('click', showCallingForm);
  $('#cancelCallingFormBtn').addEventListener('click', hideCallingForm);
  $('#scopeSelect').addEventListener('change', reloadCallingSelectors);
  $('#unitSelect').addEventListener('change', reloadCallingSelectors);
  $('#organizationSelect').addEventListener('change', loadCallings);
  $('#callingForm').addEventListener('submit', requestCalling);

  $('#areaTag').addEventListener('click', e => { e.stopPropagation(); toggleAreaOptions(); });
  $('#goToProfileBtn').addEventListener('click', () => showView('profile'));
  $('#saveNewTaskBtn').addEventListener('click', closeTaskDialog);
  $('#newTaskBtn').addEventListener('click', () => openTaskDialog());
  $$('[data-dash-tab]').forEach(button => button.addEventListener('click', () => setDashTab(button.dataset.dashTab)));
  $$('[data-task-filter]').forEach(button => button.addEventListener('click', () => setTaskFilter(button.dataset.taskFilter)));
  $('#myTasksOnly').addEventListener('change', () => { state.myTasksOnly = $('#myTasksOnly').checked; renderTasks(); renderTaskStats(); });
  $('#taskForm').addEventListener('submit', e => e.preventDefault());
  $('#closeTaskBtn').addEventListener('click', closeTaskDialog);
  $('#taskDialog').addEventListener('cancel', e => { e.preventDefault(); closeTaskDialog(); });
  $('#taskDialog').addEventListener('click', e => { if (e.target === e.currentTarget) closeTaskDialog(); });
  $('#deleteTaskBtn').addEventListener('click', deleteTask);
  $('#taskDescription').addEventListener('input', () => {
    autoResizeTextarea($('#taskDescription'));
    scheduleAutosave();
  });

  $('#taskTitleDisplay').addEventListener('click', startTitleEdit);
  $('#taskTitleInput').addEventListener('blur', commitTitleEdit);
  $('#taskTitleInput').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); commitTitleEdit(); } });

  $('#statusTag').addEventListener('click', e => { e.stopPropagation(); toggleStatusOptions(); });
  $$('#statusOptions [data-status]').forEach(btn => btn.addEventListener('click', e => { e.stopPropagation(); pickStatus(btn.dataset.status, true); }));
  $('#responsibleTag').addEventListener('click', e => { e.stopPropagation(); toggleResponsibleOptions(); });
  document.addEventListener('click', e => {
    if (!$('#statusPicker').contains(e.target)) closeStatusOptions();
    if (!$('#responsiblePicker').contains(e.target)) closeResponsibleOptions();
    if (!$('#areaPicker').contains(e.target)) closeAreaOptions();
  });

  $('#startDateDisplay').addEventListener('click', () => startDateEdit('start'));
  $('#dueDateDisplay').addEventListener('click', () => startDateEdit('due'));
  $('#startDateInput').addEventListener('change', () => commitDateEdit('start'));
  $('#startDateInput').addEventListener('blur', () => commitDateEdit('start'));
  $('#dueDateInput').addEventListener('change', () => commitDateEdit('due'));
  $('#dueDateInput').addEventListener('blur', () => commitDateEdit('due'));

  $$('[data-settings-screen]').forEach(button => button.addEventListener('click', () => showSettingsScreen(button.dataset.settingsScreen)));
  $$('[data-show-admin-form]').forEach(button => button.addEventListener('click', () => showAdminForm(button.dataset.showAdminForm)));
  $$('[data-hide-admin-form]').forEach(button => button.addEventListener('click', () => hideAdminForm(button.dataset.hideAdminForm)));
  let resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(syncIndicators, 120);
  });

  $('#unitForm').addEventListener('submit', saveUnit);
  $('#unitForm').elements.type.addEventListener('change', updateUnitParentState);
  $('#organizationForm').addEventListener('submit', saveOrganization);
  $('#adminCallingForm').addEventListener('submit', saveAdminCalling);
  $('#areaTemplateForm').addEventListener('submit', saveAreaTemplate);
  $('#ruleForm').addEventListener('submit', saveRule);

  $$('[data-interview-tab]').forEach(btn => btn.addEventListener('click', () => setInterviewTab(btn.dataset.interviewTab)));
  $$('[data-interview-filter]').forEach(btn => btn.addEventListener('click', () => setInterviewFilter(btn.dataset.interviewFilter)));
  $('#myInterviewsOnly').addEventListener('change', () => { state.myInterviewsOnly = $('#myInterviewsOnly').checked; renderInterviews(); });
  $('#addInterviewBtn').addEventListener('click', () => openInterviewDialog());
  $('#closeInterviewBtn').addEventListener('click', closeInterviewDialog);
  $('#interviewDialog').addEventListener('cancel', e => { e.preventDefault(); closeInterviewDialog(); });
  $('#interviewDialog').addEventListener('click', e => { if (e.target === e.currentTarget) closeInterviewDialog(); });
  $('#deleteInterviewBtn').addEventListener('click', deleteInterview);
  $('#saveNewInterviewBtn').addEventListener('click', closeInterviewDialog);
  $('#interviewForm').addEventListener('submit', e => e.preventDefault());
  $('#interviewerTag').addEventListener('click', e => { e.stopPropagation(); toggleInterviewerOptions(); });
  $('#interviewNotes').addEventListener('input', () => {
    autoResizeTextarea($('#interviewNotes'));
    scheduleInterviewAutosave();
  });
  $('#intervieweeName').addEventListener('input', () => scheduleInterviewAutosave());
  $('#interviewDateDisplay').addEventListener('click', () => startInterviewDateEdit('date'));
  $('#interviewTimeDisplay').addEventListener('click', () => startInterviewDateEdit('time'));
  $('#interviewDateClear').addEventListener('click', () => { setInterviewDateDisplay('date', ''); scheduleInterviewAutosave(); });
  $('#interviewTimeClear').addEventListener('click', () => { setInterviewDateDisplay('time', ''); scheduleInterviewAutosave(); });
  $('#interviewDate').addEventListener('change', () => commitInterviewDateEdit('date'));
  $('#interviewDate').addEventListener('blur', () => commitInterviewDateEdit('date'));
  $('#interviewTime').addEventListener('change', () => commitInterviewDateEdit('time'));
  $('#interviewTime').addEventListener('blur', () => commitInterviewDateEdit('time'));
  $('#interviewCompletedCheck').addEventListener('change', () => scheduleInterviewAutosave(true));
  document.addEventListener('click', e => {
    if (!$('#interviewerPicker').contains(e.target)) closeInterviewerOptions();
    if (!$('#minInterviewerPicker').contains(e.target)) closeMinInterviewerOptions();
  });

  // Ministering
  $$('[data-min-filter]').forEach(btn => btn.addEventListener('click', () => setMinFilter(btn.dataset.minFilter)));
  $('#myMinisteringOnly').addEventListener('change', () => { state.myMinisteringOnly = $('#myMinisteringOnly').checked; renderPairs(); });
  $('#addPairBtn').addEventListener('click', () => openPairDialog());
  $('#closePairDialogBtn').addEventListener('click', closePairDialog);
  $('#pairDialog').addEventListener('cancel', e => { e.preventDefault(); closePairDialog(); });
  $('#pairDialog').addEventListener('click', e => { if (e.target === e.currentTarget) closePairDialog(); });
  $('#deletePairBtn').addEventListener('click', deletePair);
  $('#pairForm').addEventListener('submit', e => { e.preventDefault(); savePair(); });
  $('#prevQuarterBtn').addEventListener('click', () => navigateQuarter(-1));
  $('#nextQuarterBtn').addEventListener('click', () => navigateQuarter(1));
  $('#closeMinisteringInterviewBtn').addEventListener('click', closeMinisteringInterview);
  $('#ministeringInterviewDialog').addEventListener('cancel', e => { e.preventDefault(); closeMinisteringInterview(); });
  $('#ministeringInterviewDialog').addEventListener('click', e => { if (e.target === e.currentTarget) closeMinisteringInterview(); });
  $('#minInterviewerTag').addEventListener('click', e => { e.stopPropagation(); toggleMinInterviewerOptions(); });
  $('#minInterviewDateDisplay').addEventListener('click', () => startMinInterviewDateEdit('date'));
  $('#minInterviewTimeDisplay').addEventListener('click', () => startMinInterviewDateEdit('time'));
  $('#minInterviewDateClear').addEventListener('click', () => { setMinInterviewDateDisplay('date', ''); scheduleMinisteringAutosave(); });
  $('#minInterviewTimeClear').addEventListener('click', () => { setMinInterviewDateDisplay('time', ''); scheduleMinisteringAutosave(); });
  $('#minInterviewDate').addEventListener('change', () => commitMinInterviewDateEdit('date'));
  $('#minInterviewDate').addEventListener('blur', () => commitMinInterviewDateEdit('date'));
  $('#minInterviewTime').addEventListener('change', () => commitMinInterviewDateEdit('time'));
  $('#minInterviewTime').addEventListener('blur', () => commitMinInterviewDateEdit('time'));
  $('#minInterviewNotes').addEventListener('input', () => { autoResizeTextarea($('#minInterviewNotes')); scheduleMinisteringAutosave(); });
  $('#minInterviewCompletedCheck').addEventListener('change', () => scheduleMinisteringAutosave(true));

  const NAV_SELECTOR = '[data-nav]';
  const TAP_SELECTORS = '[data-dash-tab], [data-task-filter], [data-interview-tab], [data-interview-filter], [data-min-filter]';
  document.addEventListener('touchstart', e => {
    if (e.target.closest(NAV_SELECTOR)) { haptic(); soundNav(); }
    else if (e.target.closest(TAP_SELECTORS)) { haptic(); soundTap(); }
  }, { passive: true });
  document.addEventListener('change', e => {
    if (e.target.matches('input[type="checkbox"]')) { haptic(); soundToggle(); }
  });
}

async function initSession() {
  const data = await api('/api/csrf');
  csrf = data.csrf;
  currentUser = data.user;
  if (currentUser) {
    await enterApp();
  } else {
    showAuth();
  }
}

async function login(event) {
  event.preventDefault();
  $('#loginStatus').textContent = 'Entrando...';
  try {
    const result = await api('/api/login', { method: 'POST', body: formData(event.currentTarget) });
    csrf = result.csrf;
    currentUser = result.user;
    await enterApp();
  } catch (error) {
    $('#loginStatus').textContent = error.message;
  }
}

async function register(event) {
  event.preventDefault();
  $('#registerStatus').textContent = 'Creando usuario...';
  try {
    const result = await api('/api/register', { method: 'POST', body: formData(event.currentTarget) });
    csrf = result.csrf;
    currentUser = result.user;
    await enterApp();
  } catch (error) {
    $('#registerStatus').textContent = error.message;
  }
}

async function logout() {
  const confirmed = await confirmDialog({
    title: 'Cerrar sesión',
    message: '¿Quieres cerrar tu sesión en este dispositivo?',
    confirmLabel: 'Cerrar sesión',
  });
  if (!confirmed) return;
  await api('/api/logout', { method: 'POST' });
  localStorage.removeItem('activeAreaId');
  currentUser = null;
  canManageSettings = false;
  adminCatalog = null;
  showAuth();
}

async function enterApp() {
  $('#authView').classList.add('hidden');
  $('#appShell').classList.remove('hidden');
  $('#userLabel').textContent = currentUser.name || currentUser.email;
  initIosInstallBanner();
  const minWait = new Promise(r => setTimeout(r, 700));
  await loadProfile();
  await loadCatalogs();
  await loadAreas();
  await loadUserCallings();
  renderAdminVisibility();
  await showView('dashboard');
  await minWait;
  hideSplash();
}

function showAuth() {
  $('#appShell').classList.add('hidden');
  $('#authView').classList.remove('hidden');
  toggleAuth('login');
  hideSplash();
}

function toggleAuth(mode) {
  $('#loginForm').classList.toggle('hidden', mode !== 'login');
  $('#registerForm').classList.toggle('hidden', mode !== 'register');
}

async function showView(view) {
  state.activeView = view;
  $$('.view').forEach(section => section.classList.remove('active-view'));
  $(`#${view}View`).classList.add('active-view');
  $$('[data-nav]').forEach(button => button.classList.toggle('active', button.dataset.nav === view));
  moveIndicator($('.bottom-nav'), $('.nav-indicator'), $('.bottom-nav button.active'));
  const showAreaCard = view === 'tasks' || view === 'interviews';
  $('#sharedAreaCard').classList.toggle('hidden', !showAreaCard);
  if (view === 'dashboard') {
    showLoader();
    await loadDashboard();
    hideLoader();
    moveIndicator($('#dashboardView .task-tabs'), $('#dashboardView .tabs-indicator'), $('#dashboardView .task-tabs button.active'));
  }
  if (view === 'profile') {
    await loadProfile();
    await loadUserCallings();
  }
  if (view === 'settings') {
    showSettingsScreen('settingsHome');
  }
  if (view === 'tasks') {
    moveIndicator($('#tasksView .task-tabs'), $('#tasksView .tabs-indicator'), $('#tasksView .task-tabs button.active'));
  }
  if (view === 'interviews') {
    showLoader();
    await loadInterviews();
    hideLoader();
    setInterviewTab('calling');
    moveIndicator($('#interviewFilterSegs'), $('#interviewFilterSegs .tabs-indicator'), $('[data-interview-filter].active'));
  }
}

function moveIndicator(container, indicator, active) {
  if (!container || !indicator || !active) return;
  const containerRect = container.getBoundingClientRect();
  const activeRect = active.getBoundingClientRect();
  indicator.style.width = `${activeRect.width}px`;
  indicator.style.transform = `translateX(${activeRect.left - containerRect.left}px)`;
}

function syncIndicators() {
  moveIndicator($('.bottom-nav'), $('.nav-indicator'), $('.bottom-nav button.active'));
  if (state.activeView === 'tasks') {
    moveIndicator($('#tasksView .task-tabs'), $('#tasksView .tabs-indicator'), $('#tasksView .task-tabs button.active'));
  }
  if (state.activeView === 'interviews') {
    moveIndicator($('#interviewSubTabs'), $('#interviewSubTabs .tabs-indicator'), $('[data-interview-tab].active'));
    moveIndicator($('#interviewFilterSegs'), $('#interviewFilterSegs .tabs-indicator'), $('[data-interview-filter].active'));
    moveIndicator($('#minFilterSegs'), $('#minFilterSegs .tabs-indicator'), $('[data-min-filter].active'));
  }
}

async function loadProfile() {
  const data = await api('/api/profile');
  currentUser = data.user;
  canManageSettings = data.can_manage_settings;
  $('#userLabel').textContent = currentUser.name || currentUser.email;
  $('#profileForm').elements.name.value = currentUser.name;
  $('#profileForm').elements.email.value = currentUser.email;
  renderAdminVisibility();
}

async function saveProfile(event) {
  event.preventDefault();
  $('#profileStatus').textContent = 'Guardando...';
  try {
    const result = await api('/api/profile', { method: 'PUT', body: formData(event.currentTarget) });
    currentUser = result.user;
    $('#profileForm').elements.current_password.value = '';
    $('#profileForm').elements.new_password.value = '';
    $('#profileStatus').textContent = 'Perfil actualizado.';
    $('#userLabel').textContent = currentUser.name || currentUser.email;
  } catch (error) {
    $('#profileStatus').textContent = error.message;
  }
}

async function loadCatalogs() {
  state.units = await api('/api/units');
  await reloadCallingSelectors();
}

async function reloadCallingSelectors() {
  const scope = $('#scopeSelect').value;
  const units = state.units.filter(unit => unit.type === scope);
  $('#unitLabel').classList.toggle('hidden', scope === 'stake');
  $('#unitSelect').innerHTML = units.map(unit => `<option value="${unit.id}">${escapeHtml(unit.name)}</option>`).join('');
  if (scope === 'stake') {
    const stake = state.units.find(unit => unit.type === 'stake');
    $('#unitSelect').innerHTML = stake ? `<option value="${stake.id}">${escapeHtml(stake.name)}</option>` : '';
  }
  state.organizations = await api(`/api/organizations?scope_type=${encodeURIComponent(scope)}`);
  $('#organizationSelect').innerHTML = state.organizations.map(org => `<option value="${org.id}">${escapeHtml(org.name)}</option>`).join('');
  await loadCallings();
}

async function loadCallings() {
  const organizationId = $('#organizationSelect').value;
  state.callings = organizationId ? await api(`/api/callings?organization_id=${organizationId}`) : [];
  $('#callingSelect').innerHTML = state.callings.map(calling => `<option value="${calling.id}">${escapeHtml(calling.name)}</option>`).join('');
}

async function loadUserCallings() {
  state.userCallings = await api('/api/user-callings');
  $('#userCallingList').innerHTML = state.userCallings.map(calling => `
    <div class="item">
      <header>
        <strong>${escapeHtml(calling.organization_name)} · ${escapeHtml(calling.calling_name)}</strong>
        <span class="badge ${calling.status === 'active' ? 'green' : calling.status === 'pending' ? 'yellow' : 'red'}">${statusLabel(calling.status)}</span>
      </header>
      <p>${escapeHtml(calling.unit_name)}</p>
      ${calling.status !== 'removed' ? `<button class="ghost" type="button" data-remove-calling="${calling.id}">Quitar</button>` : ''}
    </div>
  `).join('') || '<div class="item"><p>No tienes llamamientos registrados.</p></div>';
  $$('[data-remove-calling]').forEach(button => button.addEventListener('click', () => removeCalling(button.dataset.removeCalling)));
}

async function requestCalling(event) {
  event.preventDefault();
  $('#callingStatus').textContent = 'Enviando solicitud...';
  try {
    const result = await api('/api/user-callings', { method: 'POST', body: formData(event.currentTarget) });
    $('#callingStatus').textContent = result.auto_approved
      ? 'Solicitud aprobada automáticamente. Ya tienes acceso activo.'
      : 'Solicitud creada. Queda pendiente de aprobación.';
    await loadAreas();
    await loadUserCallings();
    hideCallingForm(false);
    if (state.activeView === 'settings') await loadRequests();
  } catch (error) {
    $('#callingStatus').textContent = error.message;
  }
}

function showCallingForm() {
  $('#callingForm').classList.remove('hidden');
  $('#showCallingFormBtn').classList.add('hidden');
  $('#callingStatus').textContent = '';
  $('#scopeSelect').focus();
}

function hideCallingForm(clearStatus = true) {
  $('#callingForm').classList.add('hidden');
  $('#showCallingFormBtn').classList.remove('hidden');
  $('#callingForm').reset();
  if (clearStatus) $('#callingStatus').textContent = '';
  reloadCallingSelectors();
}

async function removeCalling(id) {
  await api(`/api/user-callings/${id}`, { method: 'DELETE' });
  await loadAreas();
  await loadUserCallings();
  await loadProfile();
}

async function loadAreas() {
  state.areas = await api('/api/work-areas');
  const activeAreas = state.areas.filter(area => area.status === 'active');
  const container = $('#areaOptions');
  if (activeAreas.length) {
    container.innerHTML = activeAreas.map(area => `
      <button type="button" data-area-id="${area.id}" data-area-name="${escapeHtml(area.name)}" data-area-unit="${escapeHtml(area.unit_name || '')}">
        <span>${escapeHtml(area.name)}</span>
        ${area.unit_name ? `<span class="area-unit">${escapeHtml(area.unit_name)}</span>` : ''}
      </button>`).join('');
    $$('#areaOptions [data-area-id]').forEach(btn => {
      btn.addEventListener('click', async e => {
        e.stopPropagation();
        pickArea(btn.dataset.areaId, btn.dataset.areaName, btn.dataset.areaUnit);
        showLoader();
        await loadMembers();
        await loadTasks();
        if (state.activeView === 'interviews') await loadInterviews();
        if (state.activeView === 'dashboard') await loadDashboard();
        hideLoader();
      });
    });
    const savedId = localStorage.getItem('activeAreaId');
    const saved = savedId && activeAreas.find(a => a.id === savedId);
    const initial = saved || activeAreas[0];
    pickArea(initial.id, initial.name, initial.unit_name || '');
  } else {
    container.innerHTML = '<div style="padding:12px 14px;color:var(--muted);font-size:13px;">No tienes áreas activas.</div>';
    pickArea('', '', '');
  }
  await loadMembers();
  await loadTasks();
}

function pickArea(id, name, unit) {
  state.activeAreaId = id || null;
  state.activeArea = id ? (state.areas.find(a => a.id === id) || null) : null;
  if (id) localStorage.setItem('activeAreaId', id);
  else localStorage.removeItem('activeAreaId');

  $('#taskMineRow').classList.toggle('hidden', isPersonalArea());

  const hasInterviews = state.activeArea && Number(state.activeArea.has_interviews) === 1;
  const interviewsBtn = $('[data-nav="interviews"]');
  interviewsBtn.classList.toggle('hidden', !hasInterviews);
  $('.bottom-nav').classList.toggle('nav-4col', !hasInterviews);
  if (!hasInterviews && state.activeView === 'interviews') {
    showView('dashboard');
  } else {
    requestAnimationFrame(() => moveIndicator($('.bottom-nav'), $('.nav-indicator'), $('.bottom-nav button.active')));
  }

  const nameEl = $('#areaTagName');
  const unitEl = $('#areaTagUnit');
  if (name) {
    nameEl.textContent = name;
    unitEl.textContent = unit || '';
    unitEl.style.display = unit ? '' : 'none';
  } else {
    nameEl.textContent = 'Sin área seleccionada';
    unitEl.style.display = 'none';
  }
  $$('#areaOptions [data-area-id]').forEach(btn =>
    btn.classList.toggle('active-option', btn.dataset.areaId === (id || ''))
  );
  closeAreaOptions();
}

function toggleAreaOptions() {
  $('#areaOptions').classList.toggle('hidden');
}

function closeAreaOptions() {
  $('#areaOptions').classList.add('hidden');
}

async function loadMembers() {
  state.members = state.activeAreaId ? await api(`/api/work-areas/${state.activeAreaId}/members`) : [];
}

async function loadTasks() {
  state.tasks = state.activeAreaId ? await api(`/api/tasks?work_area_id=${encodeURIComponent(state.activeAreaId)}`) : [];
  state.tasks.sort(compareTasks);
  renderTasks();
  renderTaskStats();
}

// ── Dashboard ────────────────────────────────────────────────────────────────

async function loadDashboard() {
  const data = await api('/api/dashboard');
  state.dashboard.tasks = data.tasks;
  state.dashboard.interviews = data.interviews;
  renderDashboard();
}

function todayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function daysFromToday(dateStr) {
  if (!dateStr) return null;
  const [y, m, d] = dateStr.split('-').map(Number);
  const [ty, tm, td] = todayStr().split('-').map(Number);
  const ms = Date.UTC(y, m - 1, d) - Date.UTC(ty, tm - 1, td);
  return Math.round(ms / 86400000);
}

function urgencyBadge(dateStr, status) {
  if (!dateStr || status === 'done') return '';
  const days = daysFromToday(dateStr);
  if (days < 0) return `<span class="badge red">Vencida</span>`;
  if (days === 0) return `<span class="badge yellow">Hoy</span>`;
  if (days <= 7) return `<span class="badge yellow">En ${days}d</span>`;
  return '';
}

function interviewUrgencyBadge(dateStr) {
  if (!dateStr) return '';
  const days = daysFromToday(dateStr);
  if (days < 0) return `<span class="badge red">Vencida</span>`;
  if (days === 0) return `<span class="badge yellow">Hoy</span>`;
  if (days <= 7) return `<span class="badge yellow">En ${days}d</span>`;
  return `<span class="badge">${formatDate(dateStr)}</span>`;
}

function renderDashboard() {
  const today = todayStr();
  const tasks = state.dashboard.tasks;
  const interviews = state.dashboard.interviews;

  const pending  = tasks.filter(t => t.status === 'pending').length;
  const progress = tasks.filter(t => t.status === 'in_progress').length;
  const todayCount = interviews.filter(i => {
    const d = i.scheduled_date ? String(i.scheduled_date).split(' ')[0] : '';
    return d && daysFromToday(d) === 0;
  }).length;
  const weekCount = interviews.filter(i => {
    const d = i.scheduled_date ? String(i.scheduled_date).split(' ')[0] : '';
    if (!d) return false;
    const days = daysFromToday(d);
    return days >= 0 && days <= 7;
  }).length;

  $('#dashStatPending').textContent  = pending;
  $('#dashStatProgress').textContent = progress;
  $('#dashStatToday').textContent    = todayCount;
  $('#dashStatWeek').textContent     = weekCount;
  $('#dashStatTodayTile').classList.toggle('has-today', todayCount > 0);

  if (state.dashTab === 'tasks') renderDashTasks();
  else renderDashInterviews();
}

function setDashTab(tab) {
  state.dashTab = tab;
  $$('[data-dash-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.dashTab === tab));
  moveIndicator($('#dashboardView .task-tabs'), $('#dashboardView .tabs-indicator'), $('#dashboardView .task-tabs button.active'));
  $('#dashTaskList').classList.toggle('hidden', tab !== 'tasks');
  $('#dashInterviewList').classList.toggle('hidden', tab !== 'interviews');
  if (tab === 'tasks') renderDashTasks();
  else renderDashInterviews();
}

function isTaskOverdue(t) {
  if (!t.due_date || t.status === 'done') return false;
  return daysFromToday(String(t.due_date).split(' ')[0]) < 0;
}

function renderDashTasks() {
  const sorted = [...state.dashboard.tasks].sort(compareTasks);

  const statusLabel = s => ({ pending: 'Pendiente', in_progress: 'En curso' })[s] || s;
  const statusColor = s => s === 'in_progress' ? 'yellow' : '';

  $('#dashTaskList').innerHTML = sorted.length
    ? sorted.map(t => `
      <button class="item${isTaskOverdue(t) ? ' task-overdue' : ''}" data-dash-task-id="${t.id}">
        <header>
          <strong>${escapeHtml(t.title)}</strong>
          <span class="badge ${statusColor(t.status)}">${statusLabel(t.status)}</span>
        </header>
        <div class="dash-urgency">
          ${urgencyBadge(t.due_date, t.status)}
          ${t.due_date ? `<span class="dash-date">Vence ${formatDate(t.due_date)}</span>` : '<span class="dash-date">Sin fecha</span>'}
        </div>
        <span class="dash-item-area">${escapeHtml(t.area_name)}${t.unit_name ? ' · ' + escapeHtml(t.unit_name) : ''}</span>
      </button>`).join('')
    : '<div class="item"><p>No tienes tareas activas asignadas.</p></div>';

  $$('#dashTaskList [data-dash-task-id]').forEach(btn => {
    btn.addEventListener('click', () => goToTask(btn.dataset.dashTaskId));
  });
}

function renderDashInterviews() {
  const today = todayStr();
  const sorted = [...state.dashboard.interviews].sort(compareInterviews);

  $('#dashInterviewList').innerHTML = sorted.length
    ? sorted.map(i => {
      const isToday = i.scheduled_date && String(i.scheduled_date).split(' ')[0] === today;
      return `
      <button class="item${isToday ? ' interview-today' : ''}" data-dash-interview-id="${i.id}">
        <header>
          <strong>${escapeHtml(i.interviewee)}</strong>
          ${interviewUrgencyBadge(i.scheduled_date)}
        </header>
        <p>${formatInterviewDate(i.scheduled_date, i.scheduled_time)}</p>
        <span class="dash-item-area">${escapeHtml(i.area_name)}${i.unit_name ? ' · ' + escapeHtml(i.unit_name) : ''}</span>
      </button>`;
    }).join('')
    : '<div class="item"><p>No tienes entrevistas próximas asignadas.</p></div>';

  $$('#dashInterviewList [data-dash-interview-id]').forEach(btn => {
    btn.addEventListener('click', () => goToInterview(btn.dataset.dashInterviewId));
  });
}

async function goToTask(taskId) {
  const dash = state.dashboard.tasks.find(t => t.id === taskId);
  if (!dash) return;
  const area = state.areas.find(a => a.id === dash.work_area_id);
  if (area) {
    pickArea(area.id, area.name, area.unit_name || '');
    await loadMembers();
    await loadTasks();
  }
  showView('tasks');
  const task = state.tasks.find(t => t.id === taskId);
  if (task) openTaskDialog(task);
}

async function goToInterview(interviewId) {
  const dash = state.dashboard.interviews.find(i => i.id === interviewId);
  if (!dash) return;
  const area = state.areas.find(a => a.id === dash.work_area_id);
  if (area) {
    pickArea(area.id, area.name, area.unit_name || '');
    await loadInterviews();
  }
  showView('interviews');
}

// ─────────────────────────────────────────────────────────────────────────────

function isPersonalArea() {
  return !!(state.activeArea && Number(state.activeArea.is_personal) === 1);
}

function scopedTasks() {
  if (!isPersonalArea() && state.myTasksOnly && currentUser) {
    return state.tasks.filter(task => task.assigned_to === currentUser.id);
  }
  return state.tasks;
}

function renderTaskStats() {
  const counts = scopedTasks().reduce((acc, task) => {
    acc[task.status] = (acc[task.status] || 0) + 1;
    return acc;
  }, {});
  $('#statPending').textContent = counts.pending || 0;
  $('#statProgress').textContent = counts.in_progress || 0;
  $('#statDone').textContent = counts.done || 0;
}

function setTaskFilter(filter) {
  state.taskFilter = filter;
  $$('[data-task-filter]').forEach(button => button.classList.toggle('active', button.dataset.taskFilter === filter));
  moveIndicator($('#tasksView .task-tabs'), $('#tasksView .tabs-indicator'), $('#tasksView .task-tabs button.active'));
  renderTasks();
}

function renderTasks() {
  const tasks = scopedTasks().filter(task => state.taskFilter === 'done'
    ? task.status === 'done'
    : ['pending', 'in_progress'].includes(task.status));
  $('#taskList').innerHTML = tasks.map(task => `
    <button class="item${isTaskOverdue(task) ? ' task-overdue' : ''}" data-task-id="${task.id}">
      <header><strong>${escapeHtml(task.title)}</strong><span class="badge ${task.status === 'done' ? 'green' : task.status === 'in_progress' ? 'yellow' : ''}">${statusLabel(task.status)}</span></header>
      <p>Inicio: ${formatDate(task.start_date)} · Vence: ${formatDate(task.due_date)}</p>
      <div class="item-person">${avatarHtml(task.assigned_to_name)}<span>${escapeHtml(task.assigned_to_name || 'Sin responsable')}</span></div>
    </button>
  `).join('') || `<div class="item"><p>No hay tareas ${state.taskFilter === 'done' ? 'finalizadas' : 'pendientes'}.</p></div>`;
  $$('#taskList [data-task-id]').forEach(button => button.addEventListener('click', () => {
    openTaskDialog(state.tasks.find(task => task.id === button.dataset.taskId));
  }));
}

function openTaskDialog(task = null) {
  const form = $('#taskForm');
  form.reset();
  closeStatusOptions();
  closeResponsibleOptions();

  clearTimeout(autosaveTimer);
  form.elements.id.value = task?.id || '';
  $('#taskDescription').value = task?.description || '';
  setTimeout(() => autoResizeTextarea($('#taskDescription')), 0);

  const defaultId = task ? (task.assigned_to || '') : (currentUser?.id || '');
  const defaultName = task ? (task.assigned_to_name || '') : (currentUser?.name || '');
  populateResponsibleOptions(defaultId);
  pickResponsible(defaultId, defaultName);

  const titleDisplay = $('#taskTitleDisplay');
  const titleInput = $('#taskTitleInput');
  titleDisplay.textContent = task?.title || '';
  titleInput.value = task?.title || '';
  if (task) {
    titleDisplay.classList.remove('hidden');
    titleInput.classList.add('hidden');
  } else {
    titleDisplay.classList.add('hidden');
    titleInput.classList.remove('hidden');
  }

  pickStatus(task?.status || 'pending');

  let startRaw, dueRaw;
  if (task) {
    startRaw = String(task.start_date || '').split(' ')[0];
    dueRaw = String(task.due_date || '').split(' ')[0];
  } else {
    startRaw = todayIso();
    dueRaw = addDaysIso(startRaw, 7);
  }
  form.elements.start_date.value = startRaw;
  form.elements.due_date.value = dueRaw;
  setDateDisplay('start', startRaw);
  setDateDisplay('due', dueRaw);

  $('#deleteTaskBtn').classList.toggle('hidden', !task);
  $('#saveNewTaskBtn').classList.toggle('hidden', !!task);
  openModal($('#taskDialog'));
  if (!task) setTimeout(() => titleInput.focus(), 60);
}

const STATUS_CONFIG = {
  pending:     { label: 'Pendiente', cls: 'status-pending' },
  in_progress: { label: 'En curso',  cls: 'status-in_progress' },
  done:        { label: 'Hecha',     cls: 'status-done' },
};

function pickStatus(status, autosave = false) {
  const cfg = STATUS_CONFIG[status] || STATUS_CONFIG.pending;
  const tag = $('#statusTag');
  tag.textContent = cfg.label;
  tag.className = `status-tag ${cfg.cls}`;
  $('#taskStatusInput').value = status;
  $$('#statusOptions [data-status]').forEach(btn => btn.classList.toggle('active-option', btn.dataset.status === status));
  closeStatusOptions();
  if (autosave) scheduleAutosave(true);
}

function toggleStatusOptions() {
  $('#statusOptions').classList.toggle('hidden');
}

function closeStatusOptions() {
  $('#statusOptions').classList.add('hidden');
}

let autosaveTimer = null;

function scheduleAutosave(immediate = false) {
  const id = $('#taskForm').elements.id.value;
  if (!id) return;
  clearTimeout(autosaveTimer);
  if (immediate) {
    doAutosave();
  } else {
    autosaveTimer = setTimeout(doAutosave, 900);
  }
}

async function doAutosave() {
  const form = $('#taskForm');
  const id = form.elements.id.value;
  if (!id) return;
  if (!$('#taskTitleInput').classList.contains('hidden')) commitTitleEdit(false);
  const title = form.elements.title.value.trim();
  if (!title) return;
  const data = {
    title,
    description: $('#taskDescription').value,
    status: form.elements.status.value,
    assigned_to: form.elements.assigned_to.value,
    start_date: form.elements.start_date.value,
    due_date: form.elements.due_date.value,
    work_area_id: state.activeAreaId,
  };
  try {
    await api(`/api/tasks/${id}`, { method: 'PUT', body: data });
    await loadTasks();
  } catch (_) {}
}

async function closeTaskDialog() {
  clearTimeout(autosaveTimer);
  const form = $('#taskForm');
  const id = form.elements.id.value;
  if (!id) {
    if (!$('#taskTitleInput').classList.contains('hidden')) commitTitleEdit(false);
    const title = form.elements.title.value.trim();
    if (title) {
      try {
        const data = {
          title,
          description: $('#taskDescription').value,
          status: form.elements.status.value,
          assigned_to: form.elements.assigned_to.value,
          start_date: form.elements.start_date.value,
          due_date: form.elements.due_date.value,
          work_area_id: state.activeAreaId,
        };
        await api('/api/tasks', { method: 'POST', body: data });
        soundSave();
        await loadTasks();
      } catch (_) {}
    }
  }
  closeStatusOptions();
  closeResponsibleOptions();
  closeModal($('#taskDialog'));
}

function autoResizeTextarea(el) {
  el.style.height = 'auto';
  el.style.overflowY = 'hidden';
  const maxH = Math.min(Math.floor(window.innerHeight * 0.38), 340);
  if (el.scrollHeight > maxH) {
    el.style.height = maxH + 'px';
    el.style.overflowY = 'auto';
  } else {
    el.style.height = el.scrollHeight + 'px';
  }
}

function populateResponsibleOptions(currentId) {
  const container = $('#responsibleOptions');
  const options = [
    `<button type="button" data-member-id="" data-member-name="" ${!currentId ? 'class="active-option"' : ''}>
      <span class="avatar" style="--avatar-bg:#94a3b8;width:24px;height:24px;font-size:10px">?</span>
      Sin responsable
    </button>`,
    ...state.members.map(member => {
      const isActive = String(member.id) === String(currentId);
      return `<button type="button" data-member-id="${member.id}" data-member-name="${escapeHtml(member.name)}" ${isActive ? 'class="active-option"' : ''}>
        ${avatarHtml(member.name)}
        ${escapeHtml(member.name)}
      </button>`;
    }),
  ];
  container.innerHTML = options.join('');
  $$('#responsibleOptions button').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      pickResponsible(btn.dataset.memberId, btn.dataset.memberName, true);
    });
  });
}

function pickResponsible(id, name, autosave = false) {
  const avatarEl = $('#responsibleAvatar');
  const nameEl = $('#responsibleName');
  $('#taskAssignedToInput').value = id || '';
  if (name) {
    avatarEl.textContent = initials(name);
    avatarEl.style.setProperty('--avatar-bg', avatarColor(name));
    avatarEl.style.display = '';
    nameEl.textContent = name;
  } else {
    avatarEl.textContent = '';
    avatarEl.style.display = 'none';
    nameEl.textContent = 'Sin responsable';
  }
  $$('#responsibleOptions button').forEach(btn =>
    btn.classList.toggle('active-option', btn.dataset.memberId === (id || ''))
  );
  closeResponsibleOptions();
  if (autosave) scheduleAutosave(true);
}

function toggleResponsibleOptions() {
  $('#responsibleOptions').classList.toggle('hidden');
}

function closeResponsibleOptions() {
  $('#responsibleOptions').classList.add('hidden');
}

function startTitleEdit() {
  const titleDisplay = $('#taskTitleDisplay');
  const titleInput = $('#taskTitleInput');
  titleInput.value = titleDisplay.textContent;
  titleDisplay.classList.add('hidden');
  titleInput.classList.remove('hidden');
  titleInput.focus();
  titleInput.select();
}

function commitTitleEdit(triggerSave = true) {
  const titleDisplay = $('#taskTitleDisplay');
  const titleInput = $('#taskTitleInput');
  const val = titleInput.value.trim();
  if (!val) return;
  titleDisplay.textContent = val;
  titleDisplay.classList.remove('hidden');
  titleInput.classList.add('hidden');
  if (triggerSave) scheduleAutosave(true);
}

function setDateDisplay(field, rawValue) {
  const display = field === 'start' ? $('#startDateDisplay') : $('#dueDateDisplay');
  const input   = field === 'start' ? $('#startDateInput')   : $('#dueDateInput');
  const val = rawValue ? rawValue.split(' ')[0] : '';
  display.textContent = val ? formatDate(val) : 'Sin fecha';
  display.classList.toggle('no-date', !val);
  display.classList.remove('hidden');
  input.classList.add('hidden');
}

function startDateEdit(field) {
  const display = field === 'start' ? $('#startDateDisplay') : $('#dueDateDisplay');
  const input   = field === 'start' ? $('#startDateInput')   : $('#dueDateInput');
  display.classList.add('hidden');
  input.classList.remove('hidden');
  input.focus();
}

function commitDateEdit(field) {
  const input = field === 'start' ? $('#startDateInput') : $('#dueDateInput');
  setDateDisplay(field, input.value);
  scheduleAutosave();
}

function setInterviewDateDisplay(field, rawValue) {
  const isDate = field === 'date';
  const display = isDate ? $('#interviewDateDisplay') : $('#interviewTimeDisplay');
  const input   = isDate ? $('#interviewDate') : $('#interviewTime');
  const clearBtn = isDate ? $('#interviewDateClear') : $('#interviewTimeClear');
  const val = rawValue ? String(rawValue).split(' ')[0] : '';
  input.value = val;
  display.textContent = val ? (isDate ? formatDate(val) : val.slice(0, 5)) : (isDate ? 'Sin fecha' : 'Sin hora');
  display.classList.toggle('no-date', !val);
  display.classList.remove('hidden');
  input.classList.add('hidden');
  clearBtn.classList.toggle('hidden', !val);
}

function startInterviewDateEdit(field) {
  const isDate = field === 'date';
  const display = isDate ? $('#interviewDateDisplay') : $('#interviewTimeDisplay');
  const input   = isDate ? $('#interviewDate') : $('#interviewTime');
  display.classList.add('hidden');
  input.classList.remove('hidden');
  input.focus();
}

function commitInterviewDateEdit(field) {
  const input = field === 'date' ? $('#interviewDate') : $('#interviewTime');
  setInterviewDateDisplay(field, input.value);
  scheduleInterviewAutosave();
}

function setMinInterviewDateDisplay(field, rawValue) {
  const isDate = field === 'date';
  const display = isDate ? $('#minInterviewDateDisplay') : $('#minInterviewTimeDisplay');
  const input   = isDate ? $('#minInterviewDate') : $('#minInterviewTime');
  const clearBtn = isDate ? $('#minInterviewDateClear') : $('#minInterviewTimeClear');
  const val = rawValue ? String(rawValue).split(' ')[0] : '';
  input.value = val;
  display.textContent = val ? (isDate ? formatDate(val) : val.slice(0, 5)) : (isDate ? 'Sin fecha' : 'Sin hora');
  display.classList.toggle('no-date', !val);
  display.classList.remove('hidden');
  input.classList.add('hidden');
  clearBtn.classList.toggle('hidden', !val);
}

function startMinInterviewDateEdit(field) {
  const isDate = field === 'date';
  const display = isDate ? $('#minInterviewDateDisplay') : $('#minInterviewTimeDisplay');
  const input   = isDate ? $('#minInterviewDate') : $('#minInterviewTime');
  display.classList.add('hidden');
  input.classList.remove('hidden');
  input.focus();
}

function commitMinInterviewDateEdit(field) {
  const input = field === 'date' ? $('#minInterviewDate') : $('#minInterviewTime');
  setMinInterviewDateDisplay(field, input.value);
  scheduleMinisteringAutosave();
}

async function saveTask(event) {
  event.preventDefault();
  if (!$('#taskTitleInput').classList.contains('hidden')) commitTitleEdit();
  const data = { ...formData(event.currentTarget), work_area_id: state.activeAreaId };
  const id = data.id;
  delete data.id;
  await api(id ? `/api/tasks/${id}` : '/api/tasks', { method: id ? 'PUT' : 'POST', body: data });
  closeModal($('#taskDialog'));
  await loadTasks();
}

async function deleteTask() {
  const id = $('#taskForm').elements.id.value;
  if (!id) return;
  const title = $('#taskForm').elements.title.value;
  const confirmed = await confirmDialog({
    title: 'Eliminar tarea',
    message: `¿Seguro que quieres eliminar "${title}"? Esta acción no se puede deshacer.`,
    confirmLabel: 'Eliminar',
  });
  if (!confirmed) return;
  await api(`/api/tasks/${id}`, { method: 'DELETE' });
  soundDelete();
  closeModal($('#taskDialog'));
  await loadTasks();
}

function confirmDialog({ title = 'Confirmar', message = '', confirmLabel = 'Confirmar' } = {}) {
  const dialog = $('#confirmDialog');
  $('#confirmTitle').textContent = title;
  $('#confirmMessage').textContent = message;
  $('#confirmAcceptBtn').textContent = confirmLabel;
  return new Promise(resolve => {
    const accept = $('#confirmAcceptBtn');
    const cancel = $('#confirmCancelBtn');
    function settle(result) {
      accept.removeEventListener('click', onAccept);
      cancel.removeEventListener('click', onCancel);
      dialog.removeEventListener('cancel', onDismiss);
      closeModal(dialog);
      resolve(result);
    }
    function onAccept() { settle(true); }
    function onCancel() { settle(false); }
    function onDismiss(event) { event.preventDefault(); settle(false); }
    accept.addEventListener('click', onAccept);
    cancel.addEventListener('click', onCancel);
    dialog.addEventListener('cancel', onDismiss);
    openModal(dialog);
  });
}

async function showSettingsScreen(screenId) {
  if (screenId !== 'settingsHome' && $(`#${screenId}`).classList.contains('stake-admin-only') && !canManageSettings) {
    return;
  }
  $$('.settings-screen').forEach(screen => screen.classList.add('hidden'));
  hideAllAdminForms();
  $(`#${screenId}`).classList.remove('hidden');
  if (screenId === 'requestsScreen') await loadRequests();
  if (['unitsScreen', 'organizationsScreen', 'callingsScreen', 'areasScreen', 'rulesScreen'].includes(screenId)) {
    await loadAdminCatalog();
  }
}

function hideAllAdminForms() {
  ['unitForm', 'organizationForm', 'adminCallingForm', 'areaTemplateForm', 'ruleForm'].forEach(formId => {
    const form = $(`#${formId}`);
    if (form) {
      resetAdminForm(formId);
      form.classList.add('hidden');
    }
  });
}

function showAdminForm(formId) {
  resetAdminForm(formId);
  $(`#${formId}`).classList.remove('hidden');
  const first = $(`#${formId}`).querySelector('select, input:not([type="hidden"])');
  if (first) first.focus();
}

function hideAdminForm(formId) {
  resetAdminForm(formId);
  $(`#${formId}`).classList.add('hidden');
}

function renderAdminVisibility() {
  $$('.stake-admin-only').forEach(element => element.classList.toggle('hidden', !canManageSettings));
  $('#settingsAdminHint').textContent = canManageSettings
    ? 'Tienes permisos de presidencia de estaca para administrar catálogos y matrices.'
    : 'Las configuraciones avanzadas son visibles solo para la presidencia de estaca.';
}

async function loadRequests() {
  const requests = await api('/api/access-requests');
  $('#requestList').innerHTML = requests.map(request => `
    <div class="item">
      <header><strong>${escapeHtml(request.requester_name)}</strong><span class="badge yellow">Pendiente</span></header>
      <p>${escapeHtml(request.requester_email)}</p>
      <p>${escapeHtml(request.organization_name)} · ${escapeHtml(request.calling_name)} · ${escapeHtml(request.unit_name)}</p>
      <div class="actions">
        <button class="secondary" data-approve="${request.id}" type="button">Aprobar</button>
        <span></span>
        <button class="danger" data-reject="${request.id}" type="button">Rechazar</button>
      </div>
    </div>
  `).join('') || '<div class="item"><p>No hay solicitudes para aprobar.</p></div>';
  $$('[data-approve]').forEach(button => button.addEventListener('click', () => resolveRequest(button.dataset.approve, true)));
  $$('[data-reject]').forEach(button => button.addEventListener('click', () => resolveRequest(button.dataset.reject, false)));
}

async function resolveRequest(id, approve) {
  await api(`/api/access-requests/${id}/${approve ? 'approve' : 'reject'}`, { method: 'POST' });
  await loadAreas();
  await loadRequests();
}

async function loadAdminCatalog() {
  if (!canManageSettings) return;
  adminCatalog = await api('/api/admin/catalog');
  fillAdminSelects();
  renderAdminCatalog();
}

function fillAdminSelects() {
  const stakeOptions = adminCatalog.units
    .filter(unit => unit.type === 'stake' && Number(unit.active) === 1)
    .map(unit => `<option value="${unit.id}">${escapeHtml(unit.name)}</option>`)
    .join('');
  $('#unitForm').elements.parent_unit_id.innerHTML = stakeOptions;
  updateUnitParentState();
  const orgOptions = adminCatalog.organizations.map(org => `<option value="${org.id}">${escapeHtml(scopeLabel(org.scope_type))} · ${escapeHtml(org.name)}</option>`).join('');
  $('#adminCallingForm').elements.organization_id.innerHTML = orgOptions;
  $('#areaTemplateForm').elements.organization_id.innerHTML = '<option value="">Sin organización</option>' + orgOptions;
  $('#ruleForm').elements.calling_id.innerHTML = adminCatalog.callings.map(calling => `<option value="${calling.id}">${escapeHtml(calling.organization_name)} · ${escapeHtml(calling.name)}</option>`).join('');
  $('#ruleForm').elements.work_area_template_id.innerHTML = adminCatalog.work_area_templates.map(area => `<option value="${area.id}">${escapeHtml(scopeLabel(area.scope_type))} · ${escapeHtml(area.name)}</option>`).join('');
}

function renderAdminCatalog() {
  $('#unitList').innerHTML = adminCatalog.units.map(unit => adminRow({
    id: unit.id,
    title: unit.name,
    meta: `${unitTypeLabel(unit.type)}${unit.parent_name ? ` · ${unit.parent_name}` : ''}`,
    active: unit.active,
    edit: 'unit',
    remove: 'unit',
  })).join('');

  $('#organizationList').innerHTML = adminCatalog.organizations.map(org => adminRow({
    id: org.id,
    title: org.name,
    meta: `${scopeLabel(org.scope_type)} · Orden ${org.sort_order}`,
    active: org.active,
    edit: 'organization',
    remove: 'organization',
  })).join('');

  $('#adminCallingList').innerHTML = adminCatalog.callings.map(calling => adminRow({
    id: calling.id,
    title: calling.name,
    meta: `${calling.organization_name} · Autoridad: ${authorityLabel(calling.authority_scope)} · Orden ${calling.sort_order}`,
    active: calling.active,
    edit: 'calling',
    remove: 'calling',
  })).join('');

  $('#areaTemplateList').innerHTML = adminCatalog.work_area_templates.map(area => adminRow({
    id: area.id,
    title: area.name,
    meta: `${scopeLabel(area.scope_type)} · ${area.organization_name || 'Sin organización'} · Orden ${area.sort_order}`,
    active: area.active,
    edit: 'area',
    remove: 'area',
  })).join('');

  $('#ruleList').innerHTML = adminCatalog.rules.map(rule => `
    <div class="admin-row">
      <header><strong>${escapeHtml(rule.organization_name)} · ${escapeHtml(rule.calling_name)}</strong><span class="badge">${roleLabel(rule.access_role)}</span></header>
      <p class="meta">${escapeHtml(scopeLabel(rule.scope_type))} · ${escapeHtml(rule.area_name)}</p>
      <div class="actions">
        <button class="ghost" type="button" data-edit-rule="${rule.id}">Editar</button>
        <span></span>
        <button class="danger" type="button" data-delete-rule="${rule.id}">Eliminar</button>
      </div>
    </div>
  `).join('') || '<div class="item"><p>No hay reglas cargadas.</p></div>';

  wireAdminRows();
}

function adminRow({ id, title, meta, active, edit, remove }) {
  return `
    <div class="admin-row">
      <header><strong>${escapeHtml(title)}</strong><span class="badge ${Number(active) ? 'green' : 'red'}">${Number(active) ? 'Activa' : 'Inactiva'}</span></header>
      <p class="meta">${escapeHtml(meta)}</p>
      <div class="actions">
        <button class="ghost" type="button" data-edit-${edit}="${id}">Editar</button>
        <span></span>
        <button class="danger" type="button" data-delete-${remove}="${id}">Desactivar</button>
      </div>
    </div>
  `;
}

function wireAdminRows() {
  $$('[data-edit-unit]').forEach(button => button.addEventListener('click', () => editUnit(button.dataset.editUnit)));
  $$('[data-delete-unit]').forEach(button => button.addEventListener('click', () => deleteAdminItem(`/api/admin/units/${button.dataset.deleteUnit}`)));
  $$('[data-edit-organization]').forEach(button => button.addEventListener('click', () => editOrganization(button.dataset.editOrganization)));
  $$('[data-delete-organization]').forEach(button => button.addEventListener('click', () => deleteAdminItem(`/api/admin/organizations/${button.dataset.deleteOrganization}`)));
  $$('[data-edit-calling]').forEach(button => button.addEventListener('click', () => editAdminCalling(button.dataset.editCalling)));
  $$('[data-delete-calling]').forEach(button => button.addEventListener('click', () => deleteAdminItem(`/api/admin/callings/${button.dataset.deleteCalling}`)));
  $$('[data-edit-area]').forEach(button => button.addEventListener('click', () => editAreaTemplate(button.dataset.editArea)));
  $$('[data-delete-area]').forEach(button => button.addEventListener('click', () => deleteAdminItem(`/api/admin/work-area-templates/${button.dataset.deleteArea}`)));
  $$('[data-edit-rule]').forEach(button => button.addEventListener('click', () => editRule(button.dataset.editRule)));
  $$('[data-delete-rule]').forEach(button => button.addEventListener('click', () => deleteAdminItem(`/api/admin/rules/${button.dataset.deleteRule}`)));
}

async function saveUnit(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = adminFormData(form);
  const id = data.id;
  delete data.id;
  if (data.type === 'stake') data.parent_unit_id = '';
  await api(id ? `/api/admin/units/${id}` : '/api/admin/units', { method: id ? 'PUT' : 'POST', body: data });
  hideAdminForm('unitForm');
  await loadCatalogs();
  await loadAdminCatalog();
}

function editUnit(id) {
  const item = adminCatalog.units.find(unit => String(unit.id) === String(id));
  showAdminForm('unitForm');
  const form = $('#unitForm');
  form.elements.id.value = item.id;
  form.elements.type.value = item.type;
  form.elements.parent_unit_id.value = item.parent_unit_id || '';
  form.elements.name.value = item.name;
  form.elements.active.checked = Number(item.active) === 1;
  updateUnitParentState();
}

function updateUnitParentState() {
  const form = $('#unitForm');
  const isStake = form.elements.type.value === 'stake';
  form.elements.parent_unit_id.disabled = isStake;
  if (isStake) form.elements.parent_unit_id.value = '';
}

async function saveOrganization(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = adminFormData(form);
  const id = data.id;
  delete data.id;
  await api(id ? `/api/admin/organizations/${id}` : '/api/admin/organizations', { method: id ? 'PUT' : 'POST', body: data });
  hideAdminForm('organizationForm');
  await loadAdminCatalog();
}

function editOrganization(id) {
  const item = adminCatalog.organizations.find(org => String(org.id) === String(id));
  showAdminForm('organizationForm');
  const form = $('#organizationForm');
  form.elements.id.value = item.id;
  form.elements.scope_type.value = item.scope_type;
  form.elements.name.value = item.name;
  form.elements.sort_order.value = item.sort_order;
  form.elements.active.checked = Number(item.active) === 1;
}

async function saveAdminCalling(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = adminFormData(form);
  const id = data.id;
  delete data.id;
  await api(id ? `/api/admin/callings/${id}` : '/api/admin/callings', { method: id ? 'PUT' : 'POST', body: data });
  hideAdminForm('adminCallingForm');
  await loadAdminCatalog();
}

function editAdminCalling(id) {
  const item = adminCatalog.callings.find(calling => String(calling.id) === String(id));
  showAdminForm('adminCallingForm');
  const form = $('#adminCallingForm');
  form.elements.id.value = item.id;
  form.elements.organization_id.value = item.organization_id;
  form.elements.name.value = item.name;
  form.elements.authority_scope.value = item.authority_scope;
  form.elements.sort_order.value = item.sort_order;
  form.elements.active.checked = Number(item.active) === 1;
}

async function saveAreaTemplate(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = adminFormData(form);
  const id = data.id;
  delete data.id;
  await api(id ? `/api/admin/work-area-templates/${id}` : '/api/admin/work-area-templates', { method: id ? 'PUT' : 'POST', body: data });
  hideAdminForm('areaTemplateForm');
  await loadAdminCatalog();
}

function editAreaTemplate(id) {
  const item = adminCatalog.work_area_templates.find(area => String(area.id) === String(id));
  showAdminForm('areaTemplateForm');
  const form = $('#areaTemplateForm');
  form.elements.id.value = item.id;
  form.elements.scope_type.value = item.scope_type;
  form.elements.organization_id.value = item.organization_id || '';
  form.elements.name.value = item.name;
  form.elements.sort_order.value = item.sort_order;
  form.elements.is_personal.checked = Number(item.is_personal) === 1;
  form.elements.active.checked = Number(item.active) === 1;
}

async function saveRule(event) {
  event.preventDefault();
  const data = formData(event.currentTarget);
  const id = data.id;
  delete data.id;
  await api(id ? `/api/admin/rules/${id}` : '/api/admin/rules', { method: id ? 'PUT' : 'POST', body: data });
  hideAdminForm('ruleForm');
  await loadAdminCatalog();
}

function editRule(id) {
  const item = adminCatalog.rules.find(rule => String(rule.id) === String(id));
  showAdminForm('ruleForm');
  const form = $('#ruleForm');
  form.elements.id.value = item.id;
  form.elements.calling_id.value = item.calling_id;
  form.elements.work_area_template_id.value = item.work_area_template_id;
  form.elements.access_role.value = item.access_role;
}

async function deleteAdminItem(path) {
  await api(path, { method: 'DELETE' });
  await loadAdminCatalog();
}

function resetAdminForm(formId) {
  const form = $(`#${formId}`);
  form.reset();
  if (form.elements.id) form.elements.id.value = '';
  if (form.elements.sort_order) form.elements.sort_order.value = '99';
  if (form.elements.active) form.elements.active.checked = true;
  if (form.elements.is_personal) form.elements.is_personal.checked = false;
  if (formId === 'unitForm') updateUnitParentState();
}

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (csrf) headers['X-CSRF-Token'] = csrf;
  const response = await fetch(path, {
    ...options,
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error?.message || `HTTP ${response.status}`);
  }
  return payload.data;
}

function formData(form) {
  return Object.fromEntries(new FormData(form));
}

function adminFormData(form) {
  const data = formData(form);
  $$('input[type="checkbox"]', form).forEach(input => {
    data[input.name] = input.checked ? 1 : 0;
  });
  return data;
}

function statusLabel(status) {
  return ({ pending: 'Pendiente', active: 'Activa', rejected: 'Rechazada', removed: 'Quitado', in_progress: 'En curso', done: 'Hecha', member: 'Miembro' })[status] || status;
}

function scopeLabel(scope) {
  return ({ personal: 'Personal', stake: 'Estaca', ward: 'Barrio', branch: 'Rama' })[scope] || scope;
}

function unitTypeLabel(type) {
  return ({ stake: 'Estaca', ward: 'Barrio', branch: 'Rama' })[type] || type;
}

function authorityLabel(scope) {
  return ({ none: 'Sin autoridad', area: 'Área', unit: 'Unidad', stake: 'Estaca' })[scope] || scope;
}

function roleLabel(role) {
  return ({ member: 'Miembro', manager: 'Administrador', owner: 'Propietario' })[role] || role;
}

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function addDaysIso(iso, days) {
  const [y, m, day] = iso.split('-').map(Number);
  const d = new Date(y, m - 1, day + days);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatDate(value) {
  if (!value) return 'Sin fecha';
  const [datePart] = String(value).split(' ');
  const [year, month, day] = datePart.split('-').map(Number);
  if (!year || !month || !day) return value;
  const date = new Date(Date.UTC(year, month - 1, day));
  return new Intl.DateTimeFormat('es-UY', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' })
    .format(date)
    .replace(/\./g, '')
    .replace(/ de /g, ' ');
}

function compareTasks(a, b) {
  return compareDateAsc(a.due_date, b.due_date)
    || compareDateDesc(a.start_date, b.start_date)
    || compareStatus(a.status, b.status)
    || String(b.created_at || '').localeCompare(String(a.created_at || ''));
}

function compareInterviews(a, b) {
  const ad = a.scheduled_date, bd = b.scheduled_date;
  if (!ad && !bd) return String(b.created_at || '').localeCompare(String(a.created_at || ''));
  if (!ad) return -1;
  if (!bd) return 1;
  const c = String(ad).localeCompare(String(bd));
  if (c) return c;
  return String(a.scheduled_time || '').localeCompare(String(b.scheduled_time || ''));
}

function compareDateDesc(a, b) {
  if (!a && !b) return 0;
  if (!a) return 1;
  if (!b) return -1;
  return String(b).localeCompare(String(a));
}

function compareDateAsc(a, b) {
  if (!a && !b) return 0;
  if (!a) return 1;
  if (!b) return -1;
  return String(a).localeCompare(String(b));
}

function compareStatus(a, b) {
  const order = { pending: 1, in_progress: 2, done: 3 };
  return (order[a] || 99) - (order[b] || 99);
}

/* ── Interviews ────────────────────────────────────────── */

async function loadInterviews() {
  if (!state.activeAreaId) {
    state.interviews = [];
    renderInterviews();
    return;
  }
  state.interviews = await api(`/api/interviews?work_area_id=${encodeURIComponent(state.activeAreaId)}`);
  renderInterviews();
}

function renderInterviews() {
  let list = [...state.interviews];
  if (state.interviewFilter === 'upcoming') {
    list = list.filter(i => !Number(i.completed));
  } else {
    list = list.filter(i => Number(i.completed));
  }
  if (state.myInterviewsOnly && currentUser) {
    list = list.filter(i => i.interviewer_id === currentUser.id);
  }
  list.sort(compareInterviews);
  const container = $('#interviewList');
  if (!list.length) {
    container.innerHTML = `<div class="item"><p>No hay entrevistas ${state.interviewFilter === 'done' ? 'realizadas' : 'próximas'}.</p></div>`;
    return;
  }
  const today = todayStr();
  container.innerHTML = list.map(interview => {
    const completed = Number(interview.completed);
    const badge = completed
      ? '<span class="badge green">Realizada</span>'
      : (interview.scheduled_date ? '<span class="badge">Agendada</span>' : '<span class="badge yellow">Sin fecha</span>');
    const interviewerHtml = interview.interviewer_name
      ? `${avatarHtml(interview.interviewer_name)}<span>${escapeHtml(interview.interviewer_name)}</span>`
      : `<span style="color:var(--muted);font-size:13px">Sin entrevistador</span>`;
    const isToday = !completed && interview.scheduled_date && String(interview.scheduled_date).split(' ')[0] === today;
    return `
      <button class="item${isToday ? ' interview-today' : ''}" data-interview-id="${interview.id}">
        <header><strong>${escapeHtml(interview.interviewee)}</strong>${badge}</header>
        <p>${formatInterviewDate(interview.scheduled_date, interview.scheduled_time)}</p>
        <div class="item-person">${interviewerHtml}</div>
      </button>`;
  }).join('');
  $$('#interviewList [data-interview-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      openInterviewDialog(state.interviews.find(i => i.id === btn.dataset.interviewId));
    });
  });
}

function formatInterviewDate(date, time) {
  const dateStr = formatDate(date);
  if (!time || time === '00:00:00') return dateStr;
  return `${dateStr} · ${time.slice(0, 5)}`;
}

async function setInterviewTab(tab) {
  $$('[data-interview-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.interviewTab === tab));
  moveIndicator($('#interviewSubTabs'), $('#interviewSubTabs .tabs-indicator'), $('[data-interview-tab].active'));
  $('#callingInterviewsPanel').classList.toggle('hidden', tab !== 'calling');
  $('#ministeringPanel').classList.toggle('hidden', tab !== 'ministering');
  $('#addInterviewBtn').classList.toggle('hidden', tab !== 'calling');
  $('#addPairBtn').classList.toggle('hidden', tab !== 'ministering');
  if (tab === 'ministering') {
    await loadPairs();
    moveIndicator($('#minFilterSegs'), $('#minFilterSegs .tabs-indicator'), $('[data-min-filter].active'));
  }
}

function setMinFilter(filter) {
  state.minFilter = filter;
  $$('[data-min-filter]').forEach(btn => btn.classList.toggle('active', btn.dataset.minFilter === filter));
  moveIndicator($('#minFilterSegs'), $('#minFilterSegs .tabs-indicator'), $('[data-min-filter].active'));
  renderPairs();
}

function setInterviewFilter(filter) {
  state.interviewFilter = filter;
  $$('[data-interview-filter]').forEach(btn => btn.classList.toggle('active', btn.dataset.interviewFilter === filter));
  moveIndicator($('#interviewFilterSegs'), $('#interviewFilterSegs .tabs-indicator'), $('[data-interview-filter].active'));
  renderInterviews();
}

let interviewAutosaveTimer = null;

function scheduleInterviewAutosave(immediate = false) {
  const id = $('#interviewForm').elements.id.value;
  if (!id) return;
  clearTimeout(interviewAutosaveTimer);
  if (immediate) {
    doInterviewAutosave();
  } else {
    interviewAutosaveTimer = setTimeout(doInterviewAutosave, 900);
  }
}

async function doInterviewAutosave() {
  const id = $('#interviewForm').elements.id.value;
  if (!id) return;
  const interviewee = $('#intervieweeName').value.trim();
  if (!interviewee) return;
  const data = {
    interviewee,
    scheduled_date: $('#interviewDate').value || null,
    scheduled_time: $('#interviewTime').value || null,
    interviewer_id: $('#interviewerIdInput').value || null,
    notes: $('#interviewNotes').value,
    completed: $('#interviewCompletedCheck').checked ? 1 : 0,
    work_area_id: state.activeAreaId,
  };
  try {
    await api(`/api/interviews/${id}`, { method: 'PUT', body: data });
    await loadInterviews();
  } catch (_) {}
}

function openInterviewDialog(interview = null) {
  const form = $('#interviewForm');
  form.reset();
  clearTimeout(interviewAutosaveTimer);
  closeInterviewerOptions();

  form.elements.id.value = interview?.id || '';
  $('#intervieweeName').value = interview?.interviewee || '';
  const iDateVal = interview?.scheduled_date ? String(interview.scheduled_date).split(' ')[0] : '';
  const iTimeVal = interview?.scheduled_time ? interview.scheduled_time.slice(0, 5) : '';
  $('#interviewDate').value = iDateVal;
  $('#interviewTime').value = iTimeVal;
  setInterviewDateDisplay('date', iDateVal);
  setInterviewDateDisplay('time', iTimeVal);
  $('#interviewNotes').value = interview?.notes || '';
  setTimeout(() => autoResizeTextarea($('#interviewNotes')), 0);
  $('#interviewCompletedCheck').checked = !!Number(interview?.completed);

  populateInterviewerOptions(interview?.interviewer_id || '');
  pickInterviewer(interview?.interviewer_id || '', interview?.interviewer_name || '', false);

  $('#deleteInterviewBtn').classList.toggle('hidden', !interview);
  $('#saveNewInterviewBtn').classList.toggle('hidden', !!interview);
  openModal($('#interviewDialog'));
  if (!interview) setTimeout(() => $('#intervieweeName').focus(), 60);
}

async function closeInterviewDialog() {
  clearTimeout(interviewAutosaveTimer);
  const form = $('#interviewForm');
  const id = form.elements.id.value;
  if (!id) {
    const interviewee = $('#intervieweeName').value.trim();
    if (interviewee) {
      try {
        await api('/api/interviews', {
          method: 'POST',
          body: {
            interviewee,
            scheduled_date: $('#interviewDate').value || null,
            scheduled_time: $('#interviewTime').value || null,
            interviewer_id: $('#interviewerIdInput').value || null,
            notes: $('#interviewNotes').value,
            work_area_id: state.activeAreaId,
          },
        });
        soundSave();
        await loadInterviews();
      } catch (_) {}
    }
  }
  closeInterviewerOptions();
  closeModal($('#interviewDialog'));
}

async function deleteInterview() {
  const id = $('#interviewForm').elements.id.value;
  if (!id) return;
  const interviewee = $('#intervieweeName').value;
  const confirmed = await confirmDialog({
    title: 'Eliminar entrevista',
    message: `¿Seguro que quieres eliminar la entrevista con "${interviewee}"? Esta acción no se puede deshacer.`,
    confirmLabel: 'Eliminar',
  });
  if (!confirmed) return;
  await api(`/api/interviews/${id}`, { method: 'DELETE' });
  soundDelete();
  closeModal($('#interviewDialog'));
  await loadInterviews();
}

function populateInterviewerOptions(currentId) {
  const interviewers = state.members.filter(m => Number(m.can_interview) === 1);
  const container = $('#interviewerOptions');
  const options = [
    `<button type="button" data-interviewer-id="" data-interviewer-name="">
      <span class="avatar" style="--avatar-bg:#94a3b8;width:24px;height:24px;font-size:10px">?</span>
      Sin asignar
    </button>`,
    ...interviewers.map(member =>
      `<button type="button" data-interviewer-id="${member.id}" data-interviewer-name="${escapeHtml(member.name)}">
        ${avatarHtml(member.name)}
        ${escapeHtml(member.name)}
      </button>`
    ),
  ];
  container.innerHTML = options.join('');
  $$('#interviewerOptions button').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      pickInterviewer(btn.dataset.interviewerId, btn.dataset.interviewerName, true);
    });
  });
}

function pickInterviewer(id, name, autosave = false) {
  const avatarEl = $('#interviewerAvatar');
  const nameEl = $('#interviewerName');
  $('#interviewerIdInput').value = id || '';
  if (name) {
    avatarEl.textContent = initials(name);
    avatarEl.style.setProperty('--avatar-bg', avatarColor(name));
    avatarEl.style.display = '';
    nameEl.textContent = name;
  } else {
    avatarEl.textContent = '';
    avatarEl.style.display = 'none';
    nameEl.textContent = 'Sin asignar';
  }
  $$('#interviewerOptions button').forEach(btn =>
    btn.classList.toggle('active-option', btn.dataset.interviewerId === (id || ''))
  );
  closeInterviewerOptions();
  if (autosave) scheduleInterviewAutosave(true);
}

function toggleInterviewerOptions() {
  $('#interviewerOptions').classList.toggle('hidden');
}

function closeInterviewerOptions() {
  $('#interviewerOptions').classList.add('hidden');
}

/* ── end Interviews ────────────────────────────────────── */

/* ── Ministering ──────────────────────────────────────── */

function formatQuarter(q) {
  const [year, qpart] = q.split('-');
  const ranges = ['Ene – Mar', 'Abr – Jun', 'Jul – Sep', 'Oct – Dic'];
  return `${ranges[parseInt(qpart.slice(1)) - 1]} ${year}`;
}

function navigateQuarter(delta) {
  const [year, qpart] = state.pairQuarter.split('-');
  const q = parseInt(qpart.slice(1)) + delta;
  if (q < 1) state.pairQuarter = `${parseInt(year) - 1}-Q4`;
  else if (q > 4) state.pairQuarter = `${parseInt(year) + 1}-Q1`;
  else state.pairQuarter = `${year}-Q${q}`;
  loadPairs();
}

async function loadPairs() {
  if (!state.activeAreaId) { state.pairs = []; renderPairs(); return; }
  state.pairs = await api(
    `/api/ministering/pairs?work_area_id=${encodeURIComponent(state.activeAreaId)}&quarter=${encodeURIComponent(state.pairQuarter)}`
  );
  renderPairs();
}

function renderPairs() {
  $('#quarterLabel').textContent = formatQuarter(state.pairQuarter);
  const list = $('#pairList');

  let pairs = state.pairs;
  if (state.minFilter === 'upcoming') {
    pairs = pairs.filter(p => !Number(p.completed));
  } else {
    pairs = pairs.filter(p => Number(p.completed) === 1);
  }
  if (state.myMinisteringOnly && currentUser) {
    pairs = pairs.filter(p => p.interviewer_id === currentUser.id);
  }

  if (!pairs.length) {
    list.innerHTML = '<div class="empty-state"><p>No hay entrevistas en esta categoría.</p></div>';
    return;
  }
  list.innerHTML = pairs.map(pair => {
    const m2 = pair.minister2 ? ` · ${escapeHtml(pair.minister2)}` : '';
    const done = Number(pair.completed) === 1;
    const badge = done
      ? '<span class="badge green">Realizada</span>'
      : (pair.interviewer_id ? '<span class="badge">Agendada</span>' : '<span class="badge yellow">Sin asignar</span>');
    const interviewerHtml = pair.interviewer_name
      ? `${avatarHtml(pair.interviewer_name)}<span>${escapeHtml(pair.interviewer_name)}</span>`
      : `<span style="color:var(--muted);font-size:13px">Sin entrevistador</span>`;
    const dateStr = pair.scheduled_date
      ? formatInterviewDate(pair.scheduled_date, pair.scheduled_time)
      : 'Sin fecha';
    const editBtn = `<button type="button" class="pair-edit-btn" data-edit-pair="${pair.id}" aria-label="Editar pareja">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </button>`;
    return `<div class="item pair-item" data-pair-id="${pair.id}">
      <button class="pair-interview-btn" type="button">
        <header><strong>${escapeHtml(pair.minister1)}${m2}</strong>${badge}</header>
        <p>${dateStr}</p>
        <div class="item-person">${interviewerHtml}</div>
      </button>
      ${editBtn}
    </div>`;
  }).join('');
  $$('#pairList .pair-interview-btn').forEach(btn => {
    btn.addEventListener('click', () => openMinisteringInterview(btn.closest('[data-pair-id]').dataset.pairId));
  });
  $$('#pairList [data-edit-pair]').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); openPairDialog(btn.dataset.editPair); });
  });
}

function openPairDialog(pairId = null) {
  const pair = pairId ? state.pairs.find(p => p.id === pairId) : null;
  $('#pairDialogTitle').textContent = pair ? 'Editar pareja' : 'Nueva pareja';
  const f = $('#pairForm');
  f.elements.id.value = pair?.id ?? '';
  f.elements.minister1.value = pair?.minister1 ?? '';
  f.elements.minister2.value = pair?.minister2 ?? '';
  f.elements.assigned_to.value = pair?.assigned_to ?? '';
  $('#deletePairBtn').classList.toggle('hidden', !pair);
  openModal($('#pairDialog'));
}

function closePairDialog() { closeModal($('#pairDialog')); }

async function savePair() {
  const f = $('#pairForm');
  if (!f.reportValidity()) return;
  const id = f.elements.id.value;
  const payload = {
    minister1: f.elements.minister1.value.trim(),
    minister2: f.elements.minister2.value.trim(),
    assigned_to: f.elements.assigned_to.value.trim(),
  };
  try {
    if (!id) {
      payload.work_area_id = state.activeAreaId;
      await api('/api/ministering/pairs', { method: 'POST', body: payload });
      soundSave();
    } else {
      await api(`/api/ministering/pairs/${id}`, { method: 'PUT', body: payload });
    }
    closePairDialog();
    await loadPairs();
  } catch (e) { alert(e.message); }
}

async function deletePair() {
  const id = $('#pairForm').elements.id.value;
  if (!id) return;
  const pair = state.pairs.find(p => p.id === id);
  const label = pair
    ? `${pair.minister1}${pair.minister2 ? ' · ' + pair.minister2 : ''} → ${pair.assigned_to}`
    : 'esta pareja';
  const ok = await confirmDialog({
    title: 'Eliminar pareja',
    message: `¿Eliminar "${label}"? Se perderán todos sus registros de entrevistas.`,
    confirmLabel: 'Eliminar',
  });
  if (!ok) return;
  closePairDialog();
  await api(`/api/ministering/pairs/${id}`, { method: 'DELETE' });
  soundDelete();
  await loadPairs();
}

let minInterviewTimer = null;

function openMinisteringInterview(pairId) {
  const pair = state.pairs.find(p => p.id === pairId);
  if (!pair) return;
  const f = $('#ministeringInterviewForm');
  f.elements.pair_id.value = pair.id;
  f.elements.quarter.value = state.pairQuarter;
  const minDateVal = pair.scheduled_date ?? '';
  const minTimeVal = pair.scheduled_time ? pair.scheduled_time.slice(0, 5) : '';
  f.elements.scheduled_date.value = minDateVal;
  f.elements.scheduled_time.value = minTimeVal;
  setMinInterviewDateDisplay('date', minDateVal);
  setMinInterviewDateDisplay('time', minTimeVal);
  f.elements.notes.value = pair.notes ?? '';
  f.elements.completed.checked = Number(pair.completed) === 1;
  const m2 = pair.minister2 ? ` · ${escapeHtml(pair.minister2)}` : '';
  $('#minPairInfo').innerHTML =
    `<span class="pair-info-ministers">${escapeHtml(pair.minister1)}${m2}</span>` +
    `<span class="pair-info-arrow">→</span>` +
    `<span class="pair-info-assigned">${escapeHtml(pair.assigned_to)}</span>`;
  populateMinInterviewerOptions();
  pickMinInterviewer(pair.interviewer_id ?? '', pair.interviewer_name ?? '', false);
  autoResizeTextarea($('#minInterviewNotes'));
  openModal($('#ministeringInterviewDialog'));
}

function closeMinisteringInterview() {
  clearTimeout(minInterviewTimer);
  doMinisteringAutosave();
  closeModal($('#ministeringInterviewDialog'));
}

function scheduleMinisteringAutosave(immediate = false) {
  clearTimeout(minInterviewTimer);
  if (immediate) { doMinisteringAutosave(); } else { minInterviewTimer = setTimeout(doMinisteringAutosave, 900); }
}

async function doMinisteringAutosave() {
  clearTimeout(minInterviewTimer);
  const f = $('#ministeringInterviewForm');
  const pairId = f.elements.pair_id.value;
  const quarter = f.elements.quarter.value;
  if (!pairId || !quarter) return;
  const data = {
    quarter,
    scheduled_date: f.elements.scheduled_date.value || null,
    scheduled_time: f.elements.scheduled_time.value || null,
    interviewer_id: f.elements.interviewer_id.value || null,
    notes: f.elements.notes.value,
    completed: f.elements.completed.checked ? 1 : 0,
  };
  try {
    await api(`/api/ministering/pairs/${pairId}/interview`, { method: 'PUT', body: data });
    const pair = state.pairs.find(p => p.id === pairId);
    if (pair) {
      Object.assign(pair, {
        completed: data.completed, scheduled_date: data.scheduled_date,
        scheduled_time: data.scheduled_time, notes: data.notes,
        interviewer_id: data.interviewer_id,
        interviewer_name: state.members.find(m => m.id === data.interviewer_id)?.name ?? null,
      });
      renderPairs();
    }
  } catch (_) { /* silent */ }
}

function populateMinInterviewerOptions() {
  const opts = $('#minInterviewerOptions');
  const interviewers = state.members.filter(m => Number(m.can_interview) === 1);
  opts.innerHTML = [
    `<button type="button" data-min-iid="" data-min-iname="">Sin asignar</button>`,
    ...interviewers.map(m =>
      `<button type="button" data-min-iid="${m.id}" data-min-iname="${escapeHtml(m.name)}">${avatarHtml(m.name)} ${escapeHtml(m.name)}</button>`
    ),
  ].join('');
  $$('#minInterviewerOptions [data-min-iid]').forEach(btn =>
    btn.addEventListener('click', e => { e.stopPropagation(); pickMinInterviewer(btn.dataset.minIid, btn.dataset.minIname, true); })
  );
}

function pickMinInterviewer(id, name, autosave = false) {
  $('#ministeringInterviewForm').elements.interviewer_id.value = id || '';
  const avatarEl = $('#minInterviewerAvatar');
  const nameEl = $('#minInterviewerName');
  if (id && name) {
    avatarEl.textContent = initials(name);
    avatarEl.style.setProperty('--avatar-bg', avatarColor(name));
    avatarEl.style.display = '';
    nameEl.textContent = name;
  } else {
    avatarEl.textContent = '';
    avatarEl.style.display = 'none';
    nameEl.textContent = 'Sin asignar';
  }
  $$('#minInterviewerOptions [data-min-iid]').forEach(btn =>
    btn.classList.toggle('active-option', btn.dataset.minIid === (id || ''))
  );
  closeMinInterviewerOptions();
  if (autosave) scheduleMinisteringAutosave(true);
}

function toggleMinInterviewerOptions() { $('#minInterviewerOptions').classList.toggle('hidden'); }
function closeMinInterviewerOptions() { $('#minInterviewerOptions').classList.add('hidden'); }

/* ── end Ministering ────────────────────────────────────── */

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
}

async function loadAppVersion() {
  try {
    const ts = parseInt(await (await fetch('/version.php')).text(), 10);
    const label = new Date(ts * 1000).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    document.querySelectorAll('.app-version').forEach(el => el.textContent = label);
  } catch (_) {}
}

async function forceUpdate() {
  const btn = $('#forceUpdateBtn');
  btn.disabled = true;
  btn.textContent = 'Actualizando…';
  try {
    await caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
    if ('serviceWorker' in navigator) {
      const reg = await navigator.serviceWorker.getRegistration();
      if (reg) await reg.update();
    }
  } finally {
    window.location.reload();
  }
}

function initIosInstallBanner() {
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.navigator.standalone === true;
  const isSafari = /safari/i.test(navigator.userAgent) && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(navigator.userAgent);
  if (isIos && !isStandalone && isSafari && !localStorage.getItem('iosInstallDismissed')) {
    $('#iosInstallBanner').classList.remove('hidden');
  }
}

const AVATAR_PALETTE = ['#1E3A8A', '#d97706', '#10b981', '#F59E0B', '#ef4444', '#3b63c8', '#0f1f4d'];

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
}

function avatarColor(name) {
  let hash = 0;
  const value = String(name || '');
  for (let i = 0; i < value.length; i++) hash = (hash * 31 + value.charCodeAt(i)) >>> 0;
  return AVATAR_PALETTE[hash % AVATAR_PALETTE.length];
}

function avatarHtml(name) {
  if (!name) return '';
  return `<span class="avatar" style="--avatar-bg:${avatarColor(name)}">${escapeHtml(initials(name))}</span>`;
}
