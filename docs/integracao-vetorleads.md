# Contrato: envio de solicitações do site para o VetorLeads

Proposta para a integração futura (ABRASIL-COMERCIAL-02 §6). **Nada disto está implementado** e o
VetorLeads não foi alterado. O formulário do site continua independente: a solicitação é gravada aqui,
a equipe é avisada por e-mail e só depois, em segundo plano, haveria o envio.

## Por que um endpoint novo

| Entrada atual do VetorLeads | Por que não serve |
|---|---|
| `POST /api/prospects/import` | É da extensão AB Prospect: cria prospects de prospecção, não contatos recebidos |
| `POST /api/widget/{chave}/…` e `/api/web-chat/…` | São conversas do chat; a chave do widget é pública e não autoriza gravação de leads |
| `POST /api/integrations/sales/events` | Eventos do VetorSales sobre leads já existentes; não cria leads |

## Endpoint

`POST https://vetorleads.com.br/api/integrations/site-leads`

| Cabeçalho | Valor |
|---|---|
| `Authorization` | `Bearer <token do tenant>` |
| `Idempotency-Key` | UUID do envio, o mesmo em todas as tentativas |
| `Content-Type` / `Accept` | `application/json` |

### Corpo

```json
{
  "external_id": "abrasil-site:1234",
  "event": "request_created",
  "occurred_at": "2026-11-23T14:05:00Z",
  "contact": {
    "name": "Nome informado",
    "company": null,
    "email": "pessoa@empresa.com.br",
    "phone": "5551999999999"
  },
  "interest": { "code": "automacao", "label": "Automação" },
  "message": "Texto enviado pela pessoa",
  "origin": {
    "source_page": "/automacao-de-processos",
    "entry_page": "/",
    "referrer_host": "google.com",
    "utm_source": null,
    "utm_medium": null,
    "utm_campaign": null,
    "utm_term": null,
    "utm_content": null
  },
  "marketing_consent_at": null
}
```

| Campo | Regra |
|---|---|
| `external_id` | Obrigatório, até 100 caracteres: `abrasil-site:{id da solicitação}`. Único **por tenant** |
| `event` | `request_created` (nova solicitação) ou `contact_added` (novo contato na mesma solicitação) |
| `occurred_at` | ISO 8601 em UTC |
| `contact.name` | Obrigatório, até 255 |
| `contact.email`, `contact.phone` | Ao menos um. Telefone só com dígitos, 10 a 15 |
| `interest.code` | Chaves de `Lead::CONTACT_PROJECT_TYPES`; código desconhecido é aceito como texto |
| `message` | Obrigatória, até 5.000 |
| `origin.*` | Opcionais. Vazio quer dizer não identificada; o receptor não deduz nada |
| `marketing_consent_at` | Nulo = sem consentimento para comunicações de marketing |

Não são enviados: observações internas, histórico, situação do painel, IP ou dados de outras solicitações.

### Respostas

| HTTP | Significado | O que o site faz |
|---|---|---|
| 201 `{"status":"created","lead_id":…}` | Lead criado | Marca como enviado |
| 200 `{"status":"duplicate","lead_id":…}` | Envio já recebido | Marca como enviado |
| 200 `{"status":"updated","lead_id":…}` | `contact_added` registrado no lead | Marca como enviado |
| 401 / 403 | Token inválido, revogado ou tenant inativo | Para de tentar e alerta a equipe |
| 409 | Mesma chave de idempotência com outro conteúdo | Não repete; registra erro |
| 422 | Formato inválido | Não repete; registra o campo recusado, sem o valor |
| 429 / 5xx / sem resposta | Temporário | Nova tentativa, respeitando `Retry-After` |

## Autenticação e isolamento entre tenants

- Um token por tenant, só para esta finalidade. O modelo já existe no VetorLeads (`TenantApiToken`:
  64 caracteres hexadecimais, só o SHA-256 gravado, validade e revogação). Precisa ganhar **escopo**
  (`site-leads:write`): o token da extensão não deve gravar aqui, nem este importar prospects.
- O tenant vem **somente** do token. Identificador de tenant, conta, usuário ou lead no corpo é ignorado.
- `external_id`, idempotência e busca de duplicados são sempre filtrados pelo tenant, com índice único
  composto `(tenant_id, external_id, event, idempotency_key)` para envios e `(tenant_id, external_id)`
  para o lead.
- Um `lead_id` de outro tenant nunca é devolvido.
- O token fica só no `.env` do servidor do site e nunca vai para o navegador, log ou repositório.
- Limite sugerido: 60 requisições por minuto por token.

## Idempotência e deduplicação

1. O receptor guarda a chave e o hash do corpo por 30 dias, no mínimo.
2. Chave conhecida com o mesmo corpo devolve `duplicate` sem efeito; com corpo diferente, 409.
3. `request_created` com `external_id` já existente também é `duplicate`.
4. E-mail ou telefone iguais aos de outro lead **não** autorizam sobrescrever: o receptor cria o lead e
   indica a possível relação, como o site já faz. O formulário é público e não prova identidade.
5. `do_not_contact_at` do VetorLeads prevalece: um novo contato não remove a oposição registrada.

## Falhas no lado do site

- Envio por job na fila, disparado após o commit. Nunca no tempo da requisição do visitante.
- Tentativas após 1 min, 5 min, 30 min, 2 h e 12 h; tempo limite de 10 s. Depois disso fica como falha, com
  reenvio manual no painel.
- Controle numa tabela própria (`site_lead_deliveries`: solicitação, evento, chave, situação, tentativas,
  último código HTTP, enviado em), sem o corpo nem o token.
- Desligado por padrão (`VETORLEADS_SITE_LEADS_ENABLED=false`). Desligar ou perder o VetorLeads não muda
  nada no formulário, no painel ou no e-mail da equipe.
- Solicitações anteriores à ativação só são enviadas por comando explícito.

## Antes de ativar

1. VetorLeads: endpoint, escopo do token, índices únicos e testes de isolamento entre dois tenants.
2. Política de Privacidade: incluir o VetorLeads entre os sistemas que tratam o contato.
3. Homologação com tenant de teste: criação, repetição, 409, 401, queda e retorno do serviço.
4. Definir qual painel é o principal, para a equipe não acompanhar a mesma negociação em dois lugares.
