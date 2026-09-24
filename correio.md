> **Próxima tarefa — mensagens WhatsApp não lidas**
>
> Audite primeiro a implementação atual do CRM ABrasil e implemente controle de mensagens WhatsApp inbound não lidas, preservando integralmente o fluxo já funcional WAHA → n8n → `POST /api/whatsapp/inbound` → `LeadActivity`.
>
> Requisitos:
> - Não alterar o workflow n8n, WAHA, envio outbound ou ACK já funcionais.
> - Toda nova `LeadActivity` com `type=whatsapp` e `direction=inbound` deve poder ser identificada como não lida.
> - Na listagem de leads, mostrar de forma discreta e clara quando houver mensagens recebidas não lidas, preferencialmente com quantidade.
> - Ao acessar o lead e efetivamente visualizar seu histórico, marcar as mensagens inbound pendentes como lidas.
> - Mensagens outbound não entram nessa contagem.
> - Não usar `message_status` para representar leitura interna do CRM; `message_status` continua reservado aos estados de entrega do WhatsApp.
> - Preservar multitenancy/escopo e as regras atuais de acesso aos leads.
> - Evitar N+1 na listagem de leads.
> - Criar migration somente se a auditoria demonstrar que é necessária. Escolha uma modelagem simples e sustentável.
> - Criar testes cobrindo: inbound nasce não lido; outbound não conta; quantidade por lead; abertura/visualização marca como lido; isolamento entre leads; e idempotência existente continua funcionando.
> - Não executar deploy.
> - Não alterar funcionalidades fora desse escopo.
> - Ao terminar, entregar relatório dos arquivos alterados, decisão de modelagem, migrations, testes criados e comandos necessários para validação manual.
>
> Antes de implementar, examine `LeadActivity`, `LeadController`, `WhatsappInboundController`, rotas e componentes React da listagem/detalhes para aproveitar a arquitetura existente e evitar duplicação.

