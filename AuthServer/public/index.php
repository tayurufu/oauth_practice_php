<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Firebase\JWT\JWT;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../Common/common.php';
require_once __DIR__ . '/../../Database/databaseHandler.php';

$app = AppFactory::create();

session_start();

// クライアントサーバーと認証サーバーで事前に決めておく
const CLIENTS_STATIC = [
    "oauth-client-1" => [
        "client_id" => "oauth-client-1",
        "client_secret" => "oauth-client-secret-1",
        "redirect_uris" => ["http://localhost:8000/callback"],
        "scope" => "address email favorites name openid phone preferred_username"
    ]
];

// 0: 静的, 1: 動的
const OAUTH_CLIENT_TYPE = 1;

//
$app->get('/login', function(Request $request, Response $response, $args){

    $queryParams = $request->getQueryParams();
    $reqClientId = $queryParams['client_id'] ?? null;
    $reqScopeStr = $queryParams['scope'] ?? null;
    $reqResponseType = $queryParams['response_type'] ?? null;
    $reqRedirectUri = $queryParams['redirect_uri'] ?? null;
    $reqState = $queryParams['state'] ?? null;

    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    return $renderer->render($response,
        "login.php",
        [
            'client_id' => $reqClientId,
            'scope' => $reqScopeStr,
            'response_type' => $reqResponseType,
            'redirect_uri' => $reqRedirectUri,
            'state' => $reqState,
            'error' => ""
        ]
    );
});

$app->post('/login', function(Request $request, Response $response, $args){

    $queryParams = (array)$request->getParsedBody();
    $reqUser = $queryParams['user'] ?? "";
    $reqClientId = $queryParams['client_id'] ?? null;
    $reqScopeStr = $queryParams['scope'] ?? null;
    $reqResponseType = $queryParams['response_type'] ?? null;
    $reqRedirectUri = $queryParams['redirect_uri'] ?? null;
    $reqState = $queryParams['state'] ?? null;
    $user = getUser($reqUser);

    if(!$user){
        $renderer = new PhpRenderer(__DIR__ . '/../templates');
        return $renderer->render($response,
            "login.php",
            [
                'clientId' => $reqClientId ?? "",
                'scope' => $reqScopeStr ?? "",
                'response_type' => $reqResponseType ?? "",
                'redirect_uri' => $reqRedirectUri ?? "",
                'state' => $reqState ?? "",
                'error' => ""
            ]
        );
    } else {

        setLoginUser($user);

        return returnRedirect($response, '/authorize', [
            'client_id' => $reqClientId,
            'scope' => $reqScopeStr,
            'response_type' => $reqResponseType ?? "",
            'redirect_uri' => $reqRedirectUri ?? "",
            'state' => $reqState ?? "",
        ]);
    }

});

// クライアントサーバーからリダイレクト
$app->get('/authorize', function (Request $request, Response $response, $args)  {

    $queryParams = $request->getQueryParams();
    $reqClientId = $queryParams['client_id'] ?? null;
    $reqScopeStr = $queryParams['scope'] ?? null;
    $requestScopes = $reqScopeStr ? explode(" ", $reqScopeStr) : [];
    $reqResponseType = $queryParams['response_type'] ?? null;
    $reqRedirectUri = $queryParams['redirect_uri'] ?? null;
    $reqState = $queryParams['state'] ?? null;

    // OAuth clientの取得
    $client = getClient($reqClientId);

    if(!$client){
        $response->getBody()->write("クライアントとの認証コードが正しくありません。");
        return $response;
    }

    // スコープ取得
    $clientScopes = $client['scope'] ? explode(" ", $client['scope']) : [];

    if(!checkArray($clientScopes, $requestScopes)) {
        $response->getBody()->write("スコープが正しくありません。");
        return $response;
    }

    $renderer = new PhpRenderer(__DIR__ . '/../templates');

    $user = getLoginUser();
    if(!$user){
        return $renderer->render($response,
            "login.php",
            [
                'client_id' => $reqClientId,
                'scope' => $reqScopeStr,
                'response_type' => $reqResponseType ?? "",
                'redirect_uri' => $reqRedirectUri ?? "",
                'state' => $reqState ?? "",
            ]
        );
    } else {

        // クライアントからのリクエスト内容を保存
        $reqId = uniqid();
        setRequestData($reqId, $queryParams);

        return $renderer->render($response,
            "approve.php",
            [
                'username' => $user['name'] ?? ($user['name'] ?? ""),
                'client' => $client,
                'scope' => $requestScopes,
                'reqId' => $reqId,
            ]
        );
    }

});

// ユーザーからの連携可否
$app->post('/approve', function (Request $request, Response $response, $args)  {

    $queryParams = (array)$request->getParsedBody();

    $reqId = $queryParams['reqId'] ?? "";
    $reqScopes = $queryParams['scope'] ?? [];
    $isApprove = isset($queryParams['approve']);

    // クライアントサーバーへのリクエスト内容取得
    $reqData = getRequestData($reqId);
    delRequestData($reqId);

    // ログイン確認
    $user = getLoginUser();
    if(!$user){
        return returnRedirect($response, '/login', [
            'client_id' => $reqData['client_id'],
            'scope' => $reqScopes
        ]);
    }

    if($reqData === null){
        $response->getBody()->write("不正なリクエストです。やり直してください。");
        return $response;
    }

    $redirectUrl = $reqData['redirect_uri'] ?? null;

    if(!$redirectUrl){
        $renderer = new PhpRenderer(__DIR__ . '/../templates');
        return $renderer->render($response,
            "error.php",
            [
                'error' => "リダイレクト先が取得できませんでした。",
            ]
        );
    }

    if(!$isApprove){
        return returnRedirect($response, $redirectUrl, null, '連携が拒否されました。');
    }

    if($reqData['response_type'] !== 'code'){
        return returnRedirect($response, $redirectUrl, null, '連携方式がサポートされていません。');
    }

    // クライントサーバー情報の整合性チェック
    $client = getClient($reqData['client_id']);
    $clientScopes = $client['scope'] ? explode(" ", $client['scope']) : [];

    if(!hasAll($reqScopes, $clientScopes)){
        return returnRedirect($response, $redirectUrl, null, '連携する項目が異なります。');
    }

    // 認証コード生成し、クライントサーバーへリダイレクト
    $code = uniqid();

    $codeData= [
        'request' => $reqData,
        'scope' => $reqScopes,
        'user' => $user
    ];

    setCode($code, $codeData);

    return returnRedirect($response, $redirectUrl, [
        'code' => $code,
        'state' => $reqData['state']
    ]);

});

// クライントサーバーからのアクセストークン発行リクエスト
$app->post('/token', function (Request $request, Response $response, $args)  {

    $queryParams = (array)$request->getParsedBody();

    $reqGrantType = $queryParams['grant_type'] ?? "";
    $reqCode = $queryParams['code'] ?? "";
    $reqClientId = "";
    $reqClientSecret = "";
    // Basic認証
    if(!getBasic($request,$reqClientId, $reqClientSecret)){

        $reqClientId = $queryParams['client_id'] ?? null;
        $reqClientSecret =  $queryParams['client_secret'] ?? null;
        if(!$reqClientId || !$reqClientSecret){
            // error
            return returnJsonError($response, 'クライアント情報が不正です。', 401);
        }
    }

    // クライントサーバー情報取得
    $client = getClient($reqClientId);
    if(!$client){
        return returnJsonError($response, 'クライアント情報が不正です。', 401);
    }

    if($client['client_secret'] !== $reqClientSecret) {
        return returnJsonError($response, 'クライアント情報が不正です。', 401);
    }

    // 認証方式がauthorization_codeの場合、アクセストークン発行
    if($reqGrantType === 'authorization_code'){

        // ユーザーが許可リクエスト時に発行した情報と差異がないか確認
        $code = getCode($reqCode);
        if(!$code){
            return returnJsonError($response, 'クライアント情報が不正です。', 401);
        }

        $codeClientId = $code['request']['client_id'];
        $codeScope = $code['scope'];
        $scopeStr = implode(" ", $codeScope);
        $codeUser = $code['user'];

        if($codeClientId !== $reqClientId){
            return returnJsonError($response, 'クライアント情報が不正です。', 401);
        }

        delCode($reqCode);

        // アクセストークン、リフレッシュトークン作成
        $accessToken = uniqid();
        $refreshToken = uniqid();

        $email = $codeUser['email'];

        // トークン保存  ※リソースサーバーとデータ共有する
        registerAccessToken($codeClientId, $accessToken, $refreshToken, $scopeStr, $email);

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'scope' => $scopeStr
        ];

        // openIdに対応している場合、JWTでトークン作成
        if(in_array('openid', $codeScope) && $codeUser){
            // openid jwt
            $key = "example_key";
            $payload = array(
                "iss" => "http://localhost:8001",
                "sub" => $codeUser['sub'],
                "aud" => $codeClientId,
                "iat" => time(),
                "exp" => time() + (5 * 60),
            );

            $jwt = JWT::encode($payload, $key, 'HS256');

            $data['id_token'] = $jwt;

        }

        // クライントサーバーにトークン返却
        return returnJson($response, $data);

    }
    // 認証方式がrefresh_tokenの場合、リフレッシュトークン発行
    else if($reqGrantType === 'refresh_token'){

        $reqRefreshToken = $queryParams['refresh_token'] ?? null;

        if($reqRefreshToken === null){
            return returnJsonError($response, 'クライアント情報が不正です。', 400);
        }

        // リフレッシュトークンとクライアントIDに対応するデータ確認
        $result = selectAccessTokenByRefreshToken($reqRefreshToken, $client['client_id']);
        if(!$result) {
            return returnJsonError($response, 'クライアント情報が不正です。', 400);
        }

        // アクセストークン作成
        $accessToken = uniqid();

        //アクセストークン更新
        updateAccessToken($accessToken, $client['client_id']);

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $reqRefreshToken,
            'token_type' => 'Bearer',
        ];

        // クライントサーバーにトークン返却
        return returnJson($response, $data);

    }

});

// アクセストークン破棄リクエスト
$app->post('/revoke', function (Request $request, Response $response, $args) {

    $queryParams = (array)$request->getParsedBody();

    if(!getBasic($request,$reqClientId, $reqClientSecret)){

        $reqClientId = $queryParams['client_id'] ?? null;
        $reqClientSecret =  $queryParams['client_secret'] ?? null;
        if(!$reqClientId || !$reqClientSecret){
            // error
            return returnJsonError($response, 'クライアント情報が不正です。', 401);
        }
    }

    $client = getClient($reqClientId);
    if(!$client){
        return returnJsonError($response, 'クライアント情報が不正です。', 401);
    }

    if($client['client_secret'] !== $reqClientSecret) {
        return returnJsonError($response, 'クライアント情報が不正です。', 401);
    }

    if(!array_key_exists('token', $queryParams)){
        return returnJsonError($response, 'クライアント情報が不正です。', 400);
    }

    if("" === $accessToken = $queryParams['token']){
        return returnJsonError($response, 'クライアント情報が不正です。', 400);
    }

    // 保存済みのアクセストークンを削除
    deleteAccessToken($accessToken, $reqClientId);

    return returnJson($response, []);

});

// 動的クライアント登録
$app->post('/register', function (Request $request, Response $response, $args) {

    $dynamic_client = [];

    $queryParams = json_decode((string)$request->getBody(), true);

    if(array_key_exists('token_endpoint_auth_method', $queryParams)){
        $dynamic_client['token_endpoint_auth_method'] = $queryParams['token_endpoint_auth_method'];
    } else {
        $dynamic_client['token_endpoint_auth_method'] = 'secret_basic';
    }

    if(!in_array($dynamic_client['token_endpoint_auth_method'], ['secret_basic', 'secret_post', 'none'])){
        return returnJsonError($response, 'invalid client metadata', 400);
    }

    if(!array_key_exists('grant_types', $queryParams)){
        if(!array_key_exists('response_types', $queryParams)){
            $dynamic_client['grant_types'] = ['authorization_code'];
            $dynamic_client['response_types'] = ['code'];
        } else {
            $dynamic_client['response_types'] = $queryParams['response_types'];
            if(in_array('code', $dynamic_client['response_types'])){
                $dynamic_client['grant_types'] = ['authorization_code'];
            } else {
                $dynamic_client['grant_types'] = [];
            }

        }
    } else {
        if(!array_key_exists('response_types', $queryParams)){
            $dynamic_client['grant_types'] = $queryParams['grant_types'];
            if(in_array('authorization_code', $dynamic_client['grant_types'])){
                $dynamic_client['response_types'] = ['code'];
            } else {
                $dynamic_client['response_types'] = [];
            }
        } else {
            $dynamic_client['grant_types'] = $queryParams['grant_types'];
            $dynamic_client['response_types'] = $queryParams['response_types'];
            if(in_array('authorization_code', $dynamic_client['grant_types']) && !in_array('code', $dynamic_client['response_types']) ){
                $dynamic_client['response_types'][] = 'code';
            }
            if(!in_array('authorization_code', $dynamic_client['grant_types']) && in_array('code', $dynamic_client['response_types']) ){
                $dynamic_client['grant_types'][] = 'authorization_code';
            }
        }
    }

    if(count(without($dynamic_client['grant_types'], ['authorization_code', 'refresh_token'])) > 0
        || count(without($dynamic_client['response_types'], ['code'])) > 0
    ){
        return returnJsonError($response, 'クライアントメタデータが不正です。', 400);
    }

    if(!array_key_exists('redirect_uris', $queryParams)){
        return returnJsonError($response, 'リダイレクト先が不正です。', 400);
    } else {
        $dynamic_client['redirect_uris'] = $queryParams['redirect_uris'];
    }

    $dynamic_client['client_name'] = $queryParams['client_name'] ?? "";
    $dynamic_client['client_uri'] = $queryParams['client_uri'] ?? "";
    $dynamic_client['logo_uri'] = $queryParams['logo_uri'] ?? "";
    $dynamic_client['scope'] = $queryParams['scope'] ?? "";

    // クライアントID生成
    $dynamic_client['client_id'] = uniqid();
    if(in_array($dynamic_client['token_endpoint_auth_method'], ['secret_basic', 'secret_post'])
    ){
        $dynamic_client['client_secret'] = uniqid();
    }

    $dynamic_client['client_id_issued_at'] = time();
    $dynamic_client['client_secret_expires_at'] = time() + (5 * 60);

    // クライアント情報保存
    registerOAuthClient($dynamic_client);

    return returnJson($response, $dynamic_client, 201);

});


function delRequestData($reqId){
    $_SESSION['cli_requests'][$reqId] = null;
}

function setRequestData($reqId, $data){
    $_SESSION['cli_requests'][$reqId] = $data;
}

function getRequestData($reqId) {
    return $_SESSION['cli_requests'][$reqId] ?? null;
}

function delCode($codeId){
    $sql = "delete from codes where code_id = '${codeId}'";
    $db = new DatabaseHandler();
    $db->delete($sql);
}

function setCode($codeId, $data){

    $jsonData = json_encode($data);

    $sql = "select * from codes where code_id = '${codeId}'";
    $db = new DatabaseHandler();
    $results = $db->select($sql);
    if($results) {
        $sql = "update codes set data = '${jsonData}' where code_id = '${codeId}'";
        $db->update($sql);
    } else {
        $sql = "insert into codes(code_id, data) values('${codeId}','${jsonData}')";
        $db->insert($sql);
    }
}

function getCode($codeId) {
    $sql = "select code_id, data from codes where code_id = '${codeId}'";
    $db = new DatabaseHandler();
    $results = $db->select($sql);
    if($results) {
        return json_decode($results[0]['data'], true);
    } else {
        return null;
    }
}

function getClient($client_id) {

    if(OAUTH_CLIENT_TYPE !== 1){
        return CLIENTS_STATIC[$client_id] ?? null;
    }

    return getOAuthClient($client_id);

}

function getUser($email){
    $sql = "select * from users where email = '${email}'";
    $db = new DatabaseHandler();
    $result = $db->selectOne($sql);
    return $result ?? null;
}

function registerAccessToken($codeClientId, $accessToken, $refreshToken, $scopeStr, $email){

    $db = new DatabaseHandler();
    $sql = "insert into tokens('client_id', 'access_token', 'refresh_token', 'scope', 'user_email') values('${codeClientId}', '${accessToken}', '${refreshToken}', '${scopeStr}', '${email}') ";
    $db->insert($sql);
}

function updateAccessToken($accessToken, $clientId){
    $db = new DatabaseHandler();
    $sql = "update tokens set access_token = '${accessToken}' where client_id = '${clientId}'";
    $db->update($sql);
}

function deleteAccessToken($accessToken, $clientId){
    $db = new DatabaseHandler();
    $sql = "delete from tokens where access_token = '${accessToken}' and client_id = '${clientId}'";
    $db->delete($sql);
}

function selectAccessTokenByRefreshToken($refresh_token, $clientId){
    $db = new DatabaseHandler();
    $sql = "select * from tokens where refresh_token = '${refresh_token}' and client_id = '$clientId'";
    return $db->selectOne($sql);
}

function registerOAuthClient($data) {

    $client_id = $data['client_id'];
    $client_secret = $data['client_secret'];
    $client_id_issued_at = $data['client_id_issued_at'];
    $token_endpoint_auth_method = $data['token_endpoint_auth_method'];
    $client_name = $data['client_name'];
    $redirect_uris = implode(" ", $data['redirect_uris']);
    $client_uri = $data['client_uri'];
    $grant_types = implode(" ", $data['grant_types']);
    $response_types = implode(" ", $data['response_types']);
    $scope = $data['scope'];

    $db = new DatabaseHandler();

    if(getOAuthClientByClientUri($client_uri) === null){
        $sql = "insert into oauth_clients('client_id', 'client_secret', 'client_id_issued_at', 'token_endpoint_auth_method', 'client_name', 'redirect_uris', 'client_uri', 'grant_types', 'response_types', 'scope') values('${client_id}', '${client_secret}', '${client_id_issued_at}', '${token_endpoint_auth_method}', '${client_name}', '${redirect_uris}', '${client_uri}', '${grant_types}', '${response_types}', '${scope}') ";
        $db->insert($sql);
    } else {
        $sql = "update oauth_clients set client_id = '${client_id}', client_secret = '${client_secret}', client_id_issued_at = '${client_id_issued_at}', token_endpoint_auth_method = '${token_endpoint_auth_method}', client_name = '${client_name}', redirect_uris = '${redirect_uris}', client_uri = '${client_uri}', grant_types = '${grant_types}', response_types = '${response_types}', scope = '${scope}' where client_uri = '${client_uri}'";
        $db->update($sql);
    }

}

function getOAuthClient($clientId){
    $db = new DatabaseHandler();
    $sql = "select * from oauth_clients where client_id = '$clientId'";
    return $db->selectOne($sql);
}

function getOAuthClientByClientUri($clientUri){
    $db = new DatabaseHandler();
    $sql = "select * from oauth_clients where client_uri = '$clientUri'";
    return $db->selectOne($sql);
}

function setLoginUser($user){
    $_SESSION['loginUser'] = $user;
}

function getLoginUser(){
    if(isset($_SESSION['loginUser'])){
        return $_SESSION['loginUser'];
    } else {
        return null;
    }
}

$app->run();

//php -S localhost:8001 ./public/index.php
//php -dxdebug.mode=debug -dxdebug.client_port=9003 -dxdebug.client_host=127.0.0.1 -dxdebug.start_with_request=yes -S localhost:8001 ./public/index.php