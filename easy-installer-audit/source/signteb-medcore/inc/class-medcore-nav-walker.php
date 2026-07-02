<?php
/**
 * SignTeb MedCore — Nav Walker
 * منوی دسترس‌پذیر با keyboard navigation و ARIA
 */
defined( 'ABSPATH' ) || exit;

class MedCore_Nav_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ): void {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n{$indent}<ul class=\"sub-menu\" role=\"menu\" aria-hidden=\"true\">\n";
	}

	public function end_lvl( &$output, $depth = 0, $args = null ): void {
		$indent  = str_repeat( "\t", $depth );
		$output .= "{$indent}</ul>\n";
	}

	public function start_el( &$output, $data_object, $depth = 0, $args = null, $id = 0 ): void {
		$item = $data_object;

		$indent    = ( $depth ) ? str_repeat( "\t", $depth ) : '';
		$classes   = empty( $item->classes ) ? [] : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		// Active class
		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$classes[] = 'is-current';
		}
		if ( in_array( 'current-menu-parent', $classes, true ) || in_array( 'current-menu-ancestor', $classes, true ) ) {
			$classes[] = 'is-ancestor';
		}

		// Has children
		$has_children = in_array( 'menu-item-has-children', $classes, true );

		$class_names = implode( ' ', array_filter( array_map( 'trim', $classes ) ) );

		$li_atts  = 'class="' . esc_attr( $class_names ) . '"';
		$li_atts .= ' role="none"';

		$output .= $indent . '<li ' . $li_atts . '>';

		// Link attributes
		$atts = [];
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target )     ? $item->target     : '';
		$atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
		$atts['href']   = ! empty( $item->url )        ? $item->url        : '#';
		$atts['class']  = 'nav-link';
		$atts['role']   = 'menuitem';

		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$atts['aria-current'] = 'page';
		}

		if ( $has_children ) {
			$atts['aria-haspopup'] = 'true';
			$atts['aria-expanded'] = 'false';
		}

		if ( 0 === $depth ) {
			$atts['class'] = 'nav-link nav-link--top';
		} else {
			$atts['class'] = 'nav-link nav-link--sub';
		}

		$attr_str = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) || 'aria-current' === $attr ) {
				$escaped   = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attr_str .= ' ' . $attr . '="' . $escaped . '"';
			}
		}

		$item_output  = $args->before ?? '';
		$item_output .= '<a' . $attr_str . '>';
		$item_output .= ( $args->link_before ?? '' ) . apply_filters( 'the_title', $item->title, $item->ID ) . ( $args->link_after ?? '' );

		// Dropdown chevron
		if ( $has_children && 0 === $depth ) {
			$item_output .= '<span class="nav-chevron" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></span>';
		}

		$item_output .= '</a>';
		$item_output .= $args->after ?? '';

		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	public function end_el( &$output, $data_object, $depth = 0, $args = null ): void {
		$output .= "</li>\n";
	}
}
