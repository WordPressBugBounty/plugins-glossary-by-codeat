<?php

/**
 * Glossary
 *
 * @package   Glossary
 * @author    Codeat <support@codeat.co>
 * @copyright 2020
 * @license   GPL 3.0+
 * @link      https://codeat.co
 */

namespace Glossary\Integrations\CMB_Fields;

use Glossary\Engine;

/**
 * CMB2 Number text field
 */
class CMB2_MultiCheck_PostType extends Engine\Base {

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function initialize() {
		parent::initialize();

		\add_action( 'cmb2_render_multicheck_posttype', array( $this, 'render' ), 10, 5 );
	}

	public function render( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		if ( \version_compare( CMB2_VERSION, '2.2.2', '>=' ) ) {
			$field_type_object->type = new \CMB2_Type_Radio( $field_type_object );
		}

		$cpts = \get_post_types();
		unset( $cpts[ 'acf-field' ] );
		unset( $cpts[ 'acf-field-group' ] );
		unset( $cpts[ 'acf-post-type' ] );
		unset( $cpts[ 'acf-post-type' ] );
		unset( $cpts[ 'acf-taxonomy' ] );
		unset( $cpts[ 'acf-template' ] );
		unset( $cpts[ 'acf-ui-options-page' ] );
		unset( $cpts[ 'bwg_album' ] );
		unset( $cpts[ 'bwg_gallery' ] );
		unset( $cpts[ 'bwg_share' ] );
		unset( $cpts[ 'bwg_tag' ] );
		unset( $cpts[ 'bricks_fonts' ] );
		unset( $cpts[ 'bricks_template' ] );
		unset( $cpts[ 'carousels' ] );
		unset( $cpts[ 'custom_css' ] );
		unset( $cpts[ 'customize_changeset' ] );
		unset( $cpts[ 'customize_css' ] );
		unset( $cpts[ 'dlm_download' ] );
		unset( $cpts[ 'dlm_download_version' ] );
		unset( $cpts[ 'e-floating-button' ] );
		unset( $cpts[ 'elementor_font' ] );
		unset( $cpts[ 'elementor_icons' ] );
		unset( $cpts[ 'elementor_library' ] );
		unset( $cpts[ 'elementor_snippet' ] );
		unset( $cpts[ 'et_body_layout' ] );
		unset( $cpts[ 'et_code_snippet' ] );
		unset( $cpts[ 'et_footer_layout' ] );
		unset( $cpts[ 'et_header_layout' ] );
		unset( $cpts[ 'et_theme_builder' ] );
		unset( $cpts[ 'et_theme_options' ] );
		unset( $cpts[ 'et_tb_item' ] );
		unset( $cpts[ 'et_template' ] );
		unset( $cpts[ 'etheme_mega_menus' ] );
		unset( $cpts[ 'etheme_slides' ] );
		unset( $cpts[ 'fl-builder-template' ] );
		unset( $cpts[ 'fl-theme-layout' ] );
		unset( $cpts[ 'grw_feed' ] );
		unset( $cpts[ 'ils_customlinks' ] );
		unset( $cpts[ 'jp_pay_order' ] );
		unset( $cpts[ 'llms_access_plan' ] );
		unset( $cpts[ 'llms_achievement' ] );
		unset( $cpts[ 'llms_certificate' ] );
		unset( $cpts[ 'llms_coupon' ] );
		unset( $cpts[ 'llms_email' ] );
		unset( $cpts[ 'llms_engagement' ] );
		unset( $cpts[ 'llms_membership' ] );
		unset( $cpts[ 'llms_my_certificate' ] );
		unset( $cpts[ 'llms_order' ] );
		unset( $cpts[ 'llms_review' ] );
		unset( $cpts[ 'llms_transaction' ] );
		unset( $cpts[ 'llms_voucher' ] );
		unset( $cpts[ 'masonry_gallery' ] );
		unset( $cpts[ 'mc4wp-form' ] );
		unset( $cpts[ 'memberpresscoupon' ] );
		unset( $cpts[ 'memberpressgroup' ] );
		unset( $cpts[ 'memberpressrule' ] );
		unset( $cpts[ 'mp-reminder' ] );
		unset( $cpts[ 'nav_menu_item' ] );
		unset( $cpts[ 'nf_sub' ] );
		unset( $cpts[ 'oembed_cache' ] );
		unset( $cpts[ 'patterns_ai_data' ] );
		unset( $cpts[ 'product_variation' ] );
		unset( $cpts[ 'rm_content_editor' ] );
		unset( $cpts[ 'revision' ] );
		unset( $cpts[ 'scheduled-action' ] );
		unset( $cpts[ 'scheduled_action' ] );
		unset( $cpts[ 'section' ] );
		unset( $cpts[ 'sg_optimizer_jobs' ] );
		unset( $cpts[ 'shop_coupon' ] );
		unset( $cpts[ 'shop_order' ] );
		unset( $cpts[ 'shop_order_placehold' ] );
		unset( $cpts[ 'shop_order_refund' ] );
		unset( $cpts[ 'slides' ] );
		unset( $cpts[ 'staticblocks' ] );
		unset( $cpts[ 'um_form' ] );
		unset( $cpts[ 'um_role' ] );
		unset( $cpts[ 'user_request' ] );
		unset( $cpts[ 'vc4_templates' ] );
		unset( $cpts[ 'wpcf7_contact_form' ] );
		unset( $cpts[ 'wpcf7r_action' ] );
		unset( $cpts[ 'wpcf7r_leads' ] );
		unset( $cpts[ 'wp_block' ] );
		unset( $cpts[ 'wp_font_face' ] );
		unset( $cpts[ 'wp_font_family' ] );
		unset( $cpts[ 'wp_global_styles' ] );
		unset( $cpts[ 'wp_navigation' ] );
		unset( $cpts[ 'wp_template' ] );
		unset( $cpts[ 'wp_template_part' ] );
		unset( $cpts[ 'wpdiscuz_form' ] );

		$cpts    = \apply_filters( 'multicheck_posttype_' . $field->args[ '_id' ], $cpts );
		$options = '';
		$i       = 1;
		$values  = (array) $escaped_value;

		if ( $cpts ) {
			foreach ( $cpts as $cpt ) {
				$obj   = \get_post_type_object( $cpt );
				$label = $obj->labels->singular_name . ' (' . $cpt . ')';
				$args  = array(
					'value' => $cpt,
					'label' => $label,
					'type'  => 'checkbox',
					'name'  => $field->args[ '_name' ] . '[]',
				);
				if ( in_array( $cpt, $values, false ) ) { //phpcs:ignore
					$args[ 'checked' ] = 'checked';
				}

				$options .= $field_type_object->list_input( $args, $i );

				$i++;
			}
		}

		$classes = false === $field->args( 'select_all_button' ) ? 'cmb2-checkbox-list no-select-all cmb2-list' : 'cmb2-checkbox-list cmb2-list';
		echo $field_type_object->radio( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		array(
			'class'   => $classes,
			'options' => $options, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		),
		'multicheck_posttype'
		);
	}

}
