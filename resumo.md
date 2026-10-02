# Execução de `correio.md`: CRM-WA-14, horário comercial no SLA da fila WhatsApp

Data: 2026-09-28. Branch `feat/whatsapp-message-direction` (HEAD `ccecfe5`). O working tree já tinha mudanças sem commit do CRM-WA-07.1 e do CRM-WA-09 ao CRM-WA-13. Elas foram preservadas.

**Status: implementado, VALIDAÇÃO PENDENTE.** Nesta sessão, o Bash ficou indisponível: a verificação de permissão do modo automático retornou erro em todas as tentativas. Por isso nada foi executado. Não rodaram Pest, SQLite, MySQL 8.4, mutação, Pint, PHPStan, tsc, ESLint, Prettier, `vite build` nem `EXPLAIN`. As seções de resultados abaixo estão marcadas **"não executado"**, e nenhum número foi inventado. Não houve migration, deploy, commit nem alteração em produção.

> **Situação encontrada:** o working tree já trazia uma implementação parcial do CRM-WA-14, sem relatório, feita por uma sessão anterior. Ela incluía `app/Services/WhatsappBusinessHours.php`, as chaves em `Setting`, o scope, o relógio da fila e o teste `LeadWhatsappBusinessHoursSlaTest.php`. Essa versão usava **um único horário para todos os dias, mais um intervalo de almoço**. O correio atual pede **ativo + abertura + fechamento por dia da semana**, com sábado podendo abrir, por exemplo, das 08:00 às 12:00. A implementação foi adaptada a esse modelo, e o intervalo de almoço, que não está no correio, foi removido.

## 1. Auditoria

| # | Item | O que existe |
|---|---|---|
| 1–2 | Configurações | Tabela `settings` com chave e valor (`Setting`, `value` com cast `encrypted`), usada para o token da API e o WhatsApp da empresa (`CompanyWhatsappSettings`, chaves constantes no model). Serve para o horário **sem migration**. |
| 3 | `FOLLOW_UP_TIMEZONE` | `Lead::FOLLOW_UP_TIMEZONE = 'America/Sao_Paulo'`, o "relógio da equipe comercial" (o "hoje" do follow-up e datas digitadas sem fuso). É a convenção do projeto e foi reaproveitada como fuso padrão do SLA. |
| 4 | Horário já existente | Antes do CRM-WA-14 não havia timezone operacional, horário comercial, dias úteis, abertura nem fechamento. Só havia a versão parcial descrita acima, sem commit. |
| 5 | Frontend de configurações | `settings/leads` (token e WhatsApp da empresa). Não existe componente genérico de configurações reutilizável. |
| 6–7 | SLA do CRM-WA-13 | `Lead::WHATSAPP_REPLY_SLA` (0/15/30/60) e o scope `whatsappReplySla($level)`. O nível N é `EXISTS(inbound sem resposta ≤ corte(N))` e `NOT EXISTS(… ≤ corte(N+1))`. No CRM-WA-13 o corte era `now − minutos`. |
| 8 | `awaiting_reply_since` | `min(created_at)` das inbound WhatsApp sem outbound posterior, na ordem `(created_at, id)`, por subconsulta correlata (`unansweredWhatsappInbound()`). |
| 9 | Contador crítico | `Lead::query()->whatsappReplySla('critical')->count()`: um `COUNT(*)` com `EXISTS`. |
| 10 | Dashboard | `metrics.awaiting_reply` e `metrics.awaiting_reply_critical` (`DashboardController`). Na tela de leads, `awaitingReplyTotal` e `awaitingReplyCritical`. |
| 11 | Badges no navegador | `usePoll(15000)`, um `setInterval` de 30 s e um `setTimeout` agendado para o próximo limite (CRM-WA-13). |
| 12 | Relógio nos testes | `Carbon::setTestNow()`, restaurado no `afterEach`. |

Timezone: `config('app.timezone') = 'UTC'`, e o app usa `CarbonImmutable`. Os timestamps são gravados e serializados em UTC (`…Z`). **Nada disso foi alterado.**

## 2. Arquitetura escolhida

- **Serviço único `App\Services\WhatsappBusinessHours`**, registrado como `scoped` no container (uma leitura de `settings` por request). É a fonte única para o filtro, o contador, o Dashboard, a tela de leads e a fila.
- **Configuração em `settings`, sem migration:**
  - `whatsapp_sla_timezone`: identificador IANA.
  - `whatsapp_sla_week`: JSON por dia ISO, `{"1": {"active": true, "opens_at": "08:00", "closes_at": "18:00"}, …, "7": {…}}`.
- **Fallback explícito** (`WhatsappBusinessHours::DEFAULTS`): enquanto nada for salvo, vale segunda a sexta 08:00–18:00, sábado e domingo fechados, `America/Sao_Paulo`. Esse **não** é o horário real da empresa. A fila mostra o aviso **"(America/Sao_Paulo, horário padrão)"** na descrição, e `current()['configured']` indica `false`. Um dia salvo inválido (hora fora de `HH:MM`, abertura ≥ fechamento, formato errado) volta ao padrão **daquele dia**. Um fuso inválido volta a `America/Sao_Paulo`.
- **Nada é persistido sobre o SLA.** Não existem `sla_status`, `sla_started_at`, `waiting_minutes` nem `priority`. Não há job, cache, Redis nem índice novo.

## 3. Algoritmo de minutos operacionais

`elapsedSeconds(start, now)` / `elapsedMinutes()` (equivalente ao `businessMinutesBetween`):

1. Trunca os dois instantes no segundo, como o `created_at` gravado.
2. Converte para o fuso operacional e percorre **só os dias locais** entre `start` e `now`.
3. Para cada dia ativo, monta a janela `[abertura, fechamento)`. `24:00` significa meia-noite do dia seguinte.
4. Faz a interseção da janela com `[start, now]` e soma os segundos.

Não há laço por minuto: o custo é um passo por dia do intervalo.

**Filtro no banco sem calcular por lead:** a espera útil só cresce quando o início recua. Então "atingiu N minutos úteis" equivale a `início ≤ corte(now, N)`. `cutoff()` anda para trás, janela por janela, a partir de `now`, até acumular N minutos, e devolve esse instante em UTC. O scope continua o **mesmo `EXISTS` do CRM-WA-13**, trocando apenas `now − N` por `cutoff(now, N)`. São dois cortes por consulta, calculados em PHP em microssegundos. Sem nenhum dia ativo, `cutoff` retorna `null` e nenhum lead sai de Normal. Um teste confirma, instante a instante, que `início ≤ corte` ⇔ `elapsed ≥ N` (4 dias × 6 "agoras" × 3 limites, passo de 17 min 13 s).

## 4. Comportamento

- **Fora do expediente:** o lead entra na fila na hora, com `awaiting_reply_business_seconds = 0` e nível Normal, até a próxima abertura.
- **17:55 → 18:00** = 5 min, e às **23:00** continua 5 min. **Segunda 17:55 → terça 08:10** = 15 min (Atenção).
- **Sexta 17:55 → segunda 08:00** = 5 min, e às 08:10 = 15 min.
- **Sábado fechado:** não acumula. **Sábado ativo (08:00–12:00):** participa normalmente (sexta 17:55 → sábado 08:10 = Atenção). **Domingo fechado:** não acumula.
- **Responsável, leitura, nota, ligação, e-mail e follow-up** não iniciam, encerram, pausam nem reiniciam o SLA.
- **Outbound** encerra a espera, no expediente ou fora dele. Uma nova inbound inicia um SLA novo.
- **Convertido e Perdido** recebem SLA, e o status não muda.
- **Feriados:** não implementados, conforme o correio.

## 5. Filtro SLA, paginação e contador

- `?sla=normal|attention|urgent|critical` continua funcionando, agora com tempo operacional, e **no SQL, antes do `paginate`**. `total` e as páginas ficam corretos, sem filtrar em PHP, sem N+1 e sem carregar o Histórico.
- Combina com `owner` (inclusive `mine`), `status`, `product` e `search`, porque tudo é `WHERE` na mesma consulta.
- O **contador crítico** (Dashboard e tela de leads) usa o mesmo scope `whatsappReplySla('critical')`, então é impossível divergir da fila crítica com o mesmo escopo.
- Contrato da fila preservado. Só há campos **adicionados**: `awaiting_reply_business_seconds` por item e a prop `slaClock`.

## 6. Frontend e timer dos badges

`resources/js/pages/leads/awaiting-reply.tsx` (da versão parcial, sem mudança nesta sessão):

- O backend envia `slaClock = { now, periods, schedule }`. `periods` são os períodos de atendimento dos **próximos 14 dias** em ISO UTC, e `schedule` é a descrição. As regras de fuso e dias ficam só no backend. O navegador apenas soma o tempo que cai dentro dos períodos.
- "Aguardando há X min" = `awaiting_reply_business_seconds` + o expediente que passou desde `slaClock.now`. Exemplo: sexta 17:55 → segunda 08:05 mostra **"há 10 min"**, e não 62 h.
- **Próximo limite real:** `instantReaching()` percorre os períodos e acha o instante de calendário em que faltam 0 minutos úteis. Sexta 17:55 com 5 min: Atenção chega **segunda 08:10**, e não sexta 18:10. Um `setTimeout` é agendado para esse instante.
- Polling de 15 s preservado (`only: ['leads', 'slaClock']`), e o tick de 30 s também. Fora do expediente aparece "Fora do horário de atendimento: o SLA está pausado".
- Os limites 15/30/60 continuam vindo só de `slaLevels` (`Lead::WHATSAPP_REPLY_SLA`), sem duplicação no frontend.

## 7. Arquivos alterados (CRM-WA-14)

| Arquivo | Mudança |
|---|---|
| `app/Services/WhatsappBusinessHours.php` | **Novo.** Configuração por dia, cálculo útil, `cutoff`, períodos e descrição. Nesta sessão foi reescrito para janelas por dia, sem intervalo e com a flag `configured`. |
| `app/Models/Setting.php` | Chaves `WHATSAPP_SLA_TIMEZONE` e `WHATSAPP_SLA_WEEK`. Removidas as chaves antigas `DAYS`/`OPENS_AT`/`CLOSES_AT`/`BREAK_*` da versão parcial, que nunca foram publicadas. |
| `app/Providers/AppServiceProvider.php` | `scoped(WhatsappBusinessHours::class)`. |
| `app/Models/Lead.php` | `whatsappReplySla()` usa `cutoff()` em vez de `now − minutos`. |
| `app/Http/Controllers/LeadController.php` | `awaitingReply`: `awaiting_reply_business_seconds` por item (só a página atual, 25 itens) e a prop `slaClock`. |
| `resources/js/pages/leads/awaiting-reply.tsx` | Relógio útil, badge, timer para o próximo limite real e aviso de pausa. |
| `tests/Feature/LeadWhatsappBusinessHoursSlaTest.php` | **Novo.** Suíte específica, reescrita nesta sessão. |
| `tests/Feature/LeadWhatsappSlaTest.php` | O `beforeEach` passou a usar o formato novo (todos os dias 00:00–24:00 em UTC, para isolar os limites do CRM-WA-13). |

Sem migration, rota nova, dependência nova, WAHA, n8n, webhooks ou infra.

## 8. Testes (`LeadWhatsappBusinessHoursSlaTest`)

A jornada de teste é explícita (`bhSlaWeek()`): segunda a sexta 08:00–18:00, sábado e domingo fechados, `America/Sao_Paulo`. Cobertura dos 47 itens:

- 1–5: início do expediente, 14m59s, 15m, 30m e 60m (dataset num dia só e dataset atravessando a noite).
- 6–11: antes da abertura, exatamente na abertura, depois do fechamento, exatamente no fechamento, cruzando o fechamento (18:00 e 23:00) e cruzando a abertura.
- 12–17: segunda → terça, sexta → segunda, sábado fechado, sábado aberto, domingo fechado e vários dias (inclusive uma semana).
- 18–20: outbound no expediente, outbound fora dele e nova inbound à noite (entra na fila com 0 s e fica Normal até 08:15).
- 21–27: responsável, transferência, leitura, nota, ligação, e-mail e follow-up.
- 28–29: Convertido e Perdido (o status não muda).
- 30–38: filtros por nível, combinação com `owner=mine`, status, produto e busca, e paginação (30 leads com mais de 14 h de relógio, mas Normal, e 27 críticos: `total=27` e páginas 25 + 2 na ordem).
- 39–41: contador crítico = fila = Dashboard = tela de leads.
- 42–43: duas inbound seguidas, e outbound seguido de nova inbound.
- 44–47: fuso operacional (Brasília × Manaus) sem mexer no `app.timezone`, serialização ISO UTC, fronteira por segundo e fronteira por microssegundo (`08:04:59.999999` × `08:05:00.000001`).
- Extras: equivalência corte ⇔ tempo útil, próximo limite depois do fim de semana, nenhum dia ativo, valores salvos inválidos e fallback documentado.

## 9. Resultados: NÃO EXECUTADOS nesta sessão

| Verificação | Situação |
|---|---|
| Suíte nova + `LeadWhatsappSlaTest` (SQLite) | não executado |
| Teste de mutação (`>=` → `>` em `cutoff`/scope) | não executado |
| Suíte completa SQLite | não executado |
| MySQL 8.4 descartável | não executado |
| `EXPLAIN`/benchmark | não executado. A forma da consulta **não mudou** em relação ao CRM-WA-13 (o mesmo `EXISTS`/`NOT EXISTS`, só com outro valor de corte), então o plano medido no CRM-WA-13 deve valer. Falta confirmar. |
| Regressões (awaiting reply, assignment, inbound, outbound, ACK, unread, conversa, Histórico, follow-up, Dashboard, Kanban, importação) | não executado |
| Pint, PHPStan, `tsc --noEmit`, ESLint, Prettier `--check`, `vite build` | não executado |

Para concluir, rode o roteiro de validação da memória do projeto: cópia do working tree no scratchpad, `docker run --rm --network none` com a imagem `infra-abrasil-abrasilsistema`, `mysql:8.4` em rede `--internal` com `tmpfs`, e `node:22-bookworm-slim` para o frontend. Basta pedir "valide o CRM-WA-14" numa sessão com o Bash funcionando.

## 10. Riscos e limitações

1. **Validação pendente:** este é o risco principal. O código foi escrito, mas não foi executado.
2. **Sem tela de configuração.** O correio pede a representação, não uma tela. Até alguém salvar o horário real, vale o fallback (e a fila avisa "horário padrão"). Para gravar o horário real após o deploy, com autorização, rode no container: `app(App\Services\WhatsappBusinessHours::class)->save(['timezone' => 'America/Sao_Paulo', 'days' => [1 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'], …, 7 => ['active' => false, 'opens_at' => '08:00', 'closes_at' => '12:00']]])`. Uma tela em `settings/leads` fica como recomendação futura.
3. **Uma janela por dia.** Intervalo de almoço não é suportado (a versão parcial suportava, mas o correio não pede).
4. **Feriados** contam como dia normal.
5. **Relógio do navegador** e contadores do Dashboard sem polling: os mesmos limites do CRM-WA-13. A página só conhece 14 dias de períodos, o que é suficiente porque o polling os renova a cada 15 s.
6. **Mudar o horário** reclassifica imediatamente todas as esperas, inclusive as passadas, porque nada é persistido. É coerente, mas pode surpreender.

## 11. Roteiro E2E manual (após o futuro deploy)

1. Abra **Prospecção › Aguardando resposta** e confira a descrição: "Seg a Sex, 08:00–18:00 (America/Sao_Paulo, horário padrão)". Salve o horário real (item 2 dos riscos) e confira se a descrição muda e o "horário padrão" some.
2. Em horário de expediente, envie "Olá" do celular de teste. O lead aparece como Normal, "há menos de 1 min". Aos 15:00 o badge vira Atenção, sem recarregar.
3. Às 17:55, envie uma mensagem de outro número. Às 18:00 ele mostra "há 5 min", e às 18:30 continua "há 5 min", com o aviso "SLA pausado".
4. Na manhã seguinte, às 08:10, o mesmo lead vira **Atenção** no instante exato.
5. Envie uma mensagem às 20:00. Ela entra na fila como Normal, "há menos de 1 min", e continua assim até a abertura.
6. Numa sexta às 17:55, envie uma mensagem e confira na segunda: às 08:05 mostra "há 10 min", e às 08:10 vira Atenção.
7. Use **SLA = Crítico**: a quantidade bate com "N críticos" do Dashboard e com o badge da tela de leads.
8. Assuma, transfira, leia, registre uma nota e agende follow-up: o badge não muda.
9. Responda pela conversa (ou pelo celular): o lead sai da fila. Uma nova mensagem do cliente o traz de volta como Normal.
10. Regressão: não lidas, conversa, ACK, Kanban, follow-up e importação continuam iguais.

## 12. Confirmação

```text
Não houve deploy.
Não houve commit nem push.
Não houve alteração em produção.
Não houve migration em produção.
WAHA e n8n permaneceram intactos.
```

Nenhum comando foi executado nesta sessão: não houve `git reset`, `git clean` nem descarte de alterações. O working tree foi preservado, e só os arquivos listados na seção 7 foram editados. O trabalho parou no **CRM-WA-14**, e não houve avanço para o CRM-WA-15.
