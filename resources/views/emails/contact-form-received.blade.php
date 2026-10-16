<x-mail::message>
# Novo contato pelo site

**{{ $lead->contact_name ?? 'Sem nome informado' }}**, da empresa **{{ $lead->company_name }}**, entrou em contato pelo formulário do site.

<x-mail::table>
| Campo | Valor |
| :- | :- |
| Serviço de interesse | {{ $serviceLabel }} |
| WhatsApp | {{ $lead->whatsapp ?? '—' }} |
| E-mail | {{ $lead->email ?? '—' }} |
| Página de origem | {{ $lead->source_page ?? '—' }} |
</x-mail::table>

**Mensagem:**

{{ $lead->notes }}

<x-mail::button :url="$adminUrl">
Abrir a solicitação
</x-mail::button>

A solicitação já está no painel, em Solicitações, com a situação "Nova".

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
