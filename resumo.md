# Execução de `correio.md`: CRM-WA-07, Fase 2 (UX comercial da conversa WhatsApp)

Data: 2026-09-25.

## Status

**Fase 2 implementada e validada em ambiente descartável.**

- **Nenhuma migration.** Nenhuma coluna, tabela ou endpoint novo.
- n8n, WAHA, Docker/infra e os endpoints de integração (`/log`, `/inbound`, `/outbound-external`, `/messages/{id}/status`) **não foram alterados**. `GET …/whatsapp/messages` e `POST …/whatsapp/read` (Fase 1) também não.
- Sem suporte a mídia, áudio, documentos ou anexos.
- O banco de produção não foi acessado. Nada rodou no container de produção.
- Sem deploy e sem commit.

## Auditoria

| Item | O que já existia | Uso na Fase 2 |
|---|---|---|
| Composer | `<Form>` do Inertia, botão "Enviar", sem atalho de teclado | Substituído por envio controlado (`router.post`) na mesma rota `leads.whatsapp.store` |
| Dados comerciais | `Lead`: `contact_name`, `company_name`, `city`, `whatsapp`, `status`, `lost_reason`, `user` (responsável). Já vêm no prop `lead` de `leads/edit` | Cabeçalho da conversa, sem campo novo |
| Mensagens prontas | `resources/js/lib/lead-whatsapp-messages.ts`: apresentação por produto (VetorOS/VetorPet), editável em Configurações > Leads (salva em `localStorage`), usada no botão WhatsApp da listagem | Reutilizada como grupo "Apresentação". Primeiro contato e follow-up foram adicionados na mesma lib |
| Variáveis | Não existiam | Criadas na mesma lib, só para campos existentes |
| Funil | `Lead::STATUSES` (Novo, Contatado, Interessado, Reunião, Convertido, Perdido) + `LOST_REASONS`. `PATCH leads/{lead}/status` (Kanban) valida, exige motivo quando "Perdido", preenche `last_contacted_at` e registra nota no histórico | Reutilizado pela conversa. Único ajuste: parâmetro opcional `origin` |

## Arquivos

**Criados**

- `tests/Feature/LeadWhatsappConversationCommercialTest.php`

**Alterados**

- `app/Http/Controllers/LeadController.php`: `status()` aceita `origin` opcional (`kanban` | `whatsapp`), usado só no texto do histórico. Sem o campo, o comportamento e o texto do Kanban continuam iguais.
- `resources/js/components/lead-whatsapp-conversation.tsx`: composer, cabeçalho comercial, estágio do funil e mensagens rápidas. Polling, histórico, scroll e não lidas da Fase 1 **não foram tocados**.
- `resources/js/lib/lead-whatsapp-messages.ts`: `whatsappQuickMessages`, `fillWhatsappVariables` e `unresolvedWhatsappVariables`. As funções existentes não mudaram.
- `resources/js/pages/leads/edit.tsx`: passa `lead`, `statuses` e `lostReasons` para a conversa (antes era `leadId`).
- `resources/js/pages/leads/form.tsx`: `key` nos selects de status e motivo de perda. Explicação em "Decisões".

## Comportamento implementado

### 1. Composer

- **Enter** envia. **Shift+Enter** quebra a linha. O Enter que confirma um IME (acentuação/composição) é ignorado.
- **Duplo envio:** uma trava síncrona (`ref`) e o estado `sending` bloqueiam Enter e clique durante a requisição. O campo fica `readOnly` e o botão mostra "Enviando...".
- **Falha:** o texto permanece no campo e o erro aparece abaixo, com borda vermelha:
  - validação ou falha do n8n/WAHA: a mensagem vinda do backend (`message`/`whatsapp`);
  - 429 (throttle 10/min): "Muitos envios seguidos...";
  - outro erro HTTP ou sem rede: "...O texto foi mantido; tente de novo." (sem o modal de erro do Inertia).
- O campo é limpo **só no `onSuccess`**. Depois disso a conversa vai para o fim e roda um ciclo de polling (mesmo comportamento da Fase 1).
- Digitar de novo limpa o erro. Contador `n/4000` e dica de atalhos continuam visíveis.

### 2. Cabeçalho da conversa

Mostra apenas dados que já existem no lead:

- nome do contato (ou "Contato sem nome");
- empresa · cidade;
- WhatsApp formatado, com "(inválido)" quando o número não normaliza;
- responsável;
- estágio do funil, editável (item 5).

### 3. Mensagens rápidas

Menu "Mensagens rápidas" ao lado do botão Enviar, agrupado:

| Grupo | Mensagem | Origem do texto |
|---|---|---|
| Primeiro contato | Primeiro contato | nova (usa `{{empresa}}`) |
| Apresentação | Apresentação VetorOS / VetorPet | **existente**, com a edição salva em Configurações > Leads |
| Follow-up | Follow-up após apresentação; Último follow-up | novas (usam `{{nome}}`/`{{empresa}}`) |

### 4. Variáveis

- Suportadas: `{{nome}}` → `contact_name`, `{{empresa}}` → `company_name`, `{{cidade}}` → `city`. Todas são campos existentes do lead.
- Ao escolher um template, o texto **já substituído** vai para o campo de mensagem. Esse é o texto final, visível e editável antes do envio. **Nada é enviado ao selecionar.**
- Se o campo já tiver texto, a tela pede confirmação antes de substituir.
- Uma variável sem valor no lead (ex.: `{{nome}}` sem contato) ou desconhecida **fica no texto**. Aparece o aviso "Complete antes de enviar: {{nome}}" e o envio fica bloqueado até o operador editar. Assim nunca sai "Olá, ," nem um placeholder cru para o cliente.

### 5. Estágio do funil na conversa

- Um select com os estágios de `Lead::STATUSES` salva na hora pelo `PATCH leads/{lead}/status` do Kanban, com `origin=whatsapp`.
- "Perdido" mostra o select de motivo e o botão Salvar, porque o backend continua exigindo `lost_reason`.
- O histórico registra "Status alterado de X para Y **pela conversa WhatsApp**". A nota é do tipo `note`, então não entra na conversa nem nas não lidas (há teste para isso).
- Recarrega só o prop `lead` (`only: ['lead']`), sem recarregar a página.

### 6. Fase 1 preservada

O código de polling de 5 s, `?since`/`?before_id`, merge por id, ACK, não lidas/`markRead`, selo "Nova", scroll e indicador de novas mensagens **não foi alterado**. Só o JSX do composer e do cabeçalho mudou. O envio pelo celular continua aparecendo como outbound pelo polling (`/outbound-external` intacto). Todos os testes da Fase 1 passam.

## Decisões

1. **Sem migration para templates.** A estrutura existente (textos no código + edição local em Configurações) atende "simples e reutilizável". Persistir no banco só se justifica quando os templates precisarem ser compartilhados entre operadores. Isso já está listado como pendência em `INSTRUCOES_ABRASILSISTEMAS.md`, então a migration fica para essa tarefa.
2. **Reutilizar o endpoint do Kanban** em vez de criar outro. O `origin` é opcional e só muda o texto da nota. A lista é fechada (`Rule::in`), então não aceita texto livre.
3. **Status sincronizado no formulário da página:** o select de status do formulário principal é não controlado (`defaultValue`). Sem ajuste, mudar o estágio pela conversa e depois salvar o formulário gravaria o status antigo de volta. A `key={lead.status}` remonta **só esses dois selects**, então edições não salvas nos outros campos do formulário são preservadas.
4. **Variável sem valor bloqueia o envio** em vez de ser removida silenciosamente.
5. `router.post` manual no composer, em vez do `<Form>`: o Enter precisa da mesma trava de duplo envio que o clique, e o `<Form>` limpava/mantinha o texto de forma menos controlável.

## Testes executados e resultados

Ambiente: cópia do working tree na scratchpad, em containers `docker run --rm --network none`:

- imagem `infra-abrasil-abrasilsistema`, com SQLite em memória e `APP_KEY` descartável;
- `node:22-bookworm-slim`;
- `vendor` e `node_modules` de execução anterior, com `composer.lock`/`package-lock.json` idênticos.

**Novo arquivo `LeadWhatsappConversationCommercialTest.php` (9 testes, 65 assertions):**

1. `leads/edit` entrega ao cabeçalho: nome, empresa, cidade, WhatsApp, status, responsável, `statuses`, `lostReasons` e destino normalizado;
2. mudança de estágio pela conversa usa o endpoint existente e registra "pela conversa WhatsApp" com usuário e status;
3. sem `origin` (ou `origin=kanban`) o texto continua "pelo Kanban";
4. "Perdido" pela conversa exige motivo e registra o motivo;
5. `origin` desconhecido/não-string e status inexistente → erro de validação, lead intacto (3 casos);
6. sem login → login; `reader` → 403;
7. a nota de mudança de estágio não aparece na conversa nem conta como não lida.

| Validação | Resultado |
|---|---|
| `--filter=LeadWhatsappConversationCommercial` | **9 passed (65 assertions)** |
| `--filter="Whatsapp\|LeadManagement"` (WhatsApp + fluxo de leads/Kanban) | **317 passed (1448 assertions)** |
| Suíte completa | **363 passed, 1 failed**. A falha é `BlogTest > administrator can upload blog images` (`imagejpeg` indisponível na imagem). É pré-existente e igual à da Fase 1 |
| `pint --test` (`LeadController`, teste novo) | **PASS** |
| `wayfinder:generate --with-form` + `tsc --noEmit` | **0 erros** |
| ESLint (4 arquivos TS alterados) | **OK** |
| Prettier `--check` (componente, lib, `form.tsx`) | **OK** |

Sobre o Prettier em `edit.tsx`: o arquivo já tinha trechos fora do padrão antes desta tarefa. Rodar `--write` reformataria cerca de 90 linhas não relacionadas, então apliquei só a mudança de props (4 linhas) e deixei a formatação antiga como estava.

Não há test runner de frontend no projeto. Enter/Shift+Enter, trava de duplo envio e substituição de variáveis foram validados por tipo/lint e ficam no roteiro E2E abaixo.

## Migrations

**Nenhuma migration.**

## Riscos e limitações

1. **Templates locais:** follow-up e primeiro contato estão no código. A edição de apresentação em Configurações fica no `localStorage` de cada navegador (limitação pré-existente). Compartilhar entre operadores exige a tabela de templates já prevista.
2. **Variáveis limitadas** a nome, empresa e cidade. `{{produto}}` ou outras exigiriam só uma linha na lib, desde que o campo exista.
3. **Recarga do lead ao mudar estágio/enviar:** o `only: ['lead']` reexecuta `LeadController@edit`, que marca as inbound pendentes como lidas. Isso já acontecia no envio desde o CRM-WA-05, então não é regressão.
4. **Mesma página, dois lugares para o status:** o formulário principal e a conversa ficam sincronizados após salvar. Um status alterado mas **não salvo** no formulário principal é substituído quando o estágio muda pela conversa.
5. **Texto com `{{...}}` legítimo** (ex.: o operador quer mandar chaves duplas) fica bloqueado. É um caso raro, e basta remover as chaves.
6. **Autorização** continua por papel (admin), sem dono por lead (limitação herdada).

## Roteiro manual de validação E2E

Validar após o deploy, que **não foi feito**.

1. **Cabeçalho:** abrir um lead com contato, cidade e responsável. A conversa mostra nome, empresa · cidade, WhatsApp formatado, responsável e estágio atual. Em um lead sem nome: "Contato sem nome". Com número inválido: "(inválido)".
2. **Enter/Shift+Enter:** digitar, usar Shift+Enter (quebra a linha) e depois Enter. Envia uma única vez, o campo limpa e a bolha aparece à direita.
3. **Duplo envio:** pressionar Enter várias vezes rápido ou clicar repetidamente. Só uma mensagem chega ao cliente (conferir no celular e no DevTools: um único POST).
4. **Falha:** desabilitar o WhatsApp em Configurações > Leads e enviar. O erro aparece e o texto continua no campo. Reabilitar e enviar de novo: sucesso e o campo limpa. Opcional: sem rede (DevTools offline) → mensagem de conexão, texto mantido.
5. **Mensagens rápidas:** escolher "Primeiro contato". O texto aparece no campo com o nome da empresa e **não é enviado**. Editar e enviar manualmente.
6. **Variável ausente:** em um lead sem contato, escolher "Follow-up após apresentação". O aviso "Complete antes de enviar: {{nome}}" aparece e o botão fica desabilitado. Substituir `{{nome}}` libera o envio.
7. **Apresentação editada:** alterar o texto do VetorOS em Configurações > Leads e salvar. Na conversa, "Apresentação VetorOS" usa o texto editado.
8. **Substituição:** com texto no campo, escolher um template. A tela pede confirmação. Cancelar mantém o texto.
9. **Estágio:** mudar de Contatado para Interessado na conversa. Salva sem reload, o Histórico mostra "...pela conversa WhatsApp" e o select do formulário principal já mostra "Interessado". Salvar o formulário principal não reverte o status.
10. **Perdido:** escolher "Perdido". Aparece o motivo e o botão Salvar só habilita com motivo. Depois de salvar, o Kanban mostra o lead em Perdido.
11. **Kanban:** arrastar um card na listagem. O histórico continua dizendo "pelo Kanban".
12. **Regressão da Fase 1:** receber inbound (aparece em até ~5 s com "Nova"), ver o ACK evoluir (Enviada → Entregue → Lida), enviar pelo celular (aparece à direita), rolar para cima e receber (indicador "1 nova mensagem ↓"), "Carregar mensagens anteriores" em lead com mais de 100 mensagens e sair da tela (polling para).
