<?php
namespace smpp;

class Location
{
  public static $codes=[
    ['code'=>1,'name'=>'서울','pattern'=>'/^서울/'],
    ['code'=>2,'name'=>'부산','pattern'=>'/^부산/'],
    ['code'=>3,'name'=>'광주','pattern'=>'/^광주/'],
    ['code'=>4,'name'=>'대전','pattern'=>'/^대전/'],
    ['code'=>5,'name'=>'인천','pattern'=>'/^인천/'],
    ['code'=>6,'name'=>'대구','pattern'=>'/^대구/'],
    ['code'=>7,'name'=>'울산','pattern'=>'/^울산/'],
    ['code'=>8,'name'=>'경기','pattern'=>'/^경기/'],
    ['code'=>9,'name'=>'강원','pattern'=>'/^강원/'],
    ['code'=>10,'name'=>'충북','pattern'=>'/^(충북|충청북도)/'],
    ['code'=>11,'name'=>'충남','pattern'=>'/^(충남|충청남도)/'],
    ['code'=>12,'name'=>'경북','pattern'=>'/^(경북|경상북도)/'],
    ['code'=>13,'name'=>'경남','pattern'=>'/^(경남|경상남도)/'],
    ['code'=>14,'name'=>'전북','pattern'=>'/^(전북|전라북도)/'],
    ['code'=>15,'name'=>'전남','pattern'=>'/^(전남|전라남도)/'],
    ['code'=>16,'name'=>'제주','pattern'=>'/^제주/'],
    ['code'=>17,'name'=>'세종','pattern'=>'/^세종/'],
  ];

  public static function findFromAddr($addr){
    foreach(static::$codes as $r){
      $p=$r['pattern'];
      if(preg_match($p,$addr)){
        return $r['code'];
      }
    }
    return 0;
  }
}

