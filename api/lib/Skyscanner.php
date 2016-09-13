<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/29/16
 * Time: 5:22 PM
 */

namespace api\lib;


use api\lib\models\SkyscannerCache;

class Skyscanner
{
    public $apiKey;

    function tryFareFetch($start_airports, $airports, $start_date, $market, $currency, $locale)
    {
        $add_cache = true;
        foreach ($airports as $airport) {
            $cache_hit = SkyscannerCache::findOne(['start' => $start_airports, 'end' => $airport['code']]);

            $result = '';

            if ($cache_hit) {
                $cache_time = strtotime($cache_hit->date);
                $cache_limit_time = new \DateTime('now');
                $cache_limit_time->add(new \DateInterval('P10D'));
                if ($cache_time > $cache_limit_time->format('U')) {
                    $cache_hit->delete();
                } else {
                    $result = unserialize($cache_hit->data);
                    $add_cache = false;
                }
            }
            if (!$result) {
                $market = ($market == '') ? 'US' : $market;
                $currency = ($currency == '') ? 'USD' : $currency;
                $locale = ($locale == '') ? 'en-GB' : $locale;
                $end_date = date('Y-m-d', strtotime($start_date . " +7 days"));//Would be needed for round trips

                //Google flight api handled several start points -> With SkyScanner we need several calls
                $rawResult = [];
                foreach ($start_airports as $start_airport) {
                    $SSUrl = "http://partners.api.skyscanner.net/apiservices/browsequotes/v1.0/" .
                        $market . "/" .
                        $currency . "/" .
                        $locale . "/" .
                        $start_airport . "/" .
                        $airport['code'] . "/" .
                        $start_date . "/" .
                        $end_date .
                        "?apiKey=" . $this->apiKey;
                    $ch = curl_init($SSUrl);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    $curlResult = curl_exec($ch);
                    $rawResult[] = json_decode($curlResult, true);
                    curl_close($ch);
                }

                //Merge results of each api call
                foreach ($rawResult as $resultForAirport) {
                    if (empty($resultForAirport['ValidationErrors'])) {
                        if (!$result) {
                            $result = $resultForAirport;
                        } else {//combine results
                            
                            $result['Quotes'] = array_merge($result['Quotes'], $resultForAirport['Quotes']);
                            $result['Places'] = array_merge($result['Places'], $resultForAirport['Places']);
                            $result['Carriers'] = array_merge($result['Carriers'], $resultForAirport['Carriers']);
                        }
                    }
                }
            }

            if ($result) {
                break;
            }
        }
        if(!$result){
            return false;
        }
        $start_airport = '';
        if (!empty($result['Quotes'])) {
            $trip_airports = array();
            foreach ($result['Places'] as $airport) {
                $trip_airports[] = $airport['IataCode'];
            }

            $start_airport_array = array_intersect($trip_airports, $start_airports);
            $start_airport = reset($start_airport_array);
        }
        if ($add_cache && $start_airport) {
            $cache = new SkyscannerCache();
            $cache->start = $start_airport;
            $cache->end = $airport['code'];
            $cache->date = $start_date;
            $cache->data = serialize($result);
            $cache->save();
        }
        $msg = "Fare info fetch from skyscanner";
        if($cache_hit){
            $msg = "Fare info fetch from cache";
        }
        
        return [
            'data' => array(
                "result" => $result,
                "request" => array(
                    "origin" => $start_airport,
                    "destination" => $airport['code'],
                    "destination_latitude"=>$airport['latitude'],
                    "destination_longitude"=>$airport['longitude'],
                    "date" => $start_date
                )
            ),
            'msg' => $msg
        ];
        
    }
}