Continuar o módulo de prospecção/WhatsApp do CRM ABrasil Sistemas.

ESTADO ATUAL VALIDADO EM PRODUÇÃO

O pipeline abaixo está funcionando:

CRM -> n8n -> WAHA -> WhatsApp
                    ↓
                 message_id
                    ↓
                 n8n -> CRM

E também:

WhatsApp -> WAHA message.ack -> n8n -> CRM

O histórico do prospect já mostra os estados técnicos de forma amigável:

PENDING -> Enviando
SERVER  -> Enviada
DEVICE  -> Entregue
READ    -> Lida
PLAYED  -> Reproduzida
ERROR   -> Erro no envio

O endpoint de registro do envio já existe:

POST /api/prospects/{lead}/whatsapp/log

O endpoint de atualização de ACK já existe:

PATCH /api/whatsapp/messages/{providerMessageId}/status

Não alterar esses endpoints sem necessidade.

OBJETIVO DESTA ETAPA

Permitir que um usuário autenticado envie uma mensagem WhatsApp diretamente pela tela de edição/detalhes do prospect.

Fluxo desejado:

Tela do prospect
    ↓
usuário escreve mensagem
    ↓
Laravel
    ↓
webhook existente do n8n
    ↓
WAHA
    ↓
WhatsApp
    ↓
n8n registra automaticamente LeadActivity
    ↓
histórico passa a mostrar a mensagem/status

IMPORTANTE

O navegador NÃO deve chamar diretamente:

- n8n;
- WAHA;
- endpoint interno protegido por prospect.token.

O frontend deve chamar apenas o backend Laravel autenticado.

O Laravel será responsável por chamar o webhook do n8n no servidor.

--------------------------------------------------
1. AUDITORIA ANTES DA IMPLEMENTAÇÃO
--------------------------------------------------

Antes de alterar código, localizar e documentar:

- página atual `leads/edit`;
- LeadController;
- rotas web relacionadas ao Lead;
- modelo Lead;
- campos existentes para telefone/WhatsApp;
- validações existentes;
- padrão atual de chamadas Inertia/React;
- services/actions existentes para integrações HTTP;
- configuração atual em config/services.php;
- variável/configuração existente para URL do webhook do n8n, se houver;
- como mensagens de sucesso/erro são apresentadas na interface.

Não criar arquitetura paralela se já existir padrão adequado.

--------------------------------------------------
2. BACKEND PARA ENVIO
--------------------------------------------------

Criar uma rota WEB autenticada para envio.

Exemplo conceitual:

POST /leads/{lead}/whatsapp

ou outra URI coerente com o padrão existente.

Essa rota é para o usuário autenticado do CRM.

NÃO reutilizar `prospect.token` nessa rota.

Usar autenticação/autorização normal do painel.

Criar Request dedicado, por exemplo:

SendLeadWhatsappRequest

Validar pelo menos:

message:
- required
- string
- tamanho máximo razoável

Não receber o telefone livremente do frontend se não for necessário.

O backend deve buscar o WhatsApp diretamente do Lead.

--------------------------------------------------
3. TELEFONE/WHATSAPP
--------------------------------------------------

Identificar qual campo do Lead representa atualmente o WhatsApp.

Normalizar o número antes de enviar ao n8n.

Não inventar regras incompatíveis com o que o sistema já utiliza.

Para números brasileiros, preservar a lógica atualmente usada pelo projeto/integrador.

Se o Lead não possuir WhatsApp válido:

- não chamar n8n;
- retornar erro de validação amigável.

Não permitir envio silencioso para número vazio.

--------------------------------------------------
4. SERVIÇO DE INTEGRAÇÃO
--------------------------------------------------

Preferencialmente encapsular a chamada ao n8n em um Service/Action, por exemplo:

WhatsappService
LeadWhatsappService
N8nWhatsappService

Escolher nome coerente com o projeto.

Evitar colocar toda a chamada HTTP dentro do controller.

Usar Laravel HTTP Client.

O webhook atual do n8n recebe conceitualmente:

{
    "prospect_id": 118,
    "nome": "Nome do prospect",
    "whatsapp": "5551...",
    "mensagem": "Texto da mensagem"
}

Manter o contrato já validado pelo workflow.

--------------------------------------------------
5. CONFIGURAÇÃO
--------------------------------------------------

Não hardcodar a URL do n8n no controller.

Adicionar configuração apropriada em:

config/services.php

e variável correspondente no `.env.example`.

Exemplo conceitual:

N8N_WHATSAPP_WEBHOOK_URL=

A URL de produção atualmente utilizada pelo workflow deve ser configurável por ambiente.

NÃO colocar tokens, senhas ou segredos reais no repositório.

--------------------------------------------------
6. TRATAMENTO DA RESPOSTA DO N8N
--------------------------------------------------

O fluxo n8n já retorna algo semelhante a:

{
    "success": true,
    "message": "Mensagem enviada ao WAHA e registrada no CRM",
    "message_id": "3EB0...",
    "status": "PENDING",
    "activity_id": 11
}

O backend deve:

- verificar erro HTTP;
- verificar `success`;
- tratar timeout/conexão indisponível;
- retornar feedback amigável ao usuário;
- não criar uma segunda LeadActivity.

IMPORTANTE:

Quem registra a LeadActivity continua sendo o fluxo n8n através do endpoint já existente.

Não duplicar esse registro no controller web.

--------------------------------------------------
7. INTERFACE DO PROSPECT
--------------------------------------------------

Na página do prospect, adicionar uma ação clara:

"Enviar WhatsApp"

Não abrir WhatsApp Web.

Essa ação utiliza nossa integração CRM -> n8n -> WAHA.

Ao clicar, apresentar uma interface simples para escrever a mensagem.

Pode ser:

- modal/dialog;
- card expansível;

Escolher o padrão visual já utilizado pelo sistema.

Campos:

Mensagem
[textarea]

Mostrar contador de caracteres se for coerente com os componentes existentes.

Botões:

Cancelar
Enviar WhatsApp

Durante o envio:

- desabilitar botão;
- impedir duplo clique;
- mostrar estado "Enviando...".

Após sucesso:

- fechar modal;
- limpar mensagem;
- mostrar feedback de sucesso;
- atualizar/recarregar os dados necessários para que a nova atividade apareça no histórico.

Evitar reload completo da página se o padrão Inertia existente permitir atualização parcial.

--------------------------------------------------
8. CONTEXTO DO PROSPECT
--------------------------------------------------

No modal/card mostrar discretamente:

Nome do prospect
WhatsApp de destino

Exemplo:

Enviar WhatsApp

Cliente:
Assistência Técnica ABC

Destino:
(51) 99999-9999

Mensagem:
[...........................]

Isso reduz o risco de o operador enviar mensagem para o prospect errado.

--------------------------------------------------
9. SEGURANÇA
--------------------------------------------------

A rota deve:

- exigir usuário autenticado;
- respeitar a autorização existente para acesso/edição do Lead;
- não aceitar lead_id arbitrário no body;
- usar route model binding;
- não expor token interno;
- não expor credenciais WAHA;
- não expor URL interna desnecessariamente ao frontend.

Adicionar throttle coerente para envio manual de WhatsApp.

Não criar mecanismo de disparo em massa nesta etapa.

O objetivo é envio individual e deliberado pelo operador.

--------------------------------------------------
10. ERROS DE UX
--------------------------------------------------

Tratar claramente pelo menos:

- prospect sem WhatsApp;
- mensagem vazia;
- número inválido;
- n8n indisponível;
- timeout;
- resposta inválida do n8n;
- WAHA/n8n retornando success=false;
- HTTP 4xx/5xx.

Não mostrar stack trace nem detalhes internos para o usuário.

Registrar detalhes técnicos nos logs do Laravel quando necessário.

Não registrar tokens/segredos nos logs.

--------------------------------------------------
11. HISTÓRICO
--------------------------------------------------

Depois do envio bem-sucedido, a atividade criada pelo fluxo n8n deverá aparecer no histórico existente.

Ela já suporta:

Enviando
Enviada
Entregue
Lida
Reproduzida
Erro no envio

Não duplicar o componente de status.

Reutilizar:

WhatsappMessageStatus

--------------------------------------------------
12. NÃO IMPLEMENTAR AINDA
--------------------------------------------------

Nesta etapa NÃO implementar:

- disparo em massa;
- campanhas;
- fila automática de prospecção;
- templates persistidos em banco;
- IA gerando mensagens;
- agendamento;
- chatbot;
- respostas recebidas do WhatsApp;
- WebSocket;
- polling contínuo;
- edição de n8n;
- edição de WAHA.

Queremos primeiro o envio manual individual funcionando perfeitamente.

--------------------------------------------------
13. TESTES
--------------------------------------------------

Adicionar testes compatíveis com a arquitetura atual cobrindo pelo menos:

- rota exige autenticação;
- usuário autorizado consegue enviar;
- usuário sem acesso ao Lead não consegue enviar;
- Lead sem WhatsApp;
- mensagem vazia;
- mensagem válida;
- payload correto enviado ao n8n;
- prospect_id vem da rota/model e não do body;
- nome correto;
- WhatsApp correto/normalizado;
- mensagem correta;
- timeout do n8n;
- HTTP 500 do n8n;
- resposta success=false;
- resposta inválida;
- sucesso;
- nenhuma LeadActivity adicional é criada pelo controller web.

Usar Http::fake() nos testes.

Não realizar chamadas reais ao n8n/WAHA durante testes.

Testar também que segredos/URLs internas não são enviados como props Inertia.

--------------------------------------------------
14. BUILD E QUALIDADE
--------------------------------------------------

Executar:

- testes relevantes;
- Pint;
- TypeScript;
- build frontend;
- git diff --check.

Não introduzir nova infraestrutura de testes frontend apenas para esta alteração.

O projeto roda em Docker.

O container principal de produção é:

infra-abrasil-abrasilsistema-1

PHP/Artisan não devem ser executados diretamente no host.

O container de produção possui código embutido na imagem e não deve receber arquivos copiados manualmente apenas para teste.

Usar ambiente/container descartável para testes quando necessário, como nas etapas anteriores.

Não executar:

migrate:fresh
db:wipe
rollback global
ou comandos destrutivos.

--------------------------------------------------
15. PRODUÇÃO
--------------------------------------------------

Não fazer rebuild/redeploy automaticamente.

Não alterar `.env` de produção automaticamente.

Ao final informar exatamente qual variável precisa ser adicionada ao `.env` de produção e qual valor ela deve receber, SEM inventar a URL.

Se a URL puder ser determinada inequivocamente pela configuração/documentação atual do projeto, informar a URL encontrada; caso contrário, indicar que precisa ser preenchida com a URL de produção do webhook já existente.

--------------------------------------------------
16. ENTREGA
--------------------------------------------------

Ao terminar, gerar relatório contendo:

- arquitetura encontrada;
- arquivos criados;
- arquivos alterados;
- rota criada;
- Request criado;
- Service/Action criado;
- configuração adicionada;
- variável `.env` necessária;
- contrato enviado ao n8n;
- tratamento de erros;
- autorização aplicada;
- alterações na interface;
- comportamento após sucesso;
- testes executados;
- resultado dos testes;
- resultado do TypeScript/build;
- comandos necessários para deploy;
- qualquer pendência encontrada.

Pare ao concluir esta etapa.

Não faça deploy automaticamente.
