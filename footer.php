<?php
// =============================================================================
// Fichier : footer.php
// Rôle : terminer la structure générique WordPress
//
// Ce qu’il fait :
// - affiche le nom du site et déclenche wp_footer
//
// Ce qu’il ne fait pas :
// - ne contient aucune donnée d’un client précédent
// =============================================================================
defined('ABSPATH') || exit;
?>
<footer class="site-footer"><div class="inner"><span><?php bloginfo('name'); ?></span></div></footer>
<?php wp_footer(); ?>
</body></html>
