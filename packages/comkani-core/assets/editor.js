// =============================================================================
// Fichier : packages/comkani-core/assets/editor.js
// Rôle : gère les champs avancés de l'éditeur ComKani
//
// Ce qu'il fait :
// - ouvre la médiathèque WordPress pour les champs image et média
// - affiche un aperçu des images, SVG et vidéos sélectionnés
// - ajoute, supprime et réordonne les éléments répétables
// - fonctionne aussi sur les éléments ajoutés dynamiquement
//
// Fichiers liés :
// - packages/comkani-core/includes/editor.php
// =============================================================================
(() => {
    const lockComkaniMetaboxOrder = () => {
        const order = Array.isArray(window.comkaniPageEditor?.metaboxOrder)
            ? window.comkaniPageEditor.metaboxOrder
            : [];

        const container = document.querySelector('#normal-sortables');

        if (!container || order.length === 0) {
            return;
        }

        const boxes = order
            .map((id) => document.getElementById(id))
            .filter(Boolean);

        if (boxes.length === 0) {
            return;
        }

        const firstOtherBox = Array.from(container.children).find(
            (child) => !order.includes(child.id)
        );

        boxes.forEach((box) => {
            box.classList.add('comkani-locked-metabox');

            box.querySelectorAll(
                '.handle-order-higher, .handle-order-lower, [aria-label*="monter" i], [aria-label*="descendre" i]'
            ).forEach((control) => {
                control.hidden = true;
                control.setAttribute('aria-hidden', 'true');
                control.setAttribute('tabindex', '-1');
            });

            if (firstOtherBox) {
                container.insertBefore(box, firstOtherBox);
            } else {
                container.appendChild(box);
            }
        });

        if (window.jQuery?.fn?.sortable) {
            const $container = window.jQuery(container);

            try {
                const currentCancel = $container.sortable('option', 'cancel') || '';
                const lockedSelector = '.comkani-locked-metabox';

                if (!String(currentCancel).includes(lockedSelector)) {
                    $container.sortable(
                        'option',
                        'cancel',
                        [currentCancel, lockedSelector].filter(Boolean).join(', ')
                    );
                }
            } catch (error) {
                // WordPress peut initialiser le sortable apres notre script.
            }
        }
    };

    const openMedia = (field) => {
        const input = field.querySelector('[data-comkani-media-id]');
        const preview = field.querySelector('[data-comkani-media-preview]');
        const libraryType = field.dataset.libraryType || '';

        if (!input || !preview || !window.wp?.media) {
            return;
        }

        const options = {
            title: libraryType === 'image' ? 'Choisir une image' : libraryType === 'video' ? 'Choisir une vidéo' : 'Choisir un média',
            button: {
                text: libraryType === 'image' ? 'Utiliser cette image' : libraryType === 'video' ? 'Utiliser cette vidéo' : 'Utiliser ce média',
            },
            multiple: false,
        };

        if (libraryType) {
            options.library = {
                type: libraryType,
            };
        }

        const frame = window.wp.media(options);

        frame.on('select', () => {
            const media = frame.state().get('selection').first()?.toJSON();

            if (!media?.id) {
                return;
            }

            if (
                !libraryType
                && !['image', 'video'].includes(String(media.type || '').toLowerCase())
            ) {
                window.alert('Choisis une image, une icône SVG ou une vidéo');
                return;
            }

            input.value = media.id;

            const source = media.sizes?.medium?.url || media.url || '';
            const isVideo = String(media.type || '').toLowerCase() === 'video';

            if (!source) {
                preview.innerHTML = '';
                return;
            }

            preview.innerHTML = isVideo
                ? `<video src="${source}" controls muted playsinline></video>`
                : `<img src="${source}" alt="">`;
        });

        frame.open();
    };

    const addRepeaterItem = (repeater) => {
        const items = repeater.querySelector('[data-comkani-repeater-items]');
        const template = repeater.querySelector('[data-comkani-repeater-template]');
        const max = Number.parseInt(repeater.dataset.max || '0', 10);

        if (!items || !template) {
            return;
        }

        if (max > 0 && items.children.length >= max) {
            return;
        }

        const index = `${Date.now()}-${items.children.length}`;
        const html = template.innerHTML.replaceAll('__INDEX__', index);

        items.insertAdjacentHTML('beforeend', html);
    };

    document.addEventListener('click', (event) => {
        const select = event.target.closest('[data-comkani-media-select]');

        if (select) {
            event.preventDefault();
            openMedia(select.closest('[data-comkani-media-field]'));
            return;
        }

        const removeMedia = event.target.closest('[data-comkani-media-remove]');

        if (removeMedia) {
            event.preventDefault();
            const field = removeMedia.closest('[data-comkani-media-field]');
            const input = field?.querySelector('[data-comkani-media-id]');
            const preview = field?.querySelector('[data-comkani-media-preview]');

            if (input) {
                input.value = '';
            }

            if (preview) {
                preview.innerHTML = '';
            }

            return;
        }

        const add = event.target.closest('[data-comkani-repeater-add]');

        if (add) {
            event.preventDefault();
            addRepeaterItem(add.closest('[data-comkani-repeater]'));
            return;
        }

        const remove = event.target.closest('[data-comkani-repeater-remove]');

        if (remove) {
            event.preventDefault();
            remove.closest('[data-comkani-repeater-item]')?.remove();
            return;
        }

        const up = event.target.closest('[data-comkani-repeater-up]');

        if (up) {
            event.preventDefault();
            const item = up.closest('[data-comkani-repeater-item]');
            const previous = item?.previousElementSibling;

            if (item && previous) {
                item.parentNode.insertBefore(item, previous);
            }

            return;
        }

        const down = event.target.closest('[data-comkani-repeater-down]');

        if (down) {
            event.preventDefault();
            const item = down.closest('[data-comkani-repeater-item]');
            const next = item?.nextElementSibling;

            if (item && next) {
                item.parentNode.insertBefore(next, item);
            }
        }
    });

    lockComkaniMetaboxOrder();
    window.setTimeout(lockComkaniMetaboxOrder, 0);
    window.setTimeout(lockComkaniMetaboxOrder, 250);

    const publish = document.querySelector('#publish');

    if (publish && !document.querySelector('[data-comkani-page-save]')) {
        const save = document.createElement('button');
        save.type = 'button';
        save.className = 'button button-primary comkani-page-save';
        save.dataset.comkaniPageSave = '';
        save.textContent = publish.value || 'Mettre à jour';
        save.hidden = true;

        save.addEventListener('click', () => {
            publish.click();
        });

        document.body.append(save);

        const observer = new IntersectionObserver(
            ([entry]) => {
                save.hidden = entry.isIntersecting;
            },
            {
                threshold: 0,
            }
        );

        observer.observe(publish);
    }
})();
