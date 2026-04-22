<?php

/**
 * Tenant-level configuration (slug rules, reserved namespaces).
 *
 * Edit this file instead of scattering magic strings across controllers,
 * requests and tests. Reserved slugs are pre-populated with every path
 * segment the platform currently reserves for system or SPA routes.
 */
return [
    /*
     * Regex for restaurant slugs. Must match from start to end.
     * Rules enforced:
     *  - lowercase alphanumerics and single hyphens
     *  - must start and end with an alphanumeric
     *  - no consecutive hyphens (enforced by the ValidSlug rule)
     */
    'slug_regex' => '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',

    'slug_min_length' => 3,
    'slug_max_length' => 50,

    /*
     * Reserved slugs — registering a restaurant with any of these is
     * forbidden because they collide with current or future platform
     * routes (SPA universal paths, admin, billing, webhooks…).
     *
     * Keep the list lower-case. Comparisons are done case-insensitively
     * via the ValidSlug rule.
     */
    'reserved_slugs' => [
        // Platform / SaaS surfaces
        'admin', 'super', 'superadmin', 'dashboard', 'settings', 'billing',
        'auth', 'login', 'register', 'logout', 'signup', 'help', 'support',
        'about', 'contact', 'terms', 'privacy', 'health', 'up',

        // Technical
        'api', 'app', 'www', 'stripe', 'webhook', 'static', 'assets',
        'public', '_next', 'cdn', 'media', 'images',

        // SPA universal path segments
        'r', 'b', 's', 'menu', 'cart', 'delivery', 'payment', 'confirmed',
        'checkout', 'orders', 'categories', 'products',
    ],
];
