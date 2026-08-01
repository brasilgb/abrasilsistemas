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

function mapsExtractor(mode) {
  const clean = (value = '') => String(value).replace(/\s+/g, ' ').trim();
  const uniq = (items) => [...new Set(items.filter(Boolean))];
  const phonePattern = /(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?(?:9\s*)?\d{4}[-.\s]?\d{4}/;

  const parseNumber = (value) => {
    const match = clean(value).replace(',', '.').match(/\d+(?:\.\d+)?/);
    return match ? Number(match[0]) : null;
  };

  const parseReviews = (value) => {
    const match = clean(value).replace(/\./g, '').match(/\d+/);
    return match ? Number(match[0]) : null;
  };

  const parseCityState = (address) => {
    const value = clean(address);
    const stateMatch = value.match(/(?:-|,|\s)([A-Z]{2})(?:,|\s|$)/);
    const state = stateMatch?.[1] || '';
    let city = '';
    if (state) {
      const beforeState = value.slice(0, stateMatch.index).split(',').map(clean).filter(Boolean);
      city = beforeState.at(-1)?.replace(/^\d{5}-?\d{3}\s*/, '') || '';
    }
    return { city, state };
  };

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
        return clean(label.replace(/^(endereço|address|telefone|phone|site|website):?\s*/i, ''));
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
    const idSeed = `${name}|${address}|${phone}|${mapsUrl}`;
    const id = btoa(unescape(encodeURIComponent(idSeed))).replace(/=+$/g, '').slice(0, 96);
    return {
      id,
      name,
      address,
      phone,
      website,
      hasWebsite: Boolean(website),
      siteStatus: website ? 'Com site - ainda não analisado' : 'Sem site',
      canImprove: !website,
      opportunity: website
        ? 'Possui site. Recomenda-se analisar oportunidades de modernização e integração com a AB Sistemas.'
        : 'Não foi encontrado site público no perfil. Oportunidade para oferecer criação de site e soluções da AB Sistemas.',
      mapsUrl,
      city: clean(raw.city || locationInfo.city),
      state: clean(raw.state || locationInfo.state),
      category: clean(raw.category),
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
    const phone = getTextByLabels(main, ['telefone:', 'phone:']) || clean((text.match(phonePattern) || [])[0] || '');
    const website = websiteFromAnchor(main);
    const category = clean([...main.querySelectorAll('button')].find((el) => /assistência|informática|eletrônica|celular|computador|manutenção|reparo/i.test(clean(el.textContent)))?.textContent || '');
    const ratingLabel = clean(main.querySelector('[role="img"][aria-label*="estrela"], [role="img"][aria-label*="star"]')?.getAttribute('aria-label') || '');
    const reviewsText = clean([...main.querySelectorAll('button')].find((el) => /avaliaç|review/i.test(clean(el.textContent)))?.textContent || '');
    return [normalizeLead({ name, address, phone, website, category, mapsUrl: location.href, rating: ratingLabel, reviews: reviewsText })];
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
    const address = lines.find((line) => /rua|avenida|av\.|rodovia|estrada|praça|travessa|alameda|bairro|centro| - [A-Z]{2}\b/i.test(line)) || '';
    const category = lines.find((line) => /assistência|informática|eletrônica|celular|computador|manutenção|reparo/i.test(line)) || '';
    const ratingLabel = clean(root.querySelector('[role="img"][aria-label*="estrela"], [role="img"][aria-label*="star"]')?.getAttribute('aria-label') || '');
    const reviewsText = lines.find((line) => /\(\d+[\d.]*\)/.test(line)) || '';
    leads.push(normalizeLead({ name, address, phone, website, category, mapsUrl: anchor?.href || location.href, rating: ratingLabel, reviews: reviewsText }));
  }
  return leads.filter((lead, index, all) => all.findIndex((item) => item.id === lead.id || (item.name === lead.name && item.address === lead.address)) === index);
}
