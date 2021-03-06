<?php
namespace smpp\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

use smpp\Module;

class BizSmppFeature extends ActiveRecord
{
  public static function tableName(){
    return 'BizSmppFeature';
  }

  public static function getDb(){
    return Module::getInstance()->db;
  }

  public function behaviors(){
    return [
      [ 'class'=>TimestampBehavior::className(),
        'attributes'=>[
          ActiveRecord::EVENT_BEFORE_INSERT=>['update_at'],
          ActiveRecord::EVENT_BEFORE_UPDATE=>['update_at'],
        ],
      ],
    ];
  }
}

