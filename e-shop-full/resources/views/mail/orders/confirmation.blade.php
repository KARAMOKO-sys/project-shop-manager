<x-mail::message>
# Merci pour votre commande, {{ $order->customer->first_name }} !

**{{ $order->shop->name }}** a bien reçu votre commande **{{ $order->order_number }}**.

<x-mail::table>
| Produit | Qté | Total |
|:--------|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ number_format((float) $item->total_price, 0, ',', ' ') }} {{ $order->currency }} |
@endforeach
@if ((float) $order->discount_amount > 0)
| Réduction | | -{{ number_format((float) $order->discount_amount, 0, ',', ' ') }} {{ $order->currency }} |
@endif
| **Total** | | **{{ number_format((float) $order->total, 0, ',', ' ') }} {{ $order->currency }}** |
</x-mail::table>

Mode de paiement : {{ $order->payment_method->label() }}

<x-mail::button :url="$trackingUrl">
Suivre ma commande
</x-mail::button>

À très bientôt,<br>
{{ $order->shop->name }}
</x-mail::message>
