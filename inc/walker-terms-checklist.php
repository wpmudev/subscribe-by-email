<?php
/**
 * Walker to output an unordered list of category checkbox <input> elements.
 *
 * @see Walker
 * @see wp_category_checklist()
 * @see wp_terms_checklist()
 * @since 2.5.1
 */
class Walker_SBE_Terms_Checklist extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		if ( $args['indent'] ) {
			$indent = str_repeat("\t", $depth);
			$output .= "$indent<ul class='children'>\n";
		}
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		if ( $args['indent'] ) {
			$indent = str_repeat("\t", $depth);
			$output .= "$indent</ul>\n";
		}
	}

	function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
		extract($args);

		$class = ' class="settings-term"';
		$output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" class="settings-term-checkbox ' . $post_type_slug . '-checkbox" type="checkbox" name="' . $base_name . '[' . $post_type_slug . '][' . $taxonomy_slug  . '][]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ) || $selected_cats == 'select-all', true, false ) . disabled( $args['disabled'] == true, true, false ) . ' /> ' . esc_html( $category->name ) . '</label>';
	}

	function end_el( &$output, $category, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}

}