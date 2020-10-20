<?php

namespace SiteStatus\Tests;

use PHPUnit\Framework\TestCase;
use DBAL\Database;
use SiteStatus\Status;

class StatusTest extends TestCase
{
    
    protected $db;
    protected $status;
    
    public function setUp(): void
    {
        $this->db = new Database($GLOBALS['hostname'], $GLOBALS['username'], $GLOBALS['password'], $GLOBALS['database']);
        $this->status = new Status($this->db);
        if (!$this->db->isConnected()) {
            $this->markTestSkipped(
                'No local database connection is available'
            );
        }
        $this->db->query(file_get_contents(dirname(dirname(__FILE__)).'/database/site_status.sql'));
        $this->db->truncate($this->status->getTableName());
    }
    
    public function tearDown(): void
    {
        $this->db = null;
        $this->status = null;
    }
    
    /**
     * @covers SiteStatus\Status::__construct
     * @covers SiteStatus\Status::getTableName
     * @covers SiteStatus\Status::setTableName
     */
    public function testTableName()
    {
        $tableName = $this->status->getTableName();
        $newTableName = 'my_new_table';
        $this->assertEquals($this->status, $this->status->setTableName($newTableName));
        $this->assertNotEquals($tableName, $this->status->getTableName());
        $this->assertEquals($newTableName, $this->status->getTableName());
        $this->assertEquals($this->status, $this->status->setTableName($tableName));
    }
    
    /**
     * @covers SiteStatus\Status::__construct
     * @covers SiteStatus\Status::getSSLInfo
     * @covers SiteStatus\Status::setSSLInfo
     * @covers SiteStatus\Status::getTableName
     */
    public function testSSLInfo()
    {
        $this->assertTrue($this->status->getSSLInfo());
        $this->assertEquals($this->status, $this->status->setSSLInfo(false));
        $this->assertFalse($this->status->getSSLInfo());
        $this->assertEquals($this->status, $this->status->setSSLInfo('random_value'));
        $this->assertTrue($this->status->getSSLInfo());
    }
    
    /**
     * @covers SiteStatus\Status::__construct
     * @covers SiteStatus\Status::getDBStore
     * @covers SiteStatus\Status::setDBStore
     */
    public function testDatabaseStorage()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * @covers SiteStatus\Status::__construct
     * @covers SiteStatus\Status::getEmailResults
     * @covers SiteStatus\Status::setEmailResults
     */
    public function testEmailResults()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * @covers SiteStatus\Status::__construct
     * @covers SiteStatus\Status::checkStatus
     * @covers SiteStatus\Status::checkDomain
     * @covers SiteStatus\Status::sendEmail
     * @covers SiteStatus\Status::getSSLCert
     * @covers SiteStatus\Status::getTableName
     * @covers SiteStatus\Status::getWebsite
     * @covers SiteStatus\Status::storeResultsinDB
     * @covers SiteStatus\Status::getSSLInfo
     */
    public function testDomainStatus(){
        $checkDomain = $this->status->checkStatus('google.com')[0];
        $this->assertArrayHasKey('status', $checkDomain);
        $this->assertEquals(200, $checkDomain['status']);
//        $testNoneExistantDomain = $this->status->checkStatus('sdgsdvcjhxzfdbvcbcvb.com')[0];
//        $this->assertArrayHasKey('status', $testNoneExistantDomain);
//        $this->assertNotEquals(200, $testNoneExistantDomain['status']);
    }
}
