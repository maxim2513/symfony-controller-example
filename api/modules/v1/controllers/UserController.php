<?php

namespace api\modules\v1\controllers;

use api\helpers\Result;
use api\helpers\LibHelp;
use api\helpers\UserHelp;
use api\modules\v1\models\Airport;
use api\modules\v1\models\User;
use api\modules\v1\models\UserSns;
use Facebook\Facebook;
use Yii;

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
class UserController extends ActiveController
{
    public $modelClass = 'api\modules\v1\models\User';


    public function init()
    {
        parent::init();

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;    // default this controller to JSON, otherwise it's FORMAT_HTML
    }

    /**
     * Sign up user either through email or SNS endpoint
     *
     * @return array
     * @throws Exception
     */
    public function actionSignup()
    {
        $postData = \Yii::$app->request;
        $email_validator = new EmailValidator();
        // method 1: manual full login
        if (LibHelp::parseDataPost('email')) {
            $email = strtolower(LibHelp::parseDataPost('email'));

            if (!$email_validator->validate($email, $error)) {
                return Result::Error('invalid email');
            }

            $nickname = (LibHelp::parseDataPost('nickname') && strlen(LibHelp::parseDataPost('nickname')) > 1) ? LibHelp::parseDataPost('nickname') : null;
            $user_nick = User::findOne(['nickname' => $nickname]);
            $user = User::findOne(['email' => $email]);
            if ($user && $user_nick && $user_nick == $user) {
                if (!$user->passwd) {
                    $user->passwd = md5(trim(LibHelp::parseDataPost('passwd')));
                    $user->token = UserHelp::getToken();
                    $user->updated = time();
                    $user->save();
                    if ($user->errors) {
                        return Result::Error($user->errors);
                    }
                    return Result::Success(['data' => ['id' => $user->id, 'token' => $user->token]]);
                } else {
                    return Result::Error('user email exists');
                }
            }
            if ($user_nick) {
                return Result::Error('user nickname exists');
            }
            if (!$user) {
                $user = new User();
                $user->newUser();
                $user->nickname = $nickname;
                $user->email = $email;
                $user->passwd = md5(trim(LibHelp::parseDataPost('passwd')));
            }

            $user->save();
            if ($user->errors) {
                return Result::Error($user->errors);
            }
            return Result::Success(['data' => ['id' => $user->getPrimaryKey(), 'token' => $user->token]]);

//            
//            admin actions
//            
        }

//
//        SNS - Facebook
//
        if (1 == LibHelp::parseDataPost('sns_no')) {
            $sns_uid = LibHelp::parseDataPost('sns_uid');
            $fb = new Facebook(
                Yii::$app->params['Facebook']
            );
            $client = $fb->getOAuth2Client();
            $token = $client->getLongLivedAccessToken(LibHelp::parseDataPost('sns_token'));
            $fb->setDefaultAccessToken($token);

            $user_sns_data = $fb->get('/me?fields=id,name,email,gender,picture.width(200).height(200),permissions');
            $user_sns_data = $user_sns_data->getDecodedBody();

            if (!$user_sns_data) {
                return Result::Error('invalid sns login');
            }
            if ($user_sns_data['id'] != $sns_uid) {
                return Result::Error('invalid sns login');
            }

            $pic = (isset($user_sns_data['picture']['data']['url']))
                ? $user_sns_data['picture']['data']['url']
                : null;
            $user_sns = UserSns::findOne(['sns_no' => 1, 'sns_uid' => $sns_uid]);
            $user = '';
            if ($user_sns) {
                $user = $user_sns->user;
                if (!$user->email && $user_sns_data['email']) {
                    $user->email = $user_sns_data['email'];
                }
                $user->pic = $pic;
                $user_sns->sns_token = $token->getValue();
                $user->save();
                $user_sns->save();

            } else {
                $email = isset($user_sns_data['email']) ? $user_sns_data['email'] : "";
                $user = User::findOne(['email' => $email]);
                if ($user) {
                    $user->token = UserHelp::getToken();
                    $user->nickname = $user_sns_data['name'];
                    $user->pic = $pic;
                    $user->updated = time();
                    $user->save();
                } else {
                    $user = new User();
                    $user->newUser();
                    $user->email = $user_sns_data['email'];
                    $user->nickname = $user_sns_data['name'];
                    $user->pic = $pic;
                    $user->state = 'active';
                    $user->save();
                }
                $user_sns = new UserSns();
                $user_sns->newUser();
                $user_sns->user_id = $user->getPrimaryKey();
                $user_sns->sns_no = 1;
                $user_sns->sns_token = $token->getValue();
                $user_sns->sns_uid = $sns_uid;
                $user_sns->save();

            }

            if ($user_sns->errors || $user->errors) {
                return Result::Error('db error');
            }
            return Result::Success(['data' => ['id' => $user->getPrimaryKey(), 'token' => $user->token]]);

        }

        return Result::Error('invalid login method');

    }

    /**
     * Login:
     *      1) manual
     * @return array
     */
    public function actionLogin()
    {
        $postData = \Yii::$app->request;
        $email_validator = new EmailValidator();

        // method 1: manual login
        if (LibHelp::parseDataPost('email')) {
            $email = strtolower(trim(LibHelp::parseDataPost('email')));

            if (!$email_validator->validate($email, $error)) {
                return Result::Error('invalid email');
            }

            $user = User::findOne(['email' => $email]);

            if (!$user || md5(trim(LibHelp::parseDataPost('passwd'))) != $user->passwd) {
                return Result::Error('invalid login');
            }
            $user->updated = time();
            $user->save();
            // 
            // memcache insert
            //
            return Result::Success(['data' => ['id' => $user->id, 'token' => $user->token]]);
        }
        if (1 == LibHelp::parseDataPost('sns_no')) {
            return $this->actionSignup();
        }
        return Result::Error('invalid login method');
    }

    /**
     * Set current user profile
     * @return array
     */
    public function actionSetProfile()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $param = ['nickname', 'name', 'phone', 'pic', 'bio', 'link', 'email'];
        foreach ($param as $val) {
            $$val = LibHelp::parseDataPost($val);
            if (!$$val) {
                return Result::Error('forbidden');
            }
        }

        if ($nickname) {
            $user_nick = User::findOne(['nickname' => $nickname]);
            if ($user_nick && $user->id !== $user_nick->id) {
                return Result::Error('user nickname exists');
            }
            unset($user_nick);
        }

        if ($email) {

            $user_mail = User::findOne(['email' => $email]);
            if ($user_mail && $user->id !== $user_mail->id) {
                return Result::Error('user email exists');
            }

            if (!(new EmailValidator())->validate($email)) {
                return Result::Error('invalid email');
            }
            unset($user_mail);
        }
        if ($bio) {
            $validator = new StringValidator();
            $validator->max = 224;
            if (!$validator->validate($email)) {
                return Result::Error('Notes can have maximum 224 characters');
            }
        }

        foreach ($param as $val) {
            $user->$val = $$val;
        }
        $user->updated = time();
        $user->save();

        return $this->actionGetProfile();
    }

    /**
     * Get user profile
     * @return array
     */
    public function actionGetProfile()
    {
        $id = LibHelp::parseDataPost('user');
        if ($user = User::findOne(['token' => \Yii::$app->request->post('t'), 'id' => \Yii::$app->request->post('u')])) {
            $data = $user->getProfile($id);
            return Result::Success(['data' => $data]);

        } else {
            return Result::Error('forbidden');
        }
    }

    /**
     * Logout endpoint
     *
     * @return bool
     */
    public function actionLogout()
    {
        $user = User::loginToken();
        if($user) {
            $user->token = UserHelp::getToken();
            $user->save();
        }
        return true;
    }

    public function actionFbFriends()
    {
        return UserHelp::getFbFriends(LibHelp::parseDataPost('sns_token'));
    }

    public function actionTest()
    {
        return UserHelp::getFbFriends(LibHelp::parseDataPost('sns_token'));
    }

    public function actionForgotPassword()
    {
        $email = LibHelp::parseDataPost('email');

        if (!(new EmailValidator())->validate($email)) {
            return Result::Error('invalid email');
        }
        if ($user = User::findOne(['email' => $email])) {

            if (!UserHelp::sendPasswordResetToken($user->id)) {
                return Result::Error('unknown error');
            }


        } else {
            return Result::Error('user not found');
        }
        return Result::Success(['msg' => 'An email has been sent to your email address to reset your password.']);

    }

    public function actionResetPassword()
    {
        $passwd = LibHelp::parseDataPost('passwd');
        $hash = LibHelp::parseDataPost('token');
        if (!$passwd || !$hash) {
            return Result::Error('invalid token or password');
        }
        if (!$user = User::findOne(['passwd_reset' => $hash])) {
            return Result::Error('invalid token');
        }
        if ($user->reset_expires < time()) {
            return Result::Error('token expired');
        }
        $user->passwd = md5($passwd);
        $user->updated = time();
        $user->save();
        return Result::Success(['msg' => 'Password has been reset']);
    }

    public function actionGetLocation()
    {

        $session = new Session();
        $session->open();
        if (!$session['location']) {
            $location = Yii::$app->geolocation->getInfo();
            $session->set('location', [
                'latitude' => $location['geoplugin_latitude'],
                'longitude' => $location['geoplugin_longitude']
            ]);

        }
        return Result::Success(['location' => $session['location']]);
    }

    public function actionSetLocation()
    {

        $session = new Session();
        $session->open();
        if (($lat = Yii::$app->request->post('latitude')) && ($log = Yii::$app->request->post('longitude'))) {
            $session->set('location', [
                'latitude' => $lat,
                'longitude' => $log
            ]);
            return Result::Success(['location' => $session['location']]);
        }
        return Result::Error('Latitude or Longitude not sent');
    }

    public function actionGetAirports()
    {
        $session = new Session();
        $session->open();
        if (!$session['location']) {
            $this->actionGetLocation();
        }
        if (!$session['location']['latitude'] || !$session['location']['longitude']) {
            return Result::Error('no geolocation information');
        } else {
            return Result::Success(
                ['data' => Airport::getNear($session['location']['latitude'], $session['location']['longitude'])]
            );
        }

    }

    public function actionSetAirport()
    {
        $session = new Session();
        $session->open();
        if ($airport = LibHelp::parseDataPost('airport')) {
            if ($session['location']) {
                $location = $session['location'];
                $location['airport'] = $airport;
                $session->set('location', $location);
            } else {
                $session->set(
                    'location', ['airport' => $airport]
                );
            }

            return Result::Success(['data' => $session['location']['airport']]);
        } else {
            return Result::Error("airport not set");
        }
    }
}
