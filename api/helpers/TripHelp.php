<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/13/16
 * Time: 2:18 PM
 */

namespace api\helpers;


use api\modules\v1\models\Pic;
use api\modules\v1\models\Spot;
use api\modules\v1\models\Trip;
use api\modules\v1\models\TripDestination;
use api\modules\v1\models\TripFav;
use api\modules\v1\models\TripHash;
use api\modules\v1\models\TripUserInvited;
use api\modules\v1\models\User;
use yii\helpers\Url;

class TripHelp
{
    public static function validateIsPrivate($is_private)
    {
        //is_private = 0 -> public
        //is_private = 1 -> anyone with hash url
        //is_private = 2 -> private
        //is_private = 3 -> visible to facebook friends
        $is_private = isset($is_private) ? intval($is_private) : 0;
        $is_private = $is_private < 0 || $is_private > 3 ? 0 : $is_private;
        return $is_private;
    }


    public static function prepare(array $trips)
    {
        $results = [];
        foreach ($trips as $trip) {

            $trip['pic_url'] = Pic::getUrlById($trip['pic_id']);
            $hash_data = TripHash::generateHash($trip['id']);
            $trip['hash'] = $hash_data['hash'];
            $trip['code'] = $hash_data['code'];
            $destinations_data = TripDestination::getDestinations($trip['id']);
            $trip['destinations'] = $destinations_data['destinations'];
            $trip['latlngs'] = $destinations_data['latlngs'];
            $trip['destination'] = sizeof($trip['destinations']) ? $trip['destinations'][0] : '';
            if (isset($trip['access'])) {
                unset($trip['access']);
            }
            $results[] = $trip;

        }
        return $results;
    }

    public static function listFeatured($user_id, $trips, $get_user = false)
    {

        $result = [];
        foreach ($trips as $key => $trip) {
            if ($trip['pic_id'] == 0) {
                $trip['pic_id'] = '-1';
            }
            $trip['pic_url'] = Pic::getUrlById($trip['pic_id']);
            $trip['fav_count'] = TripFav::getCount($trip['id']) + Spot::getCount($trip['id']);
            $trip['faved'] = !empty($user_id) && !empty(TripFav::findOne(['trip_id' => $trip['id'], 'user_id' => $user_id]));
            $trip['activity_count'] = count(Trip::findOne($trip['id'])->spots);
            $trip['days'] = Spot::find()->select('day_no')
                ->andWhere(['trip_id' => $trip['id']])
                ->orderBy(['day_no' => SORT_DESC])->one()['day_no'];
            $destinations_data = TripDestination::getDestinations($trip['id']);
            $trip['destinations'] = $destinations_data['destinations'];
            $trip['latlngs'] = $destinations_data['latlngs'];
            $trip['destination'] = sizeof($trip['destinations']) ? $trip['destinations'][0] : '';
            $result[] = $trip;
            if ($get_user) {
                $trip['user'] = User::findOne($user_id)->getProfile($trip['user_id']);
            }
        }
        return $result;
    }

    public static function shareWithEmailAddress($trip, $user, $email)
    {
        $invite_code = TripUserInvited::generateInviteCode($trip->id, $email);

        $invite_url = Url::to('@webapp' . '#!/join/' . $invite_code, true);

        $mail = \Yii::$app->mailer->compose();

        $mail->setFrom(array('no-reply@tripverse.co' => 'Tripverse Accounts'));
        $mail->setTo($email);

        $mail->setSubject('Invitation: ' . $trip->id);

        $mail->setHtmlBody('
     <html>
      <body>
        <p>' . $user->name . ' has invited you on a trip - <a href="' . $trip->publicUrl . '">' . $trip->name . '</a>. 
        Save it in your trip collection by <a href="' . $invite_url . '">signing up to TripVerse</a>.</p>
        <p>TripVerse is a travel itinerary sharing platform</p>
      </body>
    </html>
    ');


        if ($mail->send()) {
            return $invite_code;
        } else {
            return false;
        }
    }


}