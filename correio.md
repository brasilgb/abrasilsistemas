CRM-WA-08 — Follow-up Comercial de Prospects
O CRM já possui conversa WhatsApp operacional e integração com o funil comercial. A próxima tarefa é implementar um mecanismo simples de próximo contato, para que prospects não sejam esquecidos.
Antes de alterar código, auditar completamente o modelo Lead, migrations, controllers, rotas, listagem, Kanban, formulário e histórico. Reutilizar campos e estruturas existentes sempre que possível.
Objetivo
Permitir que o operador saiba:
- quando deve entrar novamente em contato com determinado prospect;
- quais contatos estão vencidos;
- quais precisam de contato hoje;
- quais estão programados para os próximos dias.
Não criar automação de envio de WhatsApp. O sistema apenas organiza e sinaliza o trabalho comercial. O operador continua decidindo e enviando cada mensagem.
1. Primeiro auditar o que já existe
Verificar especialmente se Lead já possui campos equivalentes a:
- last_contacted_at;
- próximo contato/follow-up;
- responsável;
- status;
- observações/histórico.
O relatório anterior confirma que last_contacted_at já participa da mudança de estágio; portanto, não duplicar esse conceito.
Se não existir campo adequado para próximo contato, documentar a necessidade antes de criar migration.
2. Próximo follow-up
Cada prospect ativo poderá possuir uma data/hora opcional para próximo contato.
Na interface, permitir:
Hoje · Amanhã · +3 dias · +7 dias · Escolher data/hora · Remover
Não enviar WhatsApp automaticamente quando chegar a data.
3. Integração com a conversa WhatsApp
Na área comercial da conversa criada na Fase 2, mostrar:
Próximo contato: 28/09/2026 09:00
e permitir alterar rapidamente.
Depois de uma conversa, o operador poderá programar manualmente o próximo contato.
Não programar follow-up automaticamente apenas porque uma mensagem foi enviada.
4. Situações visuais
Apresentar de maneira clara:
Atrasado — horário já passou.
Hoje — contato previsto para hoje.
Próximo — agendado para data futura.
Sem follow-up — nenhum próximo contato definido.
Utilizar componentes/badges já existentes no projeto sempre que possível.
5. Listagem de prospects
Adicionar informações/filtros que permitam localizar:
Follow-ups atrasados
Follow-ups de hoje
Próximos follow-ups
Sem follow-up
Evitar transformar a listagem em dashboard complexo.
6. Funil
Respeitar os estados existentes:
Novo → Contatado → Interessado → Reunião → Convertido / Perdido
Prospect Convertido ou Perdido não deve aparecer como follow-up pendente, mesmo que exista uma data antiga registrada.
Avaliar se a data deve ser limpa ao converter/perder ou simplesmente ignorada. Escolher a solução com menor efeito colateral e documentar a decisão.
7. Histórico
Alterações relevantes devem ficar auditáveis no histórico do lead, por exemplo:
Próximo contato definido para 28/09/2026 às 09:00.
Próximo contato alterado de ... para ...
Próximo contato removido.
Essas entradas devem ser atividades/notas e nunca aparecer na conversa WhatsApp nem contar como mensagem não lida.
8. Responsável
Se o lead já possui responsável, reutilizá-lo.
Não criar neste momento distribuição automática, filas de vendedores, round-robin ou regras complexas de equipes.
9. Fora do escopo
Não implementar:
- disparo automático de WhatsApp;
- campanhas;
- sequências automáticas;
- IA escolhendo quando enviar;
- mídia/anexos;
- calendário completo;
- integração Google Calendar;
- notificações push;
- e-mail;
- novo sistema de permissões.
O CRM continua sendo uma ferramenta de prospecção assistida pelo operador.
10. Segurança e regressão
Preservar integralmente CRM-WA-06 e CRM-WA-07.
A Fase 2 terminou com 317 testes relacionados a WhatsApp/LeadManagement passando com 1.448 assertions.   Texto colado
Não alterar n8n, WAHA ou Docker/infra.
Executar testes específicos do novo recurso, testes de Lead/WhatsApp/Kanban afetados, suíte completa quando viável, Pint, TypeScript, ESLint e Prettier.
Entrega
Não fazer deploy nem commit.
Gerar resumo.md contendo:
- auditoria encontrada;
- decisão sobre banco/migration;
- arquivos criados e alterados;
- comportamento implementado;
- regras de follow-up;
- integração com WhatsApp;
- integração com funil;
- filtros;
- histórico;
- testes/resultados;
- riscos e limitações;
- roteiro E2E manual.
