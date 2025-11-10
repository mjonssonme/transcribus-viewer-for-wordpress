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
                    <div id="tvwp-viewer" class="tvwp-viewer" data-post-id="<?php the_ID(); ?>">
                        
                        <div class="tvwp-controls">
                            <button class="tvwp-nav" data-nav-skip="-5" title="5 pages back">-5</button>
                            <button class="tvwp-nav" data-nav-step="-1" title="Previous page">Previous</button>
                            <span class="tvwp-page-display">
                                Page 
                                <select class="tvwp-nav-jump" title="Jump to page"></select>
                                of 
                                <span class="tvwp-total-pages">...</span>
                            </span>
                            <button class="tvwp-nav" data-nav-step="1" title="Next page">Next</button>
                            <button class="tvwp-nav" data-nav-skip="5" title="5 pages forward">+5</button>
                        </div>

                        <div class="tvwp-main-content">
                            <div class="tvwp-image-pane">
                                <div class="tvwp-image-wrapper">
                                    <img id="tvwp-image" src="" alt="Transcribed page image" />
                                    <svg id="tvwp-overlay" class="tvwp-overlay" xmlns="http://www.w3.org/2000/svg"></svg>
                                </div>
                            </div>
                            <div id="tvwp-text-pane" class="tvwp-text-pane">
                                </div>
                        </div>

                    </div>

                </div></article><?php endwhile; // End of the loop. ?>

    </main></div><?php get_footer(); ?>