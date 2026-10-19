// =============================================================================
// Fichier : config/src/features/forms.ts
// Rôle : définit les formulaires du projet
//
// Ce qu'il fait :
// - définit la configuration source du projet WordPress
// - fournit une configuration typée aux générateurs ComKani
//
// Ce qu'il ne fait pas :
// - ne modifie pas directement WordPress
// - n'est pas chargé par le serveur de production
// =============================================================================

import {defineConfig} from '../../framework/config.ts';

const config = defineConfig({
    contact: {
        modal: false,
        submitLabel: 'Envoyer ma demande',
        subjects: [
            {
                value: 'expertise-comptable',
                label: 'Expertise comptable'
            },
            {
                value: 'conseil-strategique',
                label: 'Conseil stratégique'
            },
            {
                value: 'daf-externalisee',
                label: 'DAF externalisée'
            },
            {
                value: 'pilotage-financier',
                label: 'Pilotage financier'
            },
            {
                value: 'audit',
                label: 'Audit'
            },
            {
                value: 'gestion-sociale',
                label: 'Gestion sociale'
            },
            {
                value: 'commissariat-aux-comptes',
                label: 'Commissariat aux comptes'
            },
            {
                value: 'autre-demande',
                label: 'Autre demande'
            }
        ]
    },
    booking: {
        availableWeekdays: [
            1,
            2,
            3,
            4,
            5
        ],
        timeSlots: [
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
            '17:00'
        ],
        minLeadHours: 4,
        maxDaysAhead: 60,
        capacityPerSlot: 1
    },
    requests: {
        recipientEmail: '',
        retentionDays: 365,
        rateLimitPerHour: 5,
        trackSourcePage: true,
        trackRequestType: true,
        trackUtm: true
    }
});

export default config;
