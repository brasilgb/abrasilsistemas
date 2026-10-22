# Execução de `correio.md`: ABRASIL-SEO-FINAL-01, consolidação técnica e comercial do SEO

Data: 2026-10-21. Base: ABRASIL-SEO-02 (relatório anterior deste arquivo).

## 1. Resumo da implementação

- **Novo serviço:** landing page de **SEO Técnico, Performance e Otimização Digital** em `/seo-tecnico-e-otimizacao-de-sites`. Apresenta as três modalidades (SEO Essencial, Profissional e Gerenciado), sem preço e sem promessa de posição, e o CTA "Solicitar análise SEO" leva ao formulário de contato existente.
- **SEO técnico incluído:** um bloco que diz "SEO técnico incluído em todos os nossos projetos web." aparece em `/servicos` e nas páginas de sistemas sob medida, sites e landing pages. O bloco lista os 9 itens do correio, avisa que áreas privadas não são indexadas e separa esse SEO do serviço contínuo, que é contratado à parte.
- **Blog:** categoria "SEO e performance" e 5 pautas criadas como **rascunho** pelo comando `blog:seo-drafts`, com verificação de duplicidade. Nada é publicado automaticamente.
- **Search Console:** o comando `seo:check` confere se a produção está indexável, e o guia `docs/seo-deploy-e-search-console.md` cobre o deploy e o OAuth.
- **Risco real encontrado na produção (ver §11):** se `ABRASILSISTEMA_APP_URL` não estiver no `.env` da VPS, o compose define `APP_URL=http://abrasilsistema.localhost` e o site inteiro sai com `noindex`. O `seo:check` detecta esse caso.
- **Testes:** 264/264 (1.805 asserções). TypeScript, ESLint, Prettier, Pint e build ok, e validação visual feita (desktop 1440 px e celular 390 px).
- **Não feito:** deploy, push, commit e qualquer alteração na VPS ou em containers.

## 2. Funcionalidades existentes reaproveitadas

- **Infra de SEO da SEO-02 sem reescrita:** `PublicPages`, `PageRegistry`, `SitemapBuilder`, `StructuredData`, `Seo::page`, robots dinâmico, `ContentAnalyzer` e `SeoMetaStore`. A página nova entra no sitemap, no painel "Páginas e metadados", na auditoria e em "Palavras-chave" sem código novo para isso.
- **Lista `services` de `resources/js/data/site.ts`:** o serviço novo aparece sozinho no menu Soluções, na home (grupo Presença digital), no rodapé e em `/servicos`.
- **Formulário de contato e Solicitações:** `?tipo=seo` pré-seleciona o tipo, e a solicitação entra como `source=site` com `source_page`, consentimento e e-mail à equipe, como as demais. Não houve CRM novo.
- **Hero com foto e véu navy (`PageHero`/`HeroBackdrop`), `ProcessSteps`, `FaqList` e `CtaBand`.**

## 3. Auditoria da SEO-02 e correções executadas

| Item do correio | Situação |
|---|---|
| 9 seções administrativas | ok. O teste "administrador abre todas as telas de SEO" passa |
| Migrations/configurações | ok, idempotentes (`hasTable` / checagem antes de inserir) |
| Sitemap e robots dinâmicos | ok. A página nova entra no sitemap, e as áreas privadas (`/atendimento`, `/solicitacoes`, `/admin`…) ficam com `Disallow` e `X-Robots-Tag: noindex` |
| Metadados, canonical, JSON-LD | ok no HTML inicial (verificado na página nova) |
| Redirecionamentos, auditoria, análise editorial | ok (testes da SEO-02 passando) |
| Search Console / GA4 / Bing | código pronto, **não ativo**: depende de credenciais (§9) |
| Compatibilidade com a produção | risco de `APP_URL` encontrado (§11) |

**PHPStan:** os avisos nos arquivos de SEO caíram de 41 para 17, e o total do projeto, de 97 para 75. Correções sem mudança de comportamento:

- Tipo `Check` no `ContentAnalyzer` (11 métodos sem tipo de retorno).
- `array_filter` com callback que devolvia `0|1|false` passou a comparar `=== 1`.
- `HtmlInspector`: `xpath->query()` podia devolver `false`, e o `?->` não tratava esse caso.
- `SitemapBuilder::limit()` anotado como `int<1, max>`.
- Docblocks de uma linha que o PHPStan não lia (`ga4Events`, `types`, `findByPath`).
- `is_string()` redundante no `SeoChangeLogger`.

Os 17 restantes são de covariância de `Collection` e falsos positivos. No `KeywordController`, o `?->` é necessário, porque o `meta` pode ser null em execução. Nenhum indica risco.

## 4. Novas páginas e serviços

**`/seo-tecnico-e-otimizacao-de-sites`** (componente `site/seo`), seção por seção:

1. Hero com foto de painel de desempenho, ilustração de resultado de busca + auditoria, e os botões "Solicitar análise SEO" e "Ver modalidades".
2. Benefícios empresariais, com os sinais de que o site precisa de SEO ao lado.
3. O que o trabalho cobre: 6 frentes em lista numerada, sem cards.
4. Modalidades (Essencial, Profissional em destaque e Gerenciado), cada uma com seu CTA. Abaixo, a nota de que ninguém garante posição no Google.
5. Bloco "SEO técnico incluído", separando o SEO incluído do contratado.
6. Etapas da contratação: solicitação → análise inicial → proposta → correções → acompanhamento.
7. FAQ com 6 perguntas (garantia, prazo, site de terceiros, SEO × anúncios, custo, SEO nos projetos novos) e links para Sistemas sob medida, Sites empresariais e Integrações e APIs.
8. Faixa final com o CTA.

SEO da própria página:

- Título e descrição próprios, canonical, `index, follow` e og:image 1200×630 com alt.
- JSON-LD com `Service` + `OfferCatalog` das 3 modalidades, sem preço e sem avaliação, e `BreadcrumbList`.
- Intenção de busca nova, "SEO e performance", com os temas em Palavras-chave.

**Tipo de projeto "SEO Profissional"** (`seo`) no formulário e em Solicitações. Com `?tipo=seo`, o texto de ajuda pede o endereço do site.

## 5. Arquivos modificados

**Novos:**

- `resources/js/pages/site/seo.tsx`
- `resources/js/components/site/seo-included.tsx`
- `app/Console/Commands/BlogSeoDraftsCommand.php`
- `app/Console/Commands/SeoCheckCommand.php`
- `database/data/blog-seo-drafts.php` (as 5 pautas)
- `database/migrations/2026_10_21_140000_add_seo_blog_category.php`
- `public/images/site/heroes/seo-{640,1280,1920}.{avif,webp}` e `seo-og.jpg`
- `docs/seo-deploy-e-search-console.md`
- `tests/Feature/SeoServiceTest.php`

**Alterados nesta etapa:**

- `app/Support/PublicPages.php`: serviço, `SEO_SERVICE`, foto e `hasOfferCatalog`.
- `app/Http/Controllers/SiteController.php`: renderiza `site/seo` para o slug.
- `app/Models/Lead.php`: tipo `seo`.
- `app/Models/SeoMeta.php`: intenção `seo`.
- `app/Http/Controllers/Admin/Seo/KeywordController.php`.
- `app/Services/Seo/{ContentAnalyzer,HtmlInspector,SitemapBuilder,SeoSettings,SeoChangeLogger,StructuredData,PageRegistry}.php`: só anotações e as correções do §3.
- `resources/js/data/site.ts`: serviço, `includedSeo`, `seoOffer` e `seoIncluded` nos serviços web.
- `resources/js/data/hero-images.ts`.
- `resources/js/components/site/visuals.tsx`: visual `seo`.
- `resources/js/pages/site/servico.tsx` e `resources/js/pages/servicos.tsx`: bloco incluído.
- `resources/js/pages/contato.tsx`: texto de ajuda do SEO.
- `tests/Feature/PublicSiteTest.php`: exceção `site/seo` e foto `seo`.
- `docs/imagens-do-site.md`: origem e licença da foto.
- `public/build/*`, regenerado pelo build.

Os demais arquivos que aparecem no `git status` são da SEO-02, ainda não commitada. `tests/Feature/InstitutionalChatWidgetTest.php` (não rastreado) não foi criado nem alterado nesta etapa.

**Foto do hero:** Luke Chesser, Unsplash [JKUTrJ4vK00](https://unsplash.com/photos/graphs-of-performance-analytics-on-a-laptop-screen-JKUTrJ4vK00), Licença Unsplash (uso comercial livre, não é Unsplash+). Ela não repete nenhuma outra foto do site, e um teste garante isso.

## 6. Migrations

- `2026_10_21_140000_add_seo_blog_category`: cria a categoria "SEO e performance" (`seo-e-performance`) **só se** ainda não houver categoria de SEO (pelo slug `seo`/`seo-*` ou pelo nome com a palavra SEO). O `down` só remove a categoria se ela não tiver artigos.
- Foi aplicada no banco **local** (`absistemas`). Também rodei `blog:seo-drafts` localmente: 5 rascunhos, com avaliação editorial entre 85 e 95. A segunda execução não criou nada.

## 7. Testes realizados e resultados

| Verificação | Resultado |
|---|---|
| Pest (suíte completa) | **264/264**, 1.805 asserções |
| Novos (`SeoServiceTest`, 8 testes) | landing com SEO e JSON-LD sem preço ou avaliação; HTML inicial com canonical e JSON-LD; sitemap e robots; `noindex` com o bloqueio ligado; formulário `?tipo=seo` → solicitação "SEO Profissional" com origem; categoria + rascunhos sem duplicar, não públicos e fora do sitemap; bloco incluído nos serviços web; `seo:check` aprovando a produção e acusando `APP_URL` localhost e `SEO_BLOCK_INDEXING` |
| TypeScript (`tsc --noEmit`) | sem erros |
| ESLint e Prettier (arquivos alterados) | limpos |
| Pint (PHP alterado) | limpo |
| PHPStan (arquivos novos) | 0 erros |
| `npm run build` | ok |
| Validação de sitemap e JSON-LD | por teste e pelo `seo:check` |
| **Validação visual** | Chromium headless do Playwright já instalado. A primeira tentativa, pelo Chrome do MCP, falhou por falta do Chrome; a segunda funcionou. Telas conferidas: landing de SEO em 1440 e 390 px, página de Sites com o bloco incluído e contato com `?tipo=seo`. Hero legível, sem rolagem horizontal e modalidades empilhadas no celular |

## 8. Testes não executados e motivos

- **Testes com navegador interativo (cliques, envio real do formulário no browser):** só fiz capturas de tela. O envio do formulário está coberto por teste HTTP.
- **`seo:check --live`:** depende do site publicado com esta versão.
- **Rich Results Test do Google:** externo, fazer depois do deploy (o painel tem o link).
- Nenhum container foi criado, iniciado ou alterado e nada foi instalado. Usei o `php artisan serve` local, já encerrado.

## 9. Pendências de credenciais externas

- `GOOGLE_SEARCH_CONSOLE_CLIENT_ID` e `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`: cliente OAuth "Aplicativo da Web" no Google Cloud. O passo a passo está em `docs/seo-deploy-e-search-console.md` §4.
  - **Atenção:** o container lê o `.env.example`, que é versionado. As credenciais **não** devem ir para ele. O caminho seguro é mapeá-las no bloco `environment` do compose a partir do `.env` da infra, o que altera a configuração do container e depende da sua autorização.
- Verificação do Google e do Bing e IDs do GA4: pelo painel (SEO → Integrações).
- **A integração com o Search Console e o GA4 não está ativa.** Nada foi conectado nem testado com credenciais reais.

## 10. Instruções para deploy e Search Console

Detalhes em `docs/seo-deploy-e-search-console.md`.

1. **Na VPS, confirme `ABRASILSISTEMA_APP_URL=https://abrasilsistemas.com.br` no `.env` da infra** e `SEO_BLOCK_INDEXING=false`.
2. Depois do deploy autorizado, rode no container:
   - `php artisan migrate --force`
   - `php artisan optimize:clear`
   - `php artisan blog:seo-drafts --dry-run`, depois `php artisan blog:seo-drafts`
   - `php artisan seo:check` e `php artisan seo:check --live`. Os dois precisam terminar em "Pronto para indexação."
3. No Search Console (propriedade já cadastrada):
   - Envie `https://abrasilsistemas.com.br/sitemap.xml`.
   - Inspecione a home e `/seo-tecnico-e-otimizacao-de-sites` e peça a indexação.
4. Revise e publique os 5 rascunhos de SEO pelo painel, um por vez.

## 11. Riscos e limitações conhecidos

- **`APP_URL` em produção (o mais importante).** O `docker-compose.yml` da infra define `APP_URL: ${ABRASILSISTEMA_APP_URL:-http://abrasilsistema.localhost}`. Se a variável faltar no `.env` da VPS, o domínio canônico vira `abrasilsistema.localhost` e o site sai com `noindex` + `Disallow: /`.
  - O `.env.example` da infra traz o valor certo, mas não consegui ver o `.env` real da VPS.
  - O `seo:check` detecta o problema, e uma alternativa é preencher o domínio canônico no painel.
- **Foto do hero:** a tela fotografada mostra números de demonstração do fotógrafo (`479K`, `17min`). Eles não são resultados da ABrasil e ficam atenuados pelo véu navy, mas são legíveis no desktop. Está registrado em `docs/imagens-do-site.md`, com o passo a passo para trocar a foto se incomodar.
- **Duplicidade das pautas em produção:** só a execução lá confirma, porque o banco local não tinha artigos. O comando compara slug, título parecido (≥ 70%) e palavra-chave principal, e pula o que coincidir.
- **"Sites empresariais":** troquei um dos links relacionados (Automação → SEO e performance).
- **Indexação real e posições:** só o Search Console confirma, e nenhum texto do site promete posição.
- **PHPStan:** restam 75 avisos no projeto (17 nos arquivos de SEO, sem risco de execução).

**Não houve commit, push, deploy nem alteração na VPS ou em containers.**
