<?php

use Slim\Http\Request;
use Slim\Http\Response;
// Routes
$app->get('/channels', function ($request, $response, $args) {
        $sth = $this->db->prepare("SELECT * FROM channels ORDER BY id");
        $sth->execute();
        $todos = $sth->fetchAll();
        return $this->response->withJson($todos);
});


$app->get('/links/{start}/{end}/{channelid}', function ($request, $response, $args) {
    $sth = $this->db->prepare("SELECT * FROM  links WHERE id BETWEEN :starttime AND :endtime AND channel_id=:channelid");
    $sth->bindParam('starttime', $args['start']);
    $sth->bindParam('endtime', $args['end']);
    $sth->bindParam('channelid', $args['channelid']);
	$sth->execute();
	$links = $sth->fetchAll();
	return $this->response->withJson($links);
});

$app->get('/links/{start}/{end}/{channelid}/{pagenom}', function ($request, $response, $args) {
    $linksPerPage=5;
    $pagenom=$args['pagenom'];
    $limit=2;
    $offset=$pagenom*$limit;
	$sth = $this->db->prepare("SELECT * FROM links WHERE id BETWEEN :starttime AND :endtime AND channel_id=:channelid LIMIT $limit OFFSET $offset");
	$sth->bindParam('starttime', $args['start']);
    $sth->bindParam('endtime', $args['end']);
    $sth->bindParam('channelid', $args['channelid']);
    $sth->execute();
	$links = $sth->fetchAll();
	$totalLinks=count($links);
	return $this->response->withJson($links);
});

$app->get('/links/{start}/{end}', function ($request, $response, $args) {
    $sth = $this->db->prepare("SELECT * FROM  links WHERE id BETWEEN :starttime AND :endtime");
    $sth->bindParam('starttime', $args['start']);
    $sth->bindParam('endtime', $args['end']);
	$sth->execute();
	$links = $sth->fetchAll();
	return $this->response->withJson($links);
});

$app->get('/fetch-message/[{id}]', function ($request, $response, $args) {
    $token = getToken();
    $url = "https://slack.com/api/channels.history?token=$token&channel=$args[id]&pretty=1";
    $result = callAPI("GET", $url);
    $data = json_decode($result);
    foreach ($data->messages as $datum){
      if(property_exists($datum, 'attachments')){
        foreach ($datum->attachments as $attachments){
          if(property_exists($attachments, 'from_url') && (property_exists($attachments, 'image_url') || property_exists($attachments, 'thumb_url') || property_exists($attachments, 'title_link'))){
          var_dump($datum->ts);
          echo "<br/> <br/>";
            $sth = $this->db->prepare("SELECT * FROM links WHERE id=:id_attachment OR id=:id_message");
            $sth->bindParam("id_attachment", $attachments->ts);
            $sth->bindParam("id_message", $datum->ts);
            $sth->execute();
            $link = $sth->fetchObject();
            var_dump($link);
            echo "<br/><br/>";
            if($link == null){
                $sql = "INSERT INTO links (id, service_name, title, description, url, image_url, channel_id) VALUES (:id, :service_name, :title, :description, :url, :image_url, :channel_id)";
                $sth = $this->db->prepare($sql);
                if ($attachments->ts == null){
                   $sth->bindParam("id",$datum->ts);
                }
                else{   
                  $sth->bindParam("id", $attachments->ts);
                }  
                $sth->bindParam("service_name", $attachments->service_name);
                $sth->bindParam("title", $attachments->title);
                $sth->bindParam("description", $attachments->text);
                $sth->bindParam("url", $attachments->title_link);
                if ($attachments->image_url == null) {
                  $sth->bindParam("image_url", $attachments->thumb_url);
                }
                else{
                  $sth->bindParam("image_url",$attachments->image_url);
                  }  
                $sth->bindParam("channel_id", $args['id']);
                $sth->execute();
            }
          }
        }
      }
    }
});

function getToken(){
	return "xoxp-8164026197-122565617027-288161819667-2125b66418e92a7139b6b175d2c7acce";
}

function callAPI($method, $url, $data = false){
	try{
    $curl = curl_init();
    switch ($method){
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

	if (FALSE === $result)
        throw new Exception(curl_error($curl), curl_errno($curl));

    curl_close($curl);
    return $result;
} catch(Exception $e) {

    trigger_error(sprintf(
        'Curl failed with error #%d: %s',
        $e->getCode(), $e->getMessage()),
        E_USER_ERROR);

}
}