<x-mail::message>
# Bonjour {{ $order->customer->first_name }},

Votre commande **{{ $order->order_number }}** chez **{{ $order->shop->name }}** est maintenant : **{{ $order->status->label() }}**.

<x-mail::button :url="$trackingUrl">
Suivre ma commande
</x-mail::button>

Merci de votre confiance,<br>
{{ $order->shop->name }}
</x-mail::message>
