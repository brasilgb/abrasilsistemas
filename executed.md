# Execução de `correio.md`: mensagens WhatsApp não lidas

Data: 2026-09-24.

## Resumo

**Implementado, testado e commitado (`22aca11`). Não houve deploy. A migration não rodou em produção e o banco real não foi acessado.** A tabela `lead_activities` ganhou a coluna `read_at`. Toda mensagem WhatsApp `inbound` nasce com `read_at = null`, ou seja, não lida. A listagem de leads (tabela, kanban e filas de tarefas) mostra um selo verde discreto com a quantidade de mensagens recebidas não lidas. Abrir o lead, na tela que exibe o histórico, marca como lidas as pendentes daquele lead. Na mesma visita, cada mensagem que acabou de ser lida aparece com o selo "Nova".

O fluxo WAHA → n8n → `POST /api/whatsapp/inbound` → `LeadActivity` **não foi alterado**. Também ficaram intactos o workflow n8n, o WAHA, o envio outbound, o ACK/`message_status`, o endpoint de log e o `WhatsappInboundController`.

## 1. Auditoria

| Ponto | Onde | O que encontrei |
|---|---|---|
| Criação de inbound | `Api/WhatsappInboundController::record` e `Api/WhatsappActivityController` (com `direction=inbound`) | Os dois fazem `activities()->create([...])` com `type=whatsapp`, `direction=inbound` e `message_status=null`. Uma coluna nullable com default nulo já faz o inbound nascer não lido, **sem mexer nesses controllers** |
| `LeadActivity` | `app/Models/LeadActivity.php` | Tem `direction` e `message_status` (ACK do WAHA). Não havia nenhum campo de leitura interna. `message_status = READ` quer dizer que o *cliente* leu, e o correio veda usar esse campo |
| Listagem | `LeadController::index` | Um `baseQuery` é clonado para a paginação (15), o kanban (até 500), as 3 filas de tarefas (50 cada) e as métricas (`count`, `groupBy`, `get([...])`) |
| Detalhe | `LeadController::edit` → `leads/edit.tsx` | A página carrega **todas** as atividades e mostra o histórico sempre, sem aba nem lazy load. Os links para o lead não usam `prefetch` |
| Acesso | `routes/web.php` | Todas as rotas de leads ficam em `auth` + `verified` + `admin` (`EnsureUserIsAdmin` → 403). Não há policy por lead nem filtro por responsável |
| Multitenancy | `Lead::booted`, `Account` | `account_id` é resquício de migrations não versionadas. O próprio código diz que o app tem **uma única conta**, e não existe escopo por tenant nas consultas. Não há isolamento a preservar além da regra de admin |
| Idempotência | `WhatsappInboundController` | Lock por `provider_message_id` mais busca global da atividade. Uma reentrega devolve a atividade existente sem gravar nada, então não "desmarca" uma leitura |

## 2. Decisão de modelagem

**Coluna `lead_activities.read_at` (timestamp, nullable).**

- `null` em inbound = não lida. Preenchida = lida pela equipe no CRM, com o momento da leitura.
- É independente de `message_status`, que segue reservado aos ACKs de entrega.
- A leitura é **da equipe**, não de cada usuário. Todos os admins veem todos os leads e ninguém é dono da caixa de entrada. Uma tabela pivô usuário × mensagem seria complexidade sem uso hoje. Se um dia a leitura precisar ser por usuário, a migração é aditiva.
- Nas outras atividades (outbound, notas, legado) a coluna fica nula e nunca é consultada, porque o escopo sempre filtra `type=whatsapp` e `direction=inbound`.
- Por que não calcular pelo `last_viewed_at` do lead: um único carimbo no lead não diz *quais* mensagens são novas (sem o selo "Nova"), quebra quando o `created_at` e o momento da visita caem no mesmo segundo, e mistura estado de leitura com o cadastro do lead.

Código:

- `LeadActivity::unreadInbound()` (scope `#[Scope]`): `type=whatsapp`, `direction=inbound`, `read_at IS NULL`. É a única definição de "não lida".
- `Lead::unreadWhatsappMessages()` (HasMany): `activities()->unreadInbound()`.
- Listagem: `withCount('unreadWhatsappMessages')` em cada consulta **exibida** (paginação, kanban e as 3 filas). O resultado é uma subconsulta correlacionada na mesma query, sem N+1. Ficou fora do `baseQuery` de propósito: lá a subconsulta entraria no `groupBy` das métricas e quebraria o `ONLY_FULL_GROUP_BY` do MySQL.
- Marcação: em `LeadController::edit`, depois de carregar o histórico, um único `UPDATE ... SET read_at = now()` restrito ao lead aberto e às inbound pendentes. O histórico já carregado mantém `read_at = null` nessas mensagens, e a página mostra "Nova" nelas.
- Por que marcar no GET do `edit`: essa tela *é* a visualização do histórico. Ela renderiza todas as atividades no carregamento e não tem prefetch. Um POST disparado pelo front só adicionaria uma rota, um request e uma janela de falha, sem ganho real.

## 3. Migration

`database/migrations/2026_09_24_130000_add_read_at_to_lead_activities_table.php`

- `read_at` timestamp nullable, depois de `message_status`.
- Índice `lead_activities_lead_unread_index (lead_id, direction, read_at)` para a contagem por lead.
- **Backfill**: as inbound que já existem recebem `read_at = created_at`. São mensagens anteriores ao controle, e sem isso o primeiro acesso depois do deploy mostraria alertas antigos. Outbound, notas e legado sem direção continuam nulos.
- `down()` remove o índice e a coluna.
- Conferido em SQLite: rollback da migration, inserção de inbound, outbound e nota, `migrate` (só a inbound recebeu `read_at = 2026-09-01 10:00:00`) e rollback de novo, sem erro.
- Depende da migration `2026_09_24_120000_add_direction_to_lead_activities_table` (coluna `direction`).

## 4. Arquivos alterados

| Arquivo | Alteração |
|---|---|
| `database/migrations/2026_09_24_130000_add_read_at_to_lead_activities_table.php` | **Novo.** Coluna, índice e backfill |
| `app/Models/LeadActivity.php` | `read_at` no `Fillable` e no cast `datetime`. Scope `unreadInbound()` |
| `app/Models/Lead.php` | Relação `unreadWhatsappMessages()` |
| `app/Http/Controllers/LeadController.php` | `withCount('unreadWhatsappMessages')` nas 5 consultas exibidas do `index`. Marcação de leitura no `edit` |
| `resources/js/components/whatsapp-message-status.tsx` | Novo componente `WhatsappUnreadBadge`: selo verde com ícone e quantidade (`99+` acima de 99), `title`/`aria-label` "N mensagens WhatsApp não lidas", e nada quando zero |
| `resources/js/pages/leads/form.tsx` | Tipos: `Lead.unread_whatsapp_messages_count` e `LeadActivity.read_at` |
| `resources/js/pages/leads/index.tsx` | Selo ao lado do nome do lead na tabela, no card do kanban e nas filas de tarefas |
| `resources/js/pages/leads/edit.tsx` | Selo "Nova" nas mensagens recebidas que estavam não lidas até esta visita |
| `tests/Feature/LeadWhatsappUnreadTest.php` | **Novo.** 10 testes |

Não foram tocados: `WhatsappInboundController`, `StoreWhatsappInboundRequest`, `WhatsappActivityController`, `WhatsappMessageStatusController`, `LeadWhatsappService`, rotas, n8n, WAHA e docker-compose.

## 5. Testes criados (`tests/Feature/LeadWhatsappUnreadTest.php`)

| Teste | Cobre |
|---|---|
| inbound recebido pela API nasce não lido | `POST /api/whatsapp/inbound` → `read_at` nulo, `message_status` nulo, contagem 1 |
| inbound registrado pelo endpoint de log nasce não lido | `POST /api/prospects/{lead}/whatsapp/log` com `direction=inbound` |
| outbound, legado e não-WhatsApp não contam | Contagem 0 na listagem |
| listagem mostra a quantidade por lead | 2 não lidas + 1 já lida + outbound → 2. Outro lead → 0. Conferido em `leads.data`, `kanbanLeads` e `taskLeads` |
| sem N+1 | Número de queries em `lead_activities` igual com 1 e com 6 leads |
| abrir o lead marca como lido | Pendente → lida. Já lida mantém o `read_at` original. Outbound fica com `read_at` nulo e `message_status` intacto. A visita recebe `read_at` nulo (para o "Nova"), a seguinte já recebe preenchido. Listagem → 0 |
| isolamento entre leads | Abrir o lead A não marca as mensagens do lead B |
| regras de acesso | Usuário `reader` recebe 403 no `edit` e nada é marcado |
| idempotência | Reentrega do mesmo `provider_message_id` → `idempotent: true`, sem duplicar. Depois da leitura, a reentrega não volta a mensagem para não lida |
| nova mensagem depois da leitura | O lead volta a ter 1 não lida (só a nova) |

## 6. Resultado das verificações

Ambiente: cópia do working tree na scratchpad, `vendor`/`node_modules` reaproveitados de uma execução anterior (mesmos `composer.lock` e `package-lock.json`), testes em `docker run --rm --network none` com a imagem `infra-abrasil-abrasilsistema` e o SQLite em memória do `phpunit.xml`. Nenhum PHP rodou no container de produção.

- `php artisan test tests/Feature/LeadWhatsappUnreadTest.php`: **10 passed (87 assertions)**.
- `php artisan test` (suíte completa): **263 passed, 1 failed**. A falha é `BlogTest > administrator can upload…` (`imagejpeg function is not defined`): a extensão GD com JPEG não existe na imagem e o teste não tem relação com esta mudança. Todos os testes de WhatsApp (inbound, log, ACK, envio, histórico) passam.
- Pint (`--test`) nos arquivos PHP alterados: OK.
- PHPStan: nenhum erro novo, exceto `HasMany::unreadInbound()` "undefined method". O projeto não usa Larastan, que resolve scopes, e o mesmo tipo de aviso já existe em outras partes do código (a base tem ~40 erros anteriores).
- `tsc --noEmit`: OK. Prettier nos arquivos alterados: OK nas minhas alterações (`edit.tsx` já tinha trechos fora do padrão antes, e não os reformatei). ESLint: só erros de ordem de import que já existiam em `create.tsx`/`edit.tsx`, fora das linhas alteradas.
- `php artisan wayfinder:generate --with-form` seguido de `SKIP_WAYFINDER_GENERATE=1 npm run build` (`node:22-bookworm-slim`): OK (só o aviso de chunk > 500 kB, que já existia).

## 7. Deploy (não executado) e validação manual

O código e os assets entram na imagem. **Esta etapa tem migration.** As mudanças do inbound (etapa anterior, commit `253d39e`) também ainda não tiveram deploy e sobem juntas.

```bash
# 0) (Só leitura) Conferir as migrations pendentes: direction (se ainda não aplicada) e read_at
docker compose exec abrasilsistema php artisan migrate:status | grep -E 'add_direction|add_read_at'

# 0b) (Recomendado) Backup de lead_activities antes da migration
docker compose exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" abrasilsistemas lead_activities' \
  > lead_activities-$(date +%F-%H%M).sql

# 1) Rebuild das imagens (os containers atuais continuam no ar)
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 2) Migration (adiciona read_at, o índice e o backfill das inbound existentes)
docker compose run --rm --no-deps abrasilsistema php artisan migrate --force
docker compose run --rm --no-deps abrasilsistema php artisan migrate:status | grep add_read_at

# 3) Deploy
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 4) Conferência (só leitura): não lidas por lead. Logo depois do deploy, deve vir vazio (backfill)
docker compose exec abrasilsistema php artisan tinker --execute='dump(App\Models\LeadActivity::query()->unreadInbound()->selectRaw("lead_id, count(*) as total")->groupBy("lead_id")->get()->toArray());'
```

Validação manual no painel:

1. Pelo celular, mandar uma mensagem de texto para o WhatsApp da empresa a partir do número de um lead cadastrado. O n8n chama `/api/whatsapp/inbound` (201).
2. Em **Prospecção**, o lead mostra o selo verde com "1" ao lado do nome, na tabela, no kanban e na fila de tarefas em que estiver. Passar o mouse mostra "1 mensagem WhatsApp não lida".
3. Mandar mais uma mensagem e recarregar. O selo mostra "2".
4. Abrir o lead. No histórico, as duas mensagens aparecem como "Recebida · Cliente → Empresa" com o selo **Nova**.
5. Voltar para a listagem. O selo sumiu. Reabrir o lead: o "Nova" também sumiu.
6. Enviar uma mensagem pelo botão de WhatsApp do lead. A listagem continua sem selo (outbound não conta), e o ACK segue aparecendo no histórico como antes.
7. Outro lead com mensagem pendente continua com o selo depois de abrir o primeiro.

Rollback: `docker compose run --rm --no-deps abrasilsistema php artisan migrate:rollback --step=1 --force` (remove só `read_at` e o índice), depois voltar a imagem anterior e rodar `docker compose up -d` nos 3 serviços.
