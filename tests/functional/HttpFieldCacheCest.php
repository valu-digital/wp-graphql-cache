<?php

function getFieldCachingEndpoint($config)
{
    return '/?graphql&' .
        http_build_query([
            'test_query_field_config' => json_encode($config),
        ]);
}

class HttpFieldCacheCest
{
    public function testCacheStatusHeaders(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');
        $endpoint = getFieldCachingEndpoint([
            'zone' => 'functional-field-test',
            'query_name' => 'getPosts',
            'field_name' => 'post',
            'expire' => 60,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query getPosts( $postId: ID! ) {
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
        $I->seeHttpHeader('x-graphql-field-cache', 'MISS:post');

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
        $I->seeHttpHeader('x-graphql-field-cache', 'HIT:post');
    }

    public function testDoesNotCacheErrors(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $endpoint = getFieldCachingEndpoint([
            'zone' => 'functional-field-test',
            'query_name' => 'WithErrors',
            'field_name' => 'post',
            'expire' => 60,
        ]);

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query WithErrors( $postId: ID! ) {
          badField
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
                'badField' => null,
                'post' => [
                    'title' => 'Test Post',
                ],
            ],
        ]);

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
                'badField' => null,
                'post' => [
                    'title' => 'Updated Post',
                ],
            ],
        ]);
        $I->seeHttpHeader('x-graphql-field-cache', 'MISS:post');
    }
}
