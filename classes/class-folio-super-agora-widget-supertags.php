<?php

/**
 * Folio SuperAgora ActiFolios Widget
 * 
 * @since      		1.0.0
 *
 * @package         Folio
 * @subpackage 		Super
 */

class Folio_Super_Agora_Widget_Super_Tags extends WP_Widget {

	/**
	 * Sets up the widget
	 */
	public function __construct() {
		parent::__construct(
			'folio_super_agora_widget_super_tags',
			__( 'SuperTags', 'folio-super-agora' ),
			array(
				'classname'   => 'Folio_Super_Agora_Widget_Super_Tags',
				'description' => __( 'Widget SuperTags', 'folio-super-agora' ),
			)
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {

		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$output = '';

		$title = apply_filters( 'widget_title', $instance['title'] );

		$show_number_posts = ( ! empty( $instance['show_number_posts'] ) ) ? absint( $instance['show_number_posts'] ) : 0;
		$number            = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 0;
		$orderby           = ( ! empty( $instance['orderby'] ) ) ? sanitize_text_field( $instance['orderby'] ) : 'count';
		$order             = ( ! empty( $instance['order'] ) ) ? sanitize_text_field( $instance['order'] ) : 'DESC';

		$supertags = get_terms( 'super_tag', array( 
			'hide_empty' => true,
			'orderby'    => $orderby,
			'order'      => $order,
			'number'     => $number,
		) );

		$output .= $args['before_widget'];

		if ( ! empty ($title) ) {
			$output .= $args['before_title'] . $title . $args['after_title'];
		}

		$summary_class = ! empty ($title) ? 'mx-1 mb-3 mt-n3' : 'mx-1 mb-3 mt-n1';

		$summary_label = sprintf( 
			__( '%1$s supertags', 'folio-super-agora' ), 
			'<strong>' . count($supertags) . '</strong>',
		);

		$output .= '<div class="super-agora-widget-sumary '. $summary_class .'">' . $summary_label . '</div>';

		$output .= '<div class="block"><ul id="super-tags-widget" class="list list--menu list--super-tags row">';

		if ( ! empty( $supertags ) && ! is_wp_error( $supertags ) ) {

			$current_id = get_queried_object_id();

			foreach ( $supertags as $term ) {

				$output .= '<li class="col-md-4 col-lg-3">';
				$concat = '';
				if ( $show_number_posts ) {
					$concat = ' (' . $term->count . ')';
				}
				
				$class= $term->term_id == $current_id ? 'active' : '';

				$output .= '<a href="' . get_term_link( $term ) . '" class="'. $class .'">' . $term->name . $concat . '</a>';
				$output .= '</li>';

			}

		} else {
			$output .= '<li class="col-md-12">';
			$output .= __( 'There are not SuperTags with post related', 'folio-super-agora' );
			$output .= '</li>';
		}

		$output .= '</ul></div>';
		$output .= $args['after_widget'];

		echo $output;
	}

	/**
	 * Handles updating the settings for the current widget instance
	 *
	 * @param array $new_instance New settings for this instance
	 * @param array $old_instance Old settings for this instance
	 *
	 * @return array Updated settings to save
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                      = $old_instance;
		$instance['title']			   = sanitize_text_field( $new_instance['title'] );
		$instance['number']            = isset( $new_instance['number'] ) ? absint( $new_instance['number'] ) : '';
		$instance['show_number_posts'] = (int) $new_instance['show_number_posts'];
		$instance['orderby'] 		   = sanitize_text_field($new_instance['orderby']);
		$instance['order'] 			   = sanitize_text_field($new_instance['order']);

		return $instance;
	}

	/**
	 * Outputs the settings form for the widget
	 *
	 * @param array $instance Current settings
	 */
	public function form( $instance ) {
		$title             = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number            = isset( $instance['number'] ) ? absint( $instance['number'] ) : '';
		$show_number_posts = isset( $instance['show_number_posts'] ) ? absint( $instance['show_number_posts'] ) : 1;
		$orderby           = isset( $instance['orderby'] ) ? $instance['orderby'] : 'count';
		$order             = isset( $instance['order'] ) ? $instance['order'] : 'DESC';
		?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:',
					'folio-super-agora' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>"/>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'number' ); ?>">
				<?php _e( 'Max number of SuperTags to show:', 'folio-super-agora' ); ?>
			</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>"
                   name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="0"
                   value="<?php echo $number; ?>" size="3"/>
			<em><?php _e( '(0 or empty for unlimited)', 'folio-super-agora' ); ?></em>
        </p>
        <p>
			<input class="checkbox" id="<?php echo $this->get_field_id( 'show_number_posts' ); ?>"
                   name="<?php echo $this->get_field_name( 'show_number_posts' ); ?>" type="checkbox" step="1"
                   value="1" <?php echo $show_number_posts === 1 ? 'checked' : '' ?>/>
            <label for="<?php echo $this->get_field_id( 'show_number_posts' ); ?>">
				<?php _e( 'Show number of posts:', 'folio-super-agora' ); ?>
			</label>
        </p>
		<p>
            <label for="<?php echo $this->get_field_id( 'orderby' ); ?>">
				<?php _e( 'Order by:', 'folio-super-agora' ); ?>
			</label>
            <select class='widefat' id="<?php echo $this->get_field_id('orderby'); ?>"
                name="<?php echo $this->get_field_name('orderby'); ?>">
				<option value='name' <?php echo ($orderby == 'name') ? 'selected' : ''; ?>>
					<?php _e( 'Name', 'folio-super-agora' ); ?>
				</option>
				<option value='count' <?php echo ($orderby == 'count') ? 'selected' : ''; ?>>
					<?php _e( 'Count', 'folio-super-agora' ); ?>
				</option> 
			</select>
        </p>
		<p>
            <label for="<?php echo $this->get_field_id( 'order' ); ?>">
				<?php _e( 'Order:', 'folio-super-agora' ); ?>
			</label>
            <select class='widefat' id="<?php echo $this->get_field_id('order'); ?>"
                name="<?php echo $this->get_field_name('order'); ?>">
				<option value='ASC' <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>ASC</option>
				<option value='DESC' <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>DESC</option> 
			</select>
        </p>
		<?php
	}

}
