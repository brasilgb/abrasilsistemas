Implemente o CRM-WA-05 conforme a auditoria já realizada, sem ampliar o escopo.

Objetivo: adicionar à tela de edição do lead uma área “Conversa WhatsApp” funcional, utilizando exclusivamente a infraestrutura existente.

Regras obrigatórias:

1. NÃO criar migration.
2. NÃO alterar n8n.
3. NÃO alterar WAHA.
4. NÃO alterar a sessão `vetoros1-1`.
5. NÃO alterar docker-compose.
6. NÃO alterar `routes/api.php`.
7. NÃO alterar `WhatsappInboundController`, `WhatsappActivityController`, `WhatsappMessageStatusController` ou `LeadWhatsappService`.
8. Preservar integralmente as regras atuais de idempotência, `message_status` e `read_at`.
9. Não acessar nem modificar banco de produção.
10. Executar PHP/Artisan somente dentro do container da aplicação.

### Backend

Criar:

- `app/Http/Controllers/LeadWhatsappConversationController.php`
- `app/Http/Requests/Leads/MarkLeadWhatsappReadRequest.php`

Adicionar no grupo web protegido por `auth`, `verified` e `admin`:

- `GET /leads/{lead}/whatsapp/messages`
- `POST /leads/{lead}/whatsapp/read`

O GET deve:

- retornar somente atividades `type=whatsapp` que possuam `direction` válida;
- excluir WhatsApp manual sem `direction`;
- retornar somente mensagens pertencentes ao lead da rota;
- retornar as últimas 100 mensagens;
- ordenar definitivamente por `created_at ASC, id ASC`;
- expor somente:
  `id`, `direction`, `description`, `created_at`, `message_status`, `read_at`;
- nunca expor `provider_message_id`, `user_id` ou campos comerciais;
- não modificar `read_at`;
- não possuir qualquer outro efeito colateral.

O POST `/read` deve receber:

`{ up_to_id: integer }`

e marcar como lidas exclusivamente mensagens:

- inbound;
- ainda não lidas;
- pertencentes ao lead da rota;
- com `id <= up_to_id`.

Deve ser idempotente e nunca modificar `message_status`.

Pode ser criado um scope `conversation()` em `LeadActivity` caso realmente simplifique e centralize essa regra.

### Frontend

Criar:

`resources/js/components/lead-whatsapp-conversation.tsx`

Adicionar o componente em `resources/js/pages/leads/edit.tsx`, na coluna direita, acima do Histórico.

Interface:

- inbound alinhada à esquerda;
- outbound alinhada à direita;
- mostrar horário;
- mostrar status ACK somente para outbound;
- mostrar “Nova” para inbound que chegou não lida durante a visita;
- manter localmente os IDs considerados novos para o selo não desaparecer imediatamente após `read_at` ser atualizado;
- área da conversa com scroll;
- scroll automático para o final somente quando o usuário já estiver próximo do final.

Adicionar campo de envio diretamente no card.

Reutilizar a rota existente `leads.whatsapp.store`.

Não criar outro backend de envio.

Após sucesso:

- limpar o campo;
- atualizar a conversa;
- não criar mensagem otimista;
- não duplicar mensagens.

Enquanto estiver enviando, desabilitar o botão.

Exibir no próprio card erros `message` e `whatsapp`.

Se `whatsappDestination` não existir, desabilitar o envio e apresentar a explicação já utilizada atualmente.

### Polling

Implementar polling com `fetch` seguindo o padrão existente em `company-whatsapp-connection.tsx`.

Regras:

- intervalo de 10 segundos;
- executar somente se `document.visibilityState === 'visible'`;
- ao retornar para a aba, executar refresh imediatamente;
- limpar interval/listeners no unmount;
- impedir dois fetches simultâneos;
- substituir/atualizar a lista por `id`, sem duplicação;
- a janela retornada pelo backend deve também atualizar `message_status` das mensagens outbound existentes.

Após receber mensagens inbound com `read_at === null`, e somente com a página visível, enviar `POST /read` usando como `up_to_id` o maior ID inbound efetivamente exibido.

O GET nunca deve marcar mensagens como lidas.

### Histórico

WhatsApp registrado manualmente em “Registrar contato” e que não possui `direction` NÃO faz parte da conversa.

Ele deve continuar aparecendo somente no Histórico.

Após confirmar que o envio embutido funciona corretamente, remover o botão/modal duplicado de envio do cabeçalho do Histórico, mantendo apenas o envio dentro da conversa.

### Testes

Criar:

`tests/Feature/LeadWhatsappConversationTest.php`

Cobrir obrigatoriamente:

1. autenticação;
2. autorização admin;
3. somente WhatsApp com direction;
4. isolamento entre leads;
5. ordenação `created_at ASC, id ASC`;
6. limite das últimas 100;
7. ausência de `provider_message_id`;
8. GET sem efeito colateral;
9. `markRead` somente nas inbound corretas;
10. `markRead` não altera outbound;
11. `markRead` não altera outros leads;
12. `markRead` não altera `message_status`;
13. idempotência do `markRead`;
14. validação de `up_to_id`;
15. atualização posterior de ACK refletida no GET;
16. atividade registrada pelo `/log` aparecendo apenas uma vez mesmo com repetição do mesmo `message_id`;
17. regressão do comportamento atual de leitura ao abrir o lead;
18. regressão do envio atual.

Não enfraquecer ou remover testes existentes para fazer a implementação passar.

### Validação

Ao terminar, execute dentro do container:

- testes específicos relacionados a LeadWhatsapp;
- suíte PHP completa;
- `npm run types`;
- `npm run lint`.

Se algum teste falhar, investigue e corrija a implementação antes de encerrar.

Não faça deploy.

Não faça alterações fora do escopo.

Ao final, gere um relatório objetivo contendo:

- arquivos criados;
- arquivos alterados;
- rotas adicionadas;
- comportamento implementado;
- testes criados;
- resultado dos testes PHP;
- resultado de types;
- resultado de lint;
- qualquer limitação que permaneceu.

Só considere CRM-WA-05 concluído se todos os testes e validações relevantes estiverem verdes.
