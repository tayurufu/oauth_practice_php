<html>
<body>
<h1>OAuthテスト</h1>
<ul>
    <?php if(isset($error) && $error !== ""): ?>
    <li>error => <?=$error?></li>
    <?php endif; ?>
    <li>access_token => <?=$access_token?></li>
    <li>refresh_token => <?=$refresh_token?></li>
    <li>scope => <?=$scope?></li>
    <li>id_token => <?=$id_token?></li>
    <li>payload => <?=$payload?></li>
</ul>


<div>
    <ul>
        <li>resource => <?=$resource?></li>
    </ul>
</div>
<div>
    <div>favorites</div>
    <ul>
        <?php foreach($favorites as $key => $value): ?>
            <li><?=$value?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div>
    <div>ユーザー情報取得</div>
    <ul>
        <?php foreach($userinfo as $key => $value): ?>
            <li><?=$key?> : <?=$value?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div>
    <button type="button" name="authorize" onclick="location.href='/authorize'">アクセストークン取得</button>
    <br>
    <button type="button" name="fetch_resource" onclick="location.href='/fetch_resource'">リソース取得</button>
    <br>
    <button type="button" name="get_favorites" onclick="location.href='/favorites'">favorites取得</button>
    <br>
    <button type="button" name="get_userinfo" onclick="location.href='/userinfo'">ユーザー情報取得</button>
    <br>
    <form name="" action="revoke" method="post">
        <button type="submit" name="revoke">アクセストークン破棄</button>
    </form>
    <br>
    <button type="button" name="reset" onclick="location.href='/'">リセット</button>
</div>
<div>

</div>
</body>
</html>
