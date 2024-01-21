<?php
defined('BASEPATH') or exit('No direct script access allowed');

class CpanelApi
{
    private $cpanelUsername;
    private $cpanelPassword;
    private $cpanelDomain;
    private $cpanelPort;
    private $prefix;
    public $mainDomain;
    public $throwException;

    public function __construct()
    {
    }

    /**
     * Initialize the cPanel API with authentication and options.
     *
     * @param string $cpanelUsername
     * @param string $cpanelPassword
     * @param string $cpanelDomain
     * @param string $cpanelPort
     * @param string $prefix
     * @param bool $throwException
     *
     * @return $this
     */
    public function init($cpanelUsername, $cpanelPassword, $cpanelDomain, $cpanelPort = "2083", $prefix = '', $throwException = true)
    {
        $this->cpanelUsername = $cpanelUsername;
        $this->cpanelPassword = $cpanelPassword;
        $this->cpanelDomain = $cpanelDomain;
        $this->cpanelPort = $cpanelPort;
        $this->throwException = $throwException;
        $this->prefix = (str_starts_with($prefix, $cpanelUsername) ? $prefix : $cpanelUsername . '' . (empty($prefix) ? '' : $prefix . '')).'_';
        return $this;
    }

    /**
     * Set the behavior for throwing exceptions on API errors.
     *
     * @param bool $throwException
     */
    public function setThrowException($throwException)
    {
        $this->throwException = $throwException;
    }

    /**
     * Make an API call to cPanel.
     *
     * @param string $module
     * @param string $func
     * @param array $params
     * @param string $version
     *
     * @return array
     */
    private function makeAPICall($module, $func, $params = [], $version = 'uapi')
    {
        $url = "{$this->cpanelDomain}:{$this->cpanelPort}/execute/{$module}/{$func}";
        $headers = [
            "Authorization: cpanel " . $this->cpanelUsername . ":" . $this->cpanelPassword . "\n\r"
        ];

        if ($version !== 'uapi') {
            $url = "{$this->cpanelDomain}:{$this->cpanelPort}/json-api/cpanel?cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module={$module}&cpanel_jsonapi_func={$func}";
        }

        $paramsString = "";
        if (!empty($params)) {
            $paramsString = $version === 'uapi' ? "?" : "&";
            $paramsLinear = [];
            foreach ($params as $key => $value) {
                $paramsLinear[] = "$key=$value";
            }
            $paramsString .= implode('&', $paramsLinear);
        }

        $url .= $paramsString;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

        $response_text = curl_exec($ch);
       
        if (curl_errno($ch)) {
            //echo curl_error($ch);
            // Handle curl error
        }

        $response = json_decode($response_text, true);

        $success = (int)($response['result']["status"][0] ?? ($response['status'] ?? 0)) === 1;
        if (!$success && $version !== 'uapi') {
            $success = (int)($response['cpanelresult']['data'][0]['result'] ?? 0) === 1;
        }

        if (!$success) {
            $error = $response['errors'] ?? ($response['cpanelresult']['error'] ?? (strpos($response_text, 'login') !== false ? 'The panel login is invalid.' : $response_text));
            if (is_array($error)) {
                $error = implode(". ", $error);
            }

            if ($this->throwException) {
                throw new \Exception($error, 1);
            } else {
                log_message('error', $error);
            }
        }
        return $response;
    }

    /**
     * Make an API call using v2 format.
     *
     * @param string $module
     * @param string $func
     * @param array $params
     *
     * @return array
     */
    private function makeAPICallv2($module, $func, $params = [])
    {
        return $this->makeAPICall($module, $func, $params, 'v2');
    }

    /**
     * Get disk quotas for the cPanel account.
     *
     * @return array
     */
    public function getDiskQuotas()
    {
        return $this->makeAPICall('Quota', 'get_local_quota_info');
    }

        /**
     * Add a prefix to a given text if it's not already prefixed.
     *
     * @param string $text
     * @return string
     */
    public function addPrefix($text)
    {
        return (str_starts_with($text, $this->prefix) ? $text : $this->prefix . $text);
    }

    /**
     * Create a database with an optional prefix and prefix size.
     *
     * @param string $databaseName
     * @param string $dbType
     * @param int $prefixSize
     * @return array
     */
    public function createDatabase($databaseName, $dbType = 'Mysql', $prefixSize = 16)
    {
        $params = [
            'name' => $this->addPrefix($databaseName),
            'prefix-size' => $prefixSize
        ];
        return $this->makeAPICall($dbType, 'create_database', $params);
    }

    /**
     * Create a database with an optional prefix and prefix size.
     *
     * @param string $databaseName
     * @param string $dbType
     * @param int $prefixSize
     * @return array
     * @author MYSELF
     */
    public function createDatabaseAndUser($databaseName, $password, $subdomain = "")
    {
        $this->createDatabase($databaseName);
        $this->createDatabaseUser($databaseName, $password);
        $this->setDatabaseUserPrivileges($databaseName, $databaseName);
        if(!empty($subdomain)){
            $this->createSubdomain($subdomain, $_SERVER['HTTP_HOST']);
            if(stripos(APP_BASE_URL,'https') === 0){
                $this->autoSSL();
                $this->generateSSL($subdomain.".".$_SERVER['HTTP_HOST']);
                $this->toggleSslRedirect($subdomain.".".$_SERVER['HTTP_HOST']);
            }
        }
    }

    /**
     * Delete a database with an optional prefix.
     *
     * @param string $databaseName
     * @param string $dbType
     * @return array
     */
    public function deleteDatabase($databaseName, $dbType = 'Mysql')
    {
        $params = [
            'name' => $this->addPrefix($databaseName)
        ];
        return $this->makeAPICall($dbType, 'delete_database', $params);
    }

    /**
     * Create a database user with an optional prefix and prefix size.
     *
     * @param string $databaseUser
     * @param string $databasePassword
     * @param string $dbType
     * @param int $prefixSize
     * @return array
     */
    public function createDatabaseUser($databaseUser, $databasePassword, $dbType = 'Mysql', $prefixSize = 16)
    {
        $params = [
            'name' => $this->addPrefix($databaseUser),
            'password' => $databasePassword,
            'prefix-size' => $prefixSize
        ];
        return $this->makeAPICall($dbType, 'create_user', $params);
    }

    /**
     * Delete a database user with an optional prefix.
     *
     * @param string $databaseUser
     * @param string $dbType
     * @return array
     */
    public function deleteDatabaseUser($databaseUser, $dbType = 'Mysql')
    {
        $params = [
            'name' => $this->addPrefix($databaseUser)
        ];
        return $this->makeAPICall($dbType, 'delete_user', $params);
    }

    /**
     * Set privileges for a database user on a database with an optional prefix.
     *
     * @param string $databaseUser
     * @param string $databaseName
     * @param string $privileges
     * @param string $dbType
     * @return array
     */
    public function setDatabaseUserPrivileges($databaseUser, $databaseName, $privileges = 'ALL%20PRIVILEGES', $dbType = 'Mysql')
    {
        $params = [
            'user' => $this->addPrefix($databaseUser),
            'database' => $this->addPrefix($databaseName),
            'privileges' => $privileges
        ];

        return $this->makeAPICall($dbType, 'set_privileges_on_database', $params);
    }

    /**
     * Create a subdomain with optional directory and settings.
     *
     * @param string $subdomain
     * @param string $rootdomain
     * @param string $dir
     * @param int $disallowdot
     * @return array
     */
    public function createSubdomain($subdomain, $rootdomain, $dir = '/public_html/', $disallowdot = 1)
    {
        $params = [
            'domain' => $subdomain,
            'rootdomain' => $rootdomain,
            'dir' => $dir,
            'disallowdot' => $disallowdot
        ];

        return $this->makeAPICall('SubDomain', 'addsubdomain', $params);
    }

    /**
     * Delete a subdomain with an optional prefix.
     *
     * @param string $subdomain
     * @param string $rootdomain
     * @return array
     */
    public function deleteSubdomain($subdomain, $rootdomain)
    {
        $params = [
            'domain' => $subdomain . '.' . $rootdomain,
        ];

        return $this->makeAPICallv2('SubDomain', 'delsubdomain', $params);
    }

    /**
     * Create an addon domain with an optional directory.
     *
     * @param string $domain
     * @param string $subdomain
     * @param string $dir
     * @return array
     */
    public function createAddonDomain($domain, $subdomain, $dir = '/public_html/')
    {
        $params = [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $dir,
        ];

        return $this->makeAPICallv2('AddonDomain', 'addaddondomain', $params);
    }

    /**
     * Delete an addon domain with an optional prefix.
     *
     * @param string $domain
     * @param string $subdomain
     * @param string $rootdomain
     * @return array
     */
    public function deleteAddonDomain($domain, $subdomain, $rootdomain)
    {
        $params = [
            'domain' => $domain,
            'subdomain' => $subdomain . '_' . $rootdomain,
        ];

        return $this->makeAPICallv2('AddonDomain', 'deladdondomain', $params);
    }

    /**
     * Start the AutoSSL certificate generation process.
     *
     * @return array
     */
    public function autoSSL()
    {
        return $this->makeAPICall('SSL', 'start_autossl_check');
    }
    
    /**
     * Start the AutoSSL certificate generation process.
     *
     * @return array
     */
    public function toggleSslRedirect($domain, $status = 1)
    {
        $params = [
            'domains' => $domain,
            'state' => $status
        ];

        return $this->makeAPICall('SSL', 'toggle_ssl_redirect_for_domains', $params);
    }

    /**
     * Generate an SSL certificate for the given domain.
     *
     * @param string $domain
     * @return array
     */
    public function generateSSL($domain)
    {
        $params = [
            'city' => 'Houston',
            'country' => 'US',
            'company' => 'cPanel',
            'state' => 'HT',
            'host' => $domain,
            'email' => 'webmaster@' . $domain
        ];

        return $this->makeAPICallv2('SSL', 'gencrt', $params);
    }
}