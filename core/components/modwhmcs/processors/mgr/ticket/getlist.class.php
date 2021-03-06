<?php
// Require composer autoloader for guzzle
require dirname(dirname(dirname(dirname(__FILE__)))).'/model/libs/vendor/autoload.php';

use GuzzleHttp\Client;

class GetTicketsProcessor extends modProcessor {
    /** @var string $defaultSortField The default field to sort by */
    public $defaultSortField = 'id';
    /** @var string $defaultSortDirection The default direction to sort */
    public $defaultSortDirection = 'ASC';
    /** @var  string $apiUrl URL of the API */
    private $apiUrl;
    /** @var  string $username Username for the API */
    private $username;
    /** @var  string $password Password for the API */
    private $password;

    public function initialize() {
        $this->apiUrl = $this->modx->getOption('modwhmcs.whmcs_url');
        $this->username = $this->modx->getOption('modwhmcs.username');
        $this->password = $this->modx->getOption('modwhmcs.password');

        $this->setDefaultProperties(array(
            'start' => 0,
            'limit' => 20,
            'sort' => $this->defaultSortField,
            'dir' => $this->defaultSortDirection,
            'combo' => false,
            'query' => ''
        ));

        return parent::initialize();
    }

    public function getAPIData() {
        // Set post values
        $postfields = array(
            'username' => $this->username,
            'password' => md5($this->password),
            'action' => 'gettickets',
            'responsetype' => 'json'
        );

        $client = new Client([
            'base_uri' => 'https://www.hyperdrivehosting.net/billing/',
            'timeout'  => 30,
        ]);

        $response = $client->request('POST', 'includes/api.php', [
            'form_params' => $postfields
        ]);
        $jsonData = json_decode($response->getBody(), true);

        //$this->modx->log(modX::LOG_LEVEL_DEBUG, 'Response: '. print_r($jsonData,true));

        $apiData['total'] = $jsonData['numreturned'];
        $this->modx->log(modX::LOG_LEVEL_DEBUG, 'total: '. $apiData['total']);
        $apiData['results'] = $jsonData['tickets']['ticket'];
        return $apiData;
    }

    /*public function cachedRequest($method, $uri = null, array $options = []) {

        $cacheKey = $this->cacheKey;
        $cacheHandler = $this->modx->getOption(xPDO::OPT_CACHE_HANDLER, null, 'xPDOFileCache');
        $cacheExpires = intval($this->getOption('cache_expires', $options, $this->options['cache_expires'], true));
        $cacheOptions = array(
            xPDO::OPT_CACHE_KEY => $cacheKey,
            xPDO::OPT_CACHE_HANDLER => $cacheHandler,
            xPDO::OPT_CACHE_EXPIRES => $cacheExpires,
        );

        $cacheElementKey = md5($method . $uri . json_encode($options));
        $request = $this->modx->cacheManager->get($cacheElementKey, $cacheOptions);
        if (empty($request)) {
            $response = $this->client->request($method, $uri, $options);
            if ($response && $response->getBody()) {
                $request = json_decode($response->getBody()->getContents(), true);
            } else {
                $request = array();
            }
            $this->modx->cacheManager->set($cacheElementKey, $request, $cacheExpires, $cacheOptions);
        }
        return $request;
    }*/

    public function process() {
        $data = $this->getData();
        return $this->outputArray($data['results'],$data['total']);
    }

    public function getData() {
        $apiData = $this->getAPIData();
        $data = array();
        $data['results'] = array();
        $data['total'] = $apiData['total'];

        $limit = intval($this->getProperty('limit'));
        $start = intval($this->getProperty('start'));

        $count = 0;
        foreach ($apiData['results'] as $key => $value) {
            if ($key >= $start) {
                if($count < $limit) {
                    array_push($data['results'], $value);
                    $count++;
                }
            }
            if ($key > $limit) break;
        }

        //Sort
        if (empty($sortKey = $this->getProperty('sort'))) $sortKey = $this->defaultSortField;
        if (empty($sortDir = $this->getProperty('dir'))) $sortDir = $this->defaultSortDirection;
        if ($sortDir == 'DESC') {
            foreach ($data['results'] as $key => $row) {
                $dates[$key]  = $row[$sortKey];
            }
            array_multisort($dates, SORT_DESC, $data['results']);
        } else {
            foreach ($data['results'] as $key => $row) {
                $dates[$key]  = $row[$sortKey];
            }
            array_multisort($dates, SORT_ASC, $data['results']);
        }

        return $data;
    }
}
return 'GetTicketsProcessor';