# Execução de `correio.md` — ABRASIL-COMERCIAL-02 (conversão, SEO e preparação para divulgação)

Data: 23/11/2026. O `correio.md` é diferente do anterior (o último executado foi ABRASIL-COMERCIAL-01) e foi executado.
**Só no projeto local:** sem commit, push, deploy, containers, dependências novas ou alteração de outros sistemas. O VetorLeads e a infra foram apenas lidos.

**Situação:** código integrado ao `origin/main` (`84ad8c8`) e pronto para uma publicação controlada, sem commit. A divulgação ainda depende das pendências 1 a 4.

> **Correção:** a primeira versão deste relatório dava o código como pronto sem tê-lo comparado com o `main` do GitHub, que tinha 10 commits a mais.

## Integração com o `main`

A branch local avançou até o `origin/main` (sem commit novo) e o trabalho foi reaplicado por cima. Oito conflitos resolvidos:

| Arquivo | Resolução |
|---|---|
| `welcome.tsx`, `contato.tsx` | Estrutura da COMERCIAL-01/02 com as cores do `main` |
| `blog/index.tsx` | Ícone e cores do `main`; SEO vindo do painel |
| `blog/show.tsx` | Chamada para serviço e orçamento, com as cores do `main` (sai o link de WhatsApp) |
| `home-carousel.tsx` | Removido: a home usa o hero fixo, então os ajustes do `main` nesse carrossel deixam de valer |
| `BlogSeoDraftsCommand.php` | 15 pautas: as 5 de serviços do `main`, as 5 comerciais e as 5 de SEO. Sem título, endereço ou palavra-chave repetidos |
| `correio.md`, `executed.md` | Versão atual; as anteriores ficam no histórico do Git |

A paleta do `main` foi aplicada também nos arquivos novos (formulário, slider, hero e artigo no HTML inicial). Mantidos: foto do login, VetorChat à direita e rascunhos do blog.

## 1. Etapa anterior

COMERCIAL-01 conferida e preservada. Problemas comprovados e corrigidos:

| Problema | Correção |
|---|---|
| Quem informasse o e-mail ou telefone de outra pessoa reabria a solicitação dela, trocava o serviço e completava os contatos | Nova regra de deduplicação (§2) |
| Uma campanha posterior se misturava com a indicação que trouxe a pessoa | Vale a primeira origem identificada, por inteiro |
| Respostas fechadas das perguntas frequentes não existiam no HTML | Continuam no HTML, ocultas |
| Campos com letra menor que 16 px (o iPhone dá zoom ao tocar) | 16 px na lista de serviços e na mensagem |

## 2. Captação e painel comercial

- **Deduplicação:** o envio só entra numa solicitação em aberto que já tenha todos os contatos informados, e vira linha do histórico, sem mudar nome, contatos, serviço, origem ou situação. A única exceção é o consentimento para novidades, registrado se a pessoa marcar a opção. Com dado diferente, ou depois de ganha, perdida ou arquivada, abre outra; o painel mostra as relacionadas. A resposta ao visitante é igual nos dois casos.
- **Situações:** Novo contato, Em atendimento, Proposta enviada, Negociação, Ganho, Perdido (com motivo) e Arquivada. Migration: Respondida → Em atendimento; Concluída → Arquivada (nenhuma venda presumida), reversível.
- **Filtros:** origem (grupo ou campanha), serviço, data (horário de Brasília) e situação; contadores acompanham.
- **Observações e histórico** com autor e data. Solicitações com novo contato sobem na lista.
- **Acesso:** visitante vai para o login, leitor recebe 403 e prospect antigo, 404 (testado em todas as rotas).
- **Mantidos:** e-mail ou telefone obrigatório e confirmação na tela após gravar. Sessão expirada agora vira aviso no formulário. O e-mail à equipe traz a mensagem daquele envio.
- Foi usado o painel existente; não há segundo CRM.

## 3. Conversão

- Celular: botão "Orçamento" sempre visível no cabeçalho, que rola até o formulário da página; o formulário aparece antes das etapas de contratação.
- `/servicos` e `/sistemas` ganharam formulário e processo de contratação. Produtos ganharam links para sob medida e integrações.
- Quatro textos com promessa absoluta ou vaga foram ajustados. As páginas de serviço já tinham problema, benefícios, solução, processo e chamada.

## 4. SEO e HTML inicial

Necessidade medida: **0 palavras, 0 títulos e 0 links** no corpo de todas as páginas sem JavaScript.

| Página | Antes | Agora (palavras) |
|---|---|---|
| `/` | 0 | 1.109 |
| `/desenvolvimento-de-sistemas` | 0 | 894 |
| `/seo-tecnico-e-otimizacao-de-sites` | 0 | 1.283 |
| `/sistemas/vetoros` | 0 | 586 |
| `/contato` | 0 | 239 |

- Solução: 19 páginas e a moldura do blog são pré-renderizadas **no build**, com Vite e React já existentes. Nenhum Node em execução, mesmo HTML para pessoas e robôs. `SEO_PRERENDER=false` desliga. Artigos e listagem do blog saem do banco, sanitizados.
- Sitemap (17 URLs no banco local), canonical, robots e JSON-LD conferidos com `seo:check`: corretos. Só falham os itens de domínio/HTTPS, esperados em `localhost`.
- Comparação das alternativas em `docs/publicacao.md`.

## 5. SEO regional e institucional

Painel → SEO → Configurações: nome comercial, razão social, CNPJ (validado, inclusive alfanumérico), município e UF, região atendida, e-mail, telefone e horário. Aparecem no rodapé, contato, termos e JSON-LD; vazio não aparece. **Município só é publicado se a opção for marcada** e não há campo de rua. Nada foi preenchido nem inventado. O e-mail do painel agora é o exibido no site.

## 6. VetorLeads

Contrato em `docs/integracao-vetorleads.md`: dados, token por tenant, idempotência, falhas e isolamento. Nada implementado; o formulário segue independente.

## 7. Publicação

- `docs/publicacao.md`: backup, migrations, publicação, conferência e reversão.
- Novo `php artisan site:preflight` (só leitura): migrations, assets, ambiente, e-mail, fila, formulário e SEO gravado no banco.
- Local: nenhuma migration pendente e assets compilados. O preflight local aponta ambiente de desenvolvimento, SMTP sem credenciais e 1 tarefa parada na fila, porque não há worker local.

## Verificações

- Pest: **326 testes passaram** (34 a mais), repetidos depois da integração com o `main`. A contagem de pautas no teste passou de 10 para 15.
- Depois da integração: 11 páginas em 390 e 1366 px sem rolagem horizontal e sem erros de JavaScript, com o azul da marca nos botões, e envio do formulário com confirmação.
- TypeScript, ESLint dos arquivos alterados, Pint e build: passaram. PHPStan: sem erros nos serviços novos; 20 avisos em `Lead.php` e `SiteRequestController.php`, da mesma família de tipos de modelo (relações sem tipo declarado), parte deles em linhas novas do controller.
- Migration nova no MySQL local: aplicada, revertida e reaplicada; a conversão de dados foi testada com registros em SQLite.
- Navegador (Chromium, banco descartável): 11 páginas em 390 e 1366 px sem rolagem horizontal, um H1, sem erros de JavaScript e CLS de até 0,001; página sem JavaScript; envio com confirmação; filtro, mudança de situação e observação no painel. Depois do ajuste final do formulário, 6 páginas foram conferidas de novo.
- **Não verificado:** servidor de produção, entrega real de e-mail, build da imagem Docker, artigos reais do blog, Core Web Vitals em campo. O aviso de sessão expirada foi testado só por teste automatizado: no navegador o envio foi aceito mesmo sem os cookies, pela conferência de origem do Laravel.

## Pendências

1. **Autorizar a publicação** e seguir `docs/publicacao.md`. A migration precisa rodar logo após recriar o container, senão o formulário não grava.
2. **`APP_ENV` na infra:** o compose usa `local` se a variável não existir. Conferir `production` na VPS (variável comum a todos os sistemas).
3. **Enviar um contato real em produção** para confirmar SMTP e worker.
4. **Preencher dados institucionais** e revisar títulos gravados no banco em produção, que prevalecem sobre os novos.
5. **Compressão:** o nginx não aplica gzip nem cache longo aos assets deste site (o VetorOS tem). Com o HTML maior (78 KB na home, sem gzip), vale copiar a configuração; é arquivo da infra, fora do escopo.
6. Primeira origem vale por sessão (120 min). Guardá-la por mais tempo exigiria cookie de marketing com consentimento.
7. Na home, depoimentos, portfólio e últimos artigos ainda dependem de JavaScript.
8. Integração com o VetorLeads: endpoint a ser criado lá.

## Arquivos principais

- Novos: `app/Services/{Prerender,CompanyProfile}.php`, `app/Rules/Cnpj.php`, `SitePreflightCommand.php`, migration `2026_11_23_180000`, `scripts/prerender.mjs`, `resources/js/prerender/`, `resources/views/prerender/`, `docs/{publicacao,integracao-vetorleads}.md`, `PrerenderTest`, `CompanyProfileTest`.
- Alterados: `SiteRequestIntake`, `VisitOrigin`, `Lead`, `SiteRequestController`, `ContactController`, `ContactFormReceived`, `SeoSettings`, `StructuredData`, `SettingsController`, `bootstrap/app.php`, `app.blade.php`, `package.json` (script de build), telas de Solicitações, configurações de SEO, cabeçalho, rodapé, formulário e `public/build/`.
