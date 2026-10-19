// =============================================================================
// Fichier : config/framework/fields.ts
// Rôle : simplifier la déclaration des champs administrables ComKani
//
// Ce qu'il fait :
// - fournit des builders explicites pour les champs les plus fréquents
// - évite de répéter manuellement `type` dans chaque nouvelle configuration
// - conserve les objets finaux sérialisables vers PHP
// - améliore l'autocomplétion TypeScript pour Kani et Daniel
//
// Ce qu'il ne fait pas :
// - ne rend pas obligatoire la réécriture immédiate des anciennes pages
// - ne génère pas directement les contrôles HTML du back-office
// - ne contient aucune logique WordPress
//
// Fichiers liés :
// - config/framework/editor.ts
// - config/src/pages/*.ts
// =============================================================================

import type {
    CheckboxField,
    ImageField,
    LinkField,
    RepeaterField,
    SelectField,
    TextareaField,
    TextField,
} from './editor.ts';

type WithoutType<T extends {readonly type: string}> = Omit<T, 'type'>;

/**
 * Déclare un champ texte simple.
 *
 * @param options Clé, libellé et options du champ
 * @returns Définition d'un champ texte
 */
export function text<const T extends WithoutType<TextField>>(options: T): T & {readonly type: 'text'} {
    return {...options, type: 'text'};
}

/**
 * Déclare un champ texte multiligne.
 *
 * @param options Clé, libellé, nombre de lignes et valeur par défaut
 * @returns Définition d'un textarea
 */
export function textarea<const T extends WithoutType<TextareaField>>(options: T): T & {readonly type: 'textarea'} {
    return {...options, type: 'textarea'};
}

/**
 * Déclare un champ média image.
 *
 * @param options Clé, libellé et options du champ
 * @returns Définition d'un champ image
 */
export function image<const T extends WithoutType<ImageField>>(options: T): T & {readonly type: 'image'} {
    return {...options, type: 'image'};
}

/**
 * Déclare un champ lien structuré.
 *
 * @param options Clé, libellé et valeur par défaut
 * @returns Définition d'un champ lien
 */
export function link<const T extends WithoutType<LinkField>>(options: T): T & {readonly type: 'link'} {
    return {...options, type: 'link'};
}

/**
 * Déclare un champ de sélection.
 *
 * @param options Clé, libellé et options disponibles
 * @returns Définition d'un select
 */
export function select<const T extends WithoutType<SelectField>>(options: T): T & {readonly type: 'select'} {
    return {...options, type: 'select'};
}

/**
 * Déclare un champ booléen de type checkbox.
 *
 * @param options Clé, libellé et valeur par défaut
 * @returns Définition d'une checkbox
 */
export function checkbox<const T extends WithoutType<CheckboxField>>(options: T): T & {readonly type: 'checkbox'} {
    return {...options, type: 'checkbox'};
}

/**
 * Déclare un repeater contenant plusieurs sous-champs.
 *
 * @param options Clé, libellé, limites et sous-champs
 * @returns Définition d'un repeater
 */
export function repeater<const T extends WithoutType<RepeaterField>>(options: T): T & {readonly type: 'repeater'} {
    return {...options, type: 'repeater'};
}
