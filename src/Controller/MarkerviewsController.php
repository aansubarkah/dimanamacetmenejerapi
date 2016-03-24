<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;

/**
 * Markerviews Controller
 *
 * @property \App\Model\Table\MarkerviewsTable $Markerviews
 */
//@todo refactor this class as a respondent template
class MarkerviewsController extends AppController
{
    public $limit = 25;

    public $Markers = null;

    public $paginate = [
        'fields' => ['Markerviews.id', 'Markerviews.name', 'Markerviews.active'],
        'limit' => 25,
        'page' => 0,
        'order' => [
            'Markerviews.name' => 'asc'
        ]
    ];

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('Paginator');
    }

    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $this->Markers = TableRegistry::get('Markers');
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

        $lastMinutes = 30;//default is past 30 minutes
        if (isset($this->request->query['lastminutes'])) {
            if (is_numeric($this->request->query['lastminutes'])) {
                $lastMinutes = $this->request->query['lastminutes'];
            }
        }
        $lastMinutesString = '-' . $lastMinutes . ' minutes';
        // query by place_name
        $query = '';
        if (isset($this->request->query['query'])) {
            if (!empty(trim($this->request->query['query']))) {
                $query = trim($this->request->query['query']);
            }
        }

        $conditions = [
            'Markers.active' => true,
            'OR' => [
                'Markers.created >=' => date('Y-m-d H:i:s', strtotime($lastMinutesString)),
                'AND' => [
                    'Markers.active' => true
                ]
            ]
                        /*'OR' => [
                'Markerviews.created >=' => date('Y-m-d H:i:s', strtotime($lastMinutesString)),
                'AND' => [
                    'Markerviews.active' => true,
                    //    'Markerviews.pinned' => true,
                    //    'Markerviews.cleared' => false,
                ]
            ]*/
        ];

        if (!empty(trim($query))) {
            $conditions['LOWER(Markers.info) LIKE'] = '%' . strtolower($query) . '%';
        }

        $markers = $this->Markers->find('all')
            ->contain([
                'Categories', 'Weather', 'Respondents'
            ])
            ->select([
                'Markers.id', 'Markers.category_id', 'Markers.user_id', 'Markers.respondent_id',
                'Markers.weather_id', 'Markers.lat', 'Markers.lng', 'Markers.created', 'Markers.modified',
                'Markers.info', 'Markers.twitID', 'Markers.twitPlaceName', 'Markers.twitPlaceID',
                'Markers.twitTime', 'Markers.twitURL', 'Markers.isTwitPlacePrecise', 'Markers.twitImage',
                'Markers.pinned', 'Markers.cleared', 'Markers.active',
                'isPlaceNameExist' => 1,
                'place_name' => 'Markers.twitPlaceName',
                'category_name' => 'Categories.name',
                'respondent_name' => 'Respondents.name', 'respondent_contact' => 'Respondents.contact',
                'weather_name' => 'Weather.name'
                //'Categories.name AS category_name'
            ])
            ->where($conditions)
            //->group(['id'])
            ->order(['Markers.created' => 'DESC'])
            ->limit($limit)->page($page)->offset($offset)
            ->toArray();
        $allMarkers = $this->Markers->find()->where($conditions);
        $total = $allMarkers->count();

        /*
         * for now, it disabled
         * $countMarkerviews = count($markerviews);
        for ($i = 0; $i < $countMarkerviews; $i++) {
            $markerviews[$i]['category'] = $markerviews[$i]['category_id'];
    }
    *
         */
        // count user total posts
        // user total post
        /*$userTotal = $this->Markerviews->Users->Activities->find();
        $userTotal->where([
            'user_id' => $this->Auth->user('id'),
            'active' => 1
        ]);
        $userTotal->select(['sum' => $userTotal->func()->sum('value')])->first();
        $userTotalMarkers = 0;
        foreach($userTotal as $key) {
            if(!empty($key['sum'])) {
                $userTotalMarkers = $key['sum'];
            }
        }

        /*$userTotalMarkers = $this->Markerviews->find()
            ->where(['Markerviews.user_id' => $this->Auth->user('id')])
            ->count();

        $userTotalToday = $this->Markerviews->find()
            ->where([
                'AND' => [
                    ['Markerviews.user_id' => $this->Auth->user('id')],
                    ['DATE(Markerviews.created)' => date('Y-m-d')]
                ]
            ])
            ->count();*/

        $meta = [
            'total' => $total
        ];
        $this->set([
            'markerviews' => $markers,
            'meta' => $meta,
            '_serialize' => ['markerviews', 'meta']
        ]);
    }

    public function checkExistence($name = null, $limit = 25)
    {
        $data = [
            [
                'id' => 0,
                'name' => '',
                'active' => 0
            ]
        ];

        $fetchDataOptions = [
            'order' => ['Markerviews.name' => 'ASC'],
            'limit' => $limit
        ];

        $query = trim(strtolower($name));

        if (!empty($query)) {
            $fetchDataOptions['conditions']['LOWER(Markerviews.name) LIKE'] = '%' . $query . '%';
        }

        $markerviewview = $this->Markerviews->find('all', $fetchDataOptions);

        if ($markerviewview->count() > 0) {
            $data = $markerviewview;
        }

        $this->set([
            'markerviewview' => $data,
            '_serialize' => ['markerviewview']
        ]);
    }
}
