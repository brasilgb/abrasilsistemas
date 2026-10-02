import { mapsExtractor } from './maps.js';

const { parsePlace } = mapsExtractor('__parsers__');

// Campos derivados do link do perfil (e da análise do site): andam juntos.
const WEBSITE_FIELDS = ['website', 'websiteType', 'hasWebsite', 'siteStatus', 'canImprove', 'opportunity', 'websiteChecks'];
// Estado do item na própria extensão: nunca vem de uma captura nova.
const LOCAL_FIELDS = ['id', 'selected', 'sentAt', 'lastSendAt', 'sendStatus', 'sendMessage'];

const isEmpty = (value) => value == null
  || (typeof value === 'string' && value.trim() === '')
  || (typeof value === 'number' && !Number.isFinite(value));

const normalize = (value) => String(value ?? '').replace(/\s+/g, ' ').trim().toLowerCase();

// Place ID e FTID do item; itens salvos antes da 1.4.0 só têm a mapsUrl.
function placeIdentity(item) {
  const fromUrl = parsePlace(item.mapsUrl);
  return {
    placeId: item.placeId || fromUrl.placeId,
    featureId: String(item.featureId || fromUrl.featureId || '').toLowerCase()
  };
}

/**
 * Mesma regra do CRM (MapsPlace::samePlaceAs): mesmo lugar quando o Place ID ou o FTID coincidem;
 * lugares diferentes quando os dois têm o mesmo tipo de id e ele diverge; sem id comparável, null.
 * Telefone nunca identifica: empresas diferentes podem dividir o mesmo número.
 */
export function samePlace(a, b) {
  const left = placeIdentity(a);
  const right = placeIdentity(b);
  if ((left.placeId && left.placeId === right.placeId) || (left.featureId && left.featureId === right.featureId)) return true;
  if ((left.placeId && right.placeId) || (left.featureId && right.featureId)) return false;
  return null;
}

function findSame(prospects, item) {
  const byPlace = prospects.findIndex((current) => samePlace(current, item) === true);
  if (byPlace >= 0) return byPlace;
  // Sem Place ID/FTID comparável: a regra anterior (mesmo id, ou mesmo nome e endereço).
  return prospects.findIndex((current) => samePlace(current, item) === null
    && (current.id === item.id || (current.name === item.name && current.address === item.address)));
}

/**
 * Junta duas capturas do mesmo lugar (ex.: card da lista e depois o painel da empresa). A nova
 * enriquece a existente, mas vazio (null, '', undefined) ou algo reconhecidamente menos completo
 * nunca apaga um valor bom.
 */
export function mergeProspect(current, incoming) {
  const merged = { ...current };

  for (const [key, value] of Object.entries(incoming)) {
    if (LOCAL_FIELDS.includes(key) || WEBSITE_FIELDS.includes(key) || isEmpty(value)) continue;
    merged[key] = value;
  }

  // Endereço da lista ("R. X, 100") contido no do painel ("R. X, 100 - Centro, Cidade - UF"): fica o completo.
  if (!isEmpty(current.address) && !isEmpty(incoming.address) && normalize(current.address).includes(normalize(incoming.address))) {
    merged.address = current.address;
  }

  // A URL da lista traz o Place ID; a do painel às vezes não. Fica a que identifica melhor.
  if (!isEmpty(current.mapsUrl) && parsePlace(current.mapsUrl).placeId && !parsePlace(incoming.mapsUrl).placeId) {
    merged.mapsUrl = current.mapsUrl;
  }

  // Link novo e diferente: vale a classificação nova. Sem link, ou o mesmo link: fica o que já havia
  // (inclusive o resultado de "Analisar sites").
  if (!isEmpty(incoming.website) && normalize(incoming.website) !== normalize(current.website)) {
    for (const key of WEBSITE_FIELDS) if (key in incoming) merged[key] = incoming[key];
  }

  merged.id = current.id;
  merged.selected = current.selected ?? true;
  merged.sentAt = current.sentAt || null;
  return merged;
}

/** Adiciona as capturas à coleção do popup, sem duplicar o mesmo lugar. Devolve uma nova lista. */
export function mergeProspects(prospects, items) {
  const result = [...prospects];
  for (const item of items) {
    const index = findSame(result, item);
    if (index >= 0) result[index] = mergeProspect(result[index], item);
    else result.unshift(item);
  }
  return result;
}
