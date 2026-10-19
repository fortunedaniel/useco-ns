// =============================================================================
// Fichier : config/src/index.ts
// Rôle : assembler les configurations générales et fonctionnelles du starter
//
// Ce qu’il fait :
// - combine les configurations sources pour les outils de génération
//
// Ce qu’il ne fait pas :
// - ne modifie pas directement WordPress ou sa base de données
// =============================================================================

import components from './features/components.ts';
import duplication from './features/duplication.ts';
import consent from './features/consent.ts';
import editor from './features/editor.ts';
import forms from './features/forms.ts';
import project from './project.ts';
import security from './features/security.ts';
import seo from './features/seo.ts';
import sharedContent from './features/shared-content.ts';
import wordpress from './wordpress.ts';

const config = {
    ...project, ...wordpress, ...components, ...forms, ...security,
    ...seo, ...consent, ...sharedContent, ...duplication,
    pageEditor: {...editor.pageEditor, pages: {}},
};

export default config;
