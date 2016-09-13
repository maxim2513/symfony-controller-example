<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/19/16
 * Time: 8:10 PM
 */

namespace api\lib;


class GoogleApi
{
    public $geocode_api_key;

    public function getGeoCodeObject($address = null, $latlng = null , $region = 'tw')
    {
        if ($address !== null || $latlng !== null) {

            switch (true) {
                case $address !== null:
                    $querystring = '?address=' . urlencode($address);
                    break;
                case $latlng !== null:
                    $querystring = '?latlng=' . $latlng;
                    break;
                default:
                    $querystring = '';
            }

            // concat query string
            $querystring = str_replace(' ', '%20', $querystring);

            // query by address string
            $geoCodeUrl = 'https://maps.googleapis.com/maps/api/geocode/json'
                . $querystring
                . '&region=' . $region
                . '&key=' . $this->geocode_api_key;

            // get geocode object
            try {
                $ch = curl_init($geoCodeUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $response = curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                $msg = (isset($e->errorInfo[2])) ? $e->errorInfo[2] : $e->getMessage();
                if (!$this->quiet) {
                    \Yii::$app->getSession()->setFlash('error', $msg);
                    echo "\n -> Alert: " . $msg;
                }
            }

            // json decode response
            $response_a = json_decode($response);

            if (isset($response_a->results[0])) {
                return $response_a->results[0];
            } else {
                return null;
            }
        } 
        return null;
    }
    
    public function PlaceAutocomplete($input = '', $params = array())
    { // https://developers.google.com/places/documentation/autocomplete
        $input = trim(urldecode($input));
        $opts = array(
            'input' => $input,
            'sensor' => 'false',
            'key' => $this->geocode_api_key,
            'types' => (isset($params['types']) ? $params['types'] : '(regions)'),
            'language' => 'en',
            'location' => '0,0',
        );

        // e.g. country:fr
        if (isset($params['components'])) {
            $opts['components'] = $params['components'];
        }
        $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?' . http_build_query($opts, '', '&');
        $res = @json_decode(file_get_contents($url), true);
        if (isset($res['status']) && $res['status'] == "OK" && isset($res['predictions']) && count($res['predictions']) > 0) {
            $data = array();
            foreach ($res['predictions'] as $p) {
                $data[] = array('name' => $p['description'], 'ref' => $p['reference']);
            }
            $results = array('success' => 1, 'data' => $data);
        } else {
            $results = array('success' => 0);
        }
        return $results;
    }
}