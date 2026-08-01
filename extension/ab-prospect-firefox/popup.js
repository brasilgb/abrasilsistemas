import { DEFAULT_SETTINGS } from './config.js';
import { loadState, saveProspects, saveSettings } from './services/storage.js';
import { extractFromMaps } from './services/maps.js';
import { analyzeWebsite } from './services/site-analyzer.js';
import { sendToCrm, buildPayload, MAX_PROSPECTS_PER_REQUEST } from './services/crm.js';

const $ = (id) => document.getElementById(id);
let prospects = [];
let settings = { ...DEFAULT_SETTINGS };
let activeFilter = 'all';

init();

async function init() {
  bindEvents();
  const state = await loadState();
  prospects = state.prospects;
  settings = state.settings;
  fillSettings();
  render();
}

function bindEvents() {
  document.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => openTab(button.dataset.tab)));
  $('captureSelected').addEventListener('click', () => capture('selected'));
  $('captureVisible').addEventListener('click', () => capture('visible'));
  $('analyzeSelected').addEventListener('click', analyzeSelected);
  $('sendSelected').addEventListener('click', sendSelected);
  $('clearAll').addEventListener('click', clearAll);
  $('selectAll').addEventListener('change', toggleAll);
  $('search').addEventListener('input', renderList);
  $('filter').addEventListener('change', (event) => { activeFilter = event.target.value; renderList(); });
  $('saveSettings').addEventListener('click', persistSettings);
  $('testPayload').addEventListener('click', previewPayload);
}

function openTab(name) {
  document.querySelectorAll('[data-tab]').forEach((el) => el.classList.toggle('active', el.dataset.tab === name));
  document.querySelectorAll('.panel').forEach((el) => el.classList.toggle('hidden', el.id !== `${name}Panel`));
  if (name === 'list') renderList();
}

async function capture(mode) {
  const button = mode === 'selected' ? $('captureSelected') : $('captureVisible');
  setBusy(button, true);
  status(mode === 'selected' ? 'Lendo empresa selecionada...' : 'Lendo resultados visíveis...');
  try {
    const items = await extractFromMaps(mode);
    if (!items.length) throw new Error(mode === 'selected' ? 'Selecione uma empresa no Google Maps.' : 'Nenhum resultado visível foi encontrado.');
    merge(items);
    await saveProspects(prospects);
    render();
    status(`${items.length} prospect(s) adicionado(s) ou atualizado(s).`, 'success');
  } catch (error) {
    status(error.message || 'Falha na captura.', 'error');
  } finally {
    setBusy(button, false);
  }
}

function merge(items) {
  for (const item of items) {
    const index = prospects.findIndex((current) => current.id === item.id || (current.name === item.name && current.address === item.address));
    if (index >= 0) prospects[index] = { ...prospects[index], ...item, selected: prospects[index].selected ?? true, sentAt: prospects[index].sentAt || null };
    else prospects.unshift(item);
  }
}

function filteredProspects() {
  const query = $('search').value.toLowerCase().trim();
  return prospects.filter((item) => {
    const text = `${item.name} ${item.city} ${item.state} ${item.category} ${item.phone}`.toLowerCase();
    const matchesSearch = !query || text.includes(query);
    const matchesFilter = activeFilter === 'all'
      || (activeFilter === 'with-site' && item.hasWebsite)
      || (activeFilter === 'without-site' && !item.hasWebsite)
      || (activeFilter === 'improve' && item.canImprove)
      || (activeFilter === 'unsent' && !item.sentAt)
      || (activeFilter === 'send-error' && item.sendStatus === 'error')
      || (activeFilter === 'sent' && Boolean(item.sentAt));
    return matchesSearch && matchesFilter;
  });
}

function render() {
  const total = prospects.length;
  $('metricTotal').textContent = total;
  $('metricWithSite').textContent = prospects.filter((p) => p.hasWebsite).length;
  $('metricWithoutSite').textContent = prospects.filter((p) => !p.hasWebsite).length;
  $('metricImprove').textContent = prospects.filter((p) => p.canImprove).length;
  renderList();
}

function renderList() {
  const list = $('prospectList');
  const items = filteredProspects();
  list.innerHTML = '';
  $('empty').classList.toggle('hidden', items.length > 0);
  for (const item of items) {
    const row = document.createElement('article');
    row.className = 'lead-card';
    row.innerHTML = `
      <label class="lead-check"><input type="checkbox" data-id="${escapeHtml(item.id)}" ${item.selected ? 'checked' : ''}></label>
      <div class="lead-main">
        <div class="lead-title-row"><strong>${escapeHtml(item.name || 'Sem nome')}</strong><span class="badge ${item.hasWebsite ? 'site' : 'no-site'}">${item.hasWebsite ? 'Com site' : 'Sem site'}</span></div>
        <div class="muted">${escapeHtml([item.city, item.state, item.category].filter(Boolean).join(' · ') || item.address || 'Localização não identificada')}</div>
        <div class="muted">${escapeHtml(item.phone || 'Telefone não identificado')}</div>
        <div class="lead-status">${escapeHtml(item.siteStatus || '')}${renderSendStatus(item)}</div>
      </div>
      <button class="icon-button" data-remove="${escapeHtml(item.id)}" title="Remover">×</button>`;
    row.querySelector('input').addEventListener('change', async (event) => {
      item.selected = event.target.checked;
      await saveProspects(prospects);
      updateSelectedCount();
    });
    row.querySelector('[data-remove]').addEventListener('click', async () => {
      prospects = prospects.filter((p) => p.id !== item.id);
      await saveProspects(prospects);
      render();
    });
    list.appendChild(row);
  }
  updateSelectedCount();
}

async function analyzeSelected() {
  const selected = prospects.filter((p) => p.selected);
  if (!selected.length) return status('Selecione pelo menos um prospect.', 'error');
  status(`Analisando ${selected.length} site(s)...`);
  let completed = 0;
  for (const item of selected) {
    if (!item.website) {
      item.hasWebsite = false;
      item.canImprove = true;
      item.siteStatus = 'Sem site';
      item.opportunity = 'Não foi encontrado site público no perfil. Oportunidade para oferecer criação de site e soluções da AB Sistemas.';
    } else {
      Object.assign(item, await analyzeWebsite(item.website));
    }
    completed += 1;
    status(`Analisados ${completed} de ${selected.length}...`);
  }
  await saveProspects(prospects);
  render();
  status('Análise concluída.', 'success');
}

async function sendSelected() {
  const selected = prospects.filter((p) => p.selected);
  if (!selected.length) return status('Selecione pelo menos um prospect.', 'error');
  setBusy($('sendSelected'), true);

  const batches = [];
  for (let i = 0; i < selected.length; i += MAX_PROSPECTS_PER_REQUEST) {
    batches.push(selected.slice(i, i + MAX_PROSPECTS_PER_REQUEST));
  }

  let totalCreated = 0;
  let totalUpdated = 0;
  let sentBatches = 0;

  try {
    for (const batch of batches) {
      status(batches.length > 1
        ? `Enviando lote ${sentBatches + 1} de ${batches.length} (${batch.length} prospect(s))...`
        : `Enviando ${batch.length} prospect(s) ao CRM...`);

      const response = await sendToCrm(batch, settings);
      const sentAt = new Date().toISOString();
      const responseMessage = response?.message || response?.data?.message || 'Enviado com sucesso.';
      batch.forEach((item) => {
        item.sentAt = sentAt;
        item.lastSendAt = sentAt;
        item.sendStatus = 'success';
        item.sendMessage = responseMessage;
        item.selected = false;
      });
      totalCreated += Number(response?.created ?? 0);
      totalUpdated += Number(response?.updated ?? 0);
      sentBatches += 1;
      await saveProspects(prospects);
      render();
    }
    status(`Envio concluído. Novos: ${totalCreated}. Atualizados: ${totalUpdated}.`, 'success');
  } catch (error) {
    const failedAt = new Date().toISOString();
    const failedBatch = batches[sentBatches] || [];
    failedBatch.forEach((item) => {
      item.lastSendAt = failedAt;
      item.sendStatus = 'error';
      item.sendMessage = error.message || 'Falha ao enviar para o CRM.';
    });
    await saveProspects(prospects);
    render();
    const remaining = batches.length - sentBatches - 1;
    const remainingNote = remaining > 0 ? ` ${remaining} lote(s) ainda não enviados — clique em enviar novamente.` : '';
    status(`Falha no lote ${sentBatches + 1} de ${batches.length}: ${error.message || 'Falha ao enviar para o CRM.'}${remainingNote}`, 'error');
  } finally {
    setBusy($('sendSelected'), false);
  }
}

async function persistSettings() {
  settings = {
    crmUrl: $('crmUrl').value.trim(),
    crmToken: $('crmToken').value.trim(),
    crmSource: $('crmSource').value.trim() || DEFAULT_SETTINGS.crmSource
  };
  await saveSettings(settings);
  status('Configurações salvas.', 'success');
}

function fillSettings() {
  $('crmUrl').value = settings.crmUrl;
  $('crmToken').value = settings.crmToken;
  $('crmSource').value = settings.crmSource;
}

function previewPayload() {
  const selected = prospects.filter((p) => p.selected);
  $('payloadPreview').textContent = JSON.stringify(buildPayload(selected, {
    crmUrl: $('crmUrl').value.trim(),
    crmToken: $('crmToken').value.trim(),
    crmSource: $('crmSource').value.trim() || DEFAULT_SETTINGS.crmSource
  }), null, 2);
}

async function clearAll() {
  if (!confirm('Remover todos os prospects salvos na extensão?')) return;
  prospects = [];
  await saveProspects(prospects);
  render();
  status('Lista limpa.', 'success');
}

async function toggleAll(event) {
  const visibleIds = new Set(filteredProspects().map((item) => item.id));
  prospects.forEach((item) => { if (visibleIds.has(item.id)) item.selected = event.target.checked; });
  await saveProspects(prospects);
  renderList();
}

function updateSelectedCount() {
  $('selectedCount').textContent = prospects.filter((p) => p.selected).length;
}

function status(message, type = '') {
  const box = $('status');
  box.textContent = message;
  box.className = `status ${type}`;
}

function setBusy(button, busy) {
  button.disabled = busy;
  button.classList.toggle('busy', busy);
}


function renderSendStatus(item) {
  if (item.sendStatus === 'error') {
    return ` · Envio com erro${item.sendMessage ? `: ${escapeHtml(item.sendMessage)}` : ''}`;
  }
  if (item.sentAt) {
    const date = new Date(item.sentAt);
    const formatted = Number.isNaN(date.getTime()) ? '' : ` em ${date.toLocaleString('pt-BR')}`;
    return ` · Enviado${formatted}`;
  }
  return ' · Pendente';
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}
