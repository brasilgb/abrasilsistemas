# Execução de `correio.md`: recebimento de mensagens WhatsApp (`POST /api/whatsapp/inbound`)

Data: 2026-09-24.

## Resumo

**Implementado e testado. Não houve deploy nem commit, nenhuma migration foi criada e o banco de produção não foi acessado.** O novo endpoint `POST /api/whatsapp/inbound` recebe do n8n uma mensagem de texto já extraída do evento `message.any`. Ele localiza o lead pelo telefone do remetente, usando a equivalência de nono dígito que o projeto já tinha, e grava uma `LeadActivity` com `type = whatsapp`, `direction = inbound` e `message_status = null`. Nenhum lead é criado. Mensagens repetidas não geram duplicata.

n8n, WAHA, sessão `vetoros1-1`, docker-compose, frontend, `LeadWhatsappService`, o endpoint de log do envio e o de ACK **não foram alterados**.

## 1. Auditoria encontrada

| Ponto | Onde | O que encontrei |
|---|---|---|
| Rotas API do WhatsApp | `routes/api.php` | `POST prospects/{lead}/whatsapp/log` (throttle 30/min) e `PATCH whatsapp/messages/{providerMessageId}/status` (throttle 120/min), ambas com o middleware `prospect.token` |
| Autenticação do n8n | `app/Http/Middleware/EnsureValidProspectToken.php` (alias `prospect.token` em `bootstrap/app.php`) | `Authorization: Bearer <token>`, comparado com `hash_equals` ao token da tabela `settings` (`prospect_api_token`) ou, sem registro, ao `services.ab_prospect.token`. Token vazio → 401 |
| Lead | `app/Models/Lead.php`, migration `create_leads_table` | Contatos: `phone`, `whatsapp`, `email`, `instagram`, `website`. Índice em `whatsapp`. Soft delete |
| Como os telefones são gravados | `LeadRequest`, `PublicContactRequest`, importação CSV (`LeadController`) | Só dígitos, **sem** padronizar o 55 (podem ter 10/11 dígitos ou 12/13 com 55) |
| | `Api/ProspectImportController` (importação do n8n) | Grava `whatsapp` **como veio**, sem normalizar (pode ter máscara). Sem WhatsApp, usa o `phone` |
| Regra de negócio do WhatsApp do lead | `LeadWhatsappService::send`, `Lead::findDuplicate` | Só o campo `whatsapp` identifica o WhatsApp do lead. O envio nunca usa `phone` |
| Normalização existente | `LeadWhatsappService::normalizeNumber` | Só dígitos. Com 10/11 dígitos, prefixa 55. Com 12/13 começando com 55, mantém. Senão, `null` |
| Equivalência do nono dígito existente | `CompanyWhatsappConnection::normalizeJid` e `::sameNumber` | `normalizeJid` tira o sufixo `@…` e deixa só dígitos. `sameNumber` compara ignorando o nono dígito de números com 13 dígitos (`5551998931325` ≡ `555198931325`). **Reutilizados sem alteração** |
| LeadActivity | `app/Models/LeadActivity.php` | Constantes `DIRECTION_INBOUND/OUTBOUND` e `isInbound()`. Colunas `provider_message_id` (nullable, **sem UNIQUE**, índice `(lead_id, provider_message_id)`) e `message_status` |
| `WhatsappActivityController` / `LogWhatsappActivityRequest` | Endpoint de log do envio | Já aceita `direction=inbound`, mas exige que o n8n saiba o `lead_id`. A idempotência verifica só dentro do lead |
| `WhatsappMessageStatusController` | ACK | Ignora ACK de atividade `inbound` (200, `ignored: true`). Para "não encontrada", responde 404 |
| Testes | `tests/Feature/Whatsapp*ApiTest.php`, `LeadWhatsappSendTest`, `LeadHistoryWhatsappStatusTest`, `CompanyWhatsapp*Test` | Pest, SQLite em memória, token via `config('services.ab_prospect.token')` |
| Cache/lock | `.env.example` (`CACHE_STORE=database`), migration `0001_01_01_000001_create_cache_table` | A tabela `cache_locks` existe, então `Cache::lock` funciona em produção |

## 2. Arquitetura adotada

Segue o padrão dos endpoints do n8n: um FormRequest em `Requests/Api`, um controller em `Controllers/Api` e a rota em `routes/api.php` com `throttle` e `prospect.token`. Não criei service novo. A busca do lead ficou em `Lead::findByWhatsappNumber()`, ao lado de `findDuplicate()`, e reutiliza as funções de normalização e equivalência que já existiam.

Fluxo do `WhatsappInboundController@store`:

1. O FormRequest valida o payload e normaliza `from` para dígitos.
2. Se o WhatsApp da empresa tem sessão configurada no painel e ela é diferente de `session`, a mensagem é ignorada (`session_mismatch`). O WAHA é compartilhado com outras sessões. Sem sessão configurada, a mensagem é aceita, como no envio.
3. Um lock por `provider_message_id` (`Cache::lock`, 10 s, espera de até 5 s) serializa o processamento da mesma mensagem.
4. Se já existe uma atividade `whatsapp` com o mesmo `provider_message_id`, o endpoint devolve sucesso idempotente.
5. Localiza o lead. Se não encontrar, responde `lead_not_found` sem gravar nada.
6. Cria a atividade inbound e atualiza `leads.last_contacted_at`, como o endpoint de log já fazia.

Preparado para expansão: quando houver mídia, entram campos novos no FormRequest (ex.: `type`, `media_url`). O controller e a busca do lead não mudam. Nada de mídia foi implementado.

## 3. Rota criada

```
POST /api/whatsapp/inbound   → Api\WhatsappInboundController@store
middleware: throttle:120,1, prospect.token
name: api.whatsapp.inbound
```

Payload:

```json
{
  "session": "vetoros1-1",
  "provider_message_id": "3EB00B502BEA742D043912",
  "from": "555195179173",
  "push_name": "Anderson Brasil",
  "message": "Teste inbound 01"
}
```

Validação (`StoreWhatsappInboundRequest`):

| Campo | Regra |
|---|---|
| `session` | obrigatório, string, mesmo padrão de sessão do painel (`CompanyWhatsappSettings::SESSION_PATTERN`) |
| `provider_message_id` | obrigatório, string, até 255 |
| `from` | obrigatório, string, até 64. Aceita telefone (com ou sem máscara/`+`) ou JID `@c.us` / `@s.whatsapp.net`. **JID `@lid` é recusado (422)**. Depois de normalizado, precisa ter de 10 a 15 dígitos |
| `push_name` | opcional, string, até 255 (validado, não persistido: não há coluna para ele) |
| `message` | obrigatório, string, até 5000 (mesmo limite do endpoint de log) |

Respostas:

| Situação | HTTP | Corpo |
|---|---|---|
| Registrada | 201 | `{"success": true, "message": "Mensagem recebida registrada.", "activity_id": …, "lead_id": …}` |
| Já registrada (mesmo `provider_message_id`) | 200 | `{"success": true, "message": "Mensagem recebida já estava registrada.", "activity_id": …, "lead_id": …, "idempotent": true}` |
| Nenhum lead com o número | 200 | `{"success": false, "reason": "lead_not_found", "message": "…"}` |
| Sessão diferente da configurada | 200 | `{"success": false, "reason": "session_mismatch", "message": "…"}` |
| Mesma mensagem ainda em processamento (lock) | 409 | `{"success": false, "reason": "processing", "message": "…"}` |
| Sem token / token inválido | 401 | `{"message": "Token inválido."}` |
| Payload inválido | 422 | erros de validação do Laravel |

**Por que 200 para `lead_not_found`.** Mensagem de número desconhecido é tráfego normal: qualquer pessoa pode mandar mensagem para a empresa. Não é falha de integração. Com 4xx, o nó HTTP do n8n marca a execução como erro e as tentativas de repetição nunca teriam sucesso. É o mesmo critério que o `WhatsappMessageStatusController` já usa para ACK ignorado: 2xx com um indicador no corpo. O `success: false` com `reason` deixa o resultado explícito para o n8n. O 404 do ACK continua reservado para o caso anômalo de mensagem enviada que não existe.

## 4. Autenticação utilizada

O mesmo middleware `prospect.token` (`EnsureValidProspectToken`) das outras rotas chamadas pelo n8n: `Authorization: Bearer <token de integração>`. O token pode ser o do painel (Configurações → Leads) ou o de `services.ab_prospect.token`. Nenhum token novo foi criado ou exposto. O endpoint não é público.

## 5. Regra usada para localizar o lead

- Só o campo **`leads.whatsapp`**, que é a regra do projeto: o envio e a deduplicação usam esse campo. `phone`, `email` etc. não entram. A importação do n8n já copia o `phone` para `whatsapp` quando o WhatsApp vem vazio.
- Leads com soft delete ficam de fora (escopo padrão do model).
- Equivalência: `CompanyWhatsappConnection::sameNumber()` sobre os dois números passados por `LeadWhatsappService::normalizeNumber()`. Assim são equivalentes: com ou sem 55, com ou sem o nono dígito, com ou sem máscara.
- Pré-filtro SQL: `whatsapp LIKE '%d%d%d%d%d%d%d%d'` com os 8 últimos dígitos do remetente. Esses dígitos existem em todas as variações do número, inclusive com máscara, e funcionam em MySQL e SQLite. A comparação que decide é a do PHP.
- Se mais de um lead tiver o número, fica o de `last_contacted_at` mais recente (depois, o maior `id`).
- O LID nunca é usado como telefone: `@lid` é recusado na validação. Se o n8n mandar só os dígitos do LID, eles não correspondem a nenhum lead e o resultado é `lead_not_found` (há teste para isso).

## 6. Normalização aplicada

`from` → `CompanyWhatsappConnection::normalizeJid()`: tira o sufixo `@…` e deixa só dígitos. Exemplo: `555195179173@s.whatsapp.net` → `555195179173`. Para comparar, `LeadWhatsappService::normalizeNumber()` padroniza o 55 dos dois lados e `sameNumber()` ignora o nono dígito. **Nenhuma regra nova de telefone foi escrita.** Números fora do padrão brasileiro são comparados só pelos dígitos.

## 7. Estratégia de idempotência

1. **Verificação antes de inserir**, global: qualquer atividade `whatsapp` com o mesmo `provider_message_id`, de qualquer lead e em qualquer direção. Isso também protege caso o n8n repasse por engano uma mensagem `fromMe` (o `message.any` inclui as enviadas): o id dela já está registrado como outbound, então a resposta é idempotente e nada muda.
2. **Lock de aplicação** por `provider_message_id` (`Cache::lock`). É necessário porque o WAHA dispara `message` **e** `message.any` para a mesma mensagem, e os dois eventos vão para o mesmo webhook. Os dois POSTs podem chegar juntos.
3. **UNIQUE no banco: não criado.** Motivos:
   - `provider_message_id` também é gravado pelo endpoint de log, cuja idempotência é só por lead. Não há garantia de que a produção esteja sem duplicatas. A leitura do banco de produção não foi feita.
   - Um UNIQUE global mudaria o comportamento do fluxo outbound: um INSERT duplicado viraria erro 500 em vez de ser tratado. Isso contraria a exigência de não mexer no outbound.

   Recomendação para uma etapa futura: rodar a consulta de duplicatas do item 13. Se ela vier vazia, avaliar um UNIQUE `(type, provider_message_id)`. `NULL` não conflita no MySQL. Antes, o log do envio precisa tratar `QueryException` de chave duplicada.

## 8. Arquivos criados

| Arquivo | Conteúdo |
|---|---|
| `app/Http/Controllers/Api/WhatsappInboundController.php` | Endpoint: sessão, lock, idempotência, busca do lead, criação da atividade inbound |
| `app/Http/Requests/Api/StoreWhatsappInboundRequest.php` | Validação e `senderNumber()` (remetente só com dígitos) |
| `tests/Feature/WhatsappInboundApiTest.php` | 34 testes (com datasets) |

## 9. Arquivos alterados

| Arquivo | Alteração |
|---|---|
| `app/Models/Lead.php` | Novo `Lead::findByWhatsappNumber()` (+31 linhas). Nada existente foi mudado |
| `routes/api.php` | Rota `POST whatsapp/inbound` (+5 linhas) |

Não foram alterados: `WhatsappActivityController`, `LogWhatsappActivityRequest`, `WhatsappMessageStatusController`, `LeadActivity`, `LeadWhatsappService`, `CompanyWhatsappConnection`, migrations, frontend. O `correio.md` já estava modificado antes desta execução.

## 10. Testes executados e resultados

Ambiente: cópia do working tree na scratchpad, `composer install` em container descartável da imagem `infra-abrasil-abrasilsistema`, `.env` de teste gerado a partir do `.env.example` com `key:generate`, e testes em `docker run --rm --network none` com o SQLite em memória do `phpunit.xml`. Nenhum PHP rodou no container de produção. Nenhum banco real foi acessado.

```text
WhatsappInboundApiTest                                       34 passed (121 assertions)
WhatsApp + Leads + Import (9 arquivos, inclui o novo)       211 passed (937 assertions)
Suíte completa                                  253 passed, 1 failed (1077 assertions)
Pint --test (5 arquivos novos/alterados)                                        PASS
route:list --path=api/whatsapp               POST api/whatsapp/inbound presente
```

A única falha já existia e não tem relação com esta tarefa: `BlogTest > administrator can upload blog images` (`imagejpeg function is not defined`, GD da imagem sem JPEG). É a mesma falha registrada nas etapas anteriores.

Cobertura pedida no correio:

| Requisito | Teste |
|---|---|
| Autenticação obrigatória | `requires the integration token` (sem token e com token errado → 401, nada gravado) |
| Payload inválido | `rejects an invalid payload` (10 casos: sem session, session inválida, sem provider_message_id, sem from, **from `@lid`**, from com letras, from curto, sem message, message array, push_name array) |
| Inbound válido, `direction = inbound`, `message_status = null`, `user_id = null`, `provider_message_id` e texto persistidos | `records a received text message on the matching lead` (também confere `type`, `status`, `contacted_at`, `last_contacted_at` e que o status comercial do lead não muda) |
| Telefone normalizado | `normalizes the sender before looking up the lead` (máscara com `+`, JID `@s.whatsapp.net`, JID `@c.us`) |
| Lead encontrado pelo telefone / nono dígito | `matches Brazilian numbers with or without the ninth digit and country code`: inclui o caso validado `5551998931325` ≡ `555198931325` nos dois sentidos, lead sem 55 e lead gravado com máscara |
| Lead não encontrado | `does not create a lead or an activity for an unknown sender`, `does not match a different number with the same ending` (outro DDD), `does not look up the lead by the LID or other contact fields`, `ignores soft deleted leads` |
| `provider_message_id` duplicado | `does not duplicate a message with the same provider_message_id` e `does not turn an already logged outbound message into an inbound one` |
| Outbound continua funcionando | `the outbound log and ACK keep working alongside the inbound API`, mais os 60+ testes existentes de log, ACK e envio, todos passando |
| ACK de inbound ignorado | `an ACK for a message received by the inbound API is ignored` (SERVER, DEVICE, READ) |
| Extras | `accepts push_name as optional`, `picks the most recently contacted lead when a number is shared`, `only accepts the configured company session` |

## 11. Riscos

1. **Sem UNIQUE no banco.** O lock cobre as chamadas concorrentes que passam pelo Laravel, mas não é uma garantia estrutural (ver item 7).
2. **Pré-filtro com `LIKE '%…'` não usa índice.** Faz varredura em `leads`. No volume atual de um CRM é desprezível. Se a tabela crescer muito, o caminho é normalizar `leads.whatsapp` na gravação, a começar pela importação do n8n, que hoje não normaliza.
3. **Número compartilhado por mais de um lead.** A mensagem vai para o lead contatado mais recentemente. Os outros não a recebem.
4. **Só o campo `whatsapp` identifica o lead.** Um lead com o número só em `phone` não é encontrado (`lead_not_found`). Foi decisão consciente, seguindo a regra do envio.
5. **`push_name` não é gravado**, porque não há coluna para ele e não criei migration. Pode virar coluna ou entrar na descrição em uma etapa futura, se fizer sentido.
6. **Filtro de sessão só vale com o WhatsApp da empresa configurado no painel.** Sem configuração, qualquer `session` é aceita.
7. **Mensagem inbound atualiza `last_contacted_at`**, igual ao endpoint de log atual. Se o Kanban ou algum relatório tratar esse campo como "a empresa contatou o lead", o significado passa a incluir "o lead respondeu".
8. **Achado da etapa anterior ainda vale:** hoje o workflow "WAHA - Mensagens Recebidas → CRM" só tem o nó Webhook. Até o n8n chamar este endpoint (fora do escopo desta tarefa), nada inbound chega ao CRM.

## 12. Migration

**Não foi necessária.** Todas as colunas usadas (`direction`, `provider_message_id`, `message_status`) já existem. A migration `2026_09_24_120000_add_direction_to_lead_activities_table` é da etapa anterior (commit `9920809`). Se ela ainda não foi aplicada em produção, precisa rodar antes deste deploy (passo 2 abaixo).

## 13. Comandos para o deploy posterior (NÃO executados)

```bash
cd /opt/infra-abrasil

# 0) (Só leitura) Conferir se a migration de direction já está aplicada
docker compose exec abrasilsistema php artisan migrate:status | grep add_direction

# 0b) (Só leitura, recomendado) Medir duplicatas de provider_message_id antes de pensar em UNIQUE
docker compose exec abrasilsistema php artisan tinker --execute='dump(DB::table("lead_activities")->whereNotNull("provider_message_id")->select("provider_message_id", DB::raw("count(*) as total"))->groupBy("provider_message_id")->having("total", ">", 1)->get());'

# 1) Rebuild das imagens (os containers atuais continuam no ar)
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 2) SOMENTE se o passo 0 mostrar a migration de direction como "Pending":
docker compose run --rm --no-deps abrasilsistema php artisan migrate --force

# 3) Deploy
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 4) Validação pós-deploy
docker compose ps abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose exec abrasilsistema php artisan route:list --path=api/whatsapp
#    Sem token deve responder 401:
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H 'Host: abrasilsistema.localhost' -H 'Accept: application/json' http://127.0.0.1/api/whatsapp/inbound
#    Com o token do próprio container (não é impresso) e um número inexistente,
#    deve mostrar 200 {"success":false,"reason":"lead_not_found",...} sem gravar nada:
docker compose exec abrasilsistema php artisan tinker --execute='$t = App\Models\Setting::valueFor(App\Models\Setting::PROSPECT_API_TOKEN, config("services.ab_prospect.token")); $r = app()->handle(Illuminate\Http\Request::create("/api/whatsapp/inbound", "POST", [], [], [], ["HTTP_AUTHORIZATION" => "Bearer ".$t, "HTTP_ACCEPT" => "application/json", "CONTENT_TYPE" => "application/json"], json_encode(["session" => "vetoros1-1", "provider_message_id" => "DEPLOY-CHECK-".time(), "from" => "551100000000", "message" => "teste deploy"]))); dump($r->getStatusCode(), $r->getContent());'
```

Rollback: voltar a imagem anterior e rodar `docker compose up -d` nos 3 serviços. Não há migration nesta etapa.

### Próximo passo no n8n (não feito, só referência)

No workflow "WAHA - Mensagens Recebidas → CRM", depois do Webhook:

1. Filtrar `payload.fromMe = false` e mensagens de texto (`payload._data.message.conversation` ou `payload._data.message.extendedTextMessage.text`).
2. HTTP Request `POST https://<crm>/api/whatsapp/inbound`, com credencial Header Auth `Authorization: Bearer <token de integração>` (a mesma dos workflows de envio e ACK), e o corpo:
   - `session` = `{{$json.body.session}}`
   - `provider_message_id` = `{{$json.body.payload._data.key.id}}`
   - `from` = `remoteJidAlt` quando `addressingMode = lid`; senão, `remoteJid` (o endpoint aceita o JID com `@s.whatsapp.net`)
   - `push_name` = `{{$json.body.payload._data.pushName}}`
   - `message` = texto extraído no passo 1
3. Tratar `success: false` (`lead_not_found` / `session_mismatch`) como fim normal, não como erro.

---

# Execução de `correio.md`: direção das mensagens WhatsApp (inbound/outbound)

Data: 2026-09-24.

## Resumo

**Implementado e testado. Não houve deploy, a migration não rodou em produção e nada foi commitado.** A tabela `lead_activities` ganhou a coluna `direction` (`inbound` / `outbound`, com null para legado e para o que não é mensagem). O endpoint que o n8n usa para registrar mensagens aceita `direction`. Quando ele não vem, a mensagem é `outbound`, que é o contrato atual do workflow de envio. Mensagem inbound grava `message_status = null` e nunca recebe ACK. O histórico do lead passa a mostrar "Enviada · Empresa → Cliente" ou "Recebida · Cliente → Empresa", e o status de entrega só aparece nas enviadas.

n8n, WAHA, webhooks, sessão `vetoros1-1`, credenciais, docker-compose, `LeadWhatsappService`, a conexão/QR Code e a regra de progressão do ACK **não foram alterados**.

> ⚠ **Achado principal da auditoria: hoje o fluxo de entrada não chega ao Laravel.** O workflow publicado "WAHA - Mensagens Recebidas → CRM" (`sDkp8A7egcrpaL7y`, versão `55d2bc85…`) tem **só o nó Webhook**, sem nenhum HTTP Request para o CRM. As 4 versões no histórico do n8n são iguais nesse ponto. O único workflow que chama `POST /api/prospects/{lead}/whatsapp/log` é o **"CRM ABrasil - Enviar WhatsApp"**. Portanto o registro "Teste recebimento CRM 02" (lead 118, `3EB0C2EF5A6141DC0935D3`) **muito provavelmente foi gravado pelo workflow de envio**, ou seja, é uma mensagem *enviada* pelo CRM. Isso também explicaria o `message_status = DEVICE`, que teria vindo do ACK de uma mensagem enviada. Não consegui confirmar no MySQL, porque a leitura do banco de produção foi bloqueada pela política de permissões e não tentei contornar. Veja o item 12 e a consulta proposta no item 13.

## 1. Auditoria realizada

| Ponto | Onde | Observação |
|---|---|---|
| Migrations | `database/migrations/2026_07_16_145200_create_lead_activities_table.php`, `…2026_09_23_120000_add_whatsapp_provider_fields_to_lead_activities_table.php` | `type` string (default `note`); `provider_message_id` e `message_status` nullable; índice `(lead_id, provider_message_id)` |
| Model | `app/Models/LeadActivity.php` | Atributo `#[Fillable]`, constantes de string (`TYPES`, `MESSAGE_STATUS_*`), regra `canTransitionMessageStatusTo`. Não há enums PHP no projeto, então segui as constantes |
| Criação de LeadActivity `whatsapp` | `Api/WhatsappActivityController@store` (n8n) e `LeadActivityController@store` (registro manual, o usuário pode escolher o tipo "WhatsApp") | `LeadController` só cria `note` (Kanban/lote) |
| Envio | `LeadWhatsappService` → webhook n8n `crm/send-whatsapp` | O serviço **não** grava histórico: quem grava é o n8n, pelo endpoint de log |
| Endpoint n8n de log | `POST /api/prospects/{lead}/whatsapp/log` (`prospect.token`, `throttle:30,1`) | Payload do workflow de envio: `{message_id: key.id, status: <status da resposta do WAHA>, message}` e **sem campo de direção** |
| Endpoint de ACK | `PATCH /api/whatsapp/messages/{id}/status` (`prospect.token`, `throttle:120,1`) | Workflow "WAHA - Status WhatsApp": `message_id = payload.id.split('_').pop()`, `status = ackName` |
| Mensagens recebidas no n8n | Workflow "WAHA - Mensagens Recebidas → CRM" | **Só o nó Webhook (`waha-message-received`), sem chamada ao Laravel** (ver o destaque acima) |
| Serialização | `LeadController@edit` | Carrega `activities` com o model inteiro e oculta `provider_message_id`. O `DashboardController` seleciona colunas fixas e não usa status nem direção |
| Frontend | `pages/leads/edit.tsx` (histórico), `components/whatsapp-message-status.tsx`, tipo `LeadActivity` em `pages/leads/form.tsx` | O status aparece em toda atividade `whatsapp`. O dashboard mostra só a descrição |
| Testes existentes | `WhatsappActivityApiTest`, `WhatsappMessageStatusApiTest`, `LeadHistoryWhatsappStatusTest`, `LeadWhatsappSendTest` | |

Os workflows foram lidos em uma **cópia** do `database.sqlite` do n8n na scratchpad, só pelas definições (`workflow_entity`, `workflow_history`, `workflow_published_version`). O n8n não foi tocado e o conteúdo das execuções não foi lido.

## 2. Arquivos criados

- `database/migrations/2026_09_24_120000_add_direction_to_lead_activities_table.php`

## 3. Arquivos alterados

| Arquivo | Mudança |
|---|---|
| `app/Models/LeadActivity.php` | `direction` no fillable; `DIRECTION_INBOUND`, `DIRECTION_OUTBOUND`, `DIRECTIONS`; `isInbound()` |
| `app/Http/Requests/Api/LogWhatsappActivityRequest.php` | `direction` opcional (`inbound`/`outbound`, normalizado para minúsculas); `status` passa a ser `required_unless:direction,inbound` |
| `app/Http/Controllers/Api/WhatsappActivityController.php` | Grava `direction` (padrão `outbound`); inbound grava `message_status = null`; mensagens de resposta específicas para inbound. O JSON do outbound não mudou |
| `app/Http/Controllers/Api/WhatsappMessageStatusController.php` | ACK para uma atividade inbound responde `200 {success: true, ignored: true}` sem alterar nada |
| `resources/js/components/whatsapp-message-status.tsx` | Novo `WhatsappMessageDirection` e tipo `WhatsappMessageDirectionValue` |
| `resources/js/pages/leads/edit.tsx` | Direção ao lado do status; status de entrega oculto para inbound |
| `resources/js/pages/leads/form.tsx` | `direction?` no tipo `LeadActivity` |
| `tests/Feature/WhatsappActivityApiTest.php`, `WhatsappMessageStatusApiTest.php`, `LeadHistoryWhatsappStatusTest.php` | Testes novos (item 9) |

## 4. Migration criada

```php
$table->string('direction', 10)->nullable()->after('type');   // down(): dropColumn('direction')
```

- **String nullable, sem default.** Segue o padrão de `type` e `message_status`, que são strings validadas por constantes. Não usei enum no MySQL: mudar os valores depois exigiria `ALTER`, e o projeto não usa enums em nenhuma tabela.
- **Null** vale para notas, ligações, e-mails, reuniões, registros manuais e para o legado anterior à coluna. Nenhum registro histórico muda.
- **Sem índice.** O histórico é sempre filtrado por `lead_id`, que já tem os índices `(lead_id, created_at)` e `(lead_id, provider_message_id)`. O volume por lead é pequeno, então um índice em `direction` não ajudaria.
- `ADD COLUMN` nullable no MySQL 8.4 é uma operação instantânea, sem reescrever a tabela.
- Testei `migrate` e `rollback` em SQLite descartável: a coluna é criada e removida sem erro.

## 5. Regra final de direction

| Origem | direction |
|---|---|
| `POST …/whatsapp/log` **sem** `direction` (workflow de envio atual) | `outbound` |
| `POST …/whatsapp/log` com `direction: "outbound"` | `outbound` |
| `POST …/whatsapp/log` com `direction: "inbound"` | `inbound` |
| `direction` com outro valor | `422` |
| Registro manual pelo formulário do lead (qualquer tipo, inclusive "WhatsApp") | `null`, porque é anotação de um humano e não mensagem trafegada |
| Notas automáticas (Kanban/lote) | `null` |
| Registros anteriores à migration | `null` |

O padrão `outbound` é seguro porque o único chamador do endpoint hoje é o workflow de envio (verificado nas definições do n8n). O contrato dele continua igual e não precisa mudar.

**Para o recebimento funcionar**, o workflow "WAHA - Mensagens Recebidas → CRM" vai precisar (numa tarefa futura, já que alterar o n8n estava fora do escopo) de um HTTP Request com a mesma credencial Header Auth `Bearer`:

```json
POST https://abrasilsistemas.com.br/api/prospects/{lead_id}/whatsapp/log
{ "direction": "inbound", "message_id": "<id curto>", "message": "<texto>" }
```

Pontos de atenção para esse workflow:
- **filtrar `payload.fromMe = false`**, porque `message.any` também dispara para as mensagens enviadas;
- usar o id curto (`payload.id.split('_').pop()`), igual ao workflow de ACK, para a deduplicação funcionar;
- resolver o `lead_id` pelo número do remetente. Não existe endpoint de busca de lead por telefone, e isso também fica para a próxima etapa.

## 6. `message_status` para inbound

- **Inbound grava `message_status = null`**, mesmo se o payload mandar `status` (por exemplo `DEVICE`), porque o valor é descartado. Para inbound o `status` é opcional.
- **Um ACK que chegue para uma mensagem inbound é ignorado** (`200`, `ignored: true`, sem gravar). Assim uma mensagem recebida nunca vira DEVICE/READ, nem se o workflow de ACK repassar eventos de mensagens recebidas.
- A regra de progressão (`PENDING → SERVER → DEVICE → READ → PLAYED`, ERROR só antes de DEVICE) **não mudou**. A checagem de inbound entra antes dela. Registros com `direction = null` (legado) continuam recebendo ACK como antes.
- Nenhum contrato quebra: o frontend já tratava `message_status = null` (não mostra nada), e o JSON do endpoint para outbound é idêntico.

## 7. Estratégia para registros históricos

**Nenhum registro foi alterado.** O legado fica com `direction = null`, e a interface mostra esses registros como antes (status, se houver, e nenhuma direção).

Regra que **proponho, mas não apliquei**. A consulta do banco de produção foi bloqueada, então não pude medir quantos registros ela afetaria:

> `type = 'whatsapp' AND user_id IS NULL AND provider_message_id IS NOT NULL AND direction IS NULL` → `outbound`

Justificativa: esse conjunto só pode ter sido criado pelo `POST …/whatsapp/log`, e até esta tarefa o único chamador era o workflow de envio. Registros `whatsapp` com `user_id` preenchido são manuais e continuam `null`.

**A regra depende de uma confirmação sua:** o registro "Teste recebimento CRM 02" teria de ser confirmado como *enviado*. Se ele de fato foi recebido, alguma coisa fora do que encontrei chamou o endpoint, e a regra deixaria de ser segura. Veja a consulta no item 13.

## 8. Alterações de frontend

No card de cada atividade `whatsapp` do histórico (`leads/edit.tsx`), na linha onde já aparecia o status:

- **outbound:** `↗ Enviada · Empresa → Cliente` e, ao lado, o status de sempre (Enviando / Enviada / Entregue / Lida / Reproduzida / Erro no envio);
- **inbound:** `↙ Recebida · Cliente → Empresa` em verde, **sem** status de entrega (nunca "Entregue");
- **legado ou manual (`null`):** igual a antes, só o status se existir. A linha some quando está vazia (`empty:hidden`).

Nada mais no layout mudou. O dashboard não foi alterado porque não mostra status nem tipo.

## 9. Testes executados

Casos novos:

| Requisito | Teste |
|---|---|
| A: envio pelo CRM → `whatsapp` + `outbound` | `records a send without direction as outbound` (contrato real do workflow de envio) e `records an explicit outbound message` |
| B: recebimento → `whatsapp` + `inbound` | `records a received message as inbound without a delivery status` |
| C: inbound sem DEVICE artificial | o mesmo teste, com o dataset "with an artificial DEVICE status"; e `never applies an ACK to a received message` (6 status) |
| D: outbound aceita SERVER/DEVICE/READ/PLAYED | `keeps the ACK progression for outbound messages` (e os testes antigos de progressão) |
| E: proteção contra regressão | `does not regress outbound messages` (READ→DEVICE, PLAYED→READ, DEVICE→ERROR) e os testes antigos |
| F: inbound mantém lead_id/description/provider_message_id/contacted_at | assertions no teste B, que também confere `last_contacted_at` do lead |
| G: atividades que não são WhatsApp | `manual and non WhatsApp activities keep working without a direction` (fluxo real do formulário) e os testes antigos |
| H: autenticação | `rejects an inbound message without a valid token` e os testes antigos de token/401 dos dois endpoints e de login no envio |
| Extras | `deduplicates a received message`, `rejects an invalid direction`, `still requires a status for outbound messages`, `lead history exposes the direction of WhatsApp messages` (e `provider_message_id` continua oculto) |

Ambiente: cópia do working tree na scratchpad, `composer install` em container descartável da imagem `infra-abrasil-abrasilsistema`, e testes em `docker run --rm --network none` com o SQLite em memória do `phpunit.xml`. Nenhum PHP rodou no container de produção e nenhum banco real foi acessado ou migrado.

## 10. Resultado dos testes

```text
Arquivos WhatsApp + LeadManagement   125 passed (632 assertions)
Suíte completa                       219 passed, 1 failed (956 assertions)
Pint --test (PHP alterados/novos)    PASS (10 arquivos)
Migration up/rollback (SQLite)       OK
```

A única falha já existia e não tem relação com esta tarefa: `BlogTest > administrator can upload blog images` (`imagejpeg function is not defined`, GD da imagem sem JPEG). É a mesma falha registrada nas etapas anteriores.

## 11. Resultado do build

`php artisan wayfinder:generate --with-form` (imagem do projeto) e depois, em `node:22-bookworm-slim`:

- `tsc --noEmit`: OK
- `SKIP_WAYFINDER_GENERATE=1 npm run build`: OK (só o aviso de chunk > 500 kB, que já existia)
- ESLint/Prettier: `whatsapp-message-status.tsx` e `form.tsx` OK. O `edit.tsx` tem 3 erros de ESLint (`import/order`, `consistent-type-specifier-style`) e trechos fora do Prettier que **já existiam no HEAD** (conferi rodando as ferramentas na versão do HEAD). As linhas novas estão formatadas, e não mexi no restante para manter o diff pequeno.

## 12. Riscos e pendências

1. **O recebimento não está ligado.** O workflow de mensagens recebidas não chama o Laravel (item 5). Até ele ser completado, nenhuma mensagem inbound será gravada. O Laravel já está pronto para recebê-las.
2. **O registro "Teste recebimento CRM 02" precisa ser confirmado** com a consulta do item 13. Não foi possível ler o banco de produção.
3. **Ordem do deploy.** O código novo grava `direction`. Se ele subir antes da migration, o `POST …/whatsapp/log` do n8n falha (coluna inexistente) e a mensagem sai pelo WhatsApp sem ir para o histórico. Por isso o roteiro do item 13 **aplica a migration com a imagem nova antes do `up -d`**. A coluna nova é nullable e o código antigo a ignora, então esse passo é seguro com os containers atuais no ar.
4. Registros de atividade "WhatsApp" criados manualmente pelo formulário ficam com `direction = null`, porque não há como saber a direção. Se quiser, o formulário pode ganhar essa escolha depois.
5. O backfill do legado (item 7) segue pendente, à espera da sua decisão.

## 13. Comandos para você executar manualmente

Nenhum destes comandos foi executado.

```bash
cd /opt/infra-abrasil

# 0) (Recomendado) Conferir o registro "Teste recebimento CRM 02" e medir o legado (só leitura)
docker compose exec abrasilsistema php artisan tinker --execute='
  dump(DB::table("lead_activities")->where("provider_message_id","3EB0C2EF5A6141DC0935D3")->first(["id","lead_id","user_id","type","message_status","created_at"]));
  dump(DB::table("lead_activities")->where("type","whatsapp")->selectRaw("user_id is null as auto, provider_message_id is not null as has_id, count(*) c")->groupBy("auto","has_id")->get());'

# 1) Backup da tabela antes da migration
docker compose exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" abrasilsistemas lead_activities' \
  > backups/lead_activities_$(date +%Y%m%d_%H%M%S).sql

# 2) Rebuild das imagens (containers atuais continuam no ar)
docker compose build abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 3) Aplicar a migration com a IMAGEM NOVA, antes de trocar os containers
docker compose run --rm --no-deps abrasilsistema php artisan migrate --force
docker compose run --rm --no-deps abrasilsistema php artisan migrate:status | grep add_direction

# 4) Deploy
docker compose up -d abrasilsistema abrasilsistema-worker abrasilsistema-scheduler

# 5) Validação pós-deploy
docker compose ps abrasilsistema abrasilsistema-worker abrasilsistema-scheduler
docker compose exec abrasilsistema php artisan tinker --execute='dump(Schema::hasColumn("lead_activities","direction"));'
docker compose exec abrasilsistema php artisan route:list --path=api
```

Validação funcional:

1. Enviar uma mensagem pelo CRM a um lead de teste. O histórico deve mostrar **"Enviada · Empresa → Cliente"** e o status evoluindo com os ACKs (Enviada → Entregue → Lida).
2. Conferir no banco que o registro novo tem `direction = 'outbound'`:
   `docker compose exec abrasilsistema php artisan tinker --execute='dump(DB::table("lead_activities")->where("type","whatsapp")->latest("id")->first(["id","direction","message_status"]));'`
3. Registros antigos devem continuar aparecendo como antes, sem direção.

Rollback, se necessário: `docker compose run --rm --no-deps abrasilsistema php artisan migrate:rollback --step=1 --force` e voltar a imagem anterior. A remoção da coluna só descarta `direction`.

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
