<?php
namespace smpp\controllers;

use Yii;
use yii\helpers\Json;

/**
 * Smpp search all
 */
class ListController extends \yii\console\Controller
{
  /**
  * Smpp search all
  * @param $startPage Start page number
  */
  public function actionStart($startPage=1){
    $gmanClient=new \GearmanClient();
    $gmanClient->addServer($this->module->gman_server);

    $httpClient=new \GuzzleHttp\Client([
      'base_uri'=>'http://www.smpp.go.kr',
    ]);
    $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpListVw.do',[
      'form_params'=>[
        'pageIndex'=>$startPage,
        'pageUnit'=>'100',
      ],
    ]);
    $body=$res->getBody();
    $html=(string)$body;
    
    $p='#<a.*btnMove last.*fn_getList\((?<lastpage>\d+)\);#';
    if(!preg_match($p,$html,$m)){
      return;
    }
    $lastPage=$m['lastpage'];
    echo "총 페이지수 : $lastPage",PHP_EOL;

    for($i=$startPage; $i<=$lastPage; $i++){
      if($i>$startPage){
        $res=$httpClient->request('POST','/cop/registcorp/selectRegistCorpListVw.do',[
          'form_params'=>[
            'pageIndex'=>$i,
            'pageUnit'=>'100',
          ],
        ]);
        $body=$res->getBody();
        $html=(string)$body;
      }
      $this->parseList($html,function($data) use ($gmanClient,$i){
        echo "page($i) >> ".join(',',$data),PHP_EOL;
        $gmanClient->doNormal('smpp_corp_get',Json::encode([
          'bizno'=>$data['bizno'],
        ]));
      });
      sleep(1);
    }
  }

  private function parseList($html,$callback){
    $html=strip_tags($html,'<tr><td><span>');
    $html=preg_replace('/<td[^>]*>/','<td>',$html);
    $p='#<tr>'.
        ' <td>\d+</td>'.
        ' <td> <span.+fn_moveDetail\(\'(?<bizno>\d+)\'\)">(?<biznm>[^<]*)</span> </td>'.
        ' <td>(?<prenm>[^<]*)</td>'.
        ' <td>(?<addr>[^<]*)</td>'.
       ' </tr>#';
    if(preg_match_all(str_replace(' ','\s*',$p),$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        $callback([
          'bizno'=>trim($m['bizno']),
          'biznm'=>trim($m['biznm']),
          'prenm'=>trim($m['prenm']),
          'addr'=>trim($m['addr']),
        ]);
      }
    }
  }
}

