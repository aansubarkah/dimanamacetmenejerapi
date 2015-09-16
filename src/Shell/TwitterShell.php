<?php
/**
 * Created by PhpStorm.
 * User: aan
 * Date: 16/09/15
 * Time: 15:20
 */
namespace App\Shell;

use Cake\Console\Shell;
use Cake\Filesystem\Folder;
use Cake\Filesystem\File;
use TwitterAPIExchange;
use Cake\ORM\TableRegistry;

/**
 * Twitter Shell
 * @property \App\Model\Table\MarkersTable $Markers
 * @property \App\Model\Table\RespondentsTable $Respondents
 */
class TwitterShell extends Shell
{
    public $settingsTwitter = [
        'oauth_access_token' => '3555146480-sXfyGZDtrIDdzOMd1tt8srNWUijs7nCFfeag349',
        'oauth_access_token_secret' => 'fKQN5cTbpDEvic613JtfHoVz7LC9dlSfUsP0yohuwboxY',
        'consumer_key' => 'Bu8ZMGWX8LxqR0jjbCuKTvjfG',
        'consumer_secret' => 'Fx43AKjpEksdAcG7y7SmDVH4Y2UfOVgQTzwmzSRInuPZaokGrX'
    ];

    private $baseTwitterUrl = 'https://api.twitter.com/1.1/';

    public $Markers = null;
    public $Respondents = null;

    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Markers');
    }

    public function main()
    {
        //$this->out('Hello World!');
        //$this->createFile('lorem.txt','halo dunia1');
        //$dir = new Folder('app/tmp');
        //$logFile = new File('../tmp/Twitter.txt', true, 0644);
        //$logFile->write('lorem ipsum', 'w', false);
        //$dataStream = $this->getMention($latestTwitID, 800);
        $dataStream = $this->getMention(1, 800);
        $countDataStream = count($dataStream);

        $dataToDisplay = [];
        if ($countDataStream > 0) {
            foreach ($dataStream as $data) {
                if ($data['place'] !== null) {
                    $isTwitExists = $this->Markers->exists(['twitID' => $data['id'], 'active' => 1]);
                    if (!$isTwitExists) {
                        //if geo located, insert to DB
                        //first get respondent_id
                        $respondent_id = $this->findToSaveRespondent($data['user']['id'], $data['user']['name'], $data['user']['screen_name']);

                        $dataToSave = [
                            //$dataToDisplay[] = [
                            'category_id' => 1,//macet
                            'user_id' => 4,//twitter robot
                            'respondent_id' => $respondent_id,
                            'weather_id' => 1,//cerah
                            'info' => trim(str_replace('@dimanamacetid', '', $data['text'])),
                            'twitID' => $data['id'],
                            'twitCreated' => date("Y-m-d H:i:s", strtotime($data['created_at'])),//@todo this is not working, fix
                            'twitPlaceID' => $data['place']['id'],
                            'twitPlaceName' => $data['place']['name'],
                            'isTwitPlacePrecise' => 0,
                            'twitImage' => null,
                            'pinned' => 0,
                            'cleared' => 0,
                            'active' => 1
                        ];
                        // if image do exists
                        if (array_key_exists('extended_entities', $data) &&
                            array_key_exists('media', $data['extended_entities']) &&
                            $data['extended_entities']['media'][0]['type'] == 'photo'
                        ) {
                            $dataToSave['twitImage'] = $data['extended_entities']['media'][0]['media_url'];
                        }

                        if ($data['geo'] !== null) {
                            $dataToSave['lat'] = $data['geo']['coordinates'][0];
                            $dataToSave['lng'] = $data['geo']['coordinates'][1];
                            $dataToSave['isTwitPlacePrecise'] = 1;
                        } else {
                            $dataToSave['lat'] = $data['place']['bounding_box']['coordinates'][0][0][1];
                            $dataToSave['lng'] = $data['place']['bounding_box']['coordinates'][0][0][0];
                        }

                        //$dataToDisplay[] = $dataToSave;

                        //save marker
                        $marker = $this->Markers->newEntity($dataToSave);
                        $this->Markers->save($marker);
                        //add this line to log file
                        $this->out(date("Y-m-d H:i:s") . ' add TwitID:' . $data['id'] .' on id:' . $marker->id);
                    }
                }
            }
        }
    }

    private function getMention($since_id = 0, $count = 800)
    {
        $Twitter = new TwitterAPIExchange($this->settingsTwitter);

        $url = $this->baseTwitterUrl . 'statuses/mentions_timeline.json';
        $getfield = '?count=' . $count;
        if ($since_id > 0) {
            $getfield = $getfield . '&since_id=' . $since_id;
        }
        $requestMethod = 'GET';

        $data = $Twitter->setGetfield($getfield)
            ->buildOauth($url, $requestMethod)
            ->performRequest();

        return json_decode($data, true);
    }

    private function findToSaveRespondent($twitterUserID, $twitterName, $twitterScreenName)
    {
        $this->Respondents = TableRegistry::get('Respondents');
        //find if id exists
        $isRespondentExists = $this->Respondents->exists(['twitUserID' => $twitterUserID, 'active' => 1]);
        //if exists return id
        if ($isRespondentExists) {
            $respondent_id = $this->Respondents->find()
                ->select(['id'])
                ->where(['twitUserID' => $twitterUserID, 'active' => 1])
                ->order(['id' => 'DESc'])
                ->first();
            //otherwise insert into table
            $respondent_id = $respondent_id['id'];
        } else {
            $dataToSave = [
                'twitUserID' => $twitterUserID,
                'name' => $twitterName,
                'contact' => '@' . $twitterScreenName,
                'active' => 1
            ];
            $respondent = $this->Respondents->newEntity($dataToSave);
            $this->Respondents->save($respondent);

            $respondent_id = $respondent->id;
        }
        return $respondent_id;
    }
}