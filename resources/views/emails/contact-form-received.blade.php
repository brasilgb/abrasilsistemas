<x-mail::message>
# Novo contato pelo site

**{{ $lead->contact_name ?? 'Sem nome informado' }}**, da empresa **{{ $lead->company_name }}**, entrou em contato pelo formulário do site.

<x-mail::table>
| Campo | Valor |
| :- | :- |
| Serviço de interesse | {{ $serviceLabel }} |
| WhatsApp | {{ $lead->whatsapp ?? '—' }} |
| E-mail | {{ $lead->email ?? '—' }} |
</x-mail::table>

**Mensagem:**

{{ $lead->notes }}

<x-mail::button :url="$adminUrl">
Ver no painel
</x-mail::button>

Este lead já foi salvo no CRM com status "Novo".

Atenciosamente,<br>
{{ config('app.name') }}
</x-mail::message>
