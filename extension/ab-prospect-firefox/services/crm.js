export const MAX_PROSPECTS_PER_REQUEST = 50;

export function buildPayload(prospects, settings) {
  return {
    source: settings.crmSource || 'AB Prospect - Google Maps',
    product: settings.crmProduct || 'vetoros',
    prospects: prospects.map((item) => ({
      name: item.name || '',
      address: item.address || '',
      phone: item.phone || '',
      website: item.website || '',
      hasWebsite: Boolean(item.hasWebsite),
      siteStatus: item.siteStatus || (item.hasWebsite ? 'Com site - ainda não analisado' : 'Sem site'),
      canImprove: Boolean(item.canImprove),
      opportunity: item.opportunity || '',
      mapsUrl: item.mapsUrl || '',
      city: item.city || '',
      state: item.state || '',
      category: item.category || '',
      rating: item.rating == null || item.rating === '' ? null : Number(item.rating),
      reviews: item.reviews == null || item.reviews === '' ? null : Number(item.reviews),
      capturedAt: item.capturedAt || new Date().toISOString(),
      // Opcionais (1.4.0+): o CRM também os tira da mapsUrl, então a 1.3.0 continua compatível.
      ...optionalFields(item)
    }))
  };
}

function optionalFields(item) {
  const fields = {};
  if (item.placeId) fields.placeId = item.placeId;
  if (Number.isFinite(item.latitude) && Number.isFinite(item.longitude)) {
    fields.latitude = item.latitude;
    fields.longitude = item.longitude;
  }
  if (item.websiteChecks && Object.keys(item.websiteChecks).length) fields.websiteChecks = item.websiteChecks;
  return fields;
}

export async function sendToCrm(prospects, settings) {
  if (!settings.crmUrl) throw new Error('Informe o endpoint do CRM.');
  if (!prospects.length) throw new Error('Nenhum prospect selecionado para envio.');

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  };
  if (settings.crmToken) {
    headers.Authorization = settings.crmToken.startsWith('Bearer ')
      ? settings.crmToken
      : `Bearer ${settings.crmToken}`;
  }

  const response = await fetch(settings.crmUrl, {
    method: 'POST',
    headers,
    body: JSON.stringify(buildPayload(prospects, settings))
  });

  const raw = await response.text();
  let body = null;
  try { body = raw ? JSON.parse(raw) : null; } catch { body = { message: raw }; }
  if (!response.ok) {
    throw new Error(body?.message || `Falha no CRM: HTTP ${response.status}`);
  }
  return body || { success: true };
}
