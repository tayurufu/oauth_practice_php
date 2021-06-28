<?php
declare(strict_types=1);

/**
 * POST送信
 * @param $url
 * @param $param
 * @param $header
 * @param false $jsonRequest JSONで送信する場合はtrue
 * @return array ['status' => ステータスコード, 'data' => 返却値]
 */
function postRequest($url, $param, $header, $jsonRequest = false): array
{

    $postHeader = [];
    if($jsonRequest){
        $postHeader[] = "Content-Type: application/json";
        $postHeader[] = "Accept: application/json";
    } else {
        $postHeader[] = 'Content-type: application/x-www-form-urlencoded';
    }

    $postHeader = array_merge($postHeader, $header);

    $options = [
        'http' => [
            'ignore_errors' => true,
            'header' => implode("\r\n", $postHeader),
            'method' => 'POST',
            'content' =>  $jsonRequest ?  json_encode($param) : http_build_query($param)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
    $status_code = $matches[1];

    $data = json_decode($result, true);

    if($data === false || $data === null){
        return [
            'status' => $status_code,
            'error' => (string)$result
        ];
    }

    return [
        'status' => $status_code,
        'data' => $data
    ];
}

function getRequest($url, $param, $header): array
{
    $getUrl = $url;
    $headers = [];

    $headers[] = "Accept: application/json";


    $headers = array_merge($headers, $header);

    if(count($param) > 0){
        $getUrl = $getUrl . '?'. http_build_query($param);
    }

    $options = [
        'http' => [
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'method' => 'GET',
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($getUrl, false, $context);

    preg_match('/HTTP\/1\.[0|1|x] ([0-9]{3})/', $http_response_header[0], $matches);
    $status_code = $matches[1];

    $data = json_decode($result, true);

    if($data === false || $data === null){
        return [
            'status' => $status_code,
            'error' => (string)$result
        ];
    }

    return [
        'status' => $status_code,
        'data' => $data
    ];
}

/**
 * セッションへデータ格納
 * @param $key
 * @return mixed|null
 */
function getSessionData($key){

    return $_SESSION[$key] ?? null;
}

//
/**
 * セッションからデータ取得
 * @param $key
 * @param $newState
 */
function setSessionData($key, $newState){

    $_SESSION[$key] = $newState;
}

/**
 * json返却
 * @param $response
 * @param $data
 * @param int $status
 * @return mixed
 */
function returnJson($response, $data, $status = 200){
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

/**
 * jsonでエラー返却
 * @param $response
 * @param $errorMessage
 * @param $status
 * @return mixed
 */
function returnJsonError($response, $errorMessage, $status){

    $data = [
        'error' => $errorMessage,
    ];
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')->withStatus($status);
}

/**
 * リダイレクト
 * @param $response
 * @param $redirectUrl
 * @param null $data
 * @param null $errorMessage
 * @return mixed
 */
function returnRedirect($response, $redirectUrl, $data = null, $errorMessage = null){

    $queries = [];

    if($errorMessage){
        $queries['error'] = $errorMessage;
    }

    if($data){
        $queries = array_merge($queries, $data);
    }

    $redirectUrl .= '?' . http_build_query($queries);

    return $response
        ->withHeader('Location', $redirectUrl)
        ->withStatus(302);
}
/*
function convertStringToArray($str){
    if($str === null || $str === ""){
        return [];
    } else {
        return explode(" ", $str);
    }
}*/


/**
 * $arrayから$valuesを除く
 * @param $array array
 * @param $values array
 * @return array
 */
function without($array, $values): array
{
    return array_filter($array, function($e) use($values){
        return !in_array($e, $values);
    });
}

/**
 * 配列が同値か確認
 * @param $arr1
 * @param $arr2
 * @return bool
 */
function checkArray($arr1, $arr2){
    if(count($arr1) === 0 || count($arr2) === 0) {
        return false;
    }

    if(count($arr1) <> count($arr2)) {
        return false;
    }

    foreach ($arr1 as $data1){
        if(!in_array($data1, $arr2)){
            return false;
        }
    }

    return true;

}

/**
 * $arr2が$arr1の要素をすべて持っているか
 * @param $arr1
 * @param $arr2
 * @return bool
 */
function hasAll($arr1, $arr2){

    foreach ($arr1 as $data1){
        if(!in_array($data1, $arr2)){
            return false;
        }
    }

    return true;
}


// Basic認証
function getBasic(&$request, &$reqClientId, &$reqClientSecret){
    $authHeader =  $request->getHeader('Authorization');

    if($authHeader === null || count($authHeader) === 0){
        return false;
    }
    $basic = strstr($authHeader[0], 'Basic ');

    if($basic === false){
        return false;
    }
    $basic = explode(' ', $basic)[1];
    $baseClient = base64_decode($basic) ?? null;
    if($baseClient === null){
        return false;
    }

    $baseClient = explode(':', $baseClient);
    $reqClientId = $baseClient[0];
    $reqClientSecret = $baseClient[1] ?? "";

    return true;
}