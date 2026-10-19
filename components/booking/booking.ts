// =============================================================================
// Fichier : components/booking/booking.ts
// Rôle : charger les créneaux disponibles du composant Booking
//
// Ce qu'il fait :
// - écoute la date choisie dans chaque formulaire de réservation
// - interroge l'API REST ComKani pour récupérer les créneaux disponibles
// - alimente le select des horaires et les messages d'état
// - conserve le composant fonctionnel même lorsqu'aucun créneau n'est disponible
//
// Ce qu'il ne fait pas :
// - n'enregistre pas directement une demande dans WordPress
// - ne définit pas les règles métier de disponibilité
// - ne modifie pas la configuration du composant Booking
//
// Fichiers liés :
// - components/booking/booking.php
// - packages/comkani-core/includes/requests.php
// - resources/ts/main.ts
// =============================================================================

declare global {
    interface Window {
        ComKaniProject?: {
            restUrl: string;
            restNonce?: string;
        };
    }
}

/**
 * Initialise un formulaire de réservation ancien projet client.
 *
 * @param form Formulaire contenant les champs date, créneau et statut
 * @returns Rien
 */
function initBookingForm(form: HTMLFormElement): void {
    const dateInput = form.querySelector<HTMLInputElement>('[data-booking-date]');
    const timeSelect = form.querySelector<HTMLSelectElement>('[data-booking-time]');
    const status = form.querySelector<HTMLElement>('[data-booking-status]');

    if (!dateInput || !timeSelect || !status || !window.ComKaniProject?.restUrl) {
        return;
    }

    /**
     * Recharge les créneaux disponibles pour la date sélectionnée.
     *
     * @returns Promesse résolue une fois l'interface mise à jour
     */
    async function loadSlots(): Promise<void> {
        timeSelect.disabled = true;
        timeSelect.innerHTML = '<option value="">Chargement…</option>';
        status.textContent = 'Chargement des créneaux…';

        if (!dateInput.value) {
            status.textContent = '';
            return;
        }

        try {
            const url = `${window.ComKaniProject?.restUrl ?? ''}booking/slots?date=${encodeURIComponent(dateInput.value)}`;
            const response = await fetch(url, {
                headers: {
                    'X-WP-Nonce': window.ComKaniProject?.restNonce || '',
                },
            });
            const data = await response.json() as {slots?: unknown};
            const slots = Array.isArray(data.slots) ? data.slots.filter((slot): slot is string => typeof slot === 'string') : [];

            if (slots.length === 0) {
                timeSelect.innerHTML = '<option value="">Aucun créneau disponible</option>';
                status.textContent = 'Aucun créneau disponible pour cette date.';
                return;
            }

            timeSelect.innerHTML = '<option value="">Choisissez un créneau</option>';

            slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot;
                option.textContent = slot;
                timeSelect.append(option);
            });

            timeSelect.disabled = false;
            status.textContent = '';
        } catch (error) {
            console.error(error);
            timeSelect.innerHTML = '<option value="">Indisponible</option>';
            status.textContent = 'Impossible de charger les créneaux pour le moment.';
        }
    }

    dateInput.addEventListener('change', loadSlots);
}

document.querySelectorAll<HTMLFormElement>('[data-booking-form]').forEach(initBookingForm);

export {};
