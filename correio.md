# CRM-WA-06 — Sincronização de mensagens enviadas fora do CRM

Implemente o CRM-WA-06 conforme a auditoria anterior e o payload real capturado do WAHA.

## Payload real confirmado

Foi capturada uma mensagem enviada diretamente pelo celular:

- `event = message.any`
- `session = vetoros1-1`
- `payload.fromMe = true`
- `payload.source = app`
- `payload.body = "Teste crm-wa-06 enviado pelo celular"`
- `payload.hasMedia = false`
- `payload.ack = 1`
- `payload.ackName = SERVER`
- `payload.from = <LID>@lid`
- `payload._data.key.remoteJid = <LID>@lid`
- `payload._data.key.remoteJidAlt = 555195179173@s.whatsapp.net`

Portanto, quando o endereçamento vier por LID, o telefone do contato deve ser obtido de `remoteJidAlt`, e NÃO de `payload.from`.

O `provider_message_id` deve usar o ID curto do WAHA (`payload._data.key.id`). Validar isso no payload/workflow durante a implementação. Não usar o `payload.id` composto como identificador persistido.

## Objetivo

Registrar no CRM mensagens de texto enviadas diretamente pelo celular/WhatsApp conectado à sessão WAHA, fazendo-as aparecer como `outbound` no card "Conversa WhatsApp".

Preservar completamente os fluxos existentes de inbound, envio pelo CRM e ACK.

Nenhum lead deve ser criado automaticamente.

## 1. Endpoint

Criar:

`POST /api/whatsapp/outbound-external`

Protegido pelo mesmo middleware `prospect.token` utilizado pelos demais endpoints da integração.

Sugestão de nome:

`api.whatsapp.outbound-external`

Throttle: aproximadamente `120/min`.

Criar:

- `app/Http/Controllers/Api/WhatsappExternalOutboundController.php`
- `app/Http/Requests/Api/StoreWhatsappExternalOutboundRequest.php`

Payload esperado do n8n:

```json
{
  "session": "vetoros1-1",
  "provider_message_id": "ID_CURTO_WAHA",
  "to": "555195179173@s.whatsapp.net",
  "message": "Texto enviado",
  "status": "SERVER",
  "timestamp": 1790281001
}
```

## 2. Validação

Validar:

- `session`;
- `provider_message_id`;
- `to`;
- `message`;
- `status`, quando informado;
- `timestamp`, quando informado.

O destinatário pode chegar como:

- telefone;
- `@c.us`;
- `@s.whatsapp.net`.

Recusar:

- `@lid` sem telefone alternativo resolvido pelo n8n;
- `@g.us`;
- `@broadcast`;
- `@newsletter`;
- JID inválido.

O n8n deve ser responsável por enviar `remoteJidAlt` no campo `to` quando o evento original estiver endereçado por LID.

## 3. Sessão

O endpoint deve aceitar somente a sessão configurada para a empresa.

Sessão diferente:

`success=false`
`reason=session_mismatch`

Sem sessão configurada:

`success=false`
`reason=session_not_configured`

Não criar atividade nesses casos.

## 4. Identificação do lead

Normalizar `to` reutilizando a infraestrutura existente.

Reutilizar:

`Lead::findByWhatsappNumber()`

Isso deve preservar:

- 55 opcional;
- equivalência do nono dígito;
- números mascarados;
- comportamento atual de seleção do lead.

Não criar lead automaticamente.

Sem lead:

`200`
`success=false`
`reason=lead_not_found`

Mensagem enviada para o próprio número da empresa deve ser ignorada:

`reason=self_chat`

## 5. Criação da atividade

Criar:

- `type = whatsapp`
- `direction = outbound`
- `description = message`
- `provider_message_id = ID curto WAHA`
- `user_id = null`
- `read_at = null`

`message_status`:

- usar `status` quando for um valor válido aceito por `LeadActivity`;
- caso contrário usar `SERVER`.

Atualizar `last_contacted_at` do lead conforme o comportamento existente.

Não é necessária migration.

## 6. Idempotência — obrigatório

A chave lógica deve continuar sendo:

`type=whatsapp + provider_message_id`

A busca deve ser GLOBAL, e não limitada ao lead.

Usar lock baseado no mesmo identificador:

`sha1(provider_message_id)`

O endpoint `/outbound-external` deve adquirir o lock antes da verificação/inserção.

Se a atividade já existir:

- não criar outra;
- retornar sucesso idempotente, por exemplo `already_logged`.

Não utilizar texto, telefone ou horário como critério de deduplicação.

## 7. Hardening obrigatório do `/log`

ALTERAR também:

`WhatsappActivityController`

Hoje o `/log` verifica duplicidade apenas dentro do lead e não participa do mesmo lock.

Corrigir isso.

O `/log` e `/outbound-external` devem utilizar:

- a mesma chave lógica global `type=whatsapp + provider_message_id`;
- o mesmo namespace/chave de lock.

Objetivo:

### Caso A

CRM envia  
→ `/log` grava  
→ `message.any fromMe` chega

Resultado: **uma atividade**.

### Caso B

`message.any fromMe` chega primeiro  
→ `/outbound-external` grava  
→ `/log` chega depois

Resultado: **uma atividade**.

### Caso C

`/log` e `/outbound-external` chegam simultaneamente

Resultado: **uma atividade**.

Não adicionar UNIQUE nesta etapa.

Não criar migration.

## 8. Preservar inbound

Não alterar o comportamento funcional de:

- `WhatsappInboundController`;
- `StoreWhatsappInboundRequest`;
- localização de inbound;
- leitura;
- idempotência inbound.

Se for necessário compartilhar somente a função/chave do lock para garantir consistência, faça a menor alteração possível e preserve integralmente os testes existentes.

## 9. ACK

Não alterar `WhatsappMessageStatusController`, salvo se um teste demonstrar uma necessidade real.

A atividade outbound externa deve utilizar o mesmo `provider_message_id` curto já utilizado pelo ACK.

Assim:

SERVER → DEVICE → READ → PLAYED

deve funcionar normalmente.

As regras atuais de progressão/anti-regressão devem permanecer.

## 10. Frontend

NÃO alterar o frontend.

O CRM-WA-05 já deve:

- incluir `direction=outbound`;
- alinhar à direita;
- mostrar ACK;
- atualizar pelo polling;
- ordenar;
- fazer merge por `id`.

Comprove isso por teste/backend e pela validação manual.

## 11. n8n

Depois que o endpoint estiver implementado e testado, alterar o workflow de recebimento.

Antes de alterar, fazer backup/export do workflow atual.

No fluxo de `message.any`:

### inbound

`payload.fromMe = false`

→ manter fluxo atual para `/api/whatsapp/inbound`.

### outbound externo

`payload.fromMe = true`

→ encaminhar para `/api/whatsapp/outbound-external`.

Extrair:

- `session = body.session`
- `provider_message_id = payload._data.key.id`
- `to`:
  - usar `_data.key.remoteJidAlt` quando `remoteJid` estiver em LID e `remoteJidAlt` estiver disponível;
  - caso contrário usar `_data.key.remoteJid`
- `message = payload.body`, com fallback para os campos textuais já conhecidos se necessário;
- `status = payload.ackName`
- `timestamp = payload.timestamp`

Ignorar:

- grupos;
- broadcast/status;
- newsletter;
- mensagens sem texto nesta etapa.

### Wait

Pode manter um Wait curto antes do `/outbound-external` como defesa adicional, mas a correção da concorrência NÃO deve depender dele.

A garantia principal deve estar no CRM através do lock compartilhado entre `/log` e `/outbound-external`.

## 12. `source`

O payload real confirmou:

`source = app`

Não criar migration nem coluna `source` agora.

Não usar `source` como mecanismo de idempotência.

O campo poderá futuramente permitir distinguir envio pelo CRM/API de envio externo, mas isso fica fora do CRM-WA-06.

## 13. Testes

Criar:

`tests/Feature/WhatsappExternalOutboundApiTest.php`

Cobrir no mínimo:

1. token obrigatório;
2. token inválido;
3. outbound externo válido cria atividade;
4. `direction=outbound`;
5. `user_id=null`;
6. `message_status=SERVER`;
7. status válido do payload é preservado;
8. lead localizado pelo destinatário;
9. `@s.whatsapp.net`;
10. `@c.us`;
11. telefone puro;
12. equivalência do nono dígito;
13. lead inexistente não é criado;
14. sessão diferente é ignorada;
15. sessão não configurada é ignorada;
16. self-chat é ignorado;
17. grupo é rejeitado;
18. broadcast/status é rejeitado;
19. newsletter é rejeitado;
20. `@lid` sem telefone alternativo resolvido é rejeitado;
21. evento repetido não duplica;
22. `/log` primeiro + `fromMe` depois não duplica;
23. `fromMe` primeiro + `/log` depois não duplica;
24. concorrência entre `/log` e `/outbound-external` não duplica;
25. ACK posterior atualiza a mesma atividade;
26. regressão do inbound;
27. conversa retorna a nova mensagem como outbound;
28. `markRead` não altera essa outbound;
29. `last_contacted_at` é atualizado.

Preservar todos os testes existentes.

## 14. Validação

Executar em ambiente seguro/container de teste:

- testes específicos do novo endpoint;
- testes de `WhatsappInbound`;
- testes de `WhatsappActivity`;
- testes de `WhatsappMessageStatus`;
- testes de `LeadWhatsapp`;
- suíte PHP completa;
- Pint;
- TypeScript check;
- lint dos arquivos afetados.

Não alterar testes existentes para mascarar regressões.

## 15. Restrições

- NÃO criar migration.
- NÃO adicionar UNIQUE nesta etapa.
- NÃO alterar frontend.
- NÃO alterar semântica de `read_at`.
- NÃO alterar semântica de `message_status`.
- NÃO criar lead automaticamente.
- NÃO acessar banco de produção.
- NÃO fazer deploy sem a validação da implementação.
- NÃO registrar tokens ou credenciais em logs.

## Entrega

Gerar `resumo.md` informando:

- arquivos criados;
- arquivos alterados;
- endpoint criado;
- estratégia de resolução do destinatário;
- estratégia de idempotência;
- hardening realizado no `/log`;
- testes criados;
- resultados dos testes;
- alterações realizadas no n8n;
- confirmação de que nenhuma migration foi criada;
- limitações restantes;
- roteiro de teste ponta a ponta.

O CRM-WA-06 só deve ser considerado concluído quando:

1. mensagem enviada pelo celular aparecer como outbound na conversa;
2. mensagem enviada pelo CRM continuar aparecendo apenas uma vez;
3. inbound continuar funcionando;
4. ACK continuar funcionando;
5. eventos duplicados não gerarem atividades duplicadas.
