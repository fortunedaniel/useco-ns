<?php

// =============================================================================
// Fichier : home.php
// Rôle : affiche la liste des articles du site
//
// Ce qu'il fait :
// - délègue l'affichage au template archive.php
// - conserve la hiérarchie native de WordPress
//
// Fichiers liés :
// - archive.php
// =============================================================================
defined('ABSPATH') || exit;

get_template_part('archive');
