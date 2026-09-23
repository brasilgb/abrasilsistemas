Continuar a integração WhatsApp do CRM ABrasil Sistemas.

A infraestrutura já está funcionando de ponta a ponta:

CRM -> n8n -> WAHA -> WhatsApp

e os ACKs retornam:

WhatsApp -> WAHA message.ack -> n8n -> CRM

A tabela lead_activities já possui:

- provider_message_id
- message_status

Estados técnicos possíveis:

PENDING
SERVER
DEVICE
READ
PLAYED
ERROR

O endpoint de ACK já atualiza corretamente message_status e está validado.

OBJETIVO DESTA ETAPA

Melhorar a interface do histórico do prospect para exibir de maneira amigável o estado das mensagens WhatsApp.

Não alterar o fluxo de envio.
Não alterar n8n.
Não alterar WAHA.
Não criar migration.
Não modificar a lógica de ACK já implementada.

1. ANALISAR A INTERFACE ATUAL

Localize a página/componente onde o histórico/atividades do Lead/Prospect é exibido.

Antes de alterar, identifique:

- controller responsável;
- dados enviados pelo backend/Inertia;
- componente React responsável pelo histórico;
- tipos TypeScript existentes;
- como atividades `type = whatsapp` são atualmente exibidas.

Faça a menor alteração coerente com a arquitetura atual.

2. EXPOR message_status

Garanta que `message_status` esteja disponível no frontend para atividades WhatsApp.

Não exponha provider_message_id visualmente ao usuário.

Atualize os tipos TypeScript necessários.

3. TRADUÇÃO DOS STATUS

Na interface, nunca mostrar diretamente:

PENDING
SERVER
DEVICE
READ
PLAYED
ERROR

Mostrar:

PENDING -> Enviando
SERVER  -> Enviada
DEVICE  -> Entregue
READ    -> Lida
PLAYED  -> Reproduzida
ERROR   -> Erro no envio

Se message_status for null, não exibir indicador de status.

4. INDICADOR VISUAL

Nas atividades `type = whatsapp`, mostrar um indicador discreto junto à mensagem ou aos metadados.

Sugestão visual:

Enviando     → ícone Clock
Enviada      → Check
Entregue     → CheckCheck
Lida         → CheckCheck com destaque visual compatível com o design atual
Reproduzida  → CheckCheck ou ícone apropriado
Erro no envio → AlertCircle

Usar a biblioteca de ícones já existente no projeto.

Não instalar nova biblioteca apenas para isso.

Adicionar tooltip/title quando fizer sentido.

Não depender apenas de cor para comunicar o estado.

5. NÃO POLUIR O HISTÓRICO

O status deve ser secundário.

Exemplo conceitual:

WhatsApp
23/09/2026 11:30

Olá, tudo bem? Somos da ABrasil Sistemas...

✓✓ Lida

Não precisa seguir exatamente esse layout se a interface atual possuir outro padrão melhor.

6. COMPONENTIZAÇÃO

Se for adequado à estrutura atual, criar um pequeno componente reutilizável, por exemplo:

WhatsappMessageStatus

Recebendo:

status

e responsável por:

- tradução;
- ícone;
- apresentação.

Evitar espalhar vários condicionais pela página.

Se o projeto já possuir padrão equivalente para badges/status, reutilizá-lo.

7. ATUALIZAÇÃO DO STATUS

Nesta etapa NÃO implementar WebSocket, polling ou atualização em tempo real.

O status atualizado deve aparecer normalmente ao recarregar/revisitar a página.

Tempo real será avaliado posteriormente.

8. COMPATIBILIDADE

Atividades antigas de WhatsApp podem ter:

message_status = null

A interface deve continuar funcionando normalmente.

Atividades que não sejam WhatsApp não devem mostrar status técnico.

9. TESTES

Adicionar/ajustar os testes compatíveis com a arquitetura atual.

Cobrir pelo menos:

- atividade WhatsApp PENDING;
- SERVER;
- DEVICE;
- READ;
- PLAYED;
- ERROR;
- message_status null;
- atividade que não seja WhatsApp;
- carregamento do histórico contendo message_status.

Se existirem testes frontend adequados, utilizá-los.

Não introduzir uma infraestrutura de testes frontend nova apenas para esta alteração.

10. BUILD

Validar o build do frontend.

Executar PHP/Artisan somente no ambiente/container apropriado.

O projeto roda em Docker e o container principal é:

infra-abrasil-abrasilsistema-1

Não executar comandos destrutivos.

IMPORTANTE:

O container de produção possui código embutido na imagem e não monta o código-fonte.

Não copiar arquivos manualmente para dentro do container de produção apenas para testar.

Se necessário, utilizar a mesma estratégia segura usada anteriormente para testes em ambiente descartável.

11. ENTREGA

Ao terminar informar:

- arquivos analisados;
- arquivos criados;
- arquivos alterados;
- onde message_status passou a ser enviado ao frontend;
- componente responsável pela representação visual;
- mapeamento final dos seis estados;
- comportamento para null;
- testes executados;
- resultado do build;
- se será necessário rebuild/redeploy da imagem para produção.

Não faça rebuild/redeploy automaticamente.

Pare após concluir e documentar esta etapa.
