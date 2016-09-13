<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/27/16
 * Time: 5:28 PM
 */

namespace api\lib;


class Foursquare
{
    public $config;

    public function getPhoto($id, $limit = 10)
    {
        $opts = $this->config;
        $opts['limit'] = $limit;

        $url = 'https://api.foursquare.com/v2/venues/' . $id . '/photos?' . http_build_query($opts, '', '&');
        //app_error_log("[debug] $url");
        $res = @json_decode(file_get_contents($url), true);
        $data = [];
        if (isset($res['response']) && isset($res['response']['photos']) && isset($res['response']['photos']['items'])) {
            foreach ($res['response']['photos']['items'] as $p) {
                $data[] = $p['prefix'] . 'width300' . $p['suffix'];
            }
        }
        return $data;

    }

    public function autocomplete($query, $ll, $near = null)
    {
        $query = trim(urldecode($query));
        $opts = $this->config;

        $opts['query'] = $query;
        //'near'=>$near,
        $opts['ll'] = $ll;
        $opts['radius'] = 100000;
        $opts['intent'] = 'browse';
        $opts['limit'] = 10;


        $url = 'https://api.foursquare.com/v2/venues/search?' . http_build_query($opts, '', '&');
        //app_error_log("[debug] $url");
        $res = @json_decode(file_get_contents($url), true);
        if ( /*isset($res['meta']) && isset($res['meta']['code']) && $res['meta']['code'] == "200" &&*/
            isset($res['response']) && isset($res['response']['venues'])
        ) {
            $data = [];
            foreach ($res['response']['venues'] as $v) {

                //return $v['location'];
                $formattedAddress = isset($v['location']) && isset($v['location']['formattedAddress']) ? $v['location']['formattedAddress'] : array();
                foreach ($formattedAddress as $key => $val) {
                    //cleanup empty fields
                    if (!$val) {
                        unset($formattedAddress[$key]);
                    }
                }
                $lat = isset($v['location']) && isset($v['location']['lat']) ? $v['location']['lat'] : '';
                $lng = isset($v['location']) && isset($v['location']['lng']) ? $v['location']['lng'] : '';
                $addressSize = sizeof($formattedAddress);
                if ($addressSize > 1) {
                    $location = $this->parseAddress($formattedAddress[$addressSize - 2]) . ', ' . $formattedAddress[$addressSize - 1];
                } elseif ($addressSize == 1) {
                    $location = $formattedAddress[0];
                }
                $address = implode(", ", $formattedAddress);
                $name = $v['name'];
                $phone = isset($v['contact']) && isset($v['contact']['formattedPhone']) ? $v['contact']['formattedPhone'] : "";
                if ($address && $name) {
                    //$photos = app_spot_get_photos($v['id']);
                    $data[] = ['id' => $v['id'],
                        'name' => $name,
                        'address' => $address,
                        'phone' => $phone,
                        'location' => $location,
                        'lat' => $lat,
                        'lng' => $lng
                    ];
                }
            }
            return $data;
        }
        return false;
    }

    public function getDetails($id)
    {
        $opts = $this->config;
        $opts['limit'] = 10;

        $url = 'https://api.foursquare.com/v2/venues/' . $id . '?' . http_build_query($opts, '', '&');
        //app_error_log("[debug] $url");
        $res = @json_decode(file_get_contents($url), true);
        if (isset($res['response']) && isset($res['response']['venue'])) {
            $url = isset($res['response']['venue']['shortUrl']) ? $res['response']['venue']['shortUrl'] : "";
            $hoursData = isset($res['response']['venue']['hours']) ? $res['response']['venue']['hours'] : "";
            $timeframes = isset($hoursData['timeframes']) ? $hoursData['timeframes'] : array();
            $hours_array = array();

            foreach ($timeframes as $timeframe) {
                $timeframe_array = array();
                $timeframe_array[] = isset($timeframe['days']) ? $timeframe['days'] : '';

                if (isset($timeframe['open'])) {
                    foreach ($timeframe['open'] as $open) {
                        $timeframe_array[] = isset($open['renderedTime']) ? $open['renderedTime'] : '';
                    }
                }
                $hours_array[] = implode(' ', $timeframe_array);
            }

            $tipsData = isset($res['response']['venue']['tips']) ? $res['response']['venue']['tips'] : "";
            $tips = [];
            $tips_lenght = 0;

            if (isset($tipsData['groups'])) {
                foreach ($tipsData['groups'] as $group) {
                    foreach ($group['items'] as $item) {
                        $text = $item['text'];
                        $tips_lenght += strlen($text);
                        if ($tips_lenght) {
                            $tips[] = $text;
                            break;
                        }

                    }
                    if ($tips_lenght) {
                        break;
                    }
                }
            }

            return ['url' => $url, 'hours' => substr(implode("\n", $hours_array), 0, 224), 'tips' => substr(implode("\n\n", $tips), 0, 614)];
        }
        
        return false;
       
    }

    public function getTrending($near,$limit = 5, $offset = 0, $category = false){
        $opts = $this->config;
        $opts['limit'] = $limit;
        $opts['near'] = $near;
        $opts['venuePhotos']=1;
        $opts['offset'] = $offset;

        if ($category) {
            $opts['section'] = $category;
        }

        $url = 'https://api.foursquare.com/v2/venues/explore?' . http_build_query($opts, '', '&');
        //app_error_log("[debug] $url");
        $res = @json_decode(file_get_contents($url), true);
        //return $res['response']['groups'][0];
        if ( /*isset($res['meta']) && isset($res['meta']['code']) && $res['meta']['code'] == "200" &&*/
            isset($res['response']) && isset($res['response']['groups'])
        ) {
            $data = array();
            foreach ($res['response']['groups'] as $g) {
                if (isset($g['name']) && $g['name'] == 'recommended' && isset($g['items'])) {
                    foreach ($g['items'] as $i) {
                        if (isset($i['venue'])) {

                            $formattedAddress = isset($i['venue']['location']) && isset($i['venue']['location']['formattedAddress']) ? $i['venue']['location']['formattedAddress'] : array();
                            foreach ($formattedAddress as $key => $val) {
                                //cleanup empty fields
                                if (!$val) {
                                    unset($formattedAddress[$key]);
                                }
                            }
                            $address = implode(", ", $formattedAddress);

                            $lat = isset($i['venue']['location']) && isset($i['venue']['location']['lat'])
                                ? $i['venue']['location']['lat']
                                : '';
                            $lng = isset($i['venue']['location']) && isset($i['venue']['location']['lng'])
                                ? $i['venue']['location']['lng']
                                : '';
                            $lat = round(floatval($lat), 6) . '';
                            $lng = round(floatval($lng), 6) . '';

                            if (isset($i['venue']['photos']['groups'])) {
                                $part_img = $i['venue']['photos']['groups'][0]['items'][0];
                                $img = $part_img['prefix'] . 'width300' . $part_img['suffix'];
                            } else {
                                $img = '';
                            }

                            $name = $i['venue']['name'];
                            $phone = isset($i['venue']['contact']) && isset($i['venue']['contact']['formattedPhone']) ? $i['venue']['contact']['formattedPhone'] : "";
                            if ($address && $name) {
                                $category = isset($i['venue']['categories']) && isset($i['venue']['categories'][0]) && isset($i['venue']['categories'][0]['name']) ? $i['venue']['categories'][0]['name'] : "";
                                $url = "https://foursquare.com/v/" . $i['venue']['id'];
                                $data[] = ['id' => $i['venue']['id'],
                                    'name' => $name,
                                    'lat' => $lat,
                                    'lng' => $lng,
                                    'address' => $address,
                                    'phone' => $phone,
                                    'img' => $img,
                                    'url' => $url,
                                    'category' => $category
                                ];
                            }
                        }
                    }
                }
            }
            return $data;
        } 
        return false;
    }
    
    private function parseAddress($string)
    {
        preg_match_all('/\d+ /', $string, $match);
        $search = end($match[0]);
        if (!$search) {
            return $string;
        }
        $pos = strpos($string, $search);
        $pos += strlen($search);
        return substr($string, $pos);
    }
}