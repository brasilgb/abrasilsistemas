# Execução de `correio.md`: CRM-WA-05, Conversa WhatsApp no lead

Data: 2026-09-24.

## Resumo

A tela de edição do lead ganhou o card **"Conversa WhatsApp"**, na coluna direita, acima do Histórico. O card traz:

- as bolhas das mensagens;
- o ACK das mensagens enviadas;
- o selo "Nova";
- o campo de envio embutido;
- polling de 10 s.

Tudo usa a infraestrutura existente.

- **Sem migration.** n8n, WAHA, a sessão `vetoros1-1`, o docker-compose, `routes/api.php`, os controllers de API e o `LeadWhatsappService` não foram alterados.
- **O banco de produção não foi acessado.** Nenhum deploy foi feito.
- **Situação: concluído.** Todos os testes relacionados passam, e `types` e `lint` dos arquivos alterados estão verdes. A única falha da suíte completa já existia e não tem relação com esta tarefa (ver "Resultados").

## Arquivos criados

- `app/Http/Controllers/LeadWhatsappConversationController.php` (`index`, `markRead`)
- `app/Http/Requests/Leads/MarkLeadWhatsappReadRequest.php` (`up_to_id`: obrigatório, inteiro, ≥ 1)
- `resources/js/components/lead-whatsapp-conversation.tsx`
- `tests/Feature/LeadWhatsappConversationTest.php`

## Arquivos alterados

- `routes/web.php`: 2 rotas no grupo `auth` + `verified` + `admin`.
- `app/Models/LeadActivity.php`: scope `conversation()` (`type=whatsapp` e `direction` em inbound/outbound).
- `resources/js/pages/leads/edit.tsx`:
  - inclui o card da conversa;
  - remove o botão e o modal de envio do cabeçalho do Histórico;
  - reordena os imports para o arquivo passar no lint.

## Arquivo removido

- `resources/js/components/send-lead-whatsapp-dialog.tsx`: o modal duplicado de envio. O componente ficou sem uso depois que o envio passou para dentro da conversa.

## Rotas adicionadas

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `/leads/{lead}/whatsapp/messages` | `leads.whatsapp.messages` | `LeadWhatsappConversationController@index` (throttle 60/min) |
| POST | `/leads/{lead}/whatsapp/read` | `leads.whatsapp.read` | `LeadWhatsappConversationController@markRead` (throttle 60/min) |

As rotas tipadas do Wayfinder (`@/routes/leads/whatsapp`) são geradas no build. Não são editadas à mão.

## Comportamento implementado

### Backend

- **GET `/messages`:**
  - usa `$lead->activities()->conversation()`, ou seja, só mensagens do lead da rota, com `type=whatsapp` e `direction` válida. O WhatsApp manual sem `direction` fica de fora;
  - pega as últimas 100 por `created_at DESC, id DESC` e devolve em ordem `created_at ASC, id ASC`;
  - expõe somente `id`, `direction`, `description`, `created_at`, `message_status` e `read_at`;
  - não tem efeito colateral: não altera `read_at` nem nenhum outro campo.
- **POST `/read` `{ up_to_id }`:**
  - roda `$lead->unreadWhatsappMessages()->where('id', '<=', up_to_id)->update(['read_at' => now()])`;
  - marca só inbound não lidas do lead da rota;
  - é idempotente: uma segunda chamada devolve `marked: 0` e preserva o `read_at` já gravado;
  - nunca toca em `message_status`;
  - responde `{ marked: n }`.
- **Envio:** reutiliza `leads.whatsapp.store` sem alteração. Não há backend novo de envio.
- **Sem mudanças:** `LeadController::edit` continua marcando como lidas as inbound pendentes ao abrir o lead. A idempotência do `/log` e do inbound e as regras de ACK também seguem iguais.

### Frontend (`lead-whatsapp-conversation.tsx`)

**Exibição**

- Inbound fica à esquerda (bolha com borda) e outbound à direita (bolha verde).
- Cada mensagem mostra o horário.
- `WhatsappMessageStatus` (ACK) aparece só nas outbound.
- O selo **"Nova"** marca inbound que chegaram não lidas durante a visita:
  - o componente guarda esses ids num `Set` local, para o selo não sumir depois que o `read_at` for preenchido;
  - o `Set` começa com as inbound que vieram não lidas na prop `lead.activities`, ou seja, as que a abertura da página acabou de marcar como lidas.

**Rolagem**

- A área da conversa tem rolagem própria (`max-h-96`).
- A rolagem automática para o fim acontece só quando o usuário já estava a até 80 px do fim, na primeira carga ou logo depois de um envio.

**Envio**

- O envio usa o `Form` do Inertia apontando para `leads.whatsapp.store`, com `preserveScroll`, `preserveState` e `only: ['lead']`, para o Histórico continuar sincronizado.
- O botão fica desabilitado durante o envio ou com o campo vazio.
- No sucesso, o campo é limpo e a conversa é atualizada. Não há mensagem otimista.
- Os erros `message` e `whatsapp` aparecem no próprio card.
- Sem `whatsappDestination`, o campo e o botão ficam desabilitados com a explicação "Cadastre um WhatsApp válido para enviar mensagens".

**Polling**

- Usa `fetch`, no padrão de `company-whatsapp-connection.tsx`.
- `setInterval` de 10 s, executado só com `document.visibilityState === 'visible'`.
- `refresh` imediato ao voltar para a aba (`visibilitychange`).
- O interval e o listener são limpos no unmount.
- **Um fetch por vez:** um pedido feito durante um fetch vira um novo ciclo logo em seguida. Assim, o refresh pós-envio nunca se perde.
- A lista é mesclada por `id` e reordenada por `created_at`/`id`, sem duplicação. A janela do servidor também atualiza o `message_status` das outbound já exibidas.
- Se a resposta trouxer inbound com `read_at === null` e a aba estiver visível, o componente envia `POST /read` com `up_to_id` igual ao maior id inbound exibido. O GET nunca marca nada como lido.

### Histórico

- O WhatsApp manual de "Registrar contato", sem `direction`, continua só no Histórico.
- O cabeçalho do Histórico não tem mais botão de envio: o envio fica apenas dentro da conversa.

## Testes criados (`tests/Feature/LeadWhatsappConversationTest.php`: 22 testes, contando os datasets)

| # | Requisito | Teste |
|---|---|---|
| 1 | Autenticação | `the conversation endpoints require authentication` |
| 2 | Autorização admin | `the conversation endpoints require an admin user` (reader → 403 nos dois endpoints) |
| 3 | Só WhatsApp com direction | `the conversation only returns WhatsApp messages with a direction` (exclui o manual sem direction, nota e ligação com direction) |
| 4 | Isolamento entre leads | `the conversation only returns messages of the route lead` |
| 5 | Ordenação | `the conversation is ordered by created_at and id ascending` (desempate no mesmo segundo) |
| 6 | Limite de 100 | `the conversation returns only the latest 100 messages in ascending order` (105 mensagens → as 100 últimas) |
| 7 | Sem `provider_message_id` | `the conversation exposes only the message fields and never the provider message id` |
| 8 | GET sem efeito colateral | `reading the conversation has no side effects` (atributos das atividades e do lead idênticos) |
| 9 | markRead só nas inbound corretas | `markRead only marks unread inbound messages of the lead up to the given id` |
| 10 | Não altera outbound | `markRead does not touch outbound messages` |
| 11 | Não altera outros leads | `markRead does not touch messages of other leads` |
| 12 | Não altera `message_status` | `markRead never changes the message status` |
| 13 | Idempotência | `markRead is idempotent` (`marked: 0` e `read_at` original preservado) |
| 14 | Validação de `up_to_id` | `markRead validates up_to_id` (ausente, texto, decimal, zero, negativo) |
| 15 | ACK posterior no GET | `a later ACK update is reflected in the conversation` (via `PATCH /api/whatsapp/messages/{id}/status`) |
| 16 | `/log` repetido aparece uma vez | `an activity logged twice through /log with the same message id appears only once` |
| 17 | Regressão da leitura ao abrir o lead | `opening the lead still marks its pending inbound messages as read` |
| 18 | Regressão do envio | `sending through the existing route still relies on n8n to register the message` |

Nenhum teste existente foi alterado ou removido.

## Resultados

Ambiente dos testes:

- cópia do working tree na scratchpad;
- `vendor` e `node_modules` de uma execução anterior (mesmos `composer.lock` e `package-lock.json`);
- containers descartáveis com `docker run --rm --network none`:
  - PHP na imagem da aplicação `infra-abrasil-abrasilsistema`, com SQLite em memória do `phpunit.xml` e `APP_KEY` de teste;
  - Node em `node:22-bookworm-slim`.

Nenhum comando rodou no container de produção, e não havia rede para alcançar o MySQL.

**PHP**

- `php artisan test --filter=LeadWhatsapp` (Conversation, Send e Unread): **71 passed (317 assertions)**.
- `php artisan test` (suíte completa): **285 passed, 1 failed**.
  - A falha é `BlogTest > administrator can upload blog images` (`imagejpeg function is not defined`): a imagem não tem GD com JPEG.
  - A falha já existia antes desta tarefa e não tem relação com ela.
- `vendor/bin/pint --test` nos 5 arquivos PHP criados ou alterados: **PASS**.

**Types**

- O `package.json` não tem o script `types`. O equivalente é `npm run types:check` (`tsc --noEmit`), que passou: **OK, 0 erros**.
- Antes, `php artisan wayfinder:generate --with-form` gerou as rotas tipadas.

**Lint**

- `eslint` nos arquivos da tarefa (`lead-whatsapp-conversation.tsx` e `edit.tsx`): **0 problemas**. O `edit.tsx` tinha 4 erros de ordem de imports na HEAD, que foram corrigidos.
- `lead-whatsapp-conversation.tsx` também passou no Prettier.
- O `eslint .` global continua com **67 erros e 1 warning**, todos preexistentes, em cerca de 18 arquivos fora do escopo:
  - por exemplo, `admin/*`, `blog/*`, `welcome.tsx`, `settings/leads.tsx`, `leads/create.tsx`, `cookie-consent.tsx`;
  - são erros de `import/order`, `no-unused-expressions`, `set-state-in-effect` e `no-unused-vars`;
  - antes da tarefa eram 74, porque o `edit.tsx` entrava na conta.
- `npm run lint` (`eslint . --fix`) foi executado **só na cópia**. As correções automáticas nos arquivos fora do escopo **não** foram aplicadas ao repositório.

## Limitações que permaneceram

1. **Envio pelo card marca como lidas as inbound pendentes do lead.** O `Form` recarrega `lead` (`only: ['lead']`) para manter o Histórico em sincronia, e esse reload passa pelo `LeadController::edit`. Uma inbound que chegue entre dois pollings e logo antes de um envio fica marcada como lida sem receber o selo "Nova". O comportamento é o mesmo que o modal já tinha. Para mudar isso, seria preciso alterar o `edit`, o que está fora do escopo.
2. **A conversa mostra só o que o CRM registra.** Mensagens enviadas direto pelo celular (fromMe) e mídia ou áudio não aparecem, porque o inbound aceita só texto. Mudar isso exigiria alterar n8n e WAHA.
3. **Janela de 100 mensagens.** Não há paginação para mensagens mais antigas. Elas continuam visíveis no Histórico.
4. **Sem tempo real.** A atualização é por polling de 10 s. Não há infraestrutura de WebSocket.
5. **Não há testes de componente no front.** O projeto não tem essa infraestrutura. A validação do front ficou em `types`, `lint` e na validação manual abaixo.

## Validação manual sugerida (homologação/local)

1. Rebuild da imagem, que roda `wayfinder:generate` e `npm run build`. Não há migration.
2. Abrir um lead com WhatsApp válido. O card "Conversa WhatsApp" aparece acima do Histórico, e o Histórico não tem mais o botão "Enviar WhatsApp".
3. Enviar uma mensagem pelo card:
   - o botão mostra "Enviando...";
   - o campo é limpo;
   - a mensagem aparece uma única vez, à direita;
   - o ACK evolui sem recarregar a página, em até cerca de 10 s por ciclo.
4. Simular um inbound: `POST /api/whatsapp/inbound` com o token do prospect. A mensagem aparece à esquerda com "Nova" em até cerca de 10 s, e o badge da listagem de leads zera.
5. Repetir o inbound com a aba em segundo plano. A mensagem continua não lida (badge na listagem) até a aba voltar a ficar visível.
6. Abrir um lead sem WhatsApp válido: o campo e o botão ficam desabilitados, com a explicação.
7. Registrar um contato manual do tipo WhatsApp: ele aparece só no Histórico, não na conversa.
