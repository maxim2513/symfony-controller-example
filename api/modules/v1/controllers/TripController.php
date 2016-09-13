<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/10/16
 * Time: 11:30 PM
 */

namespace api\modules\v1\controllers;


use api\helpers\Result;
use api\helpers\LibHelp;
use api\helpers\TripHelp;
use api\helpers\UserHelp;
use api\lib\Flickr;
use api\modules\v1\models\Airport;
use api\modules\v1\models\Pic;
use api\modules\v1\models\Spot;
use api\modules\v1\models\Trip;
use api\modules\v1\models\TripAccess;
use api\modules\v1\models\TripDestination;
use api\modules\v1\models\TripFav;
use api\modules\v1\models\TripHash;
use api\modules\v1\models\TripTag;
use api\modules\v1\models\TripUserInvited;
use api\modules\v1\models\User;
use api\modules\v1\models\UserSns;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\rest\ActiveController;

class TripController extends ActiveController
{
    public $modelClass = 'api\modules\v1\models\Trip';

    public function actions()
    {
        $actions = parent::actions();

        // отключить действия "delete" и "create"
        unset($actions['create']);
        unset($actions['update']);
        unset($actions['delete']);
        unset($actions['view']);
        unset($actions['index']);

        return $actions;
    }

    protected function verbs()
    {
        return [
            'create' => ['POST'],
            'update' => ['POST'],
            'delete' => ['POST'],
            'view' => ['POST'],
            'index' => ['POST'],
        ];
    }

    public function init()
    {
        parent::init();

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;    // default this controller to JSON, otherwise it's FORMAT_HTML
    }

    public function actionCreate()
    {
        $user = User::loginToken();
        if ($user == false) {
            return Result::Error('forbidden');
        }

        $trip_new = new Trip();

        $trip_new->user_id = $user->getPrimaryKey();

        $trip_new->name = urldecode(LibHelp::parseDataPost('name'));

        $yr = LibHelp::parseDataPost('yr');
        $trip_new->yr = LibHelp::validateDate($yr, 'Y') ? $yr : null;

        $mth = LibHelp::parseDataPost('mth');
        $trip_new->mth = LibHelp::validateDate($mth, 'm') ? $mth : null;

        $day = LibHelp::parseDataPost('day');
        $trip_new->day = LibHelp::validateDate($day, 'd') ? $day : null;

        $trip_new->description = LibHelp::parseDataPost('description') ? LibHelp::parseDataPost('description') : '';

        $trip_new->url = LibHelp::parseDataPost('url');

        $trip_new->is_private = TripHelp::validateIsPrivate(LibHelp::parseDataPost('is_private'));

        $destinations = array();

        for ($i = 1; $i <= 5; $i++) {
            $desc = $i == 1 ? '' : $i;
            if (LibHelp::parseDataPost('destination' . $desc)) {
                $destinations[] = urldecode(LibHelp::parseDataPost('destination' . $desc));
            }
        }

        if (!$trip_new->name || !isset($destinations[0])) {
            return Result::Error('require name and destination');
        }

        $img_url = LibHelp::parseDataPost('img_url');
        if (strpos($img_url, "flickr") !== false) {
            $img = \Yii::$app->flickr->download($img_url, basename($img_url));
            $trip_new->img_url = $img['url'];
            $trip_new->original_img_url = $img['original_url'];
        } else {
            $trip_new->img_url = $img_url;
            $trip_new->original_img_url = null;
        }

        $trip_new->pic_id = intval(LibHelp::parseDataPost('pic_id'));
        $trip_new->save();

        if ($pretty_url = LibHelp::parseDataPost('pretty_url')) {
            $trip_new->pretty_url = str_replace(" ", "_", trim($pretty_url)) . $trip_new->getPrimaryKey();
        } else {
            $trip_new->pretty_url = "na/na-" . $trip_new->getPrimaryKey();
        }
        $trip_new->save();
        if ($trip_new->errors) {
            return Result::Error('db error');
        }
        TripAccess::grantAccess($user->id, $trip_new->getPrimaryKey(), TripAccess::ROLE['CREATOR']);

        TripDestination::setDestinations($trip_new->id, $destinations);
        TripHash::generateHash($trip_new->id);
        TripTag::setTags($trip_new->id, LibHelp::parseDataPost('tags'));

        return Result::Success(['data' => ['id' => $trip_new->id]]);

    }

    public function actionUpdate()
    {
        $user = User::loginToken();
        if ($user == false) {
            return Result::Error('forbidden');
        }

        $trip_id = intval(LibHelp::parseDataPost('trip_id'));

        $trip = Trip::findOne($trip_id);

        if (!$trip || ($user->id != $trip->user_id)) {
            return Result::Error('forbidden');
        }

        $trip->name = LibHelp::parseDataPost('name') ? urldecode(LibHelp::parseDataPost('name')) : $trip->name;

        $yr = LibHelp::parseDataPost('yr');
        $trip->yr = LibHelp::validateDate($yr, 'Y') ? $yr : $trip->yr;

        $mth = LibHelp::parseDataPost('mth');
        $trip->mth = LibHelp::validateDate($mth, 'm') ? $mth : $trip->mth;

        $day = LibHelp::parseDataPost('day');
        $trip->day = LibHelp::validateDate($day, 'd') ? $day : $trip->day;

        $trip->description = LibHelp::parseDataPost('description') ? LibHelp::parseDataPost('description') : $trip->description;

        $trip->url = LibHelp::parseDataPost('url');

        $trip->is_private = TripHelp::validateIsPrivate(LibHelp::parseDataPost('is_private'));

        $destinations = array();

        for ($i = 1; $i <= 5; $i++) {
            $desc = $i == 1 ? '' : $i;
            if (LibHelp::parseDataPost('destination' . $desc)) {
                $destinations[] = urldecode(LibHelp::parseDataPost('destination' . $desc));
            }
        }


        $img_url = LibHelp::parseDataPost('img_url');
        if (strpos($img_url, "flickr") !== false) {
            $img = \Yii::$app->flickr->download($img_url, basename($img_url));
            $trip->img_url = $img['url'];
            $trip->original_img_url = $img['original_url'];
        } else {
            $trip->img_url = $img_url;
            $trip->original_img_url = null;
        }

        $trip->pic_id = intval(LibHelp::parseDataPost('pic_id'));
        $trip->state = LibHelp::parseDataPost('state') ? LibHelp::parseDataPost('state') : $trip->state;

        $trip->save();

        if ($trip->errors) {
            return Result::Error('db error');
        }

        if (isset($destinations[0])) {
            TripDestination::setDestinations($trip->id, $destinations);
        }
        TripTag::updateTags($trip->id, LibHelp::parseDataPost('tags'));

        return Result::Success(['data' => ['id' => $trip->id]]);
    }

    public function actionClone()
    {
        $user = User::loginToken();

        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip || $trip->is_private != 0) {
            return Result::Error('forbidden');
        }

        $clone = new Trip();
        $clone->attributes = $trip->attributes;
        $clone->cloned = $trip->id;
        $clone->user_id = $user->id;
        $clone->created = time();
        $clone->updated = time();
        $clone->save();

        TripTag::cloneByTrip($trip->id, $clone->getPrimaryKey());

        foreach ($trip->spots as $spot) {
            $spot_new = new Spot();
            $spot_new->attributes = $spot->attributes;
            $spot_new->trip_id = $clone->getPrimaryKey();
            $spot_new->created = time();
            $spot_new->updated = time();
            $spot_new->save();
        }
        return Result::Success(['data' => ['id' => $clone->getPrimaryKey()]]);


    }

    public function actionGet()
    {
        $user = User::loginToken();

        if (!$user) {
            return Result::Error('forbidden');
        }

        $code = LibHelp::parseDataPost('code');
        $hash = LibHelp::parseDataPost('hash');

        if ($code) {
            $trip = TripHash::findOne(['code' => $code])->trip;
        } elseif ($hash) {
            $trip = TripHash::findOne(['hash' => $hash])->trip;
        } else {
            $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));
        }

        if ($code && $trip) {
            TripUserInvited::processCode($code, $user->id);
        }
        if (!TripAccess::findOne(['trip_id' => $trip->id, 'user_id' => $user->id])) {
            if (!$trip || ($trip->is_private == 2 && $user->id != $trip->user_id)
                || ($trip->is_private == 1 && !$hash && !$code && $user->id != $trip->user_id)
            ) {
                return Result::Error('forbidden');
            }
            if ($trip->is_private == 3) {
                $token = $user->sns->sns_token;
                if (!in_array($trip->id, UserHelp::getFbFriends($token))) {
                    return Result::Error('forbidden');
                }
            }
        }

        $attributes = $trip->attributes;

        $attributes['pic_url'] = Pic::getUrlById($trip->pic_id);

        $tags = TripTag::find()->select('tag_id,t.name')
            ->joinWith('tag t')
            ->andWhere(['trip_id' => $trip->id])
            ->asArray()->all();
        foreach ($tags as $tag => $foo) {
            unset($tags[$tag]['tag']);
        }

        $attributes['tags'] = $tags;

        if ($user->id == $trip->user_id) {
            $data = TripHash::generateHash($trip->id);
            $attributes['hash'] = $data['hash'];
            $attributes['code'] = $data['code'];
        }

        if (LibHelp::parseDataPost('get_fav')) {
            $attributes['fav_count'] = TripFav::getCount($trip->id) + Spot::getCount($trip->id);
            $attributes['faved'] = !empty(TripFav::findOne(['trip_id' => $trip->id, 'user_id' => $user->id]));
        }

        if (LibHelp::parseDataPost('get_user')) {
            $attributes['user'] = $user->getProfile($trip->user_id);
        }
        $destinations_data = TripDestination::getDestinations($trip->id);
        $attributes['destinations'] = $destinations_data['destinations'];
        $attributes['latlngs'] = $destinations_data['latlngs'];
        $attributes['destination'] = sizeof($attributes['destinations']) ? $attributes['destinations'][0] : '';

        return Result::Success(['data' => $attributes]);
    }

    public function actionRemoveSharedMe()
    {
        $user = User::loginToken();
        if ($user == false) {
            return Result::Error('forbidden');
        }
        $trip_id = intval(LibHelp::parseDataPost('trip_id'));

        TripAccess::deleteAll(['trip_id' => $trip_id, 'user_id' => $user->id]);

        return Result::Success();

    }

    public function actionListMine()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $trips = Trip::find()->where(['user_id' => $user->id])->orderBy(['updated' => SORT_DESC])->asArray()->all();

        $trips = TripHelp::prepare($trips);

        return Result::Success(['cnt' => count($trips), 'data' => $trips]);

    }

    public function actionListFriends()
    {
        $page = intval(LibHelp::parseDataPost('page'));
        $count = LibHelp::parseDataPost('count') ? intval(LibHelp::parseDataPost('count')) : 10;
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }

        $fb_friends = UserHelp::getFbFriends(LibHelp::parseDataPost('sns_token'));

        if ($fb_friends[0]) {
            $trips = Trip::find()
                ->andWhere(['or', ['is_private' => 0], ['is_private' => 3]])
                ->andWhere(['user_id' => $fb_friends])
                ->andWhere(['<>', 'state', 'disabled'])
                ->orderBy(['updated' => SORT_DESC])
                ->offset($page * $count)
                ->limit($count)
                ->asArray()->all();

            $trips = TripHelp::listFeatured($user->id, $trips, !empty(LibHelp::parseDataPost('get_user')));
            return Result::Success(['cnt' => count($trips), 'data' => $trips]);

        } else {
            return Result::Success(['cnt' => 0, 'data' => []]);
        }
    }

    public function actionListFeatured()
    {
        $page = intval(LibHelp::parseDataPost('page'));
        $count = LibHelp::parseDataPost('count') ? intval(LibHelp::parseDataPost('count')) : 10;
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $data = Trip::find()
            ->andWhere(['is_private' => 0, 'is_featured' => 1])
            ->andWhere(['<>', 'state', 'disabled'])
            ->orderBy(['updated' => SORT_DESC])
            ->offset($page * $count)
            ->limit($count)
            ->asArray()->all();
        $trips = TripHelp::listFeatured($user->id, $data, !empty(LibHelp::parseDataPost('get_user')));
        return Result::Success(['cnt' => count($trips), 'data' => $trips]);

    }

    public function actionListLatest()
    {
        $page = intval(LibHelp::parseDataPost('page'));
        $count = LibHelp::parseDataPost('count') ? intval(LibHelp::parseDataPost('count')) : 10;
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $data = Trip::find()->alias('t')
            ->select([
                't.*',
                'count(s.id) as activities'
            ])
            ->joinWith('spots s')
            ->andWhere(['t.is_private' => 0])
            ->andWhere(['<>', 't.state', 'disabled'])
            ->orderBy(['updated' => SORT_DESC])
            ->groupBy('t.id')
            ->having(['>', 'activities', 2])
            ->offset($page * $count)
            ->limit($count)
            ->asArray()->all();
        foreach ($data as $key => $foo) {
            unset($data[$key]['spots']);
        }
        $trips = TripHelp::listFeatured($user->id, $data, !empty(LibHelp::parseDataPost('get_user')));
        return Result::Success(['cnt' => count($trips), 'data' => $trips]);
    }

    public function actionUserTrips()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $user_code = LibHelp::parseDataPost('user');
        if (!$user_code) {
            return Result::Error('please provide user parameter');
        }
        $user_info = User::findOne(['or', ['id' => $user_code], ['nickname' => $user_code]]);
        if (!$user_info) {
            return Result::Error('user not found ');
        }

        $data = Trip::find()
            ->select(['id', 'pic_id'])
            ->andWhere(['is_private' => 0, 'user_id' => $user_info->id])
            ->andWhere(['<>', 'state', 'disabled'])
            ->limit(50)
            ->asArray()->all();
        $trips = TripHelp::listFeatured($user_info->id, $data);
        return Result::Success(['cnt' => count($trips), 'data' => $trips]);
    }

    public function actionSearch()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $fb_friends = UserHelp::getFbFriends(LibHelp::parseDataPost('sns_token'));
        $get_user = !empty(LibHelp::parseDataPost('get_user'));
        $duration = intval(LibHelp::parseDataPost('duration'));
        $duration = $duration < 0 || $duration > 3 ? 0 : $duration;
        $q = LibHelp::parseDataPost('q');
        if (!$q) {
            return Result::Error('forbidden');
        }
        $ids = Trip::find()->select('id')
            ->andWhere(['<>', 'state', 'disabled'])
            ->andWhere(['like', 'name', $q])
            ->limit(50)
            ->asArray()->all();
        $trip_ids = array_column($ids, 'id');

        $ids = TripDestination::find()->select('trip_id')
            ->andWhere(['like', 'destination', $q])
            ->limit(100)
            ->asArray()->all();
        $trip_ids = array_merge($trip_ids, array_column($ids, 'trip_id'));

        $ids = TripTag::find()->select('trip_id')
            ->joinWith('tag t')
            ->andWhere(['like', 't.name', $q])
            ->limit(50)
            ->asArray()->all();
        $trip_ids = array_merge($trip_ids, array_column($ids, 'trip_id'));

        $ids = Spot::find()->select('trip_id')
            ->andWhere(['or', ['like', 'name', $q], ['like', 'address', $q]])
            ->limit(50)
            ->asArray()->all();
        $trip_ids = array_merge($trip_ids, array_column($ids, 'trip_id'));

        $data = Trip::find()
            ->andWhere(['<>', 'state', 'disabled'])
            ->andWhere(['id' => $trip_ids])
            ->andWhere(['or',
                ['is_private' => 0],
                ['is_private' => 3, 'user_id' => $fb_friends]])
            ->orderBy(['updated' => SORT_DESC])
            ->limit(50)
            ->asArray()->all();
        $trips = TripHelp::listFeatured($user->id, $data, $get_user);

        $result = [];
        if ($duration) {
            foreach ($trips as $trip) {
                switch ($duration) {
                    case 1:
                        //1 day trip
                        if ($trip['days'] == 1) {
                            $result[] = $trip;
                        }
                        break;
                    case 2:
                        //less than week
                        if ($trip['days'] <= 7) {
                            $result[] = $trip;
                        }
                        break;
                    case 3:
                        //more than week
                        if ($trip['days'] > 7) {
                            $result[] = $trip;
                        }
                        break;
                }
            }
        } else {
            $result = $trips;
        }
        return Result::Success(['cnt' => count($result), 'data' => $result]);
    }

    public function actionListShared()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $trips = Trip::find()->alias('t')->select('t.*')->joinWith(['access a'])->andWhere(['<>', 't.user_id', $user->id])
            ->andWhere(['a.user_id' => $user->id])->orderBy(['updated' => SORT_DESC])
            ->asArray()->all();

        $trips = TripHelp::prepare($trips);
        return $trips;
    }

    public function actionListAccess()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));
        if ($trip->user->id != $user->id) {
            return Result::Error('forbidden');
        }

        $result = TripAccess::getTripUsers($trip->id);

        return Result::Success(['data' => $result]);


    }

    public function actionGrantAccess()
    {
        $user = User::loginToken();
        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip) {
            return Result::Error('forbidden');
        }


        if (empty(LibHelp::parseDataPost('sns_no'))) {
            $email = '';

            if (!LibHelp::parseDataPost('user_id')) {
                $email = filter_var(LibHelp::parseDataPost('user_email'), FILTER_VALIDATE_EMAIL);
                $email = !$email ? filter_var(LibHelp::parseDataPost('email'), FILTER_VALIDATE_EMAIL) : $email;

                if (!$email) {
                    return Result::Error('forbidden');
                }

                $user_id = User::findOne(['email' => $email])->id;
            } else {
                $user_id = intval(LibHelp::parseDataPost('user_id'));
            }

            if (empty($user_id)) {
                $user_invite = TripUserInvited::findOne(['user_email' => $email, 'trip_id' => $trip->id]);

                if ($user_invite) {
                    return Result::Success([
                        'data' => TripAccess::getTripUsers($trip->id),
                        'alreadyInvited' => true
                    ]);
                } else {
                    $invite_code = TripHelp::shareWithEmailAddress($trip, $user, $email);
                }

                if ($invite_code) {
                    $invite = new TripUserInvited();
                    $invite->invite_code = $invite_code;
                    $invite->user_email = $email;
                    $invite->trip_id = $trip->id;
                    $invite->save();

                    return Result::Success([
                        'data' => TripAccess::getTripUsers($trip->id),
                        'userInvited' => true,
                    ]);
                } else {
                    return Result::Error('error sending e-mail');
                }
            }
        } elseif (LibHelp::parseDataPost('sns_no') && LibHelp::parseDataPost('sns_uid')) {
            $sns_no = LibHelp::parseDataPost('sns_no');
            $sns_uid = LibHelp::parseDataPost('sns_uid');
            $user_id = UserSns::findOne(['sns_no' => $sns_no, 'sns_uid' => $sns_uid])->user_id;
            if (!$user_id) {
                if ($trip->name && $sns_no) {
                    $invite_code = TripUserInvited::generateInviteCode($trip->id, false, $sns_no, $sns_uid);

                } else {
                    $invite_code = false;
                }
                if ($invite_code) {
                    $invite = new TripUserInvited();
                    $invite->invite_code = $invite_code;
                    $invite->sns_no = $sns_no;
                    $invite->sns_uid = $sns_uid;
                    $invite->trip_id = $trip->id;
                    $invite->save();
                    if ($invite->errors) {
                        return Result::Error('db error');
                    }
                    return Result::Success([
                        'data' => TripAccess::getTripUsers($trip->id),
                        'userInvited' => true,
                        'invite_code' => $invite_code
                    ]);
                }
            }
        }

        if (!empty($user_id)) {
            TripAccess::grantAccess($user_id, $trip->id);
        }
        return Result::Success([
            'data' => TripAccess::getTripUsers($trip->id)
        ]);
    }

    public function actionRevokeAccess()
    {
        $user = User::loginToken();
        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip) {
            return Result::Error('forbidden');
        }
        $user_id = intval(LibHelp::parseDataPost('user_id'));

        $user_invite = TripAccess::find()->
        andWhere(['trip_id' => $trip->id, 'user_id' => $user_id])->
        andWhere(['<>', 'user_id', $user->id])->one();
        if($user_invite) {
            $user_invite->delete();
        }
        return Result::Success([
            'data' => TripAccess::getTripUsers($trip->id)
        ]);
    }

    public function actionAcceptInvite()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $invite_code = LibHelp::parseDataPost('invite_code');

        if (!$invite_code) {
            return Result::Error('invite_code required');
        }

        $result = TripUserInvited::processCode($invite_code, $user->id);

        if (!empty($result)) {
            return Result::Success(['data' => []]);
        } else {
            return Result::Success(['data' => [], 'invalidCode' => true]);
        }
    }

    public function actionInviteEmail()
    {
        $user = User::loginToken();
        $email = filter_var(LibHelp::parseDataPost('user_email'), FILTER_VALIDATE_EMAIL);

        if (!$user || !$email) {
            return Result::Error('forbidden');
        }
        $invite_url = \Yii::getAlias('@webapp') . '/#!/';

        $mail = \Yii::$app->mailer->compose();

        $mail->setFrom(array('no-reply@tripverse.co' => 'Tripverse Accounts'));
        $mail->setTo($email);

        $mail->setSubject($user->name . ' invites you to join TripVerse');

        $mail->setHtmlBody('
      <html>
      <body>
        <p>' . $user->name . ' has invited you to TripVerse.</p>
        <br/>
        <p>TripVerse is a travel plan sharing platform with an offline-readable itinerary viewer. <a href="' . $invite_url . '">Signup</a> to discover authentic travel plans created by other travel enthusiasts.</p>
        <br/>
        <p>The TripVerse team</p>
      </body>
    </html>
    ');


        if ($mail->send()) {
            return Result::Success(['userInvited' => true]);
        }else{
            return Result::Error('error sending e-mail');
        }

    }

    public function actionGetUrl()
    {
        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if ($trip && $trip->pretty_url) {
            return Result::Success(['data' => $trip->pretty_url]);
        } else {
            return Result::Error('not found');
        }

    }

    public function actionListFav()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }

        $trips = Trip::find()->alias('t')->select('t.* , f.updated as faved')->joinWith(['fav f'])
            ->andWhere(['f.user_id' => $user->id, 'f.state_no' => 1])
            ->andWhere(['<>', 't.state', 'disabled'])
            ->andWhere(['t.is_private' => 1])
            ->orderBy(['faved' => SORT_DESC])
            ->asArray()->all();
        $listFav = [];
        foreach ($trips as $trip) {
            $trip['pic_url'] = Pic::getUrlById($trip['pic_id']);
            $destinations_data = TripDestination::getDestinations($trip['id']);
            $trip['destinations'] = $destinations_data['destinations'];
            $trip['latlngs'] = $destinations_data['latlngs'];
            $trip['destination'] = sizeof($trip['destinations']) ? $trip['destinations'][0] : '';
            unset($trip['fav']);
            $clone = Trip::find()
                ->andWhere(['user_id' => $user->id])
                ->andWhere(['cloned' => $trip['id']])
                ->andWhere(['<>', 'state', 'disabled'])
                ->all();
            if (!$clone) {
                $listFav[] = $trip;
            }
        }
        return Result::Success(['cnt' => count($listFav), 'data' => $listFav]);

    }

    public function actionFav()
    {
        $user = User::loginToken();

        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip || $trip->is_private == 1) {
            return Result::Error('forbidden');
        }
        if ($trip->user_id == $user->id) {
            return Result::Error('Can\'t heart your own trip');
        }

        return TripFav::change($user->id, $trip->id, 1);
    }

    public function actionUnfav()
    {
        $user = User::loginToken();

        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip || $trip->is_private == 1) {
            return Result::Error('forbidden');
        }
        if ($trip->user_id == $user->id) {
            return Result::Error('Can\'t heart your own trip');
        }

        return TripFav::change($user->id, $trip->id, 0);
    }

    public function actionDelete()
    {
        $user = User::loginToken();
        if ($user == false) {
            return Result::Error('forbidden');
        }
        $trip_id = intval(LibHelp::parseDataPost('trip_id'));
        $trip = Trip::findOne($trip_id);

        if (!$trip || $user->id != $trip->user_id) {
            return Result::Error('forbidden');
        }

        $trip->state = 'disabled';
        $trip->save();
        if ($trip->errors) {
            return Result::Error('db error');
        }

        return Result::Success();
    }

    public function actionFlickrPhotos()
    {
        $user = User::loginToken();
        if (!$user) {
            return Result::Error('forbidden');
        }
        $q = LibHelp::parseDataPost('q');

        return \Yii::$app->flickr->getPhotos($q, 20);
    }

    public function actionDestinationAutocomplete()
    {
        $opts = array('types' => '(regions)');
        if (empty(LibHelp::parseDataPost('q'))) {
            return Result::Success(['data' => []]);
        }
        return \Yii::$app->googleApi->PlaceAutocomplete(LibHelp::parseDataPost('q'), $opts);
    }

    public function actionFeature()
    {
        $user = User::loginToken();

        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip || $trip->is_private != 0) {
            return Result::Error('forbidden');
        }
        $trip->is_featured = 1;
        $trip->save();

        if ($trip->errors) {
            return Result::Error('db error');
        }

        return Result::Success();

    }

    public function actionUnfeature()
    {
        $user = User::loginToken();

        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));

        if (!$user || !$trip || $trip->is_private != 0) {
            return Result::Error('forbidden');
        }
        $trip->is_featured = 0;
        $trip->save();

        if ($trip->errors) {
            return Result::Error('db error');
        }

        return Result::Success();

    }

    public function actionGetFareInfo(){
        $start_airports = array();
        foreach (\Yii::$app->request->post('airport') as $airport) {
            $start_airports[] = $airport;
        }
        if(!$start_airports){
            return Result::Error('no airport information');
        }
        $trip = Trip::findOne(intval(LibHelp::parseDataPost('trip_id')));
        $trip_destination= LibHelp::parseDataPost('destination');

        if(!$trip && !$trip_destination){
            return Result::Error('trip not set');
        }

        $lat = $lng = 0;
        if(!$trip){
            foreach ($trip->destinations as $destination){
                if($destination->lat != 0 && $destination->lng != 0){
                    $lat = $destination->lat;
                    $lng = $destination->lng;
                    break;
                }
            }
        }elseif($trip_destination){
            $location = \Yii::$app->googleApi->getGeoCodeObject($trip_destination, null, '');
            if (isset($location->geometry->location)) {
                $lat = $location->geometry->location->lat;
                $lng = $location->geometry->location->lng;
            }
        }
        
        if(!$lat || !$lng){
            return Result::Error('no geolocation information for trip');
        }
        $airports = Airport::getNear($lat, $lng,'City');
        $start_date = LibHelp::parseDataPost('start_date')?LibHelp::parseDataPost('start_date'):'anytime';

        $market = LibHelp::parseDataPost('market');
        $currency = LibHelp::parseDataPost('currency');
        $locale = LibHelp::parseDataPost('locale');

        $data = \Yii::$app->skyscanner->tryFareFetch($start_airports, $airports, $start_date, $market, $currency, $locale);

        if($data){
            return Result::Success($data);
        }else{
            return Result::Error('fetching-faires-failed');
        }
    }


}