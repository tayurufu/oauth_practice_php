# OAuth Practice for PHP  
「OAuth徹底入門 セキュアな認可システムを適用するための原則と実践」 Justin Riche著  
のサンプルシステムをphpで再現しました。  

## 動作環境  
php7.4  
sqlite3  

## 準備
データベースの初期設定  
```php ./Database/refresh_db.php ```  

ClientServerディレクトリで以下を実行  
```composer install```  
```php -S localhost:8001 ./public/index.php```  

AuthServerディレクトリで以下を実行  
```composer install```  
```php -S localhost:8002 ./public/index.php```  

ResourceServerディレクトリで以下を実行  
```composer install```  
```php -S localhost:8003 ./public/index.php```  

