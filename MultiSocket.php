<?php

namespace MultiSocketTask;

require_once "Utils.php";

use MultiSocketTask\Utils;

set_time_limit(0);

class MultiSocket {
    private $utils;
    private $address;
    private $port;
    private $socket;
    private $logPath;
    private $clients = [];

    const WELCOME_MSG = <<<EOF
Welcome to Avi's MultiSocket implentation!

EOF;
    const MAIN_MENU = <<<EOF

Main Menu
---------
1. Get total disk space
2. Get Google DNS ping average
3. Top 5 Google results
4. Exit

EOF;
    const RESULTS_LIMIT = 5;
    const SECRET = "294db25a677c9b8b563e179477d9385b";

    public function __construct($address, $port, $logPath = "/tmp/multisocket.log") {
        $this->utils = Utils::getInstance($logPath);
        $this->address = $address;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $this->logPath = $logPath;
        $this->shutDownStarted = false;
    }

    public function __destruct() {
        $this->shutdown();
    }

    public function listen() {
        socket_bind($this->socket, $this->address, $this->port) || die("Could not bind to address {$this->address}:{$this->port}");
        socket_listen($this->socket);
        socket_set_nonblock($this->socket);
        $this->utils->log("Listening on {$this->address}:{$this->port}");
        while (true) {
            if ($newsock = socket_accept($this->socket)) {
                if (is_resource($newsock)) {
                    if (false !== socket_write($newsock, self::WELCOME_MSG . self::MAIN_MENU . chr(0))) {
                        socket_set_nonblock($newsock);
                        $this->clients[] = $newsock;
                        $clientsCount = count($this->clients);
                        $this->utils->log("New client has connected. Total clients: {$clientsCount}");
                    }
                }
            }
            foreach ($this->clients as $idx => $clientsock) {
                $input = "";
                if ($char = socket_read($clientsock, 1024)) {
                    $input .= $char;
                }
                switch (trim($input)) {
                    case "1":
                        $this->showDiskSpace($idx);
                        $this->showMain($idx);
                        break;
                    case "2":
                        $this->showPingAvg($idx, "8.8.8.8");
                        $this->showMain($idx);
                        break;
                    case "3":
                        $this->requestSearch($idx);
                        $this->showMain($idx);
                        break;
                    case "4":
                        $this->disconnect($idx);
                        break;
                    case "":
                        break;
                    default:
                        if (md5(trim($input)) == "294db25a677c9b8b563e179477d9385b") {
                            $this->shutdown();
                            break 3;
                        }
                        $this->write($idx, "Unknown command");
                        break;
                }
            }

            sleep(1);
        }
    }

    private function showMain($idx) {
        $this->write($idx, self::MAIN_MENU);
    }

    private function showDiskSpace($idx) {
        $this->utils->log("Client #{$idx}: showDiskSpace");
        $this->write($idx, "Total disk space: {$this->utils->getFreeDiskSpace()}");
    }

    private function showPingAvg($idx, $host) {
        $this->utils->log("Client #{$idx}: showPingAvg({$host})");
        if (($avg = $this->utils->getPingAvg($host)) !== false) {
            $this->write($idx, "{$host} ping avg: {$avg}ms");
        }
    }

    private function requestSearch($idx) {
        $this->write($idx, "Search the web for: ", false);
        $input = "";
        while (true) {
            if ($char = socket_read($this->clients[$idx], 1024)) {
                $input .= $char;
            }
            $input = trim($input);
            if ($input) {
                $this->utils->log("Client #{$idx}: searchFor({$input})");
                $this->showSearchResults($idx, $this->utils->getGoogleResults(urlencode($input),self::RESULTS_LIMIT));
                break;
            }

            sleep(1);
        }
    }

    private function showSearchResults($id, $searchResults) {
        if (count($searchResults)) {
            $msg = "====================\r\n";
            foreach ($searchResults as $result) {
                $msg .= "{$result['title']}\r\n";
                $msg .= "{$result['url']}\r\n";
                $msg .= "\r\n{$result['desc']}\r\n";
                $msg .= "====================\r\n";
            }
            $this->write($id, $msg);
        }
    }

    private function disconnect($idx) {
        $this->utils->log("Client #{$idx}: disconnect");
        $this->write($idx, "Bye!");
        socket_close($this->clients[$idx]);
        unset($this->clients[$idx]);
    }

    private function shutdown() {
        if (!$this->shutDownStarted) {
            $this->shutDownStarted = true;
            $this->utils->log("SHUTDOWN");
            foreach ($this->clients as $idx => $clientsock) {
                $this->disconnect($idx);
            }
            socket_close($this->socket);
        }
    }

    private function write($id, $msg, $addEOL = true) {
        if ($addEOL) {
            $msg .= "\r\n";
        }
        socket_write($this->clients[$id], $msg.chr(0));
    }
}

$socket = new MultiSocket('127.0.0.1', 1111);
$socket->listen();
