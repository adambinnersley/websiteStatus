<?php

/**
 * Checks to see what response code an array of websites are providing and can also check the SSL expiration date of the websites, can also store the results in a database
 * 
 * @author Adam Binnersley
 */
namespace SiteStatus;

use DBAL\Database;
use PHPMailer;

class Status{
    protected static $db;
    protected static $status_table = 'site_status';

    protected $getSSLExpiry = true;
    protected $storeResults = true;
    protected $emailResults = true;
    
    protected $domains;
    protected $siteInfo = array();
    
    protected $from = 'noreply@domain.com';
    protected $fromName = 'Website Status Check';
    
    protected $smtpUsername;
    protected $smtpPassword;
    protected $emailHostname;
    
    protected $emailTo;
    
    public $count = array();

    /**
     * Constructor used to pass a Instance of the database
     * @param Database $db This should be an instance of the database connection
     */
    public function __construct(Database $db){
        self::$db = $db;
        ini_set('max_execution_time', 0);
    }
    
    /**
     * Sets the table name where the results can be found 
     * @param string $table This should be the table where your results are stored should you be storing in a database
     * @return $this
     */
    public function setTableName($table){
        self::$status_table = $table;
        return $this;
    }
    
    /**
     * Sets the SSL check setting (default is that SSL info is checked) if you don't want the SSL info checking set this to false
     * @param boolean $getSSL Set this to false if you don't want to check the SSL certificate expiry
     * @return $this
     */
    public function settingSSLInfo($getSSL = true){
        $this->getSSLExpiry = (bool)$getSSL;
        return $this;
    }
    
    /**
     * Changes the setting whether to store the results in the database or not (default is true and stores the results). If you don't want to store the results in the database set to false
     * @param boolean $storeResults If you want to store the results set to true else set to false
     * @return $this
     */
    public function settingDBStore($storeResults = true){
        $this->storeResults = (bool)$storeResults;
        return $this;
    }
    
    /**
     * Changes the setting as to whether to email the results once run
     * @param boolean $email If you don't want to send an email on completion set this to false else the default is true
     * @return $this
     */
    public function settingEmailResults($email = true){
        $this->emailResults = (bool)$email;
        return $this;
    }
    
    /**
     * Set the name and email address where any automated email will com from
     * @param string $email This should be the email address where the automated emails will come from
     * @param string $name This should be the displayed name where the emails come from
     * @return $this
     */
    public function setEmailFrom($email, $name){
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){$this->from = filter_var($email, FILTER_SANITIZE_EMAIL);}
        $this->fromName = filter_var($name, FILTER_SANITIZE_STRING);
        return $this;
    }
    
    /**
     * Set where the automated emails will go to
     * @param type $email This should be the email address where you want any emails to be sent on completion of the task
     */
    public function setEmailTo($email){
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){$this->emailTo = filter_var($email, FILTER_SANITIZE_EMAIL);}
    }
    
    /**
     * Sets the SMTP information in order to send any emails once the task is complete
     * @param string $username This should be the username/email so the email can be sent via authenticated SMTP
     * @param string $password This should be the password so the email can be sent via authenticated SMTP
     * @param string $hostname This should be the hostname so this can be set when sending the email
     * @return $this
     */
    protected function setSMTPInfo($username, $password, $hostname = false){
        $this->smtpUsername = $username;
        $this->smtpPassword = $password;
        $this->emailHostname = $hostname;
        return $this;
    }
    /**
     * Checks the status of either an individual website or an array of websites
     * @param string|array $websites This should be wither a single website or an array of websites to check the status for
     */
    public function checkStatus($websites){
        if($this->storeResults){$this->emptyDBResults();}
        if(is_array($websites)){
            $this->count['number'] = count($websites);
            foreach($websites as $i => $website){
                $this->checkDomain($website, $i);
            }
        }
        else{
            $this->count['number'] = 1;
            $this->checkDomain($websites);
        }
        $this->sendEmail();
    }
    
    /**
     * Checks a single domain and stores the results if that option is set
     * @param string $website This should be the domain name
     * @param int $i If multiple websites are being checked this will be an incrementing integer
     */
    protected function checkDomain($website, $i = 0){
        $this->siteInfo[$i]['domain'] = $website;
        $this->siteInfo[$i]['status'] = $this->getWebsite($website);
        if($this->siteInfo[$i]['status'] == 200){$this->count['ok']++;}
        else{
            $this->count['issue']++;
            $this->count['problem_domains'][] = $website;
        }
        if($this->siteInfo[$i]['status'] == 200 && $this->getSSLExpiry){$this->siteInfo[$i]['cert'] = $this->getSSLCert($website);}
        $this->storeResultsinDB($this->siteInfo[$i]['domain'], $this->siteInfo[$i]['status'], $this->siteInfo[$i]['cert']);
    }

    /**
     * Gets the website headers using curl
     * @param string $url This should be the website address
     * @return int The website status code will be returned
     */
    protected function getWebsite($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $statusCode;
    }
    
    /**
     * Retrieves the websites SSL certificate and retrieves the certificate information
     * @param string $url This should the website address
     * @return array The certificate information will be returned as an array
     */
    protected function getSSLCert($url){
        $domain = 'https://'.str_replace(array('http://', 'https://'), '', strtolower($url)); // Force it to look at the https otherwise it fails
        $orignal_parse = parse_url($domain, PHP_URL_HOST);
        $get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
        $read = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
        $cert = stream_context_get_params($read);
        return openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    }
    
    /**
     * Stores the results in the database if that option is set
     * @param string $website This should be the website address
     * @param int $status This should be the status code retrieved for that website
     * @param array $certInfo This should be the certificate information in an array format retrieved from the website
     * @return boolean If the information has successfully inserted into the database true will be returned else will return false 
     */
    protected function storeResultsinDB($website, $status, $certInfo){
        if($this->storeResults){
            $ssl_expiry = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
            if($ssl_expiry <= date('Y-m-d H:i:s')){
                $this->count['expired']++;
                $this->count['problem_domains'][] = $website;
            }
            return self::$db->insert(self::$status_table, array('website' => $website, 'status' => $status, 'ssl_expiry' => $ssl_expiry));
        }
        return false;
    }
    
    /**
     * Gets all of the results from the database to return as an array
     * @return array The results will be return as an array
     */
    public function getResults(){
        return self::$db->selectAll(self::$status_table);
    }
    
    /**
     * Empty the database so the new results can be added
     * @return boolean If the database is successfully truncated will return true else will return false
     */
    protected function emptyDBResults(){
        return self::$db->truncate(self::$status_table);
    }

    /**
     * Sends the emails if that option is set
     * @return boolean If the email is sent successfully will return true else returns false
     */
    protected function sendEmail(){
        if($this->emailResults){
            include '../email/domain-status-check-email.php';
            $email = new PHPMailer();
            $email->SMTPAuth = true;
            $email->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            $email->Username = $this->smtpUsername;
            $email->Password = $this->smtpPassword;
            if(!empty($this->emailHostname)){
                $email->IsSMTP();
                $email->Host = $this->emailHostname;
            }
            $email->SetFrom($this->from, $this->fromName);
            $email->AddAddress($this->emailTo);
            $email->Subject = sprintf($subject, $this->count['issues']);
            $email->MsgHTML(sprintf($html, $this->count['number'], $this->count['issues'], $this->count['expired'], implode("</strong><br />\n<strong>",$this->count['problem_domains'])));
            return $email->Send() ? true : false;
        }
        return false;
    }
}
