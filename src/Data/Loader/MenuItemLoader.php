<?php
namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;

class MenuItemLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {

		/**
		 * If there are no keys, return null and don't execute the query.
		 */
		if ( empty( $keys ) ) {
			return null;
		}

		$all_posts = [];
		if ( empty( $keys ) ) {
			return $keys;
		}

		/**
		 * Prepare the args for the query. We're provided a specific
		 * set of IDs, so we want to query as efficiently as possible with
		 * as little overhead as possible. We don't want to return post counts,
		 * we don't want to include sticky posts, and we want to limit the query
		 * to the count of the keys provided. The query must also return results
		 * in the same order the keys were provided in.
		 */
		$args = [
			'post_type' => 'nav_menu_item',
			'post_status' => 'any',
			'posts_per_page' => count( $keys ),
			'post__in' => $keys,
			'orderby' => 'post__in',
			'no_found_rows' => true,
			'split_the_query' => true,
			'ignore_sticky_posts' => true,
		];

		/**
		 * Ensure that WP_Query doesn't first ask for IDs since we already have them.
		 */
		add_filter( 'split_the_query', function ( $split, \WP_Query $query ) {
			if ( false === $query->get( 'split_the_query' ) ) {
				return false;
			}
			return $split;
		}, 10, 2 );


		new \WP_Query( $args );

		/**
		 * Loop over the posts and return an array of all_posts,
		 * where the key is the ID and the value is the Post passed through
		 * the model layer.
		 */
		foreach ( $keys as $key ) {

			/**
			 * The query above has added our objects to the cache
			 * so now we can pluck them from the cache to return here
			 * and if they don't exist we can throw an error, otherwise
			 * we can proceed to resolve the object via the Model layer.
			 */
			$post_object = get_post( absint( $key ) );
			if ( empty( $post_object ) ) {
				throw new \Exception( sprintf( __( 'No post exists with id: %s', 'wp-graphql' ), $key ) );
			}

			/**
			 * Return the instance through the Model to ensure we only
			 * return fields the consumer has access to.
			 */
			$all_posts[ $key ] = new MenuItem( $post_object );
		}

		return $all_posts;

	}

}