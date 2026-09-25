# Execução de `correio.md`: CRM-WA-08 (follow-up comercial de prospects)

Data: 2026-09-25.

## Status

Implementado e testado em um ambiente descartável (cópia da árvore, container `--network none`, sqlite em memória). **Sem deploy e sem commit.** Não foram alterados n8n, WAHA, Docker/infra nem os endpoints de integração (`/api/...`).

- Testes WhatsApp/Lead/Kanban/Dashboard: **335 passando (1.607 assertions)**. Antes eram 317/1.448.
- Suíte completa: **376 passando e 1 falha já existente** (`BlogTest`, `imagejpeg` ausente no container).
- TypeScript, Pint e Prettier sem erros nos arquivos alterados. O ESLint só aponta 2 erros de `import/order` em `dashboard.tsx` que já existiam no HEAD.

## 1. Auditoria encontrada

| Item | Situação encontrada |
|---|---|
| `leads.next_follow_up_at` | **Já existia**, mas era `date` (sem horário), com índice, cast `date`, campo no formulário, filtro `follow_up` (overdue/today/upcoming/none), métricas, filas de tarefas, dashboard e `lead_score`. |
| `lead_activities.next_follow_up_at` | Já existia (`date`). "Registrar contato" e "Atividade rápida" copiam o valor para o lead. |
| `last_contacted_at` | Já existia e é atualizado na mudança de estágio e por atividades com `contacted_at`. **Não foi duplicado nem alterado.** |
| Responsável | `leads.user_id` → `user`. Reaproveitado sem mudanças. |
| Status/funil | `Lead::STATUSES`: new, contacted, interested, meeting, converted, lost, com `lost_reason`. |
| Histórico | `lead_activities` (type `note`/`whatsapp`/…). A conversa usa o scope `conversation()` (whatsapp + direction) e as não lidas usam `unreadInbound()` (whatsapp + inbound + `read_at` nulo). |
| Problemas encontrados | (a) O filtro `follow_up` da listagem **não excluía** Convertido/Perdido: um convertido com data antiga aparecia como atrasado. (b) "Hoje" era calculado com `today()` em **UTC** (o app roda em UTC), então entre 21:00 e 24:00 de Brasília o dia já era o seguinte. (c) Não havia horário. |

## 2. Decisão sobre banco/migration

Criada **uma** migration, `2026_09_25_120000_add_time_to_next_follow_up_at.php`, porque o requisito pede data/hora ("28/09/2026 09:00", "Escolher data/hora", "Atrasado: horário já passou") e a coluna existente era `date`. **Nenhuma coluna nova**: só muda o tipo de `next_follow_up_at` em `leads` e `lead_activities` de `date` para `datetime`.

- Os valores antigos (só data) passam para **09:00 de Brasília** (gravado 12:00 UTC). Deixar meia-noite UTC faria a data aparecer na véspera às 21:00.
- O `down()` volta para `date` preservando o dia.
- O índice de `leads.next_follow_up_at` é mantido.
- Verificado com dados reais em sqlite (up/down): `2026-09-28` → `2026-09-28 12:00:00` → exibido como "28/09/2026 às 09:00" → rollback `2026-09-28`.

## 3. Arquivos criados e alterados

Criados:
- `database/migrations/2026_09_25_120000_add_time_to_next_follow_up_at.php`
- `resources/js/components/lead-follow-up.tsx`: `LeadFollowUpBadge`, `LeadFollowUpSummary`, `LeadFollowUpControl`, `formatFollowUp`, `toDateTimeLocalValue`
- `tests/Feature/LeadFollowUpTest.php`: 13 testes

Alterados:
- `app/Models/Lead.php`: constantes `CLOSED_STATUSES`, `FOLLOW_UP_STATES`, `FOLLOW_UP_TIMEZONE` e `FOLLOW_UP_DEFAULT_TIME`; normalização de `next_follow_up_at`; atributo `follow_up_state`; scope `followUpState()`; nota de auditoria no evento `updated`; `lead_score` usa a nova situação.
- `app/Models/LeadActivity.php`: cast `datetime` e a mesma normalização.
- `app/Http/Controllers/LeadController.php`: nova ação `followUp`; filtro, métricas e filas usam o scope.
- `app/Http/Controllers/DashboardController.php`: contadores usam o scope.
- `routes/web.php`: `PATCH leads/{lead}/follow-up` (`leads.follow-up`).
- `resources/js/components/lead-whatsapp-conversation.tsx`: controle de próximo contato na área comercial.
- `resources/js/pages/leads/form.tsx`: campo "Próximo contato" `datetime-local` e tipo `follow_up_state`.
- `resources/js/pages/leads/edit.tsx`: "Registrar contato" com data/hora; histórico exibe o horário.
- `resources/js/pages/leads/index.tsx`: badge de situação no Kanban, na tabela e nas filas; opção "Sem follow-up" no filtro; atividade rápida com data/hora.
- `resources/js/pages/dashboard.tsx`: "Próximos follow-ups" com hora e badge.
- `tests/Feature/LeadManagementTest.php`: 2 ajustes explicados em "Testes".

## 4. Comportamento implementado

- Cada prospect aberto pode ter uma data/hora opcional de próximo contato.
- Na conversa WhatsApp, a área comercial mostra **"Próximo contato: 28/09/2026 09:00"**, o badge de situação e o menu **Agendar/Alterar** com:
  - **Hoje** (18:00; depois das 18:00, a próxima hora cheia);
  - **Amanhã**, **+3 dias** e **+7 dias** (09:00, com dia e hora exibidos no item);
  - **Escolher data/hora...** (campo inline com Salvar/Cancelar);
  - **Remover**.
- Salvar recarrega só o `lead` (Inertia `only: ['lead']`), então histórico, badge e controle se atualizam sem recarregar a página nem interromper o polling.
- O formulário do lead e "Registrar contato" também aceitam data/hora.
- **Nada é enviado automaticamente**: nem ao agendar, nem quando a data chega, nem ao enviar mensagem (enviar não agenda follow-up).

## 5. Regras de follow-up

Calculadas no servidor, com uma única regra para o atributo `follow_up_state`, o filtro, as métricas, as filas e o dashboard:

| Situação | Regra (prospect aberto) |
|---|---|
| **Atrasado** | `next_follow_up_at < agora` (o horário já passou, inclusive hoje mais cedo) |
| **Hoje** | entre agora e 23:59:59 de hoje em **America/Sao_Paulo** |
| **Próximo** | depois de hoje |
| **Sem follow-up** | sem data |
| *(nula)* | Convertido ou Perdido: nunca pendente |

- Armazenamento sempre em UTC, sem segundos.
- Texto sem fuso (vindo do `datetime-local` dos formulários ou do CSV) é interpretado como horário de Brasília.
- Só a data (CSV ou integração antiga) vale como 09:00.
- ISO com fuso (enviado pela conversa) vale como o instante exato.

## 6. Integração com WhatsApp

- O controle fica no cabeçalho comercial da conversa (Fase 2), abaixo do seletor de estágio.
- As notas de follow-up são `type = note`, sem `direction`. Por isso não entram no scope `conversation()` (a conversa e o polling incremental não as veem) nem em `unreadInbound()` (não contam como não lidas). Os testes cobrem os dois casos.
- Composer, mensagens rápidas, polling de 5 s, paginação, ACK, inbound/outbound, não lidas, scroll e envio pelo celular não foram alterados.

## 7. Integração com funil

- Estados respeitados: Novo → Contatado → Interessado → Reunião → Convertido / Perdido.
- **Decisão: a data é ignorada, não limpa**, ao converter ou perder. É a opção com menor efeito colateral:
  - nenhuma mudança silenciosa de dado;
  - nenhuma nota extra a cada mudança de estágio (Kanban, lote ou conversa);
  - é reversível: ao reabrir o prospect, o agendamento volta a valer.
- Todos os filtros, métricas, filas e o dashboard excluem Convertido/Perdido.
- Na conversa, um prospect encerrado mostra "Prospect encerrado: não entra nos follow-ups pendentes." e só oferece **Remover**. O endpoint recusa agendar em encerrado, mas permite remover.

## 8. Filtros

- O filtro "Follow-up" da listagem agora tem **Atrasados · Hoje · Próximos · Sem follow-up** (a opção "Sem follow-up" era nova no select; a URL `?follow_up=none` já existia nos cards).
- Os quatro filtros consideram **só prospects abertos**.
- Os cards de métricas já linkavam para esses filtros; agora os números batem com a listagem.
- Tabela, Kanban e filas de tarefas mostram data/hora e o badge colorido, feito com o `Badge` existente: vermelho = Atrasado, âmbar = Hoje, azul = Próximo.
- Nenhum dashboard novo foi criado.

## 9. Histórico

Toda mudança do próximo contato gera uma nota automática no histórico do lead, venha da conversa, do formulário, de "Registrar contato" ou da atividade rápida:
- `Próximo contato definido para 28/09/2026 às 09:00.`
- `Próximo contato alterado de 28/09/2026 às 09:00 para 30/09/2026 às 14:30.`
- `Próximo contato removido (estava em 30/09/2026 às 14:30).`

Detalhes:
- A nota fica com o usuário logado (`user_id`) e horário de Brasília no texto.
- Salvar o mesmo valor não gera nota.
- A criação do lead não gera nota, porque a data já aparece no cadastro.

## 10. Testes / resultados

- `tests/Feature/LeadFollowUpTest.php` (novo, 13 testes, tempo congelado com `travelTo`):
  - normalização UTC/Brasília/ISO/só data;
  - as 4 situações;
  - virada de dia às 22:00 BRT (a regressão de UTC);
  - Convertido/Perdido fora das pendências;
  - estágio convertido mantém a data;
  - filtros e métricas;
  - agendar, alterar e remover com o texto exato das notas;
  - notas fora da conversa e das não lidas;
  - mesmo valor sem nota;
  - formulário gera nota;
  - bloqueio em encerrado;
  - validação e nenhum HTTP de envio (`Http::preventStrayRequests` + `assertNothingSent`);
  - visitante redirecionado para login.
- Ajustes em `LeadManagementTest`:
  - "register lead activities": espera 2 atividades (a atividade mais a nota de auditoria) em vez de 1 e verifica o texto da nota.
  - "task queues": usava `today()` (meia-noite UTC), que pela nova regra já passou. Agora congela o tempo ao meio-dia de Brasília e usa daqui a 2 horas.
- Resultados:
  - `--filter='Whatsapp|Lead|Kanban|Dashboard'`: **335 passed (1.607 assertions)**.
  - Suíte completa: **376 passed, 1 failed** (`BlogTest` upload de imagem: `imagejpeg` ausente no container; já falhava antes).
  - `tsc --noEmit`: OK.
  - ESLint: OK nos arquivos alterados, exceto 2 `import/order` em `dashboard.tsx` que já existiam no HEAD e não foram mexidos.
  - Prettier `--check`: OK nos arquivos novos e alterados. `edit.tsx` já estava fora do padrão no HEAD e não foi reformatado inteiro, para não inflar o diff.
  - Pint `--test`: OK.
  - Migration up/down com dados legados em sqlite: OK.

## 11. Riscos e limitações

- **Migration em produção (MySQL)**: `DATE → DATETIME` com `change()` preserva os valores. Depois, as linhas com data são atualizadas uma a uma (chunks de 500). Foi testada em sqlite, não em um MySQL descartável. Recomenda-se backup antes do `migrate`.
- **Fuso fixo** `America/Sao_Paulo` (`Lead::FOLLOW_UP_TIMEZONE`). O navegador exibe no fuso local; um operador fora de Brasília veria horários convertidos, e os campos `datetime-local` dos formulários são interpretados como horário de Brasília.
- **"Registrar contato" e a atividade rápida continuam limpando o follow-up quando o campo fica vazio.** Esse comportamento já existia e está coberto por teste. Agora ao menos gera a nota "Próximo contato removido", mas o operador pode apagar sem querer.
- O badge "Hoje/Atrasado" é calculado no carregamento da página: uma tela aberta há horas só muda de "Hoje" para "Atrasado" ao recarregar (o polling da conversa atualiza só as mensagens).
- A nota de auditoria depende do evento Eloquent `updated`. Um `update()` direto via query builder não a geraria; hoje nenhum código faz isso com esse campo.
- `lead_score` agora trata como "Atrasado" um follow-up de hoje cujo horário já passou (+25 em vez de +18), coerente com a nova regra.

## 12. Roteiro E2E manual

1. Rodar `php artisan migrate` (depois do backup). Conferir que um lead com follow-up antigo aparece com a mesma data às 09:00.
2. Abrir um lead **Contatado** com WhatsApp → na conversa aparece "Próximo contato: não definido" e o botão **Agendar**.
3. Agendar → **Amanhã**. Esperado: "Próximo contato: <amanhã> 09:00", badge azul "Próximo", toast "Próximo contato agendado.", nota "Próximo contato definido para …" no Histórico, **nenhuma mensagem nova na conversa** e nenhum envio no WhatsApp.
4. Alterar → **Escolher data/hora...** → hoje, uma hora à frente → Salvar. Esperado: badge âmbar "Hoje" e nota "alterado de … para …".
5. Alterar → escolher hoje com horário já passado. Esperado: badge vermelho "Atrasado".
6. Na Prospecção, filtrar Follow-up = Atrasados, Hoje, Próximos e Sem follow-up. Conferir a presença do lead e os números dos cards. Conferir o badge no Kanban, na tabela e na aba Tarefas.
7. Na conversa, mudar o estágio para **Convertido**. Esperado: o badge some, aparece "Prospect encerrado…" e o lead some de Atrasados/Hoje/Próximos e das métricas, mas a data continua gravada. Voltar para **Interessado**: o agendamento volta.
8. Com o prospect encerrado, clicar **Remover**. Esperado: nota "Próximo contato removido (estava em …)."
9. Enviar uma mensagem pela conversa. Esperado: o próximo contato não muda. Receber uma mensagem: a contagem de não lidas não inclui as notas de follow-up.
10. Editar o lead pelo formulário, mudar "Próximo contato" e salvar. Esperado: nota de alteração. Salvar de novo sem mudar: sem nota nova.
11. Às 22:00 ou mais tarde (horário de Brasília), um follow-up marcado para 23:00 do mesmo dia deve aparecer como "Hoje", e não como "Próximo".
