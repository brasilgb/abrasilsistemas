# ABRASIL-WEB-02 — Separação de responsabilidades e atendimento WhatsApp

Estamos reposicionando o site `abrasilsistemas.com.br` como portal empresarial e institucional da ABrasil Sistemas.

Existe também um projeto independente, VetorLeads, que será responsável por prospecção de empresas, gestão de leads, funil comercial e vendas.

**Não misturar as responsabilidades dos dois projetos.**

## 1. ABrasil Sistemas

O site institucional deverá concentrar:

- Apresentação da empresa.
- Sistemas próprios e soluções prontas.
- Desenvolvimento de sistemas sob medida.
- Criação de sites e landing pages.
- Desenvolvimento de aplicativos.
- Automação de processos.
- Integrações e APIs.
- Apresentação das tecnologias utilizadas.
- Formulário institucional de contato.
- Atendimento WhatsApp.

## 2. Atendimento WhatsApp

Auditar primeiro a implementação atual de leads e WhatsApp do projeto.

Preservar e simplificar o que já funciona, transformando essa área em um módulo de conversas e atendimento.

Funcionalidades desejadas:

- Caixa de entrada de conversas.
- Histórico persistente de mensagens.
- Envio e recebimento pelo WAHA.
- Resposta diretamente no painel.
- Atualização de mensagens em tempo real, quando viável.
- Identificação de contatos.
- Status de mensagens, conforme suporte da integração.
- Mensagens não lidas.
- Busca por contato.
- Identificação da página de origem, quando disponível.

O formulário de contato poderá criar um registro de solicitação simples, sem necessidade de um CRM comercial completo.

## 3. Infraestrutura

Reutilizar o WAHA existente no ecossistema `infra-abrasil`.

Não instalar uma nova instância WAHA apenas para o site.

Verificar sessões, webhooks, autenticação e integrações já configuradas.

Garantir que as conversas do atendimento institucional sejam isoladas das conversas dos demais projetos.

Não alterar configurações de produção sem validação.

## 4. VetorLeads

Toda funcionalidade de:

- Prospecção B2B.
- Captação avançada de leads.
- Campanhas.
- Funil de vendas.
- Follow-up comercial.
- Gestão de oportunidades.
- Automação de vendas.

Deve permanecer exclusivamente no projeto VetorLeads.

Não duplicar esses módulos no site ABrasil Sistemas.

## 5. Interface administrativa

Criar ou aproveitar uma área administrativa simples para o WhatsApp, com:

- Lista de conversas à esquerda.
- Mensagens no painel central.
- Informações do contato à direita, quando houver espaço.
- Campo de resposta.
- Indicadores de mensagens não lidas.

Utilizar uma interface limpa, moderna e responsiva.

## 6. Auditoria antes da implementação

Inspecionar a estrutura atual e informar:

1. O que já existe e funciona.
2. O que precisa ser mantido.
3. Quais funcionalidades de CRM/leads podem ser retiradas do site institucional sem perda de dados.
4. O que falta para permitir receber e responder mensagens.
5. Quais componentes e tabelas serão reutilizados.
6. Como manter a integração com o WAHA compartilhado.

Não excluir dados históricos de leads ou conversas.

Preparar um plano de alterações e implementar somente após a avaliação da auditoria.

Não executar push ou deploy sem autorização.