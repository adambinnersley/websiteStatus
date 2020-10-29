<?php
/**
 * Checks to see what response code an website or array of websites
 * are providing can also check the SSL expiration date of the websites
 * and can also store the results in a database if needed
 *
 * @author Adam Binnersley
 */
namespace SiteStatus;

use DBAL\Database;
use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Exception;

class Status
{
    protected $db;
    protected $status_table = 'site_status';
    
    protected $client;

    public $getSSLExpiry = true;
    public $storeResults = true;
    public $emailResults = true;
    
    protected $domains;
    protected $siteInfo = [];
    
    protected $from = 'noreply@domain.com';
    protected $fromName = 'Website Status Check';
    
    protected $smtpUsername;
    protected $smtpPassword;
    protected $emailHostname;
    
    protected $emailTo;
    
    public $count = ['number' => 0, 'ok' => 0, 'issues' => 0, 'expired' => 0, 'problem_domains' => []];

    /**
     * Constructor used to pass a Instance of the database
     * @param Database $db This should be an instance of the database connection
     */
    public function __construct($db)
    {
        if (is_object($db)) {
            $this->db = $db;
        } else {
            $this->setDBStore(false);
        }
        $this->client = new Client();
        ini_set('max_execution_time', 0);
    }
    
    /**
     * Returns the table name where the results can be found
     * @return string
     */
    public function getTableName()
    {
        return $this->status_table;
    }
    
    /**
     * Sets the table name where the results can be found
     * @param string $table This should be the table where your results are stored should you be storing in a database
     * @return $this
     */
    public function setTableName($table)
    {
        $this->status_table = $table;
        return $this;
    }
    
    /**
     * Gets the SSL checking setting
     * @return boolean If SSL certificates are to be checked will return true else return false
     */
    public function getSSLInfo()
    {
        return $this->getSSLExpiry;
    }
    
    /**
     * Sets the SSL check setting if you don't want the SSL info checking set this to false
     * @param boolean $getSSL Set this to false if you don't want to check the SSL certificate expiry
     * @return $this
     */
    public function setSSLInfo($getSSL = true)
    {
        $this->getSSLExpiry = (bool)$getSSL;
        return $this;
    }
    
    /**
     * Gets the setting if to store the results in the database or not
     * @return boolean If results should be store in the database will return true els return false
     */
    public function getDBStore()
    {
        return $this->storeResults;
    }
    
    /**
     * Changes the setting whether to store the results in the database (default is true).
     * @param boolean $storeResults If you want to store the results set to true else set to false
     * @return $this
     */
    public function setDBStore($storeResults = true)
    {
        $this->storeResults = (bool)$storeResults;
        return $this;
    }
    
    public function getEmailResults()
    {
    }
    
    /**
     * Changes the setting as to whether to email the results once run
     * @param boolean $email If you don't want to send an email on completion set this to false else the default is true
     * @return $this
     */
    public function setEmailResults($email = true)
    {
        $this->emailResults = (bool)$email;
        return $this;
    }
    
    /**
     * Set the name and email address where any automated email will com from
     * @param string $email This should be the email address where the automated emails will come from
     * @param string $name This should be the displayed name where the emails come from
     * @return $this
     */
    public function setEmailFrom($email, $name)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->from = filter_var($email, FILTER_SANITIZE_EMAIL);
        }
        $this->fromName = filter_var($name, FILTER_SANITIZE_STRING);
        return $this;
    }
    
    /**
     * Set where the automated emails will go to
     * @param string $email This should be the email address where emails are sent on completion of the task
     */
    public function setEmailTo($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->emailTo = filter_var($email, FILTER_SANITIZE_EMAIL);
        }
        return $this;
    }
    
    /**
     * Sets the SMTP information in order to send any emails once the task is complete
     * @param string $username This should be the username/email so the email can be sent via authenticated SMTP
     * @param string $password This should be the password so the email can be sent via authenticated SMTP
     * @param string $hostname This should be the hostname so this can be set when sending the email
     * @return $this
     */
    public function setSMTPInfo($username, $password, $hostname = false)
    {
        $this->smtpUsername = $username;
        $this->smtpPassword = $password;
        $this->emailHostname = $hostname;
        return $this;
    }
    /**
     * Checks the status of either an individual website or an array of websites
     * @param string|array $websites This should be a single website or an array of websites to check the status for
     */
    public function checkStatus($websites)
    {
        if (is_array($websites)) {
            $this->count['number'] = count($websites);
            foreach ($websites as $i => $website) {
                $this->checkDomain($website, $i);
            }
        } else {
            $this->count['number'] = 1;
            $this->checkDomain($websites);
        }
        $this->sendEmail();
        return $this->siteInfo;
    }
    
    /**
     * Checks a single domain and stores the results if that option is set
     * @param string $website This should be the domain name
     * @param int $i If multiple websites are being checked this will be an incrementing integer
     */
    protected function checkDomain($website, $i = 0)
    {
        $this->siteInfo[$i]['domain'] = $website;
        $this->siteInfo[$i]['status'] = $this->getWebsite($website);
        if ($this->siteInfo[$i]['status'] == 200) {
            $this->count['ok']++;
        } else {
            $this->count['issues']++;
            $this->count['problem_domains'][] = $website;
        }
        if ($this->siteInfo[$i]['status'] == 200 && $this->getSSLInfo()) {
            $this->siteInfo[$i]['cert'] = $this->getSSLCert($website);
        }
        $this->storeResultsinDB(
            $this->siteInfo[$i]['domain'],
            $this->siteInfo[$i]['status'],
            (!empty($this->siteInfo[$i]['cert']) ? $this->siteInfo[$i]['cert'] : null)
        );
        return $this->siteInfo;
    }

    /**
     * Gets the website headers using curl
     * @param string $url This should be the website address
     * @return int The website status code will be returned
     */
    protected function getWebsite($url)
    {
        try{
            $responce = $this->client->request('GET', $url, ['http_errors' => false/*, 'verify' => false*/]);
            return $responce->getStatusCode();
        }
        catch (Exception $e) {
            echo "Caught exception: {$e->getMessage()}\n";
        }
    }
    
    /**
     * Retrieves the websites SSL certificate and retrieves the certificate information
     * @param string $url This should the website address
     * @return array The certificate information will be returned as an array
     */
    protected function getSSLCert($url)
    {
        $domain = 'https://'.str_replace(['http://', 'https://'], '', strtolower($url)); // Force https else it fails
        $orignal_parse = parse_url($domain, PHP_URL_HOST);
        $get = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
        $read = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
        $cert = stream_context_get_params($read);
        return openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    }
    
    /**
     * Stores the results in the database if that option is set
     * @param string $website This should be the website address
     * @param int $status This should be the status code retrieved for that website
     * @param array $certInfo This should be the certificate information in an array format retrieved from the website
     * @return boolean If the information has successfully inserted will be returned else returns false
     */
    protected function storeResultsinDB($website, $status, $certInfo)
    {
        if ($this->storeResults) {
            $ssl_expiry = is_null($certInfo) ? null : date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
            if ($ssl_expiry <= date('Y-m-d H:i:s') || is_null($certInfo)) {
                $this->count['expired']++;
                $this->count['problem_domains'][] = $website;
            }
            if ($this->db->select($this->getTableName(), ['website' => $website])) {
                return $this->db->update($this->getTableName(), [
                    'status' => $status,
                    'ssl_expiry' => $ssl_expiry
                ], ['website' => $website], 1);
            }
            return $this->db->insert($this->getTableName(), [
                'website' => $website,
                'status' => $status,
                'ssl_expiry' => $ssl_expiry
            ]);
        }
        return false;
    }
    
    /**
     * Gets all of the results from the database to return as an array
     * @return array The results will be return as an array
     */
    public function getResults()
    {
        return $this->db->selectAll($this->getTableName());
    }
    
    /**
     * Empty the database so the new results can be added
     * @return boolean If the database is successfully truncated will return true else will return false
     */
    public function emptyResults()
    {
        return $this->db->truncate($this->getTableName());
    }

    /**
     * Sends the emails if that option is set
     * @return boolean If the email is sent successfully will return true else returns false
     */
    protected function sendEmail()
    {
        if ($this->emailResults) {
            include dirname(__DIR__).'/email/domain-status-check-email.php';
            $email = new PHPMailer();
            $email->SMTPAuth = true;
            $email->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $email->Username = $this->smtpUsername;
            $email->Password = $this->smtpPassword;
            if (!empty($this->emailHostname)) {
                $email->IsSMTP();
                $email->Host = $this->emailHostname;
            }
            $email->SetFrom($this->from, $this->fromName);
            $email->AddAddress($this->emailTo);
            $email->Subject = sprintf($subject, $this->count['issues']);
            $email->MsgHTML(sprintf($html, $this->count['number'], $this->count['issues'], $this->count['expired'], implode("</strong><br />\n<strong>", $this->count['problem_domains'])));
            return $email->Send() ? true : false;
        }
        return false;
    }
}
