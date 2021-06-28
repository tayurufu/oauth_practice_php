<html>
<body>
<h1>
    ログイン
</h1>
<div>
    <form action="/login" method="post">
    <label>Select user:</label>
    <select name="user">
        <option value="alice.wonderland@example.com">Alice</option>
        <option value="bob.loblob@example.net">Bob</option>
    </select>
    <button type="submit">ログイン</button>
        <input type="hidden" name ="client_id" value="<?=$client_id?>">
        <input type="hidden" name ="scope" value="<?=$scope?>">
        <input type="hidden" name ="response_type" value="<?=$response_type?>">
        <input type="hidden" name ="redirect_uri" value="<?=$redirect_uri?>">
        <input type="hidden" name ="state" value="<?=$state?>">
    </form>
</div>
</body>
</html>