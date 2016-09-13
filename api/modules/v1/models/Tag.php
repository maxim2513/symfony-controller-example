<?php

namespace api\modules\v1\models;

use api\helpers\UserHelp;
use \yii\db\ActiveRecord;

/**
 * Country Model
 *
 * @author Budi Irawan <deerawan@gmail.com>
 */
class Tag extends ActiveRecord
{
    const TAG_MIN_LENGTH = 2;
    const TAG_MAX_LENGTH = 20;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ts_tag';
    }

    public static function primaryKey()
    {
        return ['id'];
    }

    /**
     * Define rules for validation
     */
    public function rules()
    {
        return [
            ['name', 'string', 'max' => self::TAG_MAX_LENGTH, 'min' => self::TAG_MIN_LENGTH],
            [['created', 'cnt'], 'integer'],
        ];
    }

    
    
    public static function setTags($tags)
    {
        $words = preg_split('![\s,]+!', $tags);
        $tags = [];
        foreach ($words as $word) {
            $strlen = mb_strlen($word);
            if ($strlen >= self::TAG_MIN_LENGTH && $strlen <= self::TAG_MAX_LENGTH) {
                $tags[] = $word;
            }
        }
        $tags = array_unique($tags);

        $ids = [];
        foreach ($tags as $tag) {
            if (!$tag = self::findOne(['name' => $tag])) {
                $tag_new = new self();
                $tag_new->name = $tag;
                $tag_new->created = time();
                $tag_new->save();
                $ids[] = $tag_new->getPrimaryKey();
            } else {
                $ids[] = $tag->id;
            }
        }
        return $ids;
    }


}
