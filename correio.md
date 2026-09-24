Estamos evoluindo a integração WhatsApp do CRM ABrasil Sistemas.

IMPORTANTE:
Antes de alterar qualquer arquivo, faça uma auditoria do fluxo atual e preserve tudo que já está funcionando.

PROJETO
- Infra: /opt/infra-abrasil
- Aplicação Laravel: serviço Docker `abrasilsistema`
- Não executar PHP/Artisan diretamente no host.
- Comandos Laravel devem rodar dentro do container.
- Banco atual: MySQL.
- n8n e WAHA já estão funcionando e NÃO devem ser alterados nesta tarefa.

ESTADO ATUAL VALIDADO

Fluxo de saída funcionando:

CRM
→ n8n
→ WAHA
→ WhatsApp
→ message.ack
→ n8n
→ Laravel
→ atualização do status no CRM

Status validados:
PENDING
SERVER
DEVICE
READ
PLAYED
ERROR

Também validamos o fluxo de entrada:

WhatsApp
→ WAHA
→ evento `message.any`
→ n8n
→ Laravel
→ LeadActivity

Uma mensagem real recebida foi gravada corretamente:

description = "Teste recebimento CRM 02"
lead_id = 118
provider_message_id = "3EB0C2EF5A6141DC0935D3"

PROBLEMA DE MODELAGEM

A tabela `lead_activities` atualmente possui:

id
lead_id
user_id
type
status
contacted_at
next_follow_up_at
description
provider_message_id
message_status
created_at
updated_at

Não existe um campo que indique a direção da mensagem.

Consequentemente mensagens enviadas e recebidas ficam semanticamente misturadas.

Além disso, mensagens recebidas atualmente podem aparecer com:

message_status = DEVICE

Isso é inadequado semanticamente.

`DEVICE`, `SERVER`, `READ`, etc. são estados de entrega principalmente das mensagens OUTBOUND.

OBJETIVO

Implementar distinção formal entre mensagens WhatsApp recebidas e enviadas:

direction = inbound
direction = outbound

Significados:

inbound
cliente → empresa

outbound
empresa → cliente

REQUISITOS

1. AUDITORIA

Antes de alterar código:

- localizar migration/model de lead_activities;
- localizar todos os pontos que criam LeadActivity do tipo whatsapp;
- localizar envio pelo LeadWhatsappService;
- localizar endpoint/controller usado pelo n8n para mensagens recebidas;
- localizar endpoint/controller de atualização de ACK;
- localizar serializers/resources/controllers que retornam atividades para o frontend;
- localizar componentes React que exibem histórico/WhatsApp;
- localizar testes existentes relacionados a WhatsApp;
- verificar exatamente como o n8n chama o endpoint Laravel de mensagem recebida.

Não alterar n8n.
Não alterar WAHA.
Não alterar webhooks.
Não alterar credenciais.
Não alterar sessão `vetoros1-1`.

2. BANCO

Criar migration adicionando:

direction

à tabela lead_activities.

Preferência:

string/enum semanticamente seguro, conforme padrão já usado pelo projeto.

Valores válidos:

inbound
outbound

O campo deve ser aplicável às atividades WhatsApp.

Avalie cuidadosamente nullable/default para manter compatibilidade com atividades antigas que não são WhatsApp.

Não quebrar registros históricos.

Criar índice somente se houver benefício real para consultas do histórico/conversa.

3. MODEL

Atualizar LeadActivity conforme necessário:

- fillable/casts/etc.;
- constantes ou enum PHP, se fizer sentido no padrão atual do projeto;
- evitar strings mágicas espalhadas pelo código.

Não introduzir arquitetura desnecessária.

4. MENSAGENS ENVIADAS

Toda nova mensagem enviada pelo CRM deve ser registrada como:

type = whatsapp
direction = outbound

O comportamento atual de:

provider_message_id
message_status
ACK progression

deve continuar exatamente funcionando.

Não alterar a regra atual que impede regressão:

PENDING
→ SERVER
→ DEVICE
→ READ
→ PLAYED

ERROR conforme comportamento atual.

5. MENSAGENS RECEBIDAS

Toda mensagem recebida do WhatsApp deve ser registrada como:

type = whatsapp
direction = inbound

Para mensagens inbound:

não usar DEVICE como representação artificial de recebimento.

Avaliar o comportamento atual e definir semanticamente o melhor valor para `message_status`.

Preferência:

message_status = null

para inbound, caso isso não quebre contratos existentes.

O fato de ser uma mensagem recebida deve ser representado por:

direction = inbound

e não por message_status.

Preservar:

description
provider_message_id
contacted_at
lead_id

6. COMPATIBILIDADE COM DADOS EXISTENTES

Não atualizar registros históricos cegamente.

Auditar se é possível identificar com segurança mensagens antigas como inbound/outbound.

Se não houver forma determinística:

- não inventar direção;
- permitir null para legado, se necessário;
- documentar no relatório.

Se houver regra comprovadamente segura para classificar registros existentes, apresentar a regra antes de aplicá-la.

7. FRONTEND

Auditar a exibição atual do histórico WhatsApp.

A interface deve conseguir distinguir visualmente:

Mensagem enviada
Mensagem recebida

Não precisa transformar agora o CRM em um WhatsApp Web completo.

Faça apenas a melhoria mínima e consistente com a UI atual.

Exemplo conceitual:

Recebida
Cliente → Empresa

Enviada
Empresa → Cliente

Não exibir "Entregue" para uma mensagem inbound apenas porque ela foi recebida.

Status SERVER/DEVICE/READ/etc. devem continuar sendo exibidos para outbound.

Preservar o layout e componentes existentes.

8. API / CONTRATOS

Garantir que `direction` esteja disponível onde o frontend precisa.

Não expor dados desnecessários.

Não quebrar contratos atuais sem necessidade.

9. TESTES

Criar/ajustar testes cobrindo no mínimo:

A) envio pelo CRM cria:
type = whatsapp
direction = outbound

B) recebimento cria:
type = whatsapp
direction = inbound

C) inbound não recebe artificialmente DEVICE

D) outbound continua aceitando:
SERVER
DEVICE
READ
PLAYED

E) proteção contra regressão de ACK continua funcionando

F) mensagem inbound mantém:
lead_id
description
provider_message_id
contacted_at

G) registros/atividades não WhatsApp continuam funcionando

H) endpoints continuam protegidos conforme autenticação/autorização existente.

Execute os testes dentro do container Laravel.

Se houver testes antigos quebrados não relacionados à tarefa, identifique-os separadamente e não faça alterações oportunistas.

10. BUILD

Depois da implementação:

- executar testes relevantes;
- executar suite adequada;
- executar build frontend;
- executar TypeScript/lint pertinente se configurado.

Não fazer deploy.

Não executar migration no banco de produção.

Não executar docker compose up.

Não reconstruir containers de produção.

11. NÃO ALTERAR

Não modificar:

- workflows n8n;
- WAHA;
- webhooks;
- sessão vetoros1-1;
- API keys/tokens;
- docker-compose, salvo se descobrir necessidade absolutamente indispensável — nesse caso apenas documentar, não alterar;
- lógica de conexão/QR Code já implementada;
- fluxo funcional de envio;
- fluxo funcional de ACK.

IMPORTANTE SOBRE O EVENTO WAHA

Foi validado que nesta versão do WAHA/NOWEB:

`message.any`

é necessário para o recebimento que testamos.

A sessão atualmente possui:

message.ack
→ https://n8n.abrasilsistemas.com.br/webhook/waha-message-status

message + message.any
→ https://n8n.abrasilsistemas.com.br/webhook/waha-message-received

NÃO ALTERAR ESSA CONFIGURAÇÃO.

12. ENTREGA

Ao terminar, gere um relatório contendo:

1. auditoria realizada;
2. arquivos criados;
3. arquivos alterados;
4. migration criada;
5. regra final de direction;
6. tratamento de message_status para inbound;
7. estratégia para registros históricos;
8. alterações de frontend;
9. testes executados;
10. resultado dos testes;
11. resultado do build;
12. riscos ou pendências;
13. comandos exatos que EU devo executar manualmente para:
   - aplicar migration;
   - rebuild;
   - deploy;
   - validar pós-deploy.

Não execute esses comandos de produção.

OBJETIVO FINAL

Depois desta tarefa, o domínio deve representar corretamente:

OUTBOUND:
Empresa → Cliente
direction = outbound
message_status acompanha ACK

INBOUND:
Cliente → Empresa
direction = inbound
message_status não deve representar artificialmente entrega ao dispositivo

A implementação deve ser pequena, segura, testada e compatível com o fluxo WhatsApp que já validamos em produção.
