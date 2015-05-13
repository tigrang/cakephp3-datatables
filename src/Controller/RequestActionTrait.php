<?php
namespace DataTables\Controller;

trait RequestActionTrait
{
    /**
     * Paginates the current request based on the `config` query key
     *
     * @return void
     */
    public function processDataTablesRequest()
    {
        $config = $this->request->query('config');
        if (method_exists($this, $config)) {
            $this->setAction($config);
            return;
        }
        $this->DataTables->paginate(null, $config);
    }
}
