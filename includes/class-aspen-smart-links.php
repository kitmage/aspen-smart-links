<?php
/**
 * Main plugin class.
 *
 * @package AspenSmartLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Aspen_Smart_Links {
	const SHORTCODE = 'crm_tag_button';

	/**
	 * Namespaced admin-post action used by this plugin.
	 *
	 * @var string
	 */
	const ACTION = 'aspen_smart_links_tag_action';

	/**
	 * Legacy admin-post action kept for backward compatibility.
	 *
	 * @var string
	 */
	const LEGACY_ACTION = 'crm_tag_action';

	/**
	 * User meta key used to store tag IDs (plugin-local).
	 *
	 * @var string
	 */
	const USER_META_KEY = 'aspen_smart_links_tags';

	/**
	 * Singleton instance.
	 *
	 * @var Aspen_Smart_Links|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Aspen_Smart_Links
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_template_redirect' ), 1 );

		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_admin_post' ) );
		add_action( 'admin_post_' . self::LEGACY_ACTION, array( $this, 'handle_admin_post' ) );

		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_admin_post_nopriv' ) );
		add_action( 'admin_post_nopriv_' . self::LEGACY_ACTION, array( $this, 'handle_admin_post_nopriv' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'aspen-smart-links', false, basename( dirname( ASPEN_SMART_LINKS_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register frontend script (enqueued only when shortcode renders).
	 */
	public function register_assets() {
		wp_register_script(
			'aspen-smart-links',
			ASPEN_SMART_LINKS_PLUGIN_URL . 'assets/js/aspen-smart-links.js',
			array(),
			ASPEN_SMART_LINKS_VERSION,
			true
		);

		wp_localize_script(
			'aspen-smart-links',
			'AspenSmartLinks',
			array(
				'loadingText' => __( 'Loading...', 'aspen-smart-links' ),
			)
		);
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Shortcode renderer.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'text'   => __( 'Continue', 'aspen-smart-links' ),
				'action' => '',
				'tag_id' => '',
				'url'    => '/',
				'class'  => '',
			),
			$atts,
			self::SHORTCODE
		);

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$text   = sanitize_text_field( $atts['text'] );
		$action = sanitize_key( $atts['action'] );
		$tag_id = absint( $atts['tag_id'] );

		$url = trim( (string) $atts['url'] );
		$url = '' === $url ? '/' : $url;
		$url = esc_url_raw( $url );
		$url = '' === $url ? '/' : $url;

		$class = $this->sanitize_class_list( $atts['class'] );

		if ( ! in_array( $action, array( 'add', 'remove' ), true ) || ! $tag_id ) {
			return '';
		}

		$is_external = $this->is_external_url( $url );
		if ( ! $is_external ) {
			$url = $this->normalize_internal_url( $url );
		}

		if ( function_exists( 'wp_unique_id' ) ) {
			$form_id = wp_unique_id( 'aspen-smart-links-' );
		} else {
			$form_id = 'aspen-smart-links-' . wp_generate_password( 8, false, false );
		}

		// Only load JS if this shortcode actually renders.
		wp_enqueue_script( 'aspen-smart-links' );

		$nonce_action = 'aspen_smart_links|' . $action . '|' . $tag_id;

		$current_url = home_url( add_query_arg( array(), wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) );

		if ( $is_external ) {
			$redirect_target = $current_url;
		} else {
			$redirect_target = $url;
		}

		$redirect_target = rawurlencode( $redirect_target );

		$nonce_value = wp_create_nonce( $nonce_action );

		ob_start();
		?>
		<form
			method="get"
			id="<?php echo esc_attr( $form_id ); ?>"
			action="<?php echo esc_url( $current_url ); ?>"
			style="display:inline;"
			data-aspen-smart-links="1"
			<?php if ( $is_external ) : ?>
				data-aspen-external-url="<?php echo esc_url( $url ); ?>"
			<?php endif; ?>
		>
			<input type="hidden" name="asl_action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="asl_tag_id" value="<?php echo esc_attr( $tag_id ); ?>">
			<input type="hidden" name="asl_redirect" value="<?php echo esc_attr( $redirect_target ); ?>">
			<input type="hidden" name="_aspen_smart_links_nonce" value="<?php echo esc_attr( $nonce_value ); ?>">

			<button type="submit" <?php echo '' !== $class ? 'class="' . esc_attr( $class ) . '"' : ''; ?>>
				<?php echo esc_html( $text ); ?>
			</button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle tag actions triggered via query args (front-end).
	 *
	 * This approach works well with FluentCRM because it runs on a normal front-end request.
	 */
	public function maybe_handle_template_redirect() {
		if ( empty( $_GET['asl_action'] ) || empty( $_GET['asl_tag_id'] ) || empty( $_GET['_aspen_smart_links_nonce'] ) ) {
			return;
		}

		$current_url = home_url( add_query_arg( array(), wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) );
		$clean_url   = remove_query_arg(
			array(
				'asl_action',
				'asl_tag_id',
				'asl_redirect',
				'_aspen_smart_links_nonce',
			),
			$current_url
		);

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $clean_url ) );
			exit;
		}

		$action = sanitize_key( (string) wp_unslash( $_GET['asl_action'] ) );
		$tag_id = absint( wp_unslash( $_GET['asl_tag_id'] ) );
		$nonce  = sanitize_text_field( (string) wp_unslash( $_GET['_aspen_smart_links_nonce'] ) );

		if ( ! in_array( $action, array( 'add', 'remove' ), true ) || ! $tag_id ) {
			$this->safe_redirect( $clean_url );
		}

		if ( ! wp_verify_nonce( $nonce, 'aspen_smart_links|' . $action . '|' . $tag_id ) ) {
			$this->safe_redirect( $clean_url );
		}

		$redirect = isset( $_GET['asl_redirect'] ) ? (string) wp_unslash( $_GET['asl_redirect'] ) : '';
		$redirect = rawurldecode( $redirect );

		$user_id = get_current_user_id();

		$context = array(
			'request_action' => 'template_redirect',
			'referer'        => wp_get_referer(),
		);

		$result = $this->apply_tag_action( $user_id, $action, $tag_id, $context );

		do_action( 'aspen_smart_links_tag_action', $user_id, $action, $tag_id, $result, $context );

		$this->safe_redirect( '' !== $redirect ? $redirect : $clean_url );
	}

	/**
	 * Handle requests from logged-out users (should be rare since shortcode hides).
	 */
	public function handle_admin_post_nopriv() {
		$redirect = wp_get_referer();
		$redirect = $redirect ? $redirect : home_url( '/' );

		wp_safe_redirect( wp_login_url( $redirect ) );
		exit;
	}

	/**
	 * Handle tag action POST request.
	 */
	public function handle_admin_post() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$this->handle_admin_post_nopriv();
		}

		$is_legacy_request = isset( $_POST['crm_tag_action_type'] ) || isset( $_POST['crm_tag_id'] ) || isset( $_POST['crm_redirect'] );

		if ( $is_legacy_request ) {
			$post_action = isset( $_POST['crm_tag_action_type'] ) ? sanitize_key( wp_unslash( $_POST['crm_tag_action_type'] ) ) : '';
			$tag_id      = isset( $_POST['crm_tag_id'] ) ? absint( wp_unslash( $_POST['crm_tag_id'] ) ) : 0;
		} else {
			$post_action = isset( $_POST['asl_action'] ) ? sanitize_key( wp_unslash( $_POST['asl_action'] ) ) : '';
			$tag_id      = isset( $_POST['asl_tag_id'] ) ? absint( wp_unslash( $_POST['asl_tag_id'] ) ) : 0;
		}

		if ( ! in_array( $post_action, array( 'add', 'remove' ), true ) || ! $tag_id ) {
			$this->safe_redirect( $this->get_posted_redirect( $is_legacy_request ) );
		}

		if ( $is_legacy_request ) {
			$nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			$nonce_action = 'crm_tag_action|' . $post_action . '|' . $tag_id;
		} else {
			$nonce        = isset( $_POST['_aspen_smart_links_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_aspen_smart_links_nonce'] ) ) : '';
			$nonce_action = 'aspen_smart_links|' . $post_action . '|' . $tag_id;
		}

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'aspen-smart-links' ), 403 );
		}

		$context = array(
			'request_action' => isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '',
			'referer'        => wp_get_referer(),
		);

		$result = $this->apply_tag_action( $user_id, $post_action, $tag_id, $context );

		/**
		 * Fires after a tag action is processed.
		 *
		 * @param int    $user_id Current user ID.
		 * @param string $action  add|remove.
		 * @param int    $tag_id  Tag ID.
		 * @param bool   $result  Whether the action succeeded.
		 * @param array  $context Request context.
		 */
		do_action( 'aspen_smart_links_tag_action', $user_id, $post_action, $tag_id, $result, $context );

		$this->safe_redirect( $this->get_posted_redirect( $is_legacy_request ) );
	}

	/**
	 * Apply the tag action (plugin-local user meta + optional integrations).
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  add|remove.
	 * @param int    $tag_id  Tag ID.
	 * @param array  $context Request context.
	 * @return bool
	 */
	private function apply_tag_action( $user_id, $action, $tag_id, $context ) {
		/**
		 * Allow an external integration to fully handle the tag action.
		 *
		 * Return true/false to indicate success, or null to fall back to plugin default behavior.
		 *
		 * @param bool|null $handled  Result or null to fall back.
		 * @param int       $user_id  User ID.
		 * @param string    $action   add|remove.
		 * @param int       $tag_id   Tag ID.
		 * @param array     $context  Request context.
		 */
		$handled = apply_filters( 'aspen_smart_links_handle_tag_action', null, $user_id, $action, $tag_id, $context );

		if ( null !== $handled ) {
			return (bool) $handled;
		}

		$crm_result = $this->maybe_apply_fluentcrm_tag_action( $user_id, $action, $tag_id );

		$default_store_user_meta = ( null === $crm_result );
		$store_user_meta         = (bool) apply_filters( 'aspen_smart_links_store_user_meta', $default_store_user_meta, $user_id, $action, $tag_id, $context );

		$meta_result = null;
		if ( $store_user_meta ) {
			$meta_result = $this->update_user_meta_tags( $user_id, $action, $tag_id );
		}

		// Prefer CRM result when available.
		if ( null !== $crm_result ) {
			return (bool) $crm_result;
		}

		return (bool) $meta_result;
	}

	/**
	 * Update plugin-local tag list in user meta.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  add|remove.
	 * @param int    $tag_id  Tag ID.
	 * @return bool
	 */
	private function update_user_meta_tags( $user_id, $action, $tag_id ) {
		$tags = get_user_meta( $user_id, self::USER_META_KEY, true );
		$tags = is_array( $tags ) ? $tags : array();
		$tags = array_values( array_filter( array_map( 'absint', $tags ) ) );

		if ( 'add' === $action ) {
			if ( in_array( $tag_id, $tags, true ) ) {
				return true;
			}

			$tags[] = $tag_id;
			$tags   = array_values( array_unique( $tags ) );
		} else {
			if ( ! in_array( $tag_id, $tags, true ) ) {
				return true;
			}

			$tags = array_values( array_diff( $tags, array( $tag_id ) ) );
		}

		return false !== update_user_meta( $user_id, self::USER_META_KEY, $tags );
	}

	/**
	 * Apply a FluentCRM tag action (best effort).
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  add|remove.
	 * @param int    $tag_id  FluentCRM Tag ID.
	 * @return bool|null True/false if FluentCRM attempted, or null if FluentCRM not available/disabled.
	 */
	private function maybe_apply_fluentcrm_tag_action( $user_id, $action, $tag_id ) {
		$enabled = (bool) apply_filters( 'aspen_smart_links_enable_fluentcrm', true, $user_id, $action, $tag_id );
		if ( ! $enabled ) {
			return null;
		}

		if ( ! $this->is_fluentcrm_available() ) {
			return null;
		}

		if ( function_exists( 'fluentcrm_get_current_contact' ) ) {
			$contact = fluentcrm_get_current_contact();
			if ( is_object( $contact ) ) {
				try {
					$tag_ids = array( absint( $tag_id ) );
					if ( 'add' === $action && method_exists( $contact, 'attachTags' ) ) {
						$contact->attachTags( $tag_ids );
						return true;
					}
					if ( 'remove' === $action && method_exists( $contact, 'detachTags' ) ) {
						$contact->detachTags( $tag_ids );
						return true;
					}
				} catch ( Exception $e ) {
					$this->debug_log(
						'FluentCRM: fluentcrm_get_current_contact exception',
						array(
							'user_id' => $user_id,
							'action'  => $action,
							'tag_id'  => $tag_id,
							'message' => $e->getMessage(),
						)
					);
					return false;
				} catch ( Error $e ) {
					$this->debug_log(
						'FluentCRM: fluentcrm_get_current_contact error',
						array(
							'user_id' => $user_id,
							'action'  => $action,
							'tag_id'  => $tag_id,
							'message' => $e->getMessage(),
						)
					);
					return false;
				}
			}
		}

		$contacts_api = $this->get_fluentcrm_contacts_api();
		if ( ! is_object( $contacts_api ) ) {
			$this->debug_log( 'FluentCRM: Contacts API unavailable', array( 'user_id' => $user_id ) );
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			$this->debug_log( 'FluentCRM: missing WP user email', array( 'user_id' => $user_id ) );
			return false;
		}

		$email = sanitize_email( (string) $user->user_email );
		if ( '' === $email ) {
			$this->debug_log( 'FluentCRM: invalid WP user email', array( 'user_id' => $user_id ) );
			return false;
		}

		try {
			$contact = null;

			if ( method_exists( $contacts_api, 'getContactByUserRef' ) ) {
				$contact = $contacts_api->getContactByUserRef( $user_id );
			}

			if ( ! $contact && method_exists( $contacts_api, 'getContact' ) ) {
				$contact = $contacts_api->getContact( $email );
			}

			$tag_ids = array( absint( $tag_id ) );

			$contact_exists = is_object( $contact );

			if ( ! $contact_exists ) {
				$create_if_missing = (bool) apply_filters( 'aspen_smart_links_fluentcrm_create_if_missing', true, $user_id, $action, $tag_id );
				if ( ! $create_if_missing ) {
					$this->debug_log(
						'FluentCRM: contact not found (create disabled)',
						array(
							'user_id' => $user_id,
							'action'  => $action,
							'tag_id'  => $tag_id,
						)
					);
					return false;
				}
			}

			// Fast path: use Subscriber model methods when a contact exists.
			if ( $contact_exists ) {
				if ( 'add' === $action && method_exists( $contact, 'attachTags' ) ) {
					$contact->attachTags( $tag_ids );
					return true;
				}
				if ( 'remove' === $action && method_exists( $contact, 'detachTags' ) ) {
					$contact->detachTags( $tag_ids );
					return true;
				}
			}

			// Fallback: use Contacts API createOrUpdate() with tags/detach_tags.
			if ( ! method_exists( $contacts_api, 'createOrUpdate' ) ) {
				$this->debug_log(
					'FluentCRM: Contacts API missing createOrUpdate()',
					array(
						'user_id' => $user_id,
						'action'  => $action,
						'tag_id'  => $tag_id,
					)
				);
				return false;
			}

			$data = array(
				'email' => $email,
			);

			if ( ! $contact_exists ) {
				$data['first_name'] = sanitize_text_field( (string) $user->first_name );
				$data['last_name']  = sanitize_text_field( (string) $user->last_name );
			}

			if ( 'add' === $action ) {
				$data['tags'] = $tag_ids;
			} else {
				$data['detach_tags'] = $tag_ids;
			}

			/**
			 * Filter contact data used when updating/creating a FluentCRM contact.
			 *
			 * @param array   $data   Contact data.
			 * @param WP_User $user   WP user.
			 * @param string  $action add|remove.
			 * @param int     $tag_id Tag ID.
			 */
			$data = (array) apply_filters( 'aspen_smart_links_fluentcrm_contact_data', $data, $user, $action, $tag_id );

			$result = $contacts_api->createOrUpdate( $data );
			if ( ! $result ) {
				$this->debug_log(
					'FluentCRM: createOrUpdate failed',
					array(
						'user_id'        => $user_id,
						'action'         => $action,
						'tag_id'         => $tag_id,
						'contact_exists' => $contact_exists,
					)
				);
				return false;
			}

			return true;
		} catch ( Exception $e ) {
			$this->debug_log(
				'FluentCRM: exception',
				array(
					'user_id'  => $user_id,
					'action'   => $action,
					'tag_id'   => $tag_id,
					'message'  => $e->getMessage(),
					'code'     => $e->getCode(),
					'exception'=> get_class( $e ),
				)
			);
			return false;
		} catch ( Error $e ) {
			$this->debug_log(
				'FluentCRM: error',
				array(
					'user_id' => $user_id,
					'action'  => $action,
					'tag_id'  => $tag_id,
					'message' => $e->getMessage(),
					'error'   => get_class( $e ),
				)
			);
			return false;
		}
	}

	/**
	 * Check if FluentCRM is available.
	 *
	 * @return bool
	 */
	private function is_fluentcrm_available() {
		return function_exists( 'FluentCrmApi' ) || function_exists( 'fluentcrm_get_current_contact' );
	}

	/**
	 * Get FluentCRM Contacts API when available.
	 *
	 * @return object|null
	 */
	private function get_fluentcrm_contacts_api() {
		try {
			$api = FluentCrmApi( 'contacts' );
			return is_object( $api ) ? $api : null;
		} catch ( Exception $e ) {
			return null;
		} catch ( Error $e ) {
			return null;
		}
	}

	/**
	 * Debug logger (off by default unless WP debug logging is enabled).
	 *
	 * @param string $message Message.
	 * @param array  $context Context (no PII).
	 */
	private function debug_log( $message, $context = array() ) {
		$default_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG );
		$enabled         = (bool) apply_filters( 'aspen_smart_links_debug', $default_enabled, $message, $context );
		if ( ! $enabled ) {
			return;
		}

		$prefix = '[Aspen Smart Links] ';

		$line = $prefix . (string) $message;
		if ( is_array( $context ) && ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line );
	}

	/**
	 * Redirect back to a safe location and exit.
	 */
	private function safe_redirect( $redirect = '' ) {
		$redirect = $this->normalize_redirect( (string) $redirect );
		$redirect = esc_url_raw( $redirect );

		if ( '' === $redirect ) {
			$redirect = wp_get_referer();
		}
		if ( '' === $redirect || false === $redirect ) {
			$redirect = home_url( '/' );
		}

		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Normalize an internal (non-external) redirect target.
	 *
	 * Admin-post redirects happen from `/wp-admin/`, so relative paths like `lesson-2/`
	 * would otherwise redirect to `/wp-admin/lesson-2/`.
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	private function normalize_internal_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '/';
		}

		$parts = wp_parse_url( $url );
		if ( is_array( $parts ) && ( isset( $parts['scheme'] ) || isset( $parts['host'] ) ) ) {
			// Absolute URL (already sanitized).
			return $url;
		}

		if ( 0 === strpos( $url, '?' ) || 0 === strpos( $url, '#' ) ) {
			return home_url( '/' ) . $url;
		}

		if ( 0 !== strpos( $url, '/' ) ) {
			$url = '/' . $url;
		}

		return $url;
	}

	/**
	 * Normalize a redirect string for admin-post responses.
	 *
	 * @param string $redirect Redirect target.
	 * @return string
	 */
	private function normalize_redirect( $redirect ) {
		$redirect = trim( (string) $redirect );
		if ( '' === $redirect ) {
			return '';
		}

		// Query/fragment-only redirects should go to the site root, not /wp-admin/.
		if ( 0 === strpos( $redirect, '?' ) || 0 === strpos( $redirect, '#' ) ) {
			return home_url( '/' ) . $redirect;
		}

		// If it looks like a scheme (e.g. https:, mailto:), leave it alone.
		if ( 1 === preg_match( '/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $redirect ) ) {
			return $redirect;
		}

		// Root-relative paths are fine. Everything else becomes root-relative.
		if ( 0 !== strpos( $redirect, '/' ) ) {
			$redirect = '/' . $redirect;
		}

		return $redirect;
	}

	/**
	 * Get a redirect target from POST data.
	 *
	 * @param bool $is_legacy_request Whether to use legacy field names.
	 * @return string
	 */
	private function get_posted_redirect( $is_legacy_request ) {
		if ( $is_legacy_request ) {
			return isset( $_POST['crm_redirect'] ) ? (string) wp_unslash( $_POST['crm_redirect'] ) : '';
		}

		return isset( $_POST['asl_redirect'] ) ? (string) wp_unslash( $_POST['asl_redirect'] ) : '';
	}

	/**
	 * Sanitize a whitespace-delimited list of CSS classes.
	 *
	 * @param string $class_list CSS classes.
	 * @return string
	 */
	private function sanitize_class_list( $class_list ) {
		$class_list = trim( (string) $class_list );
		if ( '' === $class_list ) {
			return '';
		}

		$classes = preg_split( '/\s+/', $class_list );
		$classes = is_array( $classes ) ? $classes : array();
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	/**
	 * Determine whether a URL should be treated as external.
	 *
	 * @param string $url URL (already sanitized with esc_url_raw()).
	 * @return bool
	 */
	private function is_external_url( $url ) {
		$url_parts  = wp_parse_url( $url );
		$site_parts = wp_parse_url( home_url() );

		if ( empty( $url_parts ) || empty( $site_parts ) ) {
			return false;
		}

		$url_host  = isset( $url_parts['host'] ) ? (string) $url_parts['host'] : '';
		$site_host = isset( $site_parts['host'] ) ? (string) $site_parts['host'] : '';

		if ( '' === $url_host ) {
			// Relative or host-less URL.
			return false;
		}

		$url_host  = strtolower( $url_host );
		$site_host = strtolower( $site_host );

		if ( 0 === strpos( $url_host, 'www.' ) ) {
			$url_host = substr( $url_host, 4 );
		}
		if ( 0 === strpos( $site_host, 'www.' ) ) {
			$site_host = substr( $site_host, 4 );
		}

		return '' !== $site_host && $url_host !== $site_host;
	}
}
