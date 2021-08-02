<?php get_header();

    while(have_posts()) {
        the_post();
        pageBanner();
         ?>

        <div class="container container--narrow page-section">
                <div class="metabox metabox--position-up metabox--with-home-link">
                    <p><a class="metabox__blog-home-link" href="<?php echo get_post_type_archive_link('program') ?>">
                        <i class="fa fa-home" aria-hidden="true">
                        </i> All Programs</a> <span class="metabox__main"> <?php the_title(); ?>
                    </p>
                </div>

        <div class="generic-content"> <?php the_content(); ?> </div>

        <?php 
            /*-------------------------------------------------------*/
            /* Related Professors */
            $relatedProfessors = new WP_Query(array(
                'posts_per_page' => -1,
                'post_type' => 'professor',
                'orderby' => 'title',
                'order' => 'ASC',
                'meta_query' => array(
                    array(
                        'key' => 'related_program',
                        'compare' => 'LIKE', 
                        'value' => '"'.get_the_ID().'"'
                    )
                )
            ));
    
            
            if($relatedProfessors->have_posts())
            {
                echo '<hr class="section-break">';
                echo '<h2 class="headline headline--medium">' . get_the_title() . ' Professors</h2>';
    
                echo '<ul class="professor-cards">';
                while($relatedProfessors->have_posts())
                {
                $relatedProfessors->the_post(); ?>
                    <li class="professor-card__list-item">
                        <a class="professor-card" href="<?php the_permalink(); ?>"">
                            <img class="professor-card__image" src="<?php the_post_thumbnail_url('professorLandscape'); ?>" alt="">
                            <span class="professor-card__name"><?php the_title(); ?></span>
                        </a>
                    </li>
    
                    <?php 
                }
                echo '</ul>';
            }
       
            wp_reset_postdata();
            

            /*-------------------------------------------------------*/
            /* Related Events */
            $today = date('Ymd');
            $homepageEvents = new WP_Query(array(
            'posts_per_page' => 2,
            'post_type' => 'event',
            'meta_key' => 'event_date',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                'key' => 'event_date',
                'compare' => '>=',
                'value' => $today,
                'type' => 'numeric'
                ),
                array(
                    'key' => 'related_program',
                    'compare' => 'LIKE', 
                    'value' => '"'.get_the_ID().'"'
                )
            )
            ));

            
            if($homepageEvents->have_posts())
            {
                //echo '<div class="container container--narow page-selection">'; 

                echo '<hr class="section-break">';
                echo '<h2 class="headline headline--medium">Upcoming ' . get_the_title() . ' Events</h2>';

                while($homepageEvents->have_posts())
                {
                $homepageEvents->the_post(); ?>
                    <div class="event-summary">
                        <a class="event-summary__date t-center" href="<?php the_permalink(); ?>">
                        <span class="event-summary__month"><?php 
                            $eventDate = new DateTime(get_field('event_date'));
                            echo $eventDate->format('M')
                        ?></span>
                        <span class="event-summary__day"><?php 
                            $eventDate = new DateTime(get_field('event_date'));
                            echo $eventDate->format('d')
                        ?></span>
                        </a>
                        <div class="event-summary__content">
                        <h5 class="event-summary__title headline headline--tiny"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h5>
                        <p><?php if (has_excerpt()) {
                            echo get_the_excerpt();
                        }
                        else
                        {
                            echo wp_trim_words(get_the_content(), 18);
                        } ?> <a href="<?php the_permalink(); ?>" class="nu gray">Learn more</a></p>
                        </div>
                    </div>

                    <?php 
                }

                //echo '</div>';
            }
        
      ?>
    </div>

    <?php }

get_footer();
?>