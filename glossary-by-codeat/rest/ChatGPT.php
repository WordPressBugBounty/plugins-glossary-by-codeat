<?php
/**
 * Glossary
 *
 * @package Glossary
 * @author Codeat <support@codeat.co>
 * @copyright 2020
 * @license GPL 3.0+
 * @link https://codeat.co
 */

namespace Glossary\Rest;

use Glossary\Engine;
use Orhanerday\OpenAi\OpenAi;

/**
 * ChatGPT endpoints REST
 */
class ChatGPT extends Engine\Base {

	/**
	 * Initialize the class.
	 *
	 * @return bool
	 */
	public function initialize() {
		parent::initialize();

		\add_action( 'rest_api_init', array( $this, 'add_routes' ) );

		return true;
	}

	/**
	 * Our Rest endpoint
	 *
	 * @return void
	 */
	public function add_routes() {
		\register_rest_route(
			'wp/v2',
			'glossary/generate',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'openai_generate' ),
				'args'                => array(
					'prompt' => array(
						'default'  => 0,
						'required' => true,
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'edit_glossaries' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
				},
			)
		);
	}

	/**
	 * Generate the term content with OpenAI
	 *
	 * @param \WP_REST_Request $request Values.
	 * @return \WP_REST_Response|\WP_REST_Request|\WP_Error
	 */
	public function openai_generate( \WP_REST_Request $request ) { // phpcs:ignore
		$settings = \gl_get_settings_extra();

		if ( !\wp_verify_nonce( \strval( $request['nonce'] ), 'generate_nonce' ) ) { // @phpstan-ignore argument.type
			$response = \rest_ensure_response( __( 'Invalid nonce', GT_TEXTDOMAIN ) );
			$response->set_status( 500 );

			return $response;
		}

		$error = '';

		if ( isset( $settings['openai_key'] ) && !empty( $request[ 'prompt' ] ) ) {
			$open_ai = new OpenAi( $settings['openai_key'] );

			// Current-generation models reject max_tokens, temperature and penalty parameters.
			$message = $open_ai->chat(
				array(
					'model'                 => self::resolve_model( \strval( $settings['openai_model'] ?? '' ) ),
					'messages'              => array(
						array(
							'role'    => 'user',
							'content' => $request[ 'prompt' ],
						),
					),
					'max_completion_tokens' => 16000,
				)
			);
			$message = json_decode( \strval( $message ) );

			if ( is_object( $message ) ) {
				if ( isset( $message->error ) && is_object( $message->error ) ) {
					$error = $message->error->message;
				}

				if ( is_array( $message->choices )
					&& is_object( $message->choices[0]->message ) ) { // @phpstan-ignore property.nonObject
					return \rest_ensure_response( $message->choices[0]->message->content ); // @phpstan-ignore property.nonObject
				}
			}
		}

		$response = \rest_ensure_response( __( 'Request not accepted: ', GT_TEXTDOMAIN ) . $error );
		$response->set_status( 500 );

		return $response;
	}

	/**
	 * Fallback model used when the saved one is no longer offered.
	 */
	public static function default_model(): string {
		return 'gpt-6.1-sol';
	}

	/**
	 * OpenAI text models offered in the settings.
	 *
	 * @return array<string, string>
	 */
	public static function models(): array {
		return array(
			'gpt-6-astra'   => 'gpt-6-astra',
			'gpt-6.1-sol'   => 'gpt-6.1-sol',
			'gpt-6.1-terra' => 'gpt-6.1-terra',
			'gpt-6-luna'    => 'gpt-6-luna',
		);
	}

	/**
	 * Resolve a saved model, falling back to the default when it is no longer offered.
	 *
	 * @param string $model Saved model id.
	 */
	public static function resolve_model( string $model ): string {
		if ( \array_key_exists( $model, self::models() ) ) {
			return $model;
		}

		return self::default_model();
	}

}
