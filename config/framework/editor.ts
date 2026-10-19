// =============================================================================
// Fichier : config/framework/editor.ts
// Rôle : typer les pages et champs administrables du back-office ComKani
//
// Ce qu'il fait :
// - décrit les types de champs supportés par l’éditeur ComKani
// - valide la structure des groupes et repeaters au moment du typecheck
// - fournit defineEditorPage() comme point d’entrée lisible pour les pages
//
// Ce qu'il ne fait pas :
// - ne crée aucun champ dans WordPress à lui seul
// - ne lit ni n’écrit dans la base de données
// - n’est pas chargé en production par le navigateur
//
// Fichiers liés :
// - config/src/pages/*.ts
// - scripts/generate-project-config.ts
// =============================================================================

export type EditorFieldType =
    | 'text'
    | 'textarea'
    | 'image'
    | 'media'
    | 'video'
    | 'link'
    | 'select'
    | 'repeater'
    | 'number'
    | 'boolean'
    | 'checkbox'
    | 'wysiwyg';

export interface EditorFieldBase {
    readonly key: string;
    readonly label: string;
    readonly type: EditorFieldType;
    readonly description?: string;
    readonly required?: boolean;
}

export interface TextField extends EditorFieldBase {
    readonly type: 'text';
    readonly default?: string;
}

export interface TextareaField extends EditorFieldBase {
    readonly type: 'textarea';
    readonly rows?: number;
    readonly default?: string;
}

export interface ImageField extends EditorFieldBase {
    readonly type: 'image';
    readonly default?: number;
}

/** Identifiant de pièce jointe WordPress, pas une URL de média. */
export interface MediaField extends EditorFieldBase {
    readonly type: 'media';
    readonly default?: number;
}

/** Vidéo sélectionnée dans la médiathèque WordPress. */
export interface VideoField extends EditorFieldBase {
    readonly type: 'video';
    readonly default?: number;
}

export interface LinkValue {
    readonly label?: string;
    readonly url?: string;
    readonly target?: string;
}

export interface LinkField extends EditorFieldBase {
    readonly type: 'link';
    readonly default?: LinkValue;
}

export interface SelectField extends EditorFieldBase {
    readonly type: 'select';
    readonly options: Readonly<Record<string, string>>;
    readonly default?: string;
}

export interface NumberField extends EditorFieldBase {
    readonly type: 'number';
    readonly min?: number;
    readonly max?: number;
    readonly step?: number;
    readonly default?: number;
}

export interface BooleanField extends EditorFieldBase {
    readonly type: 'boolean';
    readonly default?: boolean;
}

export interface CheckboxField extends EditorFieldBase {
    readonly type: 'checkbox';
    readonly default?: boolean | string;
}

export interface WysiwygField extends EditorFieldBase {
    readonly type: 'wysiwyg';
    readonly default?: string;
}

export interface RepeaterField extends EditorFieldBase {
    readonly type: 'repeater';
    readonly min?: number;
    readonly max?: number;
    readonly fields: readonly EditorField[];
    readonly default?: readonly Readonly<Record<string, unknown>>[];
}

export type EditorField =
    | TextField
    | TextareaField
    | ImageField
    | MediaField
    | VideoField
    | LinkField
    | SelectField
    | RepeaterField
    | NumberField
    | BooleanField
    | CheckboxField
    | WysiwygField;

export interface EditorGroup {
    readonly key: string;
    readonly label: string;
    readonly description?: string;
    readonly fields: readonly EditorField[];
}

export interface EditorPageDefinition {
    readonly label: string;
    readonly description?: string;
    readonly groups: readonly EditorGroup[];
}

/**
 * Déclare la configuration éditoriale d’une page WordPress.
 *
 * La fonction ne transforme pas les données. Elle permet à TypeScript de
 * vérifier la structure au moment du développement tout en conservant un
 * objet simple exploitable par le générateur ComKani.
 *
 * @param definition Définition complète de la page et de ses groupes
 * @returns La définition inchangée, avec son typage conservé
 */
export function defineEditorPage<const T extends EditorPageDefinition>(
    definition: T,
): T {
    return definition;
}
