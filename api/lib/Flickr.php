<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/13/16
 * Time: 3:26 PM
 */

namespace api\lib;


use Imagine\Image\Box;
use yii\base\Component;
use yii\helpers\Url;
use yii\imagine\Image;

class Flickr extends Component
{
    public $api_key;
    
    public $path_upload;
    
    public function download($url,$fileName){
        $md5_file = md5($fileName);
        $tmp_dir = \Yii::getAlias('@webroot') . $this->path_upload . substr($md5_file, 0, 2) . '/' . substr($md5_file, 2, 2);
        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0777, true);
        }
        $dst_tmp = $tmp_dir .'/'. $md5_file.'.jpg';

        Image::frame($url,0,0,0)->resize(new Box(800,800 ))->save($dst_tmp);

        $url = array("url" => Url::to('@web'.$this->path_upload .substr($md5_file, 0, 2) . '/' . substr($md5_file, 2, 2) . "/{$md5_file}.jpg",true) , "original_url" => $url);
        return $url;
    }
    
    public function getPhotos($q,$limit = 20){
        $opts = array(
            'method' => 'flickr.photos.search',
            'api_key' => $this->api_key,
            'text' => $q,
            'format' => 'json',
            'nojsoncallback' => 1,
            'sort' => 'interestingness-desc',
            'per_page' => $limit
        );

        $url = 'https://api.flickr.com/services/rest?' . http_build_query($opts, '', '&');
        //app_error_log("[debug] $url");
        $res = @json_decode(file_get_contents($url), true);
        //return $res;
        if (isset($res['photos'])) {
            $data = array();
            foreach ($res['photos']['photo'] as $p) {
                $data[] = "https://farm" . $p['farm'] . ".staticflickr.com/" . $p['server'] . "/" . $p['id'] . "_" . $p['secret'] . "_n.jpg";
                // $data[] = downloadFlickr("https://farm".$p['farm'].".staticflickr.com/".$p['server']."/".$p['id']."_".$p['secret']."_n.jpg", $p['id']."_".$p['secret']."_n.jpg");
            }
            $results = array('success' => 1, 'data' => $data);
        } else {
            $results = array('success' => 0);
        }
        return $results;
    }

}