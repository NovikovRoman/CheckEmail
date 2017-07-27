<?php

namespace CheckEmail;

class CheckEmail
{
    const EOT = "\r\n";
    const DEFAULT_WEIGHT = 10;
    const PORT = 25;
    private $email;
    private $localhost;
    private $sender;
    private $connection;
    private $timeoutConnect = 15;
    private $timeoutStream = 15;
    private $domainsExcluded = [];
    private $domainsTemporary = [];
    private $logs = [];
    private $debug = false;
    private $arAlgorithm = [];

    public function __construct($email, $localhost = '')
    {
        $this->localhost = 'gmail.com';
        if ($localhost) {
            $this->localhost = $localhost;
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $this->localhost = $_SERVER['HTTP_HOST'];
        }
        $this->email = $email;
        $this->sender = 'robot@' . $this->localhost;
        $this->buildAlgorithm();
    }

    /**
     * @param string|array $domains
     * @return $this
     */
    public function addDomainTemporary($domains)
    {
        if (!is_array($domains)) {
            $domains = [$domains];
        }
        $this->domainsTemporary = array_unique(array_merge($this->domainsTemporary, $domains));
        return $this;
    }

    /**
     * @param string|array $domains
     * @return $this
     */
    public function addDomainExcluded($domains)
    {
        if (!is_array($domains)) {
            $domains = [$domains];
        }
        $this->domainsExcluded = array_unique(array_merge($this->domainsExcluded, $domains));
        return $this;
    }

    public function setSender($sender)
    {
        $this->sender = $sender;
        $this->buildAlgorithm();
        return $this;
    }

    public function setDebug()
    {
        $this->logs = [];
        $this->debug = true;
        return $this;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function check()
    {
        $this->logs = [];
        if (!function_exists('fsockopen')) {
            $this->saveLog('fsockopen not found');
            return false;
        }
        if (!$this->isValidEmail()) {
            $this->saveLog('invalid email ' . $this->email);
            return false;
        }
        $host = preg_replace('/^(.+?@)/sui', '', $this->email);
        if ($this->isDomainExcluded($host)) {
            $this->saveLog('excluded domain ' . $host);
            return false;
        }
        $mxHosts = [];
        $mxWeight = [];
        if (getmxrr($host, $mxHosts, $mxWeight) === true) {
            array_multisort($mxHosts, $mxWeight);
        } else {
            $mxHosts = [$host . '.'];
            $mxWeight = [self::DEFAULT_WEIGHT];
        }
        $this->saveLog(
            json_encode([$mxHosts, $mxWeight], JSON_UNESCAPED_UNICODE)
        );
        foreach ($mxHosts as $step => $host) {
            if (!$this->isDomainTemporary($host)) {
                $this->saveLog('excluded mxdomain ' . $host);
                continue;
            }
            $this->saveLog('step ' . $step . ': ' . $host);
            if (empty($host) || $host == '0.0.0.0') {
                $this->saveLog($host . ': invalid value');
                continue;
            }
            $this->connection = fsockopen($host, self::PORT, $errno, $error, $this->timeoutConnect);
            if (!$this->connection) {
                $this->saveLog($host . ': no connection');
                continue;
            }
            $result = $this->getResult();
            fputs($this->connection, 'QUIT' . self::EOT);
            fclose($this->connection);
            if ($result) {
                return true;
            }
        }
        return false;
    }

    private function getResult()
    {
        stream_set_timeout($this->connection, $this->timeoutStream);
        $result = false;
        foreach ($this->arAlgorithm as $msg) {
            $result = $this->sendMessage($msg);
            $info = stream_get_meta_data($this->connection);
            if (!$result || $info['timed_out']) {
                break;
            }
        }
        return $result;
    }

    private function buildAlgorithm()
    {
        $this->arAlgorithm = [
            'HELO ' . $this->localhost . self::EOT,
            'MAIL FROM:<' . $this->sender . '>' . self::EOT,
            'RCPT TO:<' . $this->email . '>' . self::EOT,
            'data' . self::EOT,
        ];
        return $this;
    }

    private function sendMessage($msg)
    {
        fputs($this->connection, $msg);
        $data = fgets($this->connection, 1024);
        $this->saveLog($data);
        return mb_substr($data, 0, 1) == '2';
    }

    private function isValidEmail()
    {
        $pattern = '(?:[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])';
        return preg_match('/' . $pattern . '/', $this->email);
    }

    private function isDomainExcluded($domain = '')
    {
        return in_array(strtolower($domain), $this->domainsExcluded);
    }

    private function isDomainTemporary($domain)
    {
        foreach ($this->domainsTemporary as $item) {
            if (preg_match('/(\s|\.)' . preg_quote($item, '/') . '$/ui', $domain)) {
                return false;
            }
        }
        return true;
    }

    private function saveLog($msg)
    {
        if ($this->debug) {
            $this->logs[] = $msg;
        }
        return $this;
    }
}