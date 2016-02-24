<?php
namespace smpp\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

use smpp\Module;

class BizBasic extends ActiveRecord
{
  public static function tableName(){
    return 'BizBasic';
  }

  public static function getDb(){
    return Module::getInstance()->db;
  }

  public function behaviors(){
    return [
      [ 'class'=>TimestampBehavior::className(),
        'attributes'=>[
          ActiveRecord::EVENT_BEFORE_INSERT=>['create_time','update_time'],
          ActiveRecord::EVENT_BEFORE_UPDATE=>['update_time'],
        ],
      ],
    ];
  }

  public function beforeSave($insert){
    if(parent::beforeSave($insert)){
      $this->office_crc=crc32($this->officename.$this->pre_name);
      return true;
    }else{
      return false;
    }
  }
}

