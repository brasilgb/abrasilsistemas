# AB Prospect — extensão Firefox

Captura empresas no Google Maps e envia como prospects para o CRM em `abrasilsistemas`.

## Assinar e publicar uma nova versão

A extensão não é distribuída pela loja pública do Firefox — é assinada como
"unlisted" (self-distribution) e o `.xpi` fica disponível para download em
`public/files` do projeto `abrasilsistemas`, mostrado na página
**Configurações → Leads**.

1. Atualize `version` em `manifest.json` e `package.json`.
2. Gere um par de API key/secret em
   https://addons.mozilla.org/developers/addon/api/key/ (conta de
   desenvolvedor Mozilla) — só precisa gerar uma vez, dá pra reusar nas
   próximas versões.
3. Rode, na raiz desta pasta:

   ```bash
   npm install
   AMO_JWT_ISSUER=xxx AMO_JWT_SECRET=yyy npm run sign
   ```

4. O script assina via `web-ext`, baixa o `.xpi` assinado pela Mozilla e
   copia automaticamente para `../../public/files/ab-prospect-firefox-v<versão>.xpi`.
5. A página de Configurações → Leads detecta o arquivo mais recente em
   `public/files` automaticamente (não precisa mexer no código do site).

Nunca commite `AMO_JWT_ISSUER`/`AMO_JWT_SECRET` — passe sempre como variável
de ambiente na hora de rodar o comando.

## Instalar a versão assinada

Depois de assinada, a extensão pode ser instalada permanentemente (sem
sumir ao fechar o navegador):

1. Baixe o `.xpi` pela página de Configurações → Leads.
2. Abra `about:addons` no Firefox.
3. Clique na engrenagem (⚙) → "Instalar extensão a partir de um arquivo".
4. Selecione o `.xpi` baixado.
