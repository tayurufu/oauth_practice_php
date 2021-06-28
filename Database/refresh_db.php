<?php

require __DIR__ . '/databaseHandler.php';

function main(){
    $db = new DatabaseHandler();
    $sql = "drop table if exists tokens";
    $db->exec($sql);

    $sql = "drop table if exists codes";
    $db->exec($sql);

    $sql = "drop table if exists users";
    $db->exec($sql);

    $sql = "drop table if exists oauth_clients";
    $db->exec($sql);

    $sql = "drop table if exists favorites";
    $db->exec($sql);

    $sql = "VACUUM";
    $db->exec($sql);


    $sql = "create table tokens(client_id text, access_token text, refresh_token text, scope text, user_email text )";
    $db->exec($sql);

    $sql = "create table codes(code_id text, data text)";
    $db->exec($sql);

    $sql = "create table users(email text, name text, preferred_username text, password text, sub text, email_verified text, address text, phone text)";
    $db->exec($sql);

    $sql = "insert into users(email, name, preferred_username, password, sub, email_verified, address, phone) values('alice.wonderland@example.com', 'Alice', 'alice', 'password', '9XE3-JI34-00132A', '1', 'america 1-1-1', '111-111-1111')";
    $db->exec($sql);

    $sql = "insert into users(email, name, preferred_username, password, sub, email_verified, address, phone) values('bob.loblob@example.net', 'Bob', 'bob', 'password', '1ZT5-OE63-57383B', '0', 'japan 2-2-2', '222-222-2222')";
    $db->exec($sql);

    $sql = "create table oauth_clients(client_id text, client_secret text, client_id_issued_at text, token_endpoint_auth_method text, client_name text, redirect_uris text, client_uri text, grant_types text, response_types text, scope text)";
    $db->exec($sql);

    $sql = "create table favorites(email text, item text )";
    $db->exec($sql);

    $sql = "insert into favorites(email, item) values('alice.wonderland@example.com', '映画')";
    $db->exec($sql);

    $sql = "insert into favorites(email, item) values('alice.wonderland@example.com', '食事')";
    $db->exec($sql);

    $sql = "insert into favorites(email, item) values('bob.loblob@example.net', 'サッカー')";
    $db->exec($sql);

}


main();