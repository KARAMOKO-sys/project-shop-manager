<x-mail::message>
# Stock bas

Il ne reste que **{{ $product->stock_quantity }}** unité(s) de **{{ $product->name }}** dans votre boutique {{ $product->shop->name }}.

Pensez à réapprovisionner pour ne pas manquer de ventes.

<x-mail::button :url="$productUrl">
Mettre à jour le stock
</x-mail::button>

L'équipe ShopAI
</x-mail::message>
