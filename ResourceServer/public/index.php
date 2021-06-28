<?php
//declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../Common/common.php';
require_once __DIR__ . '/../../Database/databaseHandler.php';

$app = AppFactory::create();

session_start();

// クライアントからのリソース取得
$app->post('/resourceTest', function (Request $request, Response $response, $args) {

    $reqAccessToken = "";

    $authHeader =  $request->getHeader('Authorization');

    if(count($authHeader) > 0){
        $auth = explode(' ', strstr($authHeader[0], 'Bearer '))[1];
        $bearer = explode(':', $auth);
        $reqAccessToken = $bearer[0];
    }

    if(!$reqAccessToken){
        return returnJsonError($response, 'アクセストークが正しくありません。', 404);
    }

    $tokens = getTokens($reqAccessToken);

    if($tokens){
        $data = [
            'resource' => [
                'name' => 'Protected Resource',
                'description' => 'This is Protected Resource'
            ]
        ];
        return returnJson($response, $data);

    } else {
        return returnJsonError($response, 'アクセストークが正しくありません。', 404);
    }

});

// クライアントからのリソース取得
$app->get('/resource/{resource_name}', function (Request $request, Response $response, $args) {

    $reqAccessToken = "";

    $reqResourceName = $args['resource_name'] ?? '';

    $authHeader = $request->getHeader('Authorization');

    if (count($authHeader) > 0) {
        $auth = explode(' ', strstr($authHeader[0], 'Bearer '))[1];
        $bearer = explode(':', $auth);
        $reqAccessToken = $bearer[0];
    }

    if(!$reqAccessToken){
        return returnJsonError($response, 'アクセストークが正しくありません。', 404);
    }

    $tokens = getTokens($reqAccessToken);

    $scopes = explode(' ', $tokens['scope']);
    $email = $tokens['user_email'];

    if(count($scopes) === 0 || !in_array($reqResourceName, $scopes)){
        return returnJsonError($response, '許可されていないリソースです。', 403);
    }

    switch ($reqResourceName) {
        case 'favorites':
            $resources = getFavorites($email);
            break;
        default:
            return returnJsonError($response, 'リソース名が正しくありません。', 404);
    }


    $data = [
        $reqResourceName => $resources
    ];
    return returnJson($response, $data);

});

// クライアントからのリソース取得
$app->get('/userinfo', function (Request $request, Response $response, $args) {

    $reqAccessToken = "";

    $authHeader = $request->getHeader('Authorization');

    if (count($authHeader) > 0) {
        $auth = explode(' ', strstr($authHeader[0], 'Bearer '))[1];
        $bearer = explode(':', $auth);
        $reqAccessToken = $bearer[0];
    }

    if(!$reqAccessToken){
        return returnJsonError($response, 'アクセストークが正しくありません。', 404);
    }

    $tokens = getTokens($reqAccessToken);

    if(!$tokens){
        return returnJsonError($response, 'アクセストークが正しくありません。', 404);
    }

    $scopes = explode(' ', $tokens['scope']);
    $email = $tokens['user_email'];

    if(count($scopes) === 0 || !in_array('openid', $scopes)){
        return returnJsonError($response, 'openidを許可してください。。', 403);
    }

    $user = getUser($email);

    $returnUserData = [];

    if(in_array('email', $scopes)){
        $returnUserData['email'] = $user['email'] ?? "";
    }
    if(in_array('address', $scopes)){
        $returnUserData['address'] = $user['address'] ?? "";
    }
    if(in_array('phone', $scopes)){
        $returnUserData['phone'] = $user['phone'] ?? "";
    }
    if(in_array('name', $scopes)){
        $returnUserData['name'] = $user['name'] ?? "";
    }
    if(in_array('preferred_username', $scopes)){
        $returnUserData['preferred_username'] = $user['preferred_username'] ?? "";
    }


    $data = [
        'userinfo' => $returnUserData
    ];
    return returnJson($response, $data);

});

function getTokens($reqAccessToken){

    $db = new DatabaseHandler();
    $sql = "select * from tokens where access_token = '${reqAccessToken}' ";
    return $db->selectOne($sql);
}

function getResource($scopes){

    return [
        'name' => 'Protected Resource',
        'description' => 'This is Protected Resource'
    ];
}

function getFavorites($email){
    $db = new DatabaseHandler();
    $sql = "select item from favorites where email = '${email}' ";
    $result =  $db->select($sql);

    $arr = [];
    if($result === null) {
        return $arr;
    }

    foreach($result as $v){
        $arr[] = $v['item'];
    }

    return $arr;
}

function getUser($email){
    $db = new DatabaseHandler();
    $sql = "select * from users where email = '${email}' ";
    return $db->selectOne($sql);
}


$app->run();

//php -S localhost:8002 ./public/index.php