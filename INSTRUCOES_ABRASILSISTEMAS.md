# Instrucoes do abrasilsistemas

## Estado atual

O projeto `abrasilsistemas` ja recebeu melhorias no CRM/prospeccao:

- Dashboard comercial real em `/dashboard`.
- Prospecao com abas `Leads`, `Tarefas`, `Funil` e `Metricas`.
- Fila de tarefas com leads atrasados, para hoje e sem follow-up.
- Kanban por status do funil.
- Score automatico e prioridade de lead.
- Motivo de perda quando o lead e marcado como perdido.
- Deduplicacao de leads por e-mail, WhatsApp ou empresa + cidade + UF.
- Importacao CSV ignora duplicados.
- Templates padrao para documentos de equipamento:
  - entrada de equipamento
  - entrega de equipamento / garantia
  - orcamento inicial

## Arquivos principais alterados

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/LeadController.php`
- `app/Http/Controllers/LeadActivityController.php`
- `app/Http/Requests/Leads/LeadRequest.php`
- `app/Http/Requests/Leads/LeadActivityRequest.php`
- `app/Models/Lead.php`
- `app/Models/LeadActivity.php`
- `database/factories/LeadFactory.php`
- `database/migrations/2026_07_18_000000_add_lost_reason_to_leads_table.php`
- `resources/js/lib/equipment-receipt-template.ts`
- `resources/js/pages/dashboard.tsx`
- `resources/js/pages/leads/index.tsx`
- `resources/js/pages/leads/form.tsx`
- `resources/js/pages/leads/create.tsx`
- `resources/js/pages/leads/edit.tsx`
- `resources/js/pages/settings/leads.tsx`
- `routes/web.php`
- `tests/Feature/LeadManagementTest.php`

## O que ainda falta fazer

### 1. Persistir templates no banco

Hoje os templates padrao ficam em:

```text
resources/js/lib/equipment-receipt-template.ts
```

Quando o usuario edita pela tela, o texto e salvo no `localStorage` do navegador.

Proximo passo recomendado:

- Criar migration `document_templates`.
- Salvar templates no banco.
- Carregar via Inertia props.
- Salvar via controller Laravel.

Sugestao de campos:

```text
id
key
name
content
created_at
updated_at
```

Chaves sugeridas:

```text
equipment_receipt
equipment_delivery
equipment_quote
```

### 2. Renomear configuracoes

Hoje os templates ficam em:

```text
Configurações > Leads
```

Como os documentos sao de O.S./equipamento, o ideal e criar uma area propria:

```text
Configurações > Documentos
```

ou:

```text
Configurações > O.S.
```

### 3. Criar modulo de O.S.

O projeto ainda nao possui modulo real de assistencia tecnica. O CRM existe, mas ainda falta a operacao de O.S.

Entidades recomendadas:

- clientes
- equipamentos
- ordens de servico
- itens/servicos do orcamento
- historico da O.S.
- anexos/fotos
- status da O.S.

Status sugeridos:

```text
entrada
em_analise
orcamento_enviado
orcamento_aprovado
em_reparo
pronto
entregue
cancelado
```

### 4. Gerar documentos a partir da O.S.

Depois do modulo de O.S., usar os templates para gerar:

- recibo de entrada
- orcamento
- recibo de entrega / garantia

Primeiro pode ser uma tela de impressao HTML. Depois, se necessario, gerar PDF.

### 5. Adicionar mais variaveis aos templates

Hoje existem:

```text
{{nome_cliente}}
{{cpf_cnpj_cliente}}
```

Variaveis recomendadas:

```text
{{numero_os}}
{{equipamento}}
{{marca}}
{{modelo}}
{{serial}}
{{imei}}
{{defeito_relatado}}
{{diagnostico}}
{{valor_orcamento}}
{{prazo_estimado}}
{{data_entrada}}
{{data_entrega}}
{{nome_empresa}}
{{cnpj_empresa}}
```

### 6. Revisar textos finais

Os textos foram salvos como padrao conforme informado durante o desenvolvimento.

Antes de usar em producao, revisar:

- acentos e redacao final
- nome juridico da empresa
- CNPJ/endereco, se necessario
- termos de garantia
- validade juridica dos textos

### 7. Corrigir dividas de lint/analise global

As validacoes focadas dos arquivos alterados estavam passando.

Comandos que passaram durante o desenvolvimento:

```bash
php artisan test
npm run types:check
npm run build
```

Tambem foi validado PHPStan nos arquivos tocados com:

```bash
vendor/bin/phpstan analyse --memory-limit=1G app/Models/Lead.php database/factories/LeadFactory.php app/Http/Controllers/LeadController.php
```

Ainda existem dividas antigas fora do escopo, vistas anteriormente em:

- `app/Http/Controllers/UserController.php`
- `app/Http/Requests/Users/StoreUserRequest.php`
- `app/Http/Requests/Users/UpdateUserRequest.php`
- `database/seeders/DatabaseSeeder.php`
- alguns arquivos React antigos quando rodado `npm run lint:check` global

### 8. Revisar assets do build

O comando `npm run build` atualiza arquivos em:

```text
public/build
```

Antes de commitar, decidir se o projeto deve versionar esses assets gerados.

Se o projeto versiona `public/build`, incluir os novos arquivos e remocoes.
Se nao versiona, ajustar `.gitignore` ou evitar commitar essas alteracoes.

## Comandos uteis ao voltar

```bash
php artisan test
npm run types:check
npm run build
git status --short
```

Se quiser rodar servidor local:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

URL:

```text
http://127.0.0.1:8001
```

