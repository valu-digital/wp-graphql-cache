<?php

use WPGraphQL\Extensions\Cache\CacheManager;

class FieldCacheTest extends \Codeception\TestCase\WPTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Start with fresh cache
        CacheManager::clear();
    }

    public function testBasicCacheWorkflow()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'test',
            'query_name' => 'getPosts',
            'field_name' => 'post',
            'expire' => 1,
        ]);

        $post_id = self::factory()->post->create([
            'post_title' => 'A Post',
        ]);

        $other_post_id = self::factory()->post->create([
            'post_title' => 'The Other Post',
        ]);

        $query = '
		query getPosts( $postId: ID!, $otherPostId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }

		  otherPost: post( id: $otherPostId, idType: DATABASE_ID ) {
			title
		  }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
                'otherPostId' => $other_post_id,
            ],
        ]);

        // Reponds correctly on cache miss
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);

        // Unrelated field is not touched
        $this->assertEquals(
            'The Other Post',
            $actual['data']['otherPost']['title']
        );

        wp_update_post([
            'ID' => $post_id,
            'post_title' => 'Updated post',
        ]);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
                'otherPostId' => $other_post_id,
            ],
        ]);

        // Responds with the same data even when the post is updated
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);
        // Unrelated field is not touched
        $this->assertEquals(
            'The Other Post',
            $actual['data']['otherPost']['title']
        );

        // Wait for the cache to expire
        sleep(2);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
                'otherPostId' => $other_post_id,
            ],
        ]);

        // Cache responds with updated data after expiration
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Updated post', $actual['data']['post']['title']);

        // Unrelated field is not touched
        $this->assertEquals(
            'The Other Post',
            $actual['data']['otherPost']['title']
        );
    }

    public function testFullCacheClear()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'ding_zone',
            'query_name' => 'getPosts',
            'field_name' => 'ding',
        ]);

        CacheManager::register_graphql_field_cache([
            'zone' => 'dong_zone',
            'query_name' => 'getPosts',
            'field_name' => 'dong',
        ]);

        $ding_id = self::factory()->post->create([
            'post_title' => 'Ding',
        ]);

        $dong_id = self::factory()->post->create([
            'post_title' => 'Dong',
        ]);

        $query = '
		query getPosts( $dingId: ID!, $dongId: ID! ) {
            ding: post( id: $dingId, idType: DATABASE_ID ) {
                title
            }

            dong: post( id: $dongId, idType: DATABASE_ID ) {
                title
            }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'dingId' => $ding_id,
                'dongId' => $dong_id,
            ],
        ]);

        // Reponds correctly on cache miss
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Ding', $actual['data']['ding']['title']);
        $this->assertEquals('Dong', $actual['data']['dong']['title']);

        wp_update_post([
            'ID' => $ding_id,
            'post_title' => 'Updated Ding',
        ]);

        wp_update_post([
            'ID' => $dong_id,
            'post_title' => 'Updated Dong',
        ]);

        // Clear both zones
        CacheManager::clear();

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'dingId' => $ding_id,
                'dongId' => $dong_id,
            ],
        ]);
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));

        $this->assertEquals('Updated Ding', $actual['data']['ding']['title']);
        $this->assertEquals('Updated Dong', $actual['data']['dong']['title']);
    }

    public function testCanCacheMultipleFieldsWithZones()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'ding_zone',
            'query_name' => 'getPosts',
            'field_name' => 'ding',
        ]);

        CacheManager::register_graphql_field_cache([
            'zone' => 'dong_zone',
            'query_name' => 'getPosts',
            'field_name' => 'dong',
        ]);

        $ding_id = self::factory()->post->create([
            'post_title' => 'Ding',
        ]);

        $dong_id = self::factory()->post->create([
            'post_title' => 'Dong',
        ]);

        $query = '
		query getPosts( $dingId: ID!, $dongId: ID! ) {
            ding: post( id: $dingId, idType: DATABASE_ID ) {
                title
            }

            dong: post( id: $dongId, idType: DATABASE_ID ) {
                title
            }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'dingId' => $ding_id,
                'dongId' => $dong_id,
            ],
        ]);

        // Reponds correctly on cache miss
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Ding', $actual['data']['ding']['title']);
        $this->assertEquals('Dong', $actual['data']['dong']['title']);

        wp_update_post([
            'ID' => $ding_id,
            'post_title' => 'Updated Ding',
        ]);

        wp_update_post([
            'ID' => $dong_id,
            'post_title' => 'Updated Dong',
        ]);

        CacheManager::clear_zone('dong_zone');

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'dingId' => $ding_id,
                'dongId' => $dong_id,
            ],
        ]);
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));

        // Ding zone was not cleard so this is not updated
        $this->assertEquals('Ding', $actual['data']['ding']['title']);

        // Dong zone was cleard so this is updated
        $this->assertEquals('Updated Dong', $actual['data']['dong']['title']);
    }

    public function testCacheIsNotSharedBetweenUsers()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'test',
            'query_name' => 'getPosts',
            'field_name' => 'post',
        ]);

        $user1 = $this->factory()->user->create();
        $user2 = $this->factory()->user->create();

        $post_id = self::factory()->post->create([
            'post_title' => 'A Post',
        ]);

        $query = '
		query getPosts( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        // Cache the query for user1

        wp_set_current_user($user1);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => 'Updated post',
        ]);

        wp_set_current_user($user2);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        // User2 sees the update post
        $this->assertEquals('Updated post', $actual['data']['post']['title']);

        wp_set_current_user($user1);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        // But user1 still sees the original from cache
        $this->assertEquals('A Post', $actual['data']['post']['title']);
    }

    public function testDoesNotCacheOnErrors()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'test',
            'query_name' => 'getPosts',
            'field_name' => 'post',
            'expire' => 60,
        ]);

        $post_id = self::factory()->post->create([
            'post_title' => 'A Post',
        ]);

        $query = '
		query getPosts( $postId: ID! ) {
          badField
		  post( id: $postId, idType: DATABASE_ID ) {
            title
          }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $this->assertArrayHasKey('errors', $actual);
        $this->assertEquals('A Post', $actual['data']['post']['title']);
        $this->assertEquals(null, $actual['data']['badField']);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => 'Updated post',
        ]);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $this->assertArrayHasKey('errors', $actual);
        $this->assertEquals(null, $actual['data']['badField']);
        $this->assertEquals('Updated post', $actual['data']['post']['title']);
    }

    public function testVariablesGenerateDifferentKeys()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'test_variables',
            'query_name' => 'getPostsVariable',
            'field_name' => 'post',
            'expire' => 1,
        ]);

        $post_id = self::factory()->post->create([
            'post_title' => 'A Post',
        ]);

        $other_post_id = self::factory()->post->create([
            'post_title' => 'The Other Post',
        ]);

        $query = '
		query getPostsVariable( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $other_post_id,
            ],
        ]);

        // Responds with the same data even when the post is updated
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('The Other Post', $actual['data']['post']['title']);
    }

    public function testVariableKeyGenerationIsFieldSpecific()
    {
        CacheManager::register_graphql_field_cache([
            'zone' => 'test_variables_field',
            'query_name' => 'getPostsVariable',
            'field_name' => 'third',
            'expire' => 1,
        ]);

        $post_id = self::factory()->post->create([
            'post_title' => 'A Post',
        ]);

        $other_post_id = self::factory()->post->create([
            'post_title' => 'The Other Post',
        ]);

        $third_post_id = self::factory()->post->create([
            'post_title' => 'Third post',
        ]);

        $query = "
		query getPostsVariable( \$postId: ID! ) {
		  post( id: \$postId, idType: DATABASE_ID ) {
			title
          }
		  third: post( id: \"$third_post_id\", idType: DATABASE_ID ) {
			title
          }
 		}
		";

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Third post', $actual['data']['third']['title']);

        wp_update_post([
            'ID' => $third_post_id,
            'post_title' => 'Third post updated',
        ]);

        $actual = graphql([
            'query' => $query,
            'variables' => [
                'postId' => $other_post_id,
            ],
        ]);

        // Responds with the same data even when the post is updated
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Third post', $actual['data']['third']['title']);
    }
}
