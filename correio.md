Estamos integrando o CRM ABrasil Sistemas (Laravel) com n8n + WAHA para envio de WhatsApp.

Analise primeiro o código existente e implemente a integração respeitando a arquitetura atual. Não reestruture partes não relacionadas e não altere comportamentos existentes desnecessariamente.

CONTEXTO JÁ CONFIRMADO

Existe:

- Model App\Models\Lead
- Model App\Models\LeadActivity
- Lead possui relacionamento activities()
- LeadActivity possui:
  - lead_id
  - user_id nullable
  - type
  - status
  - contacted_at
  - next_follow_up_at
  - description

LeadActivity::TYPES já contém:

'whatsapp' => 'WhatsApp'

IMPORTANTE:

O campo `status` de lead_activities representa o STATUS COMERCIAL do Lead e é validado contra Lead::STATUSES.

Portanto:

NÃO usar esse campo para armazenar status técnico do WAHA como:
PENDING
SENT
DELIVERED
READ
FAILED

Também já existe API:

POST /api/prospects/import

protegida pelo middleware:

prospect.token

e pelo throttle.

Existe:

App\Http\Middleware\EnsureValidProspectToken

que obtém o token de configuração/Setting.

Reutilize o mecanismo de autenticação existente para a nova API destinada ao n8n.

OBJETIVO

Quando o CRM solicitar envio de WhatsApp:

CRM
  -> n8n
  -> valida WhatsApp
  -> WAHA
  -> WAHA retorna resultado
  -> n8n chama CRM
  -> CRM registra o envio no histórico do Lead

O n8n deverá poder enviar ao CRM aproximadamente:

{
    "prospect_id": 1,
    "message_id": "3EB0F27EBBA9D67C6210DD",
    "status": "PENDING",
    "message": "Teste completo CRM → n8n → WAHA."
}

A nomenclatura externa ainda utiliza `prospect_id`, porém internamente o CRM utiliza Lead. Preserve essa compatibilidade na API.

TAREFA 1 — MIGRATION

Criar migration para adicionar em `lead_activities`:

provider_message_id
message_status

Escolha tipos, nullable, tamanho e índices adequados.

Não altere o significado do campo `status` existente.

A migration deve possuir down() correto.

TAREFA 2 — MODEL

Atualizar LeadActivity para permitir os novos campos:

provider_message_id
message_status

Preservar todos os campos e casts existentes.

Não transformar os status técnicos em Lead::STATUSES.

TAREFA 3 — ENDPOINT API

Criar endpoint específico para o retorno do n8n.

Sugestão conceitual:

POST /api/prospects/{lead}/whatsapp/log

Pode ajustar a URI/nomenclatura se a arquitetura atual indicar uma opção melhor, mas mantenha simples e RESTful.

A rota deve:

- estar em routes/api.php;
- reutilizar o middleware `prospect.token`;
- possuir throttle apropriado;
- usar route model binding quando adequado;
- retornar JSON;
- não depender de sessão/Inertia;
- não exigir usuário autenticado do painel.

TAREFA 4 — VALIDAÇÃO

Criar FormRequest ou mecanismo equivalente seguindo o padrão atual do projeto.

Validar:

message_id:
- nullable/string
- tamanho adequado

status:
- obrigatório
- string
- NÃO validar contra Lead::STATUSES

message:
- obrigatório
- string
- tamanho razoável

Se houver outros dados realmente necessários, justificar antes de adicioná-los.

TAREFA 5 — REGISTRO

Ao receber o retorno válido do n8n, registrar:

lead_id = lead recebido pela rota
user_id = null
type = whatsapp
status = null
contacted_at = now()
description = conteúdo de `message`
provider_message_id = `message_id`
message_status = status retornado pelo WAHA

IMPORTANTE:

Não alterar o status comercial do Lead simplesmente porque uma mensagem WhatsApp foi enviada.

Atualizar `last_contacted_at` do Lead somente se isso for coerente com o comportamento atual do sistema.

Analise o código existente antes de decidir e mantenha consistência com LeadActivityController.

TAREFA 6 — IDEMPOTÊNCIA

Evitar registrar a mesma mensagem duas vezes caso o n8n repita a requisição.

Quando `provider_message_id` estiver preenchido, verificar se já existe uma LeadActivity com o mesmo provider_message_id para esse Lead.

Se já existir, retornar sucesso/idempotência sem criar outra atividade.

Não provocar erro 500 em retry legítimo do n8n.

TAREFA 7 — RESPOSTA

Em sucesso retornar JSON semelhante a:

{
    "success": true,
    "message": "Envio de WhatsApp registrado.",
    "activity_id": 123
}

Utilizar status HTTP apropriado.

Para uma repetição idempotente, retornar uma resposta de sucesso clara.

TAREFA 8 — TESTES

Criar testes Feature cobrindo pelo menos:

1. endpoint rejeita requisição sem token válido;
2. endpoint rejeita payload inválido;
3. lead inexistente retorna resposta adequada;
4. registra atividade WhatsApp corretamente;
5. `type` fica `whatsapp`;
6. `user_id` fica null;
7. `status` comercial fica null;
8. `provider_message_id` é armazenado;
9. `message_status` recebe PENDING;
10. description recebe a mensagem;
11. contacted_at é preenchido;
12. requisição repetida com mesmo provider_message_id não duplica atividade;
13. envio não altera indevidamente o status comercial do Lead.

Verifique como os testes existentes configuram `prospect.token` e siga o mesmo padrão.

IMPORTANTE SOBRE EXECUÇÃO

Não fique preso tentando executar testes caso o ambiente/container não permita.

Implemente primeiro.

Depois informe exatamente os comandos que EU devo executar manualmente, por exemplo:

php artisan migrate
php artisan test --compact <arquivo-do-teste>

Se conseguir executar testes normalmente, execute os testes específicos desta implementação.

Não execute alterações destrutivas no banco.

NÃO executar:

migrate:fresh
db:wipe
reset
rollback global
seed destrutivo

TAREFA 9 — ENTREGA

Ao terminar, apresente:

1. arquivos criados;
2. arquivos alterados;
3. rota criada;
4. formato exato do JSON esperado;
5. header/token necessário;
6. exemplo curl completo;
7. resposta esperada;
8. comandos de migration;
9. comandos de teste;
10. qualquer decisão arquitetural tomada.

Não implemente ainda webhook de confirmação SENT/DELIVERED/READ do WAHA.

Nesta etapa queremos somente fechar:

CRM -> n8n -> WAHA -> retorno do n8n -> histórico do CRM.

O webhook assíncrono do WAHA será uma próxima etapa.
