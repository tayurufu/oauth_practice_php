<?php

class DatabaseHandler {

    private $db = null;

    private string $db_path = __DIR__ . DIRECTORY_SEPARATOR . 'oauth_practice.db3';

    public function open(){
        $this->db = new SQLite3($this->db_path);
    }

    public function close(){
        if($this->db !== null) {
            $this->db->close();
        }
    }

    public function select($sql) :?array {

        try{
            $this->open();

            $ret = [];
            $results = $this->db->query($sql);
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                //var_dump($row);
                $ret[] = $row;
            }

            $this->close();

            return $ret;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }

    }

    public function selectOne($sql) {

        try{
            $this->open();

            $results = $this->db->query($sql);
            $row = $results->fetchArray(SQLITE3_ASSOC);
            if($row === false){
                $row = null;
            }
            $this->close();

            return $row ?? null;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }

    }

    public function insert($sql){
        try{
            $this->open();

            $result = $this->db->exec($sql);

            $this->close();

            return $result;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }
    }

    public function update($sql){
        try{
            $this->open();

            $result = $this->db->exec($sql);

            $this->close();

            return $result;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }
    }

    public function delete($sql){
        try{
            $this->open();

            $result = $this->db->exec($sql);

            $this->close();

            return $result;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }
    }

    public function exec($sql){
        try{
            $this->open();

            $result = $this->db->exec($sql);

            $this->close();

            return $result;

        } catch(Exception $e) {
            $this->close();
            throw $e;
        }
    }

}