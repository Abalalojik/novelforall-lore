<?php
/**
 * Per-entry settings: glossary term, aliases, and the chapter that unlocks the entry.
 */

defined( 'ABSPATH' ) || exit;

const NFA_META_GLOSSARY = '_nfa_glossary';
const NFA_META_ALIASES  = '_nfa_aliases';

add_action(
	'init',
	static function () {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};
		register_post_meta(
			NFA_LORE_TYPE,
			NFA_META_GLOSSARY,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			NFA_LORE_TYPE,
			NFA_META_ALIASES,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth,
			)
		);
		register_post_meta(
			NFA_LORE_TYPE,
			NFA_META_VISIBLE_FROM,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			)
		);
	}
);

/**
 * Aliases of an entry, as a clean list ("seren, serens" → ["seren", "serens"]).
 */
function nfa_lore_entry_aliases( $post_id ) {
	$raw = (string) get_post_meta( $post_id, NFA_META_ALIASES, true );
	return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
}

/**
 * Chapters an entry or a spoiler can be tied to: every post, newest first, with its status.
 */
function nfa_lore_chapter_choices() {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'nfa_internal'   => true,
		)
	);
	$choices = array();
	foreach ( $posts as $post ) {
		$status    = 'publish' === $post->post_status ? '' : ' (' . $post->post_status . ')';
		$choices[] = array(
			'id'    => $post->ID,
			'label' => get_the_title( $post ) . $status,
		);
	}
	return $choices;
}

add_action(
	'add_meta_boxes_' . NFA_LORE_TYPE,
	static function () {
		add_meta_box( 'nfa-lore-settings', __( 'Lore Settings', 'nfa-lore' ), 'nfa_lore_render_settings_box', NFA_LORE_TYPE, 'side' );
	}
);

function nfa_lore_render_settings_box( $post ) {
	wp_nonce_field( 'nfa_lore_settings', 'nfa_lore_settings_nonce' );
	$glossary = (bool) get_post_meta( $post->ID, NFA_META_GLOSSARY, true );
	$aliases  = (string) get_post_meta( $post->ID, NFA_META_ALIASES, true );
	$from     = (int) get_post_meta( $post->ID, NFA_META_VISIBLE_FROM, true );
	?>
	<p>
		<label>
			<input type="checkbox" name="nfa_glossary" value="1" <?php checked( $glossary ); ?> />
			<?php esc_html_e( 'Glossary term (tooltip in chapters)', 'nfa-lore' ); ?>
		</label>
	</p>
	<p>
		<label for="nfa_aliases"><?php esc_html_e( 'Aliases (comma-separated)', 'nfa-lore' ); ?></label>
		<input type="text" class="widefat" id="nfa_aliases" name="nfa_aliases" value="<?php echo esc_attr( $aliases ); ?>" />
	</p>
	<p>
		<label for="nfa_visible_from"><?php esc_html_e( 'Visible from chapter', 'nfa-lore' ); ?></label>
		<select class="widefat" id="nfa_visible_from" name="nfa_visible_from">
			<option value="0"><?php esc_html_e( 'Always visible', 'nfa-lore' ); ?></option>
			<?php foreach ( nfa_lore_chapter_choices() as $choice ) : ?>
				<option value="<?php echo esc_attr( $choice['id'] ); ?>" <?php selected( $from, $choice['id'] ); ?>><?php echo esc_html( $choice['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description"><?php esc_html_e( 'The definition shown in tooltips is the entry’s excerpt.', 'nfa-lore' ); ?></p>
	<?php
}

add_action(
	'save_post_' . NFA_LORE_TYPE,
	static function ( $post_id ) {
		if ( ! isset( $_POST['nfa_lore_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nfa_lore_settings_nonce'] ) ), 'nfa_lore_settings' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, NFA_META_GLOSSARY, ! empty( $_POST['nfa_glossary'] ) );
		update_post_meta( $post_id, NFA_META_ALIASES, isset( $_POST['nfa_aliases'] ) ? sanitize_text_field( wp_unslash( $_POST['nfa_aliases'] ) ) : '' );
		update_post_meta( $post_id, NFA_META_VISIBLE_FROM, isset( $_POST['nfa_visible_from'] ) ? absint( $_POST['nfa_visible_from'] ) : 0 );
	}
);

/**
 * Admin list columns: glossary and unlocking chapter.
 */
add_filter(
	'manage_' . NFA_LORE_TYPE . '_posts_columns',
	static function ( $columns ) {
		$columns['nfa_glossary']     = __( 'Glossary', 'nfa-lore' );
		$columns['nfa_visible_from'] = __( 'Visible from', 'nfa-lore' );
		return $columns;
	}
);

add_action(
	'manage_' . NFA_LORE_TYPE . '_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'nfa_glossary' === $column ) {
			echo get_post_meta( $post_id, NFA_META_GLOSSARY, true ) ? '✓' : '—';
		} elseif ( 'nfa_visible_from' === $column ) {
			$from = (int) get_post_meta( $post_id, NFA_META_VISIBLE_FROM, true );
			if ( ! $from ) {
				esc_html_e( 'Always', 'nfa-lore' );
				return;
			}
			echo esc_html( get_the_title( $from ) );
			echo nfa_lore_chapter_is_published( $from ) ? '' : ' <em>(' . esc_html__( 'locked', 'nfa-lore' ) . ')</em>';
		}
	},
	10,
	2
);
