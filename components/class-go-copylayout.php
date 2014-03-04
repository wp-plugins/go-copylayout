<?php

class GO_CopyLayout
{
	public $script_version = 1;

	/**
	 * constructor
	 */
	public function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}//end __construct

	/**
	 * Hook into the init action to initialize the admin hooks (admin_init is too late)
	 */
	public function init()
	{
		if ( ! current_user_can( 'edit_theme_options' ) )
		{
			return;
		}//end if

		add_action('admin_menu', array( $this, 'admin_menu' ) );
	}//end init

	/**
	 * Hook into the wp_enqueue_scripts action to inject JS
	 */
	public function admin_enqueue_scripts()
	{
		global $pagenow;

		if ( 'widgets.php' != $pagenow )
		{
			return;
		}//end if

		wp_register_script(
			'go-copylayout',
			plugins_url( 'js/go-copylayout.js', __FILE__ ),
			array( 'jquery' ),
			$this->script_version,
			TRUE
		);

		wp_enqueue_script( 'go-copylayout' );

		wp_register_style(
			'go-copylayout',
			plugins_url( 'css/go-copylayout.css', __FILE__ ),
			array(),
			$this->script_version
		);

		wp_enqueue_style( 'go-copylayout' );
	}//end admin_enqueue_scripts

	/**
	 * Add the "Copy Layout" link to the admin sidebar.
	 */
	public function admin_menu()
	{
		add_theme_page('Copy Layout', 'Copy Layout', 'edit_theme_options', 'copy-layout', array( $this, 'page' ) );
	}//end admin_menu

	/**
	 * fixes the arguments so they are all snazzy-like
	 *
	 * @return $args snazzi-fied arguments
	 */
	public function fixup_args( $args = '' )
	{
		$defaults = array(
			'which' => 'sidebars_widgets,widgets',
			'base64' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		if( is_string( $args['which'] ) )
		{
			$args['which'] = explode( ',', $args['which'] );
		}//end if

		return $args;
	}//end fixup_args

	/**
	 * Get the sidebar/widget options from the options table
	 *
	 * @return $return widget options
	 */
	public function get_options( $args = '' )
	{
		$args = $this->fixup_args( $args );

		$options = wp_load_alloptions();

		$return = array();

		if( in_array( 'sidebars_widgets', $args['which'] ) )
		{
			$return['sidebars_widgets'] = $options['sidebars_widgets'];
		}//end if

		$do_widgets = in_array( 'widgets', $args['which'] );

		$widgets = array();

		foreach( $options as $name => $value )
		{
			if( $do_widgets && 'widget_' === substr($name, 0, 7) )
			{
				$widgets[ $name ] = $value;
			}//end if
		}//end foreach

		if( $do_widgets )
		{
			$return['widgets'] = $widgets;
		}//end if

		// array is full, return it

		$return = serialize( $return );

		if( $args['base64'] )
		{
			$return = base64_encode( $return );
		}//end if

		return $return;
	}//end get_options

	/**
	 * Display the page getting/setting the layout.
	 */
	public function page()
	{
		if( ! current_user_can('edit_theme_options') )
		{
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}//end if

		if( isset( $_POST['layout'] ) )
		{
			if ( ! check_admin_referer( 'go-copylayout-override', '_go_copylayout_override_nonce' ) )
			{
				wp_die( __('Invalid nonce') );
			}//end if

			return $this->replace_layout( $_POST['layout'] );
		}//end if

		$args = $this->fixup_args();

		$current = $this->get_options( $args );

		include_once __DIR__ . '/templates/admin.php';
	}//end page

	/**
	 * Replace the current layout with a user-submitted layout.
	 *
	 * @param
	 */
	public function replace_layout( $layout )
	{
		echo '<div class="wrap"><h2>Applying New Layout</h2><div class="content-container">';

		$layout = base64_decode($layout);

		if( false === $layout )
		{
			wp_die( 'Error during Base64 decoding. <a href="themes.php?page=copy-layout">Go back</a>?' );
		}//end if

		$layout = unserialize($layout);

		if( false === $layout )
		{
			wp_die( 'Error during unserialize operation. <a href="themes.php?page=copy-layout">Go back</a>?' );
		}//end if

		$options = wp_load_alloptions();

		// unserialize the sidebars_widgets - this is the cornerstone of the import
		$options['sidebars_widgets'] = isset( $options['sidebars_widgets'] ) ? unserialize( $options['sidebars_widgets'] ) : array();

		// unserialize all of the current site's widgets
		foreach ( $options as $name => $widget_data )
		{
			if( 'widget_' === substr( $name, 0, 7 ) )
			{
				$options[ $name ] = unserialize( $options[ $name ] );
			}//end if
		}//end foreach

		//
		// what do we have in the incoming array?
		//

		$has_widgets  = isset( $layout['widgets'] );
		$has_sidebars = isset( $layout['sidebars_widgets'] );

		//
		// delete things that need to be replaced
		//

		echo '<h3>Deleting options...</h3><ol>';
		$options = $this->delete_sidebars( $options, $layout );
		echo '</ol>';

		//
		// add layout pieces back in
		//

		echo '<h3>Adding options...</h3><ol>';

		if( $has_sidebars )
		{
			echo '<li>Adding sidebar_widgets...</li>';
			$options = $this->add_sidebars( $options, $layout );
		}//end if

		echo '</div></div>';
	}//end replace_layout

	/**
	 * Removes sidebars from the current site
	 *
	 * @param $options Array array of options
	 * @param $layout Array data from import
	 * @return $options updated array of options
	 */
	public function delete_sidebars( $options, $layout )
	{
		$has_widgets  = isset( $layout['widgets'] );
		$has_sidebars = isset( $layout['sidebars_widgets'] );

		if ( $has_sidebars )
		{
			echo '<li>Deleting sidebar_widgets...</li>';

			$sidebars_to_remove = is_array( $options['sidebars_widgets'] ) ? $options['sidebars_widgets'] : array();
			$sidebars_to_remove = apply_filters( 'go_copylayout_sidebars_to_remove', $sidebars_to_remove );

			foreach ( $sidebars_to_remove as $key => $sidebar )
			{
				unset( $options['sidebars_widgets'][ $key ] );
			}//end foreach

			update_option( 'sidebars_widgets', $options['sidebars_widgets'] );
		}//end if

		foreach ( $options as $name => $widget_data )
		{
			if ( ! $has_widgets || 'widget_' !== substr( $name, 0, 7 ) )
			{
				continue;
			}//end if

			echo '<li>Deleting ' . esc_html( $name ) . '...</li>';
			$widget_keys = array_keys( $widget_data );

			foreach ( $widget_keys as $key )
			{
				if ( '_multiwidget' == $key )
				{
					continue;
				}//end if

				$found = FALSE;

				foreach ( $options['sidebars_widgets'] as $sidebar )
				{
					if ( $found = in_array( substr( $name, 7 ) . "-{$key}", $sidebar ) )
					{
						break;
					}//end if
				}//end foreach

				if ( ! $found )
				{
					unset( $options[ $name ][ $key ] );
				}//end if
			}//end foreach

			update_option( $name, $options[ $name ] ?: array() );
		}//end foreach

		return $options;
	}//end delete_sidebars

	/**
	 * Adds sidebars from imported data
	 *
	 * @param $options Array array of options
	 * @param $layout Array data from import
	 * @return $options updated array of options
	 */
	public function add_sidebars( $options, $layout )
	{
		$has_widgets  = isset( $layout['widgets'] );
		$has_sidebars = isset( $layout['sidebars_widgets'] );

		$import_sidebars = unserialize( $layout['sidebars_widgets'] );

		if ( $options['sidebars_widgets'] )
		{
			// first, unserialize all of the widgets we're importing
			foreach ( $layout['widgets'] as $name => $value )
			{
				$layout['widgets'][ $name ] = unserialize( $value );
			}//end foreach

			foreach ( $import_sidebars as $import_sidebar_key => $import_sidebar )
			{
				// if the sidebar exists in the options, it has been marked for preservation - we don't
				// want to override that sidebar
				if ( isset( $options['sidebars_widgets'][ $import_sidebar_key ] ) || ! empty( $options['sidebars_widgets'][ $import_sidebar_key ] ) )
				{
					continue;
				}//end if

				// now we loop over the widgets that exist in the imported sidebar and import them,
				// overriding the imported widget IDs where necessary
				foreach ( $import_sidebar as $import_widget_key => $import_widget )
				{
					// get widget name and ID # from the widget ID slug
					preg_match( '/(.+)-([0-9]+)$/', $import_widget, $matches );

					// option names are prefixed
					$widget_option_name = "widget_{$matches[1]}";

					$import_finalized_id = $import_widget_id = $matches[2];
					$import_widget_data = $layout['widgets'][ $widget_option_name ][ $import_widget_id ];

					// if the ID of the widget we're trying to import ALREADY exists in this site's
					// widget options, we need to change the widget id of the widget we're importing
					if ( isset( $options[ $widget_option_name ][ $import_widget_id ] ) )
					{
						$exists = TRUE;
						$import_finalized_id = 0;

						// search through the current site's widget options for the first available
						// widget config slot
						while ( $exists )
						{
							// we increment first, because widgets never have an ID of 0
							$import_finalized_id++;

							$exists = isset( $options[ $widget_option_name ][ $import_finalized_id ] );
						}//end while
					}//end if

					if ( ! isset( $options[ $widget_option_name ] ) )
					{
						$options[ $widget_option_name ] = array();

						if ( isset( $layout['widgets'][ $widget_option_name ]['_multiwidget'] ) )
						{
							$options[ $widget_option_name ]['_multiwidget'] = $layout['widgets'][ $widget_option_name ]['_multiwidget'];
						}//end if
					}//end if

					// change the slug of the widget in the sidebar
					$import_sidebar[ $import_widget_key ] = "{$matches[1]}-$import_finalized_id";

					// add the widget data to the options array
					$options[ $widget_option_name ][ $import_finalized_id ] = $import_widget_data;
				}//end foreach

				$options[ 'sidebars_widgets' ][ $import_sidebar_key ] = $import_sidebar;
			}//end foreach

			foreach ( $options as $name => $widget_data )
			{
				if ( 'widget_' !== substr( $name, 0, 7 ) )
				{
					continue;
				}//end if

				echo '<li>Adding ' . esc_html( $name ) . '...</li>';
				update_option( $name, $options[ $name ] ?: array() );
			}//end foreach

			update_option( 'sidebars_widgets', $options['sidebars_widgets'] ?: array() );
		}//end if
		else
		{
			// if we get in here, there's no widget areas in the local settings.  Overwrite all local
			// settings.
			update_option( 'sidebars_widgets', $import_sidebars ?: array() );

			foreach( $layout['widgets'] as $name => $value )
			{
				echo '<li>Adding ' . esc_html( $name ) . '...</li>';
				$options[ $name ] = unserialize( $value );
				update_option( $name, $options[ $name ] ?: array() );
			}//end foreach
		}//end else

		return $options;
	}//end add_sidebars
}//end class

function go_copylayout()
{
	global $go_copylayout;

	if ( ! $go_copylayout )
	{
		$go_copylayout = new GO_CopyLayout;
	}//end if

	return $go_copylayout;
}//end go_copylayout
