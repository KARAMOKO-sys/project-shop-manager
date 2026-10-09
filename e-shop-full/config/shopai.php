<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | URL of the Nuxt application. Used to redirect visitors hitting the API
    | host and to build public links to storefronts.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    |--------------------------------------------------------------------------
    | Artificial Intelligence
    |--------------------------------------------------------------------------
    |
    | "auto" uses Claude when an API key is configured and falls back to the
    | built-in local engine otherwise. "local" never calls an external API.
    |
    */

    'ai' => [
        'driver' => env('SHOPAI_AI_DRIVER', 'auto'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('SHOPAI_AI_MODEL', 'claude-opus-5-5'),
        'timeout' => (int) env('SHOPAI_AI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Moderation Agent
    |--------------------------------------------------------------------------
    |
    | Every product is reviewed before it becomes visible to the public.
    | Risk scores range from 0 to 100.
    |
    */

    'moderation' => [
        'review_threshold' => 30,
        'reject_threshold' => 70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Shop Templates
    |--------------------------------------------------------------------------
    |
    | Ready-made designs a merchant can pick when creating a shop. The theme
    | values are applied by the storefront as Bootstrap CSS variables and can
    | be overridden per shop. "format" selects the page structure:
    | classic, editorial, catalog, menu, showcase or single_product.
    |
    */

    'templates' => [
        'minimal' => [
            'name' => 'Épuré',
            'sector' => 'general',
            'description' => 'Un design sobre et élégant qui met vos produits en valeur.',
            'ideal_for' => ['Tout type de boutique', 'Premiers pas'],
            'theme' => [
                'primary' => '#212529',
                'secondary' => '#6c757d',
                'background' => '#ffffff',
                'surface' => '#f8f9fa',
                'text' => '#212529',
                'font' => 'Inter',
                'radius' => '0.5rem',
                'hero_style' => 'minimal',
                'layout' => 'grid',
                'format' => 'classic',
            ],
            'default_categories' => ['Nouveautés', 'Meilleures ventes', 'Promotions'],
        ],
        'fashion' => [
            'name' => 'Mode & Style',
            'sector' => 'mode',
            'description' => 'Mise en page magazine avec grands visuels et typographie affirmée.',
            'ideal_for' => ['Prêt-à-porter', 'Créateurs', 'Wax & pagne'],
            'theme' => [
                'primary' => '#9b2c55',
                'secondary' => '#d4a373',
                'background' => '#fffafa',
                'surface' => '#ffffff',
                'text' => '#2b2b2b',
                'font' => 'Playfair Display',
                'radius' => '0',
                'hero_style' => 'image',
                'layout' => 'masonry',
                'format' => 'editorial',
            ],
            'default_categories' => ['Femme', 'Homme', 'Accessoires', 'Wax & Pagne'],
        ],
        'food' => [
            'name' => 'Saveurs',
            'sector' => 'alimentation',
            'description' => 'Couleurs chaleureuses pour l\'épicerie et les produits du terroir.',
            'ideal_for' => ['Épiceries', 'Produits du terroir', 'Boissons'],
            'theme' => [
                'primary' => '#c2410c',
                'secondary' => '#3f6212',
                'background' => '#fffaf3',
                'surface' => '#ffffff',
                'text' => '#3d2c1e',
                'font' => 'Poppins',
                'radius' => '1rem',
                'hero_style' => 'gradient',
                'layout' => 'grid',
                'format' => 'classic',
            ],
            'default_categories' => ['Épicerie', 'Boissons', 'Produits frais', 'Paniers cadeaux'],
        ],
        'craft' => [
            'name' => 'Artisan',
            'sector' => 'artisanat',
            'description' => 'Une vitrine spacieuse pour raconter vos créations faites main.',
            'ideal_for' => ['Artisans', 'Créations faites main', 'Art africain'],
            'theme' => [
                'primary' => '#8b5e34',
                'secondary' => '#a3b18a',
                'background' => '#fbf7f2',
                'surface' => '#ffffff',
                'text' => '#3a2e25',
                'font' => 'Lora',
                'radius' => '0.75rem',
                'hero_style' => 'image',
                'layout' => 'grid',
                'format' => 'showcase',
            ],
            'default_categories' => ['Bijoux', 'Décoration', 'Maroquinerie', 'Vannerie'],
        ],
        'tech' => [
            'name' => 'High-Tech',
            'sector' => 'electronique',
            'description' => 'Catalogue avec filtres pour présenter beaucoup de références.',
            'ideal_for' => ['Téléphonie', 'Informatique', 'Accessoires'],
            'theme' => [
                'primary' => '#1d4ed8',
                'secondary' => '#0891b2',
                'background' => '#f5f8ff',
                'surface' => '#ffffff',
                'text' => '#0b1736',
                'font' => 'Space Grotesk',
                'radius' => '0.75rem',
                'hero_style' => 'gradient',
                'layout' => 'grid',
                'format' => 'catalog',
            ],
            'default_categories' => ['Téléphones', 'Ordinateurs', 'Audio', 'Accessoires'],
        ],
        'beauty' => [
            'name' => 'Beauté & Bien-être',
            'sector' => 'beaute',
            'description' => 'Pastels doux et mise en page aérée pour cosmétiques et soins.',
            'ideal_for' => ['Cosmétiques', 'Soins naturels', 'Parfums'],
            'theme' => [
                'primary' => '#9d5c63',
                'secondary' => '#e5989b',
                'background' => '#fff8f6',
                'surface' => '#ffffff',
                'text' => '#4a3b3d',
                'font' => 'Poppins',
                'radius' => '1.25rem',
                'hero_style' => 'gradient',
                'layout' => 'list',
                'format' => 'classic',
            ],
            'default_categories' => ['Soins visage', 'Soins corps', 'Cheveux', 'Parfums'],
        ],
        'restaurant' => [
            'name' => 'Restaurant & Traiteur',
            'sector' => 'restauration',
            'description' => 'Votre carte en ligne par rubriques, avec commande en un clic.',
            'ideal_for' => ['Restaurants', 'Traiteurs', 'Fast-food', 'Pâtisseries'],
            'theme' => [
                'primary' => '#9a3412',
                'secondary' => '#ca8a04',
                'background' => '#fdf8f1',
                'surface' => '#ffffff',
                'text' => '#2d1b0e',
                'font' => 'Lora',
                'radius' => '0.75rem',
                'hero_style' => 'image',
                'layout' => 'list',
                'format' => 'menu',
            ],
            'default_categories' => ['Entrées', 'Plats', 'Grillades', 'Desserts', 'Boissons'],
        ],
        'single' => [
            'name' => 'Produit Star',
            'sector' => 'produit-unique',
            'description' => 'Une page de vente percutante pour un seul produit phare.',
            'ideal_for' => ['Dropshipping', 'Lancement de produit', 'Produit unique'],
            'theme' => [
                'primary' => '#111827',
                'secondary' => '#facc15',
                'background' => '#ffffff',
                'surface' => '#f9fafb',
                'text' => '#111827',
                'font' => 'Montserrat',
                'radius' => '0.75rem',
                'hero_style' => 'minimal',
                'layout' => 'grid',
                'format' => 'single_product',
            ],
            'default_categories' => ['Produit phare', 'Accessoires'],
        ],
        'luxury' => [
            'name' => 'Luxe & Bijoux',
            'sector' => 'luxe',
            'description' => 'Noir et or, très peu de produits, beaucoup d\'élégance.',
            'ideal_for' => ['Bijouterie', 'Montres', 'Maroquinerie de luxe'],
            'theme' => [
                'primary' => '#1c1917',
                'secondary' => '#b8860b',
                'background' => '#faf9f6',
                'surface' => '#ffffff',
                'text' => '#1c1917',
                'font' => 'Playfair Display',
                'radius' => '0',
                'hero_style' => 'minimal',
                'layout' => 'grid',
                'format' => 'showcase',
            ],
            'default_categories' => ['Colliers', 'Bagues', 'Bracelets', 'Coffrets'],
        ],
        'market' => [
            'name' => 'Grand Marché',
            'sector' => 'general',
            'description' => 'Un supermarché en ligne pour les boutiques aux nombreux rayons.',
            'ideal_for' => ['Supérettes', 'Boutiques multi-rayons', 'Grossistes'],
            'theme' => [
                'primary' => '#15803d',
                'secondary' => '#ea580c',
                'background' => '#f7faf7',
                'surface' => '#ffffff',
                'text' => '#14261a',
                'font' => 'DM Sans',
                'radius' => '0.5rem',
                'hero_style' => 'gradient',
                'layout' => 'grid',
                'format' => 'catalog',
            ],
            'default_categories' => ['Alimentation', 'Maison', 'Hygiène', 'Bébé', 'Électroménager'],
        ],
        'kids' => [
            'name' => 'Enfants & Jouets',
            'sector' => 'enfants',
            'description' => 'Couleurs joyeuses et formes arrondies pour l\'univers des enfants.',
            'ideal_for' => ['Jouets', 'Vêtements enfants', 'Puériculture'],
            'theme' => [
                'primary' => '#0e7490',
                'secondary' => '#f59e0b',
                'background' => '#fffdf5',
                'surface' => '#ffffff',
                'text' => '#1e293b',
                'font' => 'Nunito',
                'radius' => '1.5rem',
                'hero_style' => 'gradient',
                'layout' => 'grid',
                'format' => 'classic',
            ],
            'default_categories' => ['Jouets', 'Vêtements', 'Puériculture', 'Livres'],
        ],
        'home' => [
            'name' => 'Maison & Déco',
            'sector' => 'maison',
            'description' => 'Ambiance magazine déco aux tons naturels pour inspirer vos clients.',
            'ideal_for' => ['Décoration', 'Mobilier', 'Linge de maison'],
            'theme' => [
                'primary' => '#4d6b53',
                'secondary' => '#c9a66b',
                'background' => '#f8f6f1',
                'surface' => '#ffffff',
                'text' => '#2f2a24',
                'font' => 'Lora',
                'radius' => '0.25rem',
                'hero_style' => 'image',
                'layout' => 'masonry',
                'format' => 'editorial',
            ],
            'default_categories' => ['Salon', 'Chambre', 'Cuisine', 'Luminaires'],
        ],
        'sport' => [
            'name' => 'Sport & Fitness',
            'sector' => 'sport',
            'description' => 'Un design énergique et contrasté pour l\'équipement sportif.',
            'ideal_for' => ['Équipement sportif', 'Nutrition', 'Vêtements de sport'],
            'theme' => [
                'primary' => '#b91c1c',
                'secondary' => '#111827',
                'background' => '#ffffff',
                'surface' => '#f3f4f6',
                'text' => '#111827',
                'font' => 'Montserrat',
                'radius' => '0.5rem',
                'hero_style' => 'gradient',
                'layout' => 'grid',
                'format' => 'classic',
            ],
            'default_categories' => ['Football', 'Fitness', 'Running', 'Nutrition'],
        ],
        'organic' => [
            'name' => 'Bio & Fermier',
            'sector' => 'agriculture',
            'description' => 'Paniers de la ferme présentés comme une carte de saison.',
            'ideal_for' => ['Producteurs', 'Fruits & légumes', 'Produits bio'],
            'theme' => [
                'primary' => '#3f6212',
                'secondary' => '#b45309',
                'background' => '#fbfaf3',
                'surface' => '#ffffff',
                'text' => '#273016',
                'font' => 'Nunito',
                'radius' => '1rem',
                'hero_style' => 'image',
                'layout' => 'list',
                'format' => 'menu',
            ],
            'default_categories' => ['Fruits', 'Légumes', 'Céréales', 'Paniers de saison'],
        ],
    ],

];
