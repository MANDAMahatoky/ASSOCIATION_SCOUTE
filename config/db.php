<?php
function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO(
                "pgsql:host=localhost;dbname=miray393",
                "manda",
                "1234"
            );
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
            die("Erreur connexion DB : " . $e->getMessage());
        }
    }
    return $db;
}
