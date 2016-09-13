<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class TripTag extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_trip_tag';
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            [['trip_id', 'tag_id'], 'integer'],
        ];
    }

    public static function setTags($trip_id, $tags)
    {
        $tag_ids = Tag::setTags($tags);

        foreach ($tag_ids as $tag_id) {
            $tag = new self();
            $tag->trip_id = $trip_id;
            $tag->tag_id = $tag_id;
            $tag->save();
        }
        return $tag_ids;
    }

    public static function updateTags($trip_id, $tags)
    {
        $ids = self::setTags($trip_id, $tags);

        self::deleteAll(['and', 'trip_id' => $trip_id, ['not in', 'tag_id', $ids]]);
    }

    public function getTag()
    {
        return $this->hasOne(Tag::className(), ['id' => 'tag_id']);
    }

    public static function cloneByTrip($trip_id, $clone_id)
    {
        $tag_ids = self::find()->select('tag_id')
            ->andWhere(['trip_id' => $trip_id])
            ->asArray()->all();
        $tag_ids = array_column($tag_ids, 'tag_id');
        foreach ($tag_ids as $tag_id) {
            $tag = new self();
            $tag->trip_id = $clone_id;
            $tag->tag_id = $tag_id;
            $tag->save();
        }
    }

}
