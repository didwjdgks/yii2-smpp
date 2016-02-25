<?php
namespace smpp;

use yii\db\Connection;
use yii\di\Instance;

class Module extends \yii\base\Module
{
  public $db='db';

  public $gman_server;

  public $smpp_username;
  public $smpp_password;

  public function init(){
    parent::init();

    $this->db=Instance::ensure($this->db,Connection::className());
  }
}

