<?php
/**
 * The template for displaying a single Transkribus Document.
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php while ( have_posts() ) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header>

                <div class="entry-content">
                    
                    <div class="tvwp-post-content">
                        <?php the_content(); ?>
                    </div>
                    
                    <?php echo TVWP_Frontend::render_viewer_markup( get_the_ID() ); ?>

                </div></article><?php endwhile; // End of the loop. ?>

    </main></div><?php get_footer(); ?>