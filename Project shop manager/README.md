# ShopAI — application mobile

Version mobile (Flutter) de la plateforme **e-shop-full**. Elle reprend le thème « Afro-chic » du site Nuxt (vert forêt `#0F3D2E`, or `#C8963E`, ivoire, titres Fraunces, texte Inter) et utilise **uniquement l'API Laravel existante** (`/api/v1`).

## Lancer l'application

1. Démarrer l'API (dans `../e-shop-full`) en écoutant sur le réseau :
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
2. Lancer l'app en indiquant l'adresse de l'API :
   ```bash
   # Émulateur Android (valeur par défaut)
   flutter run
   # Téléphone réel sur le même Wi-Fi
   flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
   ```

Les images renvoyées par Laravel en `http://localhost:8000/storage/...` (APP_URL) sont automatiquement réécrites vers l'hôte de l'API, pour rester affichables depuis le téléphone.

## Fonctionnalités

| Espace | Écrans | Endpoints |
| --- | --- | --- |
| Vitrine | Accueil, boutique (6 formats + thème du marchand), fiche produit, recommandations | `home`, `storefront/{slug}`, `…/products`, `…/recommendations` |
| Achat | Panier multi-boutiques, commande, code promo, confirmation, suivi | `…/coupons/check`, `…/checkout`, `…/orders/{numéro}` |
| Assistant IA | Chat plateforme et chat de boutique | `chat`, `storefront/{slug}/chat` |
| Modèles | Galerie filtrée par format | `templates` |
| Vendeur | Connexion, inscription, mes boutiques, création assistée par IA | `auth/*`, `shops`, `ai/shop-setup` |
| Tableau de bord | Vue d'ensemble, produits (+ IA, photo), commandes, promotions, apparence, paramètres | `shops/{id}/*`, `ai/product-description` |
| Admin | Modération des produits | `admin/moderation` |

## Structure

```
lib/
  app/        thème, configuration API, formats FR, navigation, AppScope
  models/     modèles des ressources Laravel
  services/   ApiService (jeton Sanctum, erreurs 422 par champ, upload)
  state/      session vendeur, panier persistant
  widgets/    composants partagés (cartes produit, aperçu de thème…)
  screens/    storefront/, auth/, account/, merchant/, admin/
```

## Tests

```bash
flutter analyze
flutter test
```
