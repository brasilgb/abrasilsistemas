export async function analyzeWebsite(url) {
  const result = {
    hasWebsite: true,
    siteStatus: 'Com site',
    canImprove: false,
    websiteChecks: {},
    opportunity: 'Site encontrado. Avaliar integrações e oportunidades comerciais.'
  };

  try {
    const response = await fetch(url, { redirect: 'follow', credentials: 'omit' });
    const html = await response.text();
    const lower = html.toLowerCase();
    const checks = {
      accessible: response.ok,
      https: response.url.startsWith('https://'),
      viewport: /<meta[^>]+name=["']viewport["']/i.test(html),
      metaDescription: /<meta[^>]+name=["']description["'][^>]+content=/i.test(html) || /<meta[^>]+content=["'][^"']+["'][^>]+name=["']description["']/i.test(html),
      whatsapp: /wa\.me|api\.whatsapp\.com|whatsapp\.com\/send/i.test(html),
      contactForm: /<form\b/i.test(html),
      clientArea: /área do cliente|area do cliente|portal do cliente|consulta.{0,30}ordem de serviço|acompanhar.{0,30}reparo/i.test(lower),
      quote: /solicitar orçamento|pedir orçamento|orçamento online|orcamento online/i.test(lower)
    };

    const issues = [];
    if (!checks.accessible) issues.push('o site não respondeu normalmente');
    if (!checks.https) issues.push('não utiliza HTTPS');
    if (!checks.viewport) issues.push('não foi encontrada configuração responsiva básica');
    if (!checks.metaDescription) issues.push('não foi encontrada descrição SEO básica');
    if (!checks.whatsapp) issues.push('não foi encontrado acesso público ao WhatsApp');
    if (!checks.contactForm && !checks.quote) issues.push('não foi encontrado formulário ou solicitação de orçamento');
    if (!checks.clientArea) issues.push('não foi encontrada publicamente área do cliente ou consulta de ordem de serviço');

    result.websiteChecks = checks;
    result.canImprove = issues.length > 0;
    result.siteStatus = issues.length ? 'Com site - pode melhorar' : 'Com site - básico OK';
    result.opportunity = issues.length
      ? `Possui site, mas ${issues.join('; ')}. Há oportunidade de melhoria e integração com a AB Sistemas.`
      : 'Site encontrado com os itens básicos. Avaliar comercialmente integrações, atendimento e recursos do VetorOS.';
  } catch {
    result.canImprove = true;
    result.siteStatus = 'Com site - não foi possível validar';
    result.opportunity = 'O perfil informa um site, mas ele não pôde ser validado pela extensão. Recomenda-se verificação manual.';
  }

  return result;
}
