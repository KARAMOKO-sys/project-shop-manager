<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductService;
use App\Services\ShopService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo shops and the default products shown on the landing page.
     *
     * @var list<array{name: string, template: string, tagline: string, description: string, products: list<array{name: string, category: string, price: int, compare_at_price?: int, stock: int, description: string, tags: list<string>, featured?: bool}>}>
     */
    private const DEMO_SHOPS = [
        [
            'name' => 'Wax & Élégance',
            'template' => 'fashion',
            'tagline' => 'La mode africaine contemporaine, cousue à Abidjan.',
            'description' => 'Créations en wax et bogolan imaginées par des couturières ivoiriennes, du prêt-à-porter aux pièces sur mesure.',
            'products' => [
                ['name' => 'Robe portefeuille en wax', 'category' => 'Femme', 'price' => 18_500, 'compare_at_price' => 22_000, 'stock' => 12, 'featured' => true, 'description' => 'Robe portefeuille en coton wax 100 %, coupe midi qui s\'ajuste à toutes les silhouettes. Ceinture assortie incluse.', 'tags' => ['wax', 'robe', 'femme']],
                ['name' => 'Chemise homme bogolan', 'category' => 'Homme', 'price' => 14_000, 'stock' => 20, 'description' => 'Chemise manches courtes en bogolan teint à la main au Mali, col mao et boutons en bois.', 'tags' => ['bogolan', 'chemise', 'homme']],
                ['name' => 'Sac cabas en pagne tissé', 'category' => 'Accessoires', 'price' => 9_500, 'stock' => 15, 'featured' => true, 'description' => 'Grand cabas doublé en pagne tissé baoulé, anses en cuir, idéal pour le marché comme pour la plage.', 'tags' => ['sac', 'pagne', 'accessoire']],
                ['name' => 'Ensemble boubou brodé', 'category' => 'Wax & Pagne', 'price' => 35_000, 'stock' => 6, 'description' => 'Grand boubou en bazin riche brodé main, livré avec pantalon assorti. Parfait pour les cérémonies.', 'tags' => ['boubou', 'bazin', 'ceremonie']],
            ],
        ],
        [
            'name' => 'Saveurs du Terroir',
            'template' => 'food',
            'tagline' => 'Le meilleur de nos terroirs, livré chez vous.',
            'description' => 'Épicerie fine ouest-africaine : produits sélectionnés directement auprès des coopératives locales.',
            'products' => [
                ['name' => 'Miel pur de la Comoé 500 g', 'category' => 'Épicerie', 'price' => 4_500, 'stock' => 40, 'featured' => true, 'description' => 'Miel brut récolté par une coopérative d\'apiculteurs du nord ivoirien, non chauffé et non filtré.', 'tags' => ['miel', 'naturel', 'terroir']],
                ['name' => 'Café Robusta moulu de Man 250 g', 'category' => 'Boissons', 'price' => 3_000, 'stock' => 50, 'description' => 'Robusta torréfié artisanalement à Man, notes intenses de cacao. Mouture adaptée aux cafetières filtre.', 'tags' => ['cafe', 'robusta', 'man']],
                ['name' => 'Jus de bissap artisanal 1 L', 'category' => 'Boissons', 'price' => 1_500, 'stock' => 60, 'description' => 'Infusion de fleurs d\'hibiscus, gingembre frais et menthe, peu sucrée. À conserver au frais.', 'tags' => ['bissap', 'jus', 'hibiscus']],
                ['name' => 'Panier découverte du terroir', 'category' => 'Paniers cadeaux', 'price' => 15_000, 'compare_at_price' => 18_000, 'stock' => 10, 'featured' => true, 'description' => 'Un panier en osier garni : miel, café, attiéké sec, piment séché et confiture de mangue. Idéal à offrir.', 'tags' => ['cadeau', 'panier', 'decouverte']],
            ],
        ],
        [
            'name' => 'Karité Nature',
            'template' => 'beauty',
            'tagline' => 'Des soins naturels inspirés des rituels africains.',
            'description' => 'Cosmétiques naturels fabriqués au Burkina Faso à partir de beurre de karité et d\'huiles végétales.',
            'products' => [
                ['name' => 'Beurre de karité brut 250 g', 'category' => 'Soins corps', 'price' => 3_500, 'stock' => 80, 'featured' => true, 'description' => 'Beurre de karité non raffiné, extrait à froid par des femmes de coopératives burkinabè. Nourrit peau et cheveux.', 'tags' => ['karite', 'naturel', 'peau']],
                ['name' => 'Savon noir à l\'huile de palme', 'category' => 'Soins corps', 'price' => 1_500, 'stock' => 100, 'description' => 'Savon noir traditionnel saponifié à froid, nettoie en douceur les peaux sensibles.', 'tags' => ['savon', 'savon noir']],
                ['name' => 'Huile de baobab pour cheveux 100 ml', 'category' => 'Cheveux', 'price' => 5_000, 'stock' => 30, 'description' => 'Huile vierge de baobab qui renforce et fait briller les cheveux crépus et bouclés.', 'tags' => ['baobab', 'cheveux', 'huile']],
            ],
        ],
        [
            'name' => 'Atelier Kente',
            'template' => 'craft',
            'tagline' => 'Artisanat d\'art fait main, pièce par pièce.',
            'description' => 'Bijoux, sculptures et objets de décoration créés par des artisans du Ghana et de Côte d\'Ivoire.',
            'products' => [
                ['name' => 'Collier en perles de Krobo', 'category' => 'Bijoux', 'price' => 7_500, 'stock' => 18, 'featured' => true, 'description' => 'Collier en perles de verre recyclé façonnées à la main selon la tradition Krobo du Ghana.', 'tags' => ['perles', 'collier', 'fait main']],
                ['name' => 'Masque décoratif en bois d\'ébène', 'category' => 'Décoration', 'price' => 25_000, 'stock' => 4, 'description' => 'Masque sculpté à la main inspiré des masques Gouro, finition cire naturelle. Pièce unique.', 'tags' => ['masque', 'sculpture', 'decoration']],
                ['name' => 'Panier tressé Bolga', 'category' => 'Vannerie', 'price' => 12_000, 'stock' => 9, 'description' => 'Panier en herbe d\'éléphant tressé à Bolgatanga, anse gainée de cuir. Solide et coloré.', 'tags' => ['panier', 'vannerie', 'bolga']],
            ],
        ],
        [
            'name' => 'Chez Tantie Rose',
            'template' => 'restaurant',
            'tagline' => 'La cuisine ivoirienne comme à la maison, livrée chaude.',
            'description' => 'Maquis familial de Cocody : garba, alloco, poulet braisé et jus maison, préparés chaque jour avec des produits frais.',
            'products' => [
                ['name' => 'Garba complet', 'category' => 'Plats', 'price' => 1_500, 'stock' => 50, 'featured' => true, 'description' => 'Attiéké, thon frit, piment frais, oignons et tomates. Le classique d\'Abidjan.', 'tags' => ['garba', 'attieke']],
                ['name' => 'Poulet braisé et alloco', 'category' => 'Grillades', 'price' => 4_500, 'stock' => 30, 'description' => 'Demi-poulet mariné et braisé au feu de bois, servi avec bananes plantain frites.', 'tags' => ['poulet', 'alloco']],
                ['name' => 'Poisson braisé attiéké', 'category' => 'Grillades', 'price' => 5_000, 'stock' => 20, 'description' => 'Carpe braisée aux épices, attiéké et sauce tomate-oignon.', 'tags' => ['poisson', 'braise']],
                ['name' => 'Salade d\'avocat', 'category' => 'Entrées', 'price' => 1_500, 'stock' => 25, 'description' => 'Avocat, tomates, oignons rouges et vinaigrette citronnée.', 'tags' => ['salade', 'avocat']],
                ['name' => 'Dégué au lait caillé', 'category' => 'Desserts', 'price' => 1_000, 'stock' => 40, 'description' => 'Couscous de mil au lait caillé sucré et vanillé, servi frais.', 'tags' => ['degue', 'dessert']],
                ['name' => 'Jus de gingembre maison', 'category' => 'Boissons', 'price' => 800, 'stock' => 60, 'description' => 'Gingembre frais pressé, citron vert et une touche d\'ananas.', 'tags' => ['gingembre', 'jus']],
            ],
        ],
        [
            'name' => 'SolarLight',
            'template' => 'single',
            'tagline' => 'La lumière gratuite du soleil, toute la nuit.',
            'description' => 'Une lampe solaire puissante pensée pour les coupures de courant et les zones non raccordées.',
            'products' => [
                ['name' => 'Lampe solaire SolarLight 300', 'category' => 'Produit phare', 'price' => 15_000, 'compare_at_price' => 19_000, 'stock' => 100, 'featured' => true, 'description' => "Panneau solaire intégré, 12 heures d'éclairage après une journée de charge.\nPort USB pour recharger votre téléphone.\nRésiste à la pluie (IP65).\nGarantie 1 an.", 'tags' => ['lampe', 'solaire', 'eclairage']],
                ['name' => 'Câble USB multi-embouts', 'category' => 'Accessoires', 'price' => 2_000, 'stock' => 80, 'description' => 'Câble 3 en 1 (micro-USB, USB-C, Lightning) pour recharger tous vos téléphones sur la lampe.', 'tags' => ['cable', 'usb']],
            ],
        ],
    ];

    /**
     * Listings the moderation agent must stop, to showcase the review queue.
     *
     * @var list<array{name: string, price: int, compare_at_price?: int, description: string}>
     */
    private const SUSPICIOUS_PRODUCTS = [
        ['name' => 'Montre Rolex Submariner', 'price' => 25_000, 'description' => 'Montre Rolex qualité 1:1, réplique parfaite. Contactez-moi sur WhatsApp au +225 07 00 00 00 00.'],
        ['name' => 'Crème éclaircissante miracle', 'price' => 5_000, 'compare_at_price' => 60_000, 'description' => 'Résultats visibles en 3 jours sur toutes les taches. Stock limité, commandez vite !'],
    ];

    public function run(ShopService $shops, ProductService $products): void
    {
        User::firstOrCreate(
            ['email' => 'admin@shopai.test'],
            ['name' => 'Admin ShopAI', 'password' => 'password'],
        )->forceFill(['is_admin' => true])->save();

        $merchant = User::firstOrCreate(
            ['email' => 'marchand@shopai.test'],
            ['name' => 'Aïcha Koné', 'password' => 'password'],
        );

        foreach (self::DEMO_SHOPS as $demo) {
            if (Shop::where('name', $demo['name'])->exists()) {
                continue;
            }

            $categories = collect($demo['products'])->pluck('category')->unique()->values()->all();

            $shop = $shops->create($merchant, [
                'name' => $demo['name'],
                'template' => $demo['template'],
                'tagline' => $demo['tagline'],
                'description' => $demo['description'],
                'categories' => $categories,
            ]);

            foreach ($demo['products'] as $product) {
                $products->create($shop, [
                    'name' => $product['name'],
                    'category_id' => $shop->categories()->where('name', $product['category'])->value('id'),
                    'price' => $product['price'],
                    'compare_at_price' => $product['compare_at_price'] ?? null,
                    'stock_quantity' => $product['stock'],
                    'description' => $product['description'],
                    'tags' => $product['tags'],
                    'is_featured' => $product['featured'] ?? false,
                    'status' => ProductStatus::Published,
                ]);
            }
        }

        $showcaseShop = Shop::where('name', 'Atelier Kente')->first();

        foreach (self::SUSPICIOUS_PRODUCTS as $product) {
            if ($showcaseShop->products()->where('name', $product['name'])->exists()) {
                continue;
            }

            $products->create($showcaseShop, [...$product, 'stock_quantity' => 5, 'status' => ProductStatus::Published]);
        }

        $showcaseShop->coupons()->firstOrCreate(
            ['code' => 'BIENVENUE10'],
            ['type' => 'percentage', 'value' => 10, 'usage_limit' => 0, 'status' => 'active'],
        );
    }
}
