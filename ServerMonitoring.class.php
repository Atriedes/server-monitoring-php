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

    // Ping variable
    private $icmp_socket;
    private $request;
    private $request_len;
    private $reply;
    private $time;
    private $timer_start_time;

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
            }
        } else {
            $ip = $host;
        }

    return $ip;
    }

    /**
     * Service for PING IP/Hostname
     *
     * @access  public
     * @param   integer
     * @param   integer
     * @return  integer
     */
    public function servicePing($timeout = 5, $percision = 3)
    {
        $this->icmp_socket = socket_create(AF_INET, SOCK_RAW, 1);
        socket_set_block($this->icmp_socket);

        // parameter validation
        if ((int)$timeout <= 0) $timeout=5;
        if ((int)$percision <= 0) $percision=3;

        // set the timeout
        socket_set_option($this->icmp_socket,
            SOL_SOCKET,
            SO_RCVTIMEO,
            array(
                "sec"=>$timeout,
                "usec"=>0
            )
        );

        // is valid host?
        if (@socket_connect($this->icmp_socket, $this->ip, NULL))
        {

        } else {
            return FALSE;
        }

        // build packet data
        $data = "farinmaricar19";
        $type = "\x08";
        $code = "\x00";
        $chksm = "\x00\x00"; 
        $id = "\x00\x00";
        $sqn = "\x00\x00"; 

        $dataframe = $type.$code.$chksm.$id.$sqn.$data;

        $sum = 0;
        for($i=0;$i<strlen($dataframe);$i += 2)
        {
            if($dataframe[$i+1]) $bits = unpack('n*',$dataframe[$i].$dataframe[$i+1]);
             else $bits = unpack('C*',$dataframe[$i]);
            $sum += $bits[1];
        }

        while ($sum>>16) $sum = ($sum & 0xffff) + ($sum >> 16);
            $checksum = pack('n1',~$sum);

        $this->request = $type.$code.$checksum.$id.$sqn.$data;
        $this->request_len = strlen($this->request);

        // request start time
        $this->timer_start_time = microtime();

        socket_write($this->icmp_socket, $this->request, $this->request_len);

        if (@socket_recv($this->icmp_socket, $this->reply, 256, 0))
        {
            // retrieve finish time
            $start_time = explode (" ", $this->timer_start_time);
            $start_time = $start_time[1] + $start_time[0];
            $end_time = explode (" ", microtime());
            $end_time = $end_time[1] + $end_time[0];

            // return how long ping take process
            return number_format ($end_time - $start_time, $percision);
        } else {
            return FALSE;
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
        try {
            // open connection
            $conn = @fsockopen($this->ip, $port, $errno, $errstr, 2);
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }

        if ($conn) {
            fclose($conn);
            return TRUE;
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
            $page = curl_exec($ch);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }


        if($httpcode >= 200 && $httpcode < 300){
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
