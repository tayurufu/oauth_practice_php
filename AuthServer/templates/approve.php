<html>
<body>
<h1>hello  <?=$username?></h1>

<div>
    <div>OAuthクライアント情報</div>
    <ul>
        <?php foreach($client as $key => $value): ?>
            <li><?=$key?> : <?=$value?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div>
    <form class="form" action="/approve" method="POST">

        <p>許可する項目を選択してください</p>
        <ul>
            <?php foreach($scope as  $value): ?>
                <li>
                    <input type="checkbox" name="scope[]" id="scope_<?=$value?>" checked="checked" value="<?=$value?>">
                    <label for="scope_<?=$value?>"><?=$value?>
                </li>
            <?php endforeach; ?>
        </ul>
        <input type="submit" class="btn btn-success" name="approve" value="選択した項目を許可">
        <input type="submit" class="btn btn-danger" name="deny" value="すべて拒否">
        <input type="hidden" name="reqId" value="<?=$reqId?>">
    </form>
</div>

</body>
</html>
