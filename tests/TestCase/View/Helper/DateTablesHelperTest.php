<?php
namespace DataTables\Test\TestCase\View\Helper;

use Cake\TestSuite\TestCase;
use Cake\View\View;
use DataTables\View\Helper\DateTablesHelper;

/**
 * DataTables\View\Helper\DateTablesHelper Test Case
 */
class DateTablesHelperTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $view = new View();
        $this->DateTables = new DateTablesHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->DateTables);

        parent::tearDown();
    }

    /**
     * Test initial setup
     *
     * @return void
     */
    public function testInitialization()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
