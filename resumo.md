# Execução de `correio.md`: CRM-WA-06, sincronização de mensagens enviadas fora do CRM

Data: 2026-09-24.

## Status

**Parte do CRM implementada e validada. A etapa ainda não está concluída.**

Continuam **pendentes**:

- deploy;
- alteração do workflow no n8n;
- teste de ponta a ponta.

O n8n só pode passar a chamar `/api/whatsapp/outbound-external` depois que o CRM estiver publicado com a rota nova. As duas ações mexem em produção e aguardam confirmação (ver "Próximos passos").

- **Sem migration. Sem UNIQUE.**
- Frontend, `WhatsappInboundController`, `StoreWhatsappInboundRequest`, `WhatsappMessageStatusController`, `LeadWhatsappService`, WAHA, n8n e docker-compose **não foram alterados**.
- O banco de produção não foi acessado.
- Nenhum commit foi feito.

## Arquivos criados

- `app/Http/Controllers/Api/WhatsappExternalOutboundController.php`
- `app/Http/Requests/Api/StoreWhatsappExternalOutboundRequest.php`
- `tests/Feature/WhatsappExternalOutboundApiTest.php`

## Arquivos alterados

- `routes/api.php`: +1 rota.
- `app/Models/LeadActivity.php`:
  - `WHATSAPP_MESSAGE_LOCK_PREFIX`;
  - `whatsappMessageLock()`, com o lock compartilhado;
  - `findWhatsappMessage()`, com a busca global por `type=whatsapp + provider_message_id`.
- `app/Http/Controllers/Api/WhatsappActivityController.php` (`/log`): hardening descrito abaixo.

## Endpoint criado

`POST /api/whatsapp/outbound-external`

- Nome: `api.whatsapp.outbound-external`.
- Middleware: `throttle:120,1` e `prospect.token`.

Payload:

```json
{ "session": "vetoros1-1", "provider_message_id": "ID_CURTO_WAHA", "to": "555195179173@s.whatsapp.net",
  "message": "Texto enviado", "status": "SERVER", "timestamp": 1790281001 }
```

| Situação | Resposta |
|---|---|
| Criou a atividade | `201 {success:true, activity_id, lead_id}` |
| Id já registrado (por `/log`, `/inbound` ou por ele mesmo) | `200 {success:true, reason:"already_logged", idempotent:true, activity_id, lead_id}` |
| Sessão diferente da configurada | `200 {success:false, reason:"session_mismatch"}` |
| Nenhuma sessão configurada no painel | `200 {success:false, reason:"session_not_configured"}` |
| Destinatário = número da empresa (com ou sem nono dígito) | `200 {success:false, reason:"self_chat"}` |
| Nenhum lead com o número | `200 {success:false, reason:"lead_not_found"}`. O log registra só `session` e `provider_message_id`, sem número nem token |
| Lock ocupado por mais de 5 s | `409 {success:false, reason:"processing"}`, retentável |
| `to` inválido (`@lid`, `@g.us`, `@broadcast`, `status@broadcast`, `@newsletter`, letras, menos de 10 ou mais de 15 dígitos) ou payload inválido | `422` |

Atividade criada:

- `type=whatsapp`;
- `direction=outbound`;
- `user_id=null`;
- `status=null`;
- `read_at=null`;
- `description=message`;
- `provider_message_id` = id curto;
- `contacted_at=now()`;
- `message_status` = `status` do payload (normalizado para maiúsculas) quando está em `LeadActivity::MESSAGE_STATUSES`; caso contrário, `SERVER`.

O `last_contacted_at` do lead é atualizado como no `/log`. O `timestamp` é validado, mas não é usado: `contacted_at` e `created_at` seguem `now()`, igual aos demais endpoints.

## Estratégia de resolução do destinatário

1. O **n8n** envia em `to` o `_data.key.remoteJidAlt` quando o `remoteJid` é `@lid`. Nos outros casos, envia o `remoteJid`. O CRM recusa `@lid`, então um LID não resolvido nunca vira associação errada.
2. O FormRequest aceita telefone puro, formatado, `@c.us` ou `@s.whatsapp.net`, com a mesma regex do `from` do inbound. Normaliza com `CompanyWhatsappConnection::normalizeJid()` e exige de 10 a 15 dígitos.
3. `Lead::findByWhatsappNumber()` é reutilizado sem mudança: 55 opcional, nono dígito, máscara, soft delete e desempate por `last_contacted_at`/`id`.
4. Nenhum lead é criado.

## Estratégia de idempotência

- **Chave lógica:** `type=whatsapp + provider_message_id` (id curto), com **busca global**, em qualquer lead. Texto, telefone e horário não são usados.
- **Lock:** `LeadActivity::whatsappMessageLock($id)` = `Cache::lock('whatsapp-inbound:'.sha1($id), 10)`, com `block(5)`. É **exatamente a mesma chave** que o `WhatsappInboundController` já usava. Assim, `/inbound`, `/log` e `/outbound-external` se serializam pela mesma mensagem, sem mexer no inbound. O teste `the shared message lock is the same one used by the inbound API` garante que as chaves continuem iguais.
- A checagem de duplicidade e a inserção rodam **dentro** do lock nos dois endpoints.

## Hardening realizado no `/log`

`WhatsappActivityController@store`:

- Quando há `message_id`, a checagem e a inserção passam a rodar dentro do lock compartilhado. Sem `message_id`, o comportamento é o anterior, sem lock.
- A busca de duplicidade deixou de ser `$lead->activities()` e passou a ser **global** (`type=whatsapp + provider_message_id`). Se a mensagem já estiver registrada em qualquer lead, a resposta é idempotente (`200`, `idempotent:true`, `activity_id` existente).
- Com o lock ocupado por mais de 5 s, a resposta é `409 processing`, igual ao inbound. Na prática o lock dura milissegundos.
- O contrato do n8n não mudou: mesmo payload, mesmas mensagens, `201` na criação e `200` idempotente.

Cenários do correio:

| Caso | Resultado | Teste |
|---|---|---|
| A: `/log` grava e o `fromMe` chega depois | uma atividade (a do `/log`, com texto e status preservados) | `a CRM send logged first is not duplicated by the fromMe event` |
| B: o `fromMe` grava e o `/log` chega depois (mesmo lead **e** outro lead com o mesmo número) | uma atividade | `a fromMe event recorded first is not duplicated by the later CRM log` |
| C: simultâneos | uma atividade (o segundo espera o lock e cai na checagem global) | `the CRM log and the external outbound API share the message lock` |

## Testes criados

Arquivo: `tests/Feature/WhatsappExternalOutboundApiTest.php`, com **49 casos e 194 asserções**. Cobertura dos 29 itens do correio:

| Itens | Teste |
|---|---|
| 1, 2 | `requires the integration token` (sem token e token errado) |
| 3, 4, 5, 6 | `records a message sent outside the CRM as outbound` |
| 7 | `keeps a valid ACK status from the payload` + `falls back to SERVER without a valid status` |
| 8, 9, 10, 11 | `finds the lead by the recipient` (`@s.whatsapp.net`, `@c.us`, telefone puro e formatado) |
| 12 | `matches Brazilian numbers with or without the ninth digit and country code` |
| 13 | `does not create a lead or an activity for an unknown recipient` |
| 14 | `ignores a session other than the configured one` |
| 15 | `ignores messages while no company session is configured` (nada salvo / salvo sem sessão) |
| 16 | `ignores a message sent to the company own number` |
| 17, 18, 19, 20 | `rejects recipients that are not a phone` (grupo, `status@broadcast`, lista de broadcast, newsletter, LID, inválidos) |
| 21 | `does not duplicate a repeated event` |
| 22 | `a CRM send logged first is not duplicated by the fromMe event` |
| 23 | `a fromMe event recorded first is not duplicated by the later CRM log` |
| 24 | `the CRM log and the external outbound API share the message lock` + `the shared message lock is the same one used by the inbound API` |
| 25 | `a later ACK updates the activity recorded by the external outbound API` (SERVER → DEVICE → READ → PLAYED, com anti-regressão) |
| 26 | `the inbound API keeps working alongside the external outbound API` |
| 27, 28 | `the conversation returns the external message as outbound and markRead leaves it untouched` |
| 29 | `updates last_contacted_at` |
| extra | `rejects an invalid payload` |

Nenhum teste existente foi alterado ou removido.

## Resultados dos testes

Os testes rodaram numa cópia do working tree na scratchpad, em containers descartáveis `docker run --rm --network none`:

- PHP na imagem `infra-abrasil-abrasilsistema`, com SQLite em memória;
- Node em `node:22-bookworm-slim`;
- `vendor` e `node_modules` montados de uma execução anterior, com `composer.lock` e `package-lock.json` idênticos.

Nada rodou no container de produção.

| Validação | Resultado |
|---|---|
| `--filter=WhatsappExternalOutbound` | **49 passed (194 assertions)** |
| `--filter="WhatsappExternalOutbound\|WhatsappInbound\|WhatsappActivity\|WhatsappMessageStatus\|LeadWhatsapp"` | **208 passed (833 assertions)** |
| Suíte completa | **334 passed, 1 failed**. A falha é `BlogTest > administrator can upload blog images` (`imagejpeg` indisponível na imagem, sem GD/JPEG). Ela já existia antes e não tem relação com esta etapa |
| `pint --test` nos 6 arquivos PHP criados ou alterados | **PASS** |
| `wayfinder:generate --with-form` + `tsc --noEmit` | **OK, 0 erros**. As ações tipadas do controller novo são geradas, e a pasta é ignorada pelo git |
| ESLint | Nenhum arquivo TS/TSX do repositório foi alterado, então não há arquivo afetado a verificar |

## Alterações realizadas no n8n

**Nenhuma ainda.** A alteração depende do deploy do CRM: sem ele, o nó HTTP novo receberia 404.

Especificação do que falta no workflow "WAHA - Mensagens Recebidas → CRM":

1. **Backup:** exportar o workflow atual antes de mexer.
2. **Switch** em `payload.fromMe`, no `message.any`:
   - `false` → fluxo atual para `/api/whatsapp/inbound` (sem mudança);
   - `true` → ramo novo.
3. **Filtros do ramo `fromMe`:**
   - `event === 'message.any'`;
   - chat que não termina em `@g.us`, `@broadcast` nem `@newsletter`;
   - texto não vazio.
4. **Wait opcional** de alguns segundos. É só uma defesa extra: a garantia já está no lock do CRM.
5. **HTTP `POST /api/whatsapp/outbound-external`**, com a mesma credencial Bearer do inbound e o corpo:
   - `session` = `{{$json.body.session}}`;
   - `provider_message_id` = `payload._data.key.id`;
   - `to` = `remoteJid.endsWith('@lid') && remoteJidAlt ? remoteJidAlt : remoteJid`;
   - `message` = `payload.body`, com fallback para `_data.message.conversation` e depois `_data.message.extendedTextMessage.text`;
   - `status` = `payload.ackName`;
   - `timestamp` = `payload.timestamp`.
6. Tratar `success:false` e `already_logged` como fim normal. `409` pode ser repetido.

## Confirmação sobre migrations

**Nenhuma migration foi criada.** `git status database/` não mostra alterações. Também não foi adicionado UNIQUE.

## Limitações restantes

1. **Garantia de aplicação, não de banco:** sem UNIQUE, a proteção depende do lock no cache compartilhado (Redis em produção). Um cache por processo ou por container quebraria a garantia. Em produção, `CACHE_STORE` precisa continuar compartilhado entre os containers web.
2. **Ordem na conversa:** `created_at = now()`. Um `fromMe` reemitido tarde (reconexão do WAHA) de uma mensagem nunca registrada entra como se fosse recente. O `timestamp` é recebido, mas não é usado.
3. **CRM × celular × WhatsApp Web não se distinguem:** todas as origens ficam com `user_id=null` e `direction=outbound`. `source` ficou fora do escopo.
4. **Só texto:** mídia e áudio enviados pelo celular não entram.
5. **Volume:** toda mensagem enviada pela sessão, inclusive para quem não é lead, gera uma chamada ao CRM, com o `LIKE` do `findByWhatsappNumber`. O throttle é de 120/min, compartilhado por IP com as outras rotas da API.
6. **`/log` com 409:** se o lock ficar preso por mais de 5 s (só em caso de falha), o `/log` responde 409. Não verifiquei se o workflow de envio repete a chamada nesse caso.

## Próximos passos (aguardando confirmação)

1. Commit na branch `feat/whatsapp-message-direction`.
2. Rebuild e deploy do CRM, sem migration. A rota fica ociosa até o n8n chamá-la.
3. Validação pós-deploy:
   - `POST /api/whatsapp/outbound-external` sem token deve responder 401;
   - com token e um número inexistente, deve responder `lead_not_found`.
4. Backup e alteração do workflow no n8n, conforme a seção do n8n acima.

## Roteiro de teste ponta a ponta

1. **Celular → lead:** pelo celular da sessão `vetoros1-1`, enviar um texto a um número cadastrado como lead.
   - Em até cerca de 10 s (polling), a mensagem aparece **à direita** no card "Conversa WhatsApp", com ACK SERVER.
   - O ACK evolui para DEVICE e depois READ.
2. **LID:** repetir o passo 1 com um contato endereçado por LID (como no payload capturado). A associação deve vir pelo `remoteJidAlt`.
3. **CRM → lead:** enviar pelo card. Deve aparecer **uma** única bolha. No banco, `provider_message_id` deve ter uma única linha.
4. **Inbound:** o lead responde. A mensagem aparece à esquerda com "Nova", e o badge da listagem funciona.
5. **Sem lead:** enviar pelo celular a um número que não é lead. Nada é gravado, e o n8n recebe `lead_not_found`.
6. **Grupo e status:** enviar a um grupo e postar um status. Nada é gravado (filtrado no n8n; o CRM responde 422 se chegar).
7. **Duplicidade:** reexecutar no n8n a mesma execução `fromMe`. A resposta é `already_logged`, sem atividade nova.
8. **Para si mesmo:** enviar uma mensagem ao próprio número da empresa. A resposta é `self_chat`.

## Critérios de conclusão do CRM-WA-06

| Critério | Situação |
|---|---|
| 1. Mensagem do celular aparece como outbound | Coberto por teste; falta o E2E, que depende do deploy e do n8n |
| 2. Mensagem do CRM aparece só uma vez | Coberto por teste (casos A, B e C); falta o E2E |
| 3. Inbound continua funcionando | Todos os testes do inbound passam; falta o E2E |
| 4. ACK continua funcionando | Coberto por teste; falta o E2E |
| 5. Eventos duplicados não geram atividades duplicadas | Coberto por teste |
