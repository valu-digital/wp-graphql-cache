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
            'operationname' => 'getPosts',
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
            'operationname' => 'getPosts',
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
}
