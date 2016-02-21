<?php
namespace App\Controller;
use Cake\I18n\I18n;//cakephp need this to save datetime field
use Cake\I18n\Time;//cakephp need this to save datetime field
use Cake\Database\Type;//cakephp need this to save datetime field
use TwitterAPIExchange;
use Cake\ORM\TableRegistry;

use App\Controller\AppController;

/**
 * Markers Controller
 *
 * @property \App\Model\Table\MarkersTable $Markers
 */
class MarkersController extends AppController
{
    public $limit = 25;

    public $Places = null;

    public $paginate = [
        'fields' => ['Markers.id', 'Markers.name', 'Markers.active'],
        'limit' => 0,
        'page' => 0,
        'order' => [
            'Markers.name' => 'asc'
        ]
    ];

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('Paginator');
        //$this->loadComponent('Twitter');
    }

    /*public function twit()
    {
        $data = $this->Twitter->getTweets();

        $this->set([
            'data' => $data,
            '_serialize' => ['data']
        ]);
    }

    public function twit1()
    {
        $data = $this->Twitter->getMention();
        //$data = $this->Twitter->getSearch('e100ss');

        $this->set([
            'data' => $data,
            '_serialize' => ['data']
        ]);
    }

    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $lastMinutes = 30;//default is past 30 minutes
        if (isset($this->request->query['lastminutes'])) {
            if (is_numeric($this->request->query['lastminutes'])) {
                $lastMinutes = $this->request->query['lastminutes'];
            }
        }
        $lastMinutesString = '-' . $lastMinutes . ' minutes';

        $fetchDataOptions = [
            'conditions' => [
                'Markers.active' => true,
                'OR' => [
                    'Markers.created >=' => date('Y-m-d H:i:s', strtotime($lastMinutesString)),
                    'AND' => [
                        'Markers.pinned' => true,
                        'Markers.cleared' => false,
                    ]

                ],

            ],
            'order' => ['Markers.created' => 'DESC'],
        ];

        $markers = $this->Markers->find('all', $fetchDataOptions);
        $markers = $markers->toArray();

        $countMarkers = count($markers);
        for ($i = 0; $i < $countMarkers; $i++) {
            $markers[$i]['category'] = $markers[$i]['category_id'];
            $markers[$i]['weather'] = $markers[$i]['weather_id'];
        }

        $allMarkers = $this->Markers->find('all', $fetchDataOptions);
        $total = $allMarkers->count();

        $meta = [
            'total' => $total
        ];
        $this->set([
            'markers' => $markers,
            'meta' => $meta,
            '_serialize' => ['markers', 'meta']
        ]);
    }

    /**
     * View method
     *
     * @param string|null $id Marker id.
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function view($id = null)
    {
        $marker = $this->Markers->get($id);
        $this->set([
            'marker' => $marker,
            '_serialize' => ['marker']
        ]);
    }

    /**
     * Add From Sources Table method
     *
     * @return void Redirects on successful add, renders view otherwise.
     */
    private function addFromSources()
    {
        if ($this->request->is('post')) {
            if (isset($this->request->data['marker']['active'])) unset($this->request->data['marker']['active']);
            $this->request->data['marker']['active'] = true;
            if (isset($this->request->data['marker']['cleared'])) unset($this->request->data['marker']['cleared']);
            $this->request->data['marker']['cleared'] = false;
            unset($this->request->data['marker']['id']);
            unset($this->request->data['marker']['created']);
            unset($this->request->data['marker']['modified']);

            //$now = date('Y-m-d H:i:s');
            $this->request->data['marker']['twitTime'] = new Time($this->request->data['marker']['twitTime']) ;

            $this->request->data['marker']['user_id'] = $this->Auth->user('id');
            $marker = $this->Markers->newEntity($this->request->data['marker']);
            $this->Markers->save($marker);

            // add places
            $this->savePlace($this->request->data['marker']['twitPlaceName'], $this->request->data['marker']['lat'], $this->request->data['marker']['lng']);

            // update sources table
            $this->updateSource($this->request->data['marker']['twitID'], $this->request->data['marker']['twitPlaceName'], $this->request->data['marker']['lat'], $this->request->data['marker']['lng']);

            // post tweet
            $this->convertPostToTweet($marker->id);

            $this->set([
                'marker' => $marker,
                '_serialize' => ['marker']
            ]);
        }
    }

    private function savePlace($placeName = null, $lat = null, $lng = null)
    {
        if ($lat !== null && $lng !== null) {
            $this->Places = TableRegistry::get('Places');

            // first check if place exist on table
            $placeCount = $this->Places->find()
                ->where(['lat' => $lat, 'lng' => $lng])
                ->count();

            if ($placeCount === 0) {
                $dataToSave = [
                    'name' => $placeName,
                    'lat' => $lat,
                    'lng' => $lng,
                    'active' => 1
                ];

                $place = $this->Places->newEntity($dataToSave);
                $this->Places->save($place);
            }
        }
    }

    private function updateSource($twitID = null, $placeName = null, $lat = null, $lng = null) {
        if ($twitID !== null) {
            $query = $this->Markers->Categories->Sources->query();
            $query->update()
                ->set([
                    'isImported' => true,
                    'placeName' => $placeName,
                    'lat' => $lat,
                    'lng' => $lng
                ])
                ->where(['twitID' => $twitID])
                ->execute();
        }
    }

    /**
     * Add method
     *
     * @return void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        if ($this->request->is('post')) {
            if ($this->request->data['marker']['twitPlaceName'] === null) {
                if (isset($this->request->data['marker']['active'])) unset($this->request->data['marker']['active']);
                $this->request->data['marker']['active'] = true;
                if (isset($this->request->data['marker']['cleared'])) unset($this->request->data['marker']['cleared']);
                $this->request->data['marker']['cleared'] = false;
                unset($this->request->data['marker']['id']);
                unset($this->request->data['marker']['created']);
                unset($this->request->data['marker']['modified']);

                $now = date('Y-m-d H:i:s');
                $this->request->data['marker']['twitTime'] = new Time($now);

                // if respondent not saved yet, create it first
                if ($this->request->data['marker']['respondent_id'] === 0) {
                    $respondentToSave = [
                        'name' => $this->request->data['marker']['respondentName'],
                        'contact' => $this->request->data['marker']['respondentContact'],
                        'active' => 1
                    ];
                    $respondent = $this->Markers->Respondents->newEntity($respondentToSave);
                    $this->Markers->Respondents->save($respondent);

                    $this->request->data['marker']['respondent_id'] = $respondent->id;
                }

                //$this->request->data['marker']['user_id'] = 1;
                $marker = $this->Markers->newEntity($this->request->data['marker']);
                $this->Markers->save($marker);

                // post tweet
                $this->convertPostToTweet($marker->id);

                $this->set([
                    'marker' => $marker,
                    '_serialize' => ['marker']
                ]);
            } else {
                $this->addFromSources();
            }
        }
    }
    //public function convertPostToTweet($id = null) {
    private function convertPostToTweet($id = null) {
        //public function convertPostToTweet($id = null) {
        //$this->autoRender = false;

        if($id !== null) {
            $marker = $this->Markers->find()
                ->contain(['Respondents', 'Categories'])
                ->select(['Markers.lat', 'Markers.lng', 'Markers.info', 'Markers.twitTime', 'Respondents.name', 'Categories.name'])
                ->where(['Markers.id' => $id])
                ->first();

            $this->postTweet($marker['info'], $marker['lat'], $marker['lng'], $marker['respondent']['name'], $marker['category']['name'], $marker['twitTime']);
        }
    }

    private function postTweet($info = null, $lat = null, $lng = null, $respondent = null, $category = null, $time = null) {
        $Twitter = new TwitterAPIExchange($this->settingsTwitter);

        $url = $this->baseTwitterUrl . 'statuses/update.json';

        $lat === null ? $lat = -7.256177 : $lat = $lat;
        $lng === null ? $long = 112.752268 : $long = $lng;
        $category = null ? $category = '#macet' : $category = '#' . strtolower($category);
        $status = 'dimanamacet.com (' . date('H:i', strtotime($time)) . ') ' . $info;
        $status = $status . ' via: ' . $respondent;
        $status = $status . ' ' . $category . ' #dimanamacetid';

        $postfield = '?status=' . $status;
        $postfield = $postfield . '&lat=' . $lat;
        $postfield = $postfield . '&long=' . $long;
        $postFields = [
            'status' => $status,
            'lat' => $lat,
            'long' => $long
        ];

        $requestMethod = 'POST';

        /*$this->set([
                    'marker' => $status,
                    '_serialize' => ['marker']
    ]);*/

    $exec = $Twitter->setPostfields($postFields)
        ->buildOauth($url, $requestMethod)
        ->performRequest();
    //echo $exec;
    }

    /**
     * Edit method
     *
     * @param string|null $id Marker id.
     * @return void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $marker = $this->Markers->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            if (isset($this->request->data['marker']['active'])) unset($this->request->data['marker']['active']);

            $marker = $this->Markers->patchEntity($marker, $this->request->data['marker']);
            if ($this->Markers->save($marker)) {
                $message = 'Saved';
            } else {
                $message = 'Error';
            }
        }
        $this->set([
            'marker' => $message,
            '_serialize' => ['marker']
        ]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Marker id.
     * @return void Redirects to index.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $marker = $this->Markers->get($id);
        if ($this->request->is(['delete'])) {
            $marker->active = false;
            if ($this->Markers->save($marker)) {
                $message = 'Deleted';
            } else {
                $message = 'Error';
            }
        }
        $this->set([
            'marker' => $message,
            '_serialize' => ['marker']
        ]);
    }
}
