# Execução de `correio.md`: ABRASIL-WEB-02, separação de responsabilidades e atendimento WhatsApp

Data: 2026-10-14.

## Resumo

**Auditoria feita. O módulo de Atendimento WhatsApp foi implementado e testado localmente. A retirada do CRM ficou só como plano e aguarda sua decisão.** Não houve push, deploy nem commit. Nada foi alterado no WAHA, no n8n, no `docker-compose.yml` nem no banco de produção. Nenhum dado de leads ou conversas foi excluído.

- **Implementado (só acrescenta, sem risco aos dados):** caixa de entrada em `/atendimento` com conversas à esquerda, mensagens no centro, contato à direita e campo de resposta; não lidas, busca, status de entrega, página de origem e atualização a cada 5 s. O recebimento usa um webhook direto do WAHA, assinado por HMAC e filtrado pela sessão do WhatsApp da empresa; o envio sai pelo WAHA. São 2 tabelas novas, com migration reversível.
- **Não implementado de propósito:** a remoção das telas de CRM (prospecção, funil, importação, follow-up), porque ela muda fluxos que hoje podem estar sendo usados pelo n8n e pela extensão. Também não configurei o webhook na sessão do WAHA de produção (o correio exige validação). O plano para os dois está nas seções 6 e 7.
- **Testes:** 229/229 (15 novos do atendimento + 214 existentes), build ok, TypeScript sem erros no código do projeto, ESLint, Prettier e Pint limpos nos arquivos alterados.

> Observação: o working tree já continha a entrega anterior (ABRASIL-WEB-01, sem commit). Ela foi preservada, e esta etapa foi construída em cima dela.

## 1. Auditoria: o que já existe e funciona

| Área | Peças | Situação |
|---|---|---|
| **Envio de WhatsApp para lead** | `LeadWhatsappController` → `LeadWhatsappService` → webhook n8n (`crm/send-whatsapp`, Header Auth) → WAHA | Funciona (deploy validado em 24/09). Só envia; não recebe |
| **Registro do envio** | `POST /api/prospects/{lead}/whatsapp/log` (n8n → Laravel), grava em `lead_activities` (`type=whatsapp`) | Funciona |
| **ACK** | `PATCH /api/whatsapp/messages/{id}/status` (n8n → Laravel), regra de progressão em `LeadActivity` | Funciona |
| **WhatsApp da empresa** | `CompanyWhatsappSettings` (tabela `settings`: número, provider, sessão) | Funciona. Sessão configurada em produção: `vetoros1-1` |
| **Conexão com o WAHA** | `WahaService` + `CompanyWhatsappConnection`: status, QR Code, conectar, desconectar (Configurações → Leads) | Funciona; a API key fica só no backend |
| **Recebimento de mensagens** | — | **Não existia neste projeto.** O VetorLeads tem (`WhatsappInboundController`, via n8n, gravando em `lead_activities` com `direction`) |
| **CRM comercial** | Dashboard comercial, `/leads` (lista, Kanban, tarefas, métricas, importação CSV, ações em massa, **"limpar todos"**), atividades, score, motivo de perda, API de importação da extensão AB Prospect, extensão Firefox (`extension/`, `public/files/*.xpi`), templates de mensagem | Funciona, mas é responsabilidade do VetorLeads pelo correio |
| **Formulário de contato** | `ContactController` → `Lead` (`source=site`), deduplicação, e-mail para a equipe | Funciona |

**Infraestrutura (`infra-abrasil`):** há um único WAHA (`waha:3000`, engine NOWEB, X-Api-Key) compartilhado por `abrasilsistema` e `ab-prospect` (VetorLeads); o `docker-compose.yml` dá aos dois o mesmo `WAHA_BASE_URL`/`WAHA_API_KEY`. O n8n tem os workflows de envio, log e ACK. Pelos relatórios anteriores, **os dois projetos usam a mesma sessão `vetoros1-1`**, e o VetorLeads recebe as mensagens dessa sessão via n8n. Não consultei o WAHA nem o n8n nesta etapa: não há Docker neste ambiente, e o correio pede para não mexer em produção sem validação.

## 2. O que precisa ser mantido

- `CompanyWhatsappSettings`, `WahaService`, `CompanyWhatsappConnection` e a tela de conexão (QR Code). Viraram a base do atendimento.
- Tabelas `leads` e `lead_activities`, com todo o histórico, inclusive as mensagens de WhatsApp já registradas.
- Formulário de contato → registro da solicitação + e-mail para a equipe.
- Os endpoints de log e ACK do n8n, até confirmar no n8n que nenhum workflow ativo aponta para `abrasilsistemas.com.br` (seção 7).

## 3. O que pode sair do site sem perda de dados

Remover telas e rotas **não apaga** linhas do banco: `leads` e `lead_activities` ficam intactas e podem ser exportadas ou migradas para o VetorLeads.

| Pode sair do site | Destino | Cuidado antes de remover |
|---|---|---|
| Dashboard comercial (`/dashboard`: funil, prioridades, métricas) | VetorLeads | O admin precisa de uma tela inicial (sugestão: `/atendimento`) |
| Prospecção (`/leads`: Kanban, tarefas, métricas, score, motivo de perda, ações em massa) | VetorLeads | Manter uma lista simples de **solicitações do site** (os leads `source=site`) |
| **`DELETE /leads/clear`** (apaga todos os leads) | — | **Recomendo remover já:** é destrutivo e não tem função no site institucional |
| Importação CSV e `POST /api/prospects/import` (extensão AB Prospect) | VetorLeads | Confirmar que a extensão instalada aponta para o VetorLeads |
| Extensão Firefox (`extension/`, `public/files/ab-prospect-*.xpi`) | VetorLeads | O link público do `.xpi` deixa de existir |
| Envio para lead via n8n (`LeadWhatsappController`/`Service`) e templates de mensagem | VetorLeads | Substituído pela resposta direta no atendimento |
| `api/prospects/{lead}/whatsapp/log` e `api/whatsapp/messages/{id}/status` | VetorLeads | **Só depois** de conferir os workflows do n8n |
| Opções comerciais do `Lead` (status do funil, score, tarefas) | VetorLeads | As colunas continuam no banco; só deixam de aparecer |

Migração de dados: se o histórico comercial deste banco precisar ir para o VetorLeads, é uma exportação `leads` + `lead_activities` → importação no VetorLeads (que agora é multi-tenant). Fica para uma tarefa própria, com conferência de duplicados.

## 4. O que faltava para receber e responder (e foi feito)

| Faltava | Solução |
|---|---|
| Entrada de eventos do WAHA | `POST /api/whatsapp/waha/webhook`, que recebe `message`, `message.any` e `message.ack` direto do WAHA, sem n8n. Autenticado por **HMAC-SHA512** (`X-Webhook-Hmac`, recurso nativo do webhook do WAHA). Sem `WAHA_WEBHOOK_HMAC_KEY`, responde 503 (desligado) |
| Onde guardar conversas | Tabelas `whatsapp_conversations` e `whatsapp_messages` (seção 5), separadas do histórico comercial |
| Enviar resposta | `WahaService::sendText()` → `POST /api/sendText` (sessão + chatId + texto, X-Api-Key só no servidor) |
| Mensagens enviadas pelo celular | `message.any` com `fromMe` é gravado como mensagem enviada (já lida) |
| Status | `message.ack` atualiza o status com a mesma regra de progressão do CRM (extraída para `LeadActivity::messageStatusCanAdvance`, sem duplicar lógica) |
| Tempo real | Polling Inertia (`usePoll`, 5 s) só na tela do atendimento. Não precisa de WebSocket nem de infraestrutura nova |
| Página de origem | Os links de WhatsApp do site (botão flutuante, rodapé, home, contato) acrescentam `Página: /caminho` à mensagem pré-preenchida, e o atendimento guarda essa página na primeira mensagem do contato |

## 5. Implementação

### Modelo de dados (migration `2026_10_14_120000_create_whatsapp_conversations_tables`)

- `whatsapp_conversations`: `session`, `chat_id` (JID), `phone`, `name` (nome no WhatsApp), `lead_id` (nullable, `nullOnDelete`), `source_page`, `last_message_at`, `last_message_preview`. UNIQUE `(session, chat_id)`.
- `whatsapp_messages`: conversa, `direction` (`inbound`/`outbound`), `body`, `has_media`, `provider_message_id` **UNIQUE**, `status` (ACK), `sent_by_user_id`, `sent_at`, `read_at`.
- A migration só cria tabelas; o `down()` as remove. Validei up → down → up em SQLite descartável.

### Regras

- **Isolamento:** só entram eventos cuja `session` é a sessão do WhatsApp da empresa (Configurações → Leads). Eventos de outras sessões do WAHA compartilhado respondem `200 {reason: session_mismatch}` e não gravam nada. A tela e o envio também se limitam a essa sessão: conversa de outra sessão dá 404.
- **Idempotência:** o WAHA manda `message` e `message.any` para a mesma mensagem. O índice UNIQUE em `provider_message_id` garante uma única gravação, inclusive com requisições simultâneas.
- **Ids do WhatsApp:** o webhook manda `true_5551…@c.us_3EB0…` e o `sendText` do NOWEB devolve `3EB0…`. Gravamos o trecho final, então a resposta enviada pelo painel não é duplicada quando o eco chega pelo webhook. Isso está testado.
- **Ignorados:** grupos (`@g.us`), `status@broadcast`, canais e mensagens vazias. Mídia é gravada com o aviso "abra no WhatsApp" (o download de mídia não foi implementado).
- **Identificação:** nome no WhatsApp (`notifyName`/`pushName`), telefone formatado e vínculo automático com uma solicitação existente quando o telefone bate (com ou sem 55 e com ou sem o nono dígito). Contatos `@lid` (sem telefone) aparecem pelo identificador.
- **Não lidas:** são as mensagens recebidas com `read_at` nulo. Abrir a conversa marca como lidas, e o título da aba mostra `(N) Atendimento`.

### Arquivos criados

| Arquivo | Papel |
|---|---|
| `database/migrations/2026_10_14_120000_create_whatsapp_conversations_tables.php` | Tabelas do atendimento |
| `app/Models/WhatsappConversation.php`, `app/Models/WhatsappMessage.php` | Models, escopos de sessão e busca, não lidas, nome de exibição |
| `app/Services/WhatsappInbox.php` | Processa eventos, envia respostas, marca como lidas, identifica contato e origem |
| `app/Http/Controllers/Api/WahaWebhookController.php` | Webhook HMAC |
| `app/Http/Controllers/WhatsappInboxController.php` | Tela (`index`) e resposta (`reply`, throttle 30/min) |
| `resources/js/pages/atendimento/index.tsx` | Interface de 3 colunas, responsiva |
| `tests/Feature/WhatsappInboxTest.php` | 15 testes |

### Arquivos alterados

| Arquivo | Mudança |
|---|---|
| `app/Services/WahaService.php` | `sendText()` |
| `app/Models/LeadActivity.php` | Regra de ACK extraída para `messageStatusCanAdvance()`, com o mesmo comportamento |
| `config/services.php`, `.env.example` | `WAHA_WEBHOOK_HMAC_KEY` (documentados também `WAHA_BASE_URL`/`WAHA_API_KEY`) |
| `routes/web.php` | `GET /atendimento`, `POST /atendimento/{conversation}/mensagens` (auth + verified + admin) |
| `routes/api.php` | `POST /api/whatsapp/waha/webhook` (throttle 600/min) |
| `resources/js/components/app-sidebar.tsx` | Item "Atendimento" |
| `resources/js/lib/contact.ts` | `useSiteWhatsappUrl()`: link de WhatsApp com a página de origem |
| `whatsapp-float.tsx`, `public-footer.tsx`, `welcome.tsx`, `contato.tsx` | Usam `useSiteWhatsappUrl()` |

### Interface (`/atendimento`)

- **Desktop (≥1280 px):** conversas à esquerda (busca, horário, prévia, contador verde de não lidas), mensagens no centro (recebidas em branco, enviadas em verde com autor, hora e status Enviando/Enviada/Entregue/Lida), contato à direita (nome no WhatsApp, telefone, página de origem, primeiro contato e solicitação vinculada).
- **Tablet (768–1279 px):** lista + mensagens; o painel de contato some.
- **Celular:** a lista ocupa a tela; ao abrir uma conversa, ela ocupa a tela com botão de voltar.
- **Aviso no topo** quando falta a sessão ou a chave do webhook.
- Enter envia, Shift+Enter quebra linha. Erros do WAHA aparecem abaixo do campo.

Conferi a tela em navegador headless com dados fictícios, numa cópia isolada com SQLite descartável (1440 px e 390 px).

## 6. Como manter o WAHA compartilhado e isolar o atendimento

Nada disso foi aplicado. São os passos para quando você validar:

1. **Decidir a sessão do atendimento.** Hoje `vetoros1-1` é usada pelo site **e** pelo VetorLeads. Com a mesma sessão, as mesmas mensagens chegam aos dois sistemas, o que contraria o isolamento pedido. Recomendo uma **sessão nova no mesmo WAHA**, com o número institucional de atendimento:
   - criar a sessão no WAHA (ex.: `abrasil-atendimento`) e ler o QR Code pela tela já existente em Configurações → Leads;
   - salvar essa sessão no WhatsApp da empresa do site;
   - **confirmar que o número exibido no site** (`CONTACT_WHATSAPP_NUMBER`, hoje `(51) 99893-1325`) é o número dessa sessão. Senão, as mensagens de quem clica no site não chegam ao atendimento.
2. **Configurar o webhook da sessão no WAHA:**
   - URL: `https://abrasilsistemas.com.br/api/whatsapp/waha/webhook` (ou `http://nginx/...` com `Host: abrasilsistemas.com.br` pela rede interna);
   - eventos: `message`, `message.any`, `message.ack`;
   - `hmac.key`: um segredo novo, igual ao `WAHA_WEBHOOK_HMAC_KEY` do container `abrasilsistema` (acrescentar ao `docker-compose.yml`/`.env` do infra-abrasil).
   - ⚠️ O `PUT /api/sessions/{s}` do WAHA **substitui** a lista de webhooks. Se a sessão escolhida já tiver webhooks (ex.: os do n8n), inclua-os no mesmo PUT.
3. **Deploy:** build + `php artisan migrate` (só cria as 2 tabelas).
4. **Validação:** enviar uma mensagem de um celular de teste para o número do atendimento, conferir que aparece em `/atendimento`, responder e conferir o ACK.

## 7. Plano de retirada do CRM (aguarda sua decisão)

Na ordem, cada passo com teste e sem apagar dados:

1. **Agora (baixo risco):** remover `DELETE /leads/clear`.
2. **Conferir no n8n** os workflows "CRM ABrasil - Enviar WhatsApp", log e ACK: para qual host apontam (site ou VetorLeads). Conferir também a URL configurada na extensão AB Prospect.
3. **Transformar `/leads` em "Solicitações"**: lista simples das solicitações do formulário do site (nome, empresa, contato, tipo de projeto, mensagem, data, vínculo com a conversa), sem Kanban, score, tarefas, importação ou ações em massa.
4. **Remover do site:** dashboard comercial, importação CSV/API, extensão e `.xpi`, envio via n8n, templates e endpoints de log/ACK (se o passo 2 confirmar que não são usados), e mudar a tela inicial do admin para `/atendimento`.
5. **Opcional:** exportar o histórico comercial para o VetorLeads.

## 8. Testes

Ambiente: PHP 8.4 local com o SQLite em memória do `phpunit.xml`, `Http::fake()` + `Http::preventStrayRequests()`. Nenhuma chamada real ao WAHA ou ao n8n. Suíte completa numa cópia na scratchpad sem `public/hot` (com o `npm run dev` rodando, o Inertia tenta SSR no Vite e os testes que bloqueiam HTTP externo falham; isso já acontecia antes desta tarefa).

| Verificação | Resultado |
|---|---|
| Suíte completa | ✅ **229/229** (1.203 asserções) |
| `WhatsappInboxTest` (15) | ✅ webhook desligado sem chave (503); assinatura ausente ou errada (401); mensagem recebida cria conversa com nome, telefone, página de origem, vínculo com solicitação e não lida; `message` + `message.any` gravam uma vez; outra sessão é ignorada; grupos, status e mensagens vazias ignorados; mídia com aviso; enviada pelo celular gravada como enviada e lida; ACK avança e não regride; a tela lista só a sessão configurada e marca como lida ao abrir; conversa de outra sessão não abre; busca por nome e por telefone formatado; resposta enviada ao WAHA com sessão, chatId, texto e X-Api-Key, sem duplicar o eco do webhook e com ACK aplicado; falha do WAHA mostra erro e não grava; resposta em conversa de outra sessão dá 404; acesso só para admin |
| Migration up → down → up (SQLite) | ✅ |
| `npm run build` | ✅ |
| `tsc --noEmit` | ✅ 0 erros no código do projeto (continuam os 21 erros dos arquivos gerados pelo Wayfinder × Laravel 13.36, já registrados na ABRASIL-WEB-01) |
| ESLint, Prettier, Pint nos arquivos alterados | ✅ |
| Navegador headless (1440 e 390 px) | ✅ lista, conversa, status, painel de contato, contato sem nome com telefone formatado, layout de celular |
| **MySQL 8.4** | ⚠️ não executado (sem Docker neste ambiente). A migration usa só tipos padrão; vale rodar no staging antes da produção |

## 9. Pendências

1. Escolher e criar a sessão de atendimento e confirmar o número público (seção 6, passo 1).
2. Configurar o webhook com HMAC na sessão e a variável no `docker-compose.yml` (seção 6, passo 2).
3. Decidir a retirada do CRM (seção 7). **O passo 1 foi feito com sua autorização (14/10):** `DELETE /leads/clear` saiu da rota, do `LeadController::clear()`, do botão "Limpar prospects" com o diálogo de confirmação em `/leads` e do teste correspondente. Nenhum dado foi apagado. Suíte depois da remoção: 228/228. Os demais passos continuam aguardando.
4. Ainda não implementados: mídia (download e exibição), envio de "visto" ao WhatsApp, notificação no navegador, atribuição de conversa a atendente e respostas rápidas.
5. Rodar a migration em MySQL/staging antes da produção.
6. Commit, push e deploy aguardam sua autorização.

---

# Execução de `correio.md`: ABRASIL-WEB-01, auditoria e reposicionamento do site

Data: 2026-10-14.

## Resumo

**Implementado e testado localmente. Nada foi publicado: não houve deploy, push nem commit.** O site institucional foi reestruturado para apresentar a ABrasil Sistemas como empresa de tecnologia e software: uma home nova, uma área de **Sistemas prontos** com página própria por produto, **6 páginas de serviço** geradas a partir de um catálogo central, e as páginas Soluções, Sobre e Contato reescritas. O SEO técnico foi corrigido: as meta tags estavam duplicadas, o título repetia a marca, faltava JSON-LD no HTML inicial e a fonte não carregava. A logo de 797 KB foi trocada por uma versão de 866 bytes.

O CRM, os leads, o blog, o painel administrativo e as integrações de WhatsApp/n8n não foram alterados, exceto pela nova opção "Automação" no campo `product` do lead. Não há migration.

Testes: **214/214 passando** (com build de produção numa cópia isolada), TypeScript sem erros no código novo, ESLint e Pint limpos nos arquivos alterados.

## 1. Diagnóstico (auditoria)

### Estrutura anterior

| Rota | Página | Situação encontrada |
|---|---|---|
| `/` | `welcome.tsx` (1.123 linhas) | Carrossel automático de 3 slides (Sob medida / VetorOS / VetorPet) como hero. Cabeçalho próprio, diferente do resto do site. A empresa não era apresentada: a página só falava dos produtos |
| `/produtos` | `produtos.tsx` | VetorOS, VetorPet e "Sob medida" no mesmo formato de card |
| `/servicos` | `servicos.tsx` | 6 serviços em âncoras de uma única página, com 4 tópicos genéricos cada. Nenhum serviço tinha URL própria indexável |
| `/desenvolvimento-de-sites-para-empresas` | `company-websites.tsx` | Única página de serviço com URL própria |
| `/sobre` | `sobre.tsx` | 1 parágrafo e 4 cards |
| `/contato` | `contato.tsx` | Campo "Serviço de interesse" com as chaves do CRM; sem "Automação" |
| legais, blog, 404 | — | Funcionais |

Componentes: `SecondaryPageHeader` (com a barra "← Site principal" em toda página interna), `NavProductsMenu`, `NavServicesMenu`, `PublicFooter`, `PublicBrand`, `WhatsAppFloat`, `CookieConsent`.

### Problemas encontrados

**SEO técnico**
- **Meta tags duplicadas.** O `app.blade.php` gerava description, canonical e og:* no servidor, e cada página repetia as mesmas tags no `<Head>` do React. O Inertia v3 só substitui tags marcadas com `data-inertia`, então depois do carregamento havia **duas** descriptions e **dois** canonicals por página.
- **Título com a marca repetida.** O `title` do `app.tsx` acrescentava " - AB Sistemas" a títulos que já terminavam em "| ABrasil Sistemas".
- **JSON-LD só no JavaScript.** O schema.org existia apenas no `<Head>` do cliente, sem SSR, e não aparecia no HTML inicial.
- Sem `og:url`. Organization sem `@id`. `provider.url: '/'` relativo nos `Service`.
- Os serviços não tinham URL própria (eram âncoras `/servicos#automacao`), o que impedia ranquear para "automação de processos", "integração de sistemas" etc.
- SEO declarado duas vezes, em `routes/web.php` e no `<Head>` de cada página, com textos que já divergiam entre si.

**Performance**
- `logo_ab.png` com **797 KB** (1254×1254) exibida em 44 px em todas as páginas.
- A fonte "Instrument Sans" estava declarada no CSS mas **nunca era carregada** (caía na fonte do sistema).
- Imagens sem `width`/`height` (CLS) e sem `loading="lazy"`.

**Posicionamento e conteúdo**
- O hero em carrossel de 7 s alternava três mensagens. Ninguém lia a mensagem principal, e "Do briefing ao ar em semanas" posicionava a empresa como fornecedora rápida de sites.
- A home falava de "criar conta grátis" e "teste por 14 dias" antes de dizer o que a empresa é.
- Automação estava descrita como "sempre dentro de um projeto de software", secundária, quando o correio pede que seja uma das áreas principais.
- Não havia seção de tecnologias nem de processo de trabalho (só a "jornada" de cadastro no SaaS).

**Visual**
- Aparência de template: `font-black` com `tracking-[-0.075em]`, blobs de blur cyan/sky, grade de fundo, pílulas com ícone `Sparkles`, botões neon com sombra colorida, fundos alternados ciano/âmbar/violeta por seção e muitos cards iguais com ícone.
- Três cabeçalhos diferentes (home, páginas internas, blog).

**Responsividade:** funcional, mas o hero em carrossel mudava de altura a cada slide, e o menu móvel repetia a barra "Site principal".

### Ecossistema auditado (para não inventar nada)

| Projeto | Status | Uso no site |
|---|---|---|
| VetorOS (`gateway/vetoros`) | Produto em operação. README: OS, orçamentos, portal público do cliente, follow-ups, estoque, vendas, caixa, indicadores | Página `/sistemas/vetoros` |
| VetorPet (`vetorpet.com.br`) | Produto em operação; o código não está neste ambiente. Funcionalidades tiradas do conteúdo já publicado | Página `/sistemas/vetorpet` |
| CRM/AB Prospect, n8n, WAHA | Ferramentas internas | Citadas apenas como experiência ("na nossa própria operação usamos n8n e WhatsApp integrados ao CRM") |
| Desgarrados | Projeto de terceiro | Não publicado |
| `expo/solar-app`, `portal-solar`, `node-next/order-js` | Projetos locais sem confirmação de cliente/autorização | Não publicados; serviram só para confirmar React Native/Expo |

**Tecnologias confirmadas e publicadas:** PHP, Laravel, React, TypeScript, Inertia.js, Tailwind CSS, React Native, Expo, Android, MySQL, Redis, SQLite, APIs REST, webhooks, filas, n8n, WhatsApp, Linux, Docker, Nginx, HTTPS/Let's Encrypt.
**Não confirmadas e não publicadas:** Python, FastAPI, Next.js, Node.js (como backend), PostgreSQL, "serviços em nuvem" específicos.

## 2. Estrutura proposta e implementada

```
/                                        Home institucional
/servicos                                Soluções (hub)
  /desenvolvimento-de-sistemas           Sistemas sob medida
  /automacao-de-processos                Automação de processos
  /integracao-de-sistemas-e-apis         Integrações e APIs
  /desenvolvimento-de-aplicativos        Aplicativos
  /desenvolvimento-de-sites-para-empresas Sites empresariais (URL antiga mantida)
  /criacao-de-landing-pages              Landing Pages
/sistemas                                Sistemas prontos (portfólio de produtos)
  /sistemas/vetoros
  /sistemas/vetorpet
/sobre  /contato  /blog  /politica-de-*  /termos-de-uso
/produtos  → 301 → /sistemas
```

**Navegação:** Soluções (dropdown com os 6 serviços e um resumo de cada) · Sistemas prontos · Sobre · Blog · Contato · Entrar · **[Falar sobre um projeto]**. O mesmo cabeçalho vale para todas as páginas institucionais e legais.

**Home (ordem do correio):** hero → quem somos → sistemas prontos → soluções (Software e processos / Presença digital) → como trabalhamos → tecnologia → projetos (só aparece se houver itens no portfólio ou cases) → depoimentos (se houver) → blog (se houver) → CTA empresarial com contato.

**Página de serviço (template único):** hero com ilustração própria → "O ponto de partida" (problemas) → "O que entregamos" → "O que muda na prática" + diagramas de fluxo → "Como funciona" (processo; Sistemas sob medida usa o fluxo de 7 etapas do correio) → FAQ + soluções relacionadas → CTA com o tipo de projeto pré-selecionado.

**Página de produto:** segmento, problema, antes/depois, funcionalidades, plataformas, modelo de implantação, integrações, FAQ, CTAs "Solicitar demonstração" e "Criar conta e testar grátis". Na listagem, "Conhecer solução" e "Solicitar demonstração".

**Inteligência Artificial:** avaliei e **não criei página própria**. Não encontrei projeto de IA em produção no ecossistema. A IA aparece só como um item da Automação ("IA aplicada ao processo… sempre conectada ao fluxo e com revisão humana"). Ver pendências.

**Cases:** o tipo `CaseStudy` (Problema → Solução → Tecnologia → Resultado) e a renderização na home já estão prontos. A lista `caseStudies` está **vazia** de propósito: não há case autorizado e o correio proíbe resultados inventados.

## 3. Direção visual

- **Paleta:** navy `#0b1220` nas seções escuras, branco e `slate-50` nas claras, **um único acento** (`blue-700`). As cores de VetorOS (sky) e VetorPet (violet) aparecem só no segmento de cada produto.
- **Tipografia:** Instrument Sans, agora carregada de fato (`fonts.bunny.net`, sem cookies). Títulos em `font-semibold` e `tracking-tight`, no lugar de `font-black` com tracking extremo.
- **Removidos:** blobs de blur, grade de fundo, pílulas com `Sparkles`, botões neon, sombras coloridas, `hover:-translate-y`, carrossel e fundos coloridos alternados.
- **Componentes:** botões `rounded-md` em 4 variantes; grades de células separadas por linha de 1 px (ex.: `bg-slate-200` + `gap-px`) e listas editoriais com divisórias, no lugar de cards iguais com ícone; numeração 01–06 no processo.
- **Imagens e heroes:** cada página tem um visual próprio, sem repetir imagem e sem banco de fotos. São ilustrações em HTML/CSS que explicam o serviço:

| Página | Visual |
|---|---|
| Home, Sistemas, VetorOS | Captura real do painel do VetorOS em moldura de navegador + o fluxo "Site → CRM → WhatsApp → Sistema" |
| Sistemas sob medida | Janela de sistema com menu, indicadores e ordens com status, e "~~planilhas e cadernos~~ → um processo, um sistema" |
| Automação | Fluxo "Quando: pagamento confirmado → Então: WhatsApp, recibo PDF, financeiro, notificação" |
| Integrações e Soluções | ERP, CRM, e-commerce, WhatsApp, financeiro e app ligados por uma "camada de integração" |
| Aplicativos | Smartphone com lista de visitas e "Novo pedido", com cartão "sincronizado via API" |
| Sites | Site em desktop + celular (wireframe) |
| Landing Pages | Landing com formulário + notificação "Novo lead no CRM — origem: campanha" |

As ilustrações têm `role="img"` e um rótulo descritivo para leitores de tela.

## 4. Arquivos

### Criados

| Arquivo | Papel |
|---|---|
| `app/Support/PublicPages.php` | Catálogo de slugs + SEO dos serviços e produtos, Organization/Service/SoftwareApplication em schema.org e as URLs do sitemap. Rotas e sitemap leem daqui |
| `app/Http/Controllers/SiteController.php` | `home`, `systems`, `system`, `services`, `service`, `about` (substitui as closures do `web.php`) |
| `resources/js/data/site.ts` | **Conteúdo** de serviços, produtos, processo, tecnologias e cases. Adicionar um produto ou serviço = 1 entrada aqui + 1 em `PublicPages` |
| `resources/js/components/site/site-layout.tsx` | Estrutura comum: SEO, cabeçalho, `<main>`, rodapé, WhatsApp, cookies, link "Pular para o conteúdo" |
| `resources/js/components/site/site-header.tsx` | Cabeçalho único (desktop com dropdown, menu móvel) |
| `resources/js/components/site/seo-head.tsx` | Renderiza as tags do prop `seo` com `head-key` iguais aos `data-inertia` do Blade |
| `resources/js/components/site/blocks.tsx` | `Container`, `Eyebrow`, `SectionHeading`, `ButtonLink`, `PageHero`, `ProcessSteps`, `FlowDiagram`, `FaqList`, `CtaBand` |
| `resources/js/components/site/visuals.tsx` | `BrowserFrame` + as 6 ilustrações de hero |
| `resources/js/pages/site/servico.tsx` | Template das 6 páginas de serviço |
| `resources/js/pages/site/sistemas.tsx` | Portfólio de produtos |
| `resources/js/pages/site/sistema.tsx` | Página de cada produto |
| `public/images/logo_ab-96.webp` (866 B), `public/images/logo_ab-512.png` | Logo otimizada (cabeçalho) e logo para o schema.org |
| `tests/Feature/PublicSiteTest.php` | 18 testes (abaixo) |

### Alterados

| Arquivo | Mudança |
|---|---|
| `routes/web.php` | Rotas públicas via `SiteController`. Serviços registrados em loop a partir de `PublicPages::SERVICES`. `/sistemas/{product}` com `whereIn`. `/produtos` → 301. O nome `company-websites` foi mantido |
| `resources/views/app.blade.php` | `data-inertia` em cada tag de SEO (acaba a duplicação), `og:url`, JSON-LD no HTML inicial (`JSON_HEX_TAG` impede fechar o `<script>`), carregamento da fonte |
| `app/Support/Seo.php` | Descrição padrão reposicionada |
| `app/Http/Controllers/SitemapController.php` | Usa `PublicPages::sitemapUrls()`: entram as 6 páginas de serviço, `/sistemas` e os 2 produtos; sai `/produtos` |
| `app/Models/Lead.php` | `PRODUCTS` ganha `automacao`. Novo `CONTACT_PROJECT_TYPES` (opções do formulário, na ordem do correio) |
| `app/Http/Controllers/ContactController.php` | Envia `projectTypes` e `selectedType` (`?tipo=` pré-seleciona), SEO e ContactPage |
| `app/Http/Requests/PublicContactRequest.php` | Valida contra `CONTACT_PROJECT_TYPES`; mensagens novas |
| `resources/js/app.tsx` | O título não repete a marca. Páginas `site/*` sem layout do painel |
| `resources/js/pages/welcome.tsx` | Home reescrita (1.123 → 547 linhas, já formatada pelo Prettier) |
| `resources/js/pages/servicos.tsx` | Vira hub de soluções com links para as páginas próprias |
| `resources/js/pages/sobre.tsx` | Perfil empresarial: princípios, método, capacidade técnica |
| `resources/js/pages/contato.tsx` | Campos do correio, "Tipo de projeto", CTA **"Falar sobre meu projeto"**, aviso de privacidade, "O que acontece depois" |
| `resources/js/components/public-footer.tsx` | Colunas Soluções / Sistemas prontos / Empresa / Contato |
| `resources/js/components/public-brand.tsx` | Logo webp de 866 B com `width`/`height` |
| `resources/js/pages/legal/*.tsx` | Usam `SiteHeader` e `SeoHead` |

### Removidos

`pages/produtos.tsx`, `pages/company-websites.tsx` (a URL continua e agora usa o template de serviço), `components/secondary-page-header.tsx`, `components/nav-products-menu.tsx`, `components/nav-services-menu.tsx`, `data/product-features.ts`.

### Reaproveitados sem alteração

`WhatsAppFloat`, `CookieConsent`, `useContact`/`buildWhatsappUrl`, `Collapsible`, `DropdownMenu`, `Input`, `Label`, `InputError`, o fluxo de contato → lead no CRM → e-mail (deduplicação, honeypot, throttle), os modelos `PortfolioItem` e `Testimonial` (a home continua exibindo o que for cadastrado no admin), o blog e o captura `dashboard-vetoros.webp`.

## 5. SEO: o que mudou

- Uma única description, canonical e og:* por página (antes eram duas), testado.
- Títulos sem a marca repetida e específicos por página, como "Automação de processos empresariais | ABrasil Sistemas".
- JSON-LD no HTML inicial: `Organization` (com `@id`) em todas as páginas, `WebSite` na home, `Service` em cada serviço, `SoftwareApplication` em cada produto, `ContactPage`.
- 8 URLs novas e indexáveis: 5 de serviço (a de sites já existia), `/sistemas` e 2 de produto. O sitemap foi atualizado e `/produtos` redireciona com 301.
- `og:url` em todas as páginas.
- Headings: um `h1` por página, `h2` por seção, `h3` nos itens.
- Termos do correio cobertos nos títulos e descrições: desenvolvimento de sistemas, software sob medida, sistemas empresariais, aplicativos, sites empresariais, landing pages, automação de processos, integração de sistemas, APIs. Sem repetição forçada.
- Performance: logo de 797 KB → 866 B; imagens com `width`/`height`; `loading="lazy"` fora do hero e `fetchpriority="high"` na imagem do hero; fonte com `display=swap`.
- Acessibilidade: "Pular para o conteúdo", `aria-label` nos menus, `aria-expanded`/`aria-controls` no menu móvel, rótulos nas ilustrações, `<dl>`/`<ol>`/`<figure>` semânticos e contraste AA nos textos.

## 6. UX/UI: o que mudou

- Uma mensagem fixa no hero, sem carrossel. CTAs **Conhecer soluções** e **Falar sobre um projeto**.
- O CTA principal é o mesmo em todo o site ("Falar sobre um projeto" / "Falar sobre meu projeto") e leva ao formulário com o tipo de projeto já escolhido conforme a página de origem (`/contato?tipo=automacao#formulario`).
- Os textos explicam primeiro o problema, depois o resultado e por último a tecnologia, sem jargão. Os FAQs de cada serviço respondem objeções reais (custo, prazo, propriedade, segurança).
- Contato: campos do correio, a explicação "O que acontece depois" e o aviso de privacidade.
- Menu móvel com Soluções e Sistemas listados e o CTA no final.

## 7. Testes realizados

Ambiente: PHP 8.4 local com o SQLite em memória do `phpunit.xml` (`MAIL_MAILER=array`, sem rede real). Nenhum banco, container ou serviço de produção foi tocado.

| Verificação | Resultado |
|---|---|
| `tests/Feature/PublicSiteTest.php` | ✅ 18 testes |
| Suíte completa, numa cópia na scratchpad, sem `public/hot`, após `npm run build` | ✅ **214/214** (1.077 asserções) |
| Suíte completa na árvore de trabalho | 210/214: os 4 testes de WhatsApp (`CompanyWhatsappSettingsTest` ×2, `LeadWhatsappSendTest` ×2) falham **só por causa do `npm run dev` em execução**: com `public/hot` presente, o Inertia tenta SSR em `http://[::1]:5173/__inertia_ssr` e esses testes bloqueiam requisições externas. Na cópia sem `public/hot`, passam |
| `npm run build` (com `SKIP_WAYFINDER_GENERATE=1`, como no Dockerfile) | ✅ (só o aviso de chunk > 500 kB, que já existia) |
| `tsc --noEmit` | ✅ 0 erros nos arquivos do projeto. ⚠️ 21 erros em arquivos **gerados** pelo Wayfinder, em todos os `Route::redirect` (veja pendências) |
| ESLint nos arquivos alterados/criados | ✅ (os 61 erros de ESLint do projeto estão em admin/blog/leads/settings, arquivos não alterados) |
| Prettier nos arquivos criados/reescritos | ✅ |
| Pint nos PHP alterados | ✅ |
| Navegação real (servidor local + Chromium headless, 1440 px e 390 px) | ✅ Home, Automação, Contato (`?tipo=automacao` pré-selecionado), Aplicativos, VetorOS. Redirect `/produtos` → 301 conferido via `curl` |

O que os 18 testes cobrem: as 7 páginas institucionais renderizam o componente certo com canonical, title, description e JSON-LD; as 6 páginas de serviço; a URL e o nome de rota `company-websites` preservados; 404 para produto desconhecido; 301 em `/produtos`; **uma** description e **um** canonical no HTML inicial, mais `og:url` e JSON-LD `Service`; o JSON-LD não consegue fechar o `<script>`; o sitemap com todas as URLs e sem `/produtos`; a pré-seleção `?tipo=` válida e inválida; o envio com `automacao` gravando o lead; a recusa de tipo fora da lista; os tipos do formulário existindo em `Lead::PRODUCTS`.

## 8. Descrição visual das principais telas

- **Home (desktop):** cabeçalho navy fino com logo, menu e botão branco "Falar sobre um projeto". Hero navy com o título em 3 linhas à esquerda e a captura do VetorOS em moldura de navegador à direita, com o fluxo Site → CRM → WhatsApp → Sistema abaixo. Em seguida, uma seção branca "Engenharia de software a serviço da operação" com 3 pilares; os dois produtos lado a lado sobre fundo cinza-claro; a lista editorial de soluções em duas colunas; o processo 01–06 em faixa navy; a grade de tecnologias; o CTA final navy.
- **Home (390 px):** tudo em uma coluna, CTAs em largura total, a captura abaixo do texto, cards de produto empilhados.
- **Página de serviço (Automação):** hero navy com o fluxo "Quando / Então"; problemas em lista de duas colunas; grade 3×2 do que entregamos; "O que muda na prática" com checks ao lado dos diagramas de fluxo; processo navy; FAQ e soluções relacionadas; CTA.
- **Contato (390 px):** hero curto; formulário em cartão (Nome, Empresa, E-mail, WhatsApp, Tipo de projeto, Descrição, botão azul de largura total); depois os cartões de WhatsApp e e-mail e o "O que acontece depois".
- **VetorOS:** link de volta para "Sistemas prontos", logo + segmento, título "VetorOS: a operação da assistência técnica em um só sistema.", captura, antes/depois em dois cartões, funcionalidades, plataformas, implantação e integrações.

Capturas (temporárias, na scratchpad desta sessão): `/tmp/claude-1000/-home-anderson-projects-laravel-infra-abrasil-gateway-abrasilsistemas/d112c14c-66a1-49f9-ad1c-a5f04f73fadf/scratchpad/shots/`.

## 9. Administração e evolução (decisão)

**Não criei CMS.** O conteúdo institucional muda pouco e fica tipado em `resources/js/data/site.ts`, com o SEO e as rotas em `app/Support/PublicPages.php`:
- **novo produto:** uma entrada em `products` + uma em `PublicPages::PRODUCTS` (rota, sitemap e JSON-LD saem automaticamente) + o logo/captura em `public/images`;
- **novo serviço:** uma entrada em `services` + uma em `PublicPages::SERVICES`;
- **novo case:** uma entrada em `caseStudies` (a seção aparece sozinha na home);
- **trocar imagem/texto:** editar o campo correspondente.

O que muda com frequência já tem painel: blog, portfólio e depoimentos (`/admin/blog`, `/portfolio`, `/testimonials`) continuam aparecendo na home. Se os cases passarem a ser frequentes, vale migrá-los para o banco reaproveitando o `PortfolioItem` (problema/solução/tecnologia/resultado). Isso exige migration e não foi feito agora.

## 10. Pendências

1. **Wayfinder × Laravel 13.36.** O `composer.lock` já estava modificado antes desta tarefa (Laravel 13.32 → 13.36). A versão nova inclui o método HTTP `QUERY` nos `Route::redirect`, e os tipos do Wayfinder não o aceitam: são 21 erros de `tsc` em `resources/js/routes` e `resources/js/actions`, em todos os redirects (`admin`, `conhecimento`, `minha-biblioteca`…). O build do Vite não é afetado. Resolver atualizando `@laravel/vite-plugin-wayfinder`/`laravel/wayfinder` ou fixando o framework.
2. **SSR.** O site é renderizado no navegador; o HTML inicial tem só o `<head>` (agora correto). O Google renderiza JavaScript, mas o SSR do Inertia (`build:ssr` + processo Node no container) melhoraria a indexação e o LCP. Isso é uma mudança de infraestrutura e não foi feita.
3. **Imagens reais.** Só existe uma captura real (o painel do VetorOS, com números zerados de demonstração). Faltam: uma captura do VetorPet (web e app Android), uma captura do VetorOS com dados de demonstração preenchidos, e uma imagem OG 1200×630 própria por página (hoje todas usam a do VetorOS).
4. **Confirmar claims removidos/mantidos:**
   - removi do VetorOS "Aplicativos para equipe e clientes": o app Android aparece em `docs/roadmap-app-tecnico-android.md` como *roadmap*. Se já estiver publicado, basta incluir em `products[0].platforms`;
   - mantive as funcionalidades do VetorPet como já estavam publicadas (o código não está neste ambiente);
   - "14 dias sem cartão" foi mantido do site anterior.
5. **IA:** criar a página só quando houver um projeto real para mostrar.
6. **Cases:** cadastrar quando houver projetos autorizados pelos clientes.
7. **Tecnologias não confirmadas** (Python, FastAPI, Next.js, Node.js, PostgreSQL, nuvem): incluir em `technologyGroups` se forem usadas.
8. **Blog:** o `BlogPublicLayout` mantém o cabeçalho antigo (tem o CTA de cadastro do blog). Unificar com o `SiteHeader` numa próxima etapa.
9. **Página 404** mantém o visual antigo.
10. `logo_ab.png`, `logo_ab_2.png` e `logo_ab-old.png` (2,2 MB no total) continuam em `public/images`. Não removi porque podem estar referenciados fora do site (e-mails, outros projetos).
11. **Deploy:** precisa de build e publicação normais. Não há migration. A nova chave `automacao` em `leads.product` não exige mudança no banco (a coluna é `string`).
12. Commit e push aguardam sua autorização.

---

# Execução de `correio.md`: gerenciamento da conexão WhatsApp/WAHA pelo painel

Data: 2026-09-24.

## Resumo

**Implementado e testado. Nada foi publicado em produção.** Em Configurações → Leads → WhatsApp da empresa, o painel agora separa **Configuração** (número, provider, sessão salvos em `settings`) de **Conexão** (estado real da sessão no WAHA). A tela permite conectar (com QR Code num modal), acompanhar o status, ver a conta autenticada (`me.id` / `me.pushName`), receber alerta de número divergente e desconectar com confirmação. Toda chamada ao WAHA sai do Laravel. A `WAHA_API_KEY` nunca chega ao navegador.

Não foram alterados: o fluxo de envio CRM → n8n → WAHA, o `LeadWhatsappService`, os workflows do n8n, os webhooks do WAHA, o ACK, as credenciais e o banco. Não há migration. A sessão real `vetoros1-1` não foi consultada, conectada nem desconectada: os testes usam `Http::fake()` em containers sem rede.

## 1. Arquivos criados

| Arquivo | Papel |
|---|---|
| `app/Services/WahaService.php` | Adapter HTTP do WAHA: `getSession`, `restartSession`, `logoutSession`, `getQrCode`. Recebe o nome da sessão de quem chama, sem nada fixo no código |
| `app/Services/WahaException.php` | Erro com mensagem amigável e status HTTP |
| `app/Services/CompanyWhatsappConnection.php` | Regra de negócio: lê a sessão de `CompanyWhatsappSettings`, mapeia os status, compara o número configurado com `me.id` e decide quando reiniciar |
| `app/Http/Controllers/Settings/CompanyWhatsappConnectionController.php` | Controller fino: `status`, `connect`, `qr`, `disconnect` |
| `resources/js/components/company-whatsapp-connection.tsx` | Bloco "Conexão", modal do QR Code e confirmação de desconexão |
| `tests/Feature/CompanyWhatsappConnectionTest.php` | 28 testes (85 asserções) |

## 2. Arquivos alterados

| Arquivo | Mudança |
|---|---|
| `config/services.php` | Novo bloco `waha` (`base_url`, `api_key`, `timeout`) lido do ENV |
| `routes/settings.php` | 4 rotas novas (abaixo) |
| `resources/js/pages/settings/leads.tsx` | O resumo vira "Configuração" (o badge agora diz "Integração") e o bloco "Conexão" entra logo abaixo. `formatWhatsapp` foi movido para o componente novo |

## 3. Arquitetura

```
React (settings/leads) ──fetch + CSRF──▶ CompanyWhatsappConnectionController (auth + verified + admin)
                                           └─▶ CompanyWhatsappConnection  (sessão vinda de CompanyWhatsappSettings)
                                                 └─▶ WahaService ──X-Api-Key──▶ WAHA /api/sessions/{session}, /api/{session}/auth/qr
```

- **Fonte de verdade:** `GET /api/sessions/{session}` (`status`, `me.id`, `me.pushName`, `me.lid`). `company_whatsapp_number` serve só para comparar.
- **Mapa de status:** `WORKING` → Conectado, `SCAN_QR_CODE` → Aguardando leitura do QR Code, `STARTING` → Conectando, `FAILED` → Falha na conexão, `STOPPED` → Desconectado. Qualquer outro status → "Status desconhecido" (o painel mostra o valor bruto). Erro de comunicação → "Erro" com mensagem amigável.
- **Conectar:** se a sessão já está `WORKING`, `SCAN_QR_CODE` ou `STARTING`, nada é feito. Em qualquer outro estado, chama `POST /api/sessions/{session}/restart`. Se a sessão não existe no WAHA (404), devolve erro e **não cria sessão**, para não perder os webhooks configurados.
- **QR Code:** `GET /api/{session}/auth/qr` com `Accept: image/png`. A resposta é tratada como binário (há fallback para o formato JSON base64 de outras versões do WAHA) e validada pela assinatura PNG. Vai ao navegador por rota autenticada com `Cache-Control: no-store`. Nada é salvo. O QR só é pedido quando a sessão está em `SCAN_QR_CODE` (senão a resposta é 409).
- **Modal:** consulta `status` a cada 3 s e recarrega a imagem a cada 20 s. Se o QR expirar ou falhar, mostra o aviso e o botão "Gerar novo QR Code". Ao chegar em `WORKING`, fecha sozinho e mostra "WhatsApp conectado".
- **Número divergente:** `me.id` é normalizado (sem `@c.us`/`@s.whatsapp.net`, só dígitos). A comparação **ignora o nono dígito** de celulares brasileiros, porque contas antigas aparecem como `555198931325@c.us`. Quando os números divergem, o painel mostra "⚠ Número divergente" com os dois números. Nada é corrigido automaticamente.
- **Desconectar:** diálogo de confirmação e depois `POST /api/sessions/{session}/logout`. A sessão, os webhooks e as configurações ficam intactos.
- **Segurança:** as rotas exigem `auth`, `verified` e `admin`. A sessão vem sempre das configurações (parâmetros `session` do navegador são ignorados) e é validada contra `SESSION_PATTERN`, com `rawurlencode` na URL. Timeout de conexão de 5 s e de requisição de `WAHA_TIMEOUT` (padrão 15 s). Os logs registram sessão, operação, status HTTP e corpo truncado, nunca a API key. `connect` e `disconnect` têm `throttle:10,1`.
- **Multi-tenant futuro:** o `WahaService` não conhece empresa nem sessão fixa. Para o VetorOS, basta outro "resolvedor" de sessão por tenant no lugar de `CompanyWhatsappSettings`.

## 4. Variáveis ENV

| Variável | Uso | Situação |
|---|---|---|
| `WAHA_BASE_URL` | URL interna do WAHA | Já é passada ao `abrasilsistema` no `docker-compose.yml` (`http://waha:3000`) |
| `WAHA_API_KEY` | `X-Api-Key` do WAHA | **Falta no serviço `abrasilsistema`** |
| `WAHA_TIMEOUT` | Timeout em segundos (opcional, padrão 15) | Opcional |

## 5. docker-compose

**Precisa de uma linha**, no bloco `environment` do serviço `abrasilsistema` em `/opt/infra-abrasil/docker-compose.yml`. O worker e o scheduler herdam via `extends`. A variável `WAHA_API_KEY` já existe no `.env` da infra, porque o próprio WAHA e o VetorOS a usam:

```yaml
      WAHA_API_KEY: ${WAHA_API_KEY:-}
```

O `docker-compose.yml` não foi alterado: fica fora deste repositório e faz parte do deploy. Sem essa linha, o painel mostra "O WAHA recusou a autenticação do CRM", e o envio de mensagens continua funcionando normalmente.

## 6. Rotas adicionadas

| Método | URI | Nome | Middleware |
|---|---|---|---|
| GET | `settings/leads/whatsapp/status` | `lead-settings.whatsapp.status` | auth, verified, admin |
| POST | `settings/leads/whatsapp/connect` | `lead-settings.whatsapp.connect` | auth, verified, admin, throttle:10,1 |
| GET | `settings/leads/whatsapp/qr` | `lead-settings.whatsapp.qr` | auth, verified, admin |
| POST | `settings/leads/whatsapp/disconnect` | `lead-settings.whatsapp.disconnect` | auth, verified, admin, throttle:10,1 |

## 7. Testes criados (`tests/Feature/CompanyWhatsappConnectionTest.php`)

| Item do correio | Teste |
|---|---|
| 1. Sessão WORKING | `WORKING session is shown as connected with the authenticated account` (também confere o envio do `X-Api-Key`) |
| 2. SCAN_QR_CODE | `SCAN_QR_CODE session is waiting for the QR Code` |
| 3. FAILED (+ STOPPED, STARTING, desconhecido) | `WAHA statuses are mapped to panel states` (4 datasets) |
| 4. QR PNG | `the QR Code PNG is proxied without being parsed as JSON`, `the QR Code is refused when the session is not waiting for it`, `an invalid QR Code image is rejected` |
| 5. Restart | `connect restarts a stopped or failed session` (2), `connect does not restart a session that is already working or waiting for the QR Code` (3), `connect never creates a session that does not exist in WAHA` |
| 6. Logout | `disconnect logs out the session without deleting it or the settings` |
| 7. Erro HTTP | `WAHA HTTP errors become friendly messages` |
| 8. Timeout/falha | `WAHA timeouts and connection failures are handled` |
| 9. Comparação de número | `configured number is compared to me.id after normalization` (5 datasets, incluindo sem o nono dígito) |
| 10. Número divergente | `a different connected number is reported as divergent and never saved` (usa o caso real `555195179173@c.us`) |
| 11. Não autorizado | `guests and readers cannot manage the WhatsApp connection` (401 para visitante, 403 para `reader`, nenhuma chamada ao WAHA) |
| 12. API key não exposta | `the WAHA API key is never exposed in responses` (status, connect, disconnect e a página Inertia) |
| 13. Sessão da configuração | `the session always comes from the settings, never from the browser` |
| extra | `connection actions require a saved WAHA session` |

## 8. Execução dos testes

Os testes rodaram em uma cópia do projeto na scratchpad, com a imagem da aplicação (`infra-abrasil-abrasilsistema`) em `docker run --rm --network none` e o SQLite em memória do `phpunit.xml`. Nada foi executado no container de produção e nenhum banco real foi acessado.

```text
CompanyWhatsappConnectionTest   28 passed (85 assertions)
Suíte completa                  195 passed, 1 failed (828 assertions)
Pint --test (PHP novos/alterados) PASS
git diff --check                sem problemas
```

A única falha já existia e não tem relação com esta tarefa: `BlogTest > administrator can upload blog images` (`imagejpeg` indisponível no GD da imagem).

## 9. Build do frontend

`php artisan wayfinder:generate --with-form` (imagem do projeto), depois `SKIP_WAYFINDER_GENERATE=1 npm run build` em `node:22-bookworm-slim`:

- `npm run build`: OK (só o aviso de chunk > 500 kB, que já existia)
- `tsc --noEmit`: OK
- ESLint e Prettier no componente novo: OK
- `leads.tsx` tem 4 erros de ESLint e trechos fora do Prettier que **já existiam no HEAD** (texto da extensão, token, `setState` em efeito das mensagens). As linhas novas estão formatadas. O restante não foi mexido para manter o diff pequeno.

## 10–11. Sessão real e deploy

- A sessão WAHA real **não** foi conectada, desconectada, reiniciada nem consultada.
- **Não houve deploy.** O código e os assets entram na imagem, então é preciso rebuild/redeploy de `abrasilsistema`, `abrasilsistema-worker` e `abrasilsistema-scheduler`, depois de acrescentar `WAHA_API_KEY` no compose. Não há migration. As mudanças não foram commitadas.

## Como validar depois do deploy

1. Entrar como admin em Configurações → Leads. Em "Conexão", com a sessão atual, deve aparecer **Conectado**, a conta e o número autenticado. Se continuar autenticada como `555195179173@c.us`, aparece **⚠ Número divergente** (configurado: 5551998931325).
2. Conectar/desconectar só quando for conveniente, porque o logout derruba o envio do CRM até um novo QR ser lido.

---

# Execução de `correio.md`: deploy controlado (concluído)

Data: 2026-09-24.

## Resumo

**Deploy concluído e validado.** O build foi feito pelo Claude Code. O `docker compose up -d` foi executado pelo próprio usuário, porque a política de permissões bloqueou o comando para o Claude Code. As 13 validações pós-deploy passaram. Nada foi alterado em n8n, WAHA, webhooks, credenciais ou banco. Nenhum token foi exibido e nenhuma mensagem WhatsApp foi enviada.

## Relatório

| Item | Resultado |
|---|---|
| Build | ✅ `docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler` sem erros |
| Deploy | ✅ `docker compose up -d …` executado pelo usuário. Os 3 containers foram recriados e iniciados. `mysql` e `waha` não foram tocados |
| 1. Containers | ✅ `abrasilsistema`, `abrasilsistema-worker` e `abrasilsistema-scheduler` estão `healthy` |
| 2. Aplicação | ✅ `GET /` → 200, `GET /login` → 200 (via nginx, `Host: abrasilsistema.localhost`). Nenhum erro/exception nos logs dos containers após o deploy |
| 3. `POST leads/{lead}/whatsapp` | ✅ `leads.whatsapp.store` |
| 4. `PATCH api/whatsapp/messages/{providerMessageId}/status` | ✅ `api.whatsapp.messages.status` |
| 5. `services.n8n.whatsapp_webhook_url` | ✅ filled |
| 6. `services.n8n.whatsapp_webhook_header` | ✅ filled |
| 7. `services.n8n.whatsapp_webhook_token` | ✅ filled (verificado só como booleano, sem exibir o valor) |
| 8. Header Auth no código em produção | ✅ `app/Services/LeadWhatsappService.php:90`: `->withHeaders([$header => $token])` |
| 9. Configurações → Leads | ✅ sem login: `302 → /login`. Autenticado (requisição GET interna, só de leitura): `200`, componente Inertia `settings/leads` |
| 10. Seção "WhatsApp da empresa" | ✅ presente no bundle (`resources/js/pages/settings/leads.tsx` → `public/build/assets/leads-*.js`), com props de WhatsApp na página e rota `PUT settings/leads/whatsapp` registrada |
| 11. Workflow n8n | ✅ `CRM ABrasil - Enviar WhatsApp` (`VWvN7AWQc88fgSdV`) ativo |
| 12. WAHA | ✅ `healthy` |
| 13. Sessão `vetoros1-1` | ✅ `WORKING` (API key lida só dentro do container) |

## Erros encontrados

- O `docker compose up -d` foi bloqueado para o Claude Code pela política de permissões (deploy em produção). O usuário resolveu executando o comando manualmente.
- Nenhum erro de aplicação.

---

# Execução de `correio.md`: deploy controlado (2ª tentativa)

Data: 2026-09-24.

## Resumo

**Build concluído; deploy NÃO aplicado.** As imagens novas foram construídas com sucesso, mas o comando `docker compose up -d` foi bloqueado pela política de permissões do Claude Code (classificador do modo automático: "Production Deploy"). Os containers continuam rodando a versão anterior. As validações pós-deploy de 1 a 13 ficam pendentes até o `up -d` ser executado.

## Relatório

| Item | Resultado |
|---|---|
| Build | ✅ `docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler` terminou sem erros (imagens `infra-abrasil-abrasilsistema`, `-worker`, `-scheduler` geradas) |
| Deploy | ❌ `docker compose up -d …` **bloqueado** pela permissão do Claude Code. Não foi contornado |
| Containers | `abrasilsistema`, `abrasilsistema-worker` e `abrasilsistema-scheduler` seguem `Up 19 hours (healthy)`, **ainda com a versão antiga** |
| Rotas (imagem nova, via `docker run --rm` só para leitura) | ✅ `POST leads/{lead}/whatsapp` (`routes/web.php:150`) e ✅ `PATCH whatsapp/messages/{providerMessageId}/status` (`routes/api.php:16`) presentes |
| Header Auth (imagem nova) | ✅ `LeadWhatsappService.php` lê `services.n8n.whatsapp_webhook_header` (linha 72) e envia `withHeaders([$header => $token])` (linha 90) |
| Configuração n8n (itens 5–7) | ⏳ Pendente. Precisa do container novo em execução |
| Painel (itens 9–10) | ⏳ Pendente. Precisa do container novo em execução |
| n8n | ✅ container `healthy`. Nada foi alterado |
| WAHA / sessão | ✅ WAHA `healthy`. Nada foi alterado. A sessão `vetoros1-1` não foi consultada de novo nesta rodada |
| Erros | Apenas o bloqueio de permissão do deploy. Nenhum erro de build |

Nada foi alterado em n8n, WAHA, webhooks, credenciais ou banco. Nenhum token foi exibido. Nenhuma mensagem WhatsApp foi enviada.

## Para concluir

O usuário executa o deploy (as imagens já estão construídas):

```bash
cd /opt/infra-abrasil
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
```

Ou libera a ação com uma regra de permissão do Bash no Claude Code. Depois disso é preciso rodar as validações 1 a 13 do `correio.md`.

---

# Execução de `correio.md`: deploy controlado do WhatsApp da empresa

## Resumo

**Deploy NÃO realizado.** As pré-checagens de infraestrutura (n8n, webhook, WAHA, sessão) passaram. As checagens das variáveis `N8N_WHATSAPP_WEBHOOK_*` no container (itens 1 a 4) não puderam ser feitas: o comando que verificava só se o token estava preenchido (sem imprimi-lo) foi bloqueado pela política de permissões do Claude Code (proteção contra exposição de credenciais). O correio pede para não fazer o deploy quando algo obrigatório não for confirmado, então o build e o `up -d` não foram executados. Nada foi alterado em containers, n8n, WAHA, webhooks ou banco. Nenhuma mensagem foi enviada.

Data: 2026-09-24.

## Pré-checagens

| # | Verificação | Resultado |
|---|---|---|
| 1 | `N8N_WHATSAPP_WEBHOOK_URL` disponível no container | ⚠️ Não verificado (bloqueado) |
| 2 | `N8N_WHATSAPP_WEBHOOK_HEADER` configurado | ⚠️ Não verificado (bloqueado). O `docker-compose.yml` usa `X-CRM-Token` como padrão |
| 3 | `N8N_WHATSAPP_WEBHOOK_TOKEN` preenchido | ⚠️ Não verificado (bloqueado) |
| 4 | Header compatível com o Header Auth do workflow | ⚠️ Não verificado (depende de 2 e 3) |
| 5 | Workflow `CRM ABrasil - Enviar WhatsApp` ativo | ✅ `n8n list:workflow --active=true` lista `VWvN7AWQc88fgSdV`. `WAHA - Status WhatsApp` e `WAHA - Mensagens Recebidas → CRM` também estão ativos |
| 6 | Webhook `crm/send-whatsapp` registrado | ✅ `POST` sem Header Auth → **403** (rota inexistente de controle → 404). O workflow não foi executado |
| 7 | WAHA saudável | ✅ container `healthy` |
| 8 | Sessão `vetoros1-1` WORKING | ✅ `GET /api/sessions/vetoros1-1` → 200, `status: WORKING` (API key lida só dentro do container, não exibida) |

Estado atual dos containers (sem alteração): `abrasilsistema`, `abrasilsistema-worker` e `abrasilsistema-scheduler` estão `Up 19 hours (healthy)`, ainda com a versão anterior.

## O que precisa ser feito para seguir

Confirmar os itens 1 a 4 de uma destas formas:

- **O próprio usuário roda a checagem**, que só mostra booleanos/tamanho:
  ```bash
  cd /opt/infra-abrasil
  docker compose exec -T abrasilsistema sh -c '
    for v in N8N_WHATSAPP_WEBHOOK_URL N8N_WHATSAPP_WEBHOOK_HEADER N8N_WHATSAPP_WEBHOOK_TOKEN; do
      eval val=\$$v; [ -n "$val" ] && echo "$v=filled" || echo "$v=EMPTY"; done'
  ```
  E confere no n8n se a credential Header Auth do webhook usa o mesmo nome de header (padrão `X-CRM-Token`) e o mesmo valor de `ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_TOKEN` do `.env`.
- **Ou libera a checagem** com uma regra de permissão do Bash no Claude Code, e a execução continua daqui.

Com os itens confirmados, o deploy é o do correio:

```bash
cd /opt/infra-abrasil
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
```

## Relatório final (itens pedidos)

1. Deploy realizado: **não**.
2. Containers atualizados: nenhum.
3. Saúde dos serviços: todos `healthy` (sem mudança). WAHA ok, sessão `vetoros1-1` WORKING, n8n ok.
4. Rotas verificadas: webhook n8n `crm/send-whatsapp` registrado (403 sem auth). As rotas Laravel de envio e ACK ficam para depois do deploy.
5. Header Auth: exigido pelo webhook do n8n (403 sem header). A configuração do lado Laravel não foi confirmada.
6. Nova seção do painel: ainda não disponível em produção (depende do deploy).
7. Erros: nenhum erro de serviço. O único bloqueio foi a checagem das variáveis de ambiente.

---


# Execução de `correio.md`: sessão do WAHA vinda do Laravel no n8n

## Resumo

O workflow `CRM ABrasil - Enviar WhatsApp` (ID `VWvN7AWQc88fgSdV`) passou a usar a sessão enviada pelo Laravel em `body.session`. Quando ela não vem, usa `vetoros1-1`. Só o campo `session` do node **Edit Fields** mudou. Nenhuma mensagem real foi enviada.

## O que foi alterado

| | |
|---|---|
| Workflow | `CRM ABrasil - Enviar WhatsApp` (`VWvN7AWQc88fgSdV`) |
| Node | `Edit Fields` (Set 3.5), campo `session` |
| Expressão anterior | `vetoros1-1` (valor fixo) |
| Expressão nova | `={{ $json.body.session \|\| 'vetoros1-1' }}` |
| Versão anterior | `d862ee56-1c44-4ca4-8808-6204d0bc747b` |
| Versão nova (publicada) | `62acfd64-527d-472e-a848-f0daa1b22d46` |

O diff entre o backup e o JSON importado tem uma única linha. HTTP Request, webhook, Header Auth, If, "Registrar envio no CRM", resposta, credentials e os workflows `WAHA - Status WhatsApp` e `WAHA - Mensagens Recebidas → CRM` ficaram como estavam.

## Como foi aplicado

O n8n 2.40.5 roda em modo regular. Nesse modo, o `import:workflow --activeState=fromJson` não funciona, e o `publish:workflow` só vale depois de reiniciar o n8n. Com autorização do usuário:

```bash
cd /opt/infra-abrasil/backups/n8n
# JSON gerado a partir do backup, trocando apenas o campo session (script com assert do valor antigo)
docker cp crm_enviar_whatsapp_session_dinamica.json infra-abrasil-n8n-1:/tmp/wf_new.json
docker exec infra-abrasil-n8n-1 n8n import:workflow --input=/tmp/wf_new.json   # nova versão (import desativa)
docker exec infra-abrasil-n8n-1 n8n publish:workflow --id=VWvN7AWQc88fgSdV      # publica a versão atual
cd /opt/infra-abrasil && docker compose restart n8n                              # alguns segundos fora do ar
```

Os arquivos temporários do container (`/tmp/wf_new.json` e `/tmp/wf_backup.json`) foram removidos depois.

## Backup

```bash
docker exec infra-abrasil-n8n-1 n8n export:workflow --id=VWvN7AWQc88fgSdV --output=/tmp/wf_backup.json
docker cp infra-abrasil-n8n-1:/tmp/wf_backup.json \
  /opt/infra-abrasil/backups/n8n/crm_enviar_whatsapp_VWvN7AWQc88fgSdV_20260924_115100.json
```

O backup tem a definição original completa, com as credentials referenciadas só pelo ID e sem segredos. A versão `d862ee56-…` continua também no histórico de versões do n8n. O JSON aplicado está em `/opt/infra-abrasil/backups/n8n/crm_enviar_whatsapp_session_dinamica.json`.

## Como reverter

```bash
docker exec infra-abrasil-n8n-1 n8n publish:workflow --id=VWvN7AWQc88fgSdV --versionId=d862ee56-1c44-4ca4-8808-6204d0bc747b
cd /opt/infra-abrasil && docker compose restart n8n
```

Pelo editor também dá: restaure a versão `d862ee56-…` no histórico e publique, ou volte o campo `session` para `vetoros1-1`.

## Validações

| # | Verificação | Resultado |
|---|---|---|
| 1 | Workflow continua ativo | ✅ `active = 1`, `activeVersionId = 62acfd64-…`. Os outros dois workflows WAHA também estão ativos. Webhooks registrados: `crm/send-whatsapp`, `waha-message-status`, `waha-message-received`. n8n `healthy` depois do restart |
| 2 | Edit Fields lê `body.session` | ✅ a versão publicada tem `={{ $json.body.session \|\| 'vetoros1-1' }}` |
| 3 | HTTP Request continua com `{{$json.session}}` | ✅ body `"session": "{{$json.session}}"`, URL `http://waha:3000/api/sendText` |
| 4 | Sem `session` no body → `vetoros1-1` | ✅ `{whatsapp}` resulta em `vetoros1-1`, e `session: ""` também resulta em `vetoros1-1` |
| 5 | Com `session` no body → prevalece a recebida | ✅ `session: "outra-sessao"` resulta em `outra-sessao` |
| 6 | Nenhuma mensagem real enviada | ✅ |

Os itens 4 e 5 foram validados avaliando a expressão exata do node com o motor de expressões do próprio n8n (`n8n-workflow`), dentro do container, sem executar o workflow. O webhook de produção também foi testado com um `POST` sem Header Auth, que respondeu **403**. Isso confirma que ele está registrado e que a autenticação continua exigida, sem que o workflow tenha sido executado.

Observação: o n8n ficou alguns segundos fora do ar durante o restart. Eventos do WAHA que chegaram nesse intervalo, se houve algum, podem não ter sido processados.

---


# Etapa anterior: vínculo formal do WhatsApp da empresa (Empresa → WAHA)

## Resumo

O painel ganhou a seção **WhatsApp da empresa** em *Configurações → Leads* (`/settings/leads`). Ela guarda o número remetente, o provedor (`waha`), a sessão do WAHA e se a integração está habilitada. O envio pelo CRM (`LeadWhatsappService`) passa a ler essa configuração: com a integração habilitada, manda `provider`, `session` e `remetente_whatsapp` ao n8n; desabilitada, bloqueia o envio antes de chamar o n8n.

Não foi criada migration, não houve alteração no n8n, no WAHA, nos containers, no `.env` nem nos webhooks `waha-message-status` e `waha-message-received`. Não houve deploy.

**Ponto principal:** `vetoros1-1` **não estava no Laravel**. Ela está fixa no workflow do n8n. Até esse workflow passar a ler `body.session`, o n8n continua usando o valor fixo dele (ver item 9).

## 1. Estrutura encontrada antes da alteração

| Item | Onde / como |
|---|---|
| Empresa | Não há model de "empresa" em uso. Existe `App\Models\Account` (tabela `accounts`), mas as migrations dela **não estão no repositório** (rodaram só em produção), e o próprio model diz que a multiempresa ficou inacabada. O sistema opera como empresa única (ABrasil). |
| Configurações da empresa/integrações | Tabela `settings` (chave/valor, `value` com cast `encrypted`), model `App\Models\Setting`. Já guarda o token de integração da extensão (`prospect_api_token`). |
| Painel | `resources/js/pages/settings/leads.tsx` (menu *Configurações → Leads*, só admin), controller `Settings\LeadSettingsController`, rotas em `routes/settings.php` (`auth` + `verified` + `admin`). A página já tinha "Token de integração" e "Mensagens de WhatsApp". |
| Telefone/WhatsApp existentes | Só dos leads: `leads.whatsapp` e `leads.phone` (destinatários). Não havia nenhum campo para o WhatsApp da empresa. |
| Envio de WhatsApp | `App\Services\LeadWhatsappService::send()` → `POST` no webhook do n8n com `prospect_id`, `nome`, `whatsapp` e `mensagem`. Nenhuma sessão no payload. |
| Status/ACK | `PATCH /api/whatsapp/messages/{id}/status` (sem sessão, sem alteração). |
| Variáveis de ambiente (Laravel) | `N8N_WHATSAPP_WEBHOOK_URL`, `N8N_WHATSAPP_WEBHOOK_HEADER`, `N8N_WHATSAPP_WEBHOOK_TOKEN`, `N8N_WHATSAPP_WEBHOOK_TIMEOUT`, `AB_PROSPECT_API_TOKEN`. O compose também passa `WAHA_BASE_URL=http://waha:3000`, mas o Laravel não a usa. **`WAHA_API_KEY` não é repassada** ao container `abrasilsistema`. |
| Variáveis de ambiente (infra) | `WAHA_ENGINE`, `WAHA_API_KEY`, `WAHA_DASHBOARD_*`, `WAHA_NOWEB_WA_VERSION`, `N8N_*`, `ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_*`. Nenhuma define a sessão. |

Decisão: usei a tabela `settings`, que já existe, em vez de `accounts`. `accounts` não tem migration versionada, não existe no banco de testes e não é usada por nenhuma tela. Colocar ali dados novos criaria uma dependência de uma estrutura inacabada. Se a multiempresa for retomada, as quatro chaves migram para colunas de `accounts` sem mudar o contrato do service.

## 2. Arquivos modificados

Criados:

- `app/Services/CompanyWhatsappSettings.php`: lê e grava a configuração (`current()`, `save()`), com constantes `PROVIDERS` e `SESSION_PATTERN`.
- `app/Http/Requests/Settings/CompanyWhatsappRequest.php`: validação.
- `tests/Feature/CompanyWhatsappSettingsTest.php`: 20 testes.

Alterados:

- `app/Models/Setting.php`: constantes das quatro chaves.
- `app/Services/LeadWhatsappService.php`: recebe `CompanyWhatsappSettings` no construtor e ganha o método privado `sender()`, que monta os campos do remetente.
- `app/Http/Controllers/Settings/LeadSettingsController.php`: `edit()` envia `companyWhatsapp` e `whatsappProviders`, e há uma nova ação `updateWhatsapp()`.
- `routes/settings.php`: `PUT settings/leads/whatsapp`, nome `lead-settings.whatsapp.update`, middleware `auth`, `verified` e `admin`.
- `resources/js/pages/settings/leads.tsx`: seção "WhatsApp da empresa".
- `executed.md`: este relatório.

## 3. Migration

**Nenhuma.** A tabela `settings` (`2026_09_22_120000_create_settings_table.php`) já comporta a configuração. Os registros existentes não mudam.

## 4. Campos adicionados

Chaves novas na tabela `settings`:

| Chave | Valor | Regra |
|---|---|---|
| `company_whatsapp_enabled` | `'1'` / `'0'` | obrigatório (boolean) |
| `company_whatsapp_number` | dígitos, ex. `5551998931325` | obrigatório se habilitado; normalizado por `LeadWhatsappService::normalizeNumber()` (mesma regra dos leads: 10–11 dígitos ganham `55`, 12–13 começando com `55` ficam iguais, o resto é rejeitado) |
| `company_whatsapp_provider` | `waha` | obrigatório, `in:waha` |
| `company_whatsapp_session` | ex. `vetoros1-1` | obrigatório se habilitado; `^[A-Za-z0-9_-]{1,64}$` |

Nenhum segredo vai para o banco nem para o frontend. A API key do WAHA e o token do n8n continuam só no ambiente. A página de configurações recebe só número, provedor, sessão e status (coberto por teste).

No painel (*Configurações → Leads → WhatsApp da empresa*):

- quadro de status com um badge **Não configurado / Habilitado / Desabilitado**, o número formatado e a sessão com o provedor;
- checkbox "Integração habilitada", campos de número, provedor (select) e sessão, e o botão "Salvar WhatsApp da empresa".

O status mostra o estado **da configuração**. Não consulta o WAHA ao vivo: o container do Laravel não recebe `WAHA_API_KEY` e a tarefa proibia mexer em containers e compose.

## 5. Como ficou Empresa → WAHA

```text
Painel (Configurações → Leads → WhatsApp da empresa)
   ↓  PUT /settings/leads/whatsapp  (admin)
settings: company_whatsapp_{enabled, number, provider, session}
   ↓  CompanyWhatsappSettings::current()
LeadWhatsappService::send(Lead, mensagem)
   ├─ destinatário = Lead.whatsapp (normalizado)       → "whatsapp"
   └─ remetente    = WhatsApp da empresa               → "remetente_whatsapp", "provider", "session"
   ↓  POST webhook n8n (Header Auth, sem mudança)
n8n "CRM ABrasil - Enviar WhatsApp" → WAHA /api/sendText
```

Payload com a integração **habilitada** (só acrescenta campos; os quatro originais não mudaram):

```json
{
    "prospect_id": 118,
    "nome": "Assistência Técnica ABC",
    "whatsapp": "5551999998888",
    "mensagem": "Texto",
    "provider": "waha",
    "session": "vetoros1-1",
    "remetente_whatsapp": "5551998931325"
}
```

Comportamento por estado:

| Estado no painel | Envio | Payload |
|---|---|---|
| Nunca salvo (situação atual em produção) | segue como antes | só `prospect_id`, `nome`, `whatsapp`, `mensagem`; o n8n usa a sessão dele |
| Habilitado | envia | + `provider`, `session`, `remetente_whatsapp` |
| Desabilitado | **bloqueado**, o n8n não é chamado; aparece "O envio de WhatsApp está desativado nas configurações da empresa." | — |
| Habilitado sem número/sessão (só se o banco for editado à mão) | bloqueado, com "não está configurado" + log de erro | — |

O modo "nunca salvo" existe para não quebrar o envio que já funciona: o deploy não muda nada até um admin salvar a configuração.

## 6. Onde `vetoros1-1` estava definido

- **Laravel:** em lugar nenhum. Não há ocorrência no código, na config, no `.env.example` nem no compose.
- **n8n**, consultado **somente leitura** no `database.sqlite` (definição dos nós, sem credenciais):
  - workflow **"CRM ABrasil - Enviar WhatsApp"** (`VWvN7AWQc88fgSdV`, ativo), nó **"Edit Fields"**: atribuição fixa `session = "vetoros1-1"`. O nó "HTTP Request" usa `{{$json.session}}` no body para `http://waha:3000/api/sendText`;
  - workflow "My workflow" (`5yDW56CTIWuWLdeY`, **inativo**, teste manual): `"session": "vetoros1-1"` e um `chatId` fixo no body.
- **WAHA:** a sessão existe no volume `./volumes/waha` (não foi consultado nem alterado).

## 7. Como a sessão passou a ser resolvida

- No Laravel, a sessão vem **só** de `settings.company_whatsapp_session`, via `CompanyWhatsappSettings`. Não há nenhum valor fixo no código de negócio (o único `vetoros1-1` fica no placeholder do campo e nos dados de teste).
- Ela vai ao n8n no campo `session` do payload do webhook, junto com `provider` e `remetente_whatsapp`.
- **No n8n, a sessão efetivamente usada continua sendo a fixa** do nó "Edit Fields", porque o workflow ignora `body.session`. Isso não foi alterado, conforme a tarefa (ver item 9).

## 8. Testes executados e resultados

Ambiente: cópia do working tree na scratchpad, `composer install` em container descartável da imagem `infra-abrasil-abrasilsistema`, e testes em `docker run --rm --network none` com o SQLite em memória do `phpunit.xml`. Nenhum PHP rodou no container de produção, nenhum banco real foi acessado e nenhuma chamada real foi feita ao n8n/WAHA (`Http::fake()` + `Http::preventStrayRequests()`).

`tests/Feature/CompanyWhatsappSettingsTest.php`:

| Requisito | Teste |
|---|---|
| Configuração pode ser salva | `admin saves the company WhatsApp configuration` |
| Número normalizado | `company WhatsApp number is normalized to digits` (máscara, `+55`, já com 55, fixo) e `invalid company WhatsApp numbers are rejected` |
| Provider aceito | `only supported providers are accepted` (`evolution` recusado, `waha` aceito) |
| Session configurável | `the WAHA session is configurable and validated` |
| Obrigatoriedade ao habilitar | `number and session are required to enable the integration`, `the integration can be saved disabled without number and session` |
| Autorização | `readers cannot change the company WhatsApp` (403) |
| Painel sem segredos | `settings page shows the company WhatsApp status without secrets`, `settings page reports the integration as not configured by default` |
| Sessão da empresa usada no envio | `sending uses the configured company session and sender number` (payload exato) |
| Envio bloqueado se desabilitado | `sending is blocked when the company integration is disabled` (n8n não é chamado) |
| Lead não interfere na empresa (e vice-versa) | `lead WhatsApp and company WhatsApp do not interfere with each other` |
| Fluxo existente compatível | `without a saved configuration the existing n8n contract is kept`, e os 39 testes de `LeadWhatsappSendTest.php` rodaram **sem alteração** |

Resultados:

```text
CompanyWhatsappSettingsTest + LeadWhatsappSendTest     59 passed (217 assertions)
Suíte completa                                        167 passed, 1 failed (743 assertions)
Pint --test (7 arquivos PHP criados/alterados)        PASS
tsc --noEmit                                          OK
npm run build (após wayfinder:generate --with-form)   OK
git diff --check                                      OK
```

A falha é pré-existente e sem relação com esta etapa: `BlogTest > administrator can upload blog images` (`imagejpeg function is not defined`, GD da imagem sem JPEG).

ESLint/Prettier em `settings/leads.tsx`: o código novo está limpo. Ficam 4 erros de ESLint (linhas 12, 98, 99 e 111: `consistent-type-specifier-style`, `curly`, `padding-line` e `set-state-in-effect`) e o aviso de Prettier nos textos da extensão, que **já existiam no HEAD**. Não foram corrigidos para manter o diff mínimo.

## 9. O que ainda depende do n8n

1. **Sessão fixa no n8n.** O nó "Edit Fields" do workflow "CRM ABrasil - Enviar WhatsApp" continua com `session = "vetoros1-1"`. Para a configuração do painel valer de fato, esse campo precisa passar a ler o payload, mantendo o valor atual como fallback durante a transição:
   ```text
   session = {{ $json.body.session || 'vetoros1-1' }}
   ```
   Isso **não foi feito** (a tarefa proíbe alterar o n8n). Até lá, se o painel tiver uma sessão diferente de `vetoros1-1`, o n8n vai ignorá-la e continuar enviando por `vetoros1-1`.
2. **`remetente_whatsapp` e `provider`** são só informativos para o n8n hoje; nenhum nó os usa.
3. **Webhooks de entrada** (`waha-message-status` e `waha-message-received`) não recebem nem conferem a sessão. O ACK continua localizando a mensagem só pelo `provider_message_id`. Se houver mais de uma sessão/número no futuro, esses workflows vão precisar repassar `session` ao CRM.
4. **Status ao vivo da sessão** (WORKING/SCAN_QR, número realmente autenticado): exige que o Laravel consulte o WAHA (`GET /api/sessions/{session}`) com `WAHA_API_KEY`, que hoje não é repassada ao container `abrasilsistema`. Fica como próximo passo, e depende de mudar o compose.

## Deploy (NÃO executado)

```bash
cd /opt/infra-abrasil
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
```

Não há migration. Depois do deploy, o envio continua igual até um admin salvar a configuração em *Configurações → Leads → WhatsApp da empresa* (número autenticado no WAHA, provedor WAHA, sessão `vetoros1-1`, habilitado). Recomendo salvar exatamente `vetoros1-1` enquanto o n8n (item 9.1) não for ajustado.

---

# Etapa anterior: autenticação Header Auth do Laravel para o webhook do n8n

## Resumo

A correção pedida **já está no código**, no commit `c1d814b` ("Adiciona envio manual de WhatsApp pelo prospect via n8n com Header Auth"). Nesta etapa nenhum arquivo de código precisou mudar. O trabalho foi auditar a implementação contra os requisitos, rodar os testes e achar a causa do 403 em produção.

**Causa do 403:** o container em produção está desatualizado e o token não está configurado.

- `infra-abrasil-abrasilsistema-1` foi criado em 2026-09-23 16:17 UTC, **antes** do commit `c1d814b` (16:38 UTC). O `LeadWhatsappService` em execução **não tem** `withHeaders`, então chama o n8n sem o header.
- No container em execução, `N8N_WHATSAPP_WEBHOOK_HEADER` e `N8N_WHATSAPP_WEBHOOK_TOKEN` estão **vazias** (só a URL está definida).
- `/opt/infra-abrasil/.env` **não tem** `ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_TOKEN` (nem o `..._HEADER`).

Depois do rebuild e com o token no `.env`, o Laravel passa a enviar o header. Se o token continuar vazio, o novo código **não chama** o n8n e mostra "O envio de WhatsApp não está configurado. Avise o administrador do sistema."

## 1. Arquivos alterados

Nesta etapa só mudou `executed.md` (este relatório). A implementação auditada está no commit `c1d814b`:

| Arquivo | O que faz para a autenticação |
|---|---|
| `config/services.php` | Bloco `n8n` com `whatsapp_webhook_header` e `whatsapp_webhook_token` |
| `app/Services/LeadWhatsappService.php` | `->withHeaders([$header => $token])` na chamada. Sem header/token, não chama o n8n: gera um erro amigável e loga só o **nome** da variável que falta |
| `.env.example` | `N8N_WHATSAPP_WEBHOOK_HEADER=X-CRM-Token` e `N8N_WHATSAPP_WEBHOOK_TOKEN=` (vazio) |
| `tests/Feature/LeadWhatsappSendTest.php` | Testes de header, token, ausência e vazamento |
| `/opt/infra-abrasil/docker-compose.yml` (fora deste repositório) | Serviço `abrasilsistema` já repassa as variáveis (linhas 153–155) |

Checagem dos requisitos:

- n8n não foi alterado e a autenticação do webhook continua ativa.
- Não há token no código, só `env()`.
- O token não vai para o frontend: o controller só expõe `whatsappDestination`, e isso é coberto por teste.
- O token não vai para os logs. Os logs têm `lead_id`, o nome da variável ausente e o status/corpo da resposta do n8n; a URL do webhook é mascarada em erros de conexão.
- Fluxo funcional, endpoints de ACK/status e banco sem mudança. Nenhuma migration e nenhuma LeadActivity criada pelo Laravel.

## 2. Configuração adicionada

`config/services.php`:

```php
'n8n' => [
    'whatsapp_webhook_url' => env('N8N_WHATSAPP_WEBHOOK_URL'),
    'whatsapp_webhook_timeout' => (int) env('N8N_WHATSAPP_WEBHOOK_TIMEOUT', 20),
    'whatsapp_webhook_header' => env('N8N_WHATSAPP_WEBHOOK_HEADER', 'X-CRM-Token'),
    'whatsapp_webhook_token' => env('N8N_WHATSAPP_WEBHOOK_TOKEN'),
],
```

`LeadWhatsappService::send()`:

```php
Http::acceptJson()->asJson()
    ->withHeaders([$header => $token])
    ->connectTimeout(5)->timeout($timeout)
    ->post($url, ['prospect_id' => ..., 'nome' => ..., 'whatsapp' => ..., 'mensagem' => ...]);
```

## 3. Nome do header encontrado

**`X-CRM-Token`** é o padrão configurado. Esse nome vem do nome da credential informado no `correio.md` e **não foi confirmado** no n8n.

Na credential "Header Auth" do n8n, o nome do header é o campo **Name**, que fica cifrado junto com o valor (**Value**). O nome de exibição da credential ("X-CRM-Token") pode ser diferente dele. Para ler o campo seria preciso decriptar a credential, e o sandbox desta sessão bloqueou isso por envolver segredos. Não houve outra tentativa.

**Ação necessária:** abra a credential no editor do n8n e confira o campo **Name**. Se for diferente de `X-CRM-Token`, defina `ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_HEADER` com o nome correto. Nenhuma mudança de código é necessária.

## 4. Como configurar o docker-compose

Já está configurado em `/opt/infra-abrasil/docker-compose.yml`, serviço `abrasilsistema`. O `abrasilsistema-worker` e o `abrasilsistema-scheduler` herdam via `extends`:

```yaml
    environment:
      N8N_WHATSAPP_WEBHOOK_URL: ${ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_URL:-}
      N8N_WHATSAPP_WEBHOOK_HEADER: ${ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_HEADER:-X-CRM-Token}
      N8N_WHATSAPP_WEBHOOK_TOKEN: ${ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_TOKEN:-}
```

Nada a alterar no compose. Observação: o `env_file` do serviço é `./gateway/abrasilsistemas/.env.example`, versionado. O token **não** deve ser colocado nesse arquivo, só no `/opt/infra-abrasil/.env`. Como o `environment:` do compose tem precedência sobre o `env_file`, o valor do `.env` de infra prevalece.

## 5. Variáveis a adicionar em `/opt/infra-abrasil/.env`

O `.env` de produção não foi modificado. Adicione manualmente:

```dotenv
ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_HEADER=X-CRM-Token
ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_TOKEN=<mesmo valor do campo "Value" da credential Header Auth no n8n>
```

`ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_URL` já está definida no container em execução. Confirme que o valor é `https://n8n.abrasilsistemas.com.br/webhook/crm/send-whatsapp`.

## 6. Testes executados e resultados

Ambiente: `git archive HEAD` copiado para a scratchpad. `composer install` (com dev) rodou em container descartável da imagem `infra-abrasil-abrasilsistema`, e os testes rodaram em `docker run --rm --network none` com o SQLite em memória do `phpunit.xml`. Nenhum PHP/Artisan rodou no host ou no container de produção, e nenhuma chamada real foi feita ao n8n (`Http::fake()` + `Http::preventStrayRequests()`).

O que `tests/Feature/LeadWhatsappSendTest.php` cobre, conforme pedido:

| Requisito | Teste |
|---|---|
| Header correto e token correto enviados | `authorized user sends the WhatsApp through the n8n webhook` (`hasHeader('X-CRM-Token', token)`) |
| Nome do header vem da config | `the webhook auth header name comes from configuration` |
| Token (ou header) ausente: n8n não é chamado, sem LeadActivity, log sem token | `missing webhook auth does not call n8n nor leak anything` (dataset token/header) |
| Token/header fora das props Inertia e do HTML | `lead edit page exposes only the normalized destination, never internal URLs or tokens` |
| Token fora das mensagens de erro e dos logs no 403 do n8n | `n8n rejecting the auth (403) shows a friendly error without the token` |
| Fluxo de sucesso e payload exato `prospect_id`, `nome`, `whatsapp`, `mensagem` | `authorized user sends...` (`$request->data() === [...]`) |
| Nenhuma LeadActivity extra | `the web controller does not create a LeadActivity of its own` |

Resultados:

```text
tests/Feature/LeadWhatsappSendTest.php                        39 passed (141 assertions)
LeadWhatsappSend + LeadHistoryWhatsappStatus + WhatsappMessageStatusApi
                                                              75 passed (362 assertions)
Suíte completa                                                147 passed, 1 failed (667 assertions)
Pint --test (5 arquivos PHP da integração)                    PASS
git diff --check HEAD~1 HEAD                                  OK
```

A falha é pré-existente e não tem relação com esta etapa: `BlogTest > administrator can upload blog images`, com `imagejpeg function is not defined` (o GD da imagem não tem suporte a JPEG).

TypeScript/build: não foi executado porque nenhum arquivo de frontend mudou nesta etapa. O último build, na etapa anterior, passou em `tsc --noEmit` e `npm run build`.

## 7. Comandos para deploy (NÃO executados)

```bash
# 1. Confira o campo "Name" da credential Header Auth no editor do n8n (ver item 3).

# 2. Adicione ao /opt/infra-abrasil/.env (manualmente, sem commitar):
#    ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_HEADER=X-CRM-Token
#    ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_TOKEN=<valor da credential>

# 3. Rebuild e recriação (o código em produção é anterior ao commit c1d814b):
cd /opt/infra-abrasil
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 4. Conferência sem imprimir o token:
docker exec infra-abrasil-abrasilsistema-1 php artisan tinker --execute="var_dump(config('services.n8n.whatsapp_webhook_header'), filled(config('services.n8n.whatsapp_webhook_token')));"
docker exec infra-abrasil-abrasilsistema-1 grep -c withHeaders app/Services/LeadWhatsappService.php
```

Não há migration a rodar. O workflow "CRM ABrasil - Enviar WhatsApp" precisa estar **ativo** no n8n para a URL `/webhook/...` responder.

---

# Etapa anterior: envio manual de WhatsApp pela tela do prospect

## Resumo

A página `leads/edit` ganhou o botão **Enviar WhatsApp**, que abre um dialog para escrever a mensagem. O navegador chama só a rota web autenticada do Laravel. O Laravel monta o payload a partir do Lead da rota e chama o webhook do n8n pelo servidor. O n8n segue o fluxo já validado (WAHA, depois `POST /api/prospects/{lead}/whatsapp/log`) e é ele quem cria a `LeadActivity`. O controller web não grava histórico.

Não foram alterados: os endpoints `POST /api/prospects/{lead}/whatsapp/log` e `PATCH /api/whatsapp/messages/{providerMessageId}/status`, o n8n, o WAHA, o banco (nenhuma migration) nem o `.env` de produção. Não houve deploy.

## Arquitetura encontrada (auditoria)

| Item | Onde / como |
|---|---|
| Página do prospect | `resources/js/pages/leads/edit.tsx`: formulário do Lead, card "Dados da prospecção", card "Registrar contato" e card "Histórico" (que já usa `WhatsappMessageStatus`) |
| Controller | `LeadController::edit()` envia `lead` (com `activities.user`) via Inertia e oculta `provider_message_id` |
| Rotas web do Lead | `routes/web.php`, grupo `auth` + `verified` + `admin`: `leads.*`, `leads.status`, `leads.bulk`, `leads.activities.store` |
| Autorização | Não há Policies. O acesso ao CRM é pelo middleware `admin` (`EnsureUserIsAdmin`, `role === 'admin'`), e todo admin acessa todos os Leads. Usuários `reader` recebem 403 |
| Campo de WhatsApp | `leads.whatsapp` (string). `LeadRequest` e o import CSV guardam só os dígitos. O import da API (`ProspectImportController`) grava como veio e usa o `phone` quando `whatsapp` está vazio |
| Normalização existente | `whatsappUrl()` em `resources/js/pages/leads/index.tsx` (link wa.me): só dígitos e prefixo `55` quando não começa com `55` |
| Padrão de chamadas | Componente `<Form>` do Inertia v3 com rotas Wayfinder (`@/routes/...`), `disableWhileProcessing` e `InputError`. Dialog: `@/components/ui/dialog` (padrão de `delete-user.tsx`) |
| Integrações HTTP | Só `app/Services/MercadoPagoService.php` (SDK). Não havia service para n8n |
| `config/services.php` | Tinha `mercadopago` e `ab_prospect.token`. **Não existia** configuração de URL do n8n |
| Feedback na UI | `Inertia::flash('toast', ['type', 'message'])` exibido por `useFlashToast` (sonner). Erros de validação vão para `errors.*` do `<Form>` |

## Arquivos criados

- `app/Services/LeadWhatsappService.php`: normalização do número e chamada HTTP ao n8n.
- `app/Services/LeadWhatsappException.php`: exceção com mensagem amigável ao operador.
- `app/Http/Controllers/LeadWhatsappController.php`: ação `store`.
- `app/Http/Requests/Leads/SendLeadWhatsappRequest.php`
- `resources/js/components/send-lead-whatsapp-dialog.tsx`: componente `SendLeadWhatsappDialog`.
- `tests/Feature/LeadWhatsappSendTest.php`

## Arquivos alterados

- `routes/web.php`: nova rota.
- `app/Http/Controllers/LeadController.php`: `edit()` passa a enviar a prop `whatsappDestination`.
- `config/services.php`: bloco `n8n`.
- `.env.example`: `N8N_WHATSAPP_WEBHOOK_URL=`.
- `resources/js/pages/leads/edit.tsx`: botão/dialog no cabeçalho do card "Histórico" e prop `whatsappDestination`.
- `executed.md`: este relatório.

## Rota criada

```text
POST /leads/{lead}/whatsapp   name: leads.whatsapp.store
middleware: web, auth, verified, admin, throttle:10,1
```

A rota fica dentro do grupo `admin` existente, usa route model binding (`{lead}`) e não usa `prospect.token`.

## Request criado

`SendLeadWhatsappRequest`: `message` com `required`, `string` e `max:4000`, e mensagens em português. O `TrimStrings`/`ConvertEmptyStringsToNull` do Laravel faz uma mensagem só com espaços cair em `required`. O telefone, o nome e o id **não** são recebidos do frontend. Campos extras (`prospect_id`, `lead_id`, `nome`, `whatsapp`) são ignorados.

## Service criado

`LeadWhatsappService`:

- `normalizeNumber(?string): ?string` (estático):
  - remove tudo que não é dígito;
  - com 10 ou 11 dígitos (DDD + número), prefixa `55`;
  - com 12 ou 13 dígitos começando com `55`, mantém como está;
  - em qualquer outro caso, retorna `null` (número inválido).
- `send(Lead, string $message)`: valida o número, lê a URL da config, faz o `POST` com Laravel HTTP Client (`acceptJson`, `asJson`, `connectTimeout(5)`, `timeout(20)`, **sem retry** para não duplicar mensagem) e interpreta a resposta.

A regra de número é a mesma do link wa.me da listagem, com uma correção: um número local de **DDD 55** (Santa Maria/RS, ex. `55999998888`) começa com `55` e o link wa.me não o prefixava. Aqui ele é tratado pelo tamanho (11 dígitos) e vira `5555999998888`.

## Configuração adicionada

`config/services.php`:

```php
'n8n' => [
    'whatsapp_webhook_url' => env('N8N_WHATSAPP_WEBHOOK_URL'),
    'whatsapp_webhook_timeout' => (int) env('N8N_WHATSAPP_WEBHOOK_TIMEOUT', 20),
],
```

`.env.example`: `N8N_WHATSAPP_WEBHOOK_URL=` (vazio, sem segredo). `N8N_WHATSAPP_WEBHOOK_TIMEOUT` é opcional e ficou fora do `.env.example` de propósito. Esse arquivo é o `env_file` do container em produção, e um valor vazio viraria timeout 0. Mesmo assim, o service usa 20 s quando o valor não é positivo.

Se a URL estiver vazia, o envio não é feito: o operador vê "O envio de WhatsApp não está configurado…" e o problema vai para o log.

## Variável `.env` necessária em produção

```text
N8N_WHATSAPP_WEBHOOK_URL=<URL de produção do webhook do workflow "CRM ABrasil - Enviar WhatsApp">
```

**A URL não pôde ser determinada de forma inequívoca**, então não foi inventada:

- O projeto não tem nenhuma referência ao webhook de envio (código, `.env.example`, docs).
- Na infra, `N8N_APP_URL=https://n8n.abrasilsistemas.com.br`. A URL de produção de um webhook n8n segue o formato `https://n8n.abrasilsistemas.com.br/webhook/<path>`.
- O banco do n8n (consulta somente leitura) mostra o workflow **"CRM ABrasil - Enviar WhatsApp" como inativo** (`active = 0`). O único webhook de produção registrado é `waha-message-status`, do workflow de ACK. O `<path>` do webhook de envio não foi lido: a inspeção dos nós do workflow foi bloqueada pela política de permissões desta sessão.
- Como alternativa, o container do Laravel está na mesma rede Docker do n8n, então `http://n8n:5678/webhook/<path>` também funciona sem sair para a internet.

**Como a variável chega ao container:** o serviço `abrasilsistema` do `/opt/infra-abrasil/docker-compose.yml` usa `env_file: ./gateway/abrasilsistemas/.env.example` e um bloco `environment`. Ele **não** lê o `/opt/infra-abrasil/.env` diretamente. Só colocar a variável no `.env` da infra não basta: ela precisa ser repassada no compose. Sugestão (não aplicada):

```yaml
# docker-compose.yml, serviço abrasilsistema, em environment:
      N8N_WHATSAPP_WEBHOOK_URL: ${ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_URL:-}
```

```text
# /opt/infra-abrasil/.env
ABRASILSISTEMA_N8N_WHATSAPP_WEBHOOK_URL=https://n8n.abrasilsistemas.com.br/webhook/<path-do-workflow>
```

## Contrato enviado ao n8n

`POST <N8N_WHATSAPP_WEBHOOK_URL>` com `Content-Type: application/json`:

```json
{
    "prospect_id": 118,
    "nome": "Assistência Técnica ABC",
    "whatsapp": "5551999998888",
    "mensagem": "Texto da mensagem"
}
```

- `prospect_id` = `$lead->id` do route model binding
- `nome` = `$lead->company_name`
- `whatsapp` = `$lead->whatsapp` normalizado
- `mensagem` = `message` validada

Resposta esperada: `{"success": true, "message": "...", "message_id": "3EB0...", "status": "PENDING", "activity_id": 11}`.

## Tratamento de erros

Todos os erros voltam como erro de validação no campo `whatsapp` (ou `message`), exibidos dentro do dialog, que continua aberto. Nenhum stack trace ou detalhe interno chega ao usuário. O log registra só `lead_id`, status HTTP e até 500 caracteres do corpo. Tokens e a URL do webhook ficam fora do log: nas falhas de conexão, a URL é trocada por `[n8n-webhook]`.

| Situação | n8n é chamado? | Mensagem ao operador |
|---|---|---|
| Mensagem vazia / só espaços / ausente | não | Escreva a mensagem que será enviada. |
| Mensagem > 4000 caracteres | não | A mensagem deve ter no máximo 4000 caracteres. |
| Prospect sem WhatsApp | não | Este prospect não possui WhatsApp cadastrado. |
| Número inválido | não | O WhatsApp cadastrado neste prospect não é um número válido. |
| URL não configurada | não | O envio de WhatsApp não está configurado. Avise o administrador do sistema. |
| Timeout | sim | O serviço de WhatsApp demorou para responder. Confira o histórico antes de reenviar para evitar mensagem duplicada. |
| n8n indisponível (conexão recusada/DNS) | sim | Não foi possível conectar ao serviço de WhatsApp. Tente novamente em instantes. |
| HTTP 4xx/5xx | sim | O serviço de WhatsApp recusou o envio. Tente novamente em instantes. |
| Resposta não JSON / sem `success` | sim | O serviço de WhatsApp retornou uma resposta inesperada. Confira o histórico antes de reenviar. |
| `success` diferente de `true` | sim | A mensagem não pôde ser enviada pelo WhatsApp. Verifique o número e tente novamente. |
| Mais de 10 envios/minuto pelo mesmo usuário | não | HTTP 429 (throttle) |

No timeout e na resposta inválida, o aviso pede para conferir o histórico: a mensagem pode ter saído mesmo sem confirmação, e por isso não há retry automático.

## Autorização aplicada

- `auth` + `verified` + `admin`, a mesma regra de todas as rotas do CRM. Usuário não autenticado é redirecionado ao login. Usuário `reader` recebe 403.
- O Lead vem só do route model binding (Leads com soft delete dão 404). Nenhum `lead_id`, telefone ou nome do body é usado.
- `throttle:10,1`: 10 envios por minuto por usuário autenticado. Não existe envio em massa.
- Não há exposição ao frontend: token `AB_PROSPECT_API_TOKEN`, credenciais WAHA e URL do n8n não viram props Inertia. A única prop nova é `whatsappDestination` (o número já normalizado), para o operador conferir o destino.

## Alterações na interface

- No cabeçalho do card **Histórico** há o botão **Enviar WhatsApp** (ícone `MessageCircle`). Ele não abre o WhatsApp Web.
- Sem WhatsApp válido, o botão aparece desabilitado com a dica "Cadastre um WhatsApp válido para enviar mensagens".
- Dialog (`@/components/ui/dialog`, mesmo padrão de `delete-user.tsx`):
  - título "Enviar WhatsApp" e uma descrição curta;
  - bloco discreto com **Cliente** (`company_name`) e **Destino** (formatado, ex. `(51) 99999-8888`);
  - textarea **Mensagem** com contador `n/4000` (`maxLength` 4000);
  - erros `errors.message` e `errors.whatsapp`;
  - botões **Cancelar** e **Enviar WhatsApp**.
- Durante o envio:
  - o `<Form>` usa `disableWhileProcessing`;
  - os dois botões ficam desabilitados, o que impede duplo clique;
  - o botão mostra spinner + **"Enviando..."**;
  - o envio também fica desabilitado com a mensagem vazia.

## Comportamento após sucesso

- O dialog fecha e a mensagem é limpa.
- Aparece o toast de sucesso "Mensagem enviada para o WhatsApp." (flash Inertia).
- Recarga parcial: o `<Form>` usa `only: ['lead']`, `preserveState` e `preserveScroll`. Só a prop `lead` (com `activities`) é recarregada, sem reload completo. Edições não salvas no formulário do Lead e a rolagem são preservadas.
- A atividade criada pelo n8n aparece no Histórico com o `WhatsappMessageStatus` existente (Enviando, depois Enviada/Entregue/Lida…). O componente de status não foi duplicado.
- O n8n registra a atividade antes de responder. Se o workflow registrar só depois, ela aparece na próxima visita à página. Não há polling nem WebSocket.

## Testes executados

`tests/Feature/LeadWhatsappSendTest.php`: 35 testes com datasets, todos com `Http::fake()` e `Http::preventStrayRequests()`, sem nenhuma chamada real ao n8n/WAHA. Cobrem:

- autenticação obrigatória;
- `reader` recebe 403 sem chamar o n8n;
- envio com sucesso, redirect e toast;
- payload exato (URL, método, `prospect_id`, `nome`, `whatsapp` normalizado, `mensagem`);
- `prospect_id`/nome/destino vêm da rota mesmo com `prospect_id`, `lead_id`, `nome` e `whatsapp` forjados no body;
- normalização (celular, fixo, já com 55, com máscara, DDD 55);
- Lead sem WhatsApp (`null`, `''`) e número inválido, sem chamar o n8n;
- mensagem vazia, só espaços, ausente e longa demais;
- timeout, conexão recusada, HTTP 500/502/404/422 (sem vazar o corpo), `success=false`, resposta HTML, vazia e JSON sem `success`;
- URL não configurada;
- throttle (o 11º envio no mesmo minuto dá 429);
- nenhuma `LeadActivity` criada pelo controller web;
- a página `leads/edit` expõe `whatsappDestination` normalizado e o HTML/props **não** contêm a URL do webhook, o path dele nem o token `AB_PROSPECT_API_TOKEN`;
- `whatsappDestination = null` sem WhatsApp.

## Resultado dos testes

```text
tests/Feature/LeadWhatsappSendTest.php     35 passed (121 assertions)
Suíte completa                             143 passed, 1 failed (647 assertions)
Pint --test (PHP criados/alterados + teste) PASS (8 arquivos)
git diff --check                           sem problemas
```

A única falha é pré-existente e não tem relação com esta etapa: `BlogTest > administrator can upload blog images` (`imagejpeg function is not defined`, GD da imagem sem JPEG).

## Resultado do TypeScript/build

Reproduzindo o `Dockerfile`: `php artisan wayfinder:generate --with-form` (gera `@/routes/leads/whatsapp`) e depois `SKIP_WAYFINDER_GENERATE=1 npm run build` em `node:22-bookworm-slim`.

- `tsc --noEmit`: OK
- `npm run build`: OK (só o aviso de chunk > 500 kB, que já existia)
- ESLint/Prettier em `send-lead-whatsapp-dialog.tsx`: OK
- `edit.tsx`: o import novo segue o `import/order`. Continuam os 3 erros de ESLint (`import/order`/`consistent-type-specifier-style` em `@/routes/leads` e `@/pages/leads/form`) e o aviso de Prettier que **já existiam no HEAD**. Não foram corrigidos para manter o diff mínimo.

### Ambiente

Tudo rodou numa cópia do projeto na scratchpad:

- PHP: container descartável `docker run --rm --network none` da imagem `infra-abrasil-abrasilsistema`, com `composer install` (inclui dev) e SQLite em memória do `phpunit.xml`;
- build: `node:22-bookworm-slim`.

Nada foi copiado para o container de produção `infra-abrasil-abrasilsistema-1`. Nenhum banco real foi acessado e nenhum comando destrutivo foi executado. O banco do n8n só foi aberto em modo somente leitura para listar workflows e webhooks.

## Comandos necessários para deploy (não executados)

1. Commitar as alterações.
2. Configurar a URL (seção "Variável `.env` necessária em produção"): repassar `N8N_WHATSAPP_WEBHOOK_URL` no `docker-compose.yml` e definir o valor no `/opt/infra-abrasil/.env`.
3. No n8n, **ativar** o workflow "CRM ABrasil - Enviar WhatsApp" para a URL `/webhook/<path>` responder em produção.
4. Rebuild e recriação dos serviços do compose (nomes conferidos no `docker-compose.yml`):

```bash
cd /opt/infra-abrasil
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
```

5. Conferir:

```bash
docker exec infra-abrasil-abrasilsistema-1 php artisan route:list --path=leads/{lead}/whatsapp
docker exec infra-abrasil-abrasilsistema-1 php artisan tinker --execute="var_dump(filled(config('services.n8n.whatsapp_webhook_url')));"
```

Não há migration a rodar. O mesmo rebuild também publica as etapas anteriores ainda não implantadas (ACK e status no histórico), se for o caso.

## Pendências encontradas

1. **URL do webhook de envio**: o path não foi identificado no projeto. Precisa ser preenchido com a URL de produção do webhook do workflow "CRM ABrasil - Enviar WhatsApp".
2. **Workflow de envio inativo no n8n** (`active = 0`). Até ser ativado, só a URL de teste (`/webhook-test/...`, com o editor "escutando") responde, e o envio pelo CRM vai falhar com "recusou o envio" (404).
3. **Autenticação do webhook**: não se sabe se o nó Webhook do n8n exige header/credencial. Se exigir, falta configurar isso (hoje o Laravel envia sem autenticação, conforme o contrato descrito).
4. **Resposta do n8n**: o Laravel exige JSON com `success: true`. Se o nó "Respond to Webhook" não estiver no fim do workflow (resposta padrão "Workflow was started"), o operador verá "resposta inesperada".
5. **`env_file` de produção é o `.env.example` do repositório**: mudanças nesse arquivo afetam o container. Vale revisar esse arranjo no compose (ex. `AB_PROSPECT_API_TOKEN` também vem dele).
6. O ESLint/Prettier de `edit.tsx` tem problemas pré-existentes (ver acima). O `BlogTest` de upload JPEG falha por falta de suporte JPEG no GD da imagem.

---

# Etapa anterior: status das mensagens WhatsApp no histórico do prospect

## Resumo

O histórico do Lead (`leads/edit`) passou a exibir, só nas atividades `type = whatsapp`, um indicador discreto com o estado da mensagem (ícone + texto traduzido). Não foram alterados o fluxo de envio, n8n, WAHA, a lógica de ACK nem o banco (nenhuma migration). Não há atualização em tempo real: o status atualizado aparece ao recarregar/revisitar a página.

## Arquivos analisados

- `app/Http/Controllers/LeadController.php` (`edit()`: carrega `activities` + `user` e envia via Inertia para `leads/edit`)
- `app/Models/LeadActivity.php`
- `resources/js/pages/leads/edit.tsx` (card "Histórico", que renderiza as atividades)
- `resources/js/pages/leads/form.tsx` (tipos `Lead` e `LeadActivity`)
- `package.json` (sem infraestrutura de testes frontend; `lucide-react` já é usado)

Antes desta etapa, as atividades WhatsApp eram exibidas como qualquer outra: rótulo do tipo, responsável, data, descrição e metadados (status comercial, contato, follow-up).

## Arquivos criados

- `resources/js/components/whatsapp-message-status.tsx`: componente `WhatsappMessageStatus`
- `tests/Feature/LeadHistoryWhatsappStatusTest.php`

## Arquivos alterados

- `app/Http/Controllers/LeadController.php`: `edit()` oculta `provider_message_id` das atividades enviadas ao frontend.
- `resources/js/pages/leads/form.tsx`: tipo `LeadActivity` recebeu `message_status?: WhatsappMessageStatusValue | null`.
- `resources/js/pages/leads/edit.tsx`: renderiza `<WhatsappMessageStatus>` abaixo da descrição, apenas quando `activity.type === 'whatsapp'`.
- `executed.md`: este relatório.

## Onde `message_status` chega ao frontend

Em `LeadController::edit()`, na prop Inertia `lead.activities[*].message_status`. A coluna já vinha no `load()` das atividades; agora `provider_message_id` é removido com `makeHidden`, então o ID do WAHA não vai para o navegador.

## Componente de apresentação

`WhatsappMessageStatus` (`resources/js/components/whatsapp-message-status.tsx`) recebe `status` e cuida da tradução, do ícone (lucide-react, já instalado), da cor e do `title` (tooltip "Status da mensagem: …"). O estado é comunicado por ícone **e** texto, não só por cor.

| `message_status` | Texto exibido | Ícone | Destaque |
|---|---|---|---|
| `PENDING` | Enviando | Clock | cinza (`text-muted-foreground`) |
| `SERVER` | Enviada | Check | cinza |
| `DEVICE` | Entregue | CheckCheck | cinza |
| `READ` | Lida | CheckCheck | azul (`text-sky-600` / `dark:text-sky-400`), negrito médio |
| `PLAYED` | Reproduzida | CheckCheck | azul, negrito médio |
| `ERROR` | Erro no envio | AlertCircle | `text-destructive`, negrito médio |

Os códigos técnicos nunca aparecem na tela.

## Comportamento para `null` e valores desconhecidos

`message_status` `null` (atividades WhatsApp antigas) ou com valor desconhecido: o componente retorna `null` e nada é exibido; o restante da atividade aparece normalmente. Atividades que não são WhatsApp não renderizam o componente.

## Testes executados

`tests/Feature/LeadHistoryWhatsappStatusTest.php` (9 testes):

- página `leads/edit` expõe `message_status` para `PENDING`, `SERVER`, `DEVICE`, `READ`, `PLAYED` e `ERROR` (dataset de `LeadActivity::MESSAGE_STATUSES`), sem `provider_message_id`;
- atividade WhatsApp antiga com `message_status = null`;
- atividade que não é WhatsApp (`note`) sem status técnico;
- histórico misto (WhatsApp `READ` + nota) na ordem correta, com os status.

A tradução/ícone é feita no frontend. Como o projeto não tem infraestrutura de testes frontend, nenhuma foi criada. O mapeamento está numa única tabela no componente e foi validado por `tsc`.

Resultados (container descartável, ver abaixo):

```text
LeadHistoryWhatsappStatus + WhatsappMessageStatusApi + WhatsappActivityApi + LeadManagement   62 passed (363 assertions)
Suíte completa                                         108 passed, 1 failed (526 assertions)
Pint --test (PHP alterados/criados)                    PASS
git diff --check                                       sem problemas
```

A única falha é pré-existente e não relacionada: `BlogTest` — `imagejpeg function is not defined` (GD da imagem sem JPEG).

## Build do frontend

Reproduzindo o `Dockerfile`: `php artisan wayfinder:generate --with-form` na imagem `infra-abrasil-abrasilsistema` e depois `SKIP_WAYFINDER_GENERATE=1 npm run build` em `node:22-bookworm-slim`.

- `npm run build`: OK (só o aviso de chunk > 500 kB, que já existia)
- `tsc --noEmit`: OK
- ESLint e Prettier nos arquivos novos (`whatsapp-message-status.tsx`, `form.tsx`): OK
- `edit.tsx` tem erros de ESLint (`import/order`) e de Prettier que **já existiam no HEAD**. A formatação do arquivo não foi alterada para manter o diff mínimo.

### Ambiente

Tudo rodou em uma cópia do projeto na scratchpad, montada em containers `docker run --rm --network none` (imagem do projeto para PHP, `node:22-bookworm-slim` para o build), com SQLite em memória do `phpunit.xml`. Nada foi copiado para o container de produção `infra-abrasil-abrasilsistema-1`, nenhum banco real foi acessado e nenhum comando destrutivo foi executado.

## Produção

**Rebuild/redeploy da imagem é necessário**: o código (incluindo os assets do Vite) é embutido na imagem. Não foi feito automaticamente. Não há migration a rodar. O mesmo rebuild também publica a rota de ACK da etapa anterior (abaixo), que ainda não foi commitada nem implantada.

---

# Etapa anterior — ACK de mensagens WhatsApp (WAHA `message.ack`)

## Resumo

Criado endpoint para o n8n atualizar **somente** `lead_activities.message_status` de uma mensagem WhatsApp já registrada, localizada pelo `provider_message_id` já normalizado (ex.: `3EB05962A724B2EB80AD88`). O Laravel não processa o payload bruto do WAHA.

Nenhuma migration foi criada: as colunas `provider_message_id` e `message_status` já existem. O endpoint de criação (`POST /api/prospects/{lead}/whatsapp/log`) não foi alterado. Nenhuma interface foi alterada.

## Arquivos criados

- `app/Http/Controllers/Api/WhatsappMessageStatusController.php`
- `app/Http/Requests/Api/UpdateWhatsappMessageStatusRequest.php`
- `tests/Feature/WhatsappMessageStatusApiTest.php`

## Arquivos alterados

- `app/Models/LeadActivity.php`: constantes `MESSAGE_STATUS_*`, `MESSAGE_STATUS_PROGRESSION`, `MESSAGE_STATUSES` e o método `canTransitionMessageStatusTo()` (lista e regra centralizadas no model).
- `routes/api.php`: nova rota.
- `executed.md`: este relatório.

## Rota

```text
PATCH /api/whatsapp/messages/{providerMessageId}/status
```

- Nome: `api.whatsapp.messages.status`
- Middleware: `throttle:120,1`, `prospect.token` (mesmo Bearer token da importação e do log de envio)
- Sem autenticação de usuário do painel.
- Throttle maior que o das outras rotas (30/min), pois cada mensagem gera vários ACKs.

## JSON esperado

```json
{ "status": "DEVICE" }
```

Valores aceitos: `PENDING`, `SERVER`, `DEVICE`, `READ`, `PLAYED`, `ERROR`. O valor é normalizado para maiúsculas (`device` é aceito). Status comerciais (`Lead::STATUSES`) são rejeitados.

## Exemplo curl

```bash
curl -X PATCH "https://<host>/api/whatsapp/messages/3EB05962A724B2EB80AD88/status" \
  -H "Authorization: Bearer <PROSPECT_API_TOKEN>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"status":"DEVICE"}'
```

No n8n, o ID pode ser extraído do `payload.id` composto pegando o trecho após o último `_`:

```js
{{ $json.payload.id.split('_').pop() }}   // -> 3EB05962A724B2EB80AD88
```

e o status vem de `payload.ackName`.

## Respostas possíveis

| HTTP | Situação | Corpo |
|------|----------|-------|
| 200 | Status atualizado | `{"success":true,"message":"Status da mensagem atualizado.","activity_id":6,"message_status":"DEVICE"}` |
| 200 | Mesmo status já gravado | `{"success":true,"message":"Status já estava atualizado.","activity_id":6,"message_status":"DEVICE","idempotent":true}` |
| 200 | ACK fora de ordem/regressão (não altera nada) | `{"success":true,"message":"Status ignorado para evitar regressão.","activity_id":6,"message_status":"READ","ignored":true}` |
| 401 | Token ausente/inválido | `{"message":"Token inválido."}` |
| 404 | `provider_message_id` inexistente ou atividade não é `whatsapp` | `{"success":false,"message":"Mensagem WhatsApp não encontrada."}` |
| 422 | `status` ausente ou inválido | erros de validação padrão do Laravel |
| 429 | Throttle excedido | padrão do Laravel |

A regressão retorna 200 (e não 409) de propósito: ACK repetido/fora de ordem é esperado e não deve fazer o workflow do n8n falhar ou reenviar. O campo `message_status` da resposta sempre reflete o valor efetivamente gravado.

## Regra de progressão dos ACKs

Implementada em `LeadActivity::canTransitionMessageStatusTo()`:

1. Progressão linear: `PENDING → SERVER → DEVICE → READ → PLAYED`. Só avança; saltos são permitidos (ex.: `PENDING → READ`, se ACKs intermediários se perderem). Nunca retrocede (`READ ↛ DEVICE`, `DEVICE ↛ SERVER`, etc.).
2. `ERROR` fica fora da progressão:
   - aceito apenas enquanto a mensagem não foi confirmada no aparelho (`PENDING` ou `SERVER`);
   - ignorado se a atividade já está em `DEVICE`, `READ` ou `PLAYED` (um erro tardio não desfaz uma entrega comprovada).
3. A partir de `ERROR`, um ACK positivo (`SERVER`, `DEVICE`, `READ`, `PLAYED`) é aceito, pois comprova que a mensagem avançou; `ERROR → PENDING` é ignorado.
4. Se `message_status` atual for `null` (ou valor desconhecido), qualquer status válido é aceito.
5. Mesmo status → resposta idempotente, sem gravação.

Apenas `message_status` (e o `updated_at` automático do Eloquent) é alterado. `status`, `contacted_at`, `next_follow_up_at`, `description`, `user_id`, `provider_message_id` da atividade e `status`/`last_contacted_at` do Lead não são tocados.

Se houver mais de uma atividade WhatsApp com o mesmo `provider_message_id` (não esperado), a mais recente é atualizada.

## Testes executados

Arquivo novo: `tests/Feature/WhatsappMessageStatusApiTest.php` — cobre token ausente, token inválido, payloads inválidos (ausente, desconhecido, status comercial, não string), ID inexistente, atividade não WhatsApp, `PENDING→SERVER`, `SERVER→DEVICE`, `DEVICE→READ`, `READ→PLAYED`, salto `PENDING→READ`, status minúsculo, bloqueio de `READ→DEVICE`, `DEVICE→SERVER`, `READ→PENDING`, `PLAYED→READ`, idempotência, preservação dos campos da atividade e do status comercial do Lead, e todas as regras de `ERROR`.

Resultados:

```text
tests/Feature/WhatsappMessageStatusApiTest.php                     27 passed (88 assertions)
WhatsappMessageStatus + WhatsappActivity + ProspectImport + LeadManagement   57 passed (253 assertions)
Suite completa                                                     99 passed, 1 failed
Pint --test nos arquivos alterados                                 PASS
git diff --check                                                   sem problemas
```

A única falha da suíte completa é pré-existente e não relacionada: `BlogTest > administrator can upload…` — `imagejpeg function is not defined` (a extensão GD da imagem não tem suporte a JPEG).

### Observação sobre o ambiente de testes

O container `infra-abrasil-abrasilsistema-1` **não** monta o código-fonte (o código está embutido na imagem) e a imagem é de produção, sem dependências de dev (sem Pest), então `docker exec … php artisan test` responde `Command "test" is not defined`. Copiar arquivos para dentro do container em execução colocaria a rota em produção sem rebuild, por isso não foi feito.

Os testes foram executados em um container **descartável** (`docker run --rm`) da mesma imagem `infra-abrasil-abrasilsistema`, sobre uma cópia do projeto na scratchpad com `composer install` (incluindo dev), usando SQLite em memória do `phpunit.xml`. Nenhum banco real foi acessado e nenhum comando destrutivo foi executado.

## Para colocar em produção

A rota só fica ativa após rebuild/redeploy da imagem:

Faça o rebuild pelo processo de deploy de costume (os nomes dos serviços do compose não foram verificados aqui) e depois confirme:

```bash
docker exec infra-abrasil-abrasilsistema-1 php artisan route:list --path=api/whatsapp
```

Não há migration a executar.
