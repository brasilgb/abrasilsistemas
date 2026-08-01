const api = globalThis.browser ?? globalThis.chrome;

function download(name, content, type) {
  const url = URL.createObjectURL(new Blob([content], { type }));
  return api.downloads.download({ url, filename: name, saveAs: true }).finally(() => setTimeout(() => URL.revokeObjectURL(url), 1500));
}

export function exportJson(prospects) {
  return download(`ab-prospect-${Date.now()}.json`, JSON.stringify(prospects, null, 2), 'application/json');
}

export function exportCsv(prospects) {
  const fields = ['name','address','phone','website','hasWebsite','siteStatus','canImprove','opportunity','mapsUrl','city','state','category','rating','reviews','capturedAt'];
  const escape = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
  const rows = [fields.join(','), ...prospects.map((item) => fields.map((field) => escape(item[field])).join(','))];
  return download(`ab-prospect-${Date.now()}.csv`, '\uFEFF' + rows.join('\n'), 'text/csv;charset=utf-8');
}
