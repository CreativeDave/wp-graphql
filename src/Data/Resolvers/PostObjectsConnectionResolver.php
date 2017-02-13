<?php
namespace WPGraphQL\Data\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;

/**
 * Class PostObjectsConnection
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class PostObjectsConnectionResolver {

	/**
	 * PostObjectsConnection constructor.
	 *
	 * @param $post_type
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 * @since 0.0.5
	 */
	public function __construct( $post_type, $source, $args, $context, $info ) {
		self::resolve( $post_type, $source, $args, $context, $info );
	}

	/**
	 * resolve
	 *
	 * This handles resolving a query for post objects (of any specified $post_type) from the root_query or from any
	 * connection where post_objects are queryable.
	 *
	 * This resolver takes in the Relay standard args (before, after, first, last) and uses them to query from the
	 * WP_Query and return results according to the Relay spec.
	 *
	 * PAGINATION DETAILS:
	 * For backward pagination, last and before should be used together.
	 * - last should be a non-negative integer
	 * - before should be a cursor which contains the offset of the position in the overall collection of data
	 *
	 * For forward pagination, first and after should be used together.
	 * - first should be a non-negative integer
	 * - after should be a cursor which contains the offset of the position in the overall collection of data
	 *
	 * PAGINATION ALGORITHM:
	 * If $first is set:
	 * - if $first is less than 0, throw an error
	 * - if $edges has length greater than first, slice the $edges to be the length of $first be removing $edges from the end of $edges
	 *
	 * If $last is set:
	 * - If $last is less than 0, throw an error
	 * - if $edges has length greater than $last, slice the $edges to be the length of $last by removing $edges from the start of $edges
	 *
	 * ADDITIONAL ARGUMENTS:
	 * Additional arguments are mapped from the GraphQL friendly names to WP_Query-friendly names and are applied to
	 * the WP_Query appropriately.
	 *
	 * @param $post_type
	 * @param $source
	 * @param array $args
	 * @param $context
	 * @param ResolveInfo $info
	 * @return array
	 * @throws \Exception
	 * @since 0.0.5
	 */
	public static function resolve( $post_type, $source, array $args, $context, ResolveInfo $info ) {

		/**
		 * Get the subfields that were queried so we can make proper decisions
		 */
		$field_selection = $info->getFieldSelection( 5 );

		/**
		 * Get the cursor offset based on the Cursor passed to the after/before args
		 * @since 0.0.5
		 */
		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : 0;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : 0;
		$first = absint( $args['first'] ) ? $args['first'] : null;
		$last = absint( $args['last'] ) ? $args['last'] : null;

		/**
		 * Throw an error if both First and Last were used, as they should not be used together as the
		 * first/last determines the order of the query results.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $args['after'] ) && ! empty( $args['before'] ) ) {
			throw new \Exception( __( '"Before" and "After" should not be used together in arguments.', 'wp-graphql' ) );
		}
		if ( ! empty( $first ) && ! empty( $last ) ) {
			throw new \Exception( __( '"First" and "Last" should not be used together in arguments.', 'wp-graphql' ) );
		}

		/**
		 * Determine the posts_per_page to query based on the $first/$last args
		 * @since 0.0.5
		 */
		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
			$query_args['posts_per_page'] = absint( $first );
			if ( ! empty( $before ) ) {
				$query_args['paged'] = 1;
			} elseif ( ! empty( $after ) ) {
				$query_args['paged'] = absint( ( $after / $first ) + 1 );
			}
		} elseif ( ! empty( $last ) ) {
			$query_args['order'] = 'ASC';
			$query_args['posts_per_page'] = absint( $last );
			if ( ! empty( $before ) ) {
				$query_args['order'] = 'DESC';
				$query_args['paged'] = absint( $before / $last );
			} elseif ( ! empty( $after ) ) {
				$query_args['paged'] = 1;
			}
		}

		/**
		 * Set the post_type based on the $post_type passed to the resolver
		 * @since 0.0.5
		 */
		$query_args['post_type'] = $post_type;

		/**
		 * Set no_found_rows to true by default to make queries more efficient by not having to calculate
		 * the entire set of data.
		 * @since 0.0.5
		 */
		$query_args['no_found_rows'] = true;

		/**
		 * If "pageInfo" is in the fieldSelection, we need to calculate the pagination details, so we need to run
		 * the query with no_found_rows set to false.
		 * @since 0.0.5
		 */
		if ( ! empty( $args ) || ! empty( $field_selection['pageInfo'] ) ) {
			$query_args['no_found_rows'] = false;
		}

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Query names to be used in the WP_Query
		 */
		$entered_args = [];
		if ( ! empty( $args['where'] ) ) {
			$entered_args = self::allowed_custom_args( $args['where'], $post_type, $source, $args, $context, $info );
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 * @since 0.0.5
		 */
		if ( ! empty( $entered_args ) ) {
			$query_args = array_merge( $query_args, $entered_args );
		}

		/**
		 * Run the query
		 * @since 0.0.5
		 */
		$wp_query = new \WP_Query( $query_args );

		/**
		 * Grab the post results out of the query
		 * @since 0.0.5
		 */
		$post_results = $wp_query->posts;

		/**
		 * Throw an exception if no results were found.
		 * @since 0.0.5
		 */
		if ( empty( $post_results ) ) {
			throw new \Exception( __( 'No results were found for the query. Try broadening the arguments.', 'wp-graphql' ) );
		}

		/**
		 * If pagination info was selected and we know the entire length of the data set, we need to build the offsets
		 * based on the details we received back from the query and query_args
		 */
		$edge_count = ! empty( $wp_query->found_posts ) ? absint( $wp_query->found_posts ) : count( $wp_query->posts );
		$meta['arrayLength'] = $edge_count;
		$meta['sliceStart'] = 0;

		/**
		 * Build the pagination details based on the arguments passed.
		 * @since 0.0.5
		 */
		if ( ! empty( $last ) ) {
			$meta['sliceStart'] = ( $edge_count - $last );
			$post_results = array_reverse( $post_results );
			if ( ! empty( $before ) ) {
				$meta['sliceStart'] = absint( $before - $last );
			} elseif ( ! empty( $after ) ) {
				$meta['sliceStart'] = absint( $after );
			}
		} elseif ( ! empty( $first ) ) {
			if ( ! empty( $before ) ) {
				$meta['sliceStart'] = absint( 0 );
			} elseif ( ! empty( $after ) ) {
				$meta['sliceStart'] = absint( $after + 1 );
			}
		}

		/**
		 * Generate the array of posts with keys representing the position
		 * of the post in the greater array of data
		 * @since 0.0.5
		 */
		$posts_array = [];
		if ( is_array( $post_results ) && ! empty( $post_results ) ) {
			$index = $meta['sliceStart'];
			foreach ( $post_results as $post ) {
				$posts_array[ $index ] = $post;
				$index++;
			}
		}

		/**
		 * Generate the Relay fields (pageInfo, Edges, Cursor, etc)
		 * @since 0.0.5
		 */
		$posts = Relay::connectionFromArraySlice( $posts_array, $args, $meta );

		/**
		 * Return the connection
		 * @since 0.0.5
		 */
		return $posts;

	}

	/**
	 * allowed_custom_args
	 *
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_Query friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be down to explore
	 * more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @since 0.0.5
	 */
	public static function allowed_custom_args( $args, $post_type, $source, $args, $context, $info ) {

		/**
		 * Start a fresh array
		 */
		$query_args = [];

		/**
		 * Author $args
		 */
		if ( ! empty( $args['author'] ) ) { $query_args['author'] = $args['author']; }
		if ( ! empty( $args['authorName'] ) ) { $query_args['author_name'] = $args['authorName']; }
		if ( ! empty( $args['authorIn'] ) ) { $query_args['author__in'] = $args['authorIn']; }
		if ( ! empty( $args['authorNotIn'] ) ) { $query_args['author__not_in'] = $args['authorNotIn']; }

		/**
		 * Category $args
		 */
		if ( ! empty( $args['cat'] ) ) { $query_args['cat'] = $args['cat']; }
		if ( ! empty( $args['categoryName'] ) ) { $query_args['category_name'] = $args['categoryName']; }
		if ( ! empty( $args['categoryAnd'] ) ) { $query_args['category__and'] = $args['categoryAnd']; }
		if ( ! empty( $args['categoryIn'] ) ) { $query_args['category__in'] = $args['categoryIn']; }
		if ( ! empty( $args['categoryNotIn'] ) ) { $query_args['category__not_in'] = $args['categoryNotIn']; }

		/**
		 * Tag $args
		 */
		if ( ! empty( $args['tag'] ) ) { $query_args['tag'] = $args['tag']; }
		if ( ! empty( $args['tagId'] ) ) { $query_args['tag_id'] = $args['tagId']; }
		if ( ! empty( $args['tagIds'] ) ) { $query_args['tag__and'] = $args['tagIds']; }
		if ( ! empty( $args['tagNotIn'] ) ) { $query_args['tag__not_in'] = $args['tagNotIn']; }
		if ( ! empty( $args['tagSlugAnd'] ) ) { $query_args['tag_slug__and'] = $args['tagSlugAnd']; }
		if ( ! empty( $args['tagSlugIn'] ) ) { $query_args['tag_slug__in'] = $args['tagSlugIn']; }

		/**
		 * TaxQuery $args
		 * This maps the GraphQL taxQuery input to the WP_Query tax_query format
		 * @since 0.0.5
		 */
		$tax_query = null;
		if ( ! empty( $args['taxQuery'] ) ) {
			$tax_query = $args['taxQuery'];
			if ( ! empty( $tax_query['taxArray'] ) && is_array( $tax_query['taxArray'] ) ) {
				if ( 2 < count( $tax_query['taxArray'] ) ) {
					unset( $tax_query['relation'] );
				}
				foreach ( $tax_query['taxArray'] as $tax_array_key => $value ) {
					$tax_query[] = [
						$tax_array_key => $value,
					];
				}
			}
			unset( $tax_query['taxArray'] );

		}
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		/**
		 * Post & Page Parameters
		 */
		if ( ! empty( $args['id'] ) ) { $query_args['p'] = absint( $args['id'] ); }
		if ( ! empty( $args['name'] ) ) { $query_args['name'] = $args['name']; }
		if ( ! empty( $args['title'] ) ) { $query_args['title'] = $args['title']; }
		if ( ! empty( $args['parent'] ) ) { $query_args['post_parent'] = $args['parent']; }
		if ( ! empty( $args['parentIn'] ) ) { $query_args['post_parent__in'] = $args['parentIn']; }
		if ( ! empty( $args['parentNotIn'] ) ) { $query_args['post_parent__not_in'] = $args['parentNotIn']; }
		if ( ! empty( $args['in'] ) ) { $query_args['post__in'] = $args['in']; }
		if ( ! empty( $args['notIn'] ) ) { $query_args['post__not_in'] = $args['notIn']; }
		if ( ! empty( $args['nameIn'] ) ) { $query_args['post_name__in'] = $args['nameIn']; }

		/**
		 * Password Parameters
		 */
		if ( ! empty( $args['hasPassword'] ) ) { $query_args['has_password'] = $args['hasPassword']; }
		if ( ! empty( $args['password'] ) ) { $query_args['post_password'] = $args['password']; }

		/**
		 * Status Parameters
		 */
		if ( ! empty( $args['status'] ) ) { $query_args['post_status'] = $args['status']; }

		/**
		 * Order Parameters
		 */
		if ( ! empty( $args['orderby'] ) ) { $query_args['orderby'] = $args['orderby']; }

		/**
		 * DateQuery Parameters
		 */
		if ( ! empty( $args['dateQuery'] ) ) { $query_args['date_query'] = $args['dateQuery']; }

		/**
		 * metaQuery Parameters
		 */
		$meta_query = null;
		if ( ! empty( $args['metaQuery'] ) ) {
			$meta_query = $args['metaQuery'];
			if ( ! empty( $meta_query['metaArray'] ) && is_array( $meta_query['metaArray'] ) ) {
				if ( 2 < count( $meta_query['metaArray'] ) ) {
					unset( $meta_query['relation'] );
				}
				foreach ( $meta_query['metaArray'] as $meta_query_key => $value ) {
					$meta_query[] = [
						$meta_query_key => $value,
					];
				}
			}
			unset( $meta_query['metaArray'] );

		}
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		/**
		 * Filter the $query_args
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the WP_Query
		 *
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_wp_query_allowed_args', $query_args, $args, $post_type, $source, $args, $context, $info );

		/**
		 * Return the Query Args
		 */
		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

}