<?php

class UserObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;

	public function setUp() {
		parent::setUp();

		$this->current_time = strtotime( '- 1 day' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function createUserObject( $args = [] ) {

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'role' => 'subscriber',
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$user_id = $this->factory->user->create( $args );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $user_id;

	}

	/**
	 * testUserQuery
	 *
	 * This tests creating a single user with data and retrieving said user via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testUserQuery() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject(
			[
				'user_email' => 'test@test.com',
			]
		);
		$user    = get_user_by( 'id', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				avatar {
					size
				}
				capKey
				capabilities
				comments {
					edges {
						node {
							commentId
						}
					}
				}
				description
				email
				extraCapabilities
				firstName
				id
				lastName
				locale
				mediaItems {
					edges {
						node {
							mediaItemId
						}
					}
				}
				name
				nickname
				pages {
					edges {
						node {
							pageId
						}
					}
				}
				posts {
					edges {
						node {
							postId
						}
					}
				}
				registeredDate
				roles
				slug
				url
				userId
				username
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );


		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'user' => [
					'avatar'            => [
						'size' => 96,
					],
					'capKey'            => 'wp_capabilities',
					'capabilities'      => [ 'read', 'level_0', 'subscriber' ],
					'comments'          => [
						'edges' => [],
					],
					'description'       => null,
					'email'             => 'test@test.com',
					'extraCapabilities' => [ 'read', 'level_0', 'subscriber' ],
					'firstName'         => null,
					'id'                => $global_id,
					'lastName'         => null,
					'locale'            => 'en_US',
					'mediaItems'        => [
						'edges' => [],
					],
					'name'              => $user->data->display_name,
					'nickname'          => $user->nickname,
					'pages'             => [
						'edges' => [],
					],
					'posts'             => [
						'edges' => [],
					],
					'registeredDate'    => date( 'c', strtotime( $user->user_registered ) ),
					'roles'             => [ 'subscriber' ],
					'slug'              => $user->data->user_nicename,
					'url'               => null,
					'userId'            => $user_id,
					'username'          => $user->data->user_login,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserQueryWithComments
	 *
	 * This tests a single user with comments connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithComments() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$comment_id = $this->factory->comment->create( [ 'user_id' => $user_id ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				comments {
					edges {
						node {
							commentId
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'user' => [
					'comments' => [
						'edges' => [
							[
								'node' => [
									'commentId' => $comment_id,
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserQueryWithPosts
	 *
	 * This tests a single user with posts connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithPosts() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create( [ 'post_author' => $user_id ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				posts {
					edges {
						node {
							postId
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'user' => [
					'posts' => [
						'edges' => [
							[
								'node' => [
									'postId' => $post_id,
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserQueryWithPages
	 *
	 * This tests a single user with pages connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithPages() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create( [ 'post_author' => $user_id, 'post_type' => 'page' ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				pages {
					edges {
						node {
							pageId
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'user' => [
					'pages' => [
						'edges' => [
							[
								'node' => [
									'pageId' => $post_id,
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserQueryWithMedia
	 *
	 * This tests a single user with mediaItems connection.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWithMedia() {

		/**
		 * Create a user
		 */
		$user_id = $this->createUserObject();

		$post_id = $this->factory->post->create( [ 'post_author' => $user_id, 'post_type' => 'attachment' ] );

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				mediaItems {
					edges {
						node {
							mediaItemId
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'user' => [
					'mediaItems' => [
						'edges' => [
							[
								'node' => [
									'mediaItemId' => $post_id,
								],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testUserQueryWhereUserDoesNotExist
	 *
	 * Tests a query for non existant user.
	 *
	 * @since 0.0.5
	 */
	public function testUserQueryWhereUserDoesNotExist() {
		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			user(id: \"{$global_id}\") {
				userId
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data'   => [
				'user' => null,
			],
			'errors' => [
				[
					'message'   => 'No user was found with the provided ID',
					'locations' => [
						[
							'line'   => 3,
							'column' => 4,
						],
					],
					'path'      => [
						'user',
					],
					'category' => 'user',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	public function testUsersQuery() {

		/**
		 * Create a user
		 */
		$email = 'test@test.com';
		$user_id = $this->createUserObject(
			[
				'user_email' => $email,
			]
		);

		/**
		 * Create the global ID based on the user_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user', $user_id );
		wp_set_current_user( $user_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query {
			users(first:1) {
				edges{
				  node{
				    id
				    userId
				    email
				  }
				}
			}
		}';

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'users' => [
					'edges' => [
						[
							'node' => [
								'id' => $global_id,
								'userId' => $user_id,
								'email' => $email,
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testQueryAllUsersAsAdmin() {

		$user_1 = [
			'user_email' => 'user1@email.com',
			'user_login' => 'user1',
			'user_url' => 'https://test1.com',
			'first_name' => 'User1',
			'last_name' => 'Test',
		];

		$user_2 = [
			'user_email' => 'user2@email.com',
			'user_login' => 'user2',
			'user_url' => 'https://test2.com',
			'first_name' => 'User2',
			'last_name' => 'Test',
		];

		$user_1_id = $this->createUserObject( $user_1 );
		$user_2_id = $this->createUserObject( $user_2 );
		$admin = $this->createUserObject( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin );

		$query = '
		query {
			users(first:2) {
				edges{
				  node{
				    userId
				    username
				    email
				    firstName
				    lastName
				    url
				  }
				}
			}
		}';

		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'users' => [
					'edges' => [
						[
							'node' => [
								'userId' => $user_2_id,
								'username' => $user_2['user_login'],
								'email' => $user_2['user_email'],
								'firstName' => $user_2['first_name'],
								'lastName' => $user_2['last_name'],
								'url' => $user_2['user_url'],
							],
						],
						[
							'node' => [
								'userId' => $user_1_id,
								'username' => $user_1['user_login'],
								'email' => $user_1['user_email'],
								'firstName' => $user_1['first_name'],
								'lastName' => $user_1['last_name'],
								'url' => $user_1['user_url'],
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testQueryAllUsersAsSubscriber() {

		$user_1 = [
			'user_email' => 'user1@email.com',
			'user_login' => 'user1',
			'user_url' => 'https://test1.com',
			'first_name' => 'User1',
			'last_name' => 'Test',
			'role' => 'subscriber',
			'description' => 'User 1 Test',
		];

		$user_2 = [
			'user_email' => 'user2@email.com',
			'user_login' => 'user2',
			'user_url' => 'https://test2.com',
			'first_name' => 'User2',
			'last_name' => 'Test',
			'role' => 'subscriber',
			'description' => 'User 2 test',
		];

		$user_1_id = $this->createUserObject( $user_1 );
		$user_2_id = $this->createUserObject( $user_2 );

		wp_set_current_user( $user_2_id );

		$query = '
		query {
			users(first:2) {
				edges{
				  node{
				    userId
				    username
				    email
				    firstName
				    lastName
				    url
				    description
				    isPublic
				    isRestricted
				    isPrivate
				  }
				}
			}
		}';

		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'users' => [
					'edges' => [
						[
							'node' => [
								'userId' => $user_2_id,
								'username' => $user_2['user_login'],
								'email' => $user_2['user_email'],
								'firstName' => $user_2['first_name'],
								'lastName' => $user_2['last_name'],
								'url' => $user_2['user_url'],
								'description' => $user_2['description'],
								'isPublic' => true,
								'isRestricted' => false,
								'isPrivate' => false,
							],
						],
						[
							'node' => [
								'userId' => $user_1_id,
								'username' => null,
								'email' => null,
								'firstName' => $user_1['first_name'],
								'lastName' => $user_2['last_name'],
								'url' => null,
								'description' => $user_1['description'],
								'isPublic' => false,
								'isRestricted' => true,
								'isPrivate' => false,
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	public function testPageInfoQuery() {

		/**
		 * Let's create 2 admins and 1 subscriber so we can test our "where" arg is working
		 */
		$this->factory->user->create([
			'role' => 'administrator',
		]);

		$this->factory->user->create([
			'role' => 'administrator',
		]);

		$this->factory->user->create([
			'role' => 'subscriber',
		]);

		$query = '
		query{
		  users(first:2 where: {role:ADMINISTRATOR}){
		    pageInfo{
		      hasNextPage
		    }
		    edges{
		      node{
		        id
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		$this->assertNotEmpty( $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 2, $actual['data']['users']['edges'] );

		$query = '
		query{   
		  users(first:1 where: {role:SUBSCRIBER}){
		    pageInfo{
		      hasNextPage
		    }
		    edges{
		      node{
		        id
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Now let's make sure the subscriber role query worked
		 */
		$this->assertNotEmpty( $actual['data']['users']['pageInfo'] );
		$this->assertNotEmpty( $actual['data']['users']['edges'] );
		$this->assertCount( 1, $actual['data']['users']['edges'] );

	}

}