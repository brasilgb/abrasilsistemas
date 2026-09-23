# Execução de `correio.md`

## Implementação concluída

Foi criada a integração para o retorno do n8n/WAHA ao CRM. O status técnico do provedor fica em `message_status`; o campo comercial `lead_activities.status` permanece `null` e o status comercial do Lead não é alterado.

## Arquivos criados

- `database/migrations/2026_09_23_120000_add_whatsapp_provider_fields_to_lead_activities_table.php`
- `app/Http/Controllers/Api/WhatsappActivityController.php`
- `app/Http/Requests/Api/LogWhatsappActivityRequest.php`
- `tests/Feature/WhatsappActivityApiTest.php`

## Arquivos alterados

- `app/Models/LeadActivity.php`: adicionados `provider_message_id` e `message_status` aos campos preenchíveis.
- `routes/api.php`: adicionada a rota de registro do retorno WhatsApp.
- `executed.md`: este relatório.

## Rota criada

```text
POST /api/prospects/{lead}/whatsapp/log
```

A rota usa route model binding, `prospect.token` e `throttle:30,1`. O token deve ser enviado como Bearer token, usando o mesmo mecanismo da API de importação.

## JSON esperado

Requisição:

```json
{
  "message_id": "3EB0F27EBBA9D67C6210DD",
  "status": "PENDING",
  "message": "Teste completo CRM → n8n → WAHA."
}
```

Resposta de novo registro (`201`):

```json
{
  "success": true,
  "message": "Envio de WhatsApp registrado.",
  "activity_id": 123
}
```

Uma repetição com o mesmo `message_id` para o mesmo Lead retorna `200`, não cria outra atividade e inclui `idempotent: true`.

## Comandos para executar manualmente

```bash
php artisan migrate
php artisan test --compact tests/Feature/WhatsappActivityApiTest.php
```

## Verificações executadas

```text
php artisan test --compact tests/Feature/ProspectImportApiTest.php tests/Feature/WhatsappActivityApiTest.php
8 testes passaram, 41 asserções.
```

Também foi executado o Pint em modo de verificação e `git diff --check`, ambos sem problemas.
