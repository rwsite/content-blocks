<?php
/**
 * Reusable content block
 */

class cb_main {

    public $plugin_name;
    public $plugin_version;
    public $option_name;
    public $option_prefix;
    public $form_element_prefix;
    public $nonce_name;
    public $post_type_slug;
    public $post_type_label;
    public $post_type_labels;
    public $post_type_menu_icon;
    public $usage_about_page;
    public $post_type_shortcode;

    public $plugin_file;
    public $plugin_path;
    public $plugin_url;
    public $plugin_basename;

    public $circular_block_tracker = array();
    public $page_title = '';
    public $content_block_list = array();
    public $content_block_slug_list = array();
    public $content_block_list_by_slug = array();
    public $option_values = array();
    public $para_list;
    public $var_values = array();


	public function __construct($plugin_params = null) {
	    
	    $this->plugin_name = __('Content Block', 'block');
        $this->plugin_version = '1.0.0';
        $this->option_name = 'cb_option';
        $this->option_prefix = $this->option_name;
        $this->form_element_prefix = 'cb-form-item-';
        $this->nonce_name = 'cb_nonce';
        $this->post_type_slug = 'block';
        $this->post_type_label = __('Block');
        $this->post_type_labels = __('Block');
        $this->post_type_menu_icon = 'dashicons-screenoptions';
        $this->usage_about_page = 'cb_usage_about_page';
        $this->post_type_shortcode = $this->post_type_slug ;
            
		$plugin_file = $plugin_params['file'];
		$this->plugin_file     = $plugin_file;
		$this->plugin_path     = plugin_dir_path( $this->plugin_file );
		$this->plugin_url      = plugin_dir_url( $this->plugin_file );
		$this->plugin_basename = plugin_basename( $this->plugin_file );

        $this->para_list = array(
            'none'                     => 'No Paragraph Tags / Run Shortcodes',
            'no-shortcodes'            => 'No Paragraph Tags / No Shortcodes',
            'paragraphs'               => 'Add Paragraph Tags / Run Shortcodes',
            'paragraphs-no-shortcodes' => 'Add Paragraph Tags / No Shortcodes',
            'full'                     => 'Full Content Filtering'
        );
		
	}

	public static function plugin_activate() {
		flush_rewrite_rules();
	}

	public static function plugin_deactivate() {
		flush_rewrite_rules();
	}

	public function add_actions(){
	    
        add_action( 'init',                         [$this, 'register_post'] );
        add_action( 'widgets_init',                 [$this, 'register_widget'] );
        add_action( 'wp_head',                      [$this, 'do_wp_head'] );
        add_action( 'admin_enqueue_scripts',        [$this, 'enqueue_scripts'] );
        add_filter( 'pre_get_posts',                [$this,'reorder_list'] );
        add_filter( 'manage_' . $this->post_type_slug . '_posts_columns', [$this, 'set_column_titles'] );
        add_action( 'manage_' . $this->post_type_slug . '_posts_custom_column', [$this, 'set_columns'], 10, 2 );
        add_filter( 'enter_title_here',             [$this, 'change_title_text'] );

        add_shortcode( $this->post_type_shortcode,  [$this, 'content_block_shortcode'] );
        add_filter( 'the_content',                  [$this,'do_the_content'] );
        add_action( 'wp_ajax_cb_hide_notice',       [$this, 'hide_notice'] );

        // We safely integrate with VC with this hook
        add_action( 'init', [$this, 'integrateWithVC'] );
    }

	public function register_post() {

		$user               = wp_get_current_user();
		$allowed_roles      = array(
			'editor',
			'administrator',
			'author'
		);
		$publicly_queryable = array_intersect( $allowed_roles, $user->roles );

		register_post_type( $this->post_type_slug, array(
			'label'                => __( $this->post_type_labels, 'block' ),
			'labels'               => array(
				'name'               => __( $this->post_type_labels, 'block' ),
				'singular_name'      => __( $this->post_type_label, 'block' ),
				'menu_name'          => __( $this->post_type_labels, 'block' ),
				'name_admin_bar'     => __( $this->post_type_label, 'block' ),
				'all_items'          => __( 'All ' . $this->post_type_labels, 'block' ),
				'add_new'            => __( 'Add ' . $this->post_type_label, 'block' ),
				'add_new_item'       => __( 'Add ' . $this->post_type_label, 'block' ),
				'edit_item'          => __( 'Edit ' . $this->post_type_label, 'block' ),
				'new_item'           => __( 'New ' . $this->post_type_label, 'block' ),
				'view_item'          => __( 'View ' . $this->post_type_label, 'block' ),
				'search_items'       => __( 'Search ' . $this->post_type_labels, 'block' ),
				'not_found'          => __( 'No ' . strtolower( $this->post_type_labels ) . ' found', 'block' ),
				'not_found_in_trash' => __( 'No ' . strtolower( $this->post_type_labels ) . ' found in the Trash', 'block' ),
				'parent_item_colon'  => __( 'Parent ' . $this->post_type_label, 'block' )
			),
			'public'               => $publicly_queryable,
			'exclude_from_search'  => true,
			'publicly_queryable'   => $publicly_queryable,
			'show_ui'              => true,
			'show_in_nav_menus'    => false,
			'show_in_menu'         => true,
			'show_in_admin_bar'    => true,
			'menu_icon'            => $this->post_type_menu_icon,
			'hierarchical'         => false,
			'supports'             => array(
				'title',
				'editor'
			),                         /** @see cb_main::meta_box() */
			'register_meta_box_cb' => [$this, 'meta_box'],
			'has_archive'          => false
		) );

		flush_rewrite_rules();

		$args = array(
			'post_type'   => $this->post_type_slug,
			'post_status' => 'any',
			'nopaging'    => true
		);

		$content_blocks = get_posts( $args );

		foreach ( $content_blocks as $content_block ) {
			if ( trim( $content_block->post_title ) == '' ) {
				$new_content_block_values = array(
					'ID'         => $content_block->ID,
					'post_title' => __( 'Content Block', 'block' ) . ' ' . $content_block->ID
				);

				wp_update_post( $new_content_block_values );
			}
		}

		$args = array(
			'post_type'   => $this->post_type_slug,
			'post_status' => 'publish',
			'nopaging'    => true
		);

		$content_blocks = get_posts( $args );
		$this->content_block_list = array();

		foreach ( $content_blocks as $content_block ) {
			$this->content_block_list[ $content_block->ID ]                = $content_block->post_title;
			$this->content_block_slug_list[ $content_block->ID ]           = $content_block->post_name;
			$this->content_block_list_by_slug[ $content_block->post_name ] = $content_block->ID;
		}
	}

	public function register_widget() {
		register_widget( 'cb_widget' );
	}

	public function do_wp_head() {

		$this->page_title = get_the_title();
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog', array(
			'jquery',
			'jquery-ui-core'
		) );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	public function reorder_list( $query ) {

		if ( $query->is_admin ) {
			if ( $query->get( 'post_type' ) == $this->post_type_slug ) {
				$query->set( 'orderby', 'post_title' );
				$query->set( 'order', 'ASC' );
			}
		}

		return $query;
	}

	public function set_column_titles() {

		return array(
			'cb'                                => '<input type="checkbox"/>',
			'title'                             => __( 'Title', 'block' ),
			$this->post_type_slug . 'shortcode' => __( 'Shortcode', 'block' ),
			'date'                              => __( 'Date', 'block' )
		);

	}

	public function set_columns( $column, $post_id ) {

		switch ( $column ) {
			case $this->post_type_slug . 'shortcode' :

				echo '<input type="text" class="cb_read_only_input" value="' . esc_attr( '[' . $this->post_type_shortcode . ' id="' . $post_id . '"]' ) . '" style="width: 270px !important;"/>';
				break;

			default :
				break;
		}

	}



	public function change_title_text( $title ) {
        if ( $this->post_type_slug === get_post_type() ) {
            return __( 'Enter block title', 'block' );
		}
		return $title;
	}


	public function meta_box( $post ) {
		add_meta_box( $post->id, __( 'Block usage', 'block' ), [$this, 'meta_box_screen'], $this->post_type_slug, 'side', 'default' );
	}

	public function is_edit_page( $new_edit = null ) {
		global $pagenow;
		if ( ! is_admin() ) {
			return false;
		}
		if ( $new_edit == "edit" ) {
			return in_array( $pagenow, array( 'post.php', ) );
		} elseif ( $new_edit == "new" ) {
			return in_array( $pagenow, array( 'post-new.php' ) );
		} else {
			return in_array( $pagenow, array(
				'post.php',
				'post-new.php'
			) );
		}
	}

	public function meta_box_screen( $post ) {

		$code = array();

		if ( $this->is_edit_page( 'edit' ) ) {
			$post_id    = $post->ID;
			$post_slug  = $post->post_name;
			$code[]  = '[' . $this->post_type_shortcode . ' id="' . $post_id . '"]';
			/*$code[ 1 ]  = '[' . $this->post_type_shortcode . ' id="' . $post_id . '" para="no-shortcodes"]';
			$code[ 2 ]  = '[' . $this->post_type_shortcode . ' id="' . $post_id . '" para="paragraphs"]';
			$code[ 3 ]  = '[' . $this->post_type_shortcode . ' id="' . $post_id . '" para="paragraphs-no-shortcodes"]';
			$code[ 4 ]  = '[' . $this->post_type_shortcode . ' id="' . $post_id . '" para="full"]';*/
			$code[]  = '[' . $this->post_type_shortcode . ' slug="' . $post_slug . '"]';
			/*$code[ 6 ]  = '[' . $this->post_type_shortcode . ' slug="' . $post_slug . '" para="no-shortcodes"]';
			$code[ 7 ]  = '[' . $this->post_type_shortcode . ' slug="' . $post_slug . '" para="paragraphs"]';
			$code[ 8 ]  = '[' . $this->post_type_shortcode . ' slug="' . $post_slug . '" para="paragraphs-no-shortcodes"]';
			$code[ 9 ]  = '[' . $this->post_type_shortcode . ' slug="' . $post_slug . '" para="full"]';*/
			$code[] = '<?php echo do_shortcode( \'[' . $this->post_type_shortcode . ' id="' . $post_id . '"]\'); ?>';
			/*$code[ 11 ] = '<?php echo do_shortcode( '. $code[ 1 ] .' ); ?>';
			$code[ 12 ] = '<?php echo do_shortcode( '. $code[ 2 ] .' ); ?>';
			$code[ 13 ] = '<?php echo do_shortcode( '. $code[ 3 ] .' ); ?>';
			$code[ 14 ] = '<?php echo do_shortcode( '. $code[ 4 ] .' ); ?>';*/
			$code[] = '<?php echo do_shortcode( \'[' . $this->post_type_shortcode . ' slug="' . $post_slug . '"]\' ); ?>';
			/*$code[ 16 ] = '<?php echo do_shortcode( '. $code[ 6 ] .' ); ?>';
			$code[ 17 ] = '<?php echo do_shortcode( '. $code[ 7 ] .' ); ?>';
			$code[ 18 ] = '<?php echo do_shortcode( '. $code[ 8 ] .' ); ?>';
			$code[ 19 ] = '<?php echo do_shortcode( '. $code[ 9 ] .' ); ?>';*/
		} else {
			$code[] = __( 'New block', 'block' );
		}

		$access            = array(
			__( 'Shortcode', 'block' ),
			__( 'PHP', 'block' )
		);
		$methods           = array(
			__( 'ID', 'block' ),
			__( 'Slug', 'block' )
		);
		$para_descriptions = array(
			__( 'No Paragraph Tags / Run Shortcodes', 'block' ),
			__( 'No Paragraph Tags / No Shortcodes', 'block' ),
			__( 'Add Paragraph Tags / Run Shortcodes', 'block' ),
			__( 'Add Paragraph Tags / No Shortcodes', 'block' ),
			__( 'Full Content Filtering', 'block' )
		);

		echo '<h1>'. __('Example usage','block') .'</h1>';
		echo '<div class="cb_meta">';
        foreach ($code as $cod) {
           echo '<div class="cb_meta_field" style="display: grid;"><input type="text" class="cb_read_only_input" id="' . esc_attr( $this->option_prefix . 'access_' . $cod ) . '" value="' . esc_attr( $cod ) . '" />
            </div>';
        }
        echo '</div>';
	}

	public function do_the_content( $content ) {

		if ( get_post_type() == $this->post_type_slug ) {
			if ( class_exists( 'Vc_Base' ) ) {
				$vc = new Vc_Base;
				$vc->addFrontCss();
			}
		}

		if ( get_post_type() == $this->post_type_slug ) {
			if ( class_exists( 'SiteOrigin_Panels' ) ) {
				$renderer = SiteOrigin_Panels::renderer();
				$renderer->add_inline_css( get_the_ID(), $renderer->generate_css( get_the_ID() ) );
			}
		}

		return $content;
	}

	public function get_content( $id, $para = false ) {
		global $wp_query;

		if ( in_array( $id, $this->circular_block_tracker ) ) {
			return '';
		}

		$this->circular_block_tracker[] = $id;

		$post_content = '';

		switch ( $para ) {

			case 'full':

				$args = array(
					'p'           => $id,
					'post_type'   => $this->post_type_slug,
					'post_status' => 'publish',
					'nopaging'    => true
				);

				if ( class_exists( 'FLBuilder' ) ) {
					ob_start();
					FLBuilder::render_query( $args );
					$post_content = ob_get_clean();
				} else {
					$the_query = new WP_Query( $args );

					$original_query = false;

					if ( class_exists( 'Vc_Base' ) && ( ! is_singular() ) ) {
						$original_query = $wp_query;
						$wp_query       = $the_query;
					}

					if ( $the_query->have_posts() ) {
						while ( $the_query->have_posts() ) {
							$the_query->the_post();
							$post_content = apply_filters( 'the_content', get_the_content() );
							break;
						}
					}

					if ( is_object( $original_query ) ) {
						if ( get_class( $original_query ) == 'WP_Query' ) {
							$wp_query = $original_query;
						}
					}

					wp_reset_postdata();
				}

				break;

			default:
				$args       = array(
					'p'           => $id,
					'post_type'   => $this->post_type_slug,
					'post_status' => 'publish',
					'nopaging'    => true
				);
				$block_post = get_posts( $args );

				if ( count( $block_post ) > 0 ) {
					switch ( $para ) {

						case 'paragraphs':
							$post_content = do_shortcode( wpautop( $block_post[ 0 ]->post_content ) );
							break;

						case 'paragraphs-no-shortcodes':
							$post_content = wpautop( $block_post[ 0 ]->post_content );
							break;

						case 'no-shortcodes':
							$post_content = $block_post[ 0 ]->post_content;
							break;

						default:
							$post_content = do_shortcode( $block_post[ 0 ]->post_content );
							break;

					}
				}

				break;
		}

		foreach ( array_keys( $this->circular_block_tracker, $id ) as $key ) {
			unset( $this->circular_block_tracker[ $key ] );
		}

		return $post_content;
	}

	/**
	 * @param bool $id
	 * @param bool $para
	 * @param array $vars
	 *
	 * @return mixed|string|void
	 */
	public function get_block_by_id( $id = false, $para = false, $vars = array() ) {
		$html = '';
		$id   = $this->get_clean_id( $id );
		$para = $this->get_clean_para( $para );

		if ( is_array( $vars ) ) {
			foreach ( $vars as $key => $value ) {
				$key = trim( strtolower( $key ) );
				if ( ( substr( $key, 0, 3 ) == 'var' ) && ( strlen( $key ) > 3 ) ) {
					if ( preg_match( '/^[a-z0-9]+$/', $key ) === 1 ) {
						$this->var_values[ $key ] = str_replace( "\\\"", "\"", $value );
					}
				}
			}
		}

		if ( $id !== false ) {
			$html = $this->get_content( $id, $para );
		}

		return $html;
	}

	public function get_block_by_slug( $slug = false, $para = false, $vars = array() ) {
		$html = '';

		$slug = $this->get_clean_slug( $slug );
		$para = $this->get_clean_para( $para );

		if ( is_array( $vars ) ) {
			foreach ( $vars as $key => $value ) {
				$key = trim( strtolower( $key ) );
				if ( ( substr( $key, 0, 3 ) == 'var' ) && ( strlen( $key ) > 3 ) ) {
					if ( preg_match( '/^[a-z0-9]+$/', $key ) === 1 ) {
						$this->var_values[ $key ] = str_replace( "\\\"", "\"", $value );
					}
				}
			}
		}

		if ( $slug !== false ) {
			$html = $this->get_content( $this->content_block_list_by_slug[ $slug ], $para );
		}

		return $html;
	}

	public function get_clean_id( $id = false ) {
		if ( preg_match( '/^((0{1})|([1-9]{1})|([1-9]{1}[0-9]*))$/', trim( strval( $id ) ) ) === 1 ) {
			if ( isset( $this->content_block_list[ $id ] ) ) {
				return intval( $id );
			}
		}

		return false;
	}

	public function get_clean_slug( $slug = '' ) {
		$slug = trim( strval( $slug ) );
		if ( $slug != '' ) {
			if ( isset( $this->content_block_list_by_slug[ $slug ] ) ) {
				return $slug;
			}
		}

		return false;
	}

	public function get_clean_para( $para = '' ) {
		if ( $para === false ) {
			return '';
		} elseif ( $para === true ) {
			return 'full';
		} else {
			$para = strtolower( trim( strval( $para ) ) );
			if ( in_array( $para, array(
				'',
				'no-shortcodes',
				'yes',
				'full',
				'paragraphs',
				'paragraphs-no-shortcodes'
			) ) ) {
				if ( $para == 'yes' ) {
					$para = 'full';
				}

				return $para;
			}
		}

		return '';
	}

	public function content_block_shortcode( $atts ) {

		$html = '';

		foreach ( $atts as $key => $value ) {
			$key = trim( strtolower( $key ) );
			if ( ( substr( $key, 0, 3 ) == 'var' ) && ( strlen( $key ) > 3 ) ) {
				if ( preg_match( '/^[a-z0-9]+$/', $key ) === 1 ) {
					$this->var_values[ $key ] = str_replace( "\\\"", "\"", $value );
				}
			}
		}

		$para = '';

		if ( isset( $atts[ 'para' ] ) ) {
			$para = $atts[ 'para' ];
		}

		if ( isset( $atts[ 'id' ] ) ) {
			$html = $this->get_block_by_id( $atts[ 'id' ], $para );
		} elseif ( isset( $atts[ 'slug' ] ) ) {
			$html = $this->get_block_by_slug( $atts[ 'slug' ], $para );
		} elseif ( isset( $atts[ 'getvar' ] ) ) {
			$var = trim( strtolower( $atts[ 'getvar' ] ) );
			if ( ( substr( $var, 0, 3 ) == 'var' ) && ( strlen( $var ) > 3 ) ) {
				if ( preg_match( '/^[a-z0-9-_]+$/', $var ) === 1 ) {
					if ( isset( $this->var_values[ $var ] ) ) {
						$html = esc_html( $this->var_values[ $var ] );
					}
				}
			}
		} elseif ( isset( $atts[ 'datetime' ] ) ) {
			if ( $datetime = date( $atts[ 'datetime' ] ) ) {
				$html = esc_html( $datetime );
			}
		} elseif ( isset( $atts[ 'info' ] ) ) {
			$info = strtolower( trim( strval( $atts[ 'info' ] ) ) );
			switch ( $info ) {
				case 'site-title':
					$html = esc_html( get_bloginfo( 'name' ) );
					break;
				case 'page-title':
					$html = esc_html( $this->page_title );
					break;
			}
		}

		return $html;
	}



	// Element Mapping
	public function integrateWithVC() {
		// Check if WPBakery Page Builder is installed
		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			// Display notice that Extend WPBakery Page Builder is required
			// add_action('admin_notices',  [$this, 'showVcVersionNotice']);
			return;
		}

		vc_map( array(
			"name" => __("Content block", 'block'),
			"description" => __("Add content block", 'block'),
			"base" => $this->post_type_shortcode,
			"class" => $this->post_type_shortcode,
			"controls" => "full",
			"icon" => defined('WPB_PLUGIN_URL') ? WPB_PLUGIN_URL . 'assets/img/icon.png' : '',
			"category" => __('Content', 'js_composer'),
			"params" => array(
				array(
					"type" => "dropdown",
					"holder" => "div",
					"heading" => __("Select Content Block by slug", 'block'),
					"param_name" => "slug",
					"value" => $this->content_block_slug_list,
					"description" => __("Select content block to insert on this page", 'block')
				),
			)
		) );
	}
	// Show notice if your plugin is activated but Visual Composer is not
	public function showVcVersionNotice() {
		echo '
        <div class="updated">
          <p>'.sprintf(__('<strong>%s</strong> requires <strong><a href="http://bit.ly/vcomposer" target="_blank">WPBakery Page builder</a></strong> plugin to be installed and activated on your site.', 'block'), $this->post_type_slug).'</p>
        </div>';
	}

}