Execute a etapa **ABRASIL-WEB-03 — Consolidação do site institucional e separação definitiva do CRM/VetorLeads**.

Use como base o estado atual após ABRASIL-WEB-01 e ABRASIL-WEB-02.

O objetivo desta etapa é transformar definitivamente `abrasilsistemas.com.br` em um site institucional e de atendimento da ABrasil Sistemas, retirando dele as responsabilidades comerciais que agora pertencem ao VetorLeads.

Não apagar dados existentes.

## 1. Arquitetura definitiva

O site ABrasil Sistemas deve manter apenas:

- site institucional;
- páginas de serviços;
- páginas dos sistemas;
- formulário de contato;
- solicitações recebidas pelo site;
- atendimento WhatsApp;
- configurações do WhatsApp institucional;
- blog;
- portfólio;
- depoimentos;
- administração dessas áreas.

O VetorLeads passa a ser responsável por:

- prospecção;
- extensão AB Prospect;
- importação de prospects;
- Kanban;
- funil comercial;
- scoring;
- tarefas;
- follow-up;
- disparos;
- campanhas;
- templates comerciais;
- automações comerciais;
- gestão completa de leads/prospects.

Não duplicar essas funções entre os dois sistemas.

## 2. Remover o CRM visual do abrasilsistemas

Retirar do frontend, menus, rotas e controllers institucionais:

- dashboard comercial;
- Kanban;
- funil;
- scoring;
- tarefas comerciais;
- follow-up;
- importação CSV de prospects;
- importação da extensão;
- ações em massa;
- templates comerciais de WhatsApp;
- envio de WhatsApp específico do CRM;
- download/distribuição da extensão AB Prospect.

Antes de remover endpoints usados pelo n8n ou pela extensão, auditar as referências.

Se ainda houver integração externa apontando para o site antigo, documentar exatamente qual é e não quebrá-la nesta etapa.

Não excluir registros das tabelas `leads` ou `lead_activities`.

## 3. Criar área "Solicitações"

A antiga área `/leads` não deve continuar sendo um CRM.

Criar ou transformar em uma área simples chamada:

**Solicitações**

Ela deve mostrar exclusivamente contatos originados pelo site institucional, especialmente `source=site`.

Campos principais:

- data;
- nome;
- empresa;
- telefone;
- WhatsApp;
- e-mail;
- tipo de projeto;
- mensagem;
- página/origem;
- situação da solicitação;
- vínculo com conversa WhatsApp, quando existir.

Pode permitir:

- visualizar;
- marcar como nova;
- em atendimento;
- respondida;
- concluída;
- arquivar.

Não implementar funil comercial complexo nessa área.

Caso seja tecnicamente melhor, mantenha as tabelas atuais e apenas simplifique a utilização delas. Não criar migration desnecessária.

## 4. Atendimento como página inicial administrativa

Depois do login administrativo, priorizar:

`/atendimento`

A navegação administrativa principal deve ficar aproximadamente:

- Atendimento
- Solicitações
- Blog
- Portfólio
- Depoimentos
- Configurações

Remover itens relacionados à prospecção e CRM.

## 5. WhatsApp institucional separado

Não utilizar a sessão comercial do VetorLeads como arquitetura definitiva.

Preparar o sistema para usar uma sessão dedicada, sugerida:

`abrasil-atendimento`

Não criar nem alterar a sessão real do WAHA nesta etapa caso isso envolva produção.

O sistema deve continuar lendo o nome da sessão pelas configurações da empresa, nunca hardcoded.

O mesmo container WAHA continuará compartilhado entre os sistemas, mas cada aplicação deve usar sua própria sessão.

Arquitetura desejada:

WhatsApp institucional
→ sessão `abrasil-atendimento`
→ webhook direto WAHA
→ `/api/whatsapp/waha/webhook`
→ `whatsapp_conversations`
→ `whatsapp_messages`
→ `/atendimento`

Resposta:

`/atendimento`
→ `WahaService`
→ WAHA
→ WhatsApp do cliente.

Para esse atendimento não utilizar n8n.

## 6. Configurações

A antiga seção "Configurações → Leads" deve ser revisada.

Como o site não será mais CRM, renomear adequadamente para algo como:

**Configurações → WhatsApp**

ou

**Configurações → Atendimento**

Manter:

- número;
- provider;
- sessão;
- status;
- QR Code;
- conectar;
- desconectar.

Remover referências visuais a "Leads" onde não fizer mais sentido.

Não expor API key do WAHA no navegador.

## 7. Webhooks antigos do n8n

Auditar:

- `CRM ABrasil - Enviar WhatsApp`
- log de envio;
- ACK;
- `WAHA - Mensagens Recebidas → CRM`;
- qualquer workflow apontando para `abrasilsistemas.com.br`.

Também conferir referências da extensão AB Prospect.

Produzir uma tabela:

| integração | origem | destino atual | ainda usada? | destino correto |

Se o destino correto for VetorLeads, registrar a necessidade de mudança.

Não alterar workflow de produção sem necessidade nesta etapa.

## 8. Código legado

Depois de confirmar que determinada implementação não possui mais dependentes:

remover código morto relacionado ao CRM institucional.

Mas não remover tabelas ou dados históricos.

Pode permanecer temporariamente estrutura de banco legada se isso reduzir risco.

O objetivo é limpar aplicação e interface, não fazer uma grande migration destrutiva.

## 9. Site institucional

Preservar integralmente o trabalho aprovado da ABRASIL-WEB-01:

- home empresarial;
- serviços;
- sistemas prontos;
- páginas próprias de serviços;
- SEO;
- navegação;
- identidade visual;
- formulários;
- blog;
- portfólio;
- depoimentos.

Não reescrever novamente o site institucional.

Apenas corrigir inconsistências encontradas durante a consolidação.

## 10. Pontos adicionais da auditoria

Avaliar e, se forem mudanças locais seguras, corrigir:

- menu/cabeçalho antigo do blog;
- página 404 ainda no visual antigo;
- referências antigas a CRM/Leads no painel;
- nomes de menu que não representam mais a função atual.

Não criar CMS novo.

## 11. Testes

Executar:

- suíte Laravel completa;
- testes do atendimento;
- testes das solicitações;
- testes de autorização;
- `npm run build`;
- TypeScript;
- Pint;
- ESLint/Prettier nos arquivos alterados.

Verificar especialmente:

1. visitante não acessa administração;
2. usuário sem permissão não acessa atendimento;
3. somente solicitações do site aparecem na área de solicitações;
4. histórico não foi apagado;
5. `/atendimento` continua funcionando;
6. sessão diferente não consegue acessar conversas de outra sessão;
7. nenhum segredo WAHA aparece no frontend;
8. nenhuma funcionalidade de prospecção permanece acessível pelo site institucional;
9. rotas removidas realmente retornam 404 ou deixam de existir;
10. site público continua funcionando e com SEO correto.

## 12. Não executar ainda

Não fazer:

- deploy;
- push;
- criação da sessão WAHA de produção;
- alteração de webhooks WAHA de produção;
- alteração de workflows n8n de produção;
- exclusão de dados;
- drop de tabelas.

Commit também deve aguardar autorização.

## 13. Relatório final

Ao terminar, informar:

1. arquivos criados;
2. arquivos alterados;
3. arquivos removidos;
4. rotas removidas;
5. rotas mantidas;
6. como ficou a área Solicitações;
7. como ficou o menu administrativo;
8. referências antigas ao CRM que ainda permaneceram e por quê;
9. integrações n8n/WAHA encontradas;
10. o que precisa ser alterado no VetorLeads;
11. testes executados e resultado;
12. pendências para produção.

O objetivo final é deixar clara esta divisão:

**abrasilsistemas.com.br = institucional + solicitações + atendimento**

**VetorLeads = prospecção + CRM + vendas + automação comercial**


### ABRASIL-WEB-03 — Complemento: imagens institucionais e identidade visual

Adicionar à implementação da ABRASIL-WEB-03 uma revisão completa das imagens dos heroes e cabeçalhos do site institucional.

**Objetivo:** transmitir a imagem de uma empresa sólida, moderna e especializada em tecnologia empresarial.

**Requisitos:**

1. Cada página de serviço deve receber uma imagem institucional exclusiva e coerente com seu nicho.
2. A home deve ter um hero de forte impacto visual, representando tecnologia e soluções empresariais.
3. Substituir ou complementar as ilustrações HTML/CSS atuais por fotografias ou composições visuais profissionais.
4. Utilizar imagens de qualidade, licenciadas para uso comercial, sem marcas de terceiros ou conteúdo genérico inadequado.
5. Não repetir a mesma imagem em páginas diferentes.
6. Preservar a paleta navy, branco e azul da ABrasil Sistemas, utilizando overlays discretos quando necessário.
7. Garantir excelente legibilidade dos títulos e CTAs sobre as imagens.
8. Otimizar imagens em WebP ou AVIF, com tamanhos responsivos, evitando aumentar excessivamente o carregamento.
9. Aplicar `srcset`, `sizes`, dimensões explícitas e carregamento prioritário apenas para a imagem principal visível.
10. Garantir compatibilidade com desktop, tablet e smartphone.
11. Usar capturas reais dos sistemas VetorOS e VetorPet nas páginas específicas de produtos, quando disponíveis, sem inventar telas ou funcionalidades.
12. Incluir textos alternativos descritivos e metadados adequados para SEO.
13. Organizar as imagens em diretórios próprios, com nomes semânticos e manutenção simples.

**Páginas contempladas:**

- Home institucional
- Sistemas prontos
- Sistemas sob medida
- Automação de processos
- Integrações e APIs
- Desenvolvimento de aplicativos
- Desenvolvimento de sites empresariais
- Landing pages
- VetorOS
- VetorPet
- Sobre a ABrasil Sistemas
- Contato

Nas páginas VetorOS e VetorPet, priorizar a identidade e o segmento de atuação de cada produto.

**Diretriz visual:** utilizar linguagem fotográfica corporativa contemporânea, com tecnologia real, ambientes de trabalho, desenvolvimento de software, operações empresariais e infraestrutura digital.

Evitar robôs humanoides genéricos, cérebros brilhantes, mãos artificiais, excesso de circuitos luminosos e imagens artificiais repetitivas.

As imagens devem valorizar a apresentação empresarial, sem transformar o site em um catálogo de fotografias.

**Entrega:** implementar as imagens efetivamente, não apenas sugeri-las. Registrar a origem e a licença de cada imagem utilizada e informar eventuais imagens ainda pendentes. Manter o visual institucional já aprovado e validar a apresentação nas resoluções desktop e mobile.

Esta exigência integra a ABRASIL-WEB-03 e deve ser executada antes do fechamento da etapa.