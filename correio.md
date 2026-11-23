# ABRASIL-COMERCIAL-02 — Conversão, SEO e preparação para divulgação

**Projeto:** ABrasilSistemas  
**Diretório:** localizar o projeto existente na infraestrutura Laravel.  
**Objetivo:** concluir os ajustes comerciais e técnicos necessários para iniciar a captação de clientes.

## 1. Consolidar a etapa anterior

Verificar a implementação da ABRASIL-COMERCIAL-01.

Preservar os recursos entregues e não refazer telas ou componentes já funcionais.

Corrigir apenas problemas comprovados.

## 2. Revisão da captação comercial

Auditar o fluxo de Solicitações:

- Impedir acesso não autorizado às solicitações existentes.
- Garantir que a deduplicação não exponha nem sobrescreva dados de terceiros.
- Preservar a primeira origem comercial de forma confiável.
- Manter e-mail OU telefone obrigatório.
- Confirmar que o cliente recebe feedback após enviar a solicitação.
- Melhorar o painel comercial com filtros de origem, serviço, data e situação.
- Permitir acompanhamento de novos contatos, atendimento, proposta enviada, negociação, ganho e perdido.
- Registrar observações e histórico das alterações comerciais.

Não criar um segundo CRM caso o painel existente já atenda às necessidades.

## 3. Melhorias de conversão

Revisar as chamadas comerciais das páginas.

Cada página deve apresentar claramente:

- Problema que o serviço resolve.
- Benefícios concretos.
- Solução oferecida.
- Processo de contratação.
- Chamada para solicitar orçamento.

Evitar promessas genéricas ou resultados não comprovados.

Garantir uma experiência especialmente boa em dispositivos móveis.

## 4. SEO e indexação

Auditar a renderização efetiva do conteúdo no HTML inicial.

Identificar quais textos são acessíveis sem execução de JavaScript.

Pesquisar uma solução de prerenderização compatível com Laravel e a infraestrutura atual, sem exigir um processo Node permanente, se tecnicamente viável.

Não instalar SSR ou serviços adicionais sem demonstrar necessidade.

Revisar:

- Sitemap e indexação.
- Canonical.
- Metadados.
- Dados estruturados.
- Conteúdo principal.
- Links internos.
- SEO regional.
- Desempenho das páginas públicas.

Não inventar endereço, avaliações, CNPJ ou informações institucionais.

## 5. SEO regional e institucional

Preparar os campos administrativos necessários para:

- Nome comercial.
- Razão social, quando aplicável.
- CNPJ.
- Município e UF.
- Região atendida.
- E-mail de contato.
- Telefone comercial.
- Horário de atendimento, quando informado.

Distinguir localização física de área de atendimento.

Não apresentar endereço residencial ou privado como endereço comercial público.

## 6. Integração futura com VetorLeads

Documentar o contrato necessário para um endpoint autenticado de recebimento de leads.

Definir estrutura de dados, autenticação, idempotência, tratamento de falhas e isolamento entre tenants.

Não modificar o VetorLeads nesta execução.

O funcionamento do formulário da ABrasilSistemas deve permanecer independente dessa integração.

## 7. Preparação da publicação

Conferir:

- Migrations pendentes.
- Assets compilados.
- Configurações de produção.
- SEO armazenado no banco.
- Funcionamento dos formulários.
- Configuração de e-mail.
- Backup e possibilidade de reversão.

Não executar deploy nesta etapa.

## 8. Regras

- Trabalhar apenas no projeto ABrasilSistemas.
- Não criar containers locais.
- Não alterar outros sistemas.
- Não adicionar dependências desnecessárias.
- Executar somente testes pertinentes às alterações.
- Não insistir em testes de navegador que falhem por limitações do ambiente.
- Não repetir toda a suíte de testes sem necessidade.
- Priorizar implementação funcional em vez de auditorias repetitivas.
- Não efetuar commit, push ou deploy sem autorização.
- Registrar resultados e pendências em `executed.md`.

**Critério de conclusão:** site preparado tecnicamente para uma publicação controlada e início da divulgação comercial.
