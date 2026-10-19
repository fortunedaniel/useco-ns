<?php

// =============================================================================
// Fichier : components/faq/faq.php
// Rôle : affiche les groupes de FAQ associés à une page WordPress
//
// Ce qu'il fait :
// - récupère les FAQ sélectionnées dans l'administration
// - reprend le titre du groupe défini dans la configuration projet
// - affiche trois questions par défaut puis permet d'afficher les suivantes
// - utilise details/summary pour rester accessible sans JavaScript
// - ajoute le Schema FAQPage correspondant aux questions disponibles
//
// Ce qu'il ne fait pas :
// - ne modifie pas les FAQ enregistrées dans WordPress
// - ne crée pas de groupe de FAQ dans l'administration
//
// Fichiers liés :
// - packages/comkani-core/includes/faq.php
// - components/faq/faq.ts
// - resources/scss/components/_faq.scss
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Retourne le titre du groupe de FAQ associé à une page.
 *
 * @param int $page_id Identifiant de la page WordPress.
 * @return string
 */
function comkani_faq_page_title(int $page_id): string
{
    $page_slug = (string) get_post_field('post_name', $page_id);
    $groups = (array) comkani_project_get('faq.groups', []);

    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $pages = array_map(
            'sanitize_title',
            (array) ($group['pages'] ?? [])
        );

        if (!in_array($page_slug, $pages, true)) {
            continue;
        }

        $title = trim((string) ($group['title'] ?? ''));

        if ($title !== '') {
            return $title;
        }
    }

    return 'Questions fréquentes';
}

/**
 * Affiche la FAQ associée à une page WordPress.
 *
 * Les trois premières questions restent immédiatement visibles. Les suivantes
 * sont révélées à la demande afin de préserver la hauteur du panneau horizontal.
 *
 * @param int $page_id Identifiant de la page WordPress.
 * @return void
 */
function comkani_render_page_faq(int $page_id): void
{
    $items = comkani_faq_get_items_for_page($page_id);

    if ($items === []) {
        return;
    }

    $title = comkani_faq_page_title($page_id);
    $visible_count = 3;
    $has_more = count($items) > $visible_count;
    $list_id = wp_unique_id('faq-list-');
    ?>
    <div class="faq" aria-labelledby="faq-title">
        <h2 class="title" id="faq-title">FAQ</h2>
        <p><?php echo esc_html($title); ?></p>

        <div class="list" id="<?php echo esc_attr($list_id); ?>" data-faq-list>
            <?php foreach ($items as $index => $item) : ?>
                <details
                    class="item"
                    <?php echo $index >= $visible_count ? 'hidden data-faq-extra' : ''; ?>
                >
                    <summary>
                        <?php echo esc_html((string) $item['question']); ?>
                        <span class="icon" aria-hidden="true">+</span>
                    </summary>
                    <div class="answer">
                        <?php echo wp_kses_post((string) $item['answer']); ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <?php if ($has_more) : ?>
            <button
                type="button"
                class="button-tertiary more"
                data-faq-more
                aria-controls="<?php echo esc_attr($list_id); ?>"
                aria-expanded="false"
            >
                Voir plus de questions
            </button>
        <?php endif; ?>
    </div>
    <?php

    $schema_items = [];

    foreach ($items as $item) {
        $schema_items[] = [
            '@type' => 'Question',
            'name' => wp_strip_all_tags((string) $item['question']),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => wp_strip_all_tags((string) $item['answer']),
            ],
        ];
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $schema_items,
    ];

    echo '<script type="application/ld+json">'
        . wp_json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        )
        . '</script>';
}
