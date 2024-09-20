// quiz_server.php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require __DIR__ . '/vendor/autoload.php';

class QuizServer implements MessageComponentInterface {
    protected $clients;
    protected $userScores;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userScores = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->userScores[$conn->resourceId] = 0;
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $answer = intval($msg);
        $question = $this->generateQuestion();
        $correctAnswer = eval('return ' . $question . ';');

        if ($answer === $correctAnswer) {
            $this->userScores[$from->resourceId]++;
            $this->broadcastScores();
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->userScores[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function generateQuestion() {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operator = ['+', '-'][rand(0, 1)];
        return "{$num1} {$operator} {$num2}";
    }

    private function broadcastScores() {
        $scores = $this->userScores;
        foreach ($this->clients as $client) {
            $client->send(json_encode($scores));
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new QuizServer()
        )
    ),
    8080
);

$server->run();
