<?php

namespace MultiSocketTask;

use DOMDocument;
use DOMXPath;

class Utils {
    const PING_COUNT = 3;

    private static $instance = null;
    private $os;
    private $logPath;

    private function __construct($logPath) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") {
            $this->os = "Windows";
        }
        elseif (strtoupper(PHP_OS) === "DARWIN") {
            $this->os = "macOS";
        }
        else {
            $this->os = "Linux";
        }
        $this->logPath = $logPath;
    }

    public function getInstance($logPath) {
        if (self::$instance === null) {
            self::$instance = new Utils($logPath);
        }

        return self::$instance;
    }

    public function getOS() {
        return $this->os;
    }

    public function getFreeDiskSpace() {
        $path = $this->os === 'Windows' ? "C:" : "/";
        return $this->getHumanReadable(disk_free_space($path));
    }

    public function getHumanReadable($bytes) {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes)-1) / 3);
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . $size[$factor];
    }

    public function getPingAvg($host) {
        switch ($this->os) {
            case "Windows":
                $pingCmd = sprintf("ping -n %s %s", self::PING_COUNT, $host);
                break;
            case "macOS":
                $pingCmd = sprintf("ping -n -c %s %s", self::PING_COUNT, $host);
                break;
            default:
                $pingCmd = sprintf("ping -n -c %s %s", self::PING_COUNT, $host);
        }
        $result = explode("=", exec($pingCmd));
        $labels = explode("/", trim($result[0]));
        $values = explode("/", trim($result[1]));
        for ($i = 0; $i < count($labels); $i++) {
            if ($labels[$i] == "avg") {
                return $values[$i];
            }
        }
        return false;
    }

    public function getGoogleResults($query, $limit) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.google.com/search?q={$query}");
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        $results = [];
        if (($googleResponse = curl_exec($ch)) !== false) {
            $results = self::parseGoogleResults($googleResponse, $limit);
        }
        curl_close($ch);
        return $results;
    }

    public function parseGoogleResults($htmlString, $limit) {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $htmlString, LIBXML_NOWARNING|LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $query = "//div[contains(@class, 'rc')]/div[position() = 1]/a";
        $links = $xpath->query($query);
        $query = "//div[contains(@class, 'rc')]/div/a/h3";
        $titles = $xpath->query($query);
        $query = "//div[contains(@class, 'rc')]/div[position() = 2]//span//span";
        $descs = $xpath->query($query);

        $result = [];
        for ($i = 0; $i < $limit; $i++) {
            $result[] = [
                'url' => $links[$i]->getAttribute('href'),
                'title' => $titles[$i]->textContent,
                'desc' => $descs[$i]->textContent
            ];
        }
        return $result;
    }

    public function log($msg) {
        $msg = sprintf("[%s] $msg" . PHP_EOL, gmdate("Y-m-d H:i:s"));
        @file_put_contents($this->logPath, $msg, FILE_APPEND);
    }
}
