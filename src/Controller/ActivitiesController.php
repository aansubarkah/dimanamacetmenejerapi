<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

/**
 * Activities Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 * @property \App\Model\Table\MarkersTable $Markers
 */
class ActivitiesController extends AppController
{
    //public $uses = false;
    //protected $Users = null;
    //protected $markers = null;

    public function initialize() {
        parent::initialize();
        //$this->loadModel('Users');
        //$this->Users = TableRegistry::get('Users');
    }

    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $id = $this->Auth->user('id');

        // user total post
        $userTotal = $this->Activities->find();
        $userTotal->where([
            'user_id' => $id,
            'active' => 1
        ]);
        $userTotal->select(['sum' => $userTotal->func()->sum('value')])->first();
        $total = 0;
        foreach($userTotal as $key) {
            if(!empty($key['sum'])) {
                $total = $key['sum'];
            }
        }

        // user total post in this week
        $userTotalWeek = $this->Activities->find();
        $userTotalWeek->where([
            'user_id' => $id,
            'DATE(occured) >' => date('Y-m-d', strtotime('-7 days')),
            'active' => 1
        ]);
        $userTotalWeek->select(['sum' => $userTotalWeek->func()->sum('value')])->first();
        $totalWeek = 0;
        foreach($userTotalWeek as $key) {
            if(!empty($key['sum'])) {
                $totalWeek = $key['sum'];
            }
        }

        // user count 6 days before today
        $weekly = [];
        $totalDay = 0;
        for($i = 0; $i < 6; $i++) {
            $days = '-' . (6-$i) . ' days';
            $date = date('Y-m-d', strtotime($days));
            $userTotalDay = $this->Activities->find();
            $userTotalDay->where([
                'user_id' => $id,
                'DATE(occured)' => $date,
                'active' => 1
            ]);
            $userTotalDay->select(['value']);
            $userTotalDay->first();

            foreach($userTotalDay as $key) {
                $totalDay = $key['value'];
            }

            $weekly[] = [
                'id' => $i+1,
                'name' => $date,
                'value' => $totalDay
            ];
        }

        $userTotalToday = $this->Activities->Users->Markers->find()
            ->where([
                'user_id' => $id,
                'active' => 1,
                'DATE(created)' => date('Y-m-d')
            ])
            ->count();

        $weekly[] = [
            'id' => 7,
            'name' => date('Y-m-d'),
            'value' => $userTotalToday
        ];

        $meta = [
            'total' => $total + $userTotalToday,
            'totalWeek' => $totalWeek + $userTotalToday,
            'totalToday' => $userTotalToday
        ];

        $this->set([
            'activities' => $weekly,
            'meta' => $meta,
            '_serialize' => ['activities', 'meta']
        ]);
    }

    /**
     * View method
     *
     * @param string|null $id Activity id.
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function view($id = null)
    {
        $id = $this->Auth->user('id');

        $userTotal = $this->Users->Markers->find()
            ->where([
                'AND' => [
                    ['Markers.user_id' => $id],
                    ['Markers.active' => 1]
                ]
            ])
            ->count();

        $userTotalWeek = $this->Users->Markers->find()
            ->where([
                'AND' => [
                    ['Markers.user_id' => $id],
                    ['Markers.active' => 1],
                    ['DATE(Markers.created) >' => date('Y-m-d', strtotime('-7 days'))]
                ]
            ])
            ->count();


        // user count 7 days
        $weekly = [];
        for($i = 0; $i < 7; $i++) {
            $days = '-' . (6-$i) . ' days';
            $date = date('Y-m-d', strtotime($days));
            $userRowsCount = $this->Users->Markers->find()
                ->where([
                    'AND' => [
                        ['Markers.user_id' => $id],
                        ['Date(Markers.created)' => $date],
                        ['Markers.active' => 1]
                    ]
                ])
                ->count();
            $weekly[] = [
                'id' => $i+1,
                'name' => $date,
                'value' => $userRowsCount
            ];
        }

        $meta = [
            'total' => $userTotal,
            'totalWeek' => $userTotalWeek
        ];

        $this->set([
            'activities' => $weekly,
            'meta' => $meta,
            '_serialize' => ['activities', 'meta']
        ]);
    }

    /**
     * Add method
     *
     * @return void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $activity = $this->Activities->newEntity();
        if ($this->request->is('post')) {
            $activity = $this->Activities->patchEntity($activity, $this->request->data);
            if ($this->Activities->save($activity)) {
                $this->Flash->success(__('The activity has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The activity could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('activity'));
        $this->set('_serialize', ['activity']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Activity id.
     * @return void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $activity = $this->Activities->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $activity = $this->Activities->patchEntity($activity, $this->request->data);
            if ($this->Activities->save($activity)) {
                $this->Flash->success(__('The activity has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The activity could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('activity'));
        $this->set('_serialize', ['activity']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Activity id.
     * @return void Redirects to index.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $activity = $this->Activities->get($id);
        if ($this->Activities->delete($activity)) {
            $this->Flash->success(__('The activity has been deleted.'));
        } else {
            $this->Flash->error(__('The activity could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
