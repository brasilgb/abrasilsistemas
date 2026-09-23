# Execução de `correio.md` — Status das mensagens WhatsApp no histórico do prospect

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
