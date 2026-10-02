# CRM-LEAD-02.3: fechamento da extensão AB Prospect 1.4.0

Data: 2026-09-30 · Executado a partir de `correio.md` (CRM-LEAD-02.3) · Branch `feat/whatsapp-message-direction`, HEAD `c5407ec`

## Resumo

- **Duplicata visual do popup corrigida.** A lista e depois o painel da mesma empresa agora viram **um único item**. A identidade é o Place ID **ou** o FTID, a mesma regra do CRM (`MapsPlace::samePlaceAs`). O telefone nunca identifica.
- O merge enriquece o registro existente sem apagar valor bom. O Place ID da lista é mantido, e o endereço completo, a cidade e a UF do painel entram no registro.
- **16/16 testes Node** (8 novos para os cenários A a D). Com o `merge()` antigo, 7 dos novos falham, então eles detectam o bug. Os 30 itens reais da CRM-LEAD-02.2 reprocessados dão **24 itens**, o mesmo que o CRM.
- **Smoke test real** no Google Maps: 10 itens da lista mais 3 painéis sem Place ID resultaram em **10 itens**, e nada foi enviado.
- Resultado da validação: lint 0 erros, build 1.4.0 com o pacote conferido.
- **Assinatura BLOQUEADA** por falta das credenciais AMO no ambiente (seção 12). Por isso **não houve deploy** (seção 14). Parado aqui.

## Estado inicial

| Item | Valor |
|---|---|
| Branch | `feat/whatsapp-message-direction` |
| HEAD | `c5407ec` (Identidade confiável do lead e melhoria da captura, CRM-LEAD-02) |
| Estado encontrado | Corresponde ao encerramento da CRM-LEAD-02.2: `executed.md` com "CRM-LEAD-02.2 APROVADA — EXTENSÃO 1.4.0 PRONTA PARA ASSINATURA", `manifest.json`/`package.json` na **1.4.0** e só o XPI 1.3.0 em `public/files/` |

Alterações que **já existiam** antes desta etapa (`git status` inicial). Nenhuma foi descartada ou sobrescrita, o que foi conferido por md5 antes e depois:

```
 M app/Http/Controllers/Api/ProspectImportController.php     (02.2)
 M app/Models/Lead.php                                       (02.2)
 M app/Services/BrazilianPhone.php                           (02.2)
 M correio.md
 M executed.md
 M extension/ab-prospect-firefox/package.json                (02.1)
 M extension/ab-prospect-firefox/scripts/release.sh          (02.1)
 M extension/ab-prospect-firefox/services/maps.js            (02.1, alterado de novo nesta etapa)
 M resources/js/components/lead-whatsapp-conversation.tsx    (02.2)
 M resources/js/pages/leads/edit.tsx                         (02.2)
 M resources/js/pages/leads/form.tsx                         (02.2)
 M resumo.md
?? extension/ab-prospect-firefox/tests/                      (02.1, alterado de novo nesta etapa)
?? tests/Feature/LeadWhatsappLineTypeTest.php                (02.2)
```

## Correção

### Causa da duplicidade visual

O `merge()` do `popup.js` considerava a mesma empresa só quando havia o mesmo `id` **ou** o mesmo `placeId` **ou** o mesmo nome e endereço. A URL do painel da empresa selecionada muitas vezes **não tem Place ID** (`!19s…`), então o `id` do item passa a ser derivado de nome, endereço, telefone e URL. O endereço do painel é o completo ("R. X, 575 - Centro, Garibaldi - RS…") e o da lista é o parcial ("R. X, 575"). Nenhuma das três regras casava, e o painel virava um segundo item. O FTID (`!1s0x…:0x…`), igual nas duas URLs, não era usado no popup, só no CRM.

Além disso, o merge antigo fazia `{ ...existente, ...novo }`, então um campo vazio da captura nova apagava um valor bom e o `id` do item era trocado.

### Arquivos alterados nesta etapa

| Arquivo | Mudança |
|---|---|
| `extension/ab-prospect-firefox/services/prospects.js` (novo) | `samePlace()`, `mergeProspect()` e `mergeProspects()`, puros e testáveis em Node |
| `extension/ab-prospect-firefox/services/maps.js` | `parsePlace()` passa a devolver também `featureId`, com o mesmo regex de `MapsPlace::fromUrl` (minúsculo). `normalizeLead()` grava `featureId` no item |
| `extension/ab-prospect-firefox/popup.js` | `merge()` removido. `capture()` chama `mergeProspects()` |
| `extension/ab-prospect-firefox/tests/extension.test.mjs` | Teste do `parsePlace` com FTID e 8 testes novos (ver Testes) |

O payload enviado ao CRM **não mudou**: `featureId` não é enviado, porque o CRM lê o FTID da `mapsUrl`. O contrato 1.3.0 continua coberto pelo teste existente.

### Estratégia de identificação (Place ID / FTID)

Reutiliza o parser existente (`mapsExtractor('__parsers__').parsePlace`) e a mesma semântica do CRM:

1. **Mesmo lugar** quando o Place ID coincide **ou** o FTID coincide.
2. **Lugares diferentes** quando os dois têm Place ID, ou os dois têm FTID, e ele diverge. Aí o nome e o endereço iguais **não** juntam, como no caso de filiais.
3. Sem id comparável (URL de busca, por exemplo), vale a regra anterior: mesmo `id` ou mesmo nome e endereço.
4. Um item salvo antes desta correção (sem o campo `featureId`) tem o FTID lido da `mapsUrl` na hora da comparação.
5. **O telefone nunca é usado** como identificador.

### Estratégia de merge

- Campo vazio da captura nova (`null`, `undefined`, `''`, `NaN`) **nunca** substitui um valor existente. Um campo preenchido enriquece o registro.
- **Endereço**: se o existente contém o novo (o parcial da lista dentro do completo do painel), fica o completo, em qualquer ordem de captura.
- **`mapsUrl`**: se a existente tem Place ID e a nova não, fica a existente, e o `placeId` permanece.
- **Site** (`website`, `websiteType`, `hasWebsite`, `siteStatus`, `canImprove`, `opportunity`, `websiteChecks`) anda em bloco. A classificação nova só entra se vier um link **novo e diferente**. Sem link, ou com o mesmo link, fica o que havia, **inclusive o resultado de "Analisar sites"**.
- **Estado local** (`id`, `selected`, `sentAt`, `lastSendAt`, `sendStatus`, `sendMessage`) é sempre o do item existente.

## Testes

### Testes adicionados (`tests/extension.test.mjs`, URLs reais do Maps da CRM-LEAD-02.2)

| Teste | Cenário | Verifica |
|---|---|---|
| `A: list then panel…` | A | Lista (Place ID + FTID) e depois o painel (sem Place ID, mesmo FTID) dão **1 item**, com o Place ID e a URL da lista e o endereço completo, cidade, UF, telefone e site do painel. O payload leva o `placeId` |
| `A: order does not matter…` | A | Painel e depois lista também dão 1 item, e o endereço parcial não substitui o completo |
| `B: same Place ID…` | B | Mesmo Place ID dá 1 item, com o `id`, a seleção e o status de envio mantidos |
| `C: same phone, different FTIDs…` | C | Mesmo telefone com FTIDs diferentes dá **2 itens**. Mesmo nome e endereço com FTIDs diferentes também dá 2 |
| `D: empty fields…` | D | Captura posterior com os campos vazios não apaga endereço, telefone, cidade, UF, categoria, site, rating, reviews, Place ID, coordenadas nem URL |
| `D: a recapture keeps "Analisar sites"…` | D | Recapturar não desfaz a análise do mesmo site. Um link novo reclassifica |
| `items saved before 1.4.0…` | — | Um item salvo sem `featureId` casa pelo FTID da URL |
| `no Place ID nor FTID…` | — | Sem id comparável, vale a regra antiga (nome e endereço) |

### Resultados

| Verificação | Resultado |
|---|---|
| `node --test tests/extension.test.mjs` | **16/16** (8 anteriores + 8 novos) |
| Mutante: `merge()` antigo no lugar do novo | **7 falhas** entre os novos, o que prova que os testes detectam o bug |
| Replay dos **30 itens reais** do popup da CRM-LEAD-02.2 (na ordem de captura) | **30 → 24 itens**, igual aos 24 leads que o CRM gerou. Os 6 painéis foram incorporados com o Place ID mantido |
| `node --check` | OK em `popup.js`, `config.js` e `services/*.js` (inclusive `prospects.js`) |
| `web-ext lint` (`npm run lint`) | **0 erros**, 1 aviso `UNSAFE_VAR_ASSIGNMENT` (`innerHTML` do `renderList`) e 1 notice `MISSING_DATA_COLLECTION_PERMISSIONS`, ambos **pré-existentes** e iguais aos da 02.2 |
| TypeScript | Não se aplica: a extensão é JS puro, sem `tsc` |
| `web-ext build` (`npm run build`) | OK: `ab_prospect_google_maps_e_crm-1.4.0.zip` (20.441 bytes, sha256 `4e27a94e…d3c01b2d`), zip íntegro |
| CRM (PHP/TSX) | Não alterado nesta etapa, então a suíte Pest não foi reexecutada. Resultado da 02.2: 1025 passed + `BlogTest` pré-existente |

Tudo rodou em containers descartáveis (`node:22-bookworm-slim`, `--network none`) sobre uma cópia no scratchpad.

### Smoke test real do popup

| Item | Como |
|---|---|
| Ambiente | O mesmo da 02.2: Firefox descartável (`selenium/standalone-firefox`, geckodriver, headless, pt-BR), com o zip 1.4.0 **desta etapa** como extensão temporária. A página real `popup.html` foi aberta e os botões reais foram clicados. Só `tabs.query` foi redirecionado para a aba do Maps. Container e rede foram removidos ao final |
| Segurança | O endpoint foi trocado para `http://127.0.0.1:9/nao-enviar` antes de qualquer captura. **"Enviar ao CRM" não foi clicado**, nenhum lead foi criado, nenhum WhatsApp foi enviado e o CRM de produção não foi tocado |
| Busca | **auto elétrica em Garibaldi RS** |

Fluxo: 1) busca → 2) "Capturar lista": **10 itens**, Total 10 → 3) clique em 3 empresas da própria lista → 4) "Empresa selecionada" para cada uma → 5) popup final: **Total 10, 10 cards**.

As 3 URLs de painel vieram **sem Place ID**, com o mesmo FTID do card. É exatamente o cenário de produção:

| Empresa | Ocorrências | Place ID | Endereço (lista → final) | Cidade/UF | Telefone |
|---|---|---|---|---|---|
| Gnatta auto elétrica | **1** | mantido (`ChIJKyhf…`) | "R. Gen. Osório, 575" → "R. Gen. Osório, 575 - Centro, Garibaldi - RS, 95720-000, Brasil" | vazio → Garibaldi/RS | +55 54 99974-1027 |
| César Longhi Auto Elétrica | **1** | mantido (`ChIJhzKo…`) | "R. Manoel Peterlongo Filho, 712" → "… - Champanhe, Garibaldi - RS, 95720-000, Brasil" | vazio → Garibaldi/RS | +55 54 99997-1008 |
| AMG Auto Elétrica e Mecânica | **1** | mantido (`ChIJnfZ4…`) | "R. Gen. Osório, 57" → "R. Gen. Osório, 57 - Centro, Garibaldi - RS, 95720-000, Brasil" | vazio → Garibaldi/RS | +55 54 99993-6061 |

Rating, reviews e categoria foram mantidos, iguais ao Maps. O card do popup mostra "Garibaldi · RS · Autoelétrico". Na 1.4.0 anterior, o mesmo fluxo resultaria em 13 itens.

## WhatsApp

A semântica da CRM-LEAD-02.2 **não foi alterada**. Nenhum arquivo do CRM foi tocado nesta etapa, e a extensão continua sem classificar telefone e sem enviar `whatsapp`.

- **O telefone fixo não é considerado "sem WhatsApp"** automaticamente. Ele não é copiado para `whatsapp` e fica com `whatsapp_status = unverified`.
- O telefone fixo aparece só como **"Telefone fixo · WhatsApp não verificado"**, o que não afirma ausência de WhatsApp.
- **Um WhatsApp fixo informado explicitamente continua permitido** (`informed`). O envio manual e as automações só exigem o campo `whatsapp` preenchido.
- O celular do Maps continua copiado para `whatsapp` (`probable`).

## Release

| Item | Situação |
|---|---|
| Versão | **1.4.0** em `manifest.json` e `package.json`. Não foi para 1.4.1 |
| Manifest | MV3, id `ab-prospect@abrasilsistemas.com.br`, `strict_min_version` 128.0, permissões iguais às da 02.2 |
| Pacote gerado | `web-ext build`, **só no scratchpad**. Conteúdo: `manifest.json`, `popup.{html,css,js}`, `config.js`, `icons/*` (4) e `services/{crm,maps,prospects,site-analyzer,storage}.js`. **Fora do pacote**: `tests/`, `scripts/`, `node_modules/`, `package*.json`, `README.md`, `artifacts/`, `.amo-upload-uuid`, `.gitignore` |
| Assinatura | **BLOQUEADA: credenciais AMO ausentes.** `AMO_JWT_ISSUER` e `AMO_JWT_SECRET` não estão definidas no ambiente. Só a presença foi verificada, nenhum valor foi lido. Não tentei contornar a assinatura |
| XPI assinado | **Não existe.** `public/files/` continua só com `ab-prospect-firefox-v1.3.0.xpi` (md5 `a62746cd…`, inalterado). Nenhum zip não assinado foi publicado |

**A implementação passou em tudo. A extensão está pronta para assinatura.**

Para assinar, é preciso o par de chaves da API do AMO (JWT issuer e secret) da conta dona do add-on `ab-prospect@abrasilsistemas.com.br`. O par é gerado em https://addons.mozilla.org/developers/addon/api/key/. O canal é `unlisted`, já configurado no script.

Próximo passo, com as chaves disponíveis (digitadas no shell, fora do Git e fora de qualquer arquivo):

```bash
cd /opt/infra-abrasil/gateway/abrasilsistemas/extension/ab-prospect-firefox
npm ci                                   # web-ext (node_modules não está no repositório)
read -rs AMO_JWT_ISSUER; export AMO_JWT_ISSUER
read -rs AMO_JWT_SECRET; export AMO_JWT_SECRET
npm run sign                             # scripts/release.sh: web-ext sign --channel=unlisted
unset AMO_JWT_ISSUER AMO_JWT_SECRET
```

O `release.sh` já copia o XPI assinado para `public/files/ab-prospect-firefox-v1.4.0.xpi`, que é o mecanismo existente. `LeadSettingsController::edit()` escolhe o `ab-prospect-firefox-*.xpi|zip` mais recente de `public/files/`, e a tela Configurações → Leads mostra o nome do arquivo (`…-v1.4.0.xpi`) e o marca como assinado por ser `.xpi`. Não há segundo mecanismo de distribuição a criar.

## Deploy

| Item | Situação |
|---|---|
| Realizado | **Não.** A seção 14 exige a assinatura concluída e o XPI válido, e a assinatura está bloqueada |
| Serviços afetados | Nenhum. Produção não foi tocada: sem `docker exec`, sem build de imagem, sem acesso ao banco |
| Migrations | Confirmado no código: **nenhuma migration nova** (33 em `database/migrations`, todas versionadas, nenhuma nova ou alterada no `git status`). O `migrate:status` em produção fica para o momento do deploy |
| Validações pós-deploy | Não aplicáveis |
| Scheduler CRM-WA-31 | Não verificado nem alterado nesta etapa (sem acesso à produção) |

Quando houver o XPI assinado, o deploy precisa levar junto as mudanças do CRM da CRM-LEAD-02.2 (`whatsapp_status` e os textos da tela) e o próprio XPI. `public/files` é servido a partir do código na imagem, então um build do ABrasil é necessário. O roteiro é o mesmo dos deploys anteriores: backup, `docker compose build` dos 3 serviços abrasilsistema, `migrate:status` (esperado: nada pendente), `up -d --no-deps` e as validações da seção 16.

## Captura real em produção

Não feita. Depois da assinatura e do deploy, o sistema estará pronto para uma captura controlada de poucas empresas reais pela extensão instalada.

## Git

Nenhum commit e nenhum push.

`git status` final:

```
 M app/Http/Controllers/Api/ProspectImportController.php
 M app/Models/Lead.php
 M app/Services/BrazilianPhone.php
 M correio.md
 M executed.md
 M extension/ab-prospect-firefox/package.json
 M extension/ab-prospect-firefox/popup.js
 M extension/ab-prospect-firefox/scripts/release.sh
 M extension/ab-prospect-firefox/services/maps.js
 M resources/js/components/lead-whatsapp-conversation.tsx
 M resources/js/pages/leads/edit.tsx
 M resources/js/pages/leads/form.tsx
 M resumo.md
?? extension/ab-prospect-firefox/services/prospects.js
?? extension/ab-prospect-firefox/tests/
?? tests/Feature/LeadWhatsappLineTypeTest.php
```

Arquivos alterados **especificamente pela CRM-LEAD-02.3**:

- `extension/ab-prospect-firefox/services/prospects.js` (novo)
- `extension/ab-prospect-firefox/services/maps.js` (`featureId` no `parsePlace`/`normalizeLead`)
- `extension/ab-prospect-firefox/popup.js` (usa `mergeProspects`)
- `extension/ab-prospect-firefox/tests/extension.test.mjs` (testes novos)
- `executed.md` (este relatório)

## Observações (não bloqueiam)

1. **Duplicatas já salvas não são limpas.** Um navegador que já tenha duplicatas no `storage` da extensão (de testes com a 1.4.0 não publicada) as mantém. As novas capturas não duplicam mais. "Limpar" resolve. Não fiz consolidação automática ao abrir o popup, para não mexer em dados salvos sem pedido.
2. A categoria pode diferir entre o card e o painel (ex.: "Oficina mecânica" × "Mecânica para carros"). Nenhuma das duas é "menos completa", então vale a última captura.
3. A notice `data_collection_permissions` do lint vai virar obrigatória em versões futuras do Firefox. Vale tratar numa versão posterior, não na 1.4.0.

**CRM-LEAD-02.3: IMPLEMENTAÇÃO APROVADA. ASSINATURA BLOQUEADA POR FALTA DE CREDENCIAIS AMO.**

Parado aqui. Não assinei, não publiquei, não fiz deploy, não fiz commit e não iniciei a CRM-LEAD-03.
