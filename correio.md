CRM-WA-07 — Fase 2: UX comercial da conversa WhatsApp
Trabalhar sobre a Fase 1 já implementada e não alterar n8n, WAHA, Docker/infra nem os endpoints de integração existentes. Não criar suporte a mídia, áudio, documentos ou anexos.
Implementar:
1. Composer de mensagens
   - Enter envia.
   - Shift+Enter cria nova linha.
   - impedir duplo envio enquanto a requisição estiver em andamento;
   - manter o texto digitado caso o envio falhe;
   - limpar o campo somente após envio confirmado;
   - feedback visual discreto durante envio e em caso de erro.
2. Cabeçalho da conversa
   - exibir os dados comerciais já existentes no lead/prospect, sem criar campos novos desnecessariamente;
   - priorizar nome, empresa, WhatsApp, situação/status e responsável somente se esses dados já existirem no modelo atual.
3. Mensagens rápidas
   - primeiro auditar se o projeto já possui estrutura semelhante;
   - permitir selecionar textos prontos para primeiro contato, apresentação e follow-up;
   - inicialmente priorizar solução simples e reutilizável;
   - não criar migration sem antes justificar que ela é realmente necessária.
4. Variáveis
   - preparar substituições como {{nome}} e {{empresa}}, exclusivamente para campos que realmente existam no lead;
   - mostrar o texto final ao usuário antes do envio;
   - nunca enviar automaticamente ao selecionar um template.
5. Integração com o fluxo comercial
   - auditar os status/etapas atualmente existentes no CRM;
   - facilitar a atualização do estágio do prospect a partir da área da conversa quando isso puder reutilizar funcionalidades existentes;
   - não inventar um novo pipeline ou modelo de dados.
6. Preservar Fase 1
   - polling incremental de 5 s;
   - paginação/histórico;
   - atualização de ACK;
   - inbound/outbound;
   - não lidas;
   - scroll e indicador de novas mensagens;
   - envio realizado pelo celular aparecendo como outbound.
A Fase 1 possui atualmente paginação e polling incremental no mesmo endpoint e não deve regredir.   Texto colado
Fora do escopo: imagens, vídeos, áudio, gravação de voz, documentos, anexos, stickers e qualquer processamento de mídia.
Antes de implementar, auditar models, controllers, componentes React, rotas e testes relacionados a leads/WhatsApp para reutilizar o que já existe.
Depois da implementação, executar os testes relacionados ao WhatsApp, testes do fluxo de leads afetado, TypeScript, ESLint/Prettier e Pint. Não fazer deploy nem commit.
Ao final, gerar resumo.md contendo: arquivos criados/alterados, comportamento implementado, decisões tomadas, testes executados/resultados, migrations (se houver), riscos/limitações e roteiro E2E manual.
