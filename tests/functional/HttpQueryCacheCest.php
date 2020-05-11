<?php

function getQueryCachingEndpoint($config)
{
    return '/?graphql&' .
        http_build_query([
            'test_query_cache_config' => json_encode($config),
        ]);
}

class HttpQueryCacheCest
{
    public function testBasicFullQueryCache(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $endpoint = getQueryCachingEndpoint([
            'zone' => 'query_cache_test',
            'query_name' => 'getPostsFullQuery',
            'expire' => 60,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query getPostsFullQuery( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'MISS');

        $I->updateInDatabase(
            $I->grabPostsTableName(),
            ['post_title' => 'Updated Post'],
            ['ID' => $post_id]
        );

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->seeHttpHeader('Content-Type', 'application/json; charset=UTF-8');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'HIT');
    }

    public function testDoesNotCacheNonMatchedQueries(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $endpoint = getQueryCachingEndpoint([
            'zone' => 'query_cache_test',
            'query_name' => 'getPostsFullQuery',
            'expire' => 60,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query SomeQueryOtherQuery( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->dontSeeHttpHeader('x-graphql-query-cache', 'MISS');

        $I->updateInDatabase(
            $I->grabPostsTableName(),
            ['post_title' => 'Updated Post'],
            ['ID' => $post_id]
        );

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->seeHttpHeader('Content-Type', 'application/json; charset=UTF-8');
        $I->dontSeeHttpHeader('x-graphql-query-cache', 'HIT');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Updated Post',
                ],
            ],
        ]);
    }

    public function testCacheCanExpire(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $endpoint = getQueryCachingEndpoint([
            'zone' => 'query_cache_test',
            'query_name' => 'getPostsFullQuery',
            'expire' => 1,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query getPostsFullQuery( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'MISS');

        $I->updateInDatabase(
            $I->grabPostsTableName(),
            ['post_title' => 'Updated Post'],
            ['ID' => $post_id]
        );

        // Let it expire
        sleep(2);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->seeHttpHeader('Content-Type', 'application/json; charset=UTF-8');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Updated Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'MISS');
    }

    public function testWildcardCache(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $endpoint = getQueryCachingEndpoint([
            'zone' => 'query_cache_test',
            'query_name' => '*',
            'expire' => 60,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query getRandomQueryName( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'MISS');

        $I->updateInDatabase(
            $I->grabPostsTableName(),
            ['post_title' => 'Updated Post'],
            ['ID' => $post_id]
        );

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($endpoint, [
            'query' => $query,
            'variables' => [
                'postId' => $post_id,
            ],
        ]);

        $I->seeHttpHeader('Content-Type', 'application/json; charset=UTF-8');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-query-cache', 'HIT');
    }
}
