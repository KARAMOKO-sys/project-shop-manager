<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ERP Modules
    |--------------------------------------------------------------------------
    |
    | Catalogue of the business applications a merchant can install on a shop,
    | in the spirit of Odoo "Apps". "core" modules are always installed and
    | cannot be removed. "depends" lists the modules installed automatically
    | first. Uninstalling a module keeps its data: reinstalling restores it.
    |
    */

    'modules' => [
        'sales' => [
            'name' => 'Ventes & boutique en ligne',
            'summary' => 'Catalogue, vitrine, commandes, codes promo et paiements.',
            'category' => 'Ventes',
            'icon' => 'bi-shop',
            'depends' => [],
            'core' => true,
        ],
        'inventory' => [
            'name' => 'Inventaire',
            'summary' => 'Entrepôts, mouvements de stock tracés, inventaires physiques, transferts et réapprovisionnement intelligent.',
            'category' => 'Opérations',
            'icon' => 'bi-boxes',
            'depends' => [],
            'core' => false,
        ],
        'purchase' => [
            'name' => 'Achats',
            'summary' => 'Fournisseurs, bons de commande, réceptions partielles et coût moyen pondéré.',
            'category' => 'Opérations',
            'icon' => 'bi-truck',
            'depends' => ['inventory'],
            'core' => false,
        ],
        'invoicing' => [
            'name' => 'Facturation',
            'summary' => 'Factures numérotées depuis les commandes, TVA, encaissements et créances clients.',
            'category' => 'Finance',
            'icon' => 'bi-receipt-cutoff',
            'depends' => [],
            'core' => false,
        ],
        'expenses' => [
            'name' => 'Dépenses',
            'summary' => 'Loyer, salaires, transport, marketing : suivez vos charges et votre bénéfice réel.',
            'category' => 'Finance',
            'icon' => 'bi-wallet2',
            'depends' => [],
            'core' => false,
        ],
        'crm' => [
            'name' => 'CRM',
            'summary' => 'Pipeline d’opportunités, prévision pondérée et conversion des prospects en clients.',
            'category' => 'Ventes',
            'icon' => 'bi-kanban',
            'depends' => [],
            'core' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    |
    | "sales_window_days" is the history used to compute the sales velocity of
    | each product; "coverage_days" is how many days of sales a replenishment
    | should cover on top of the supplier lead time.
    |
    */

    'inventory' => [
        'sales_window_days' => 30,
        'coverage_days' => 14,
        'default_lead_time_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enterprise Service Bus (ESB) bridge
    |--------------------------------------------------------------------------
    |
    | Business events are written to the transactional outbox in the same
    | database transaction as the change itself, then relayed to the ESB
    | (Apache Camel, WSO2, MuleSoft, RabbitMQ HTTP gateway…) by the
    | `erp:outbox:publish` command. Each message is signed with HMAC-SHA256
    | in the X-ShopAI-Signature header. Without an endpoint nothing leaves
    | the platform and messages simply wait in the outbox.
    |
    */

    'esb' => [
        'endpoint' => env('ERP_ESB_ENDPOINT'),
        'secret' => env('ERP_ESB_SECRET'),
        'timeout' => (int) env('ERP_ESB_TIMEOUT', 10),
        'batch_size' => (int) env('ERP_ESB_BATCH_SIZE', 100),
        'max_attempts' => (int) env('ERP_ESB_MAX_ATTEMPTS', 10),
    ],

];
