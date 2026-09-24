TAREFA — Gerenciamento da conexão WhatsApp/WAHA pelo painel do CRM ABrasil

Projeto:
ABrasil Sistemas / CRM de Leads.

Objetivo:
Implementar no próprio painel do CRM o gerenciamento da sessão WhatsApp WAHA, incluindo geração e exibição do QR Code, status da sessão, número realmente autenticado e desconexão.

IMPORTANTE:
Antes de implementar, audite o código atual relacionado a:
- Configurações → Leads
- CompanyWhatsappSettings
- LeadWhatsappService
- integrações WAHA/n8n existentes
- rotas e controllers de settings
- estrutura Docker/env/config relacionada ao WAHA

Não recrie funcionalidades que já existam.

==================================================
1. SITUAÇÃO ATUAL
==================================================

Já existe em:

Configurações → Leads → WhatsApp da empresa

configuração contendo:

- habilitado/desabilitado
- número WhatsApp da empresa
- provider = waha
- session
- exemplo atual de sessão: vetoros1-1

As configurações são persistidas na tabela settings.

Já existe envio funcionando:

CRM
→ Laravel
→ n8n
→ WAHA
→ WhatsApp

NÃO alterar esse fluxo.

Também já existem workflows n8n para:
- envio
- message.ack/status
- mensagens recebidas

NÃO modificar n8n.
NÃO modificar webhooks WAHA existentes.
NÃO alterar credenciais.
NÃO alterar banco desnecessariamente.

==================================================
2. PROBLEMA ATUAL
==================================================

Hoje o painel pode informar um número configurado diferente
do número realmente autenticado na sessão WAHA.

Exemplo encontrado:

Número configurado no CRM:
5551998931325

Mas a sessão WAHA estava autenticada como:
555195179173@c.us

Portanto:

company_whatsapp_number

NÃO deve ser considerado prova de qual conta está conectada.

A fonte de verdade para a conexão deve ser:

GET /api/sessions/{session}

e principalmente:

status
me.id
me.pushName
me.lid

==================================================
3. NOVA FUNCIONALIDADE
==================================================

Na seção existente:

Configurações
→ Leads
→ WhatsApp da empresa

adicionar gerenciamento visual da conexão WAHA.

A interface deverá mostrar algo semelhante a:

WhatsApp da empresa

Número configurado:
(51) 99893-1325

Provider:
WAHA

Sessão:
vetoros1-1

Status da conexão:
[Conectado / Desconectado / Aguardando QR Code / Conectando / Erro]

Quando conectado:

Número autenticado:
(51) XXXXX-XXXX

Nome da conta:
<me.pushName>

Sessão:
vetoros1-1

Status:
Conectado

==================================================
4. BOTÃO CONECTAR WHATSAPP
==================================================

Quando não estiver conectado:

[ Conectar WhatsApp ]

O Laravel deve controlar a sessão WAHA pelo backend.

Nunca chamar WAHA diretamente pelo React/browser.

Fluxo esperado:

Frontend
    ↓
Laravel
    ↓
WAHA API

O backend deve:

1. consultar a sessão;
2. iniciar/reiniciar quando necessário;
3. aguardar/identificar SCAN_QR_CODE;
4. buscar o QR Code;
5. devolver o QR para o frontend de maneira segura.

Endpoint WAHA já validado:

GET /api/{session}/auth/qr

Esse endpoint retorna diretamente uma imagem PNG.

No ambiente atual foi validado como:

PNG
292 x 292
RGBA

Portanto NÃO interpretar a resposta como JSON.

O backend Laravel deve receber o PNG e repassá-lo corretamente
ao frontend, preferencialmente por uma rota autenticada.

==================================================
5. EXIBIÇÃO DO QR CODE
==================================================

Quando WAHA retornar:

status = SCAN_QR_CODE

mostrar o QR Code dentro de um Dialog/Modal.

Exemplo:

┌──────────────────────────────┐
│ Conectar WhatsApp            │
│                              │
│ Abra o WhatsApp no celular   │
│                              │
│      [ QR CODE ]             │
│                              │
│ WhatsApp                     │
│ → Aparelhos conectados       │
│ → Conectar aparelho          │
│                              │
│ Aguardando conexão...        │
└──────────────────────────────┘

Não salvar QR Code permanentemente.

QR é temporário e pode expirar.

Se expirar:
- informar ao usuário;
- permitir gerar/atualizar QR;
- não destruir configurações existentes.

==================================================
6. MONITORAMENTO DO STATUS
==================================================

Enquanto o modal estiver aberto, o frontend poderá consultar
periodicamente um endpoint Laravel de status.

Não consultar WAHA diretamente pelo browser.

Por exemplo:

GET /settings/leads/whatsapp/status

Laravel consulta:

GET /api/sessions/{session}

Mapear estados WAHA para estados amigáveis da UI.

Exemplo:

WORKING
→ Conectado

SCAN_QR_CODE
→ Aguardando leitura do QR Code

STARTING
→ Conectando

FAILED
→ Falha na conexão

STOPPED
→ Desconectado

Não assumir que essa lista é completa.
Tratar estados desconhecidos de forma segura.

Quando mudar para:

WORKING

fechar automaticamente o QR/modal ou atualizar a interface
para sucesso.

Depois consultar novamente a sessão e mostrar:

me.id
me.pushName

==================================================
7. VALIDAÇÃO DO NÚMERO
==================================================

Implementar comparação entre:

company_whatsapp_number

e

me.id retornado pelo WAHA.

Normalizar ambos antes da comparação.

Exemplo:

me.id:
5551998931325@c.us

normalizado:
5551998931325

Se forem iguais:

✓ Número conectado corretamente

Se forem diferentes:

ATENÇÃO

Número configurado:
(51) 99893-1325

Número conectado:
(51) XXXXX-XXXX

A sessão WAHA está conectada a um número diferente
do configurado para esta empresa.

Não corrigir automaticamente.
Não sobrescrever company_whatsapp_number.

O usuário deve decidir.

==================================================
8. DESCONEXÃO
==================================================

Quando estiver WORKING, disponibilizar:

[ Desconectar WhatsApp ]

Com confirmação antes da operação.

Laravel deverá chamar o logout da sessão WAHA.

Endpoint já utilizado com sucesso:

POST /api/sessions/{session}/logout

Depois atualizar o estado visual.

Não excluir a sessão.
Não apagar os webhooks.
Não apagar as configurações da empresa.

==================================================
9. SEGURANÇA
==================================================

A WAHA API possui autenticação por:

X-Api-Key

A chave NÃO pode:
- ir para React;
- aparecer no HTML;
- ser armazenada em settings;
- ser retornada pelas APIs do frontend;
- aparecer em logs.

Criar configuração Laravel via ENV/config.

Exemplo conceitual:

WAHA_BASE_URL=http://waha:3000
WAHA_API_KEY=...

Usar config/services.php ou estrutura equivalente já existente.

Nunca usar env() diretamente fora dos arquivos config.

Toda comunicação:

Laravel → WAHA

deve acontecer server-side.

Aplicar:
- autenticação
- autorização adequada
- timeout
- tratamento de erro
- validação do nome da sessão

Não permitir que uma sessão arbitrária enviada pelo browser
seja usada para consultar a WAHA API.

A sessão deve ser obtida das configurações da empresa.

==================================================
10. SERVICE
==================================================

Preferencialmente criar uma abstração como:

app/Services/WahaService.php

Responsabilidades:

getSession(string $session)
restartSession(string $session)
logoutSession(string $session)
getQrCode(string $session)

ou nomes equivalentes adequados ao padrão do projeto.

Não colocar chamadas HTTP WAHA diretamente no controller.

==================================================
11. CONTROLLER
==================================================

Criar controller específico ou adaptar a arquitetura existente.

Possíveis operações:

status
connect
qr
disconnect

Os nomes finais devem seguir o padrão atual do projeto.

O controller deve ser fino.

Regra de negócio e integração WAHA ficam no Service.

==================================================
12. UX
==================================================

Reutilizar os componentes e padrões visuais existentes.

Não redesenhar a página inteira.

A seção WhatsApp deve deixar clara a diferença entre:

CONFIGURAÇÃO

e

CONEXÃO REAL.

Exemplo:

Configuração
Número: (51) 99893-1325
Provider: WAHA
Sessão: vetoros1-1

Conexão
Status: ● Conectado
Conta: Anderson Brasil
Número conectado: (51) 99893-1325

ou:

Status: ⚠ Número divergente

==================================================
13. ARQUITETURA FUTURA
==================================================

Embora o ABrasil atualmente utilize uma única empresa,
não acoplar o WahaService à sessão:

vetoros1-1

Não colocar esse nome hardcoded.

A sessão deve vir da configuração da empresa.

Isso permitirá posteriormente aplicar a mesma arquitetura
ao VetorOS multi-tenant:

tenant A → sessão própria
tenant B → sessão própria
tenant C → sessão própria

Mas NÃO implementar multitenancy do VetorOS nesta tarefa.

Somente deixar a abstração preparada.

==================================================
14. TESTES
==================================================

Criar testes automatizados sem acessar o WAHA real.

Usar Http::fake().

Testar no mínimo:

1. leitura de sessão WORKING;
2. leitura de SCAN_QR_CODE;
3. leitura de FAILED;
4. obtenção de QR PNG;
5. restart;
6. logout;
7. erro HTTP WAHA;
8. timeout/falha de comunicação;
9. comparação número configurado x me.id;
10. número divergente;
11. usuário não autorizado;
12. API key nunca exposta na resposta;
13. sessão usada vem da configuração e não de parâmetro arbitrário do browser.

Executar testes dentro do container da aplicação.

IMPORTANTE:
Neste projeto, comandos PHP/Laravel/Artisan devem ser executados
dentro do container Docker da aplicação, nunca diretamente no host.

==================================================
15. NÃO FAZER
==================================================

NÃO:
- alterar workflows n8n;
- alterar webhooks existentes;
- alterar lógica de message.ack;
- alterar envio de mensagens já funcionando;
- recriar sessão WAHA sem necessidade;
- excluir sessão;
- alterar credenciais;
- expor WAHA_API_KEY;
- hardcodar vetoros1-1;
- adicionar dependências desnecessárias;
- criar migration se a estrutura atual de settings for suficiente;
- executar ações destrutivas no WhatsApp real durante os testes.

==================================================
16. ENTREGA
==================================================

Ao finalizar:

1. listar arquivos criados;
2. listar arquivos alterados;
3. explicar resumidamente a arquitetura implementada;
4. informar novas variáveis ENV necessárias;
5. informar se docker-compose precisa receber essas variáveis;
6. mostrar rotas adicionadas;
7. mostrar testes criados;
8. executar testes;
9. executar build do frontend;
10. NÃO conectar/desconectar automaticamente a sessão real;
11. NÃO fazer deploy de produção sem minha autorização.

Gerar um relatório final objetivo.

Objetivo final:

O administrador entra em:

Configurações → Leads → WhatsApp da empresa

e consegue:

Configurar número
→ Conectar WhatsApp
→ visualizar QR Code
→ escanear
→ acompanhar status
→ confirmar número realmente conectado
→ desconectar quando necessário

sem precisar acessar terminal, Docker ou painel WAHA.
