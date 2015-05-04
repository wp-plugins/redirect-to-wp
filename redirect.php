<?php
/*
Plugin Name: Redirect To WP
Description: Add a meta box to all posts, pages, custom post types to add url you want to redirect from (old url) to the new post. You can use redirection plugin here: <a href="https://wordpress.org/plugins/redirection/">Redirection Plugin.</a> The other option is the website htaccess file.
Author: Yehuda Hassine
Author URI: http://wpdevops.co.il
Version: 1.0
License: GPL2
*/

/*

    Copyright (C) 2015  Yehuda Hassine

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$redirect_to_wp = new redirect_to_wp;


class redirect_to_wp {
	function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box') );
		add_action( 'save_post', array( $this, 'save_post') );
	}

	function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' ); 

		foreach ($post_types as $key => $screen ) {
			add_meta_box( 'source_redirect', 'Source Redirect', array( $this, 'view_meta_box' ), $screen );
		}
	}

	function view_meta_box( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'sr_meta_box', 'sr_meta_box_nonce' );

		/*
		 * Use get_post_meta() to retrieve an existing value
		 * from the database and use the value for the form.
		 */
		$value = get_post_meta( $post->ID, 'source_redirect_url', true );
		$redirect = get_post_meta( $post->ID, 'source_redirect_method', true );
		switch ( $redirect ) {
			case 1:
				$method = 'Redirection Plugin';
				break;
			case 2:
				$method = 'Htaccess File.';
				break;			
		}

		?>
		<select name="do_redirect">
			<option value="0">Choose redirect method</option>
			<?php
			if ( defined( "REDIRECTION_FILE" ) )
				echo '<option value="1">Redirection Plugin</option>';
			?>
			<option value="2">Htaccess File</option>
		</select>
		<br><br>
		<?php
		echo '<label for="source_redirect">';
		echo 'Insert here the old page url';
		echo '</label> ';
		echo '<input type="text" id="source_redirect" name="source_redirect" value="' . esc_attr( $value ) . '" size="25" /><br>';
		echo ( $method ) ? '<span><b>Redirect Method: ' . $method . '</b></span>' : '<span><b>No Redirect.</b></span>';
	}


	function save_post( $post_id ) {
	
		if ( wp_is_post_revision( $post_id ) )
			return;		

		if ( ! isset( $_POST['sr_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['sr_meta_box_nonce'], 'sr_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}	

		if ( !isset( $_POST['do_redirect'] ) || $_POST['do_redirect'] == 0 ) {
			return;
		}	

		$wp_url = get_permalink( $post_id );
		$source = $_POST['source_redirect'];  
		$method = absint( $_POST['do_redirect'] );

		switch ( $method ) {
			case '1':
				$this->update_redirection( $source, $wp_url );
				break;
			case '2':
				$this->update_htaccess( $source, $wp_url );
				break;			

		}

		update_post_meta( $post_id, 'source_redirect_url', esc_url( $source ) );
		update_post_meta( $post_id, 'source_redirect_method', $method );

	}


	function update_redirection( $source, $wp_url ) {
		Red_Item::create( array(
			'source' => $this->extrect_host( $source ),
			'target' => $this->extrect_host( $wp_url ),
			'regex'  => false,
			'group'  => 1,
			'match'  => 'url',
			'red_action' => 'url'
		) );
	}


	function update_htaccess( $source, $wp_url ) {
		file_put_contents( ABSPATH . '/.htaccess', 'Redirect 301 ' . $this->extrect_host( $source ) . 
			' ' . $this->extrect_host( $wp_url ) . PHP_EOL, FILE_APPEND );		
	}


	function extrect_host( $url ) {

		$url_parse = parse_url( $url );

		$url = str_replace( $url_parse['scheme'] . '://' . $url_parse['host'], "", $url );

		return $url;
	}
}
