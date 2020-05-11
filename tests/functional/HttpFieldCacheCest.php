<?php

class HttpFieldCacheCest
{
    public function testCacheStatusHeaders(FunctionalTester $I)
    {
        shell_exec('rm -rf /tmp/wp-graphql-cache/');

        $post_id = $I->havePostInDatabase(['post_title' => 'Test Post']);

        $query = '
		query getPosts( $postId: ID! ) {
		  post( id: $postId, idType: DATABASE_ID ) {
			title
          }
 		}
        ';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/?graphql', [
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
        $I->sendPOST('/?graphql', [
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
        $I->seeHttpHeader('x-graphql-field-cache', 'HIT:post');
    }
}
