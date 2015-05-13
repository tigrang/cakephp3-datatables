<?php
namespace DataTables\View;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\View\JsonView;

class DataTablesResponseView extends JsonView
{
    /**
     * DataTable views are always located in the 'datatable' sub directory for a
     * controllers views.
     *
     * @var string
     */
    public $subDir = 'datatables';

    /**
     * Constructor
     *
     * @param \Cake\Network\Request $request Request instance.
     * @param \Cake\Network\Response $response Response instance.
     * @param \Cake\Event\EventManager $eventManager EventManager instance.
     * @param array $viewOptions An array of view options
     */
    public function __construct(
        Request $request = null,
        Response $response = null,
        EventManager $eventManager = null,
        array $viewOptions = []
    ) {
        parent::__construct($request, $response, $eventManager, $viewOptions);
        $this->dtResponse = $this->viewVars['dataTableData'];
    }

    /**
     * Renders file and returns json-encoded response
     *
     * @param string|null $view Name of view file to use
     * @param string|null $layout Layout to use.
     * @return string|void Rendered content or null if content already rendered and returned earlier.
     */
    public function render($view = null, $layout = null)
    {
        parent::render($view, $layout);
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && Configure::read('debug')) {
            $options = JSON_PRETTY_PRINT;
        }
        return json_encode($this->dtResponse, $options);
    }
}
