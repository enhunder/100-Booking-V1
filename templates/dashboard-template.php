<?php
/**
 * Template Name: 100Studios Dashboard
 * Description: Vollbild-Dashboard ohne Navigation und Footer
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000">
    <?php wp_head(); ?>
</head>
<body <?php body_class('sb-dashboard-page'); ?>>

    <?php
    while ( have_posts() ) :
        the_post();
        echo do_shortcode('[studiobook_portal]');
    endwhile;
    ?>

    <?php wp_footer(); ?>
</body>
</html>
