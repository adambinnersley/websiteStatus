<?php

/**
 * Description of SiteStatus
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
    
    protected $siteInfo = array();

    public function __construct(Database $db){
        self::$db = $db;
        ini_set('max_execution_time', 0);
    }
    
    public function setTableName($table){
        self::$status_table = $table;
    }
    
    public function settingSSLInfo($getSSL = true){
        $this->getSSLExpiry = (bool)$getSSL;
    }
    
    public function settingDBStore($storeResults = true){
        $this->storeResults = (bool)$storeResults;
    }
    
    public function settingEmailResults($email = true){
        $this->emailResults = (bool)$email;
    }

    public function checkStatus($websites){
        if($this->storeResults){$this->emptyDBResults();}
        if(is_array($websites)){
            foreach($websites as $i => $website){
                $this->checkDomain($website, $i);
            }
        }
        else{
            $this->checkDomain($websites);
        }
        $this->sendEmail();
    }
    
    protected function checkDomain($website, $i = 0){
        $this->siteInfo[$i]['domain'] = $website;
        $this->siteInfo[$i]['status'] = $this->getWebsite($website);
        if($this->siteInfo[$i]['status'] == 200 && $this->getSSLExpiry){$this->siteInfo[$i]['cert'] = $this->getSSLCert($website);}
        $this->storeResultsinDB($this->siteInfo[$i]['domain'], $this->siteInfo[$i]['status'], $this->siteInfo[$i]['cert']);
    }

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
    
    protected function getSSLCert($url){
        $orignal_parse = parse_url($url, PHP_URL_HOST);
        $get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
        $read = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
        $cert = stream_context_get_params($read);
        return openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    }
    
    protected function storeResultsinDB($website, $status, $certInfo){
        if($this->storeResults){
            $ssl_expiry = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
            return self::$db->insert(self::$status_table, array('website' => $website, 'status' => $status, 'ssl_expiry' => $ssl_expiry));
        }
        return false;
    }
    
    protected function emptyDBResults(){
        return self::$db->truncate(self::$status_table);
    }

    protected function sendEmail(){
        $mail = new PHPMailer();
    }
}
