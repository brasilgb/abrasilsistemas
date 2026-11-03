# Execução de `correio.md`: ABRASIL-SITE — VetorSales e VetorLeads

Data: 2026-11-03.

**Situação: alterações apenas no código local. Nada foi commitado, enviado, publicado nem levado à VPS.**

## 1. Resumo da implementação

- **VetorPet → VetorSales**: já estava aplicado pela execução anterior. Nesta rodada foi conferido de novo: não há mais menção ao VetorPet nas páginas públicas, no sitemap, nas metas, nem nos links. Os únicos usos restantes são técnicos (valor `vetorpet` aceito em `Lead::PRODUCTS` e importações) e o redirect 301.
- **VetorLeads incluído no portfólio**: nova página `/sistemas/vetorleads`, listada em `/sistemas` e na home (via `PublicPages`), com logotipo e captura reais do produto.
- **Apresentação e SEO**: título, descrição, og:image, alt do hero, JSON-LD e sitemap da página nova; títulos e descrições da home e de `/sistemas` atualizados para VetorOS, VetorSales e VetorLeads; termos de uso citam os três produtos.
- **Diferenciação**: a página do VetorLeads trata de prospecção e funil; a do VetorSales, de carteira, pedidos e comissões. Há uma pergunta frequente comparando os dois.

## 2. Funcionalidades já existentes e reaproveitadas

- Template `site/sistema` (hero, funcionalidades, FAQ, bloco de chamada final) e a rota `/sistemas/{product}`.
- Formulário de contato da ABrasil com `?tipo=vetorleads` (botão "Solicitar demonstração"), que grava o tipo em `Lead::CONTACT_PROJECT_TYPES`.
- Sitemap, canonical e JSON-LD gerados a partir de `PublicPages::PRODUCTS`.
- Redirect 301 de `/sistemas/vetorpet` para `/sistemas/vetorsales`, da execução anterior.

## 3. Correções executadas

- Nenhuma correção estrutural. Ajuste de texto do bloco de termos (`termos.tsx`) para citar o VetorLeads.
- Painel de SEO: `KeywordController` passa a acompanhar `page:sistema:vetorleads` (tema "produtos").
- Descrição padrão de SEO (`SeoSettings`) e da home atualizadas para citar os três produtos.

## 4. Novas páginas e serviços

- **`/sistemas/vetorleads`** (produto `vetorleads`):
  - Descrição base, conforme o correio: "Plataforma para organizar a prospecção e acompanhar oportunidades comerciais, reunindo gestão de leads, funil de vendas, atendimento por WebChat e WhatsApp e recursos de inteligência artificial para apoio comercial."
  - Benefícios e funcionalidades tirados da própria landing e do painel do VetorLeads: captura via extensão AB Prospect, score de aderência, funil Kanban com motivo de perda, follow-ups, central de atendimento (WebChat e WhatsApp) e atendimento com IA conforme a franquia.
  - Botões: "Solicitar demonstração" (formulário ABrasil) e "Conhecer o sistema" (link para https://vetorleads.com.br).
  - Não há botão de teste gratuito, porque não foi confirmado que o produto oferece teste.
- Campo opcional `secondaryCtaLabel` em `Product` (`resources/js/data/site.ts`), usado no botão da hero e no bloco final da página de produto. Os produtos existentes mantêm os rótulos anteriores.

## 5. Arquivos modificados

Código e conteúdo:
- `resources/js/data/site.ts`: produto VetorLeads, tipo `Product.secondaryCtaLabel`.
- `resources/js/data/hero-images.ts`: chave e alt do hero `vetorleads`.
- `resources/js/pages/site/sistema.tsx`: rótulo secundário configurável.
- `resources/js/pages/legal/termos.tsx`: menção ao VetorLeads e ao domínio vetorleads.com.br.
- `app/Support/PublicPages.php`: produto, alt do hero, títulos e descrições de home e `/sistemas`.
- `app/Services/Seo/SeoSettings.php`: descrição padrão.
- `app/Models/Lead.php`: `vetorleads` em `PRODUCTS` e `CONTACT_PROJECT_TYPES`.
- `app/Http/Controllers/Admin/Seo/KeywordController.php`: palavra-chave da página.
- `tests/Feature/PublicSiteTest.php`: páginas, heroes e 301 incluindo o VetorLeads.
- `docs/imagens-do-site.md`: origem das imagens do VetorLeads.

Imagens:
- Novos: `public/images/logo_vetorleads.webp`, `public/images/dashboard-vetorleads.webp`, `public/images/site/heroes/vetorleads-{640,1280,1920}.{avif,webp}` e `vetorleads-og.jpg`.
- Gerado com PHP GD a partir de `gateway/vetorleads/public/images/vetorleads-hero-green-1280.webp` (recorte 16:9 de 1280×720).
- Compilação: `public/build/` regenerado com `npm run build`.

## 6. Migrations

Nenhuma.

## 7. Testes realizados e resultados

- **Pest** (`vendor/bin/pest`): 268 testes, 1863 asserções, todos passaram.
- **TypeScript** (`tsc --noEmit`): sem erros.
- **ESLint** nos arquivos alterados: sem erros.
- **Build** (`npm run build`): concluído.
- **HTTP** (servidor local `php artisan serve`): `/`, `/sistemas`, `/sistemas/vetorleads`, `/sistemas/vetorsales`, `/termos-de-uso`, `/contato?tipo=vetorleads` e `/sitemap.xml` → 200. `/sistemas/vetorpet` → 301.
- **Navegador** (Chromium do Playwright já em cache, binário da versão 1243): `/sistemas/vetorleads` em 1440 px e 390 px, e `/sistemas` em 1440 px. Sem overflow horizontal; hero, logotipo, captura e botões renderizados corretamente. Revisão visual feita pelas capturas. Os heroes de VetorSales e VetorLeads também foram conferidos pelos testes de tamanho e formato.

## 8. Testes não executados e motivos

- **Pint e PHPStan**: não rodados nesta rodada. A execução anterior registrou avisos pré-existentes em linhas não alteradas.
- **Teste de interação completo no navegador** (formulário de contato com `?tipo=vetorleads`, navegação do menu): não executado; verificado apenas o carregamento e o layout.
- **Sem containers**: nenhum container foi criado ou executado, conforme a política do correio.

## 9. Pendências de credenciais externas

- Nenhuma credencial foi necessária.
- Search Console: após o deploy, pedir reindexação de `/sistemas/vetorleads` e reenviar o sitemap (mesmo procedimento do VetorSales).

## 10. Instruções para deploy e Search Console

- Não executado. Para publicar: commit das alterações, build e deploy na VPS, somente após autorização específica.
- Após o deploy: validar `https://abrasilsistemas.com.br/sistemas/vetorleads` e `/sitemap.xml`, e solicitar indexação no Search Console.
- Revisar no painel de SEO da produção se há metas personalizadas para `sistema:vetorleads` ou descrições padrão antigas.

## 11. Riscos e limitações conhecidos

- **Hero do VetorLeads**: a foto original tem 1280 px de largura. A variante de 1920 px é ampliada e pode aparecer um pouco suavizada.
- **Afirmações sobre o produto**: as funcionalidades foram copiadas da landing e do painel do VetorLeads. WhatsApp e atendimento com IA dependem de liberação ou franquia por empresa, e a página informa isso. Vale confirmar com a equipe do VetorLeads antes de publicar.
- **Bloco final da página**: o texto "crie sua conta e teste por conta própria" é compartilhado pelos produtos, e para o VetorLeads não há teste gratuito confirmado. O botão, porém, já leva ao site do produto.
- **Ambiente local**: o sitemap local mostra `http://localhost:8000` nas URLs porque `APP_URL` está configurado assim no ambiente. Isso não é código do site; em produção a URL deve sair correta.
- **Domínio vetorpet.com.br**: continua respondendo (fora deste site). Redirecioná-lo para vetorsales.com.br depende de outro projeto.
- **Links antigos** `/contato?tipo=vetorpet` abrem o formulário sem tipo pré-selecionado.
