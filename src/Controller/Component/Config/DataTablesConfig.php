<?php
namespace DataTables\Controller\Component\Config;

use Cake\Core\InstanceConfigTrait;

/**
 * DataTables Config
 */
class DataTablesConfig
{
    use InstanceConfigTrait;

    const SEARCH_GLOBAL = 1;

    const SEARCH_COLUMN = 2;

    /**
     * @var array
     */
    protected $_defaultConfig = [
        'autoData' => true,
        'autoRender' => true,
        'finder' => 'all',
        'columns' => [],
        'conditions' => [],
        'maxLimit' => 100,
        'viewVar' => 'dtResults',
        'order' => [],
    ];

    /**
     *
     * @param string $name Name of the config to parse
     * @param array $config List of configs
     */
    public function __construct($name, $config)
    {
        if (!isset($config[$name])) {
            throw new Exception(sprintf('%s: Missing config %s', __CLASS__, $name));
        }

        $this->_defaultConfig['table'] = $this->_defaultConfig['view'] = $name;

        $thisConfig = $config[$name];
        unset($config[$name]);
        $this->config(array_merge($config, $thisConfig));
        $this->_parse();
    }

    /**
     * Converts field to table.field
     *
     * @param string $field Field to convert
     * @return string
     */
    protected function _toColumn($field)
    {
        return (strpos($field, '.') !== false) ? $field : $this->config('table') . '.' . $field;
    }

    /**
     * Parse the config specified
     *
     * @return void
     */
    protected function _parse()
    {
        $columns = $fields = [];
        foreach ($this->config('columns') as $field => $options) {
            $useField = !is_null($options);
            $enabled = $useField && (!isset($options['useField']) || $options['useField']);
            if (is_numeric($field)) {
                $field = $options;
                $options = [];
            }
            if (is_bool($options)) {
                $enabled = $options;
                $options = [];
            }
            $label = \Cake\Utility\Inflector::humanize($field);
            if (is_string($options)) {
                $label = $options;
                $options = [];
            }
            $defaults = [
                'useField' => $useField,
                'label' => $label,
                'bSortable' => $enabled,
                'bSearchable' => $enabled,
            ];
            $options = array_merge($defaults, (array)$options);
            $column = ($options['useField']) ? $this->_toColumn($field) : $field;
            $columns[$column] = $options;
            if ($options['useField']) {
                $fields[] = $column;
            }
        }
        $this->config(compact('fields'));
        $this->configShallow(compact('columns'));
    }
}
