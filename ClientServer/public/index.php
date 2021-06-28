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

// 認証サーバーの設定
const AUTH_SERVER = [
    'authorizationEndpoint' => 'http://localhost:8001/authorize',
    'tokenEndpoint' => 'http://localhost:8001/token',
    'revocationEndpoint' => 'http://localhost:8001/revoke',
    'registrationEndpoint' => 'http://localhost:8001/register',
];

//リソースサーバーの設定
const RESOURCE_SERVER = [
    'resourceEndpoint' => 'http://localhost:8002/resourceTest',
    'favoritesEndpoint' => 'http://localhost:8002/resource/favorites',
    'userInfoEndpoint' => 'http://localhost:8002/userinfo'
];

// 【静的】クライアント情報  クライアントサーバーと認証サーバーで事前に決めておく
const OAUTH_STATIC_CLIENT = [
    "client_id" => "oauth-client-1",
    "client_secret" => "oauth-client-secret-1",
    "redirect_uris" => ["http://localhost:8000/callback"],
    "scope" => "address email favorites name openid phone preferred_username",
];

// 【動的】クライアント情報設定用
const OAUTH_DYNAMIC_CLIENT = [
    "client_name" => "OAuth Dynamic Client",
    "client_uri" => "http://localhost:8000",
    "redirect_uris" => ["http://localhost:8000/callback"],
    "grant_types" => ["authorization_code"],
    "response_types" => ["code"],
    "token_endpoint_auth_method" => "secret_basic",
    "scope" => "address email favorites name openid phone preferred_username",
];

// 0: 静的, 1: 動的
const OAUTH_CLIENT_TYPE = 1;

// index             ・
$app->get('/', function (Request $request, Response $response, $args) {

    clearAll();

    return renderIndex($response, []);
});

// 認証サーバーへの認証リクエスト
$app->get('/authorize', function (Request $request, Response $response, $args) {

    clear();

    if(getOAuthClientData('client_id') === null){
        registerOAuthClient();
        if(getOAuthClientData('client_id') === null){
            return renderIndex($response, ['error' => '動的クライアント登録に失敗しました。']);
        }
    }

    // stateを保存 CSRF対策
    $state = uniqid();
    setSessionData('state', $state);

    $url = AUTH_SERVER['authorizationEndpoint'] . '?'. http_build_query([
            'response_type' => 'code',
            'client_id' => getOAuthClientData('client_id'),
            'scope' => getOAuthClientData('scope'),
            'redirect_uri' => getOAuthClientData('redirect_uris')[0],
            'state' => $state,
    ]);

    // 認証サーバーへリダイレクト
    return $response
        ->withHeader('Location', $url)
        ->withStatus(302);

});

// 認証サーバーで承認・拒否後リダイレクト
$app->get('/callback', function (Request $request, Response $response, $args) {

    $queryParams = $request->getQueryParams();
    $reqState = $queryParams['state'] ?? '';
    $reqCode = $queryParams['code'] ?? '';

    // 認証サーバーからエラーがあれば表示
    if(array_key_exists('error', $queryParams)){
        return renderIndex($response, ['error' => $queryParams['error']]);
    }

    // 認証サーバーにリダイレクトしたときのstateと戻ってきたときのstateが違えばエラー
    $clientState = getSessionData('state');
    if($reqState !== $clientState){
        return renderIndex($response, ['error' => "state error : " . $reqState . ", client state: " . $clientState]);
    }

    // 認証サーバーへへアクセストークン取得リクエスト
    $url = AUTH_SERVER['tokenEndpoint'];
    $reqParams = [
        'grant_type' => 'authorization_code',
        'code' => $reqCode,
        //'client_id' => $client['client_id'],
        //'client_secret' => $client['client_secret']
    ];
    $header = [
        'Authorization: Basic ' . base64_encode(getOAuthClientData('client_id') . ":" . getOAuthClientData('client_secret'))
    ];

    $result = postRequest($url, $reqParams, $header);

    if($result['status'] === '200' || $result['status'] < 300){
        //取得成功
        $access_token = $result['data']['access_token'] ?? null;
        $refresh_token = $result['data']['refresh_token'] ?? null;
        $scope = $result['data']['scope'] ?? null;

        // 認証サーバーから取得したアクセストークン、リフレッシュトークン、スコープを保存
        setSessionData('access_token', $access_token);
        setSessionData('refresh_token', $refresh_token);
        setSessionData('scope', $scope);

        // openidの場合JWT取得
        if(array_key_exists('id_token', $result['data'])){
            $key = "example_key";
            $jwt = $result['data']['id_token'];
            $decoded = JWT::decode($jwt, $key, array('HS256'));
            $payload = (array)$decoded;

            if($payload['iss'] == 'http://localhost:8001'){
                if($payload['aud'] == getOAuthClientData('client_id')){
                    $now = time();

                    if($payload['iat'] <= $now){
                        if($payload['exp'] >= $now){
                            setSessionData('id_token', $jwt);
                            setSessionData('payload', $payload);
                        }
                    }
                }
            }
        } else {
            setSessionData('id_token', null);
            setSessionData('payload', null);
        }


    } else {
        // authServerへリフレッシュトークン取得リクエスト
        $url = AUTH_SERVER['tokenEndpoint'];
        $reqParams = [
            'grant_type' => 'refresh_token',
            'code' => $reqCode,
            //'client_id' => OAUTH_CLIENT['client_id'],
            //'client_secret' => OAUTH_CLIENT['client_secret']
            'redirect_uri' => getOAuthClientData('redirect_uri')[0] ?? ""
        ];
        $header = [
            'Authorization: Basic ' . base64_encode(getOAuthClientData('client_id') . ":" . getOAuthClientData('client_secret'))
        ];

        $result = postRequest($url, $reqParams, $header);

        if($result['status'] === '200' || (int)$result['status'] < 300){
            //取得成功
            $access_token = $result['data']['access_token'];
            $refresh_token = $result['data']['refresh_token'];
            $scope = $result['data']['scope'];
            setSessionData('access_token', $access_token);
            setSessionData('refresh_token', $refresh_token);
            setSessionData('scope', $scope);
        } else {
            // 取得失敗
            setSessionData('access_token', "");
            setSessionData('refresh_token', "");
            setSessionData('scope', "");
            setSessionData('id_token', "");
            setSessionData('payload', "");
            return renderIndex($response, ['error' => "status error : " . $result['status']]);
        }

    }

    return renderIndex($response, []);

});

// リソースサーバーへのリクエスト
$app->get('/fetch_resource', function (Request $request, Response $response, $args) {

    if(!getOAuthClientData('client_id') === null){
        return renderIndex($response, ['error' => '動的クライアントが登録されていないか、期限切れです。']);
    }

    // 保存済みのアクセストーク取得
    $accessToken = getSessionData('access_token');

    if(!$accessToken){
        return renderIndex($response, ['error' => 'アクセストークンが存在しません。']);
    }

    $header = [
        'Authorization: Bearer ' . $accessToken
    ];

    // リソースサーバーへアクセスしてデータ取得
    $result = postRequest(RESOURCE_SERVER['resourceEndpoint'], [], $header);

    if($result['status'] === '200' || (int)$result['status'] < 300){
        return renderIndex($response, [
            'resource' => $result['data']['resource']['name'] ?? "",
        ]);
    } else {
        $error = $result['error'] ?? 'リソース取得に失敗しました。';
        return renderIndex($response, ['error' => $error]);
    }

});

// リソースサーバーへのリクエスト
$app->get('/favorites', function (Request $request, Response $response, $args) {

    if(!getOAuthClientData('client_id') === null){
        return renderIndex($response, ['error' => '動的クライアントが登録されていないか、期限切れです。']);
    }

    // 保存済みのアクセストーク取得
    $accessToken = getSessionData('access_token');

    if(!$accessToken){
        return renderIndex($response, ['error' => 'アクセストークンが存在しません。']);
    }

    $header = [
        'Authorization: Bearer ' . $accessToken
    ];

    // リソースサーバーへアクセスしてデータ取得
    $result = getRequest(RESOURCE_SERVER['favoritesEndpoint'], [], $header);

    if($result['status'] === '200' || (int)$result['status'] < 300){
        if(array_key_exists('error', $result)){
            return renderIndex($response, ['error' => $result['error']]);
        }
        if(!array_key_exists('data', $result)){
            return renderIndex($response, ['error' => 'リソース取得に失敗しました。']);
        }

        return renderIndex($response, [
            'favorites' => $result['data']['favorites'] ?? "",
        ]);
    } else {
        $error = $result['data']['error'] ?? 'リソース取得に失敗しました。';
        return renderIndex($response, ['error' => $error]);
    }

});

// リソースサーバーへのリクエスト
$app->get('/userinfo', function (Request $request, Response $response, $args) {

    if(!getOAuthClientData('client_id') === null){
        return renderIndex($response, ['error' => '動的クライアントが登録されていないか、期限切れです。']);
    }

    // 保存済みのアクセストーク取得
    $accessToken = getSessionData('access_token');

    if(!$accessToken){
        return renderIndex($response, ['error' => 'アクセストークンが存在しません。']);
    }

    $header = [
        'Authorization: Bearer ' . $accessToken
    ];

    // リソースサーバーへアクセスしてデータ取得
    $result = getRequest(RESOURCE_SERVER['userInfoEndpoint'], [], $header);

    if($result['status'] === '200' || (int)$result['status'] < 300){

        if(array_key_exists('error', $result)){
            return renderIndex($response, ['error' => $result['error']]);
        }
        if(!array_key_exists('data', $result)){
            return renderIndex($response, ['error' => 'リソース取得に失敗しました。']);
        }

        return renderIndex($response, [
            'userinfo' => $result['data']['userinfo'] ?? "",
        ]);
    } else {
        $error = $result['data']['error'] ?? 'リソース取得に失敗しました。';
        return renderIndex($response, ['error' => $error]);
    }

});

// アクセストークン破棄
$app->post('/revoke', function (Request $request, Response $response, $args) {

    if(!getOAuthClientData('client_id') === null){
        return renderIndex($response, ['error' => '動的クライアントが登録されていないか、期限切れです。']);
    }

    // 保存済みのアクセストーク取得
    $accessToken = getSessionData('access_token');

    if(!$accessToken){
        return renderIndex($response, ['error' => 'アクセストークンが存在しません。']);
    }

    $header = [
        'Authorization: Basic ' . base64_encode(getOAuthClientData('client_id') . ":" . getOAuthClientData('client_secret'))
    ];
    $reqParams = [
        'token' => $accessToken,
    ];
    // リソースサーバーへアクセス
    $result = postRequest(AUTH_SERVER['revocationEndpoint'], $reqParams, $header);

    setSessionData('access_token', null);
    setSessionData('refresh_token', null);
    setSessionData('scope', null);
    setSessionData('id_token', null);
    setSessionData('payload', null);

    if($result['status'] === '200' || (int)$result['status'] < 300){
        return renderIndex($response, []);
    } else {
        return renderIndex($response, ['error' => 'リソースサーバーへのリクエストに失敗しました。']);
    }
});


/**
 * リセット
 */
function clearAll(){

    $_SESSION = array();

    if (isset($_COOKIE["PHPSESSID"])) {
        setcookie("PHPSESSID", '', time() - 1800, '/');
    }

    session_destroy();

}

/**
 * アクセストークンなどリセット
 */
function clear(){
    unset($_SESSION['access_token']);
    unset($_SESSION['refresh_token']);
    unset($_SESSION['scope']);
    unset($_SESSION['id_token']);
    unset($_SESSION['payload']);
    unset($_SESSION['loginUser']);

    if(OAUTH_CLIENT_TYPE === 1) {
        unset($_SESSION['oauth_client_dynamic']);
    }
}


/**
 * 画面表示
 * @param $response
 * @param $values
 * @return Response
 * @throws Throwable
 */
function renderIndex($response, $values){

    $payload = "";
    if(array_key_exists('payload', $values) && $values['payload'] !== ""){
        $payload = json_encode($values['payload']);
    } elseif ((getSessionData('payload') ?? "") !== "") {
        $payload = json_encode(getSessionData('payload'));
    }

    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    return $renderer->render($response,
        "index.php",
        [
            'access_token' => $values['access_token'] ?? (getSessionData('access_token') ?? ""),
            'refresh_token' => $values['refresh_token'] ?? (getSessionData('refresh_token') ?? ""),
            'scope' => $values['scope']  ?? (getSessionData('scope') ?? ""),
            'resource' => $values['resource'] ?? "",
            'id_token' => $values['id_token'] ?? (getSessionData('id_token') ?? ""),
            'payload' => $payload,
            'favorites' => $values['favorites'] ?? [],
            'userinfo'  => $values['userinfo'] ?? [],
            'error' => $values['error'] ?? ""
        ]
    );
}

/**
 * 動的クライアント登録
 * @return string
 * @throws Exception
 */
function registerOAuthClient(){

    $reqParams = OAUTH_DYNAMIC_CLIENT;

    $result = postRequest(AUTH_SERVER['registrationEndpoint'], $reqParams, [], true);

    $data = $result['data'];

    if($result['status'] === '200' || (int)$result['status'] < 300){
        setOAuthClientData("client_id", $data['client_id'] ?? null);
        setOAuthClientData("client_name", $data['client_name'] ?? null);
        setOAuthClientData("client_secret", $data['client_secret'] ?? null);
        setOAuthClientData("client_id_created_at", $data['client_id_created_at'] ?? null);
        setOAuthClientData("client_secret_expires_at", $data['client_secret_expires_at'] ?? null);
        setOAuthClientData("client_uri", $data['client_uri'] ?? null);
        setOAuthClientData("token_endpoint_auth_method", $data['token_endpoint_auth_method'] ?? null);
        setOAuthClientData("grant_types", $data['grant_types'] ?? null);
        setOAuthClientData("response_types", $data['response_types'] ?? null);
        setOAuthClientData("redirect_uris", $data['redirect_uris'] ?? null);
        setOAuthClientData("scope", $data['scope'] ?? null);
        setOAuthClientData("logo_uri", $data['logo_uri'] ?? null);

        return "";
    } else {
        return "error";
    }
}

/**
 * OAuthクライアント情報取得
 * @param $name
 * @return mixed|null
 */
function getOAuthClientData($name){

    if(OAUTH_CLIENT_TYPE !== 1) {
        return OAUTH_STATIC_CLIENT[$name] ?? null;
    } else {
        if(!isset($_SESSION['oauth_client_dynamic'])){
            return null;
        }

        $now = time();
        $exp = $_SESSION['oauth_client_dynamic']['client_secret_expires_at'] ?? 0;
        if($now > $exp) {
            return null;
        }
        return $_SESSION['oauth_client_dynamic'][$name] ?? null;
    }
}

/**
 * OAuthクライアント情報設定
 * @param $name
 * @param $data
 * @throws Exception
 */
function setOAuthClientData($name, $data){
    if(OAUTH_CLIENT_TYPE !== 1) {
        throw new \Exception("OAUTH_CLIENT_TYPE is not dynamic");
    } else {
        $_SESSION['oauth_client_dynamic'][$name] = $data;
    }
}

$app->run();

//php -S localhost:8000 ./public/index.php
//php -dxdebug.mode=debug -dxdebug.client_port=9003 -dxdebug.client_host=127.0.0.1 -dxdebug.start_with_request=yes -S localhost:8000 ./public/index.php