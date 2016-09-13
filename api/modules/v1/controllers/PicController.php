<?php

namespace api\modules\v1\controllers;

use api\helpers\Result;
use api\helpers\LibHelp;
use api\helpers\UserHelp;
use api\modules\v1\models\Airport;
use api\modules\v1\models\Pic;
use api\modules\v1\models\User;
use api\modules\v1\models\UserSns;
use tpmanc\imagick\Imagick;
use Yii;

use yii\helpers\Url;
use yii\rest\ActiveController;
use yii\validators\EmailValidator;
use yii\validators\StringValidator;
use yii\validators\UrlValidator;
use yii\web\Session;

/**
 * Country Controller API
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class PicController extends ActiveController
{
    public $modelClass = 'api\modules\v1\models\Pic';


    public function init()
    {
        parent::init();

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;    // default this controller to JSON, otherwise it's FORMAT_HTML
    }


    public function actionUpload()
    {
        ini_set('memory_limit', '128M');
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }

        if ($img = LibHelp::parseDataPost('pic_64')) {
            $img = str_replace('data:image/png;base64,', '', $img);
            $img = str_replace(' ', '+', $img);
            $data = base64_decode($img);

            $tmp_path_64 = \Yii::getAlias('@webroot') . Pic::TMP_Path . 'base_64/';

            $imgName64 = uniqid() . '.png';
            $path64 = $tmp_path_64 . $imgName64;

            $success = file_put_contents($path64, $data);
            $md5_file = md5_file($path64);
            $filesize = filesize($path64);
            $tmpName = $path64;
            list($width, $height, $imgtype) = getimagesize($tmpName);
        } elseif (($pic = $_FILES['pic']) && (is_uploaded_file($_FILES['pic']['tmp_name']) && !$_FILES['pic']['error'])) {
            $tmpName = $pic['tmp_name'];
            list($width, $height, $imgtype) = getimagesize($tmpName);

            $valid_types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG);
            if (!in_array($imgtype, $valid_types)) {
                return Result::Error('invalid file type');
            }
            // reset orientation while copying
            Pic::copyJpg($tmpName, $tmpName, 100);
            // obtain dimensions again in case the orientation changed
            list($width, $height, $imgtype) = getimagesize($tmpName);
            // minimum 200x200
            if ($width < 200 || $height < 200) {
                return Result::Error('invalid dimensions (must be at least 200x200)');
            }


            if ($width > 1024 || $height > 1024) {
                Imagick::open($tmpName)->resize(1024, 1024)->saveTo($tmpName);
            }

            $md5_file = md5_file($tmpName);
            $filesize = filesize($tmpName);

        }
        $type_no = LibHelp::parseDataPost('type_no') ? intval(LibHelp::parseDataPost('type_no')) : 1;

        $old = Pic::findOne(['md5val' => $md5_file, 'filesize' => $filesize]);

        $tmp_path = Pic::TMP_Path . substr($md5_file, 0, 2) . '/' . substr($md5_file, 2, 2);
        $url = Url::to('@web' . $tmp_path . "/{$md5_file}-{$filesize}.jpg", true);

        if ($old) {
            if (LibHelp::parseDataPost('pic_64')) {
                unlink($path64);
            }
            $result = ['data' => ['id' => $old->id, 'url' => $url]];
        }

        if (!$result) {
            $tmp_dir = \Yii::getAlias('@webroot') . $tmp_path;

            @mkdir($tmp_dir, 0777, true);
            $dst_tmp = "{$tmp_dir}/{$md5_file}-{$filesize}.jpg";
            copy($tmpName, $dst_tmp);
            $dst_thumb = "{$tmp_dir}/{$md5_file}-{$filesize}-t.jpg";
            Imagick::open($tmpName)->resize(200, 200)->saveTo($dst_thumb);
            $pic = new Pic();
            $pic->user_id = $user->id;
            $pic->type_no = $type_no;
            $pic->md5val = $md5_file;
            $pic->filesize = $filesize;
            $pic->width = $width;
            $pic->height = $height;
            $pic->ext = 'jpg';
            $pic->save();

            if ($pic->errors) {
                return Result::Error('db error');
            }


            $result = ['data' => ['id' => $pic->getPrimaryKey(), 'url' => $url]];
        }
        $cbData = LibHelp::parseDataPost('cb');
        if ($cbData && preg_match('!^(window.parent.)?[a-zA-Z_][a-zA-Z0-9]*$!', $cbData) == 1) {
            header_remove("Content-Type");
            header("Content-Type: text/html; charset=UTF-8");
            $jsonOutput = json_encode($result);
            echo '<script>' . $cbData . '(' . $jsonOutput . ');</script>';
        }

        return $result;
    }

}
