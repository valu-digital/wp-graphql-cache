<?php

use WPGraphQL\Extensions\Cache\CacheManager;

class ExampleTest extends \Codeception\TestCase\WPTestCase
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
		query getPosts( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }

		  otherPost: post( id: $postId, idType: DATABASE_ID ) {
			title
		  }
 		}
		';

        $actual = graphql([
            'query' => $query,
            'operationname' => 'getPosts',
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        // Reponds correctly on cache miss
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => 'Updated post',
        ]);

        $actual = graphql([
            'query' => $query,
            'operationname' => 'getPosts',
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        // Responds with the same data even when the post is updated
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('A Post', $actual['data']['post']['title']);

        // Wait for the cache to expire
        sleep(2);

        $actual = graphql([
            'query' => $query,
            'operationname' => 'getPosts',
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        // Cache responds with updated data after expiration
        $this->assertArrayNotHasKey('errors', $actual, print_r($actual, true));
        $this->assertEquals('Updated post', $actual['data']['post']['title']);
    }
}
