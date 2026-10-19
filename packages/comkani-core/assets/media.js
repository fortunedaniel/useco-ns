// =============================================================================
// Fichier : packages/comkani-core/assets/media.js
// Rôle : simplifie l'édition des légendes dans la médiathèque WordPress
//
// Ce qu'il fait :
// - affiche une case permettant d'activer la légende
// - masque le champ Légende lorsqu'il n'est pas utilisé
// - synchronise la case avec les données enregistrées par WordPress
// - fonctionne dans la médiathèque et les sélecteurs de médias
//
// Fichiers liés :
// - packages/comkani-core/includes/media.php
// - packages/comkani-core/assets/admin.css
// =============================================================================

const comkaniMediaCaptionFields = () => {
    const panels = document.querySelectorAll(
        '.attachment-details'
    );

    panels.forEach((panel) => {
        const captionField = panel.querySelector(
            '.setting[data-setting="caption"]'
        );

        const compatibilityField =
            panel.querySelector(
                '.compat-field-comkani_caption_enabled'
            );

        const storedCheckbox =
            compatibilityField?.querySelector(
                'input[type="checkbox"]'
            );

        if (
            !captionField
            || !compatibilityField
            || !storedCheckbox
        ) {
            return;
        }

        compatibilityField.hidden = true;

        let toggle = panel.querySelector(
            '[data-comkani-caption-toggle]'
        );

        if (!toggle) {
            toggle = document.createElement(
                'label'
            );

            toggle.className =
                'setting comkani-caption-toggle';

            toggle.dataset.comkaniCaptionToggle =
                '';

            toggle.innerHTML = `
                <span class="name">Légende</span>
                <span class="comkani-caption-toggle-control">
                    <input type="checkbox">
                    <span>Afficher la légende sur le site</span>
                </span>
            `;

            captionField.before(toggle);
        }

        const checkbox = toggle.querySelector(
            'input[type="checkbox"]'
        );

        if (!checkbox) {
            return;
        }

        checkbox.checked =
            storedCheckbox.checked;

        captionField.hidden =
            !checkbox.checked;

        if (
            checkbox.dataset.comkaniBound ===
            '1'
        ) {
            return;
        }

        checkbox.dataset.comkaniBound = '1';

        checkbox.addEventListener(
            'change',
            () => {
                storedCheckbox.checked =
                    checkbox.checked;

                captionField.hidden =
                    !checkbox.checked;

                storedCheckbox.dispatchEvent(
                    new Event(
                        'change',
                        {
                            bubbles: true,
                        }
                    )
                );
            }
        );
    });
};

const comkaniMediaCaptionInit = () => {
    comkaniMediaCaptionFields();

    const observer = new MutationObserver(
        () => {
            comkaniMediaCaptionFields();
        }
    );

    observer.observe(
        document.body,
        {
            childList: true,
            subtree: true,
        }
    );
};

if (
    document.readyState === 'loading'
) {
    document.addEventListener(
        'DOMContentLoaded',
        comkaniMediaCaptionInit
    );
} else {
    comkaniMediaCaptionInit();
}