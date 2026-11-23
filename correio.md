# ABRASIL-COMERCIAL-03 — Publicação e ativação comercial

**Projeto:** ABrasilSistemas  
**Domínio:** abrasilsistemas.com.br  
**Ambiente:** VPS Hostinger KVM 4

## Objetivo

Publicar as melhorias das etapas COMERCIAL-01 e COMERCIAL-02, validar o funcionamento real e preparar o site para divulgação e captação de clientes.

## 1. Preparação

- Conferir Git e alterações locais.
- Consolidar exclusivamente os arquivos relacionados à ABrasilSistemas.
- Inspecionar `docs/publicacao.md`.
- Verificar migrations pendentes e compatibilidade com o banco de produção.
- Verificar backup do banco e possibilidade de reversão.
- Conferir a configuração real de `APP_ENV`, sem alterar a variável compartilhada de outros produtos.
- Verificar SMTP, filas e workers.

Não apagar dados existentes.

## 2. Publicação

**Autorizada a publicação controlada da ABrasilSistemas**, desde que o backup e as verificações críticas sejam concluídos satisfatoriamente.

- Realizar commit e push exclusivamente das alterações pertinentes.
- Atualizar a aplicação na VPS pelo procedimento existente.
- Não reconstruir nem reiniciar outros produtos desnecessariamente.
- Executar as migrations da ABrasilSistemas no momento correto.
- Compilar e publicar os assets, incluindo o HTML pré-renderizado.
- Preservar dados e configurações existentes.

Se houver falha crítica, interromper a publicação e documentar o motivo.

## 3. Configuração nginx

Conferir a configuração atual do domínio e habilitar, quando apropriado:

- Compressão gzip para HTML, CSS e JavaScript.
- Cache longo para assets versionados.
- Cache conservador para HTML.
- Headers apropriados.
- HTTPS e redirecionamentos.

Não alterar configurações globais que afetem os outros sistemas.

## 4. Homologação real

Verificar no domínio público:

- Página inicial.
- Páginas de serviços.
- Produtos Vetor.
- Contato.
- Blog.
- Formulários comerciais.
- Painel de Solicitações.
- Sitemap.
- Robots.txt.
- Canonical.
- Dados estruturados.
- Conteúdo visível sem JavaScript.
- Versão para celular.
- Widget VetorChat.

Realizar um envio comercial de teste identificado como homologação, verificando gravação, notificações e entrega pelo SMTP.

Não gerar solicitações repetitivas desnecessárias.

## 5. SEO e informações institucionais

Revisar configurações de SEO armazenadas em produção.

Verificar se prevalecem sobre os metadados atualizados.

Conferir o comportamento do pré-render quando informações editáveis são alteradas.

Não preencher CNPJ, razão social, endereço ou horários com informações presumidas.

Preparar o preenchimento manual dos dados institucionais.

## 6. Encerramento

Registrar em `executed.md`:

- Commits e versão publicada.
- Resultado do backup.
- Migrations aplicadas.
- Ajustes realizados.
- Verificações de produção.
- Pendências reais.
- Situação final da publicação.

Evitar repetir toda a suíte de testes já aprovada. Priorizar homologação funcional na VPS.

**Critério de aceite:** ABrasilSistemas publicada, acessível, com formulários funcionando, notificações verificadas e páginas preparadas para indexação.
