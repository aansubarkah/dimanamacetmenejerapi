<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * Places Controller
 *
 * @property \App\Model\Table\PlacesTable $Places
 */
class PlacesController extends AppController
{
    public $limit = 25;

    public $paginate = [
        'fields' => ['Places.id', 'Places.name', 'Places.active'],
        'limit' => 25,
        'page' => 0,
        'order' => [
            'Places.name' => 'asc'
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
        if (isset($this->request->query['showAll']) && $this->request->query['showAll'] == true) {
            $this->showAll();
        } else {
            $limit = $this->limit;
            if (isset($this->request->query['limit'])) {
                if (is_numeric($this->request->query['limit'])) {
                    $limit = $this->request->query['limit'];
                }
            }

            if (isset($this->request->query['searchName'])) {
                $searchName = trim($this->request->query['searchName']);
                $this->checkExistence($searchName, $limit);
            } else {
                $offset = 0;
                if (isset($this->request->query['page'])) {
                    if (is_numeric($this->request->query['page'])) {
                        $offset = $this->request->query['page'] - 1;
                    }
                }

                $query = '';
                if (isset($this->request->query['query'])) {
                    if (!empty(trim($this->request->query['query']))) {
                        $query = trim($this->request->query['query']);
                    }
                }

                $fetchDataOptions = [
                    'conditions' => ['Places.active' => true],
                    'order' => ['Places.name' => 'ASC'],
                    'limit' => $limit,
                    'page' => $offset
                ];

                $region_id = $this->Auth->user('region_id');
                if ($region_id != 1) {
                    $fetchDataOptions['conditions']['OR'] = [
                        ['Places.region_id' => 1],
                        ['Places.region_id' => $region_id]
                    ];
                    //$fetchDataOptions['conditions']['Places.region_id'] = $region_id;
                }

                if (!empty(trim($query))) {
                    $fetchDataOptions['conditions']['LOWER(Places.name) LIKE'] = '%' . strtolower($query) . '%';
                }

                $this->paginate = $fetchDataOptions;
                $places = $this->paginate('Places');

                $allPlaces = $this->Places->find('all', $fetchDataOptions);
                $total = $allPlaces->count();

                $meta = [
                    'total' => $total
                ];
                $this->set([
                    'places' => $places,
                    'meta' => $meta,
                    '_serialize' => ['places', 'meta']
                ]);
            }
        }
    }

    public function showAll() {
        $fetchDataOptions = [
            'conditions' => ['Places.active' => true],
            'order' => ['Places.name' => 'ASC']
        ];

        $region_id = $this->Auth->user('region_id');
        if ($region_id != 1) {
            $fetchDataOptions['conditions']['OR'] = [
                ['Places.region_id' => 1],
                ['Places.region_id' => $region_id]
            ];
            //$fetchDataOptions['conditions']['Places.region_id'] = $region_id;
        }

        $allPlaces = $this->Places->find('all', $fetchDataOptions);
        $total = $allPlaces->count();

        $meta = [
            'total' => $total
        ];

        $this->set([
            'places' => $allPlaces,
            'meta' => $meta,
            '_serialize' => ['places', 'meta']
        ]);
    }

    /**
     * View method
     *
     * @param string|null $id Place id.
     * @return void
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function view($id = null)
    {
        $place = $this->Places->get($id);
        $this->set([
            'place' => $place,
            '_serialize' => ['place']
        ]);
    }

    /**
     * Add method
     *
     * @return void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        if ($this->request->is('post')) {
            if (isset($this->request->data['place']['active'])) unset($this->request->data['place']['active']);
            if (isset($this->request->data['place']['id'])) unset($this->request->data['place']['id']);
            $this->request->data['place']['active'] = true;

            $place = $this->Places->newEntity($this->request->data['place']);
            $this->Places->save($place);

            $this->set([
                'place' => $place,
                '_serialize' => ['place']
            ]);
        }
    }

    /**
     * Edit method
     *
     * @param string|null $id Place id.
     * @return void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $place = $this->Places->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            if (isset($this->request->data['place']['active'])) unset($this->request->data['place']['active']);

            $place = $this->Places->patchEntity($place, $this->request->data['place']);
            if ($this->Places->save($place)) {
                $message = 'Saved';
            } else {
                $message = 'Error';
            }
        }
        $this->set([
            'place' => $message,
            '_serialize' => ['place']
        ]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Place id.
     * @return void Redirects to index.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $place = $this->Places->get($id);
        if ($this->request->is(['delete'])) {
            $place->active = false;
            if ($this->Places->save($place)) {
                $message = 'Deleted';
            } else {
                $message = 'Error';
            }
        }
        $this->set([
            'place' => $place,
            '_serialize' => ['place']
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
            'order' => ['Places.name' => 'ASC'],
            'limit' => $limit
        ];

        $query = trim(strtolower($name));

        if (!empty($query)) {
            $fetchDataOptions['conditions']['LOWER(Places.name) LIKE'] = '%' . $query . '%';
        }

        $place = $this->Places->find('all', $fetchDataOptions);

        if ($place->count() > 0) {
            $data = $place;
        }

        $this->set([
            'place' => $data,
            '_serialize' => ['place']
        ]);
    }

    private function nearestCity($lat = null, $lng = null) {
        if($lat !== null && $lng !== null) {
            $items = [
                [156, 2, -6.186486, 106.834091],
                [157, 2, -6.138414, 106.863953],
                [158, 2, -6.168329, 106.758850],
                [159, 2, -6.261493, 106.810600],
                [160, 2, -6.225014, 106.900444],
                [267, 2, -6.564396, 106.252213],
                [268, 2, -6.187210, 106.487709],
                [269, 2, -6.139734, 106.040504],
                [270, 2, -6.202394, 106.652710],
                [271, 2, -6.002534, 106.011124],
                [272, 2, -6.110366, 106.163979],
                [273, 2, -6.283522, 106.711296],
                [161, 1, -6.551776, 106.629128],
                [162, 3, -7.213405, 106.629128],
                [163, 3, -7.357977, 107.195717],
                [164, 3, -7.134070, 107.621529],
                [165, 3, -7.501220, 107.763618],
                [166, 3, -7.651331, 108.142868],
                [167, 3, -7.332077, 108.349251],
                [168, 3, -7.013805, 108.570061],
                [169, 3, -6.689888, 108.475082],
                [170, 3, -6.779060, 108.285202],
                [171, 3, -6.832858, 107.953186],
                [172, 3, -6.337310, 108.325836],
                [173, 3, -6.348762, 107.763618],
                [174, 3, -6.564924, 107.432198],
                [175, 3, -6.322730, 107.337578],
                [176, 1, -6.247447, 107.148453],
                [177, 3, -6.865221, 107.491974],
                [178, 3, -7.615061, 108.498825],
                [179, 1, -6.597147, 106.806038],
                [180, 3, -6.927736, 106.929955],
                [181, 3, -6.917464, 107.619125],
                [182, 3, -6.732023, 108.552315],
                [183, 1, -6.238270, 106.975571],
                [184, 1, -6.402484, 106.794243],
                [185, 3, -6.884082, 107.541306],
                [186, 3, -7.350581, 108.217163],
                [187, 3, -7.370687, 108.534248],
                [228, 6, -8.126331, 111.141426],
                [229, 6, -7.865076, 111.469635],
                [230, 6, -8.182411, 111.618378],
                [231, 6, -8.084321, 111.904556],
                [232, 6, -8.130866, 112.220009],
                [233, 6, -7.823240, 112.190712],
                [234, 6, -8.242209, 112.715210],
                [235, 6, -8.094357, 113.144157],
                [236, 6, -8.184486, 113.668076],
                [237, 6, -8.219094, 114.369141],
                [238, 6, -7.967391, 113.906059],
                [239, 6, -7.788852, 114.191498],
                [240, 6, -7.871756, 113.477608],
                [241, 6, -7.785996, 112.858215],
                [242, 6, -7.472613, 112.667542],
                [243, 6, -7.563831, 112.476830],
                [244, 6, -7.574087, 112.286087],
                [245, 6, -7.594351, 111.904556],
                [246, 6, -7.609331, 111.618378],
                [247, 6, -7.643314, 111.356049],
                [248, 6, -7.460987, 111.332199],
                [249, 6, -7.317463, 111.761467],
                [250, 6, -6.984746, 111.952248],
                [251, 6, -7.126926, 112.333778],
                [252, 6, -7.155029, 112.572189],
                [253, 6, -7.038375, 112.913666],
                [254, 6, -7.040233, 113.239449],
                [255, 6, -7.105086, 113.525230],
                [256, 6, -6.925400, 113.906059],
                [257, 6, -7.848016, 112.017830],
                [258, 6, -8.095463, 112.160904],
                [259, 6, -7.966620, 112.632629],
                [260, 6, -7.776423, 113.203712],
                [261, 6, -7.646919, 112.899925],
                [262, 6, -7.470475, 112.440132],
                [263, 6, -7.631059, 111.530014],
                [264, 6, -7.257472, 112.752090],
                [265, 6, -7.883065, 112.533447]
            ];

            $ref = [$lat, $lng];
            $cityArray = $items;

            $distances = array_map(function($item) use($ref) {
                $a = array_slice($item, -2);
                return $this->distance($a, $ref);
            }, $cityArray);

            asort($distances);

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

    public function addRegionToPlace() {
        $places = $this->Places->find('all', [
            'conditions' => ['active' => 1]
        ]);

        foreach ($places as $place) {
            $newRegion = $this->nearestCity($place['lat'], $place['lng']);

            $query = $this->Places->get($place['id']);
            $query->region_id = $newRegion[1];
            $this->Places->save($query);
        }

        $allPlaces = $this->Places->find('all', [
            'conditions' => ['active' => 1],
            'order' => ['name' => 'ASC'],
            //'limit' => 10
        ]);


        $this->set([
            'place' => $allPlaces,
            '_serialize' => ['place']
        ]);
    }

    public function renameStreet() {
        $places = $this->Places->find('all', [
            'conditions' => ['active' => 1],
            'order' => ['name' => 'ASC'],
            //'limit' => 10
        ]);

        foreach ($places as $place) {
            //$newPlace = $this->Places->find('')
            $newName = $place['name'];
            $newName = trim($newName);
            $newName = str_replace('Jalan', 'Jl.', $newName);
            $newName = str_replace('#Tol', 'Tol', $newName);
            $newName = str_replace('#Tol_', 'Tol ', $newName);
            $newName = str_replace('Exit Gerbang Tol', 'GT', $newName);
            $newName = str_replace('Gerbang Tol', 'GT', $newName);
            $newName = str_replace('Keluar Tol', 'GT', $newName);
            $newName = str_replace(' :', ':', $newName);
            $newName = str_replace(' | ', ' - ', $newName);
            $newName = str_replace(' menuju ', ' arah ', $newName);
            $newName = str_replace('Tol Dalam Kota', 'Toldakot', $newName);
            $newName = str_replace('Tol Jakarta Cikampek', 'Tol Japek', $newName);
            $newName = str_replace('Tol Japek:', 'Tol Japek', $newName);
            $newName = preg_replace('!\s+!', ' ', $newName);

            //$newName = str_replace

            $query = $this->Places->get($place['id']);
            $query->name = $newName;
            $this->Places->save($query);
        }

        $allPlaces = $this->Places->find('all', [
            'conditions' => ['active' => 1],
            'order' => ['name' => 'ASC'],
            //'limit' => 10
        ]);


        $this->set([
            'place' => $allPlaces,
            '_serialize' => ['place']
        ]);
    }
}
