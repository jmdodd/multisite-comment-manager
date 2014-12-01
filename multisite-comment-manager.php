<?php
/*
Plugin Name: Multisite Comment Manager
Description: Provide multisite admins with a quick overview of recent comments on all sites.
Version: 0.2
Author: Jennifer M. Dodd
Author URI: http://bajada.net
*/

/*
	Copyright 2011-2014 Jennifer M. Dodd (email: jmdodd@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class UCC_Multisite_Comments_Manager {

	public static $instance;

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new UCC_Multisite_Comments_Manager();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_view() {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			// Not logged in.
		} else {
			// Logged in.
			$defined_blogs = array( 6, 4, 1 );

			$blogs = get_blogs_of_user( $current_user->ID );

			$user_blogs = array();
			foreach ( $blogs as $blog ) {
				$user_blogs[] = $blog->userblog_id ;
			}

			foreach( $defined_blogs as $defined_blog ) {
				if ( in_array( $defined_blog, $user_blogs ) ) {
					$blog = $blogs[$defined_blog];
					switch_to_blog( $defined_blog );

					$number = 7;
					switch( $defined_blog ) {
						case 6:
							$number = 10;
							break;
						case 4:
						case 2:
							$number = 10;
							break;
						case 1:
							$number = 5;
							break;
					}

					if ( current_user_can( 'moderate_comments' ) ) {
						$comments = get_comments( 'number=' . $number );
						?>
						<div class="wrap">
						<div id="icon-edit-comments" class="icon32"><br /></div><h2>Comments on <?php echo $blog->blogname; ?></h2>

						<?php
						if ( $comments ) {
							// Formatting derived from wp-admin/includes/class-wp-comments-list-table.php
							?>
							<table class="widefat fixed comments" cellspacing="0">
							<thead>
								<tr>
									<th scope='col' id='author' class='manage-column column-author'  style="">Author</th>
									<th scope='col' id='comment' class='manage-column column-comment'  style="">Comment</th>
									<th scope='col' id='response' class='manage-column column-response'  style="">In Response To</th>
								</tr>
							</thead>

							<tbody id="the-comment-list" class="list:comment">
							<?php
							global $comment, $comment_status, $the_comment_status, $post;

							// Populate pending_comments
							$comment_post_ids = array();
							foreach ( $comments as $c ) {
								$comment_post_ids[] = $c->comment_post_ID;
							}
							$pending_count = get_pending_comments_num( $comment_post_ids );

							foreach ( $comments as $comment ) {
								$the_comment_status = wp_get_comment_status( $comment );
								$comment_url = esc_url( get_comment_link() );
								$post = get_post( $comment->comment_post_ID );

								if ( get_option('show_avatars') ) {
									$avatar = get_avatar( $comment, 32 ) . ' ';
								} else {
									$avatar = '';
								}

								$author_url = get_comment_author_url();
								if ( 'http://' == $author_url ) {
									$author_url = '';
								}
								$author_url_display = preg_replace( '|http://(www\.)?|i', '', $author_url );
								if ( strlen( $author_url_display ) > 50 ) {
									$author_url_display = substr( $author_url_display, 0, 49 ) . '...';
								}

								if ( isset( $pending_count[$post->ID] ) ) {
									$pending_comments = $pending_count[$post->ID];
								} else {
									$pending_count_temp = get_pending_comments_num( array( $post->ID ) );
									$pending_comments = $this->pending_count[$post->ID] = $pending_count_temp[$post->ID];
								}

								if ( current_user_can( 'edit_post', $post->ID ) ) {
									$post_link = "<a href='" . get_edit_post_link( $post->ID ) . "'>";
									$post_link .= get_the_title( $post->ID ) . '</a>';
								} else {
									$post_link = get_the_title( $post->ID );
								}
								?>
								<tr id='comment-<?php echo $comment->comment_ID; ?>' class='<?php echo $the_comment_status; ?>'>
									<td class='author column-author'>
									<strong><?php echo $avatar . get_comment_author(); ?></strong><br />
									<?php
									if ( ! empty( $author_url ) ) {
										echo "<a title='$author_url' href='$author_url'>$author_url_display</a><br />\n";
									}

									if ( current_user_can( 'edit_comment' ) ) {
										if ( ! empty( $comment->comment_author_email ) ) {
											comment_author_email_link();
											echo '<br />' . "\n";
										}
										echo '<a href="' . admin_url( 'edit-comments.php?s=' . get_comment_author_IP() . '&amp;mode=detail' );
										if ( 'spam' == $comment_status ) {
											echo '&amp;comment_status=spam';
										}
										echo '">' . get_comment_author_IP() . '</a>' . "\n";
									}
									?></td>

									<td class='comment column-comment'>
									<div class="submitted-on">
									<?php printf( __( 'Submitted by %4$s on <a href="%1$s">%2$s at %3$s</a>' ), $comment_url, get_comment_date( __( 'Y/m/d' ), $comment->comment_ID ), get_comment_date( get_option( 'time_format' ), $comment->comment_ID ), get_comment_author() ); ?>
									</div>
									<?php comment_text( $comment->comment_ID ); ?>
									<div class="row-actions">
									<span class='edit'><a href='<?php echo admin_url( "comment.php?action=editcomment&c=$comment->comment_ID" ); ?>' title='Edit comment'>Edit</a></span>
									<span class='history'> | <a href='<?php echo admin_url( "comment.php?action=editcomment&c=$comment->comment_ID#akismet-status" ); ?>' title='View comment history'> History</a></span>
									<span class='spam'> | <a href='<?php echo admin_url( "comment.php?action=spam&c=$comment->comment_ID" ); ?>' title='Mark this comment as spam'>Spam</a></span>
									<span class='trash'> | <a href='<?php echo admin_url( "comment.php?action=trash&c=$comment->comment_ID" ); ?>' title='Move this comment to the trash'>Trash</a></span>
									</div>
									</td>

									<td class='response column-response'>
									<div class="response-links">
									<span class="post-com-count-wrapper"><?php echo $post_link; ?><br />
									<?php $this->comments_bubble( $post->ID, $pending_comments ); ?>
									</span>
									<a href='<?php echo get_permalink( $post->ID ); ?>'>#</a>
									</div>
									<?php
									if ( 'attachment' == $post->post_type && ( $thumb = wp_get_attachment_image( $post->ID, array( 80, 60 ), true ) ) ) {
										echo $thumb;
									}
									?>
									</td>
								</tr>
								<?php
							}
							?>
							</tbody>
							</table>
							<?php
						} else {
							echo 'No comments found.';
						}
						?>
						</div>
						<?php
					} // Skip blogs where user cannot moderate_comments.
				}
				restore_current_blog();
			}
		}
	}

	function admin_menu() {
		add_submenu_page( 'index.php', 'Multisite Comments', 'Multisite Comments', 'moderate_comments', 'multisite-comments', array( $this, 'admin_view' ) );
	}

	/**
	 * Display a comment count bubble
	 * See WP_List_Table->comments_bubble() in wp-admin/includes/class-wp-list-table.php
	 *
	 * @param int $post_id The post ID
	 * @param int $pending_comments Number of pending comments
	 */
	function comments_bubble( $post_id, $pending_comments ) {
		$pending_phrase = sprintf( __( '%s pending' ), number_format( $pending_comments ) );

		if ( $pending_comments ) {
			echo '<strong>';
		}

		echo "<a href='" . esc_url( add_query_arg( 'p', $post_id, admin_url( 'edit-comments.php' ) ) ) . "' title='" . esc_attr( $pending_phrase ) . "' class='post-com-count'><span class='comment-count'>" . number_format_i18n( get_comments_number() ) . "</span></a>";

		if ( $pending_comments ) {
			echo '</strong>';
		}
	}
}

UCC_Multisite_Comments_Manager::init();
