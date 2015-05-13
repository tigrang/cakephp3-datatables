<?php
namespace DataTables\View\Helper;

use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\View\Helper\HtmlHelper;
use Cake\View\View;

/**
 * DataTables helper
 */
class DataTablesHelper extends HtmlHelper
{
    /**
     * Table header labels
     *
     * @var array
     */
    protected $_labels = [];

    /**
     * Column data passed from controller
     *
     * @var array
     */
    protected $_dtColumns;

    /**
     * Javascript settings for all pagination configs
     *
     * @var
     */
    protected $_dtSettings = [];

    /**
     * Default Constructor
     *
     * @param \Cake\View\View $View The View this helper is being attached to.
     * @param array $config Configuration settings for the helper.
     */
    public function __construct(View $View, array $config = [])
    {
        parent::__construct($View, $config);
        if (isset($View->viewVars['dtColumns'])) {
            foreach ($View->viewVars['dtColumns'] as $config => $columns) {
                $this->_parseSettings($config, $columns);
            }
        }

        $this->config([
            'table' => [
                'class' => 'dataTable',
                'trOptions' => [],
                'thOptions' => [],
                'theadOptions' => [],
                'tbody' => '',
                'tbodyOptions' => [],
                'tfoot' => '',
                'tfootOptions' => [],
            ],
            'scriptBlock' => 'script',
            'js' => [
                'aoColumns' => true,
                'sAjaxSource' => ['action' => 'processDataTablesRequest'],
                'bServerSide' => true,
            ],
        ]);
    }

    /**
     * Event listeners.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return ['View.afterRender' => 'afterRender'];
    }

    /**
     * Output dataTable settings to script block
     *
     * @param Event $event The View.afterRender event
     * @param string $viewFile View file being rendered
     * @return void
     */
    public function afterRender(Event $event, $viewFile)
    {
        $jsVar = sprintf('var dataTablesSettings = %s;', json_encode($this->_dtSettings));
        $this->scriptBlock($jsVar, ['block' => 'dataTablesSettings']);
        if ($this->settings['scriptBlock'] !== false) {
            $initScript = <<< INIT_SCRIPT
$(document).ready(function() {
    $('.dataTable').each(function() {
        var table = $(this);
        var settings = dataTablesSettings[table.attr('data-config')];
        table.dataTable(settings);
    });
});
INIT_SCRIPT;
            $this->scriptBlock($initScript, ['block' => $this->config('scriptBlock')]);
        }
    }

    /**
     * Renders a DataTable
     *
     * Options take on the following values:
     * - `class` For table. Default: `dataTable`
     * - `trOptions` Array of options for tr
     * - `thOptions` Array of options for th
     * - `theadOptions` Array of options for thead
     * - `tbody` Content for tbody
     * - `tbodyOptions` Array of options for tbody
     *
     * The rest of the keys wil be passed as options for the table
     *
     * @param string $config Config to render
     * @param array $options Options for table
     * @param array $js Options for js var
     * @return string
     */
    public function render($config = null, $options = [], $js = [])
    {
        if ($config === null) {
            $config = current(array_keys($this->request->params['models']));
        }

        $options = array_merge($this->config('table'), $options);
        $trOptions = $options['trOptions'];
        $thOptions = $options['thOptions'];
        unset($options['trOptions'], $options['thOptions']);
        
        $theadOptions = $options['theadOptions'];
        $tbodyOptions = $options['tbodyOptions'];
        $tfootOptions = $options['tfootOptions'];
        unset($options['theadOptions'], $options['tbodyOptions'], $options['tfootOptions']);
        
        $tbody = $options['tbody'];
        $tfoot = $options['tfoot'];
        unset($options['tbody'], $options['tfoot']);
        
        $tableHeaders = $this->tableHeaders($this->_labels[$config], $thOptions, $trOptions);

        $tableHead = $this->tag('thead', $tableHeaders, $theadOptions);
        $tableBody = $this->tag('tbody', $tbody, $tbodyOptions);
        $tableFooter = $this->tag('tfoot', $tfoot, $tfootOptions);
        
        $options['data-config'] = $config;
        
        $table = $this->tag('table', $tableHead . $tableBody . $tableFooter, $options);
        $this->jsSettings($config, $js);
        return $table;
    }

    /**
     * Sets label at the given index.
     *
     * @param string $config Name of the config to use
     * @param int $index of column to change
     * @param string $label new label to be set. `__LABEL__` string will be replaced by the original label
     * @return void
     */
    public function setLabel($config, $index, $label)
    {
        $oldLabel = $this->_labels[$config][$index];
        $oldOptions = $options = [];
        if (is_array($oldLabel)) {
            list($oldLabel, $oldOptions) = $oldLabel;
        }
        if (is_array($label)) {
            list($label, $options) = $label;
        }
        $this->_labels[$config][$index] = [
            $this->_parseLabel($label, $oldLabel),
            array_merge($oldOptions, $options),
        ];
    }

    /**
     * Returns js settings either as an array or json-encoded string
     *
     * @param array $config Name of the config to use
     * @param array $settings Settings
     * @param bool $encode Whether to json encode or not
     * @return array|string
     */
    public function jsSettings($config, $settings = [], $encode = false)
    {
        $settings = array_merge($this->config('js'), (array)$settings);
        if (!empty($settings['bServerSide'])) {
            if (!isset($settings['sAjaxSource']) || $settings['sAjaxSource'] === true) {
                $settings['sAjaxSource'] = $this->request->here();
            }
            if (!is_string($settings['sAjaxSource'])) {
                $settings['sAjaxSource']['?']['config'] = $config;
                $settings['sAjaxSource'] = Router::url($settings['sAjaxSource']);
            }
        }
        if (isset($settings['aoColumns']) && $settings['aoColumns'] === true) {
            $settings['aoColumns'] = $this->_dtColumns[$config];
        }
        $this->_dtSettings[$config] = $settings;
        return ($encode) ? json_encode($settings) : $settings;
    }

    /**
     * Parse a label with its options
     *
     * @param string $label Label
     * @param string $oldLabel Old Label
     * @return string
     */
    protected function _parseLabel($label, $oldLabel = '')
    {
        $replacements = [
            '__CHECKBOX__' => '<input type="checkbox" class="check-all">',
            '__LABEL__' => $oldLabel,
        ];
        foreach ($replacements as $search => $replace) {
            $label = str_replace($search, $replace, $label);
        }
        return $label;
    }

    /**
     * Parse settings
     *
     * @param string $config Name of the config to use
     * @param array $columns Columns
     * @return array
     */
    protected function _parseSettings($config, $columns)
    {
        foreach ($columns as $field => $options) {
            if ($options === null) {
                $label = $field;
                $options = [
                    'bSearchable' => false,
                    'bSortable' => false,
                ];
            } else {
                $label = $options['label'];
                unset($options['label']);
                if (isset($options['bSearchable'])) {
                    $options['bSearchable'] = (bool)$options['bSearchable'];
                }
            }
            $this->_labels[$config][] = $this->_parseLabel($label);
            $this->_dtColumns[$config][] = $options;
        }
        return $this->_dtColumns[$config];
    }
}
