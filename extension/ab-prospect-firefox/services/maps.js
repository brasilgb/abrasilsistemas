const api = globalThis.browser ?? globalThis.chrome;

export async function getActiveMapsTab() {
  const [tab] = await api.tabs.query({ active: true, currentWindow: true });
  if (!tab?.id || !/^https?:/.test(tab.url || '')) throw new Error('Abra o Google Maps em uma aba HTTP/HTTPS.');
  if (!/google\.[^/]+\/maps|maps\.google\./i.test(tab.url)) throw new Error('Abra uma pesquisa no Google Maps.');
  return tab;
}

export async function extractFromMaps(mode) {
  const tab = await getActiveMapsTab();
  const result = await api.scripting.executeScript({
    target: { tabId: tab.id },
    func: mapsExtractor,
    args: [mode]
  });
  return result?.[0]?.result || [];
}

// Executada dentro da aba do Maps (scripting.executeScript serializa a função): tudo que ela usa
// precisa estar aqui dentro. Com mode === '__parsers__' devolve só as funções puras (testes).
export function mapsExtractor(mode) {
  const clean = (value = '') => String(value).replace(/\s+/g, ' ').trim();
  const uniq = (items) => [...new Set(items.filter(Boolean))];
  const phonePattern = /(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?(?:9\s*)?\d{4}[-.\s]?\d{4}/;
  const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
  const addressPattern = /(?:^|\s)(?:r\.|rua|av\.|avenida|rod\.|rodovia|estr\.|estrada|pç\.|pça\.|praça|tv\.|travessa|al\.|alameda|lgo\.|largo|beco|servidão|quadra|qd\.)(?=\s|$)|\b(?:br|ers|rs|sc|pr|sp|mg|go|ba)-\d{2,3}\b|,\s*\d+|\bs\/n\b|\d{5}-?\d{3}/i;
  const statePattern = new RegExp(` - (?:${UFS.join('|')})\\b`);
  const hoursPattern = /\b(?:aberto|fechado|fecha|abre|open|closed|opens|closes|24 horas|temporariamente|permanentemente)\b/i;

  const parseNumber = (value) => {
    const match = clean(value).replace(',', '.').match(/\d+(?:\.\d+)?/);
    return match ? Number(match[0]) : null;
  };

  // Quantidade de avaliações: o número entre parênteses ("4,7(1.234)") ou o número colado em
  // "avaliações"/"reviews". Nunca o primeiro número do texto, que é a nota (bug da 1.3.0).
  const parseReviews = (value) => {
    const text = clean(value);
    const match = text.match(/\((\d[\d.,\s]*)\)/) || text.match(/(\d[\d.,]*)\s*(?:avalia|review|opini)/i);
    if (!match) return null;
    const digits = match[1].replace(/\D/g, '');
    return digits ? Number(digits) : null;
  };

  const isAddress = (value) => addressPattern.test(clean(value)) || statePattern.test(clean(value));

  // Cidade/UF só quando o texto traz "Cidade - UF" (UF da lista oficial): o card da lista do
  // Maps normalmente não traz, o painel da empresa e o Plus Code trazem.
  const parseCityState = (value) => {
    const text = clean(value);
    const pattern = new RegExp(`(?:^|[,\\s])([^,\\d][^,]*?)\\s+-\\s+(${UFS.join('|')})(?=\\s*(?:,|$|\\d{5}))`, 'g');
    const matches = [...text.matchAll(pattern)];
    const last = matches.at(-1);
    if (!last) return { city: '', state: '' };
    const city = clean(last[1].split(',').at(-1)).replace(/^\d{5}-?\d{3}\s*/, '');
    return { city, state: last[2] };
  };

  // Tipo do link do perfil: site próprio ou só rede social / link na bio / WhatsApp.
  const websiteType = (url) => {
    let host = '';
    try { host = new URL(url).hostname.toLowerCase(); } catch { return url ? 'own' : ''; }
    const is = (domains) => domains.some((domain) => host === domain || host.endsWith(`.${domain}`));
    if (is(['instagram.com', 'instagr.am', 'facebook.com', 'fb.com', 'fb.me', 'm.me', 'tiktok.com', 'youtube.com', 'youtu.be', 'twitter.com', 'x.com', 'linkedin.com', 'pinterest.com', 'threads.net', 'kwai.com'])) return 'social';
    if (is(['linktr.ee', 'linktree.com', 'bio.link', 'bio.site', 'beacons.ai', 'lnk.bio', 'taplink.cc', 'taplink.ws', 'campsite.bio', 'linkin.bio', 'msha.ke', 'allmylinks.com', 'solo.to', 'hoo.be', 'linkbio.co', 'bento.me', 'contate.me'])) return 'link_bio';
    if (is(['wa.me', 'whatsapp.com', 'wa.link'])) return 'whatsapp';
    return 'own';
  };

  // Identidade do lugar lida da própria URL (a mesma regra do CRM, MapsPlace::fromUrl).
  const parsePlace = (url) => {
    const text = String(url || '');
    const placeId = text.match(/(?:!19s|[?&](?:query_)?place_?id=|place_id:)([A-Za-z0-9_-]{4,255})/)?.[1] || '';
    const coords = text.match(/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/);
    const latitude = coords ? Number(coords[1]) : null;
    const longitude = coords ? Number(coords[2]) : null;
    const valid = latitude !== null && Math.abs(latitude) <= 90 && Math.abs(longitude) <= 180 && !(latitude === 0 && longitude === 0);
    return { placeId, latitude: valid ? latitude : null, longitude: valid ? longitude : null };
  };

  // Card da lista de resultados: as linhas de texto do card, na ordem em que aparecem.
  // Formato típico: nome / "4,7(123)" / "Categoria · Endereço" / "Aberto ⋅ Fecha às 18:00 · (51) 3333-4444".
  const parseCard = ({ name, lines }) => {
    const cleanName = clean(name).toLowerCase();
    let category = '';
    let address = '';
    let reviewsText = '';

    for (const line of lines.map(clean).filter(Boolean)) {
      if (line.toLowerCase() === cleanName) continue;
      if (!reviewsText && /^\d[,.]\d\s*\(/.test(line)) reviewsText = line;
      if (hoursPattern.test(line) || phonePattern.test(line.replace(/^\d[,.]\d\s*\([\d.,\s]+\)/, ''))) continue;
      // Só a linha "Categoria · Endereço" (ou um endereço sozinho): botões ("Site", "Rotas") e
      // trechos de avaliação nunca viram categoria.
      if (!/[·⋅]/.test(line) && !isAddress(line)) continue;

      const parts = line
        .replace(/^\d[,.]\d\s*\([\d.,\s]+\)/, '')
        .split(/\s*[·⋅]\s*/)
        .map(clean)
        // Ícones (acessibilidade etc.), faixa de preço e partes vazias.
        .filter((part) => part && /[\p{L}\d]/u.test(part) && !/^(?:[$€£]+|R\$\s*[\d–-]+.*)$/u.test(part));

      for (const part of parts) {
        if (part.toLowerCase() === cleanName) continue;
        if (!address && isAddress(part)) address = part;
        else if (!category && !isAddress(part) && !/^\d/.test(part)) category = part;
      }
      if (category && address) break;
    }

    return { category, address, reviews: parseReviews(reviewsText) };
  };

  const parsers = { clean, parseNumber, parseReviews, parseCityState, parseCard, parsePlace, websiteType, isAddress };
  if (mode === '__parsers__') return parsers;

  const websiteFromAnchor = (root) => {
    const anchors = [...root.querySelectorAll('a[href]')];
    const ordered = [
      ...anchors.filter((a) => /authority/.test(a.getAttribute('data-item-id') || '')),
      ...anchors.filter((a) => /site|website/i.test(a.getAttribute('aria-label') || '')),
      ...anchors
    ];
    for (const anchor of ordered) {
      const href = anchor.href || '';
      if (/^https?:/i.test(href) && !/google\.|goo\.gl|maps\.app|support\.google/i.test(href)) return href;
    }
    return '';
  };

  const getTextByLabels = (root, words) => {
    const elements = [...root.querySelectorAll('[aria-label],button[data-item-id],a[data-item-id]')];
    for (const el of elements) {
      const label = clean(el.getAttribute('aria-label') || el.textContent || '');
      if (words.some((word) => label.toLowerCase().includes(word))) {
        return clean(label.replace(/^(endereço|address|telefone|phone|site|website|plus code):?\s*/i, ''));
      }
    }
    return '';
  };

  const normalizeLead = (raw) => {
    const name = clean(raw.name);
    const address = clean(raw.address);
    const phone = clean(raw.phone);
    const website = clean(raw.website);
    const mapsUrl = clean(raw.mapsUrl || location.href);
    const locationInfo = parseCityState(address);
    const fallbackLocation = raw.locationHint ? parseCityState(raw.locationHint) : { city: '', state: '' };
    const place = parsePlace(mapsUrl);
    const type = website ? websiteType(website) : '';
    const idSeed = place.placeId || `${name}|${address}|${phone}|${mapsUrl}`;
    const id = btoa(unescape(encodeURIComponent(idSeed))).replace(/=+$/g, '').slice(0, 96);
    const pending = type === 'own';
    return {
      id,
      name,
      address,
      phone,
      website,
      websiteType: type,
      // Rede social, link na bio ou WhatsApp não é site próprio.
      hasWebsite: pending,
      siteStatus: pending ? 'Com site - ainda não analisado' : (website ? 'Sem site próprio' : 'Sem site'),
      canImprove: !pending,
      websiteChecks: null,
      opportunity: pending
        ? 'Possui site. Recomenda-se analisar oportunidades de modernização e integração com a AB Sistemas.'
        : website
          ? 'O perfil no Maps aponta só para rede social, link na bio ou WhatsApp. Oportunidade para oferecer site próprio e soluções da AB Sistemas.'
          : 'Não foi encontrado site público no perfil. Oportunidade para oferecer criação de site e soluções da AB Sistemas.',
      mapsUrl,
      placeId: place.placeId,
      latitude: place.latitude,
      longitude: place.longitude,
      city: clean(raw.city || locationInfo.city || fallbackLocation.city),
      state: clean(raw.state || locationInfo.state || fallbackLocation.state),
      category: clean(raw.category).toLowerCase() === name.toLowerCase() ? '' : clean(raw.category),
      rating: raw.rating == null ? null : parseNumber(raw.rating),
      reviews: raw.reviews == null ? null : parseReviews(raw.reviews),
      capturedAt: new Date().toISOString(),
      selected: true,
      sentAt: null
    };
  };

  if (mode === 'selected') {
    const main = document.querySelector('main[role="main"]') || document.body;
    const name = clean(main.querySelector('h1')?.textContent || '');
    if (!name) return [];
    const text = clean(main.innerText || '');
    const address = getTextByLabels(main, ['endereço:', 'address:']);
    const plusCode = getTextByLabels(main, ['plus code:']);
    const phone = getTextByLabels(main, ['telefone:', 'phone:']) || clean((text.match(phonePattern) || [])[0] || '');
    const website = websiteFromAnchor(main);
    // Botão da categoria no painel do lugar; sem ele, fica vazia (nunca o nome da empresa).
    const category = clean(main.querySelector('button[jsaction*="category"]')?.textContent || '');
    const ratingLabel = clean(main.querySelector('[role="img"][aria-label*="estrela"], [role="img"][aria-label*="star"]')?.getAttribute('aria-label') || '');
    const reviewsText = clean(
      main.querySelector('[aria-label*="avaliaç"], [aria-label*="review"]')?.getAttribute('aria-label')
      || [...main.querySelectorAll('button, span')].find((el) => /\(\d[\d.]*\)|\d[\d.]*\s*(?:avaliaç|review)/i.test(clean(el.textContent)))?.textContent
      || ''
    );
    return [normalizeLead({ name, address, phone, website, category, locationHint: plusCode, mapsUrl: location.href, rating: ratingLabel, reviews: reviewsText })];
  }

  const feed = document.querySelector('[role="feed"]');
  if (!feed) return [];
  const cards = [...feed.querySelectorAll('div[role="article"], a[href*="/maps/place/"]')];
  const roots = uniq(cards.map((card) => card.closest('div[role="article"]') || card));
  const leads = [];
  for (const root of roots.slice(0, 60)) {
    const anchor = root.matches('a[href*="/maps/place/"]') ? root : root.querySelector('a[href*="/maps/place/"]');
    const name = clean(anchor?.getAttribute('aria-label') || root.querySelector('.fontHeadlineSmall')?.textContent || anchor?.textContent || '');
    if (!name) continue;
    const text = clean(root.innerText || '');
    const lines = (root.innerText || '').split(/\n/).map(clean).filter(Boolean);
    const phone = clean((text.match(phonePattern) || [])[0] || '');
    const website = websiteFromAnchor(root);
    const card = parseCard({ name, lines });
    const ratingLabel = clean(root.querySelector('[role="img"][aria-label*="estrela"], [role="img"][aria-label*="star"]')?.getAttribute('aria-label') || '');
    const reviewsLabel = clean(root.querySelector('[role="img"][aria-label*="avaliaç"], [role="img"][aria-label*="review"]')?.getAttribute('aria-label') || '');
    leads.push(normalizeLead({
      name,
      address: card.address,
      phone,
      website,
      category: card.category,
      mapsUrl: anchor?.href || location.href,
      rating: ratingLabel,
      reviews: card.reviews != null ? `(${card.reviews})` : reviewsLabel
    }));
  }
  return leads.filter((lead, index, all) => all.findIndex((item) => item.id === lead.id || (item.name === lead.name && item.address === lead.address)) === index);
}
