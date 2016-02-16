<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Sources Controller
 *
 * @property \App\Model\Table\SourcesTable $Sources
 */
class SourcesController extends AppController
{

    public $limit = 25;
    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $respondent_id = null;
        if (!empty($this->request->query['respondentID'])) {
            $respondent_id = $this->request->query['respondentID'];
        }

        $region_id = 1;
        $conditions = [];
        $sources = [];
        $total = 0;

        // limiting query
        $limit = $this->limit;
        if (isset($this->request->query['limit'])) {
            if (is_numeric($this->request->query['limit'])) {
                $limit = $this->request->query['limit'];
            }
        }

        $page = 1;
        $offset = 0;
        if (isset($this->request->query['page'])) {
            if (is_numeric($this->request->query['page'])) {
                $page = (int)$this->request->query['page'];
                $offset = ($page - 1) * $limit;
            }
        }

        if (isset($this->request->query['query'])) {
            $query = trim($this->request->query['query']);
            if (!empty($query)) {
                //$page = 1;
                //$offset = 0;
                $conditions[] = ['LOWER(info) LIKE' => '%' . strtolower($query) . '%'];
            }
        }
        // get user region
        $user = $this->Sources->Regions->Users->get($this->Auth->user('id'));

        if(!empty($respondent_id)) {
            $conditions[]=['respondent_id' => $respondent_id];
        } else {
            if ($user['region_id'] !== 1) {
                $conditions['OR'] = [
                    ['region_id' => 1],
                    ['region_id' => $user['region_id']]
                ];
            } else {
                $conditions[] = ['region_id' => 1];
            }
        }

        $conditions[] = ['active' => 1];
        $conditions[] = ['isImported' => 0];

        $sources = $this->Sources->find()
            ->where($conditions)
            ->order(['twitTime' => 'DESC'])
            ->limit($limit)->page($page)->offset($offset)
            ->toArray();
        $totalSources = $this->Sources->find()->where($conditions);
        $total = $totalSources->count();

        $meta = [
            'total' => $total
        ];
        $this->set([
            'sources' => $sources,
            'meta' => $meta,
            '_serialize' => ['sources', 'meta']
        ]);
    }

    /**
     * User Timeline method
     *
     * @return void
     */
    public function timeline() {

    }

    /**
     * View method
     *
     * @param string|null $id Source id.
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function view($id = null)
    {
        $source = $this->Sources->get($id);
        $this->set('source', $source);
        $this->set('_serialize', ['source']);
    }

    /**
     * Add method
     *
     * @return void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $source = $this->Sources->newEntity();
        if ($this->request->is('post')) {
            $source = $this->Sources->patchEntity($source, $this->request->data);
            if ($this->Sources->save($source)) {
                $this->Flash->success(__('The source has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The source could not be saved. Please, try again.'));
            }
        }
        $respondents = $this->Sources->Respondents->find('list', ['limit' => 200]);
        $regions = $this->Sources->Regions->find('list', ['limit' => 200]);
        $this->set(compact('source', 'respondents', 'regions'));
        $this->set('_serialize', ['source']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Source id.
     * @return void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $source = $this->Sources->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $source = $this->Sources->patchEntity($source, $this->request->data);
            if ($this->Sources->save($source)) {
                $this->Flash->success(__('The source has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The source could not be saved. Please, try again.'));
            }
        }
        $respondents = $this->Sources->Respondents->find('list', ['limit' => 200]);
        $regions = $this->Sources->Regions->find('list', ['limit' => 200]);
        $this->set(compact('source', 'respondents', 'regions'));
        $this->set('_serialize', ['source']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Source id.
     * @return void Redirects to index.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $source = $this->Sources->get($id);
        if ($this->request->is(['delete'])) {
            $source->active = false;
            if ($this->Sources->save($source)) {
                //$message = 'Deleted';
                $message = $source;
            } else {
                $message = 'Error';
            }
        }
        $this->set([
            'source' => $message,
            '_serialize' => ['source']
        ]);
    }
}
