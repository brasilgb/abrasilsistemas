# ABRASIL-VETORCHAT-01 — Substituir botão WhatsApp antigo pelo VetorChat

## Objetivo

No site https://abrasilsistemas.com.br, remover o botão flutuante antigo do WhatsApp que redireciona o visitante para o WhatsApp Web.

Substituir pelo widget oficial do VetorChat, fornecido pelo VetorLeads.

## 1. Identificar a implementação antiga

Localizar o componente que renderiza o botão flutuante, incluindo referências a:

- whatsapp-float
- wa.me
- api.whatsapp.com
- WhatsApp Web
- Componentes globais do layout institucional

Desativar apenas o botão flutuante antigo, preservando links de contato WhatsApp existentes em páginas onde forem intencionais.

## 2. Ativar o VetorChat

Utilizar a integração já implementada:

VETORCHAT_SCRIPT_URL=https://vetorleads.com.br/widget.js

VETORCHAT_WIDGET_KEY deve utilizar a chave de instalação já cadastrada no VetorLeads.

A instalação deve equivaler a:

```html
<script src="https://vetorleads.com.br/widget.js" data-widget-key="CHAVE_CONFIGURADA" async></script>
```

Não adicionar o script duas vezes.

Não incluir chaves privadas WAHA no frontend.

## 3. Comportamento esperado

- Um único botão flutuante de atendimento.
- Abrir o VetorChat dentro da própria página.
- Interface responsiva em desktop e dispositivos móveis.
- Não redirecionar automaticamente ao WhatsApp Web.
- Identificar o tenant institucional ABrasil.
- Identificar o produto ABrasil Sistemas.
- Registrar mensagens no inbox correspondente do VetorLeads.
- Preservar isolamento multitenant.

## 4. Verificação da VPS

Confirmar se o container ABrasil está executando o código atualizado.

Verificar se VETORCHAT_WIDGET_KEY está efetivamente disponível ao Laravel.

Verificar cache de configuração, layout Blade, componentes React e artefatos frontend.

Se a imagem estiver desatualizada, gerar o build e atualizar exclusivamente o serviço ABrasil Sistemas, mediante verificação de segurança.

Não reconstruir outros containers.

## 5. Homologação objetiva

Verificar:

1. Botão WhatsApp antigo ausente.
2. VetorChat visível na página inicial.
3. Clique abre janela de conversa dentro do site.
4. Widget carrega sem erro.
5. Mensagem de teste chega ao VetorLeads.
6. Resposta retorna ao visitante.

Se os testes de navegador estiverem indisponíveis, utilizar HTTP, código renderizado, APIs e logs, registrando o que não foi visualmente confirmado.

## 6. Restrições

Não alterar VetorOS, VetorPet ou Desgarrados.

Não instalar Docker local.

Não repetir suítes extensas.

Não modificar configurações válidas de SEO e Google.

Não habilitar envio automático para contatos reais sem validação.

## Resultado

Registrar em executed.md o componente removido, a versão publicada, o widget ativado e os resultados operacionais.

Concluir a substituição e publicação, sem iniciar outra auditoria geral.


# VETOROS-CHAT-13 — Substituição do WhatsApp antigo pelo VetorChat

## Objetivo

Substituir o botão flutuante antigo do WhatsApp no site público do VetorOS pelo widget oficial do VetorChat SaaS, centralizado no VetorLeads.

Utilizar a implementação já desenvolvida e publicada, sem criar outro sistema de atendimento.

## 1. Revisar o site público

Localizar todos os componentes responsáveis pelo botão flutuante do WhatsApp, inclusive:

- Layout público.
- Componentes React.
- Views Blade.
- Scripts globais.
- Links `wa.me` e `api.whatsapp.com`.

Remover somente o botão flutuante antigo.

Preservar links de WhatsApp que façam parte de funcionalidades comerciais intencionais e do uso operacional do VetorOS.

Não modificar o WhatsApp dos clientes das assistências técnicas.

## 2. Integrar o widget oficial

Utilizar as variáveis já existentes:

```dotenv
VETORCHAT_SCRIPT_URL=https://vetorleads.com.br/widget.js
VETORCHAT_WIDGET_KEY=CHAVE_PUBLICA_DA_INSTALACAO
```

O resultado deve equivaler a:

```html
<script src="https://vetorleads.com.br/widget.js"
        data-widget-key="CHAVE_PUBLICA_DA_INSTALACAO"
        async></script>
```

Reutilizar a integração de `resources/views/app.blade.php` já implementada.

Não duplicar scripts.

Não incluir tokens privados WAHA no frontend.

## 3. Identificação do atendimento

Configurar a instalação para:

- Tenant: ABrasil Sistemas.
- Produto: VetorOS.
- Origem: domínio público do VetorOS.
- Canal: Webchat.
- Inbox: atendimento do VetorLeads.

Utilizar a chave de instalação correspondente ao produto VetorOS. Compartilhar a mesma chave institucional somente se o modelo de autorização e identificação de produtos do VetorLeads permitir.

As mensagens recebidas devem indicar claramente que o visitante está interessado no VetorOS.

## 4. Comportamento visual

O novo widget deve:

- Substituir o botão flutuante anterior.
- Aparecer uma única vez.
- Abrir o atendimento dentro do próprio site.
- Funcionar em desktop e dispositivos móveis.
- Não atrapalhar menus, formulários ou navegação.
- Não abrir WhatsApp Web automaticamente.

O design deve ser consistente com o widget utilizado no ABrasil Sistemas.

## 5. Segurança

- Respeitar os domínios autorizados da instalação.
- Preservar isolamento entre tenants.
- Não expor chaves privadas ou tokens de serviço.
- Não alterar automações comerciais existentes.
- Não modificar conversas reais de clientes.
- Não habilitar respostas automáticas não homologadas.

## 6. Publicação

Trabalhar exclusivamente no VetorOS.

Verificar o commit existente e o estado da imagem de produção.

Se houver alterações necessárias, criar commit e push seletivos antes do deploy.

Na VPS, atualizar somente o serviço VetorOS, após verificar backup, configuração e rollback.

Não reiniciar MySQL, Redis, WAHA, VetorLeads ou outros serviços desnecessariamente.

Não executar Docker local.

## 7. Homologação

Verificar:

1. Botão antigo removido.
2. Widget VetorChat carregando.
3. Produto VetorOS identificado.
4. Mensagem chegando ao inbox correto.
5. Resposta retornando ao visitante.
6. Site e painel VetorOS funcionando normalmente.

Utilizar identidade de teste autorizada para validar mensagens.

Se o navegador não estiver disponível, utilizar verificações HTTP, API e logs sem insistir em instalações de navegadores.

## 8. Resultado

Registrar em `executed.md`:

- Arquivos modificados.
- Commit e versão publicada.
- Estado do widget.
- Resultado dos testes.
- Pendências reais.

**Objetivo: disponibilizar um único canal de atendimento web integrado ao VetorLeads, preservando o WhatsApp operacional dos clientes do VetorOS.**
