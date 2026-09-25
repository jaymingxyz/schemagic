<?php
/**
 * Location edit screen: tabbed form, health check, live preview.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes.
 */
final class Meta_Boxes {

	const NONCE_ACTION   = 'schemagic_save_location';
	const NONCE_NAME     = 'schemagic_nonce';
	const PREVIEW_ACTION = 'schemagic_preview';

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes_' . Location_Post_Type::POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . Location_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 1 );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( __CLASS__, 'ajax_preview' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add meta boxes.
	 */
	public static function add() {
		$type = Location_Post_Type::POST_TYPE;

		add_meta_box( 'schemagic-details', __( 'Business details', 'schemagic' ), array( __CLASS__, 'render_details' ), $type, 'normal', 'high' );
		add_meta_box( 'schemagic-preview', __( 'Schema preview', 'schemagic' ), array( __CLASS__, 'render_preview' ), $type, 'normal', 'default' );
		add_meta_box( 'schemagic-health', __( 'Schema health', 'schemagic' ), array( __CLASS__, 'render_health' ), $type, 'side', 'default' );
	}

	/**
	 * Location data for the form. New locations get sensible defaults.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private static function form_data( $post ) {
		if ( metadata_exists( 'post', $post->ID, Location_Post_Type::META_KEY ) ) {
			return Location_Post_Type::get_data( $post->ID );
		}

		$data = Fields::defaults();

		// A second location usually belongs on its own page, not the front page.
		if ( count( Location_Post_Type::published_ids() ) >= 1 ) {
			$data['display'] = 'pages';
		}

		return $data;
	}

	/**
	 * Tabbed details form.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_details( $post ) {
		$data     = self::form_data( $post );
		$sections = Fields::sections();
		$grouped  = array();

		foreach ( Fields::all() as $key => $field ) {
			$grouped[ $field['section'] ][ $key ] = $field;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<div class="schemagic-editor">';

		// Open the import box on new locations, where it's most useful.
		self::render_import( ! metadata_exists( 'post', $post->ID, Location_Post_Type::META_KEY ) );
		echo '<div class="schemagic-tabs" role="tablist" aria-label="' . esc_attr__( 'Business details sections', 'schemagic' ) . '">';

		foreach ( $sections as $id => $section ) {
			if ( empty( $grouped[ $id ] ) ) {
				continue;
			}

			printf(
				'<button type="button" class="schemagic-tab" role="tab" id="schemagic-tab-%1$s" aria-controls="schemagic-panel-%1$s" aria-selected="false" data-schemagic-tab="%1$s"%2$s>%3$s</button>',
				esc_attr( $id ),
				self::show_if_attr( $section ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in show_if_attr().
				esc_html( $section['label'] )
			);
		}

		echo '</div>';

		foreach ( $sections as $id => $section ) {
			if ( empty( $grouped[ $id ] ) ) {
				continue;
			}

			printf(
				'<div class="schemagic-panel" role="tabpanel" id="schemagic-panel-%1$s" aria-labelledby="schemagic-tab-%1$s"%2$s>',
				esc_attr( $id ),
				self::show_if_attr( $section ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in show_if_attr().
			);
			echo '<h3 class="schemagic-panel__title">' . esc_html( $section['label'] ) . '</h3>';

			foreach ( $grouped[ $id ] as $key => $field ) {
				self::row( $key, $field, isset( $data[ $key ] ) ? $data[ $key ] : null );
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Collapsible box for pasting existing JSON-LD. The textarea has no name, so it's never saved.
	 *
	 * @param bool $open Whether to show it expanded.
	 */
	private static function render_import( $open ) {
		?>
		<details class="schemagic-import"<?php echo $open ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Import existing schema', 'schemagic' ); ?></summary>
			<div class="schemagic-import__body">
				<p>
					<label for="schemagic-import-code">
						<?php esc_html_e( 'Paste JSON-LD code, a <script type="application/ld+json"> tag, or a page\'s HTML source. Schemagic fills in every field it finds, replacing what\'s there. Nothing is saved until you click Publish or Update.', 'schemagic' ); ?>
					</label>
				</p>
				<textarea id="schemagic-import-code" class="large-text code" rows="8" spellcheck="false" autocomplete="off" placeholder="<?php echo esc_attr( '{ "@context": "https://schema.org", "@type": "Dentist", "name": "…" }' ); ?>"></textarea>
				<p class="schemagic-import__actions">
					<button type="button" class="button" id="schemagic-import-run"><?php esc_html_e( 'Fill in fields', 'schemagic' ); ?></button>
					<span class="schemagic-import__choose" hidden>
						<label for="schemagic-import-choice"><?php esc_html_e( 'Business:', 'schemagic' ); ?></label>
						<select id="schemagic-import-choice"></select>
					</span>
					<span class="spinner"></span>
				</p>
				<div id="schemagic-import-result" aria-live="polite"></div>
			</div>
		</details>
		<?php
	}

	/**
	 * Health check box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_health( $post ) {
		$result = Health_Check::run( self::form_data( $post ), $post->post_title, $post->post_status );

		echo '<div id="schemagic-health-output" aria-live="polite">';
		echo Health_Check::render( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		echo '</div>';
	}

	/**
	 * Live preview box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_preview( $post ) {
		$data     = self::form_data( $post );
		$schema   = Schema_Builder::build( $data, $post->ID, $post->post_title );
		$test_url = self::test_url( $data );
		?>
		<p class="schemagic-preview__intro">
			<?php esc_html_e( 'This is the code Schemagic adds to your page. It updates as you edit.', 'schemagic' ); ?>
		</p>
		<pre class="schemagic-preview__code"><code id="schemagic-preview-code"><?php echo esc_html( Schema_Builder::encode( $schema, true ) ); ?></code></pre>
		<p class="schemagic-preview__actions">
			<button type="button" class="button" id="schemagic-copy"><?php esc_html_e( 'Copy code', 'schemagic' ); ?></button>
			<a class="button" id="schemagic-test-google" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( self::rich_results_url( $test_url ) ); ?>">
				<?php esc_html_e( 'Rich Results Test', 'schemagic' ); ?>
			</a>
			<a class="button" id="schemagic-test-validator" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( self::validator_url( $test_url ) ); ?>">
				<?php esc_html_e( 'Schema.org Validator', 'schemagic' ); ?>
			</a>
			<span class="schemagic-preview__status" id="schemagic-preview-status" aria-live="polite"></span>
		</p>
		<p class="description">
			<?php esc_html_e( 'The test tools load your live page, so publish or update first. If your site isn\'t public yet, copy the code and paste it into the tool instead.', 'schemagic' ); ?>
		</p>
		<?php
	}

	/**
	 * One labelled field row.
	 *
	 * @param string $key   Field key.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 */
	private static function row( $key, array $field, $value ) {
		$id       = 'schemagic-' . str_replace( '_', '-', $key );
		$priority = isset( $field['priority'] ) ? $field['priority'] : 'optional';
		$grouped  = in_array( $field['type'], array( 'radio', 'hours', 'special_hours' ), true );

		if ( 'checkbox' === $field['type'] && ! empty( $field['description'] ) ) {
			$field['checkbox_label'] = $field['description'];
			unset( $field['description'] );
		}

		printf( '<div class="schemagic-field schemagic-field--%s">', esc_attr( str_replace( '_', '-', $field['type'] ) ) );
		echo '<div class="schemagic-field__label">';

		if ( $grouped ) {
			printf( '<span class="schemagic-label" id="%s-label">%s</span>', esc_attr( $id ), esc_html( $field['label'] ) );
		} else {
			printf( '<label class="schemagic-label" for="%s">%s</label>', esc_attr( $id ), esc_html( $field['label'] ) );
		}

		if ( 'required' === $priority ) {
			echo ' <span class="schemagic-badge schemagic-badge--required">' . esc_html__( 'Required', 'schemagic' ) . '</span>';
		} elseif ( 'recommended' === $priority ) {
			echo ' <span class="schemagic-badge schemagic-badge--recommended">' . esc_html__( 'Recommended', 'schemagic' ) . '</span>';
		}

		echo '</div><div class="schemagic-field__control">';

		self::control( 'schemagic[' . $key . ']', $id, $field, $value );

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}

		echo '</div></div>';
	}

	/**
	 * Render a form control. Shared with the settings page.
	 *
	 * @param string $name  Input name.
	 * @param string $id    Input ID.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 */
	public static function control( $name, $id, array $field, $value ) {
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';

		switch ( $field['type'] ) {
			case 'text':
			case 'tel':
			case 'email':
			case 'url':
			case 'coordinate':
				$attrs = array(
					'text'       => 'type="text"',
					'tel'        => 'type="tel"',
					// Plain text inputs: browser validation on a hidden tab would silently block saving.
					'email'      => 'type="text" inputmode="email" autocomplete="email"',
					'url'        => 'type="text" inputmode="url"',
					'coordinate' => 'type="text" inputmode="decimal"',
				);
				printf(
					'<input %1$s class="%2$s" id="%3$s" name="%4$s" value="%5$s" placeholder="%6$s"%7$s />',
					$attrs[ $field['type'] ], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static strings.
					'coordinate' === $field['type'] ? 'schemagic-input--short' : 'regular-text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $placeholder ),
					! empty( $field['maxlength'] ) ? ' maxlength="' . esc_attr( (string) $field['maxlength'] ) . '"' : ''
				);
				break;

			case 'textarea':
			case 'lines':
			case 'url_lines':
				$text = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
				printf(
					'<textarea class="large-text" rows="4" id="%1$s" name="%2$s" placeholder="%3$s">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $placeholder ),
					esc_textarea( $text )
				);
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html( isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : $field['label'] )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $option => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'radio':
				printf( '<fieldset class="schemagic-radios" aria-labelledby="%s-label">', esc_attr( $id ) );
				foreach ( $field['options'] as $option => $label ) {
					printf(
						'<label><input type="radio" name="%1$s" value="%2$s"%3$s /> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $option ),
						checked( (string) $value, (string) $option, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'business_type':
				self::business_type_control( $name, $id, (string) $value );
				break;

			case 'country':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				echo '<option value="">' . esc_html__( '— Select a country —', 'schemagic' ) . '</option>';
				foreach ( Fields::countries() as $code => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $code ), selected( (string) $value, $code, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'image':
			case 'gallery':
				self::media_control( $name, $id, $field['type'], $value );
				break;

			case 'pages':
				self::pages_control( $name, $id, (array) $value );
				break;

			case 'hours':
				self::hours_control( $name, $id, (array) $value );
				break;

			case 'special_hours':
				self::special_hours_control( $name, $id, (array) $value );
				break;

			default:
				/**
				 * Render a custom field type registered through the schemagic_fields filter.
				 *
				 * @param string $name  Input name.
				 * @param string $id    Input ID.
				 * @param array  $field Field definition.
				 * @param mixed  $value Current value.
				 */
				do_action( 'schemagic_render_' . $field['type'], $name, $id, $field, $value );
		}
	}

	/**
	 * Searchable business type select.
	 *
	 * @param string $name  Input name.
	 * @param string $id    Input ID.
	 * @param string $value Current type.
	 */
	private static function business_type_control( $name, $id, $value ) {
		printf(
			'<input type="search" class="regular-text schemagic-type-search" placeholder="%1$s" aria-label="%1$s" aria-controls="%2$s" />',
			esc_attr__( 'Search business types…', 'schemagic' ),
			esc_attr( $id )
		);

		printf( '<select class="schemagic-type-select" id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );

		foreach ( Business_Types::tree() as $item ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $item['type'] ),
				selected( $value, $item['type'], false ),
				esc_html( str_repeat( '— ', $item['depth'] ) . $item['label'] )
			);
		}

		echo '</select>';
	}

	/**
	 * Media Library picker for one image or a gallery.
	 *
	 * @param string    $name  Input name.
	 * @param string    $id    Input ID.
	 * @param string    $type  'image' or 'gallery'.
	 * @param int|int[] $value Attachment ID(s).
	 */
	private static function media_control( $name, $id, $type, $value ) {
		$ids      = array_filter( array_map( 'absint', (array) $value ) );
		$multiple = 'gallery' === $type;

		printf( '<div class="schemagic-media" data-multiple="%s">', $multiple ? '1' : '0' );
		printf( '<input type="hidden" class="schemagic-media__value" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( implode( ',', $ids ) ) );

		echo '<div class="schemagic-media__preview">';
		foreach ( $ids as $attachment_id ) {
			echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'class' => 'schemagic-media__thumb' ) );
		}
		echo '</div>';

		printf(
			'<button type="button" class="button schemagic-media__select">%s</button> ',
			$multiple ? esc_html__( 'Choose photos', 'schemagic' ) : esc_html__( 'Choose image', 'schemagic' )
		);
		printf(
			'<button type="button" class="button-link schemagic-media__remove"%1$s>%2$s</button>',
			$ids ? '' : ' hidden',
			$multiple ? esc_html__( 'Remove all', 'schemagic' ) : esc_html__( 'Remove', 'schemagic' )
		);

		echo '</div>';
	}

	/**
	 * Multi-select of published pages.
	 *
	 * @param string $name     Input name.
	 * @param string $id       Input ID.
	 * @param int[]  $selected Selected page IDs.
	 */
	private static function pages_control( $name, $id, array $selected ) {
		$pages    = get_pages(
			array(
				'sort_column' => 'post_title',
				'post_status' => 'publish',
			)
		);
		$front_id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$selected = array_map( 'intval', $selected );

		if ( ! $pages ) {
			echo '<p>' . esc_html__( 'You have no published pages yet.', 'schemagic' ) . '</p>';
			return;
		}

		printf( '<select multiple size="8" class="schemagic-pages" id="%1$s" name="%2$s[]">', esc_attr( $id ), esc_attr( $name ) );

		foreach ( $pages as $page ) {
			$label = '' !== $page->post_title ? $page->post_title : __( '(no title)', 'schemagic' );

			if ( $page->ID === $front_id ) {
				/* translators: %s: page title. */
				$label = sprintf( __( '%s (front page)', 'schemagic' ), $label );
			}

			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $page->ID,
				in_array( (int) $page->ID, $selected, true ) ? ' selected' : '',
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Weekly hours table.
	 *
	 * @param string $name  Input name.
	 * @param string $id    Base ID.
	 * @param array  $hours Hours keyed by day.
	 */
	private static function hours_control( $name, $id, array $hours ) {
		$hours = wp_parse_args( $hours, Fields::default_hours() );

		printf( '<table class="schemagic-hours" role="presentation" id="%s">', esc_attr( $id ) );

		foreach ( Fields::days() as $day => $info ) {
			$row   = $hours[ $day ];
			$base  = $name . '[' . $day . ']';
			$modes = Fields::hour_modes();

			printf( '<tr class="schemagic-hours__day" data-day="%s">', esc_attr( $day ) );
			printf( '<th scope="row">%s</th>', esc_html( $info['label'] ) );

			printf( '<td><select class="schemagic-hours__mode" name="%1$s[mode]" aria-label="%2$s">', esc_attr( $base ), esc_attr( $info['label'] ) );
			foreach ( $modes as $mode => $label ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $mode ), selected( $row['mode'], $mode, false ), esc_html( $label ) );
			}
			echo '</select></td>';

			printf( '<td class="schemagic-hours__ranges" data-name="%s[ranges]">', esc_attr( $base ) );
			echo '<div class="schemagic-hours__list">';
			foreach ( array_values( (array) $row['ranges'] ) as $i => $range ) {
				self::range_row( $base . '[ranges]', (string) $i, $range['opens'], $range['closes'] );
			}
			echo '</div>';
			echo '<button type="button" class="button-link schemagic-hours__add">' . esc_html__( '+ Add hours', 'schemagic' ) . '</button>';
			echo '</td></tr>';
		}

		echo '</table>';
		echo '<p><button type="button" class="button schemagic-hours__copy">' . esc_html__( 'Copy Monday to Tuesday–Friday', 'schemagic' ) . '</button></p>';

		echo '<template id="schemagic-range-template">';
		self::range_row( '__NAME__', '__INDEX__', '', '' );
		echo '</template>';
	}

	/**
	 * One opens/closes pair.
	 *
	 * @param string $base   Name prefix.
	 * @param string $index  Row index.
	 * @param string $opens  Opening time.
	 * @param string $closes Closing time.
	 */
	private static function range_row( $base, $index, $opens, $closes ) {
		$prefix = $base . '[' . $index . ']';

		printf(
			'<span class="schemagic-range"><input type="time" name="%1$s[opens]" value="%2$s" aria-label="%3$s" /> <span aria-hidden="true">–</span> <input type="time" name="%1$s[closes]" value="%4$s" aria-label="%5$s" /> <button type="button" class="button-link schemagic-range__remove" aria-label="%6$s">&times;</button></span>',
			esc_attr( $prefix ),
			esc_attr( $opens ),
			esc_attr__( 'Opens', 'schemagic' ),
			esc_attr( $closes ),
			esc_attr__( 'Closes', 'schemagic' ),
			esc_attr__( 'Remove these hours', 'schemagic' )
		);
	}

	/**
	 * Special hours repeater.
	 *
	 * @param string $name Input name.
	 * @param string $id   Base ID.
	 * @param array  $rows Rows.
	 */
	private static function special_hours_control( $name, $id, array $rows ) {
		printf( '<div class="schemagic-special" id="%1$s" data-name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
		printf( '<table class="widefat striped schemagic-special__table"%s>', $rows ? '' : ' hidden' );
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'From', 'schemagic' ) . '</th>';
		echo '<th>' . esc_html__( 'Through', 'schemagic' ) . '</th>';
		echo '<th>' . esc_html__( 'Closed', 'schemagic' ) . '</th>';
		echo '<th>' . esc_html__( 'Opens', 'schemagic' ) . '</th>';
		echo '<th>' . esc_html__( 'Closes', 'schemagic' ) . '</th>';
		echo '<th><span class="screen-reader-text">' . esc_html__( 'Actions', 'schemagic' ) . '</span></th>';
		echo '</tr></thead><tbody>';

		foreach ( array_values( $rows ) as $i => $row ) {
			self::special_row( $name, (string) $i, $row );
		}

		echo '</tbody></table>';
		echo '<p><button type="button" class="button schemagic-special__add">' . esc_html__( 'Add date', 'schemagic' ) . '</button></p>';

		echo '<template id="schemagic-special-template">';
		self::special_row( $name, '__INDEX__', array() );
		echo '</template>';
		echo '</div>';
	}

	/**
	 * One special hours row.
	 *
	 * @param string $name  Input name.
	 * @param string $index Row index.
	 * @param array  $row   Row data.
	 */
	private static function special_row( $name, $index, array $row ) {
		$row    = wp_parse_args(
			$row,
			array(
				'from'    => '',
				'through' => '',
				'closed'  => 1,
				'opens'   => '',
				'closes'  => '',
			)
		);
		$prefix = $name . '[' . $index . ']';

		echo '<tr class="schemagic-special__row">';
		printf( '<td><input type="date" name="%1$s[from]" value="%2$s" aria-label="%3$s" /></td>', esc_attr( $prefix ), esc_attr( $row['from'] ), esc_attr__( 'From', 'schemagic' ) );
		printf( '<td><input type="date" name="%1$s[through]" value="%2$s" aria-label="%3$s" /></td>', esc_attr( $prefix ), esc_attr( $row['through'] ), esc_attr__( 'Through', 'schemagic' ) );
		printf( '<td><input type="checkbox" class="schemagic-special__closed" name="%1$s[closed]" value="1"%2$s aria-label="%3$s" /></td>', esc_attr( $prefix ), checked( ! empty( $row['closed'] ), true, false ), esc_attr__( 'Closed all day', 'schemagic' ) );
		printf( '<td><input type="time" class="schemagic-special__time" name="%1$s[opens]" value="%2$s" aria-label="%3$s" /></td>', esc_attr( $prefix ), esc_attr( $row['opens'] ), esc_attr__( 'Opens', 'schemagic' ) );
		printf( '<td><input type="time" class="schemagic-special__time" name="%1$s[closes]" value="%2$s" aria-label="%3$s" /></td>', esc_attr( $prefix ), esc_attr( $row['closes'] ), esc_attr__( 'Closes', 'schemagic' ) );
		printf( '<td><button type="button" class="button-link schemagic-special__remove" aria-label="%1$s">&times;</button></td>', esc_attr__( 'Remove this date', 'schemagic' ) );
		echo '</tr>';
	}

	/**
	 * data-show-if-type attribute for a section, if it has one.
	 *
	 * @param array $section Section definition.
	 * @return string
	 */
	private static function show_if_attr( array $section ) {
		if ( empty( $section['show_if_type'] ) ) {
			return '';
		}

		return ' data-show-if-type="' . esc_attr( $section['show_if_type'] ) . '"';
	}

	/**
	 * Save location data.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in Sanitizer::location().
		$raw   = isset( $_POST['schemagic'] ) ? wp_unslash( $_POST['schemagic'] ) : array();
		$clean = Sanitizer::location( $raw );

		// update_post_meta() unslashes its value, so slash it first to keep backslashes intact.
		update_post_meta( $post_id, Location_Post_Type::META_KEY, wp_slash( $clean ) );
	}

	/**
	 * AJAX: build the preview and health check from unsaved form data.
	 */
	public static function ajax_preview() {
		check_ajax_referer( self::PREVIEW_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id || Location_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in Sanitizer::location().
		$raw   = isset( $_POST['schemagic'] ) ? wp_unslash( $_POST['schemagic'] ) : array();
		$title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$data  = Sanitizer::location( $raw );
		$url   = self::test_url( $data );

		wp_send_json_success(
			array(
				'json'         => Schema_Builder::encode( Schema_Builder::build( $data, $post_id, $title ), true ),
				'health'       => Health_Check::render( Health_Check::run( $data, $title, get_post_status( $post_id ) ) ),
				'richResults'  => self::rich_results_url( $url ),
				'validator'    => self::validator_url( $url ),
			)
		);
	}

	/**
	 * The page URL the test tools should load.
	 *
	 * @param array $data Location data.
	 * @return string
	 */
	private static function test_url( array $data ) {
		if ( 'pages' === $data['display'] ) {
			return Schema_Builder::default_url( $data );
		}

		return home_url( '/' );
	}

	/**
	 * Google Rich Results Test URL.
	 *
	 * @param string $url Page URL.
	 * @return string
	 */
	private static function rich_results_url( $url ) {
		return 'https://search.google.com/test/rich-results?url=' . rawurlencode( $url );
	}

	/**
	 * Schema.org Validator URL.
	 *
	 * @param string $url Page URL.
	 * @return string
	 */
	private static function validator_url( $url ) {
		return 'https://validator.schema.org/#url=' . rawurlencode( $url );
	}

	/**
	 * Enqueue admin assets on the location editor and settings page.
	 */
	public static function enqueue() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$is_editor   = 'post' === $screen->base && Location_Post_Type::POST_TYPE === $screen->post_type;
		$is_settings = Settings::screen_id() === $screen->id;

		if ( ! $is_editor && ! $is_settings ) {
			return;
		}

		global $post;

		wp_enqueue_media();
		wp_enqueue_style( 'schemagic-admin', SCHEMAGIC_URL . 'assets/admin.css', array(), SCHEMAGIC_VERSION );
		wp_enqueue_script( 'schemagic-admin', SCHEMAGIC_URL . 'assets/admin.js', array( 'jquery' ), SCHEMAGIC_VERSION, true );

		wp_localize_script(
			'schemagic-admin',
			'schemagicAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'previewAction' => self::PREVIEW_ACTION,
				'nonce'         => $is_editor ? wp_create_nonce( self::PREVIEW_ACTION ) : '',
				'postId'        => $is_editor && $post ? (int) $post->ID : 0,
				'typeParents'   => $is_editor ? Business_Types::parent_map() : array(),
				'importAction'  => Importer::AJAX_ACTION,
				'importNonce'   => $is_editor ? wp_create_nonce( Importer::AJAX_ACTION ) : '',
				'fieldTypes'    => $is_editor ? wp_list_pluck( Fields::all(), 'type' ) : array(),
				'i18n'          => array(
					'chooseImage'   => __( 'Choose image', 'schemagic' ),
					'choosePhotos'  => __( 'Choose photos', 'schemagic' ),
					'useImage'      => __( 'Use this image', 'schemagic' ),
					'usePhotos'     => __( 'Use these photos', 'schemagic' ),
					'copied'        => __( 'Copied.', 'schemagic' ),
					'copyFailed'    => __( 'Copy failed. Select the code and copy it manually.', 'schemagic' ),
					'updating'      => __( 'Updating preview…', 'schemagic' ),
					'previewError'  => __( 'Preview could not be updated. Your changes will still save.', 'schemagic' ),
					'importEmpty'   => __( 'Paste some schema code first.', 'schemagic' ),
					'importFailed'  => __( 'The code couldn\'t be imported. Please try again.', 'schemagic' ),
					'importConfirm' => __( 'Importing replaces any field found in the code. Continue?', 'schemagic' ),
					'importFilled'  => __( 'Filled in:', 'schemagic' ),
					'importReview'  => __( 'Check each tab, then click Publish or Update to save.', 'schemagic' ),
					'importNotes'   => __( 'Some details need your attention:', 'schemagic' ),
				),
			)
		);
	}
}
