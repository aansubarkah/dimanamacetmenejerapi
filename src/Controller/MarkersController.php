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

            $this->request->data['marker']['twitTime'] = new Time($this->request->data['marker']['twitTime']) ;

            $this->request->data['marker']['user_id'] = $this->Auth->user('id');
            $marker = $this->Markers->newEntity($this->request->data['marker']);
            $this->Markers->save($marker);

            // add places
            $this->savePlace($this->request->data['marker']['twitPlaceName'], $this->request->data['marker']['lat'], $this->request->data['marker']['lng']);

            // update sources table
            $this->updateSource($this->request->data['marker']['twitID'],
                $this->request->data['marker']['twitPlaceName'],
                $this->request->data['marker']['lat'],
                $this->request->data['marker']['lng'],
                $this->request->data['marker']['category_id'],
                $this->request->data['marker']['weather_id']);

            // post tweet
            $this->convertPostToTweetFromSource($marker->id, $this->request->data['marker']['lat'], $this->request->data['marker']['lng'], $this->request->data['marker']['respondent_id']);

            $this->set([
                'marker' => $marker,
                '_serialize' => ['marker']
            ]);
        }
    }

    public function testPlace($lat = null, $lng = null) {
        if ($lat !== null && $lng !== null) {
            $this->Places = TableRegistry::get('Places');

            // first check if place exist on table
            $options = [
                'conditions' => [
                    'CAST(lat AS DECIMAL(10,6)) =' => $lat,
                    'CAST(lng AS DECIMAL(10,6)) =' => $lng,
                    'active' => 1
                ],
                'limit' => 1
            ];
            $place = $this->Places->find('all', $options)->toArray();
            //$placeCount = $place->count();
            $placeCount = 0;

            $this->set([
                'place' => $place[0]['name'],
                'lat' => $lat,
                'marker' => $placeCount,
                '_serialize' => ['place' , 'lat', 'marker']
            ]);

        }
    }
    private function savePlace($placeName = null, $lat = null, $lng = null)
    {
        if ($lat !== null && $lng !== null) {
            $this->Places = TableRegistry::get('Places');

            $options = [
                'conditions' => [
                    'CAST(lat AS DECIMAL(10,6)) =' => $lat,
                    'CAST(lng AS DECIMAL(10,6)) =' => $lng
                ]
            ];
            // first check if place exist on table

            $place = $this->Places->find('all', $options);
            $placeCount = $place->count();

            if ($placeCount < 1) {
                $region_id = $this->Auth->user('region_id');
                $dataToSave = [
                    'name' => $placeName,
                    'region_id' => $region_id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'active' => 1
                ];

                $place = $this->Places->newEntity($dataToSave);
                $this->Places->save($place);
            }
        }
    }

    private function updateSource($twitID = null, $placeName = null, $lat = null, $lng = null, $category_id = 1, $weather_id = 1) {
        if ($twitID !== null) {
            $weather = $this->Markers->Weather->find()
                ->select(['name'])
                ->where(['id' => $weather_id])
                ->first();
            $weatherName = $weather['name'];

            $category = $this->Markers->Categories->find()
                ->select(['name'])
                ->where(['id' => $category_id])
                ->first();
            $categoryName = $category['name'];

            $query = $this->Markers->Categories->Sources->query();
            $query->update()
                ->set([
                    'isImported' => true,
                    'placeName' => $placeName,
                    'lat' => $lat,
                    'lng' => $lng,
                    'category_id' => $category_id,
                    'categoryName' => $categoryName,
                    'weather_id' => $weather_id,
                    'weatherName' => $weatherName
                ])
                ->where(['twitID' => $twitID])
                ->execute();
        }
    }

    //public function test($id = null, $lat = null, $lng = null, $respondent_id = 25) {
    private function convertPostToTweetFromSource($id = null, $lat = null, $lng = null, $respondent_id = 25) {
        if($id !== null) {
            $marker = $this->Markers->find()
                ->contain(['Respondents', 'Categories'])
                ->select(['Markers.lat', 'Markers.lng', 'Markers.info', 'Markers.twitTime', 'Markers.category_id', 'Markers.twitPlaceName', 'Respondents.name', 'Categories.name'])
                ->where(['Markers.id' => $id])
                ->first();

            $this->Places = TableRegistry::get('Places');
            $options = [
                'conditions' => [
                    'CAST(lat AS DECIMAL(10,6)) =' => $marker['lat'],
                    'CAST(lng AS DECIMAL(10,6)) =' => $marker['lng'],
                    'active' => 1
                ],
                'limit' => 1
            ];
            // first check if place exist on table

            $place = $this->Places->find('all', $options)->toArray();

            /*$place = $this->Places->find()
                ->select(['name'])
                ->where(['lat' => $marker['lat'], 'lng' => $marker['lng']])
            ->first();*/

            // find respondent
            $respondent = $this->Markers->Respondents->find()
                ->select(['name'])
                ->where([
                    'id' => $respondent_id,
                    'isOfficial' => 1,
                    'active' => 1
                ])
                ->first();

            if(empty($respondent)) {
                $marker['respondent']['name'] = 'TMC';
            }

            $this->postTweetFromSource($id, $marker['info'], $marker['lat'], $marker['lng'], $marker['respondent']['name'], $marker['category']['name'], $marker['twitTime'], $marker['category_id'], $place[0]['name']);
            //$this->postTweetFromSource($marker['info'], $marker['lat'], $marker['lng'], $marker['respondent']['name'], $marker['category']['name'], $marker['twitTime'], $marker['category_id'], $marker['twitPlaceName']);
        }
    }

    private function postLongTweetFromSource($id = null, $info = null, $lat = null, $lng = null, $respondent = null, $category = null, $time = null, $category_id = 1, $placeName = null) {
        if (!empty(trim($placeName))) {
            $Twitter = new TwitterAPIExchange($this->settingsTwitter);

            $url = $this->baseTwitterUrl . 'statuses/update.json';

            $lat === null ? $lat = -7.256177 : $lat = $lat;
            $lng === null ? $long = 112.752268 : $long = $lng;
            $city = $this->nearestCity($lat, $lng);
            $category = null ? $category = '#MACET' : $category = '#' . strtoupper($category);
            $identity = 'dimanamacet.com (' . date('H:i', strtotime($time)) . ') ';
            $cityHashtag = '#' . strtoupper($city[1]) . ' ';
            $placeInfo = $placeName . ' ' . $info;
            $via = ' via: ' . $respondent . ' ';
            $identityHashtag = '#dimanamacetid';
            $placeInfoArr = preg_split('/[,.\s;]+/', $placeInfo);
            $i = 1;
            $mustLen = strlen($identity) + strlen($city) + strlen($category) + strlen($via) + strlen($identityHashtag);
            $dataLen = strlen($placeInfo);

            if ($mustLen > 140) {
                return;
            }

            $status = $identity . $cityHashtag . ' ';
            if ($category_id !== 3) {
                $status = $status . $category . ' ';
            }
            $statusBeforeInfo = $status;

            foreach ($placeInfoArr as $word) {
                $wordLen = strlen($word);
                if (($mustLen + $wordLen) < 141) {
                    $status = $status . $word . ' ';
                } else {
                    $status = $statusBeforeInfo;
                }
            }

            if ($category_id !== 3) {
                $status = $status . $category . ' ';
                $status = $status . $placeName . ' ';
                $status = $status . $info;
            } else {
                $status = $status . $placeName . ' ';
                $status = $status . $info . ' ';
                $status = $status . $category;
            }

            $statusWithRespondent = $status . ' via: ' . $respondent;

            if (strlen($statusWithRespondent) < 141) {
                $status = $statusWithRespondent;
            }
            //$status = $status . ' via: ' . $respondent;
            $status = preg_replace('!\s+!', ' ', $status);

            //$status = $status . ' via: ' . $respondent;
            //$status = preg_replace('!\s+!', ' ', $status);

            if (strlen($status) < 126) {
                $status = $status . ' #dimanamacetid';
            }

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
            //->performRequest(true, ['CURLOPT_TIMEOUT' => 20]);

            $message = json_decode($exec, true);

            if (array_key_exists('errors', $message)) {
                $dataToSave = [
                    'user_id' => $this->Auth->user('id'),
                    'controller' => 'Markers',
                    'controllerID' => $id,
                    'action' => 'postTweetFromSource',
                    'name' => $message['errors'][0]['message'],
                    'active' => 1
                    //'name' => 'Success'
                ];
                $log = $this->Markers->Users->Logs->newEntity($dataToSave);
                $this->Markers->Users->Logs->save($log);
            }
        }
    }

    private function postTweetFromSource($id = null, $info = null, $lat = null, $lng = null, $respondent = null, $category = null, $time = null, $category_id = 1, $placeName = null) {
        if (!empty(trim($placeName))) {
            $Twitter = new TwitterAPIExchange($this->settingsTwitter);

            $url = $this->baseTwitterUrl . 'statuses/update.json';

            $lat === null ? $lat = -7.256177 : $lat = $lat;
            $lng === null ? $long = 112.752268 : $long = $lng;
            $city = $this->nearestCity($lat, $lng);
            $category = null ? $category = '#MACET' : $category = '#' . strtoupper($category);
            $status = 'dimanamacet.com (' . date('H:i', strtotime($time)) . ') ';
            $status = $status . ' #' . strtoupper($city[1]) . ' ';

            if ($category_id !== 3) {
                $status = $status . $category . ' ';
                $status = $status . $placeName . ' ';
                $status = $status . $info;
            } else {
                $status = $status . $placeName . ' ';
                $status = $status . $info . ' ';
                $status = $status . $category;
            }

            $respondent = str_replace(' ', '', $respondent);
            $statusWithRespondent = $status . ' #' . $respondent;
            //$statusWithRespondent = $status . ' via: ' . $respondent;

            if (strlen($statusWithRespondent) < 141) {
                $status = $statusWithRespondent;
            }
            //$status = $status . ' via: ' . $respondent;
            $status = preg_replace('!\s+!', ' ', $status);

            //$status = $status . ' via: ' . $respondent;
            //$status = preg_replace('!\s+!', ' ', $status);

            if (strlen($status) < 126) {
                $status = $status . ' #dimanamacetid';
            }

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
            //->performRequest(true, ['CURLOPT_TIMEOUT' => 20]);

            $message = json_decode($exec, true);

            if (array_key_exists('errors', $message)) {
                $dataToSave = [
                    'user_id' => $this->Auth->user('id'),
                    'controller' => 'Markers',
                    'controllerID' => $id,
                    'action' => 'postTweetFromSource',
                    'name' => $message['errors'][0]['message'],
                    'active' => 1
                    //'name' => 'Success'
                ];
                $log = $this->Markers->Users->Logs->newEntity($dataToSave);
                $this->Markers->Users->Logs->save($log);
            }
        }
    }

    //public function testR($lat = null, $lng = null) {
    private function nearestCity($lat = null, $lng = null) {
        if($lat !== null && $lng !== null) {
            $items = [
                //[155, 'Kep Seribu', -6.193689, 106.851158],
                [156, 'Jakarta', -6.186486, 106.834091],
                [157, 'Jakarta', -6.138414, 106.863953],
                [158, 'Jakarta', -6.168329, 106.758850],
                [159, 'Jakarta', -6.261493, 106.810600],
                [160, 'Jakarta', -6.225014, 106.900444],
                [267, 'Lebak', -6.564396, 106.252213],
                [268, 'Tangerang', -6.187210, 106.487709],
                [269, 'Serang', -6.139734, 106.040504],
                [270, 'Tangerang', -6.202394, 106.652710],
                [271, 'Cilegon', -6.002534, 106.011124],
                [272, 'Serang', -6.110366, 106.163979],
                [273, 'Tangsel', -6.283522, 106.711296],
                [161, 'Bogor', -6.551776, 106.629128],
                [162, 'Sukabumi', -7.213405, 106.629128],
                [163, 'Cianjur', -7.357977, 107.195717],
                [164, 'Bandung', -7.134070, 107.621529],
                [165, 'Garut', -7.501220, 107.763618],
                [166, 'Tasikmalaya', -7.651331, 108.142868],
                [167, 'Ciamis', -7.332077, 108.349251],
                [168, 'Kuningan', -7.013805, 108.570061],
                [169, 'Cirebon', -6.689888, 108.475082],
                [170, 'Majalengka', -6.779060, 108.285202],
                [171, 'Sumedang', -6.832858, 107.953186],
                [172, 'Indramayu', -6.337310, 108.325836],
                [173, 'Subang', -6.348762, 107.763618],
                [174, 'Purwakarta', -6.564924, 107.432198],
                [175, 'Karawang', -6.322730, 107.337578],
                [176, 'Bekasi', -6.247447, 107.148453],
                [177, 'Bandung Barat', -6.865221, 107.491974],
                [178, 'Pangandaran', -7.615061, 108.498825],
                [179, 'Bogor', -6.597147, 106.806038],
                [180, 'Sukabumi', -6.927736, 106.929955],
                [181, 'Bandung', -6.917464, 107.619125],
                [182, 'Cirebon', -6.732023, 108.552315],
                [183, 'Bekasi', -6.238270, 106.975571],
                [184, 'Depok', -6.402484, 106.794243],
                [185, 'Cimahi', -6.884082, 107.541306],
                [186, 'Tasikmalaya', -7.350581, 108.217163],
                [187, 'Banjar', -7.370687, 108.534248],
                [228, 'Pacitan', -8.126331, 111.141426],
                [229, 'Ponorogo', -7.865076, 111.469635],
                [230, 'Trenggalek', -8.182411, 111.618378],
                [231, 'Tulungagung', -8.084321, 111.904556],
                [232, 'Blitar', -8.130866, 112.220009],
                [233, 'Kediri', -7.823240, 112.190712],
                [234, 'Malang', -8.242209, 112.715210],
                [235, 'Lumajang', -8.094357, 113.144157],
                [236, 'Jember', -8.184486, 113.668076],
                [237, 'Banyuwangi', -8.219094, 114.369141],
                [238, 'Bondowoso', -7.967391, 113.906059],
                [239, 'Situbondo', -7.788852, 114.191498],
                [240, 'Probolinggo', -7.871756, 113.477608],
                [241, 'Pasuruan', -7.785996, 112.858215],
                [242, 'Sidoarjo', -7.472613, 112.667542],
                [243, 'Mojokerto', -7.563831, 112.476830],
                [244, 'Jombang', -7.574087, 112.286087],
                [245, 'Nganjuk', -7.594351, 111.904556],
                [246, 'Madiun', -7.609331, 111.618378],
                [247, 'Magetan', -7.643314, 111.356049],
                [248, 'Ngawi', -7.460987, 111.332199],
                [249, 'Bojonegoro', -7.317463, 111.761467],
                [250, 'Tuban', -6.984746, 111.952248],
                [251, 'Lamongan', -7.126926, 112.333778],
                [252, 'Gresik', -7.155029, 112.572189],
                [253, 'Bangkalan', -7.038375, 112.913666],
                [254, 'Sampang', -7.040233, 113.239449],
                [255, 'Pamekasan', -7.105086, 113.525230],
                [256, 'Sumenep', -6.925400, 113.906059],
                [257, 'Kediri', -7.848016, 112.017830],
                [258, 'Blitar', -8.095463, 112.160904],
                [259, 'Malang', -7.966620, 112.632629],
                [260, 'Probolinggo', -7.776423, 113.203712],
                [261, 'Pasuruan', -7.646919, 112.899925],
                [262, 'Mojokerto', -7.470475, 112.440132],
                [263, 'Madiun', -7.631059, 111.530014],
                [264, 'Surabaya', -7.257472, 112.752090],
                [265, 'Batu', -7.883065, 112.533447]
            ];

            $itemsJakarta = [
                //[155, 'Kep Seribu', -6.193689, 106.851158],
                [156, 'Jakarta', -6.186486, 106.834091],
                [157, 'Jakarta', -6.138414, 106.863953],
                [158, 'Jakarta', -6.168329, 106.758850],
                [159, 'Jakarta', -6.261493, 106.810600],
                [160, 'Jakarta', -6.225014, 106.900444],
                [267, 'Lebak', -6.564396, 106.252213],
                [268, 'Tangerang', -6.187210, 106.487709],
                [269, 'Serang', -6.139734, 106.040504],
                [270, 'Tangerang', -6.202394, 106.652710],
                [271, 'Cilegon', -6.002534, 106.011124],
                [272, 'Serang', -6.110366, 106.163979],
                [273, 'Tangsel', -6.283522, 106.711296],
                [161, 'Bogor', -6.551776, 106.629128],
                [162, 'Sukabumi', -7.213405, 106.629128],
                [163, 'Cianjur', -7.357977, 107.195717],
                [164, 'Bandung', -7.134070, 107.621529],
                [165, 'Garut', -7.501220, 107.763618],
                [166, 'Tasikmalaya', -7.651331, 108.142868],
                [167, 'Ciamis', -7.332077, 108.349251],
                [168, 'Kuningan', -7.013805, 108.570061],
                [169, 'Cirebon', -6.689888, 108.475082],
                [170, 'Majalengka', -6.779060, 108.285202],
                [171, 'Sumedang', -6.832858, 107.953186],
                [172, 'Indramayu', -6.337310, 108.325836],
                [173, 'Subang', -6.348762, 107.763618],
                [174, 'Purwakarta', -6.564924, 107.432198],
                [175, 'Karawang', -6.322730, 107.337578],
                [176, 'Bekasi', -6.247447, 107.148453],
                [177, 'Bandung Barat', -6.865221, 107.491974],
                [178, 'Pangandaran', -7.615061, 108.498825],
                [179, 'Bogor', -6.597147, 106.806038],
                [180, 'Sukabumi', -6.927736, 106.929955],
                [181, 'Bandung', -6.917464, 107.619125],
                [182, 'Cirebon', -6.732023, 108.552315],
                [183, 'Bekasi', -6.238270, 106.975571],
                [184, 'Depok', -6.402484, 106.794243],
                [185, 'Cimahi', -6.884082, 107.541306],
                [186, 'Tasikmalaya', -7.350581, 108.217163],
                [187, 'Banjar', -7.370687, 108.534248]

            ];

            $itemsBandung = [
                [161, 'Bogor', -6.551776, 106.629128],
                [162, 'Sukabumi', -7.213405, 106.629128],
                [163, 'Cianjur', -7.357977, 107.195717],
                [164, 'Bandung', -7.134070, 107.621529],
                [165, 'Garut', -7.501220, 107.763618],
                [166, 'Tasikmalaya', -7.651331, 108.142868],
                [167, 'Ciamis', -7.332077, 108.349251],
                [168, 'Kuningan', -7.013805, 108.570061],
                [169, 'Cirebon', -6.689888, 108.475082],
                [170, 'Majalengka', -6.779060, 108.285202],
                [171, 'Sumedang', -6.832858, 107.953186],
                [172, 'Indramayu', -6.337310, 108.325836],
                [173, 'Subang', -6.348762, 107.763618],
                [174, 'Purwakarta', -6.564924, 107.432198],
                [175, 'Karawang', -6.322730, 107.337578],
                [176, 'Bekasi', -6.247447, 107.148453],
                [177, 'Bandung Barat', -6.865221, 107.491974],
                [178, 'Pangandaran', -7.615061, 108.498825],
                [179, 'Bogor', -6.597147, 106.806038],
                [180, 'Sukabumi', -6.927736, 106.929955],
                [181, 'Bandung', -6.917464, 107.619125],
                [182, 'Cirebon', -6.732023, 108.552315],
                [183, 'Bekasi', -6.238270, 106.975571],
                [184, 'Depok', -6.402484, 106.794243],
                [185, 'Cimahi', -6.884082, 107.541306],
                [186, 'Tasikmalaya', -7.350581, 108.217163],
                [187, 'Banjar', -7.370687, 108.534248]
            ];

            $itemsSurabaya = [
                [228, 'Pacitan', -8.126331, 111.141426],
                [229, 'Ponorogo', -7.865076, 111.469635],
                [230, 'Trenggalek', -8.182411, 111.618378],
                [231, 'Tulungagung', -8.084321, 111.904556],
                [232, 'Blitar', -8.130866, 112.220009],
                [233, 'Kediri', -7.823240, 112.190712],
                [234, 'Malang', -8.242209, 112.715210],
                [235, 'Lumajang', -8.094357, 113.144157],
                [236, 'Jember', -8.184486, 113.668076],
                [237, 'Banyuwangi', -8.219094, 114.369141],
                [238, 'Bondowoso', -7.967391, 113.906059],
                [239, 'Situbondo', -7.788852, 114.191498],
                [240, 'Probolinggo', -7.871756, 113.477608],
                [241, 'Pasuruan', -7.785996, 112.858215],
                [242, 'Sidoarjo', -7.472613, 112.667542],
                [243, 'Mojokerto', -7.563831, 112.476830],
                [244, 'Jombang', -7.574087, 112.286087],
                [245, 'Nganjuk', -7.594351, 111.904556],
                [246, 'Madiun', -7.609331, 111.618378],
                [247, 'Magetan', -7.643314, 111.356049],
                [248, 'Ngawi', -7.460987, 111.332199],
                [249, 'Bojonegoro', -7.317463, 111.761467],
                [250, 'Tuban', -6.984746, 111.952248],
                [251, 'Lamongan', -7.126926, 112.333778],
                [252, 'Gresik', -7.155029, 112.572189],
                [253, 'Bangkalan', -7.038375, 112.913666],
                [254, 'Sampang', -7.040233, 113.239449],
                [255, 'Pamekasan', -7.105086, 113.525230],
                [256, 'Sumenep', -6.925400, 113.906059],
                [257, 'Kediri', -7.848016, 112.017830],
                [258, 'Blitar', -8.095463, 112.160904],
                [259, 'Malang', -7.966620, 112.632629],
                [260, 'Probolinggo', -7.776423, 113.203712],
                [261, 'Pasuruan', -7.646919, 112.899925],
                [262, 'Mojokerto', -7.470475, 112.440132],
                [263, 'Madiun', -7.631059, 111.530014],
                [264, 'Surabaya', -7.257472, 112.752090],
                [265, 'Batu', -7.883065, 112.533447]
            ];

            $ref = [$lat, $lng];
            $userID = $this->Auth->user('id');
            $user = $this->Markers->Users->find()
                ->where(['id' => $userID])
                ->first();

            $cityArray = $items;

            switch ($user['region_id']) {
            case 2:
                $cityArray = $itemsJakarta;
                break;
            case 3:
                $cityArray = $itemsBandung;
                break;
            case 6:
                $cityArray = $itemsSurabaya;
                break;
            default:
                $cityArray = $items;
            }

            $distances = array_map(function($item) use($ref) {
                $a = array_slice($item, -2);
                return $this->distance($a, $ref);
            }, $cityArray);

            asort($distances);

        /*$this->set([
           'markers' => $cityArray[key($distances)],
            '_serialize' => ['markers']
        ]);*/
            return $cityArray[key($distances)];
        }
    }

    private function distance($a, $b)
    {
        list($lat1, $lon1) = $a;
        list($lat2, $lon2) = $b;

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles;
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

                // add places
                $this->savePlace($this->request->data['marker']['twitPlaceName'], $this->request->data['marker']['lat'], $this->request->data['marker']['lng']);


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
                ->select(['Markers.lat', 'Markers.lng', 'Markers.info', 'Markers.twitTime', 'Markers.category_id', 'Markers.respondent_id', 'Respondents.name', 'Categories.name'])
                ->where(['Markers.id' => $id])
                ->first();

            // find respondent
            $respondent = $this->Markers->Respondents->find()
                ->select(['name'])
                ->where([
                    'id' => $marker['respondent_id'],
                    'isOfficial' => 1,
                    'active' => 1
                ])
                ->first();

            if(empty($respondent)) {
                $marker['respondent']['name'] = 'TMC';
            }

            $this->postTweet($id, $marker['info'], $marker['lat'], $marker['lng'], $marker['respondent']['name'], $marker['category']['name'], $marker['twitTime'], $marker['category_id']);
        }
    }

    private function postTweet($id = null, $info = null, $lat = null, $lng = null, $respondent = null, $category = null, $time = null, $category_id = 1) {
        $Twitter = new TwitterAPIExchange($this->settingsTwitter);

        $url = $this->baseTwitterUrl . 'statuses/update.json';

        $lat === null ? $lat = -7.256177 : $lat = $lat;
        $lng === null ? $long = 112.752268 : $long = $lng;
        $city = $this->nearestCity($lat, $lng);
        $category = null ? $category = '#MACET' : $category = '#' . strtoupper($category);
        $status = 'dimanamacet.com (' . date('H:i', strtotime($time)) . ') ';
        $status = $status . ' #' . strtoupper($city[1]) . ' ';

        if ($category_id !== 3) {
            $status = $status . $category . ' ';
            $status = $status . $info;
        } else {
            $status = $status . $info . ' ';
            $status = $status . $category;
        }

        //$statusWithRespondent = $status . ' via: ' . $respondent;
        $respondent = str_replace(' ', '', $respondent);
        $statusWithRespondent = $status . ' #' . $respondent;

        if (strlen($statusWithRespondent) < 141) {
            $status = $statusWithRespondent;
        }
        //$status = $status . ' via: ' . $respondent;
        $status = preg_replace('!\s+!', ' ', $status);

        if (strlen($status) < 126) {
            $status = $status . ' #dimanamacetid';
        }

        $postfield = '?status=' . $status;
        $postfield = $postfield . '&lat=' . $lat;
        $postfield = $postfield . '&long=' . $long;
        $postFields = [
            'status' => $status,
            'lat' => $lat,
            'long' => $long
        ];

        $requestMethod = 'POST';

        /*$exec = $Twitter->setPostfields($postFields)
            ->buildOauth($url, $requestMethod)
        ->performRequest();*/
        //echo $exec;
        //$message = json_decode($exec);
        $exec = $Twitter->setPostfields($postFields)
            ->buildOauth($url, $requestMethod)
            ->performRequest();
        //->performRequest(true, ['CURLOPT_TIMEOUT' => 20]);

        $message = json_decode($exec, true);

        if (array_key_exists('errors', $message)) {
            $dataToSave = [
                'user_id' => $this->Auth->user('id'),
                'controller' => 'Markers',
                'controllerID' => $id,
                'action' => 'postTweet',
                'name' => $message['errors'][0]['message']
            ];
            $log = $this->Markers->Users->Logs->newEntity($dataToSave);
            $this->Markers->Users->Logs->save($log);
        }

        if (array_key_exists('errors', $message)) {
            $dataToSave = [
                'user_id' => $this->Auth->user('id'),
                'controller' => 'Markers',
                'action' => 'postTweetFromSource',
                'name' => $message['errors'][0]['message'],
                'active' => 1
            ];
            $log = $this->Markers->Users->Logs->newEntity($dataToSave);
            $this->Markers->Users->Logs->save($log);
        }

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
