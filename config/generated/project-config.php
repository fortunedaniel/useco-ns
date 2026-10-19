<?php

// =============================================================================
// FICHIER GÉNÉRÉ AUTOMATIQUEMENT - NE PAS MODIFIER DIRECTEMENT
// Source : config/src/**/*.ts
// Générateur : scripts/generate-project-config.ts
// Toute modification manuelle sera remplacée par npm run config/generate/qa.
// =============================================================================
defined('ABSPATH') || exit;

return [
    'project' => [
        'name' => 'ComKani Starter',
        'slug' => 'comkani-starter',
        'type' => 'multipage',
        'language' => 'fr_FR',
        'timezone' => 'Europe/Paris',
        'tagline' => '',
    ],
    'organization' => [
        'name' => '',
        'legalName' => '',
        'description' => '',
        'email' => '',
        'privacyEmail' => '',
        'phone' => '',
        'address' => '',
        'city' => '',
        'postalCode' => '',
        'country' => 'France',
        'areaServed' => [],
        'sameAs' => [],
        'legalForm' => '',
        'siren' => '',
        'siret' => '',
        'vatNumber' => '',
        'capital' => '',
        'registration' => '',
        'publicationDirector' => '',
        'professionalRegistration' => [
            'organization' => '',
            'region' => '',
            'number' => '',
        ],
        'hosting' => [
            'name' => '',
            'legalName' => '',
            'address' => '',
            'postalCode' => '',
            'city' => '',
            'country' => '',
            'phone' => '',
            'url' => '',
        ],
    ],
    'designSystem' => [
        'theme' => [
            'defaultMode' => 'light',
            'supportsDarkMode' => true,
        ],
        'tokens' => [
            'light' => 'config/tokens/colors/light.tokens.json',
            'dark' => 'config/tokens/colors/dark.tokens.json',
            'typography' => 'config/tokens/typography',
            'spacings' => 'config/tokens/spacings',
            'borders' => 'config/tokens/borders.tokens.json',
            'radius' => 'config/tokens/radius.tokens.json',
            'shadows' => 'config/tokens/shadows.tokens.json',
            'motions' => 'config/tokens/motions.tokens.json',
            'grid' => 'config/tokens/grid.tokens.json',
            'effects' => 'config/tokens/effects.tokens.json',
        ],
        'foundations' => [
            'spacings' => 'responsive',
            'radius' => 'figma',
            'borders' => 'figma',
            'shadows' => 'figma',
            'motions' => 'figma',
            'grid' => 'responsive',
            'effects' => [
                'glass',
            ],
        ],
        'typography' => [
            'primary' => [
                'family' => 'Inter',
            ],
            'secondary' => [
                'family' => 'Manrope',
            ],
        ],
    ],
    'footer' => [
        'socials' => [],
        'credit' => [
            'label' => 'Agence ComKani',
            'url' => 'https://www.comkani.fr/',
        ],
    ],
    'pages' => [
        [
            'title' => 'Accueil',
            'slug' => 'accueil',
            'home' => true,
        ],
        [
            'title' => 'Actualités',
            'slug' => 'actualites',
        ],
    ],
    'wordpress' => [
        'posts' => [
            'enabled' => true,
            'indexPage' => 'actualites',
            'permalinkStructure' => '/%postname%/',
            'labels' => [
                'singular' => 'Article',
                'plural' => 'Articles',
                'gender' => 'masculine',
            ],
        ],
    ],
    'legalPages' => [
        'mentions' => false,
        'privacyCookies' => false,
        'cgv' => false,
        'cgu' => false,
        'shippingReturns' => false,
        'refund' => false,
        'bookingTerms' => false,
    ],
    'menus' => [
        'primary' => [],
        'footer' => [],
        'legal' => [],
    ],
    'postTypes' => [],
    'components' => [
        'contact' => false,
        'booking' => false,
        'faq' => false,
        'whatsapp' => false,
        'testimonials' => false,
    ],
    'header' => [
        'actions' => [],
    ],
    'faq' => [
        'groups' => [],
    ],
    'contact' => [
        'modal' => false,
        'submitLabel' => 'Envoyer ma demande',
        'subjects' => [
            [
                'value' => 'expertise-comptable',
                'label' => 'Expertise comptable',
            ],
            [
                'value' => 'conseil-strategique',
                'label' => 'Conseil stratégique',
            ],
            [
                'value' => 'daf-externalisee',
                'label' => 'DAF externalisée',
            ],
            [
                'value' => 'pilotage-financier',
                'label' => 'Pilotage financier',
            ],
            [
                'value' => 'audit',
                'label' => 'Audit',
            ],
            [
                'value' => 'gestion-sociale',
                'label' => 'Gestion sociale',
            ],
            [
                'value' => 'commissariat-aux-comptes',
                'label' => 'Commissariat aux comptes',
            ],
            [
                'value' => 'autre-demande',
                'label' => 'Autre demande',
            ],
        ],
    ],
    'booking' => [
        'availableWeekdays' => [
            1,
            2,
            3,
            4,
            5,
        ],
        'timeSlots' => [
            '09:00',
            '09:30',
            '10:00',
            '10:30',
            '11:00',
            '11:30',
            '14:00',
            '14:30',
            '15:00',
            '15:30',
            '16:00',
            '16:30',
            '17:00',
        ],
        'minLeadHours' => 4,
        'maxDaysAhead' => 60,
        'capacityPerSlot' => 1,
    ],
    'requests' => [
        'recipientEmail' => '',
        'retentionDays' => 365,
        'rateLimitPerHour' => 5,
        'trackSourcePage' => true,
        'trackRequestType' => true,
        'trackUtm' => true,
    ],
    'login' => [
        'enabled' => false,
        'slug' => '',
    ],
    'security' => [
        'xmlrpc' => false,
        'applicationPasswords' => true,
        'publicUserRestEndpoints' => false,
        'authorEnumeration' => false,
        'allowSvgForAgency' => true,
        'allowZipForAgency' => true,
    ],
    'roles' => [
        'clientManagerCanManageMenus' => false,
        'editorCanManageFaq' => true,
        'editorCanManageRequests' => false,
    ],
    'admin' => [
        'menuOrder' => [
            'dashboard',
            'comkani',
            'pages',
            'media',
            'requests',
            'faq',
            'users',
        ],
    ],
    'seo' => [
        'enabled' => true,
        'technical' => true,
        'content' => true,
        'local' => true,
        'entity' => true,
        'aeo' => true,
        'geo' => true,
        'sxo' => true,
        'breadcrumbs' => false,
        'organizationType' => 'ProfessionalService',
        'schema' => [
            'enabled' => true,
            'website' => true,
            'webPage' => true,
            'organization' => true,
            'localBusiness' => false,
            'professionalService' => true,
            'service' => true,
            'breadcrumbList' => false,
            'faqPage' => true,
            'article' => false,
            'person' => false,
        ],
        'yoast' => [
            'enabled' => true,
            'avoidDuplicateSchema' => true,
        ],
        'indexing' => [
            'development' => false,
            'staging' => false,
            'production' => true,
        ],
    ],
    'consent' => [
        'enabled' => true,
        'cookieName' => 'comkani_consent',
        'cookieLifetimeDays' => 180,
        'policyPage' => 'privacyCookies',
        'defaultLanguage' => 'fr',
        'categories' => [
            'necessary' => [
                'required' => true,
            ],
            'analytics' => [
                'required' => false,
            ],
        ],
        'translations' => [
            'fr' => [
                'labels' => [
                    'title' => 'Vos préférences de confidentialité',
                    'intro' => 'Nous utilisons des cookies nécessaires au fonctionnement du site et, avec votre accord, des outils de mesure d’audience.',
                    'acceptAll' => 'Tout accepter',
                    'refuseAll' => 'Tout refuser',
                    'customize' => 'Paramétrer',
                    'save' => 'Enregistrer mes choix',
                    'close' => 'Fermer',
                    'learnMore' => 'En savoir plus',
                    'manage' => 'Gérer mes cookies',
                    'settingsTitle' => 'Préférences de confidentialité',
                    'settingsIntro' => 'Choisissez les catégories que vous souhaitez autoriser.',
                    'alwaysActive' => 'Toujours actif',
                    'allow' => 'Autoriser',
                ],
                'categories' => [
                    'necessary' => [
                        'label' => 'Nécessaires',
                        'description' => 'Indispensables au fonctionnement, à la sécurité et aux formulaires du site.',
                    ],
                    'analytics' => [
                        'label' => 'Mesure d’audience',
                        'description' => 'Permet de mesurer l’utilisation du site lorsque vous donnez votre accord.',
                    ],
                ],
            ],
        ],
        'services' => [
            'ga4' => [
                'enabled' => false,
                'category' => 'analytics',
                'measurementId' => '',
            ],
            'gtm' => [
                'enabled' => false,
                'category' => 'analytics',
                'containerId' => '',
            ],
            'hotjar' => [
                'enabled' => false,
                'category' => 'analytics',
                'siteId' => '',
            ],
            'externalScripts' => [],
        ],
    ],
    'sharedContent' => [
        'clients' => [
            'enabled' => false,
            'pages' => [],
        ],
        'steps' => [
            'enabled' => false,
            'pages' => [],
            'defaults' => [],
        ],
    ],
    'duplication' => [
        'enabled' => true,
        'postTypes' => [
            'page' => true,
            'post' => true,
        ],
    ],
    'pageEditor' => [
        'enabled' => true,
        'pages' => [],
    ],
];
