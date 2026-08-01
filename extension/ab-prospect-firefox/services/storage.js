import { DEFAULT_SETTINGS, STORAGE_KEYS } from '../config.js';

const api = globalThis.browser ?? globalThis.chrome;

export async function loadState() {
  const data = await api.storage.local.get([STORAGE_KEYS.prospects, STORAGE_KEYS.settings]);
  return {
    prospects: Array.isArray(data[STORAGE_KEYS.prospects]) ? data[STORAGE_KEYS.prospects] : [],
    settings: { ...DEFAULT_SETTINGS, ...(data[STORAGE_KEYS.settings] || {}) }
  };
}

export async function saveProspects(prospects) {
  await api.storage.local.set({ [STORAGE_KEYS.prospects]: prospects });
}

export async function saveSettings(settings) {
  await api.storage.local.set({ [STORAGE_KEYS.settings]: settings });
}
