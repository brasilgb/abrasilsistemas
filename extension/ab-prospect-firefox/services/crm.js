export const MAX_PROSPECTS_PER_REQUEST = 50;

export function buildPayload(prospects, settings) {
  return {
    source: settings.crmSource || 'AB Prospect - Google Maps',
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
      capturedAt: item.capturedAt || new Date().toISOString()
    }))
  };
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
