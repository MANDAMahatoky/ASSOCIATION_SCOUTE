<?php

function getDB() {
    try {
        $db = new PDO("mysql:host=localhost;dbname=association_scoute", "manda", "1234");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Erreur connexion DB : " . $e->getMessage());
    }
}
