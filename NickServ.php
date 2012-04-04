<?php

class Service_Atheme_NickServ {
        private $port = ATHEME_SERVER_PORT;
        private $host = ATHEME_SERVER_IP;
        private $path = '/xmlrpc';
        
        private $client = null;
        
        private $nickname = null;
        private $token = null;
        private $error = null;
        
        function __construct() {
                $this->client = new Driver_XML_IXR_Client($this->host,$this->path,$this->port);
        }
        
        function setDebug($toggle) {
                $this->client->debug = $toggle;
        }
        
        function login($nickname,$password) {
                if(!$this->client->query('atheme.login',$nickname,$password)) {
                        // Something went wrong
                        $this->error = $this->client->getErrorCode() .' ' . $this->client->getErrorMessage();
                        $this->client->clearError();
                        return false;
                }
                // Everything went okay with the login
                // Save the token and return true
                $this->setNickName($nickname);
                $this->setToken($this->client->getResponse());
                return true;
        }
        
        function setToken($str) {
                $this->token = $str;
        }
        
        function getToken() {
                return $this->token;
        }
        
        function setNickName($str) {
                $this->nickname = $str;
        }
        
        function getNickName() {
                return $this->nickname;
        }
        
        function clearError() {
                $this->error = null;
        }
        
        //
        // Commands proper.
        //
        function sendCommand($str) {
                // Send a generic command
                $args = array(
                        'atheme.command',
                        $this->getToken(), // auth cookie
                        $this->getNickName(), // account name
                        '.' // source ip
                        );
                return call_user_func_array(array($this->client,'query'),array_merge($args,explode(' ',$str)));
        }
        
        function getRegDate($as_unix_ts = false) {
                $info = explode("\n",$this->getNickInfo($this->getNickName()));
                $matches = array();
                foreach($info as $line) {
                        if(preg_match('/^Registered {1,}: (?P<datestr>.*)\(/', trim($line),$matches)) {
                                // They look like this: May 31 22:30:23 2007
                                if($as_unix_ts) {
                                        $date_info = date_parse_from_format('M d H:i:s Y',trim($matches['datestr']));
                                        return mktime($date_info['hour'],$date_info['minute'],$date_info['second'],$date_info['month'],$date_info['day'],$date_info['year']);
                                } else {
                                        return trim($matches['datestr']);
                                }
                        }
                }
                return false;
        }
        
        function getNickInfo($nickname) {
                if(!empty($nickname)) {
                        if(!$this->sendCommand('NickServ INFO '.$nickname)) {
                                // Something went wrong
                                $this->error = $this->client->getErrorCode() .' ' . $this->client->getErrorMessage();
                                $this->client->clearError();
                                return false;
                        } else {
                                return $this->client->getResponse();
                        }
                }
                $this->error = 'Blank nickname provided.';
                return false;
        }
        
        function setEnforce($on = true) {
                if(!$this->sendCommand('NickServ SET ENFORCE '.($on ? 'ON' : 'OFF'))) {
                        // Something went wrong
                        $this->error = $this->client->getErrorCode() .' ' . $this->client->getErrorMessage();
                        $this->client->clearError();
                        return false;
                }
                return true;
        }
        
        /* bool/array unserialize_xml ( string $input [ , callback $callback ] )
         * Unserializes an XML string, returning a multi-dimensional associative array, optionally runs a callback on all non-array data
         * Returns false on all failure
         * Notes:
            * Root XML tags are stripped
            * Due to its recursive nature, unserialize_xml() will also support SimpleXMLElement objects and arrays as input
            * Uses simplexml_load_string() for XML parsing, see SimpleXML documentation for more info
         */
        function unserialize_xml($input, $callback = null, $recurse = false)
        {
                // Get input, loading an xml string with simplexml if its the top level of recursion
                $data = ((!$recurse) && is_string($input))? simplexml_load_string($input): $input;
                // Convert SimpleXMLElements to array
                if ($data instanceof SimpleXMLElement) $data = (array) $data;
                // Recurse into arrays
                if (is_array($data)) foreach ($data as &$item) $item = unserialize_xml($item, $callback, true);
                // Run callback and return
                return (!is_array($data) && is_callable($callback))? call_user_func($callback, $data): $data;
        }
        
}

?>
