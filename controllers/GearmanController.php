<?php
namespace smpp\controllers;

use GearmanWorker;

use Yii;
use yii\helpers\Json;
use yii\helpers\Console;

use smpp\models\BizBasic;
use smpp\models\BizSmppProd;
use smpp\models\BizSmppFeature;
use smpp\models\BizSmppTech;
use smpp\Location;

/**
 * Runs gearman worker for smpp
 */
class GearmanController extends \yii\console\Controller
{
  /**
  * Runs gearman worker for smpp
  */
  public function actionIndex(){
    ini_set('memory_limit','256M');
    echo 'use database '.$this->module->db->dsn,PHP_EOL;
    echo 'gearman worker initialize...',PHP_EOL;
    echo '  server   : '.$this->module->gman_server,PHP_EOL;
    echo '  function : "smpp_corp_get"',PHP_EOL;
    echo 'start gearman worker...',PHP_EOL;

    $worker=new GearmanWorker;
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('smpp_corp_get',[$this,'smpp_corp_get']);
    while($worker->work());
  }

  public function smpp_corp_get($job){
    $workload=$job->workload();
    echo $workload,PHP_EOL;
    $workload=Json::decode($workload);

    $this->module->db->close();

    $httpClient=new \GuzzleHttp\Client([
      'base_uri'=>'http://www.smpp.go.kr',
      'cookies'=>true,
    ]);

    $res=$httpClient->request('POST','https://www.smpp.go.kr/uat/uia/actionLogin.do',[
      'verify'=>false,
      'form_params'=>[
        'userSe'=>'USR',
        'id'=>$this->module->smpp_username,
        'password'=>$this->module->smpp_password,
      ],
    ]);

    $bizBasic=BizBasic::findOne(['officeno'=>$workload['bizno']]);
    if($bizBasic===null){
      $bizBasic=new BizBasic([
        'officeno'=>$workload['bizno'],
        'is_member'=>'E',
        'charge_info'=>'',
        'charge_bidq'=>'',
      ]);
    }

    //sleep(1);
    //일반정보
    $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpGnrlInfoVw.do',[
      'form_params'=>[
        'bsnmNo'=>$workload['bizno'],
        'menuId'=>'1013',
        'codeId'=>'SCOPE',
      ],
    ]);
    $body=$res->getBody();
    $html=(string)$body;
    $html=$this->strip_tags($html);

    echo ' > ';
    $p='#<tr> <th>업체명</th> <td>(?<biznm>[^<]*)</td> <th>대표자명</th> <td>(?<prenm>[^<]*)</td> </tr>'.
       ' <tr> <th>사업자등록번호</th> <td>[^<]*</td> <th>전화번호</th> <td>(?<tel>[^<]*)</td> </tr>'.
       ' <tr> <th>주소\(본사\)</th> <td>\(\d+\) (?<addr>[^<]*)</td> </tr>#';
    if(preg_match(str_replace(' ','\s*',$p),$html,$m)){
      $biznm=trim($m['biznm']);
      $prenm=trim($m['prenm']);
      $tel=trim($m['tel']);
      $addr=trim($m['addr']);
      echo "$biznm $prenm $tel $addr";
      $bizBasic->officename=$biznm;
      $bizBasic->location=Location::findFromAddr($addr);
      $bizBasic->pre_name=$prenm;
      $bizBasic->tel=$tel;
    }
    $p='#<tr> <th>사업자등록일</th> <td>(?<regdate>[^<]*)</td> </tr>#';
    if(preg_match(str_replace(' ','\s*',$p),$html,$m)){
      $regdate=trim($m['regdate']);
      echo " $regdate";
      $bizBasic->biz_startdate=$regdate;
    }
    $bizBasic->save();
    echo PHP_EOL;

    //sleep(1);
    //제품정보
    $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpPrductInfoListVw.do',[
      'form_params'=>[
        'menuId'=>'1013',
        'bsnmNo'=>$workload['bizno'],
        'codeId'=>'SCOPE',
      ],
    ]);
    $body=$res->getBody();
    $html=(string)$body;
    $html=$this->strip_tags($html);
    $this->parseProdList($html,function($data) use ($httpClient,$workload){
      echo ' > '.join(',',$data);

      $bizSmppProd=BizSmppProd::findOne(['officeno'=>$workload['bizno'],'gcode'=>$data['gcode']]);
      if($bizSmppProd===null){
        $bizSmppProd=new BizSmppProd([
          'officeno'=>$workload['bizno'],
          'gcode'=>$data['gcode'],
        ]);
      }
      $bizSmppProd->gname=$data['gname'];

      //usleep(500);
      $res=$httpClient->request('GET','/cop/registcorp/selectRegistCorpPrductInfoDetailVw.do?callback=&bsnmNo='.$workload['bizno'].'&prductNo='.$data['artno']);
      $body=$res->getBody();
      $html=(string)$body;
      $html=$this->strip_tags($html);
      $p='/결과:적격/';
      if(preg_match($p,$html)){
        echo Console::ansiFormat(' - 적격',[Console::FG_GREEN]);
        $bizSmppProd->is_self='y';
      }else{
        if($bizSmppProd->is_self!='y'){
          $bizSmppProd->is_self='n';
        }
      }
      
      $bizSmppProd->save();
      echo PHP_EOL;
    });
    BizSmppProd::deleteAll('officeno=:officeno and update_at<:update_at',[
      ':officeno'=>$workload['bizno'],
      ':update_at'=>time()-(60*60*24*7),
    ]);

    //sleep(1);
    //기술력
    $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpTssListVw.do',[
      'form_params'=>[
        'menuId'=>'1013',
        'bsnmNo'=>$workload['bizno'],
        'codeId'=>'SCOPE',
      ],
    ]);
    $body=$res->getBody();
    $html=(string)$body;
    $html=$this->strip_tags($html);
    $p='#<tr> <td>(?<div>[^<]+)</td> <td>[^<]+</td> <td>[^<]+</td> <td>[^<]+</td> <td> \d{4}-\d{2}-\d{2} </td> <td>[^<]*</td> <td>[^<]*</td> </tr>'.
           '( <tr> <td>[^<]+</td> <td>[^<]+</td> <td>[^<]+</td> <td> \d{4}-\d{2}-\d{2} </td> <td>[^<]*</td> <td>[^<]*</td> </tr>)*#';
    if(preg_match_all(str_replace(' ','\s*',$p),$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        $div=trim($m['div']); //인증구분
        echo ' > '.$div,PHP_EOL;
        $p_sub='#<tr>( <td>[^<]*</td>)? <td>(?<regno>[^<]+)</td> <td>(?<pname>[^<]+)</td> <td>(?<regorg>[^<]+)</td> <td>(?<regdate>[^<]+)</td> <td>(?<expire>[^<]*)</td> <td>(?<valid>[^<]*)</td> </tr>#';
        if(preg_match_all(str_replace(' ','\s*',$p_sub),$m[0],$sub_matches,PREG_SET_ORDER)){
          foreach($sub_matches as $sub_m){
            $regno=trim($sub_m['regno']);
            $pname=trim($sub_m['pname']);
            $regorg=trim($sub_m['regorg']);
            $regdate=trim($sub_m['regdate']);
            $expire=trim($sub_m['expire']);
            $valid=trim($sub_m['valid']);
            echo "  >> $regno,$pname,$regorg,$regdate,$expire,$valid",PHP_EOL;

            $bizSmppTech=BizSmppTech::findOne([
              'officeno'=>$workload['bizno'],
              'cert_type'=>$div,
              'cert_num'=>$regno,
            ]);
            if($bizSmppTech===null){
              $bizSmppTech=new BizSmppTech([
                'officeno'=>$workload['bizno'],
                'cert_type'=>$div,
                'cert_num'=>$regno,
              ]);
            }
            $bizSmppTech->techname=$pname;
            $bizSmppTech->cert_org=$regorg;
            $bizSmppTech->cert_date=$regdate;
            $bizSmppTech->cert_expire=$expire;
            $bizSmppTech->cert_validity=$valid;
            $bizSmppTech->save();
          }
        }
      }
    }
    BizSmppTech::deleteAll('officeno=:officeno and update_at<:update_at',[
      ':officeno'=>$workload['bizno'],
      ':update_at'=>time()-(60*60*24*7),
    ]);

    //sleep(1);
    //기업특징
    $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpSfeListVw.do',[
      'form_params'=>[
        'menuId'=>'1013',
        'bsnmNo'=>$workload['bizno'],
        'codeId'=>'SCOPE',
        'sfeCodeSe'=>'ENVI',
      ],
    ]);
    $body=$res->getBody();
    $html=(string)$body;
    $html=$this->strip_tags($html);
    $this->parseCharacterList($html,function($data) use ($workload) {
      echo ' > '.join(',',$data),PHP_EOL;

      $bizSmppFeature=BizSmppFeature::findOne([
        'officeno'=>$workload['bizno'],
        'cert_type'=>$data['div'],
      ]);
      if($bizSmppFeature===null){
        $bizSmppFeature=new BizSmppFeature([
          'officeno'=>$workload['bizno'],
          'cert_type'=>$data['div'],
        ]);
      }
      $bizSmppFeature->cert_org=$data['regorg'];
      $bizSmppFeature->cert_num=$data['regno'];
      $bizSmppFeature->cert_date=$data['regdate'];
      $bizSmppFeature->cert_expire=$data['expire'];
      $bizSmppFeature->cert_validity=$data['valid'];
      $bizSmppFeature->save();
    });
    BizSmppFeature::deleteAll('officeno=:officeno and update_at<:update_at',[
      ':officeno'=>$workload['bizno'],
      ':update_at'=>time()-(60*60*24*7),
    ]);

    $httpClient->get('https://www.smpp.go.kr/uat/uia/actionLogout.do',[
      'verify'=>false,
    ]);

    gc_collect_cycles();
    $this->stdout(sprintf("[%s] Peak memory usage: %s MB\n",date('Y-m-d H:i:s'),(memory_get_peak_usage(true)/1024/1024)),Console::FG_YELLOW);
  }

  protected function parseProdList($html,$callback){
    $p='#<tr> <td>(?<artno>\d+)</td> <td>(?<gcode>\d{10})</td> <td>(?<gname>[^<]*)</td> <td>(?<pname>[^<]*)</td> </tr>#';
    if(!preg_match_all(str_replace(' ','\s*',$p),$html,$matches,PREG_SET_ORDER)){
      return;
    }
    foreach($matches as $m){
      list($pname)=explode('(',trim($m['pname']));
      $callback([
        'artno'=>trim($m['artno']),
        'gcode'=>trim($m['gcode']),
        'gname'=>trim($m['gname']),
        'pname'=>trim($pname),
      ]);
    }
  }

  protected function parseCharacterList($html,$callback){
    $p='#<tr> <td>(?<div>[^<]+)</td> <td>(?<regorg>[^<]+)</td> <td>(?<regno>[^<]+)</td> <td> (?<regdate>\d{4}-\d{2}-\d{2}) </td> <td>(?<expire>[^<]*)</td> <td>(?<valid>[^<]*)</td> </tr>#';
    $p=str_replace(' ','\s*',$p);
    if(!preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
      return;
    }
    foreach($matches as $m){
      $callback([
        'div'=>trim($m['div']),
        'regorg'=>trim($m['regorg']),
        'regno'=>trim($m['regno']),
        'regdate'=>trim($m['regdate']),
        'expire'=>trim($m['expire']),
        'valid'=>trim($m['valid']),
      ]);
    }
  }

  protected function strip_tags($html){
    $html=strip_tags($html,'<tr><th><td>');
    $html=str_replace('&nbsp;',' ',$html);
    $html=preg_replace('/<tr[^>]*>/','<tr>',$html);
    $html=preg_replace('/<td[^>]*>/','<td>',$html);
    $html=preg_replace('/<th[^>]*>/','<th>',$html);
    return $html;
  }
}

