import test from 'node:test';
import assert from 'node:assert/strict';
import { mapsExtractor } from '../services/maps.js';
import { buildPayload } from '../services/crm.js';
import { mergeProspects, samePlace } from '../services/prospects.js';

const p = mapsExtractor('__parsers__');
const URL_ = 'https://www.google.com/maps/place/Oficina/data=!4m7!3m6!1s0x9519a1b2c3d4e5f6:0x1111111111111111!8m2!3d-29.1678123!4d-51.1794456!16s%2Fg%2F11abc!19sChIJOficinaExemplo0001?authuser=0';

test('reviews: parenthesized count, never the rating', () => {
  assert.equal(p.parseReviews('4,7(123)'), 123);
  assert.equal(p.parseReviews('4,7(1.234) · $$'), 1234);
  assert.equal(p.parseReviews('5,0 (4)'), 4);
  assert.equal(p.parseReviews('4,5 estrelas 1.234 Avaliações'), 1234);
  assert.equal(p.parseReviews('87 avaliações'), 87);
  assert.equal(p.parseReviews('4,7'), null);
  assert.equal(p.parseReviews('Nenhuma avaliação'), null);
});

test('card: category and address split, name and buttons ignored', () => {
  const name = 'Oficina do João';
  const r = p.parseCard({ name, lines: [name, '4,7(123)', 'Oficina mecânica ·  · R. das Flores, 100', 'Aberto ⋅ Fecha às 18:00 · (54) 3222-1111', 'Site', 'Rotas'] });
  assert.deepEqual(r, { category: 'Oficina mecânica', address: 'R. das Flores, 100', reviews: 123 });
  const r2 = p.parseCard({ name, lines: [name, '4,9(51) · Assistência técnica de celulares · Av. Brasil, 2000', 'Fechado ⋅ Abre às 09:00'] });
  assert.deepEqual(r2, { category: 'Assistência técnica de celulares', address: 'Av. Brasil, 2000', reviews: 51 });
  // Sem linha de categoria: fica vazia, nunca o nome nem botões.
  const r3 = p.parseCard({ name: 'Dr Celular', lines: ['Dr Celular', '5,0(3)', 'Site', 'Rotas', '"Ótimo atendimento"'] });
  assert.deepEqual(r3, { category: '', address: '', reviews: 3 });
  // Categoria igual ao nome é descartada.
  const r4 = p.parseCard({ name: 'Pet Shop', lines: ['Pet Shop', 'Pet Shop · Rua Sete, 7'] });
  assert.deepEqual(r4, { category: '', address: 'Rua Sete, 7', reviews: null });
  // Faixa de preço não vira categoria.
  const r5 = p.parseCard({ name: 'Bar X', lines: ['Bar X', '4,2(10) · R$ 20–40', 'Bar · Rod. BR-116, 1234'] });
  assert.equal(r5.category, 'Bar');
  assert.equal(r5.address, 'Rod. BR-116, 1234');
  // "Bar - de esquina" não é "Cidade - UF".
  assert.equal(p.isAddress('Assistência - de celulares'), false);
});

test('city/state only from "Cidade - UF"', () => {
  assert.deepEqual(p.parseCityState('R. X, 123 - Centro, Porto Alegre - RS, 90000-000'), { city: 'Porto Alegre', state: 'RS' });
  assert.deepEqual(p.parseCityState('5V5J+2F Centro, Caxias do Sul - RS'), { city: 'Caxias do Sul', state: 'RS' });
  assert.deepEqual(p.parseCityState('Caxias do Sul - RS'), { city: 'Caxias do Sul', state: 'RS' });
  assert.deepEqual(p.parseCityState('R. das Flores, 100'), { city: '', state: '' });
  assert.deepEqual(p.parseCityState('Oficina - DR Silva'), { city: '', state: '' });
  assert.deepEqual(p.parseCityState('Loja XY - AB'), { city: '', state: '' });
});

test('place identity from the Maps URL', () => {
  assert.deepEqual(p.parsePlace(URL_), { placeId: 'ChIJOficinaExemplo0001', featureId: '0x9519a1b2c3d4e5f6:0x1111111111111111', latitude: -29.1678123, longitude: -51.1794456 });
  assert.deepEqual(p.parsePlace('https://www.google.com/maps/search/x/@-29,-51,14z'), { placeId: '', featureId: '', latitude: null, longitude: null });
  assert.equal(p.parsePlace('https://www.google.com/maps?ftid=0x951C1E2A1537B161:0x8923A0DCD0D5BA3').featureId, '0x951c1e2a1537b161:0x8923a0dcd0d5ba3');
});

test('website type', () => {
  assert.equal(p.websiteType('https://oficina.com.br'), 'own');
  assert.equal(p.websiteType('https://www.instagram.com/oficina'), 'social');
  assert.equal(p.websiteType('https://linktr.ee/oficina'), 'link_bio');
  assert.equal(p.websiteType('https://wa.me/5554999999999'), 'whatsapp');
  assert.equal(p.websiteType('https://meuinstagram.com.br'), 'own');
});

test('payload keeps the 1.3.0 contract and adds optional fields only when present', () => {
  const base = { name: 'Oficina', hasWebsite: false, canImprove: true, mapsUrl: URL_ };
  const old = buildPayload([base], {}).prospects[0];
  assert.deepEqual(Object.keys(old), ['name','address','phone','website','hasWebsite','siteStatus','canImprove','opportunity','mapsUrl','city','state','category','rating','reviews','capturedAt']);
  const neu = buildPayload([{ ...base, placeId: 'ChIJOficinaExemplo0001', latitude: -29.1, longitude: -51.1, websiteChecks: { https: true } }], {}).prospects[0];
  assert.equal(neu.placeId, 'ChIJOficinaExemplo0001');
  assert.equal(neu.latitude, -29.1);
  assert.deepEqual(neu.websiteChecks, { https: true });
  const partial = buildPayload([{ ...base, latitude: null, longitude: null, websiteChecks: {} }], {}).prospects[0];
  assert.equal('latitude' in partial, false);
  assert.equal('websiteChecks' in partial, false);
});

// Formatos do Google Maps real (CRM-LEAD-02.1, 2026-09-30).
test('real Maps: card whose rating line has no count keeps the address', () => {
  const name = 'Mecânica Exemplo';
  const r = p.parseCard({ name, lines: [name, name, '4,8', 'Oficina mecânica · \ue934 · R. Augusto Adamatti, 53 Floresta E', 'Aberto 24 horas · +55 54 99999-0000', '\ue52e'] });
  assert.deepEqual(r, { category: 'Oficina mecânica', address: 'R. Augusto Adamatti, 53 Floresta E', reviews: null });
  const r2 = p.parseCard({ name, lines: [name, name, '4,5(6.862)', 'Pet Shop · \ue934 · Av. Protásio Alves, 4194', 'Aberto · Fecha 00:00 · +55 51 3333-0000'] });
  assert.deepEqual(r2, { category: 'Pet Shop', address: 'Av. Protásio Alves, 4194', reviews: 6862 });
  // Faixa de preço "acima de R$ 200" (com nbsp) não vira categoria.
  const r3 = p.parseCard({ name: 'Restaurante Exemplo', lines: ['Restaurante Exemplo', 'Restaurante Exemplo', '4,8(1.721) · +R$ 200', 'Restaurante ·  · R. Pedro Candiago, 364', 'Local elegante com culinária alemã'] });
  assert.deepEqual(r3, { category: 'Restaurante', address: 'R. Pedro Candiago, 364', reviews: 1721 });
  const r4 = p.parseCard({ name: 'Cantina', lines: ['Cantina', '4,7(1.895) · R$ 100–200', 'Italiana ·  · R. Wilma Dinnebier, 91'] });
  assert.equal(r4.category, 'Italiana');
});

test('real Maps: review count from the star label and the panel labels', () => {
  assert.equal(p.parseReviews('4,8 estrelas 454 comentários'), 454);
  assert.equal(p.parseReviews('4,6 estrelas 5.065 comentários'), 5065);
  assert.equal(p.parseReviews('2.394 avaliações'), 2394);
  assert.equal(p.parseReviews('4,8 estrelas '), null);
  assert.equal(p.parseReviews('Saiba mais sobre a divulgação legal de avaliações públicas no Google Maps'), null);
});

// Duplicata visual do popup (CRM-LEAD-02.3): URLs reais do Maps da CRM-LEAD-02.2.
// Card da lista: tem Place ID (!19s). Painel da empresa: sem Place ID, mesmo FTID (!1s0x…:0x…).
const LIST_URL = 'https://www.google.com/maps/place/Pet+Shop+Pulo+do+Gato/data=!4m7!3m6!1s0x951ea08444963095:0xc1cdb38c4cd2c5ef!8m2!3d-29.2222394!4d-51.3396292!16s%2Fg%2F11hcsfszdp!19sChIJlTCWRISgHpUR78XSTIyzzcE?authuser=0&hl=pt-BR&rclk=1';
const PANEL_URL = 'https://www.google.com/maps/place/Pet+Shop+Pulo+do+Gato/@-29.227911,-51.3859458,14z/data=!4m10!1m2!2m1!1spet+shop+em+Farroupilha+RS!3m6!1s0x951ea08444963095:0xc1cdb38c4cd2c5ef!8m2!3d-29.2222394!4d-51.3396292!15sChpwZXQgc2hvcCBlbSBGYXJyb3VwaWxoYSBSU1oc';
const OTHER_URL = 'https://www.google.com/maps/place/Pop+Dog+Petshop/data=!4m7!3m6!1s0x951c1e280e7839ab:0x63aef5838d462835!8m2!3d-29.227911!4d-51.347837!16s%2Fg%2F11b7lr0r6q!19sChIJqzl4DigeHJURNShGjYP1rmM?authuser=0';

const capture = (fields) => {
  const place = p.parsePlace(fields.mapsUrl);
  return {
    id: `id-${fields.mapsUrl.length}-${fields.name}`,
    name: '', address: '', phone: '', website: '', websiteType: '', hasWebsite: false,
    siteStatus: 'Sem site', canImprove: true, websiteChecks: null, opportunity: 'Sem site',
    placeId: place.placeId, featureId: place.featureId, latitude: place.latitude, longitude: place.longitude,
    city: '', state: '', category: '', rating: null, reviews: null,
    capturedAt: '2026-09-30T12:00:00.000Z', selected: true, sentAt: null,
    ...fields
  };
};
const fromList = capture({ name: 'Pet Shop Pulo do Gato', mapsUrl: LIST_URL, category: 'Pet shop', address: 'R. Júlio de Castilhos, 1500', rating: 4.8, reviews: 89 });
const fromPanel = capture({ name: 'Pet Shop Pulo do Gato', mapsUrl: PANEL_URL, category: 'Pet shop', address: 'R. Júlio de Castilhos, 1500 - Centro, Farroupilha - RS, 95170-000', city: 'Farroupilha', state: 'RS', phone: '+55 54 99999-0000', website: 'https://pulodogato.com.br', websiteType: 'own', hasWebsite: true, siteStatus: 'Com site - ainda não analisado', canImprove: false, opportunity: 'Possui site.', rating: 4.8, reviews: 89, capturedAt: '2026-09-30T12:05:00.000Z' });

test('A: list then panel of the same place (panel without Place ID, same FTID) is one item', () => {
  assert.equal(fromPanel.placeId, '');
  assert.equal(fromPanel.featureId, fromList.featureId);
  const result = mergeProspects(mergeProspects([], [fromList]), [fromPanel]);
  assert.equal(result.length, 1);
  const [item] = result;
  assert.equal(item.id, fromList.id);
  assert.equal(item.placeId, 'ChIJlTCWRISgHpUR78XSTIyzzcE');
  assert.equal(item.featureId, '0x951ea08444963095:0xc1cdb38c4cd2c5ef');
  assert.equal(item.mapsUrl, LIST_URL, 'a URL com Place ID é mantida');
  assert.equal(item.address, fromPanel.address);
  assert.equal(item.city, 'Farroupilha');
  assert.equal(item.state, 'RS');
  assert.equal(item.phone, '+55 54 99999-0000');
  assert.equal(item.website, 'https://pulodogato.com.br');
  assert.equal(item.hasWebsite, true);
  assert.equal(item.siteStatus, 'Com site - ainda não analisado');
  assert.equal(item.rating, 4.8);
  assert.equal(item.reviews, 89);
  assert.equal(item.latitude, -29.2222394);
  // O payload leva o Place ID da lista e a URL que o CRM lê.
  const sent = buildPayload(result, {}).prospects[0];
  assert.equal(sent.placeId, 'ChIJlTCWRISgHpUR78XSTIyzzcE');
  assert.equal(sent.mapsUrl, LIST_URL);
});

test('A: order does not matter (panel first, then the list)', () => {
  const result = mergeProspects(mergeProspects([], [fromPanel]), [fromList]);
  assert.equal(result.length, 1);
  assert.equal(result[0].placeId, 'ChIJlTCWRISgHpUR78XSTIyzzcE');
  assert.equal(result[0].mapsUrl, LIST_URL);
  // O endereço parcial da lista não substitui o completo do painel.
  assert.equal(result[0].address, fromPanel.address);
  assert.equal(result[0].city, 'Farroupilha');
  assert.equal(result[0].website, 'https://pulodogato.com.br');
});

test('B: same Place ID captured again is one item, local state kept', () => {
  const first = mergeProspects([], [fromList]);
  first[0].selected = false;
  first[0].sentAt = '2026-09-30T13:00:00.000Z';
  first[0].sendStatus = 'success';
  const again = capture({ ...fromList, id: 'outro-id', reviews: 90 });
  const result = mergeProspects(first, [again]);
  assert.equal(result.length, 1);
  assert.equal(result[0].id, fromList.id);
  assert.equal(result[0].reviews, 90);
  assert.equal(result[0].selected, false);
  assert.equal(result[0].sentAt, '2026-09-30T13:00:00.000Z');
  assert.equal(result[0].sendStatus, 'success');
});

test('C: same phone, different FTIDs stay two items', () => {
  const a = capture({ name: 'Pet Shop Pulo do Gato', mapsUrl: LIST_URL, phone: '+55 54 3261-0000' });
  const b = capture({ name: 'Pop Dog Petshop', mapsUrl: OTHER_URL, phone: '+55 54 3261-0000' });
  assert.equal(samePlace(a, b), false);
  assert.equal(mergeProspects([], [a, b]).length, 2);
  // Nem com o mesmo nome e endereço: FTIDs diferentes são lugares diferentes (filiais).
  const c = capture({ name: 'Rede X', address: 'Centro', mapsUrl: LIST_URL });
  const d = capture({ name: 'Rede X', address: 'Centro', mapsUrl: OTHER_URL });
  assert.equal(mergeProspects([], [c, d]).length, 2);
});

test('D: empty fields of a later capture never erase captured data', () => {
  const full = mergeProspects(mergeProspects([], [fromList]), [fromPanel]);
  const sparse = capture({ name: 'Pet Shop Pulo do Gato', mapsUrl: PANEL_URL, address: '', phone: '', city: '', state: '', category: '', website: '', rating: null, reviews: undefined, latitude: null, longitude: null });
  delete sparse.reviews;
  const [item] = mergeProspects(full, [sparse]);
  for (const key of ['address', 'phone', 'city', 'state', 'category', 'website', 'rating', 'reviews', 'placeId', 'latitude', 'longitude', 'mapsUrl']) {
    assert.deepEqual(item[key], full[0][key], key);
  }
  assert.equal(item.hasWebsite, true);
  assert.equal(item.siteStatus, 'Com site - ainda não analisado');
});

test('D: a recapture keeps the "Analisar sites" result of the same link', () => {
  const [analyzed] = mergeProspects([], [fromPanel]);
  Object.assign(analyzed, { siteStatus: 'Com site - pode melhorar', canImprove: true, websiteChecks: { https: true } });
  const [item] = mergeProspects([analyzed], [fromList, { ...fromPanel, id: 'x' }]);
  assert.equal(item.siteStatus, 'Com site - pode melhorar');
  assert.deepEqual(item.websiteChecks, { https: true });
  // Link novo: vale a classificação nova.
  const [changed] = mergeProspects([analyzed], [{ ...fromPanel, website: 'https://www.instagram.com/pulodogato', websiteType: 'social', hasWebsite: false, siteStatus: 'Sem site próprio' }]);
  assert.equal(changed.websiteType, 'social');
  assert.equal(changed.hasWebsite, false);
});

test('items saved before 1.4.0 (no featureId field) still match by the URL FTID', () => {
  const legacy = { ...fromList };
  delete legacy.featureId;
  delete legacy.placeId;
  assert.equal(mergeProspects([legacy], [fromPanel]).length, 1);
});

test('no Place ID nor FTID: falls back to the old rule (same id, or same name and address)', () => {
  const noId = (fields) => capture({ mapsUrl: 'https://www.google.com/maps/search/pet', ...fields });
  assert.equal(mergeProspects([], [noId({ name: 'X', address: 'Rua 1' }), noId({ name: 'X', address: 'Rua 1', id: 'y' })]).length, 1);
  assert.equal(mergeProspects([], [noId({ name: 'X', address: 'Rua 1' }), noId({ name: 'X', address: 'Rua 2', id: 'y' })]).length, 2);
});
