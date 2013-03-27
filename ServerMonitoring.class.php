<?php

/**
* ServerMonitoring
*
* PING, ICMP, Web monitoring class
*
* @author Prasetyo Wicaksono <prasetyo@nanmit.es>
* @license DBAD <http://dbad-license.org/license>
* @link https://github.com/Atriedes/server-monitoring-php
* @link http://us3.php.net/manual/en/function.socket-create.php#80775
* @link http://css-tricks.com/snippets/php/check-if-website-is-available/
*/

Class ServerMonitoring
{
    private $ip = '';
    private $host = '';

    // ping variable
    private $icmpSocket;
    private $request;
    private $requestLen;
    private $reply;
    private $time;
    private $timerStartTime;

    // time config
    private $percision = 3;

    /**
     * Class constructor
     *
     * @access  public
     * @param   string
     * @return  object
     */
    function __construct($host = '')
    {
        try {
           $this->ip = $this->validateHost($host);
           $this->host = $host;
        } catch(Exception $e)
        {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Validate hostname
     *
     * @access  private
     * @param   string
     * @return  string
     */
    private function validateHost($host)
    {
        // host is IP?
        if (!preg_match('/^([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])$/', $host)) {
            // get IP for given host name
            $ip = gethostbyname($host);
            if ($ip === $host){
                 throw new Exception('Invalid host');
                 return FALSE;
            }
        } else {
            $ip = $host;
        }

    return $ip;
    }

    private function startTime()
    {
        $this->timerStartTime = microtime();
    }

    private function finishTime()
    {
        $startTime = explode (" ", $this->timerStartTime);
        $startTime = $startTime[1] + $startTime[0];
        $endTime = explode (" ", microtime());
        $endTime = $endTime[1] + $endTime[0];

        // return how long ping take process
        return number_format ($endTime - $startTime, $this->percision);
    }

    /**
     * Service for PING IP/Hostname
     *
     * @access  public
     * @param   integer
     * @param   integer
     * @return  integer
     */
    public function servicePing($timeout = 5)
    {
        $this->icmpSocket = socket_create(AF_INET, SOCK_RAW, 1);
        socket_set_block($this->icmpSocket);

        // parameter validation
        if ((int)$timeout <= 0) $timeout=5;

        // set the timeout
        socket_set_option($this->icmpSocket,
            SOL_SOCKET,
            SO_RCVTIMEO,
            array(
                "sec"=>$timeout,
                "usec"=>0
            )
        );

        // is valid host?
        if (@socket_connect($this->icmpSocket, $this->ip, NULL))
        {

        } else {
            return array('isSuccess' => FALSE, 'msg' => 'cant connect to host', 'errorCode' => 503);
        }

        // build packet data
        $data = "farinmaricar19";
        $type = "\x08";
        $code = "\x00";
        $chksm = "\x00\x00"; 
        $id = "\x00\x00";
        $sqn = "\x00\x00"; 

        $dataFrame = $type.$code.$chksm.$id.$sqn.$data;

        $sum = 0;
        for($i=0;$i<strlen($dataFrame);$i += 2)
        {
            if($dataFrame[$i+1]) $bits = unpack('n*',$dataFrame[$i].$dataFrame[$i+1]);
             else $bits = unpack('C*',$dataFrame[$i]);
            $sum += $bits[1];
        }

        while ($sum>>16) $sum = ($sum & 0xffff) + ($sum >> 16);
            $checksum = pack('n1',~$sum);

        $this->request = $type.$code.$checksum.$id.$sqn.$data;
        $this->requestLen = strlen($this->request);

        // request start time
        $this->startTime();

        socket_write($this->icmpSocket, $this->request, $this->requestLen);

        if (@socket_recv($this->icmpSocket, $this->reply, 256, 0))
        {
            // retrieve finish time
            return array('isSuccess' => TRYE, 'msg' => $this->finishTime(), 'code' => 200);
        } else {
            return array('isSuccess' => FALSE, 'msg' => 'request timeout', 'code' => 408);
        }

    }

    /**
     * Service for determine spesific port is online
     *
     * @access  public
     * @param   integer
     * @return  boolean
     */
    public function serviceIcmp($port)
    {
        // is valid host?
        $this->icmpSocket = socket_create(AF_INET, SOCK_RAW, 1);
        if (@socket_connect($this->icmpSocket, $this->ip, NULL))
        {

        } else {
            return array('isSuccess' => FALSE, 'msg' => 'cant connect to host', 'code' => 503);
        }


        try {
            $this->startTime();
            // open connection
            $conn = @fsockopen($this->ip, $port, $errno, $errstr, 2);
            $endTime = $this->finishTime();
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }

        if ($conn) {
            fclose($conn);
            return array('isSuccess' => TRUE, 'msg' => $endTime, 'code' => 200);
        } else {
            return array('isSuccess' => FALSE, 'msg' => 'cant connect to port '.$port, 'code' => 503);
        }
    }

    /**
     * Service for determine website is online
     *
     * @access  public
     * @param   boolean
     * @return  boolean
     */
    public function serviceCurl($isSecure = FALSE)
    {

         // is valid host?
        $this->icmpSocket = socket_create(AF_INET, SOCK_RAW, 1);
        if (@socket_connect($this->icmpSocket, $this->ip, NULL))
        {

        } else {
            return array('isSuccess' => FALSE, 'msg' => 'cant connect to host', 'code' => 503);
        }

        // set agent
        $agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";

        if($isSecure)
        {
            $url = "https://".$this->host;
        } else {
            $url = "http://".$this->host;
        }

        try {
            $ch = curl_init();
            curl_setopt ($ch, CURLOPT_URL,$url );
            curl_setopt($ch, CURLOPT_USERAGENT, $agent);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch,CURLOPT_VERBOSE,FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch,CURLOPT_SSLVERSION,3);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, FALSE);

            $this->startTime();

             // execute request
            $page = curl_exec($ch);

            $endTime = $this->finishTime();

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }


        if($httpcode >= 200 && $httpcode < 300){
            return array('isSuccess' => TRUE, 'msg' => $endTime, 'code' => 200);
        } else {
            return array('isSuccess' => FALSE, 'msg' => 'read code', 'code' => $httpcode);
        }
    }
}
