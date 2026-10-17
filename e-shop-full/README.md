# ShopAI - Plateforme e-commerce multi-tenant propulsée par l'IA

Chaque commerçant crée sa boutique en quelques minutes, choisit un modèle de design et le personnalise sans code. L'IA l'assiste à chaque étape, et un agent de modération vérifie chaque produit avant qu'il soit visible du public.

- **API** : Laravel 13 (ce dossier), versionnée sous `/api/v1`, authentification par jetons Sanctum
- **Frontend** : Nuxt + Bootstrap 5, dans [frontend/](frontend/)

## Fonctionnalités

- Création de boutique guidée par l'IA, à partir d'une phrase décrivant l'activité
- 6 modèles de design (Épuré, Mode, Saveurs, Artisan, High-Tech, Beauté), personnalisables : couleurs, police, arrondis, style de bannière, mise en page
- Catalogue, catégories, gestion du stock, commandes avec transitions de statut contrôlées
- Checkout sécurisé : prix, stock et codes promo calculés côté serveur ; paiement à la livraison ou paiement en ligne hébergé par Flutterwave (carte et moyens locaux disponibles pour le compte du marchand)
- Configuration de Flutterwave par boutique avec secrets chiffrés, vérification des transactions côté serveur et validation des webhooks
- Codes promo gérés par le marchand, avec vérification au moment du paiement
- Suivi de commande pour le client, à partir du numéro de commande et de l'email
- Emails : confirmation de commande au client, alerte au marchand, changement de statut, alerte de stock bas
- Recommandations « Les clients ont aussi acheté »
- Photos de produits et logos envoyés depuis le tableau de bord
- Tableau de bord : chiffre d'affaires des 30 derniers jours, produits en stock bas
- Rédaction IA des descriptions produits et des mots-clés
- **Agent de modération anti-fraude** : produits interdits, contrefaçons, arnaques, prix suspects, contacts hors plateforme. Verdicts possibles :
  - `approved` : produit visible du public
  - `pending_review` : produit en attente de vérification humaine (file d'attente admin)
  - `rejected` : produit refusé
- Chatbot de la plateforme et chatbot de service client 24/7 dans chaque boutique (suivi de commande, recherche de produits)
- Page d'accueil avec des produits mis en avant par défaut

## Moteur IA

| `SHOPAI_AI_DRIVER` | Comportement |
| --- | --- |
| `auto` (défaut) | Utilise Claude si `ANTHROPIC_API_KEY` est défini, sinon le moteur local |
| `local` | Règles et heuristiques uniquement, aucun appel externe |

Le moteur local fonctionne sans clé API. Quand Claude est actif, l'agent de modération combine les deux avis et garde le plus prudent. Si un appel à Claude échoue, le moteur local prend le relais.

Les emails, ainsi que les vérifications de produits faites par Claude, passent par la file d'attente : lancez un worker avec `php artisan queue:work`. En local, `MAIL_MAILER=log` écrit les emails dans `storage/logs/laravel.log`.

## Démarrage

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link     # photos envoyées par les marchands
php artisan serve            # API sur http://localhost:8000
php artisan queue:work       # dans un autre terminal : emails et IA

cd frontend && npm install && npm run dev   # site sur http://localhost:3000
```

Comptes de démonstration (mot de passe `password`) :

- `admin@shopai.test` : administrateur, accès à la file de modération
- `marchand@shopai.test` : marchand, propriétaire des 4 boutiques de démonstration

Le seed crée aussi 2 produits suspects, pour montrer l'agent de modération en action, et le code promo `BIENVENUE10` sur la boutique Atelier Kente.

Pour activer Flutterwave, ouvrez **Paramètres → Paiements** dans le tableau de bord de la boutique, saisissez la clé secrète et le secret hash du webhook fournis par le compte Flutterwave du marchand, puis activez les paiements en ligne. Enregistrez l’URL de webhook affichée par le tableau de bord Flutterwave. Commencez avec les identifiants de test ; les identifiants de production ne doivent jamais être partagés ni ajoutés au dépôt. Le client est redirigé vers la page de paiement hébergée par Flutterwave, et une commande n’est marquée comme payée qu’après vérification serveur du montant, de la devise et de la référence.

## Tests

```bash
php artisan test --compact
```
