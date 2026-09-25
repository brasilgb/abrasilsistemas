TAREFA: CRM-WA-07 — Conversa WhatsApp operacional
FASE 1 — Atualização automática, não lidas e UX

Projeto: ABrasil Sistemas
Aplicação Laravel existente.
Não alterar n8n, WAHA ou infraestrutura Docker nesta tarefa.
Não alterar os endpoints já estabilizados do CRM-WA-06 sem necessidade comprovada.

OBJETIVO

Evoluir a área atual "Conversa WhatsApp" do lead para funcionar como uma
conversa operacional, preservando integralmente o fluxo existente:

- inbound Cliente → Empresa;
- outbound CRM → Cliente;
- outbound externo Empresa → Cliente;
- ACK/status das mensagens;
- idempotência já implementada.

Antes de implementar, AUDITE o código atual e reutilize tudo que já existir.

FASE 1

1. ATUALIZAÇÃO AUTOMÁTICA

A conversa aberta deve receber alterações sem reload manual da página.

Nesta fase, preferir polling simples e confiável, evitando introduzir
WebSocket/Reverb apenas para isso.

- auditar como o frontend atual recebe LeadActivity;
- criar/reutilizar endpoint adequado para consultar mensagens;
- enquanto a conversa estiver aberta, atualizar aproximadamente a cada 5 s;
- não duplicar mensagens já exibidas;
- atualizar também mudanças de message_status/ACK;
- interromper polling quando o componente for desmontado;
- evitar reload completo da página;
- não fazer polling de toda a página/lead se somente as mensagens forem necessárias.

2. NÃO LIDAS

Auditar primeiro a implementação existente de:
- status "Nova";
- read_at;
- badges/contadores existentes.

Preservar a semântica atual sempre que possível.

Comportamento desejado:

Cliente envia mensagem
        ↓
Lead/conversa indica mensagem nova
        ↓
Operador abre a conversa
        ↓
mensagens inbound pendentes são consideradas visualizadas
        ↓
badge/indicador é atualizado

Não marcar mensagens outbound como não lidas.

Evitar criar colunas/tabelas se o modelo atual já suporta corretamente esse
comportamento.

3. UX DA CONVERSA

Melhorar o componente existente, sem redesenhar o CRM inteiro:

- inbound alinhado à esquerda;
- outbound alinhado à direita;
- horário em cada mensagem;
- identificação visual:
  Cliente → Empresa
  Empresa → Cliente
- status outbound:
  Pendente
  Enviada
  Entregue
  Lida
  Falhou, quando aplicável;
- separação visual por data quando houver mensagens de dias diferentes;
- ao abrir a conversa, posicionar nas mensagens mais recentes;
- ao chegar mensagem nova enquanto o usuário estiver próximo do final,
  acompanhar automaticamente;
- se o usuário estiver lendo mensagens antigas, não forçar o scroll;
- mostrar indicador de novas mensagens nesse caso;
- estado de carregamento discreto;
- estado vazio adequado.

4. HISTÓRICO

Não carregar histórico ilimitado.

Auditar volume e implementação atual e implementar paginação incremental
caso ainda não exista:

- mensagens recentes primeiro;
- opção "Carregar mensagens anteriores";
- preservar ordem cronológica na tela;
- não duplicar registros entre páginas e polling.

5. SEGURANÇA

- respeitar autenticação/autorização existente;
- garantir que um usuário não consulte conversa de lead que não possa acessar;
- não expor token do WAHA/n8n;
- não criar endpoint público para leitura da conversa;
- validar IDs recebidos pelo backend.

6. NÃO FAZER NESTA FASE

Não implementar:
- imagens;
- documentos;
- áudio;
- gravação de áudio;
- WebSocket/Reverb, salvo se o projeto já utilizar essa infraestrutura;
- respostas automáticas;
- IA;
- chatbot;
- templates;
- alteração do workflow n8n;
- alteração da integração WAHA estabilizada.

Esses itens ficam para fases posteriores.

TESTES OBRIGATÓRIOS

Criar testes cobrindo pelo menos:

1. usuário autorizado consulta mensagens do lead;
2. usuário não autorizado não consulta;
3. inbound aparece como inbound;
4. outbound aparece como outbound;
5. status/ACK é retornado corretamente;
6. leitura marca somente inbound pertinente;
7. mensagens já lidas não são alteradas indevidamente;
8. paginação não duplica mensagens;
9. polling incremental retorna mensagens novas;
10. atualização de ACK de mensagem existente é percebida;
11. comportamento sem mensagens;
12. funcionalidades anteriores do WhatsApp continuam funcionando.

IMPORTANTE SOBRE O AMBIENTE

O projeto roda em Docker.

Não executar PHP/Artisan diretamente no host.
Comandos Laravel/PHP devem ser executados dentro do container da aplicação.

Antes de qualquer alteração:
- auditar estrutura atual;
- identificar arquivos envolvidos;
- identificar endpoints existentes;
- identificar componente atual da conversa;
- identificar modelo/campos LeadActivity relacionados ao WhatsApp.

Evitar migrations desnecessárias.

ENTREGA

Ao terminar, gerar um relatório objetivo contendo:

- auditoria encontrada;
- arquivos alterados/criados;
- endpoints criados/reutilizados;
- funcionamento do polling;
- funcionamento das não lidas;
- paginação;
- mapeamento dos ACK/status;
- testes criados;
- resultados dos testes;
- migrations criadas ou "nenhuma migration";
- riscos/limitações encontrados;
- roteiro manual de validação E2E.

Não fazer deploy.
Não alterar n8n.
Não alterar WAHA.
Não fazer commit automaticamente.
