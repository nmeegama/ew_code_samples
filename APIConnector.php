<?php

namespace Drupal\fidante_connect\Services;
use Drupal\Core\Cache\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class APIConnector {
  private $url;
  private $token;
  private $project;

  /**
   * APIConnector constructor.
   *
   * @param $url
   * @param $token
   * @param $project
   */
  public function __construct() {
    $this->url = 'https://datafeeds.myfeed/api/';
    $this->token = '12345678910';
    $this->project = 'evolving_web';
  }

  /**
   * A function to build the API endpoint
   * @param  string  $apicategory
   *
   * @return string
   */
  private function constructMainAPIURL($apicategory = 'funddata') {
    return $this->url.$apicategory.'/'.$this->project.'/'.$this->token;
  }

  /**
   * A function to get the entire fundata payload per cititcode and cache it for one hour
   * @param $citicode
   *
   * @return mixed
   */
  private function getAllFundData($citicode) {
    $url = $this->constructMainAPIURL();
    try {

      $cid = 'fidante_connect:' . 'fund_'.$citicode;
      $fund_data = NULL;
      // See if the data is cached.
      if ($cache = \Drupal::cache()->get($cid)) {
        $fund_data = $cache->data;
      }
      else {
        $client = new Client();
        $response = $client->get($url, ['query' => ['rangename' => 'ew', 'citicodes' => $citicode]]);
        $result = json_decode($response->getBody(), TRUE);
        $fund_data = $result[0];
        // Cache the data for one hour from now.
        $in_one_hour = strtotime("+1 hour");
        \Drupal::cache()->set($cid, $fund_data, $in_one_hour);

      }
      return $fund_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('fidante_connect')->error($e->getMessage());
      $fund_data['code'] = $e->getCode();
      return $fund_data;
    }
  }

  /**
   * A function to get data for the about us section
   * @param $citicode
   * @param $fund_type
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array|mixed
   */
  public function getAboutFundaData($citicode, $fund_type, $node) {

    switch($fund_type){
      case'xaro':
      case'xkap':
        return $this->getAboutFundDataXAROXKAP($citicode, $node);
        break;
      case'dual':
        return $this->getAboutFundDataDual($citicode, $node);
        break;
      default:
        return $this->getAboutFundDataGeneral($citicode, $node);
    }

  }

  /**
   * About datafro XAR and XKAP
   * @param $citicode
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array|mixed
   */
  public function getAboutFundDataXAROXKAP($citicode, $node) {
    try {

      $fund_data = $this->getAllFundData($citicode);
      if(!isset($fund_data['code'])) {
        $return_data = [];
        $return_data['inception_date'] = $fund_data['UnitLaunchDate'];
        $return_data['last_traded_price']['value'] = '<span data-yourir="price"></span>';
        $return_data['last_traded_price']['date'] = '<span data-yourir="date"></span>';

        $uph_link = $node->field_unit_price_history->uri;
        if(empty($uph_link)) {
          $uph_link = '';
        } else {
          $uph_link = substr_count($uph_link, 'node') > 0 ?  \Drupal::service('path_alias.manager')->getAliasByPath(str_replace('entity:', '/', $uph_link)) : str_replace('entity:', '/', $uph_link);
          $uph_link = str_replace('internal:', '', $uph_link);
        }

        $return_data['nav_per_unit']['value'] = '';
        $return_data['nav_per_unit']['date'] = '';
        $return_data['nav_per_unit']['link'] = [
          'text' => $node->field_unit_price_history->title,
          'url' => $uph_link
        ];
        $return_data['benchmark'] = $fund_data['BespokeField1'];
        $return_data['currency'] = $fund_data['FundCurrency'];
        $return_data['distribution_freq'] = $this->getDividendFrequencyText($fund_data['DividendFrequency']);
        $return_data['suggested_min_time_frame'] = $fund_data['MinInvTimeFrame'];
        $return_data['unit_size']['value'] = '<span data-yourir="price"></span>';
        $return_data = array_merge($return_data, $this->getAboutCommonData($fund_data, $node));


        return $return_data;
      } else {
        return $fund_data;
      }

    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * About datafro XAR and XKAP
   * @param $citicode
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array|mixed
   */
  public function getAboutFundDataDual($citicode, $node) {
    try {

      $fund_data = $this->getAllFundData($citicode);
      if(!isset($fund_data['code'])) {
        $return_data = [];
        $return_data['inception_date'] = $fund_data['UnitLaunchDate'];
        $return_data['benchmark'] = $fund_data['BespokeField1'];
        $return_data['distribution_freq'] = $this->getDividendFrequencyText($fund_data['DividendFrequency']);
        $return_data['suggested_min_time_frame'] = $fund_data['MinInvTimeFrame'];
        $return_data['fund_currency'] = $fund_data['FundCurrency'];

        return $return_data;
      } else {
        return $fund_data;
      }

    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * Function to get data fro general funds
   * @param $citicode
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array|mixed
   */
  public function getAboutFundDataGeneral($citicode, $node) {
    try {

      $fund_data = $this->getAllFundData($citicode);
      if(!isset($fund_data['code'])) {
        $return_data = [];
        $return_data['inception_date'] = $fund_data['UnitLaunchDate'];
        $return_data['unit_price']['value'] = 'Buy '. $fund_data['Offer'] . ' / '. 'Sell '. $fund_data['Bid'];
        $return_data['unit_price']['date'] = $fund_data['UnitPriceDate'];
        $uph_link = $node->field_unit_price_history->uri;
        if(empty($uph_link)) {
          $uph_link = '';
        } else {
          $uph_link = substr_count($uph_link, 'node') > 0 ?  \Drupal::service('path_alias.manager')->getAliasByPath(str_replace('entity:', '/', $uph_link)) : str_replace('entity:', '/', $uph_link);
          $uph_link = str_replace('internal:', '', $uph_link);
        }
        $return_data['unit_price']['link'] = [
          'url' => $uph_link,
          'text' => $node->field_unit_price_history->title,
        ];

        $return_data = array_merge($return_data, $this->getAboutCommonData($fund_data, $node));

        return $return_data;
      } else {
        return $fund_data;
      }

    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }


  /**
   * A function to get common about data for all fund types
   * @param $fund_data
   * @param \Drupal\node\Entity\Node $node
   *
   * @return array
   */
  private function getAboutCommonData($fund_data, $node) {
    $return_data = [];
    $bsh_link = $node->field_buy_sell_history->uri;
    if(empty($bsh_link)) {
      $bsh_link = '';
    } else {
      $bsh_link = substr_count($bsh_link, 'node') > 0 ?  \Drupal::service('path_alias.manager')->getAliasByPath(str_replace('entity:', '/', $bsh_link)) : str_replace('entity:', '/', $bsh_link);
      $bsh_link = str_replace('internal:', '', $bsh_link);
    }
    $return_data['buy_sell_spred'] = [
      'buy_spread' => $fund_data['BuySpread'] .'%',
      'sell_spread' => $fund_data['SellSpread'] . '%',
      'date' => $fund_data['BuySellSpreadDate'],
      'link' => [
        'url' => $bsh_link,
        'title' => $node->field_buy_sell_history->title,
      ],
    ];

    $return_data['inception_date'] = $fund_data['UnitLaunchDate'];
    $return_data['benchmark'] = $fund_data['BespokeField1'];

    $return_data['fund_size']['value'] = $fund_data['UnitSize'];
    $return_data['fund_size']['date'] = $fund_data['UnitSizeDate'];
    $return_data['distribution_freq'] = $this->getDividendFrequencyText($fund_data['DividendFrequency']);
    $return_data['suggested_min_time_frame'] = $fund_data['MinInvTimeFrame'];
    $return_data['fund_currency'] = $fund_data['FundCurrency'];

    return $return_data;
  }

  /**
   * A function to get the $dividend_frequency from the payload and return a meaningful text.
   * @param $dividend_frequency
   *
   * @return string
   */
  private function getDividendFrequencyText($dividend_frequency) {
    $dividend_frequency_text = '';
    switch ($dividend_frequency) {
      case '1':
        $dividend_frequency_text = 'Annually';
        break;
      case '2':
        $dividend_frequency_text = 'Bianually';
        break;
      case '3':
        $dividend_frequency_text = 'Triannual';
        break;
      case '4':
        $dividend_frequency_text = 'Quarterly';
        break;
      case '12':
        $dividend_frequency_text = 'Monthly';
        break;
      default:
        $dividend_frequency_text = '';
    }
    return $dividend_frequency_text;
  }


  /**
   * A function to get data for the manager portfolio section
   * @param $citicode
   */
  public function getManagerData($citicode) {

    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      foreach ($fund_data['ManagerInformation']['ManagerPerson'] as $manager) {
        $return_data[] = $manager['ManagerPersonName'];
      }
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }

  /**
   * A function to get data for the FUND IDs section
   * @param $citicode
   */
  public function getFundIDsData($citicode, $type = NULL) {

    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $return_data['APIR'] = isset($fund_data['APIR']) ? $fund_data['APIR'] : '';
      $return_data['ARSN'] = isset($fund_data['ARSN']) ? $fund_data['ARSN'] : '';
      $return_data['TICKER'] = isset($fund_data['Ticker']) ? $fund_data['Ticker'] : '';
      if(!($type == 'xaro' || $type == 'xkap')) {
        $return_data['mFund'] = isset($fund_data['mFund']) ? $fund_data['mFund'] : '';
      }

      if($type == 'dual' || $type == 'xaro' || $type == 'xkap') {
        $return_data['ISIN'] = isset($fund_data['ISIN']) ? $fund_data['ISIN'] : '';
      }
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }


  /**
   * A function to get data for the Fees/Pricing section
   * @param $citicode
   */
  public function getFundFeesData($citicode) {

    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $return_data['management_cost'] = $fund_data['AMC'];
      $return_data['performance_fee'] = $fund_data['PerfFee'];
      $return_data['min_investment'] = $fund_data['MinInv'];
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }

  /**
   * A function to get data for the Fees/Pricing section
   * @param $citicode
   */
  public function getFundUnlistedData($citicode, $node) {

    $bsh_link = $node->field_buy_sell_history->uri;
    if(empty($bsh_link)) {
      $bsh_link = '';
    } else {
      $bsh_link = substr_count($bsh_link, 'node') > 0 ?  \Drupal::service('path_alias.manager')->getAliasByPath(str_replace('entity:', '/', $bsh_link)) : str_replace('entity:', '/', $bsh_link);
      $bsh_link = str_replace('internal:', '', $bsh_link);
    }
    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $return_data['buy_sell_spred'] = [
        'buy_spread' => $fund_data['BuySpread'] . '%',
        'sell_spread' => $fund_data['SellSpread'] . '%',
        'date' => $fund_data['BuySellSpreadDate'],
        'link' => [
          'url' => $bsh_link,
          'title' => $node->field_buy_sell_history->title,
        ],
      ];

      $return_data['unit_price']['value'] = 'Buy '. $fund_data['Offer'] . ' / '. 'Sell '. $fund_data['Bid'];
      $return_data['unit_price']['date'] = $fund_data['UnitPriceDate'];
      $uph_link = $node->field_unit_price_history->uri;
      if(empty($uph_link)) {
        $uph_link = '';
      } else {
        $uph_link = substr_count($uph_link, 'node') > 0 ?  \Drupal::service('path_alias.manager')->getAliasByPath(str_replace('entity:', '/', $uph_link)) : str_replace('entity:', '/', $uph_link);
        $uph_link = str_replace('internal:', '', $uph_link);
      }
      $return_data['unit_price']['link'] = [
        'url' => $uph_link,
        'text' => $node->field_unit_price_history->title,
      ];
      $return_data['min_investment'] = $fund_data['MinInv'];
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }

  /**
   * A function to get data for the Fees/Pricing section
   * @param $citicode
   */
  public function getFundFeesPricingdData($citicode) {
    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $return_data['management_cost'] = $fund_data['AMC'];
      $return_data['performance_fee'] = $fund_data['PerfFee'];
      $return_data['min_investment'] = $fund_data['MinInv'];
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }

  /**
   * A function to get data for the Fees/Pricing section
   * @param $citicode
   */
  public function getUnitPricesAndDistributionData($citicode) {
    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $cumulative_fund_data = $this->getCumulativeFundData($fund_data);
      $table =$this->prepareUnitPricesAndDistTable($cumulative_fund_data);
      $return_data['table'] = $table;
      $return_data['date'] = $fund_data['CumulativePerfAsAt_ME'];
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * Function to grab the Cumulative Fund datda from the payload.
   * @param $fund_data
   *
   * @return array
   */
  private function getCumulativeFundData($fund_data) {
    $index_replacement_array = [
      '1m' => '1M',
      '3m' =>'3M',
      '1y' =>'1YR',
      '3y' =>'3YR (p.a)',
      '5y' =>'5YR (p.a)',
      '10y' =>'10YR (p.a)',
      'sincelaunch' =>'SI (p.a)',
    ];
    $cumulative_fund_data = [];
    $annualised_fund_data = [];
    foreach ($fund_data as $key => $element) {
      if(substr_count($key, 'Cumulative') > 0) {
        $index = strtolower(str_replace('_ME', '', str_replace('Cumulative', '', $key)));
        if(in_array($index, ['1m', '3m', '1y'])) {
          $index = $index_replacement_array[$index];
          $cumulative_fund_data[$index] = $element;
        }

      }
    }

    foreach ($fund_data as $key => $element) {
      if(substr_count($key, 'Annualised') > 0) {
        $index = strtolower(str_replace('_ME', '', str_replace('Annualised', '', $key)));
        if(in_array($index, ['3y', '5y', '10y', 'sincelaunch'])) {
          $index = $index_replacement_array[$index];
          $annualised_fund_data[$index] = $element;
        }
      }
    }

    return array_merge($cumulative_fund_data, $annualised_fund_data);

  }



  /**
   * A function to prepare the table for unit prices and dist.
   * @param $array
   *
   * @return array
   */
  private function prepareUnitPricesAndDistTable($array) {
    $table_array = [];
    $table_array[0] = array_merge([''], array_keys($array));
    $table_array[1] = array_merge(['Fund'], array_values($array));
    return $table_array;
  }

  /**
   * A function to get the entire fundata payload per cititcode and cache it for one hour
   * @param $citicode
   *
   * @return mixed
   */
  private function getAllFundRatingsData($citicode) {
    $url = 'https://datafeeds.myfeed.com/api/funddata/ewcustom/123456f?rangename=ew&citicodes='.$citicode;
    try {

      $cid = 'ew_connect:' . 'fund_ratings_'.$citicode;
      $fund_ratings_data = NULL;
      // See if the data is cached.
      if ($cache = \Drupal::cache()->get($cid)) {
        $fund_ratings_data = $cache->data;
      }
      else {
        $client = new Client();
        $response = $client->get($url);
        $result = json_decode($response->getBody(), TRUE);
        $fund_ratings_data = $result[0];
        // Cache the data for one hour from now.
        $in_one_hour = strtotime("+1 hour");
        \Drupal::cache()->set($cid, $fund_ratings_data, $in_one_hour);

      }
      return $fund_ratings_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * A function to get fund ratings data
   * @param $citicode
   *
   * @return array
   */
  public function getFundRatingsData($citicode) {
    $return_data = [];
    try {
      $fund_ratings_data = $this->getAllFundRatingsData($citicode);
      $return_data = $this->prepareFundRatingsTable($fund_ratings_data);
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }

  }

  public function getTop10HoldingsaData($citicode) {
    $return_data = [];
    try {
      $fund_data = $this->getAllFundData($citicode);
      $return_data['date'] = $fund_data['Top10Holdings']['Top10HoldingsDate'];
      $return_data['breakdown'] = $this->prepareTop10HoldingsData($fund_data['Top10Holdings']['Breakdowns']['Breakdown']);
      return $return_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * A function to prepare the data in a tabular manner
   * @param $fund_ratings_data
   *
   * @return array
   */
  private function prepareFundRatingsTable($fund_ratings_data) {
    $table_array = [];
    $fund_ratings_data = array_diff_key($fund_ratings_data, ['Citicode'=> '', 'FundNameLong'=> '' , 'APIR' => '']);
    $table_array[0] = ['Organisation', 'Rating'];
    foreach ($fund_ratings_data as  $key => $rating) {
      if($key != 'BondAdvisor') {
        $table_array[] = [$key , $rating];
      }

    }

    return $table_array;
  }

  private function prepareTop10HoldingsData($top_10_holdings_raw_data) {
    $return_data = [];
    foreach ($top_10_holdings_raw_data as $data_element) {
      $return_data[] = [
        'name' => $data_element['BreakdownName'],
        'weight' => $data_element['BreakdownWeight'],
        'display_name' => $data_element['BreakdownDisplayName'],
      ];
    }

    return $return_data;
  }

  /**
   * Main function to get the share class data
   * @param $nid
   * @param $endpoint
   *
   * @return mixed
   */
  public function getEUShareClassData($nid, $endpoint) {
    $url = $endpoint;
    try {
      $client = new Client();
      $response = $client->get($url);
      $code = $response->getStatusCode();
      $share_class_data = json_decode($response->getBody(), TRUE);
      return $share_class_data;
    }
    catch (RequestException $e) {
      \Drupal::logger('ew_connect')->error($e->getMessage());
    }
  }

  /**
   * Function to structure the share class data into a table
   * @param $nid
   * @param $endpoint
   *
   * @return array|\string[][]
   */
  public function getEUShareClassTableData($nid, $endpoint) {
    $share_class_data = $this->getEUShareClassData($nid, $endpoint);
    $table_data = [];
    $headings = [0 => ['Share class', 'Currency', 'ISIN', 'NAV', 'KIID / KID' ]];
    $share_class_date = '';
    $count = 0;
    foreach ($share_class_data as $row) {
      if($count == 0 || empty($share_class_date)) {
        $share_class_date = $row['UnitPriceDate'];
      }
      $table_data[] = [
        $row['UnitNameShort'],
        $row['Currency'],
        $row['ISIN'],
        $row['Mid'],
        'On Request',

      ];
    }
    return array('date' => $share_class_date,  'table' =>array_merge($headings, $table_data));
  }
}
