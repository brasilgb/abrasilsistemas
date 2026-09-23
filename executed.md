# Execução de `correio.md`: envio manual de WhatsApp pela tela do prospect

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
