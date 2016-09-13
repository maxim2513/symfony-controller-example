<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/1/16
 * Time: 2:56 PM
 */

namespace api\helpers;


use api\modules\v1\models\User;
use api\modules\v1\models\UserSns;
use Facebook\Facebook;
use yii\helpers\Url;

class UserHelp
{
    public static function getToken($salt = null)
    {
        $salt = $salt ? $salt : isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

        $a = array(uniqid(), $salt, microtime(), rand(1, 999999));
        shuffle($a);
        return md5(implode(':', $a));
    }
    
    public static function sendPasswordResetToken($user_id)
    {
        $exists = true;
        $hash = '';

        while ($exists) {
            $hash = substr(md5(rand()) . $user_id . md5(rand()) . md5(rand()), 0, 127);

            if (User::findOne(['passwd_reset' => $hash])) {
                $exists = true;
            } else {
                $exists = false;
            }
        }
        $user = User::findOne($user_id);
        $user->reset_expires = time() + 3600;
        $user->passwd_reset = $hash;
        $user->save();
        $mail = \Yii::$app->mailer->compose();

        $mail->setFrom(array('no-reply@tripverse.co' => 'Tripverse Accounts'));
        $mail->setTo($user->email);

        $mail->setSubject('Tripverse - Password Reset');

        $link = Url::to('@web' . '/reset-password/' . $hash,true);

        $mail->setHtmlBody('
    <html>
    <head>
      <title>Tripverse - Password Reset</title>
    </head>
    <body>
      Hello Tripverse user,<br><br>
      To reset your password, simply click the link below.<br><br>
      <a href="' . $link . '">' . $link . '</a><br><br>
      Thanks.
    </body>
    </html>
    ');


        return $mail->send();
    }

    public static function getFbFriends($token){
        $fb = new Facebook(
            \Yii::$app->params['Facebook']
        );

        $fb->setDefaultAccessToken($token);

        $user_sns_data = $fb->get('/me/friends');
        $data = $user_sns_data->getDecodedBody();

        if (!$data || empty($data['data'])) {
            return array();
        }
        $fb_ids = array();
        foreach ($data['data'] as $friend) {
            $fb_ids[] = $friend['id'];
        }
        if (!sizeof($fb_ids)) {
            return array();
        }
        $friends = UserSns::getUsersByUid($fb_ids);

        $ids= [];
        foreach ($friends as $friend){
            $ids[]=$friend->user_id;
        }

        return $ids;
    }
}