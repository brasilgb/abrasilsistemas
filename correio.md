# CRM-LEAD-02.3 — Fechamento da extensão AB Prospect 1.4.0

## Objetivo

Concluir tecnicamente a versão **1.4.0 da extensão AB Prospect**, corrigindo a duplicidade visual no popup quando a mesma empresa é capturada primeiro pela lista do Google Maps e depois pelo painel da empresa selecionada.

Após a correção, validar integralmente a extensão e preparar sua assinatura/publicação.

Esta etapa deve permanecer **conservadora**.

Não iniciar CRM-LEAD-03, enriquecimento por CNPJ, reputação, IA, scoring adicional ou integrações externas.

---

## 1. Auditoria inicial obrigatória

Antes de alterar qualquer arquivo:

1. Ler integralmente:
   - `correio.md`
   - `executed.md`
   - alterações pendentes da CRM-LEAD-02.1 e CRM-LEAD-02.2
   - arquivos atuais da extensão AB Prospect 1.4.0.

2. Confirmar que o estado encontrado corresponde ao encerramento da CRM-LEAD-02.2.

3. Verificar o `git status` e registrar no relatório quais alterações já existiam antes desta etapa.

Não descartar nem sobrescrever alterações existentes.

---

# 2. Corrigir somente a duplicidade visual do popup

## Problema observado

Nos testes reais da CRM-LEAD-02.2, uma mesma empresa pode aparecer duas vezes no popup:

- captura pela lista do Google Maps:
  - possui `Place ID`;
  - possui FTID;
  - possui coordenadas.

- captura posterior pelo painel da empresa selecionada:
  - pode não possuir `Place ID`;
  - possui o mesmo FTID;
  - possui coordenadas;
  - normalmente contém dados mais completos.

O CRM já consegue consolidar corretamente esses registros pelo FTID.

O problema restante é somente no estado/listagem visual da própria extensão.

Exemplo observado:

- 30 itens apresentados no popup;
- 24 empresas efetivamente distintas;
- CRM consolidou corretamente em 24 leads.

---

# 3. Regra de identidade no popup

Ao adicionar uma empresa à coleção mantida pelo popup, considerar que dois registros representam a mesma empresa quando houver:

1. mesmo `Place ID`;

OU

2. mesmo FTID extraído de `mapsUrl`.

O FTID deve ser interpretado a partir do padrão já existente nas URLs do Google Maps.

Preferir reutilizar parser/função existente no projeto em vez de criar lógica duplicada.

Não utilizar telefone como identificador principal para essa consolidação.

Empresas diferentes podem compartilhar telefone.

---

# 4. Merge dos dados

Quando a mesma empresa for encontrada novamente, não criar um segundo item.

Fazer merge dos registros.

A captura mais completa deve enriquecer a já existente.

Exemplo:

lista:

- nome
- rating
- reviews
- categoria
- endereço parcial
- Place ID
- FTID
- coordenadas

painel selecionado:

- nome
- rating
- reviews
- categoria
- endereço completo
- cidade
- UF
- telefone
- site
- FTID
- coordenadas

Resultado esperado:

**um único item contendo a melhor combinação dos dois registros.**

## Regra importante

Um valor válido já existente não deve ser substituído por:

- `null`;
- string vazia;
- `undefined`;
- valor reconhecidamente menos completo.

Por exemplo:

se a captura da lista possui `placeId` e a captura do painel não possui, o `placeId` original deve permanecer.

Ao mesmo tempo, se o painel posteriormente fornecer cidade, UF, telefone ou site, esses campos devem enriquecer o registro existente.

---

# 5. Não alterar regras de telefone/WhatsApp

Não modificar a semântica aprovada na CRM-LEAD-02.2.

Manter:

### Telefone móvel vindo do Maps

Pode ser copiado automaticamente para WhatsApp.

Status:

`probable`

### Telefone fixo vindo do Maps

Deve permanecer como telefone.

Não copiar automaticamente para `whatsapp`.

Status:

`unverified`

Apresentação equivalente a:

`Telefone fixo · WhatsApp não verificado`

Isso **não significa que o telefone não possua WhatsApp**.

Significa somente que a extensão/CRM ainda não verificou essa informação.

### WhatsApp explicitamente informado

Mesmo que o número seja classificado como fixo, deve ser preservado.

Status:

`informed`

Não bloquear envio manual ou automações quando o campo `whatsapp` estiver preenchido.

---

# 6. Testes automatizados obrigatórios

Adicionar teste específico reproduzindo o problema encontrado em produção.

## Cenário A — mesma empresa

Captura da lista:

- Place ID presente;
- FTID `A`;
- dados básicos.

Depois captura do painel:

- sem Place ID;
- FTID `A`;
- dados adicionais.

Resultado esperado:

`1 empresa`

e não:

`2 empresas`

O registro final deve manter o Place ID da primeira captura e incorporar os dados mais completos da segunda.

---

## Cenário B — mesma empresa capturada novamente

Duas capturas contendo o mesmo Place ID.

Resultado:

`1 empresa`

---

## Cenário C — empresas distintas

Mesmo telefone, porém FTIDs diferentes.

Resultado:

`2 empresas`

Não consolidar somente pelo telefone.

---

## Cenário D — dados incompletos

Registro posterior possui campos vazios.

Esses campos não devem apagar dados válidos já capturados anteriormente.

---

# 7. Executar validações da extensão

Executar novamente, conforme os comandos existentes no projeto:

- testes Node;
- lint/web-ext lint;
- TypeScript, se aplicável;
- build da extensão;
- demais verificações já utilizadas nas CRM-LEAD-02.1/02.2.

Todos devem ser registrados no `executed.md`.

Não mascarar warnings ou falhas.

---

# 8. Smoke test real do popup

Se o ambiente utilizado na CRM-LEAD-02.2 continuar disponível, realizar um smoke test pequeno utilizando Google Maps real.

Não é necessário repetir a bateria de 30 registros.

Utilizar aproximadamente 2 ou 3 empresas.

Fluxo mínimo:

1. pesquisar no Google Maps;
2. capturar lista;
3. selecionar uma empresa da própria lista;
4. capturar `Empresa selecionada`;
5. verificar o popup.

Resultado esperado:

a empresa capturada pela lista e posteriormente pelo painel deve continuar aparecendo **uma única vez**.

Os dados mais completos do painel devem estar incorporados ao registro.

---

# 9. Não enviar o smoke test para produção

Durante esse teste:

- não clicar em `Enviar ao CRM`;
- não criar leads reais;
- não enviar WhatsApp;
- não alterar dados do CRM de produção.

Se necessário, manter endpoint propositalmente inválido/local como feito anteriormente.

---

# 10. Preparação da versão 1.4.0

Após todos os testes passarem:

1. confirmar versão `1.4.0`;
2. conferir manifest;
3. conferir arquivos incluídos no pacote;
4. garantir que testes, artefatos temporários e arquivos desnecessários não façam parte do pacote final;
5. gerar o artefato final da extensão utilizando o mecanismo de release existente no projeto.

Não alterar para 1.4.1 nesta etapa.

A CRM-LEAD-02.3 faz parte do fechamento da própria 1.4.0 ainda não publicada.

---

# 11. Assinatura Mozilla

Verificar o mecanismo existente em:

`scripts/release.sh`

e demais arquivos relacionados ao processo de assinatura.

## Segurança

Nunca:

- imprimir credenciais;
- registrar secrets no `executed.md`;
- inserir credenciais no Git;
- colocar API key/secret diretamente no código;
- exibir valores de variáveis secretas nos logs.

Se as credenciais Mozilla/AMO necessárias estiverem disponíveis de forma segura no ambiente, utilizar o mecanismo oficial já preparado pelo projeto para assinar a extensão.

Após a assinatura:

- verificar que o XPI foi realmente gerado;
- verificar versão 1.4.0;
- verificar integridade básica do pacote;
- registrar apenas informações não sensíveis.

---

# 12. Caso não existam credenciais para assinatura

Se as credenciais Mozilla/AMO não estiverem disponíveis:

**PARAR neste ponto.**

Não tentar contornar a assinatura.

Não publicar extensão não assinada como se fosse release final.

Registrar no `executed.md`:

- que toda a implementação passou;
- que a extensão está pronta para assinatura;
- qual credencial/configuração é necessária;
- qual é o próximo comando/processo depois que a credencial estiver disponível.

Nesse caso não executar deploy final da extensão.

---

# 13. Artefato assinado

Se a assinatura for concluída com sucesso, identificar o caminho esperado atualmente pelo CRM para download da extensão.

Verificar a implementação existente antes de decidir o nome/caminho.

O XPI assinado deve ser disponibilizado no mecanismo já utilizado pelo projeto, preferencialmente sob a estrutura existente de:

`public/files/`

Não inventar um segundo mecanismo de distribuição.

Verificar também a tela/configuração existente do CRM relacionada ao download da extensão.

Ela deve apresentar claramente a versão:

`AB Prospect 1.4.0`

---

# 14. Deploy controlado do CRM

Somente executar o deploy se:

- assinatura da extensão tiver sido concluída;
- XPI final estiver válido;
- os testes estiverem aprovados;
- não houver bloqueador identificado.

O deploy deve afetar somente os componentes necessários do ABrasil Sistemas.

Não alterar:

- VetorOS;
- VetorPet;
- n8n;
- WAHA;
- MySQL;
- Redis;
- nginx global;
- configurações dos produtos;
- configuração CRM-WA-31;
- tokens das APIs.

Não executar mudanças destrutivas.

---

# 15. Banco de dados

A CRM-LEAD-02.2 não deveria exigir nova migration.

Antes do deploy, confirmar isso no código.

Executar:

`php artisan migrate:status`

ou equivalente dentro do container correto.

Se aparecer migration inesperada relacionada a esta etapa:

**PARAR e documentar antes de aplicá-la.**

---

# 16. Validação após deploy

Após o deploy, validar pelo menos:

- container ABrasil saudável;
- aplicação respondendo;
- `/`;
- `/login`;
- assets;
- API protegida continua retornando `401` sem autenticação/token adequado;
- tela/configuração de Leads;
- download da extensão;
- versão apresentada como `1.4.0`;
- arquivo XPI acessível;
- scheduler continua saudável;
- configuração da CRM-WA-31 permanece ativa.

Não enviar mensagens WhatsApp como parte desse smoke test.

---

# 17. Captura real em produção

Não criar automaticamente uma bateria de leads reais.

Após o deploy, se toda a infraestrutura estiver aprovada, parar antes de uma importação real significativa.

No máximo, informar no relatório que o sistema está pronto para fazermos posteriormente uma captura controlada de poucas empresas reais pela extensão instalada.

Não poluir o CRM com dados de teste.

---

# 18. Fora de escopo

Não implementar nesta etapa:

- consulta CNPJ;
- Receita Federal;
- BrasilAPI;
- ReceitaWS;
- Serasa;
- reputação empresarial;
- score de reputação;
- score por IA;
- LLM;
- classificação por IA;
- scraping adicional;
- marketplace;
- distribuidores;
- Mercado Livre;
- Instagram enrichment adicional;
- busca externa de telefone;
- validação automática se telefone fixo possui WhatsApp;
- CRM-LEAD-03.

Esses itens pertencem às próximas etapas.

---

# 19. Git

Não fazer commit nem push automaticamente, salvo se houver autorização explícita já registrada para isso.

Preservar todas as alterações anteriores.

Ao final mostrar:

`git status`

e registrar os arquivos alterados especificamente pela CRM-LEAD-02.3.

---

# 20. Relatório obrigatório

Atualizar `executed.md` com:

## CRM-LEAD-02.3

### Estado inicial
- branch;
- HEAD;
- alterações previamente existentes.

### Correção
- causa da duplicidade visual;
- arquivos alterados;
- estratégia de identificação Place ID/FTID;
- estratégia de merge.

### Testes
- testes adicionados;
- resultados Node;
- lint;
- build;
- smoke test real.

### WhatsApp
Confirmar explicitamente que:

- telefone fixo não é considerado automaticamente “sem WhatsApp”;
- telefone fixo apenas permanece “WhatsApp não verificado”;
- WhatsApp fixo explicitamente informado continua permitido.

### Release
Informar:

- versão;
- pacote gerado;
- assinatura realizada ou bloqueada;
- caminho do XPI, se existente.

### Deploy
Informar:

- se foi realizado;
- serviços afetados;
- validações pós-deploy;
- estado do scheduler CRM-WA-31.

### Git
- `git status`;
- nenhum commit/push, salvo autorização.

---

# Critério de aprovação

Considerar a CRM-LEAD-02.3 concluída somente quando:

1. lista + painel da mesma empresa resultarem em um único item no popup;
2. Place ID e FTID funcionarem corretamente como identidade;
3. merge preservar os dados mais completos;
4. empresas distintas não forem consolidadas apenas por telefone;
5. semântica de telefone/WhatsApp da CRM-LEAD-02.2 permanecer intacta;
6. testes da extensão passarem;
7. smoke test real confirmar a correção;
8. versão 1.4.0 estiver pronta para distribuição;
9. assinatura estiver concluída **ou** houver bloqueio explícito exclusivamente por ausência de credenciais;
10. nenhuma funcionalidade da CRM-LEAD-03 tiver sido iniciada.

Ao concluir, atualizar `executed.md` e **parar**.

Não iniciar automaticamente a CRM-LEAD-03.
