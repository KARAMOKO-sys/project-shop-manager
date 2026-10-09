<x-mail::message>
# Nouvelle commande !

{{ $order->customer->first_name }} {{ $order->customer->last_name }} vient de commander sur **{{ $order->shop->name }}**.

<x-mail::table>
| Produit | Qté | Total |
|:--------|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ number_format((float) $item->total_price, 0, ',', ' ') }} {{ $order->currency }} |
@endforeach
| **Total** | | **{{ number_format((float) $order->total, 0, ',', ' ') }} {{ $order->currency }}** |
</x-mail::table>

Paiement : {{ $order->payment_method->label() }} · Client : {{ $order->customer->email }}{{ $order->customer->phone ? ' · '.$order->customer->phone : '' }}

<x-mail::button :url="$dashboardUrl">
Traiter la commande
</x-mail::button>

L'équipe ShopAI
</x-mail::message>
