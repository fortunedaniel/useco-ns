// =============================================================================
// Fichier : resources/ts/main.ts
// Rôle : point d’entrée commun et générique des ressources front-end ComKani
//
// Ce qu’il fait :
// - charge les styles et comportements partagés
// - ne charge aucun composant de projet désactivé
//
// Ce qu’il ne fait pas :
// - ne charge pas les interactions d'une ancienne page d'accueil
// - ne charge pas les composants optionnels sans leur activation
// =============================================================================

import '../scss/style.scss';
import './shared/navigation';
import './shared/modal';
import './shared/motion';
import './shared/button-motion';
import './layout/header-scroll';
