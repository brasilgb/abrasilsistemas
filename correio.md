# ABRASIL-SEO-FINAL-01 — Consolidação técnica e comercial do SEO

**Projeto:** ABrasil Sistemas
**Base:** ABRASIL-SEO-02 concluída em 21/10/2026
**Prioridade:** Alta

## Objetivo

Consolidar o sistema de SEO implementado na ABRASIL-SEO-02, corrigir pendências relevantes e incorporar SEO profissional ao portfólio comercial da ABrasil Sistemas.

Não reconstruir o sistema existente nem duplicar funcionalidades.

A empresa adotará duas modalidades comerciais:

1. SEO técnico essencial incluído em todos os novos projetos de sites, landing pages, portais e sistemas web.
2. SEO profissional contratado separadamente para otimização de sites e aplicações existentes, incluindo acompanhamento contínuo opcional.

---

## 1. Avaliação da implementação anterior

Ler o relatório ABRASIL-SEO-02 e auditar os arquivos efetivamente implementados.

Confirmar:

- Funcionamento das nove seções administrativas.
- Integridade das migrations e configurações.
- Sitemap e robots.txt dinâmicos.
- Metadados e canonical.
- Redirecionamentos.
- Auditoria interna.
- JSON-LD.
- Análise editorial.
- Integrações do Search Console, GA4 e Bing.
- Compatibilidade com a infraestrutura de produção existente.

Corrigir problemas reais encontrados sem reescrever módulos funcionais.

Revisar os avisos PHPStan referentes ao novo código, priorizando os que indiquem riscos efetivos.

## 2. SEO incluído no desenvolvimento

Atualizar a apresentação dos serviços da ABrasil para informar:

**"SEO técnico incluído em todos os nossos projetos web."**

Explicar que sites e sistemas públicos são desenvolvidos com:

- Estrutura HTML apropriada.
- Metadados individualizados.
- Sitemap XML.
- URLs amigáveis.
- Canonical.
- Dados estruturados pertinentes.
- Otimização técnica de carregamento.
- Configuração de indexabilidade.
- Compatibilidade com mecanismos de busca.

Nos sistemas web, aplicar indexação somente às páginas públicas relevantes.

Não permitir indexação de dashboards, informações comerciais privadas, dados de clientes ou áreas autenticadas.

Distinguir SEO técnico incluído de serviços contínuos de conteúdo, monitoramento e consultoria.

## 3. Criar o serviço SEO Profissional

Adicionar ao portfólio institucional:

**SEO Técnico, Performance e Otimização Digital**

Criar uma landing page com identidade visual profissional e tecnológica, compatível com a identidade atual da ABrasil.

A página deve apresentar três modalidades:

### SEO Essencial

Auditoria e implantação técnica para empresas que já possuem um site.

### SEO Profissional

Auditoria aprofundada, correções técnicas, dados estruturados, performance, otimização editorial e melhorias de indexabilidade.

### SEO Gerenciado

Acompanhamento recorrente de desempenho orgânico, recomendações, relatórios e melhorias contínuas.

Não estabelecer preços fixos.

Não prometer posições garantidas no Google.

## 4. Página comercial

A landing page deverá possuir:

- Hero com imagem profissional relacionada à tecnologia, SEO e análise de dados.
- Título e subtítulo comerciais.
- Benefícios empresariais.
- Detalhamento dos serviços.
- Explicação das modalidades.
- Etapas da contratação.
- Perguntas frequentes úteis.
- CTA "Solicitar análise SEO".
- Integração com o formulário de contato existente.
- Links para os serviços de desenvolvimento de software, sites e integração.

Garantir responsividade, acessibilidade e SEO completo da própria página.

Evitar seções repetitivas, excesso de cards e aparência genérica de template.

## 5. Integração comercial

O formulário de solicitação deve identificar o interesse do visitante como "SEO Profissional".

Utilizar o sistema de atendimento existente da ABrasil.

Preservar o registro da origem do contato, informações comerciais necessárias e consentimentos aplicáveis.

Não criar outro CRM ou sistema paralelo de leads.

## 6. Preparação para Google Search Console

Como a propriedade abrasilsistemas.com.br já foi cadastrada, verificar se o site está pronto para configuração e indexação após o deploy.

Validar tecnicamente:

- `/sitemap.xml`
- `/robots.txt`
- Canonical.
- Diretivas robots.
- Metadados.
- HTTPS.
- Redirecionamentos.
- Dados estruturados.
- Ausência de bloqueio indevido à indexação no domínio de produção.

Examinar cuidadosamente `SEO_BLOCK_INDEXING`, `APP_URL` e o domínio canônico para evitar colocar `noindex` na produção.

Preparar instruções para configurar o OAuth oficial do Search Console no Google Cloud.

Não inserir credenciais no código ou nos relatórios.

Não afirmar que a integração está ativa sem autorização e teste real.

## 7. Melhorias para o blog

Adicionar a categoria comercial/editorial SEO, se ainda não existir.

Preparar pautas para artigos como:

- Por que sua empresa precisa de SEO técnico?
- Como aparecer nas pesquisas do Google?
- SEO para sites empresariais: o que realmente importa?
- Como melhorar o desempenho e a indexação de um site?
- Qual a diferença entre SEO técnico e anúncios pagos?

Antes de criar novos artigos, verificar os existentes para evitar duplicidade.

Criar novos conteúdos como rascunho, sem publicação automática.

## 8. Política obrigatória de ambiente

**É expressamente proibido criar, iniciar, modificar ou reconstruir containers Docker ou Podman no ambiente local.**

Não executar Docker Compose localmente.

Não instalar serviços de infraestrutura para contornar problemas dos testes.

Não modificar a configuração dos containers de produção.

Utilizar os recursos e serviços locais já disponíveis.

Somente realizar alterações na VPS quando houver autorização específica.

## 9. Política obrigatória de testes

Executar testes adequados à stack Laravel, React/Inertia e banco de dados existentes.

Priorizar:

- PHPUnit/Pest.
- Testes de rotas e controladores.
- Testes HTTP.
- TypeScript.
- ESLint.
- PHPStan.
- Build.
- Validação de sitemap e JSON-LD.
- Testes de interface quando o navegador estiver funcional.

### Regra de interrupção

Se Playwright, navegador ou outra ferramenta apresentar falhas repetitivas de ambiente, fazer no máximo duas tentativas sem progresso.

Depois, mudar para outro método de validação funcional.

Não insistir indefinidamente em instalação de navegadores, bibliotecas ou reparos do ambiente.

Falhas reais da aplicação devem ser corrigidas, não ignoradas.

Testes HTTP ou builds não substituem a validação visual. Caso esta seja impossível, registrar a pendência.

Não criar containers temporários para executar testes.

## 10. Segurança

- Proteger rotas administrativas.
- Validar entradas e URLs.
- Evitar XSS e SSRF.
- Proteger tokens e credenciais.
- Respeitar LGPD e consentimento de cookies.
- Verificar redirects para evitar loops.
- Não introduzir indexação de ambientes privados.
- Preservar os dados e URLs existentes.

## 11. Entrega e relatório

Ao concluir, atualizar `executed.md` com:

1. Resumo da implementação.
2. Funcionalidades já existentes e reaproveitadas.
3. Correções executadas.
4. Novas páginas e serviços.
5. Arquivos modificados.
6. Migrations, caso necessárias.
7. Testes realizados e resultados.
8. Testes não executados e motivos.
9. Pendências de credenciais externas.
10. Instruções para deploy e Search Console.
11. Riscos ou limitações conhecidos.

Não executar commit, push ou deploy sem autorização.

**Critério final:** SEO interno consolidado, novo serviço comercial disponível no código, SEO técnico apresentado como diferencial dos projetos web e sistema preparado para publicação segura na VPS.
