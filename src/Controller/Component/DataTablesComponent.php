<?php
namespace DataTables\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use DataTables\Controller\Component\Config\DataTablesConfig;

/**
 * DataTables component
 */
class DataTablesComponent extends Component
{
    /**
     * Events supported by this component.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [];
    }

    /**
     * Returns DataTablesConfig object for the given config name
     *
     * @param string $name Name of config
     * @return DataTablesConfig
     */
    public function getConfig($name)
    {
        if ($name === null) {
            $name = $this->_registry->getController()->request->params['action'];
        }
        return new DataTablesConfig($name, $this->config());
    }

    /**
     * Paginates the given query and/or config name
     *
     * @param Query $query Custom query to paginate on
     * @param string $name Name of the config to use
     * @param array $options Extra options to merge
     * @return void
     */
    public function paginate(Query $query = null, $name = null, array $options = [])
    {
        $config = $this->getConfig($name);
        $config->config($options);

        if (!$query) {
            $table = \Cake\ORM\TableRegistry::get($config->config('table'));
            $query = $table->find($config->config('finder'));
        }

        $query->applyOptions($config->config());

        $iTotalRecords = $query->count();

        $this->_paginate($query, $config);
        $this->_search($query, $config);
        $this->_sort($query, $config);

        $iTotalDisplayRecords = $query->count();

        $autoData = $config->config('autoData');
        $autoRender = $config->config('autoRender');

        $results = $query->hydrate(!$autoData)->toArray();

        $aaData = [];
        if ($autoData) {
            $associations = $query->repository()->associations();
            foreach ($results as $result) {
                $row = [];
                foreach ($config->config('columns') as $column => $options) {
                    if (!$options['useField']) {
                        $row[] = null;
                        break;
                    }

                    $path = explode('.', $column);
                    if ($path[0] === $query->repository()->alias()) {
                        unset($path[0]);
                    } else {
                        $path[0] = $associations->get($path[0])->property();
                    }
                    $value = Hash::extract($result, implode('.', $path));
                    $row[] = $value ? $value[0] : null;
                }
                $aaData[] = $row;
            }
        }
        $dataTableData = [
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'sEcho' => (int)Hash::get($this->_getParams(), 'sEcho'),
            'aaData' => $aaData,
        ];

        $controller = $this->_registry->getController();
        $controller->set(compact('dataTableData'));

        if ($autoData && $autoRender) {
            $controller->viewClass = 'Json';
            $controller->set('_serialize', 'dataTableData');
        } else {
            $controller->viewClass = 'DataTables.DataTablesResponse';
            $controller->view = $config->config('view');
            $controller->set($config->config('viewVar'), $results);
        }
    }

    /**
     * Sets view vars needed for the helper
     *
     * @param array|string $names Names of the configs to parse for view
     * @return void
     */
    public function setViewVar($names)
    {
        $dtColumns = [];
        foreach ((array)$names as $name) {
            $dtColumns[$name] = $this->getConfig($name)->config('columns');
        }
        $this->_registry->getController()->set(compact('dtColumns'));
    }

    /**
     * Sets pagination limit and page
     *
     * @param Query $query Query to use to paginate
     * @param DataTableConfig $config Config to use for pagination settings
     * @return void
     */
    protected function _paginate(Query $query, DataTablesConfig $config)
    {
        $params = $this->_getParams();
        if (!isset($params['iDisplayLength'], $params['iDisplayStart'])) {
            $query->limit($config->config('maxLimit'));
            return;
        }
        $query->limit(min($params['iDisplayLength'], $config->config('maxLimit')));
        $query->offset($params['iDisplayStart']);
    }

    /**
     * Adds conditions to filter results
     *
     * @param Query $query Query to search on
     * @param DataTableConfig $config Config to use for column info
     * @return void
     */
    protected function _search(Query $query, DataTablesConfig $config)
    {
        $i = 0;
        $conditions = $types = [];
        $params = $this->_getParams();
        $searchTerm = Hash::get($params, 'sSearch');
        foreach ($config->config('columns') as $column => $options) {
            if ($options['useField']) {
                $searchable = $options['bSearchable'];
                if ($searchable === false) {
                    continue;
                }
                $searchKey = "sSearch_$i";
                $columnSearchTerm = Hash::get($params, $searchKey);

                $types[$column] = Hash::get($options, 'type', 'string');

                if ($searchTerm && ($searchable === true || $searchable === DataTableConfig::SEARCH_GLOBAL)) {
                    $conditions[] = ["$column LIKE" => '%' . $searchTerm . '%'];
                }
                if ($columnSearchTerm && ($searchable === true || $searchable === DataTableConfig::SEARCH_COLUMN)) {
                    $conditions[] = ["$column LIKE" => '%' . $columnSearchTerm . '%'];
                }
                if (is_callable([$query->repository(), $searchable])) {
                    $query->repository()->$searchable($query, $config, $column, $searchTerm, $columnSearchTerm);
                }
            }
            $i++;
        }
        if (empty($conditions)) {
            return;
        }
        $query->andWhere(['OR' => $conditions], $types);
    }

    /**
     * Sets sort field and direction
     *
     * @param Query $query Query to sort on
     * @param DataTableConfig $config Config to use for column info
     * @return void
     */
    protected function _sort(Query $query, DataTablesConfig $config)
    {
        $params = $this->_getParams();
        $columns = $config->config('columns');
        $count = count($columns);
        for ($i = 0; $i < $count; $i++) {
            $sortColKey = "iSortCol_$i";
            if (!isset($params[$sortColKey])) {
                continue;
            }

            $column = Hash::get(array_keys($columns), $params[$sortColKey]);
            if (!$column || !$columns[$column]['bSortable']) {
                continue;
            }

            $direction = Hash::get($params, "sSortDir_$i");
            if (!in_array(strtolower($direction), ['asc', 'desc'])) {
                $direction = 'asc';
            }
            $query->order([$column => $direction]);
        }
    }

    /**
     * Gets datatable request params
     *
     * @return array
     */
    protected function _getParams()
    {
        $request = $name = $this->_registry->getController()->request;
        $property = $request->is('get') ? 'query' : 'data';
        return $request->{$property};
    }
}
