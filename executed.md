# Execução de `correio.md`: CRM-WA-07, Fase 1 (conversa WhatsApp operacional)

Data: 2026-09-25.

## Status

**Fase 1 implementada e validada em ambiente descartável.**

- **Nenhuma migration.** Nenhuma coluna ou tabela nova.
- n8n, WAHA, Docker/infra e os endpoints do CRM-WA-06 (`/log`, `/inbound`, `/outbound-external`, `/messages/{id}/status`) **não foram alterados**.
- O banco de produção não foi acessado. Nada rodou no container de produção.
- Sem deploy e sem commit.

## Auditoria encontrada

O CRM-WA-05 já tinha entregado boa parte da base:

| Item | Estado antes |
|---|---|
| Componente | `resources/js/components/lead-whatsapp-conversation.tsx`, usado em `leads/edit.tsx` |
| Leitura | `GET leads/{lead}/whatsapp/messages` (`LeadWhatsappConversationController@index`), JSON com as **100 últimas**, sem paginação |
| Polling | A cada **10 s**, sempre rebaixando as 100 últimas (não incremental). Pausava com a aba oculta e parava no unmount. Merge por `id` |
| Não lidas | `read_at` só em inbound (scope `unreadInbound`). Abrir o lead marca tudo como lido (`LeadController@edit`). O componente chama `POST leads/{lead}/whatsapp/read` (`up_to_id`) com a aba visível. O selo "Nova" fica na sessão. A listagem usa `withCount('unreadWhatsappMessages')` + `WhatsappUnreadBadge` |
| ACK | `message_status` (PENDING, SERVER, DEVICE, READ, PLAYED, ERROR). O update do ACK usa `save()`, então toca o `updated_at` |
| Autorização | Rotas no grupo `auth` + `verified` + `admin`. Não há dono por lead: todo admin acessa todos os leads |
| Tempo real | Não há WebSocket/Reverb no projeto |

**Lacunas em relação ao pedido:**

- polling não incremental e de 10 s;
- sem histórico paginado;
- sem separador por data;
- sem indicador de "novas mensagens" ao ler o histórico;
- rótulos de status diferentes do pedido;
- throttle de 60/min apertado para 5 s com várias abas.

## Arquivos

**Criados**

- `app/Http/Requests/Leads/LeadWhatsappMessagesRequest.php`: valida `before_id` e `since`.
- `tests/Feature/LeadWhatsappConversationPollingTest.php`

**Alterados**

- `app/Http/Controllers/LeadWhatsappConversationController.php`: `index` ganhou paginação por cursor e polling incremental. `markRead` não mudou.
- `resources/js/components/lead-whatsapp-conversation.tsx`: polling incremental de 5 s, histórico, separador por data, indicador de novas mensagens, direção e estado vazio.
- `resources/js/components/whatsapp-message-status.tsx`: rótulos `PENDING` → **Pendente** e `ERROR` → **Falhou**.
- `routes/web.php`: throttle de `leads.whatsapp.messages` de 60 para **120/min**.

Os testes existentes não foram alterados.

## Endpoints

Nenhum endpoint novo. O `GET leads/{lead}/whatsapp/messages` foi **reutilizado** e estendido, de forma compatível:

| Chamada | Retorno |
|---|---|
| sem parâmetros | 100 mais recentes, em ordem cronológica + `has_more` + `cursor` |
| `?before_id=ID` | 100 anteriores a essa mensagem + `has_more` + `cursor` |
| `?since=CURSOR` | mensagens da conversa com `updated_at >= cursor` + `reset` + `cursor` |

- `before_id` precisa ser uma mensagem da conversa **do lead da rota** (exists com `lead_id`, `type=whatsapp` e `direction`). Se não for, retorna 422.
- `since` é um inteiro ≥ 0.
- Os dois parâmetros são mutuamente exclusivos (`prohibits`).
- Campos expostos: os mesmos de antes (`id`, `direction`, `description`, `created_at`, `message_status`, `read_at`). O `provider_message_id` continua oculto.
- `POST leads/{lead}/whatsapp/read` foi reutilizado sem mudança.

## Polling

- A cada **5 s**, só com a aba visível. Também roda ao voltar para a aba. O intervalo e o listener são removidos no unmount.
- Um request por vez. Um pedido durante outro vira um novo ciclo em seguida.
- A 1ª carga traz a página recente e o `cursor` (`now() - 2 s`, em Unix). Os ciclos seguintes pedem só `?since=cursor`, ou seja, apenas o que foi criado ou alterado. O polling não recarrega o lead nem a página Inertia.
- O `updated_at` muda:
  - na criação (`/log`, `/inbound`, `/outbound-external`);
  - no ACK;
  - no `markRead`.
- Por isso o incremental percebe mensagens novas **e** mudanças de status em mensagens já exibidas.
- **Folga de 2 s no cursor:** `updated_at` tem precisão de segundo. Pode haver repetição, mas a tela faz merge por `id`, então não duplica.
- **Mudança em massa:** mais de 200 alterações num ciclo retornam `reset: true`, e a tela recarrega a página recente.

## Não lidas

A semântica atual foi preservada:

- abrir o lead marca as pendentes;
- a conversa marca, com `up_to_id`, só as inbound exibidas com a aba visível;
- outbound nunca entra (a leitura dela é o ACK);
- `read_at` já preenchido não é regravado;
- a consulta (inclusive o polling) não tem efeito colateral.

O selo "Nova" continua na bolha durante a visita. O contador da listagem cai assim que o `markRead` roda.

## Paginação

- A abertura traz as 100 mais recentes. "Carregar mensagens anteriores" aparece no topo enquanto `has_more` for verdadeiro.
- **Keyset** em (`created_at`, `id`), coerente com a ordenação da tela. O desempate por `id` cobre várias mensagens no mesmo segundo. O teste cruza a fronteira de página com empate e verifica que não há duplicata nem buraco.
- Ao inserir mensagens antigas, a posição do scroll é preservada.
- Tudo passa pelo mesmo merge por `id`, então páginas e polling não duplicam registros.

## UX

- Inbound fica à esquerda e outbound à direita (verde).
- Cada bolha mostra a direção ("Recebida · Cliente → Empresa" / "Enviada · Empresa → Cliente"), o horário (HH:mm) e, na outbound, o status.
- Separador por dia: "Hoje", "Ontem" ou a data por extenso.
- A conversa abre no fim. Se o usuário estiver perto do fim (80 px), a tela acompanha as mensagens novas. Se estiver lendo o histórico, o scroll não é forçado e aparece o botão flutuante "N novas mensagens ↓". O botão some ao rolar até o fim.
- Após enviar uma mensagem, a tela sempre vai para o fim. Isso corrigiu um caso em que o `onSuccess` era sobrescrito pelo cálculo de posição.
- Carregamento: spinner só na 1ª carga e no botão de histórico. O polling é silencioso.
- Estado vazio com ícone e convite para enviar a primeira mensagem. Erro discreto em texto.

## Mapeamento ACK/status (outbound)

| `message_status` | Rótulo |
|---|---|
| PENDING | Pendente |
| SERVER | Enviada |
| DEVICE | Entregue |
| READ | Lida |
| PLAYED | Reproduzida (mantido; só ocorre em mídia/áudio) |
| ERROR | Falhou |

Os rótulos novos também valem no histórico de atividades de `leads/edit.tsx`, que usa o mesmo componente.

## Segurança

- Leitura só por rota web autenticada (`auth` + `verified` + `admin` + throttle). Nenhum endpoint público.
- Os testes cobrem: sem login → 401, usuário `reader` → 403, nos três modos.
- A consulta é sempre escopada em `$lead->activities()`. O `before_id` é validado contra o lead da rota.
- Nenhum token do WAHA/n8n é exposto.
- Limitação: não existe dono por lead no modelo atual, então "lead que o usuário não pode acessar" equivale a "usuário não admin".

## Testes criados

`tests/Feature/LeadWhatsappConversationPollingTest.php`: **20 testes**.

| # | Obrigatório | Cobertura |
|---|---|---|
| 1 | autorizado consulta | página + `has_more` + `cursor` |
| 2 | não autorizado | 401 sem login e 403 para `reader`, nos 3 modos |
| 3–5 | inbound, outbound, status/ACK | direção e `message_status` (PENDING, DEVICE, ERROR) |
| 6 | leitura marca só inbound pertinente | outbound, já lidas e posteriores ao `up_to_id` intactas; contador da listagem |
| 7 | já lidas não são alteradas | `read_at` antigo preservado; polling não marca nada |
| 8 | paginação sem duplicar | 3 páginas com empate de segundo na fronteira; validação de `before_id` (outro lead, nota, inexistente, texto, zero) |
| 9 | polling incremental retorna novas | só o que mudou após o cursor, sem outro lead e sem nota |
| 10 | ACK percebido | PATCH do status aparece no `since` e só ela |
| 11 | sem mensagens | página e polling vazios |
| 12 | fluxos anteriores | `/log` (repetido, sem duplicar), `/inbound` e `/outbound-external` aparecem no polling com direção e status corretos |
| extra | | `since` inválido e combinação com `before_id`; `reset` em mudança em massa; `read_at` refletido no polling |

## Resultados

O ambiente foi uma cópia do working tree na scratchpad, em containers `docker run --rm --network none`:

- imagem `infra-abrasil-abrasilsistema`, com SQLite em memória e `APP_KEY` descartável;
- `node:22-bookworm-slim`;
- `vendor` e `node_modules` de uma execução anterior, com locks idênticos.

| Validação | Resultado |
|---|---|
| `--filter=LeadWhatsappConversation` (novos + existentes) | **42 passed (188 assertions)** |
| `--filter="WhatsappExternalOutbound\|WhatsappInbound\|WhatsappActivity\|WhatsappMessageStatus\|LeadWhatsapp"` | **228 passed (932 assertions)** |
| Suíte completa | **354 passed, 1 failed**. A falha é `BlogTest > administrator can upload blog images` (`imagejpeg` indisponível na imagem). É pré-existente e já aparecia no CRM-WA-06 |
| `pint --test` (controller, request, teste, rotas) | **PASS** |
| `wayfinder:generate --with-form` + `tsc --noEmit` | **0 erros** |
| ESLint + Prettier (`lead-whatsapp-conversation.tsx`, `whatsapp-message-status.tsx`) | **OK**. O Prettier foi aplicado |

## Migrations

**Nenhuma migration.**

## Riscos e limitações

1. **Índice:** o polling filtra por `lead_id` + `updated_at`, e não há índice em `updated_at`. O índice `lead_id` limita a varredura às atividades do lead, o que basta para o volume atual. Se um lead acumular milhares de atividades, avaliar o índice (`lead_id`, `updated_at`), que exigiria migration.
2. **Relógio:** o cursor usa o relógio do app, e o `updated_at` também. Com vários containers web, uma diferença de relógio acima de 2 s poderia perder uma mudança. O `reset`/recarga por visibilidade atenua. Mitigação futura: cursor baseado em `max(updated_at)` do banco.
3. **Carga:** 12 req/min por aba aberta. O throttle subiu para 120/min por usuário, o que comporta cerca de 10 abas.
4. **Histórico e ACK:** mudanças de ACK em mensagens antigas carregadas via histórico são percebidas pelo incremental. Após um `reset`, mudanças anteriores a ele fora da página recente não são reaplicadas (caso raro).
5. **Ordem:** segue `created_at` do CRM, não o `timestamp` do WhatsApp (limitação herdada do CRM-WA-06).
6. **"Novas mensagens":** conta também outbound vinda do celular ou de outra aba, não só inbound.
7. **Sem dono por lead:** a autorização é por papel (admin), como no restante do CRM.

## Roteiro manual de validação E2E

Validar após o deploy, que **não foi feito**.

1. Abrir um lead com conversa. A conversa abre posicionada no fim, com separadores de data e direção em cada bolha.
2. **Inbound:** o cliente envia uma mensagem. Em até cerca de 5 s, ela aparece à esquerda com "Nova", sem reload. No DevTools, só há chamadas `messages?since=…`.
3. Em outra aba, a listagem de leads não mostra o badge desse lead depois do `markRead`. Com a aba da conversa oculta, a mensagem continua não lida até voltar à aba.
4. **Envio pelo CRM:** enviar pelo card. A bolha aparece à direita como "Pendente" ou "Enviada" e evolui para "Entregue" e depois "Lida" sem reload. Deve haver uma única bolha.
5. **Celular:** enviar pelo aparelho da sessão. A mensagem aparece à direita.
6. **Scroll:** rolar para cima e receber uma mensagem. A posição se mantém e aparece "1 nova mensagem ↓". Clicar leva ao fim e o botão some.
7. **Histórico:** em um lead com mais de 100 mensagens, clicar em "Carregar mensagens anteriores". As mensagens antigas entram no topo sem pular a leitura e sem duplicar. O botão some ao chegar ao início.
8. **Vazio:** em um lead sem mensagens, aparece o estado vazio. Sem WhatsApp válido, o envio fica desabilitado.
9. **Falha:** uma mensagem com ACK ERROR aparece como "Falhou".
10. **Navegação:** sair da tela do lead. No DevTools, o polling para.
11. **Acesso:** um usuário não admin recebe 403 em `leads/{id}/whatsapp/messages`.
