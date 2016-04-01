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

    public $limit = 5;
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

        if (isset($this->request->query['older']) && $this->request->query['older'] == true) {
            $limit = 5;
            if (isset($this->request->query['min_id'])) {
                $minId = (int)$this->request->query['min_id'];
                if (is_numeric($minId)) {
                    $conditions[] = ['id <' => $minId];
                }
            }
        }

        if (isset($this->request->query['newer']) && $this->request->query['newer'] == true) {
            if (isset($this->request->query['max_id']) && is_numeric($this->request->query['max_id'])) {
                $conditions[] = ['id >' => $this->request->query['max_id']];
            }
        }

        $lastMinutesString = '-30 minutes';// change on production
        if (!isset($this->request->query['older']) && !isset($this->request->query['newer'])) {
            //$conditions[] = ['twitTime >=' => date('Y-m-d H:i:s', strtotime($lastMinutesString))];
            $limit = $this->limit;
        }

        if (isset($this->request->query['page'])) {
            if ($this->request->query['page'] == 1) {
            //if (is_numeric($this->request->query['page'])) {
                $page = 1;
                $offset = 0;
                //$page = (int)$this->request->query['page'];
                //$offset = ($page - 1) * $limit;
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
            }
        }

        $conditions[] = ['active' => 1];
        $conditions[] = ['isImported' => 0];

        if (isset($limit)) {
            if (isset($page)) {
                $sources = $this->Sources->find()
                    ->where($conditions)
                    ->order(['twitTime' => 'DESC'])
                    ->limit($limit)->page($page)->offset($offset)
                    ->toArray();
            } else {
                $sources = $this->Sources->find()
                    ->where($conditions)
                    ->order(['twitTime' => 'DESC'])
                    ->limit($limit)
                    ->toArray();
            }
        } else {
            $sources = $this->Sources->find()
                ->where($conditions)
                ->order(['twitTime' => 'DESC'])
                ->toArray();
        }
        $sourcesCount = count($sources);

        // max and min id on sources table
        $maxId = 0;
        $minId = 0;
        if ($sourcesCount > 0) {
            $getMaxMin = $this->maxMinId($sources);
            $maxId = $getMaxMin[0];
            $minId = $getMaxMin[1];
            //$maxId = $sources[0]['id'];
            //$minId = $sources[$sourcesCount - 1]['id'];
        } else {
            $sources[] = [
                'id' => 0,
                'respondent_id' => 0,
                'region_id' => 0
            ];
        }

        $meta = [
            'total' => $sourcesCount,
            'total_pages' => 100,
            'maxId' => $maxId,
            'minId' => $minId
        ];
        $this->set([
            'sources' => $sources,
            'meta' => $meta,
            '_serialize' => ['sources', 'meta']
        ]);
    }

    private function maxMinId($arr = null) {
        $maxId = 0;
        $minId = 0;
        if (count($arr) > 0) {
            $minId = $arr[0]['id'];
            foreach($arr as $key=>$value) {
                if ($value['id'] > $maxId) {
                    $maxId = $value['id'];
                }
                if ($value['id'] < $minId) {
                    $minId = $value['id'];
                }
            }
        }
        return [$maxId, $minId];
    }
    /**
     * Index method
     *
     * @return void
     */
    public function index1()
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

        $lastMinutes = 30;
        if (isset($this->request->query['lastminutes'])) {
            if (is_numeric($this->request->query['lastminutes'])) {
                $lastMinutes = $this->request->query['lastminutes'];
            }
        }
        $lastMinutesString = '-' . $lastMinutes . ' minutes';

        $conditions[] = ['twitTime >=' => date('Y-m-d H:i:s', strtotime($lastMinutesString))];

        if (isset($this->request->query['query'])) {
            $query = trim($this->request->query['query']);
            if (!empty($query)) {
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

        // biggest id
        $biggestId = $this->Sources->find()
            ->where($conditions)
            ->order(['twitTime' => 'DESC'])
            ->first();
        // lowest id
        $lowestId = $this->Sources->find()
            ->where($conditions)
            ->order(['twitTime' => 'ASC'])
            ->first();

        $meta = [
            'total' => $total,
            'maxId' => $biggestId['id'],
            'minId' => $lowestId['id']
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
            $source->isRelevant = false;
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
