<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\HierarchiesTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\HierarchiesTable Test Case
 */
class HierarchiesTableTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.hierarchies',
        'app.regencies',
        'app.states',
        'app.regions',
        'app.users',
        'app.groups',
        'app.markers',
        'app.categories',
        'app.markerviews',
        'app.respondents',
        'app.sources',
        'app.weathers',
        'app.weather',
        'app.activities'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('Hierarchies') ? [] : ['className' => 'App\Model\Table\HierarchiesTable'];
        $this->Hierarchies = TableRegistry::get('Hierarchies', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Hierarchies);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
