<?php
class SessionManager implements SessionHandlerInterface {
    private PDO $pdo;
    private int $duree = 3600;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function open($path, $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->pdo->prepare("
            SELECT donnees FROM session
            WHERE id_session = ?
            AND date_expiration > NOW()
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
       //DEBUG 
	file_put_contents('/tmp/session_read.txt',
        "READ ID: $id\nRESULT: " . print_r($result, true) . "\n---\n",
        FILE_APPEND
   	 );
	 return $result ? $result['donnees'] : '';
    }

    public function write($id, $data): bool {
        // $data est fourni par PHP — c'est la chaîne sérialisée de $_SESSION
        // On ne touche PAS à $_SESSION ici
        if(empty($data)){
		return true;
	}
	//$expiration = date('Y-m-d H:i:s', time() + $this->duree);

        // Extraire id_membre depuis $data sans toucher $_SESSION
        $id_membre = null;
        if (preg_match('/utilisateur\|a:.*?"id_membre";i:(\d+)/', $data, $m)) {
            $id_membre = (int) $m[1];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO session (id_session, id_membre, donnees, date_expiration)
            VALUES (?, ?, ?, NOW()+ INTERVAL '1 hour')
            ON CONFLICT (id_session)
            DO UPDATE SET
                id_membre       = EXCLUDED.id_membre,
                donnees         = EXCLUDED.donnees,
                date_expiration = EXCLUDED.date_expiration
        ");
        return $stmt->execute([$id, $id_membre, $data]);
    }

    public function destroy($id): bool {
        $this->pdo->prepare("
            DELETE FROM session WHERE id_session = ?
        ")->execute([$id]);
        return true;
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->pdo->prepare("
            DELETE FROM session WHERE date_expiration < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
