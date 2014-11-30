<?php
    /*
     * This library is licensed under the MIT License
     * (c) 2014 Ammar Bandukwala
     */

    /*
    * To Do: Full Request/Response header support, complete error support, Metadata support
    */
    trait OSErrors
    {
        private $errors;
        public function addError($error)
        {
            $this->errors[] = $error;
        }
        public function clearErrors()
        {
            $this->errors = array();
        }
        public function getErrors()
        {
            return $this->errors;
        }
        public function getLastError()
        {
            return $this->errors[count($this->errors) - 1];
        }
    }
    trait OSHTTP
    {
        public $status;
        public $statuscode;
        public $useragent = "PHP OpenStack Client";
        public function load($ch, &$headers, &$body)
        {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);
            $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $headers = explode("\r\n", substr($response, 0, $hsize));
            $tmp = explode(" ", $headers[0]);
            $this->status = "";
            $this->statuscode = $tmp[1];
            for($i = 2; $i < count($tmp); $i++)
            {
                if($i > 2)
                {
                    $this->status .= " ";
                }
                $this->status .= $tmp[$i];
            }
            $ocount = count($headers);
            unset($headers[0]);
            for($i = 1; $i < $ocount; $i++)
            {
                if(strpos($headers[$i], ":"))
                {
                    $words = explode(" ", $headers[$i]);
                    $headers[str_replace(":", "", $words[0])] = "";
                    for($ii = 1; $ii < count($words); $ii++)
                    {
                        if($ii > 0)
                        {
                            $words[$ii] .= " ";
                        }
                        $headers[str_replace(":", "", $words[0])] .= $words[$ii];
                    }
                }
                unset($headers[$i]);
            }
            $body = substr($response, $hsize);
        }
    }
    class OSToken
    {
        public function __construct($arr = array())
        {
            foreach ($arr as $key => $value)
            {
                $this->$key = $value;
            }
        }
        public function getDeathTimestamp()
        {
            date_default_timezone_set('America/Los_Angeles');
            return time() + (strtotime($this->expires) - strtotime($this->issued_at));
        }
    }

    class OSEndpoint
    {
        public function __construct($arr = array())
        {
            foreach ($arr as $key => $value)
            {
                $this->$key = $value;
            }
        }
    }
    class OSService
    {
        private $endpoints;
        public function __construct($arr = array())
        {
            foreach ($arr as $key => $value)
            {
                $this->$key = $value;
            }
            foreach ($this->endpoints as &$key)
            {
                $key = new OSEndpoint($key);

            }
        }
        public function getEndpointsByRegion($region)
        {
            $return_array = array();
            foreach($this->endpoints as $key)
            {
                if($key->region == $region)
                {
                    $return_array[] = $key;
                }
            }
            return $return_array;
        }
        public function getEndpoints()
        {
            return $this->endpoints;
        }
    }
    class OSServiceCatalog
    {
        private $services;
        public $caseSensitive;
        public function __construct($arr)
        {
            $this->caseSensitive = false;
            foreach ($arr as $key => $value)
            {
                $this->services[$key] = new OSService($value);
            }
        }
        public function getServicesByType($type)
        {
            if(!$this->caseSensitive) $type = strtolower($type);
            $return_array = array();
            foreach($this->services as $key)
            {
                $key_type = (String)$key->type;
                if(!$this->caseSensitive) $key_type = strtolower($key_type);
                if($key_type == $type)
                {
                    $return_array[] = $key;
                }
            }
            return $return_array;
        }
        public function getServicesByName($name)
        {
            if(!$this->caseSensitive) $name = strtolower($name);
            $return_array = array();
            foreach($this->services as $key)
            {
                $key_name = (String)$key->name;
                if(!$this->caseSensitive) $key_name = strtolower($key_name);
                if($key_name == $name)
                {
                    $return_array[] = $key;
                }
            }
            return $return_array;
        }
        public function getServices()
        {
            return $this->services;
        }
    }
    class OSIdentity
    {
        use OSErrors;
        use OSHTTP;
        public $authEndpoint;
        public $username;
        public $password;
        public $tenantName;
        public $tenantId;
        public $id;
        public $serviceCatalog;
        public $accessToken;
        private $passwordCredentials; //bool

        public function __construct($authEndpoint, $username = "", $password = "")
        {
            if(substr($authEndpoint, -7) !== "/tokens")
            {
                $authEndpoint .= "/tokens";
            }
            $this->authEndpoint = $authEndpoint;
            $this->username = $username;
            $this->password = $password;

            $this->passwordCredentials = false;
            $this->token = false;
        }
        public function login($headers = array())
        {
            $headers[] = "Content-Type: application/json";
            $request = array();
            if(!empty($this->tenantName))
            {
                $request["auth"]["tenantName"] = $this->tenantName;
            }
            if(!empty($this->tenantId))
            {
                $request["auth"]["tenantId"] = $this->tenantId;
                if(isset($request["auth"]["tenantName"]))
                {
                    $this->addError("tenantName and tenantId specified together.");
                }
            }
            if(!empty($this->username))
            {
                $request["auth"]["passwordCredentials"]["username"] = $this->username;
                $request["auth"]["passwordCredentials"]["password"] = $this->password;
            }
            if(!empty($this->id))
            {
                $request["auth"]["token"]["id"] = $id;
            }
            $ch = curl_init($this->authEndpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
            $this->load($ch, $rheaders, $rbody);
            $rbody = json_decode($rbody, true);
            if($this->statuscode != "200" && $this->statuscode != "203") $this->addError($this->status . " (" . $this->statuscode . ")");
            $this->serviceCatalog = new OSServiceCatalog($rbody["access"]["serviceCatalog"]);
            $this->accessToken = new OSToken($rbody["access"]["token"]);
        }
    }
    class OSContainer
    {
        use OSErrors;
        use OSHTTP;
        public $ObjectStore;
        public $name;
        public function __construct($name, $ObjectStore)
        {
            $this->name = $name;
            $this->ObjectStore = $ObjectStore;
        }
        public function getDetails($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->ObjectStore->objectStoreURL . "/" . $this->name . "?format=json");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200) $this->addError($this->status . " (" . $this->statuscode . ")");;
            return array("headers" => $headers, "body" => json_decode($body, true));
        }
        public function getObjects($headers = array())
        {
            $return_array = array();
            $details = $this->getDetails($headers);
            foreach($details["body"] as $key)
            {
                $return_array[] = new OSObject($key["name"], $this);
            }
            return $return_array;

        }
        public function getObjectList($headers = array())
        {
            $return_array = array();
            $objects = $this->getObjects($headers);
            foreach($objects as $key)
            {
                $return_array[] = $key->name;
            }
            return $return_array;
        }
        public function getObjectByName($name, $headers = array())
        {
            $objects = $this->getObjects($headers);
            foreach($objects as $key)
            {
                if($key->name == $name)
                {
                    return $key;
                }
            }
            return false;
        }
        public function getObject($name, $headers = array())
        {
            return $this->getObjectByName($name, $headers);
        }
        public function getMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->ObjectStore->objectStoreURL . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return $headers;
        }
        public function updateMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->ObjectStore->objectStoreURL . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return true;
        }
        public function createObject($name, $data = "", $headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->ObjectStore->Identity->accessToken->id;
            $headers[] = "Content-Length: " . strlen($data);
            $headers[] = "X-Detect-Content-Type: true";
            $ch = curl_init($this->ObjectStore->objectStoreURL . "/" . $this->name . "/" . $name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 201)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return new OSObject($name, $this);
        }
        public function delete($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->ObjectStore->objectStoreURL . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");
                return false;
            }
            return true;
        }
        public function __toString()
        {
            return $this->name;
        }
    }
    class OSObject
    {
        use OSErrors;
        use OSHTTP;
        public $name;
        public $Container;
        public function __construct($name, $Container)
        {
            $this->name = $name;
            $this->Container = $Container;
        }
        public function getData($headers = array())
        {
            return $this->getDetails($headers)["body"];
        }
        public function getDetails($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Container->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->Container->ObjectStore->objectStoreURL . "/" . $this->Container->name . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return array("headers" => $headers, "body" => $body);
        }
        public function getMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Container->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->Container->ObjectStore->objectStoreURL . "/" . $this->Container->name . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return $headers;
        }
        public function updateMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Container->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->Container->ObjectStore->objectStoreURL . "/" . $this->Container->name . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 202 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return true;
        }
        public function copy($destination, $headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Container->ObjectStore->Identity->accessToken->id;
            $headers[] = "Destination: " . $destination;
            $ch = curl_init($this->Container->ObjectStore->objectStoreURL . "/" . $this->Container->name . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'COPY');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 201 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            $splitName = explode("/", $destination);
            $destContainer = $this->Container->ObjectStore->getContainerByName($splitName[0]);
            unset($splitName[0]);
            $destName = implode("/", $splitName);
            return new OSObject($destName, $destContainer);
        }
        public function delete($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Container->ObjectStore->Identity->accessToken->id;
            $ch = curl_init($this->Container->ObjectStore->objectStoreURL . "/" . $this->Container->name . "/" . $this->name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return true;
        }
        public function __toString()
        {
            return $this->getData();
        }
    }
    class OSObjectStore
    {
        use OSErrors;
        use OSHTTP;
        public $objectStoreURL;
        public $Indentity;
        public function __construct($Identity)
        {
            $this->Identity = $Identity;
            $this->objectStoreURL = $Identity->serviceCatalog->getServicesByType("object-store")[0]->getEndpoints()[0]->publicURL;
        }
        public function getDetails($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Identity->accessToken->id;
            $ch = curl_init($this->objectStoreURL . "?format=json");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200) $this->addError($this->status . " (" . $this->statuscode . ")");
            return array("headers" => $headers, "body" => json_decode($body, true));
        }
        public function getMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Identity->accessToken->id;
            $ch = curl_init($this->objectStoreURL);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200) $this->addError($this->status . " (" . $this->statuscode . ")");;
            return array("headers" => $headers);
        }
        public function getContainers($headers = array())
        {
            $return_array = array();
            $tmp = $this->getDetails();
            $body = $tmp["body"];
            $headers = $tmp["headers"];
            foreach($body as $key)
            {
                $return_array[] = new OSContainer($key["name"], $this);
            }
            return $return_array;
        }
        public function getContainer($name, $headers = array())
        {
            return $this->getContainerByName($name, $headers = array());
        }
        public function getContainerList($headers = array())
        {
            $return_array = array();
            $containers = $this->getContainers($headers);
            foreach($containers as $key)
            {
                $return_array[] = $key->name;
            }
            return $return_array;
        }
        public function getContainerByName($name, $headers = array())
        {
            $return_array = array();
            $containers = $this->getContainers($headers);
            foreach($containers as $key)
            {
                if($key->name == $name)
                {
                    return $key;
                }
            }
            $this->addError("Container not found");
            return false;
        }
        public function updateMetadata($headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Identity->accessToken->id;
            $ch = curl_init($this->objectStoreURL);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 200)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");;
                return false;
            }
            return true;
        }
        public function createContainer($name, $headers = array())
        {
            $headers[] = "X-Auth-Token: " . $this->Identity->accessToken->id;
            $headers[] = "Content-Length: 0";
            $ch = curl_init($this->objectStoreURL . "/" . $name);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $this->load($ch, $headers, $body);
            if($this->statuscode != 204 && $this->statuscode != 201)
            {
                $this->addError($this->status . " (" . $this->statuscode . ")");
                return false;
            }
            return new OSContainer($name, $this);
        }
    }

?>
