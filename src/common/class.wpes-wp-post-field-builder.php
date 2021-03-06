<?php

class WPES_WP_Post_Field_Builder extends WPES_Abstract_Field_Builder {

	var $index_media = false;
	var $index_internal_links = false;

	public function get_mappings( $args = array() ) {
		$defaults = array(
			'all_field_enabled' => false,
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$mappings = new WPES_WP_Mappings();

		$dynamic_post_templates = array(
			array(
				"tax_template_name" => array(
					"path_match" => "taxonomy.*.name",
					"mapping" => $mappings->text_lcase_raw( 'name' ),
			) ),
			array(
				"tax_template_slug" => array(
					"path_match" => "taxonomy.*.slug",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"tax_template_term_id" => array(
					"path_match" => "taxonomy.*.term_id",
					"mapping" => $mappings->primitive( 'long' ),
			) ),
			array(
				"has_template" => array(
					"path_match" => "has.*",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
			array(
				"shortcode_args_template" => array(
					"path_match" => "shortcode.*.id",
					"mapping" => $mappings->keyword(),
			) ),
			array(
				"shortcode_count_template" => array(
					"path_match" => "shortcode.*.count",
					"mapping" => $mappings->primitive( 'short' ),
			) ),
		);

		if ( $args['index_meta'] ) {
			$dynamic_post_templates[] = array(
				"meta_str_template" => array(
					"path_match" => "meta.*.value",
					"mapping" => $mappings->text_lcase_raw( 'value' ),
			) );
			$dynamic_post_templates[] = array(
				"meta_long_template" => array(
					"path_match" => "meta.*.long",
					"mapping" => $mappings->primitive( 'long' ),
			) );
			$dynamic_post_templates[] = array(
				"meta_bool_template" => array(
					"path_match" => "meta.*.boolean",
					"mapping" => $mappings->primitive( 'boolean' ),
			) );
			$dynamic_post_templates[] = array(
				"meta_float_template" => array(
					"path_match" => "meta.*.double",
					"mapping" => $mappings->primitive( 'double' ),
			) );
		}

		//same mapping for both pages, posts, all custom post types
		$post_mapping = array(
			'dynamic_templates' => $dynamic_post_templates,
			'_all' => array( 'enabled' => $args['all_field_enabled'] ),
			'_analyzer' => array( 'path' => 'lang_analyzer' ),
			'properties' => array(

				//////////////////////////////////
				//Blog/Post meta fields

				'post_id'               => $mappings->primitive_stored( 'long' ),
				'blog_id'               => $mappings->primitive_stored( 'integer' ),
				'site_id'               => $mappings->primitive( 'short' ),
				'post_type'             => $mappings->keyword(),
				'post_format'           => $mappings->keyword(),
				'post_status'           => $mappings->keyword(),
				'public'                => $mappings->primitive( 'boolean' ),
				'has_password'          => $mappings->primitive( 'boolean' ),

				'parent_post_id'        => $mappings->primitive( 'long' ),
				'ancestor_post_ids'     => $mappings->primitive( 'long' ),

				'menu_order'            => $mappings->primitive( 'integer' ),

				'lang'                  => $mappings->keyword(),
				'lang_analyzer'         => $mappings->keyword(),

				'url'                   => $mappings->text_raw( 'url' ),
				'slug'                  => $mappings->keyword(),

				'date'                  => $mappings->datetime(),
				'date_token'            => $mappings->datetimetoken(),
				'date_gmt'              => $mappings->datetime_stored(),
				'date_gmt_token'        => $mappings->datetimetoken(),
				'modified'              => $mappings->datetime(),
				'modified_token'        => $mappings->datetimetoken(),
				'modified_gmt'          => $mappings->datetime(),
				'modified_gmt_token'    => $mappings->datetimetoken(),

				'sticky'                => $mappings->primitive( 'boolean' ),

				//////////////////////////////////
				//Post Content fields

				'author'                => $mappings->text_raw( 'author' ),
				'author_login'          => $mappings->keyword(),
				'author_id'             => $mappings->primitive( 'integer' ),
				'title'                 => $mappings->text_count( 'title' ),
				'content'               => $mappings->text_count( 'content' ),
				'excerpt'               => $mappings->text_count( 'excerpt' ),
				'tag_cat_count'         => $mappings->primitive( 'short' ),
				'tag'                   => $mappings->tagcat( 'tag' ),
				'category'              => $mappings->tagcat( 'category' ),

				//taxonomy.*.* added as dynamic template

				//////////////////////////////////
				//Embedded Media/Shortcodes/etc

				//has.* added as dynamic template

				'link' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->text_raw( 'url' ),
						'host'          => $mappings->keyword(),
						'host_reversed' => $mappings->keyword(),
					),
				),
				'image' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->keyword(),
					),
				),
				'shortcode_types'       => $mappings->keyword(),
				'embed' => array(
					'type' => 'object',
					'properties' => array(
						'url'           => $mappings->keyword(),
					),
				),
				'hashtag' => array(
					'type' => 'object',
					'properties' => array(
						'name'          => $mappings->keyword(),
					),
				),
				'mention' => array(
					'type' => 'object',
					'properties' => array(
						'name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'name'  => $mappings->keyword(),
								'lc'    => $mappings->keyword_lcase(),
							),
						),
					),
				),

				//////////////////////////////////
				//Comments

				'commenter_ids'         => $mappings->primitive( 'integer' ),
				'comment_count'         => $mappings->primitive( 'integer' ),

			)
		);

		if ( $this->index_media ) {
			//Indexing media attachments also
			// Add additional fields here
		}

		return $post_mapping;
	}

	public function get_all_fields( $args = array() ) {
		$defaults = array(
			'index_meta' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		switch_to_blog( $args['blog_id'] );

		$post = get_post( $args['post_id'] );
		if ( !$post ) {
			restore_current_blog();
			return false;
		}

		$blog = get_blog_details( $args['blog_id'] );

		$data = array(
			'blog_id'      => $this->clean_int( $args['blog_id'], 'blog_id' ),
			'site_id'      => $this->clean_short( $blog->site_id, 'site_id' ),
		);
		$lang_data = $this->post_lang( $args['blog_id'] );
		$post_data = $this->post_fields( $post, $lang_data['lang'] );
		$tax_data = $this->taxonomy( $post );
		$commenters_data = $this->commenters( $args['blog_id'], $post );
		$feat_img_data = $this->featured_image( $post );
		$media_data = $this->extract_media( $args['blog_id'], $post );
		if ( $args['index_meta'] ) {
			$meta_data = $this->meta( $post );
		} else {
			$meta_data = array();
		}

		$data = array_merge(
			$data,
			$lang_data,
			$post_data,
			$tax_data,
			$commenters_data,
			$media_data,
			$feat_img_data,
			$meta_data
		);
		restore_current_blog();
		return $data;
	}

	public function get_update_script( $args ) {
		global $wpdb;
		$update_script = array();

		if ( count( $args['updates'] ) != 1 )
			return new WP_Error( 'es-doc-callbacks', 'Don\'t currently support multiple updates in one op' );

		foreach ( $args['updates'] as $op => $update_args ) {
			switch ( $op ) {
				case 'add_comment' :
					$user_id = $update_args;
					$post = get_blog_post( $args['blog_id'], $args['id'] );
					if ( !$post )
						return array();
					if ( $user_id ) {
						$update_script['script'] = 'if ( !ctx._source.commenter_ids.contains(commenter) ) { ctx._source.commenter_ids += commenter; }; ctx._source.comment_count = count;';
						$update_script['params'] = array( "commenter" => $update_args, 'count' => $post->comment_count );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = count;';
						$update_script['params'] = array( 'count' => $post->comment_count );
					}

					$date = false;
					if ( $args['add_last_comment_date_gmt'] ) {
						$date = $this->most_recent_comment_date( $args['blog_id'], $args['id'] );
					}
					if ( $date ) {
						$update_script['script'] .= ' ctx._source.last_comment_date_gmt = date;';
						$update_script['params']['date'] = $this->clean_date( $date );
					}
					break;
				case 'remove_comment' :
					$remove = false;
					$user_id = $update_args;
					$blog_details = get_blog_details( $args['blog_id'] );
					$post = get_blog_post( $args['blog_id'], $args['id'] );
					if ( !$post )
						return array();
					if ( $user_id ) { //user_id 0 is never in the list
						//check whether this commenter has any approved comments left on the post
						$comment_id = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_ID FROM wp_%d_comments WHERE comment_post_ID = %d AND user_id = %d AND comment_approved = "1" LIMIT 1', $args['blog_id'], $args['id'], $user_id ) );
						if ( !$comment_id )
							$remove = true;
					}

					if ( $remove ) {
						$update_script['script'] = 'if ( ctx._source.commenter_ids.contains(commenter) ) { idx = ctx._source.commenter_ids.indexOf(commenter); ctx._source.commenter_ids.remove(idx); }; ctx._source.comment_count = count;';
						$update_script['params'] = array( "commenter" => $update_args, 'count' => $post->comment_count );
					} else {
						$update_script['script'] = 'ctx._source.comment_count = count;';
						$update_script['params'] = array( 'count' => $post->comment_count );
					}

					if ( $args['add_last_comment_date_gmt'] ) {
						$date = $this->most_recent_comment_date( $args['blog_id'], $args['id'] );
						$update_script['script'] .= ' ctx._source.last_comment_date_gmt = date;';
						$update_script['params']['date'] = $this->clean_date( $date );
					}
					break;
			}
		}
		return $update_script;
	}

	function is_post_public( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );

		$post = get_post( $post_id );
		if ( ! $post ) {
			restore_current_blog();
			return false;
		}

		$post_status = get_post_status( $post_id );
		$public_stati = get_post_stati( array( 'public' => true ) );

		if ( ! in_array( $post_status, $public_stati ) ) {
			restore_current_blog();
			return false;
		}

		if ( strlen( $post->post_password ) > 0 ) {
			restore_current_blog();
			return false;
		}

		$post_ok = true;
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( $post_type_obj->exclude_from_search ) {
			$post_ok = false;
		}

		if ( ! $post_type_obj->public ) {
			$post_ok = false;
		}

		restore_current_blog();
		return $post_ok;
	}

	function is_parent_post_public( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$post = get_post( $post_id );
		//if there is no parent (eg unattached media) we consider it public
		if ( 0 == $post->post_parent )
			return true;

		restore_current_blog();
		return $this->is_post_public( $blog_id, $post->post_parent );
	}

	function is_post_indexable( $blog_id, $post_id ) {
		if ( $post_id == false )
			return false;

		if ( !$this->index_media ) {
			//filter out attachments
			$post = get_blog_post( $blog_id, $post_id );
			if ( 'attachment' == $post->post_type )
				return false;
		}

		return $this->is_post_public( $blog_id, $post_id );
	}

	function post_fields( $post, $lang, $index_mlt_content = false, $index_custom_taxonomy = true, $multi_lang_support = false ) {
		$blog_id = get_current_blog_id();
		$user = get_userdata( $post->post_author );

		$post_title = $this->remove_shortcodes( $this->clean_string( $post->post_title ) );
		$post_content = $this->remove_shortcodes( $this->clean_string( $post->post_content ) );
		$post_excerpt = $this->remove_shortcodes( $this->clean_string( $post->post_excerpt ) );

		$format = get_post_format( $post->ID );
		if ( false === $format ) {
			$format = 'standard';
		}

		if ( 'attachment' == $post->post_type ) {
			$url = wp_get_attachment_url( $post->ID );
			if ( false == $url )
				$url = get_permalink( $post->ID );
		} else {
			$url = get_permalink( $post->ID );
		}

		$data = array(
			'post_id'      => $this->clean_long( $post->ID, 'post_id' ),
			'post_type'    => $post->post_type,
			'post_format'  => $format,
			'post_status'  => get_post_status( $post->ID ),
			'parent_post_id' => $post->post_parent,
			'ancestor_post_ids' => get_post_ancestors( $post->ID ),
			'public'       => (boolean) $this->is_post_public( $blog_id, $post->ID ),
			'has_password' => ( strlen( $post->post_password ) > 0 ),
			'url'          => $this->remove_url_scheme( $url ),
			'slug'         => $post->post_name,
			'date'          => $this->clean_date( $post->post_date ),
			'date_gmt'      => $this->clean_date( $post->post_date_gmt ),
			'date_token'     => $this->date_object( $post->post_date ),
			'date_gmt_token' => $this->date_object( $post->post_date_gmt ),
			'modified'          => $this->clean_date( $post->post_modified ),
			'modified_gmt'      => $this->clean_date( $post->post_modified_gmt ),
			'modified_token'     => $this->date_object( $post->post_modified ),
			'modified_gmt_token' => $this->date_object( $post->post_modified_gmt ),
			'sticky'       => (boolean) is_sticky( $post->ID ),
			'title'        => $post_title,
			'content'      => $post_content,
			'excerpt'      => $post_excerpt,
			'menu_order'   => $this->clean_int( $post->menu_order, 'menu_order' ),
		);

		$author_data = $this->post_author( $blog_id, $post );
		$data = array_merge( $data, $author_data );

		return $data;
	}

	public function post_lang( $blog_id, $post = null, $is_es2 = false ) {
		$fld_bldr = new WPES_WP_Blog_Field_Builder();
		return $fld_bldr->blog_lang( $blog_id );
	}

	public function post_author( $blog_id, $post ) {
		$post_user = get_userdata( $post->post_author );
		$data['author'] = $this->clean_string( $post_user->display_name );
		$data['author_login'] = $this->clean_string( $post_user->user_login );
		$data['author_id'] = $this->clean_int( $post_user->ID, 'author_id' );
		return $data;
	}

	public function taxonomy( $post ) {
		global $wpdb;
		$data = array();

		//get all terms associated with post and store as appropriate taxonomy
		//by Higgs this is ugly
		$query = $wpdb->prepare( "SELECT tt.taxonomy, t.name, t.slug, t.term_id FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = %d ", $post->ID );
		$terms = $wpdb->get_results( $query );

		$tax_list = array();
		foreach ( $terms as $term ) {
			$tax = $this->clean_string( $term->taxonomy );
			if ( ! isset( $tax_list[$tax] ) )
				$tax_list[$tax] = array();

			$tax_list[$tax][] = array(
				'name' => $this->clean_string( $term->name, 1024 ), // Limit at 1KB -- overkill varchar at 200
				'slug' => $this->clean_string( $term->slug, 1024 ), //clean in case of non utf-8
				'term_id' => $term->term_id
			);
		}

		if ( isset( $tax_list['post_tag'] ) ) {
			$data['tag'] = $tax_list['post_tag'];
			unset( $tax_list['post_tag'] );
		}

		if ( isset( $tax_list['category'] ) ) {
			$data['category'] = $tax_list['category'];
			unset( $tax_list['category'] );
		}

		$data['tag_cat_count'] = $this->clean_short( ( count( $data['tag'] ) + count( $data['category'] ) ), 'tag_cat_count' );

		if ( ! empty( $tax_list ) ) {
			$data['taxonomy'] = array();
			foreach ( $tax_list as $name => $term_list ) {
				$data['taxonomy'][$name] = $term_list;
			}
		}
		return $data;
	}

	public function guesstimate_meta_count( $blog_id ) {
		global $wpdb;
		
		//$metakey_cnt = wp_cache_get( 'es_metakey_count' );
		if ( false == $metakey_cnt ) {
			$num_samples = 100;
			$sampled_meta = array();
			$deets = get_blog_details( $blog_id );
			$num_posts = $deets->post_count;
			if ( $num_posts < ( 10 * $num_samples ) ) {
				return $this->exact_count_meta( $blog_id );
			}
			$max_post_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );

			for( $i = 0; $i < $num_samples * 1.1; $i++ ) {
				$post_id = mt_rand( 1, $max_post_id ); 
				$post_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID >= %d LIMIT 1",
					$post_id
				) );
				$sampled_meta[] = get_post_meta( $post_id );
			}

			$keys = array();
			for( $i = 0; $i < $num_samples; $i++ ) {
				foreach( $sampled_meta[$i] as $key => $val ) {
					if ( ! is_protected_meta( $key ) ) {
						$keys[ $key ] = true;
					}
				}
			}
			
			$new_keys = 0;
			for( $i = $num_samples; $i < $num_samples * 1.1; $i++ ) {
				foreach( $sampled_meta[$i] as $key => $val ) {
					if ( ! is_protected_meta( $key ) ) {
						if ( ! isset( $keys[ $key ] ) ) {
							$new_keys++;
						}
					}
				}
			}

			$metakey_cnt = count( $keys );
			$metakey_cnt += ( $num_posts - $num_samples ) / ( $num_posts * 0.1 ) * $new_keys;
			$metakey_cnt = intval( $metakey_cnt );
			
			//wp_cache_set( 'es_metakey_count', $metakey_cnt );
		}
		return $metakey_cnt;
	}

	public function exact_count_meta( $blog_id ) {
		global $wpdb;
		
		//$metakey_cnt = wp_cache_get( 'es_metakey_count' );
		if ( false == $metakey_cnt ) {
			$metakey_cnt = $wpdb->get_var( "SELECT COUNT(DISTINCT(meta_key)) FROM $wpdb->postmeta WHERE meta_key NOT LIKE '\_%'" );
			wp_cache_set( 'es_metakey_count', $metakey_cnt );
		}
		return (int) $metakey_cnt;
	}
	
	public function meta( $post, $blacklist = array(), $whitelist = array() ) {
		global $wpdb;
		global $blog_id;
		$data = array();
		if ( 568005 == $blog_id ) {
			return $data;
		}
		$meta = get_post_meta( $post->ID );
		if ( !empty( $meta ) ) {
			$data['meta'] = array();

			$metakey_cnt = $this->exact_count_meta( $blog_id );
			if ( $metakey_cnt > 10000 )
				return $data;

			foreach ( $meta as $key => $v ) {
				if ( in_array( $key, $blacklist ) )
					continue;
				if ( in_array( $key, $whitelist ) || !is_protected_meta( $key ) ) {
					$unserialized = maybe_unserialize( $v ); //try one more unserialize op
					if ( $this->is_assoc_array( $unserialized ) )
						continue;

					if ( is_object( $unserialized ) )
						continue;

					if ( is_array( $unserialized ) && $this->is_multi_dim_array( $unserialized ) )
						continue;

					$clean_key = $this->clean_object( $key );
					if ( !is_array( $unserialized ) ) {
						$unserialized = array( $unserialized );
					}

					$data['meta'][$clean_key] = array(
						'value' => array(),
						'long' => array(),
						'double' => array(),
						'boolean' => array(),
					);
					foreach ( $unserialized as $val ) {
						$data['meta'][$clean_key]['value'][] = $this->clean_string( (string) $val, 4096 ); // limit at 4KB
						$data['meta'][$clean_key]['long'][] = $this->clean_long( (int) $val, 'meta.' . $clean_key . '.long' );
						$data['meta'][$clean_key]['double'][] = $this->clean_float( $val );
						if ( ( "false" === $val ) || ( "FALSE" === $val ) ) {
							$bool = false;
						} elseif ( ( 'true' === $val ) || ( 'TRUE' === $val ) ) {
							$bool = true;
						} else {
							$bool = (boolean) $val;
						}
						$data['meta'][$clean_key]['boolean'][] = $bool;
					}
				}
			}
		}
		return $data;
	}

	public function commenters( $blog_id, $post ) {
		global $wpdb;
		$data = array();

		$blog_details = get_blog_details( $blog_id );

		//post->comment_count is not always correct for JP
		$comment_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = 1", $post->ID ) );
		if ( 1 == $blog_details->site_id ) { //wp.com
			$query = $wpdb->prepare( "SELECT DISTINCT user_id FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = 1 AND user_id != 0", $post->ID );
			$commenter_ids = $wpdb->get_col( $query );
		} else {
			$commenter_ids = array();

			//If there are tons of comments, they are probably all anon anyways, so don't try and load them all
			//will run out of memory otherwise
			if ( $comment_count < 5000 ) {
				//jetpack comments from highlander have wpcom id stored in comment meta
				// if they aren't using highlander then the user ids don't mean anything since they are specific to the blog
				// unfortunately no cleaner way to do this query
				$query = $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = 1", $post->ID );
				$comment_ids = $wpdb->get_col( $query );
				if ( ! empty( $comment_ids ) ) {
					foreach ( $comment_ids as $idx => $comment_id ) {
						// Clear cache every 1000 calls for posts with thousands of commenters
						if ( 0 === ( $idx % 1000 ) )
							stop_the_insanity();
	
						$commenter_id = get_comment_meta( $comment_id, '_jetpack_wpcom_user_id', true );
						if ( $commenter_id )
							$commenter_ids[$commenter_id] = true;
					}
					$commenter_ids = array_keys( $commenter_ids );
				}
			}
		}

		$data['commenter_ids'] = array();
		foreach ( $commenter_ids as $commenter_id ) {
			if ( $commenter_id ) //get rid of user id 0
				$data['commenter_ids'][] = $this->clean_int( $commenter_id, 'commenter_ids' );
		}
		if ( $comment_count > 0 )
			$data['comment_count'] = $this->clean_int( $comment_count, 'comment_count' );
		else
			$data['comment_count'] = 0; //sometimes comment_count is negative!

		return $data;
	}

	public function most_recent_comment_date( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );
		$comments = get_comments( array(
			'post_id' => $post_id,
			'number'  => 1,
			'status'  => 'approve',
			'order'   => 'DESC',
			'orderby' => 'comment_date_gmt',
			'type'    => 'comments',
		) );

		if ( empty( $comments ) )
			$date = false;
		else
			$date = $comments[0]->comment_date_gmt;
		
		restore_current_blog();
		return $date;
	}
	
	public function extract_media( $blog_id, $post ) {
		$GLOBALS['post'] = $post; //We need to set the global $post var to be a valid post so that inside gallery_shortcode it will be found for galleries that do not pass in an include. If not it will do a get_children() call without a post parent ID, returning ALL of the attachments and loading them in memory. See https://elasticsearchp2.wordpress.com/2016/01/15/vip-es-indexing-is-failing/ and https://a8c.slack.com/archives/vip/p1452891275024235
		require_lib('class.wpcom-media-meta-extractor');
		$data = Jetpack_Media_Meta_Extractor::extract( $blog_id, $post->ID, Jetpack_Media_Meta_Extractor::ALL );

		//only allow those top level fields that we expect to prevent accidentally creating new mappings
		$whitelist = array( 'has', 'link', 'image', 'shortcode', 'mention', 'hashtag', 'embed', 'shortcode_types' );
		foreach ( $data as $field => $d ) {
			if ( ! in_array( $field, $whitelist ) )
				unset( $data[$field] );
		}

		//clean longs, ints, shorts
		if ( isset( $data['has'] ) ) {
			foreach ( $data['has'] as $key => $cnt) {
				$data['has'][$key] = $this->clean_short( $cnt, 'has.' . $key );
			}
		}
		if ( isset( $data['shortcode'] ) ) {
			foreach ( $data['shortcode'] as $code => $obj) {
				$data['shortcode'][$code]['count'] = $this->clean_short( $obj['count'], 'shortcode.' . $code . '.count' );
			}
		}
		//clean urls (we don't want the scheme so we can do prefix matching)
		if ( isset( $data['image'] ) ) {
			foreach ( $data['image'] as $idx => $obj) {
				$data['image'][$idx]['url'] = $this->remove_url_scheme( $this->clean_string( $obj['url'] ) );
			}
		}
		//clean links to make sure they don't break json_encode with non-utf8 chars
		if ( isset( $data['link'] ) ) {
			$internal_links = array();

			foreach ( $data['link'] as $idx => $obj) {
				$data['link'][$idx]['url'] = $this->clean_string( $obj['url'] );
				$data['link'][$idx]['host'] = $this->clean_string( $obj['host'] );
				$data['link'][$idx]['host_reversed'] = $this->clean_string( $obj['host_reversed'] );

				$url = $obj['url'];
				if ( $this->_index_internal_links( $blog_id, $url ) ) {

					$internal_link = $this->internal_link( $url );
					if ( $internal_link ) {
						$internal_links[] = $internal_link;
					}
				}
			}

			$internal_links_count = count( $internal_links );
			if ( $internal_links_count > 0 ) {
				$data['link_internal'] = $internal_links;
				$data['has']['link_internal'] = $this->clean_short( $internal_links_count, 'has.link_internal' );
			}
		}

		return $data;
	}

	private function _index_internal_links( $blog_id, $url ) {
		if ( !$this->index_internal_links ) {
			return false;
		}

		/**
		 *  Currently only Capital One is using this system of searching
		 *  internal links. To index this field is very expensive and causing
		 *  replication lag so we are limiting it to only clients that need it
		 *  for now.
		 *
		 *  See:
		 *  https://a8c.slack.com/archives/C02JQ08G0/p1540398953000100
		 *  https://elasticsearchp2.wordpress.com/2017/09/26/indexing-inline-attachments/
		 */
		$whitelist = array(
			123129067,
			129577076,
			129497226,
			137234973,
		);
		if ( !in_array( $blog_id, $whitelist ) ) {
			return false;
		}

		if ( !$this->is_internal_link( $blog_id, $url ) ) {
			return false;
		}

		return true;
	}

	public function featured_image( $post ) {
		$data = array();
		if ( has_post_thumbnail( $post->ID ) ) {
			$struct = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail' );
			if ( isset( $struct[0] ) )
				$data['featured_image'] = $this->remove_url_scheme( $struct[0] );
		}

		return $data;
	}

	public function attached_files( $blog_id, $post ) {
		//keep an in memory cache of encoded files to try and reduce
		// network I/O
		static $url2data = array();
		
		$attachment_post_ids = array();

		if ( 'attachment' == $post->post_type ) {
			$attachment_post_ids[] = $post->ID;
		} else {
			switch_to_blog( $args['blog_id'] );
			$children = get_children( array(
				'post_parent' => $post->ID,
				'post_type' => 'attachment',
				'numberposts' => -1,
				'post_status' => 'any',
			) );
			restore_current_blog();
			$attachment_post_ids = array_keys( $children );
		}

		if ( empty( $attachment_post_ids ) )
			return;

		////////////////////////////////////////////
		// Impose some limits on what we index

		$max_file_size = 1000000; //1MB

		//TODO: change this to a blacklist
		// For images and movies for instance we don't need to fetch the file
		// to get this data since it is in the post meta for the attachment post
		$allowed_file_types = array(
			'application/pdf',
			'application/vnd.ms-powerpoint',
			'application/msword',
		);
		
		//Build list of allowed domains with the domains reversed
		// Any of the site's domains, or a subdomain are allowed
		$deets = get_blog_details( $blog_id );
		$allowed_domains = array( $this->reverse_host( 'wordpress.com' ) );
		$parsed = parse_url( $deets->siteurl );
		$allowed_domains[] = $this->reverse_host( $parsed['host'] );
		$parsed = parse_url( $deets->home );
		$allowed_domains[] = $this->reverse_host( $parsed['host'] );
		$allowed_domains = array_values( array_unique( $allowed_domains ) );

		$data = array();
		$file_urls = array();
		foreach( $attachment_post_ids as $id ) {
			$att = get_blog_post( $blog_id, $id );
			$file_url = $att->guid;

			if ( empty( $file_url ) )
				continue;

			if ( ! in_array( $att->post_mime_type, $allowed_file_types ) )
				continue; //skip
			$file_urls[] = $file_url;
		}

		$file_urls = array_values( array_unique( $file_urls ) );
		foreach( $file_urls as $file_url ) {
			//check that the url is on an allowed domain
			$parsed = parse_url( $file_url );
			$file_host = $this->reverse_host( $parsed['host'] );
			$ok = false;
			foreach( $allowed_domains as $allowed ) {
				if ( substr( $file_host, 0, strlen( $allowed ) ) === $allowed ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok )
				continue;

			if ( isset( $url2data[$file_url] ) ) {
				//we already have the data for this url in memory so don't fetch again
				$data[] = $url2data[$file_url];
				continue;
			}
			
			//TODO: could make these much more efficient by using
			// curl multi to make these non-blocking
			$meta = $this->retrieve_remote_file_meta( $file_url );
			if ( false === $size )
				continue;
			if ( $meta['size'] > $max_file_size )
				continue; //skip

			$contents = $this->retrieve_remote_file( $file_url );
			if ( false === $contents )
				continue;


			$url2data[$file_url] = array(
				'_name' => $this->remove_url_scheme( $this->clean_string( $file_url ) ),
				'_content' => base64_encode( $contents ),
			);
			$data[] = $url2data[$file_url];
		}

		//free up memory periodically
		if ( count( $url2data ) > 20 )
			$url2data = array();
		
		return $data;
	}

	protected function internal_link( $url ) {
		$url = esc_url_raw( $url );

		$parsed_url = parse_url( $url );

		// Check if it's a media link
		if ( isset( $parsed_url['path'] ) ) {
			$extension = pathinfo( $parsed_url['path'], PATHINFO_EXTENSION );
			if ( $extension ) {
				$attachment_id = attachment_url_to_postid( $url );
				if ( $attachment_id ) {
					return array(
						'post_id' => $this->clean_long( $attachment_id, 'post_id' ),
						'post_type' => 'attachment',
					);
				}
			}
		}

		// Check if it's a comment link
		if ( isset( $parsed_url['fragment'] )
			&& 0 === strpos( $parsed_url['fragment'], 'comment-' ) ) {
			if ( preg_match( '/^comment-(\d+)/', $parsed_url['fragment'], $matches ) ) {
				$comment_id = absint( $matches[1] );
				$comment = get_comment( $comment_id );
				if ( $comment ) {
					return array(
						'comment_id' => $this->clean_long( $comment_id, 'comment_id' ),
						'post_id' => $this->clean_long( $comment->comment_post_ID, 'post_id' ),
						'post_type' => sanitize_key( get_post_type( $post_id ) ),
					);
				}
			}
		}

		// Fallback to permalinks
		$post_id = url_to_postid( $url );
		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return array(
			'post_id' => $this->clean_long( $post_id, 'post_id' ),
			'post_type' => $this->clean_string( $post->post_type ),
		);
	}

	protected function is_internal_link( $_blog_id, $url ) {
		// For WP.com, we need to handle `example.files.wordpress.com` separately
		$url_host = parse_url( $url, PHP_URL_HOST );
		if ( false !== strpos( $url_host, '.files.wordpress.com' ) ) {
			// Also handle special sub-subdomain blogs like lobby.vip.wordpress.com and en.blog.wordpress.com
			// Their urls may be in the form: `lobby-vip.wordpress.com`
			$normalized_url_host = str_replace( '-', '.', $url_host );
			$files_host = wpcom_get_blog_files_host();
			return $url_host === $files_host || $normalized_url_host === $files_host;
		}

		$_blog_details = get_blog_details( $_blog_id );

		$normalized_url = $this->normalize_url_for_compare( $url, $url );
		$normalized_siteurl = $this->normalize_url_for_compare( $_blog_details->siteurl, $url );
		$normalized_home = $this->normalize_url_for_compare( $_blog_details->home, $url );

		$internal_domains = array_unique( [
			$normalized_siteurl,
			$normalized_home,
		] );

		$is_internal = false;
		foreach ( $internal_domains as $internal_domain ) {
			if ( 0 === strpos( $normalized_url, $internal_domain ) ) {
				$is_internal = true;
				break;
			}
		}

		return $is_internal;
	}

	protected function normalize_url_for_compare( $to_normalize_url, $baseline_url ) {
		// TODO: need to handle absolute and relative URLs
		$normalized_url = esc_url_raw( $to_normalize_url );
		$normalized_url = preg_replace( '/^[a-zA-z]+:\/\//', '://', $normalized_url );

		// Technique borrowed from url_to_postid.
		// Add 'www.' if it is absent and should be there.
		if ( false !== strpos( $baseline_url, '://www.' )
			&& false === strpos( $normalized_url, '://www.' ) ) {
			$normalized_url = str_replace( '://', '://www.', $normalized_url );
		} elseif ( false === strpos( $baseline_url, '://www.' ) ) {
			// Strip 'www.' if it is present and shouldn't be.
			$normalized_url = str_replace( '://www.', '://', $normalized_url );
		}

		return $normalized_url;
	}
}

