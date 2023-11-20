<?php
include_once '../baseInfo.php';
include_once '../config.php';
$time = time();

if(file_exists("warnOffset.txt")) $warnOffset = file_get_contents("warnOffset.txt");
else $warnOffset = 0;
$limit = 50;

$stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `status`=1 AND `notif`=0 ORDER BY `id` ASC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $warnOffset);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

if($orders){
    if($orders->num_rows>0){
        while ($order = $orders->fetch_assoc()){
            $send = false;
    	    $from_id = $order['userid'];
    	    $token = $order['token'];
            $remark = $order['remark'];
            $server_id = $order['server_id'];
            $inbound_id = $order['inbound_id'];
            $links_list = $order['link']; 
            $notif = $order['notif'];
            $expiryTime = "";
            $response = getJson($server_id)->obj; 
            $found = false;
            foreach($response as $row){
                if($inbound_id == 0) { 
                    if($row->remark == $remark) { 
                        $found = true;
                        $total = $row->total;
                        $up = $row->up;
                        $down = $row->down;
                        $expiryTime = $row->expiryTime;
                        break;
                    }
                }else{
                    if($row->id == $inbound_id) {
                        $settings = json_decode($row->settings, true); 
                        $clients = $settings['clients'];
                        foreach($clients as $key => $client) {
                            if($client['email'] == $remark) {
                                $found = true;
                                $total = $client['totalGB'];
                                break;
                            }
                        }
                        
                        $clientStats = $row->clientStats; 
                        foreach($clientStats as $key => $clientStat) {
                            if($clientStat->email == $remark) {
                                $up = $clientStat->up;
                                $down = $clientStat->down;
                                $expiryTime = $clientStat->expiryTime;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
            if(!$found) continue;
            
            $leftgb = round( ($total - $up - $down) / 1073741824, 2);
            $now_microdate = floor(microtime(true) * 1000);
            if($expiryTime != null && $total != null){
                $send = "";
                if($expiryTime < $now_microdate + 86400000) $send = "روز"; elseif($leftgb < 1) $send = "گیگ";
                if($send != ""){  
                    $msg = "💡 کاربر گرامی، 
        از سرویس اشتراک $remark کمتر از (۱ $send) باقی مونده. میتونید از قسمت کانفیگ های من سرویس فعلیتون رو تمدید کنید یا سرویس جدید خریداری کنید.";
                    sendMessage( $msg, null, null, $from_id);
                    $newTIme = $time + 86400 * 2;
                    $stmt = $connection->prepare("UPDATE `orders_list` SET `notif`= ? WHERE `remark`=?");
                    $stmt->bind_param("is", $newTIme, $remark);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        file_put_contents("warnOffset.txt", $warnOffset + $limit);
    }else unlink('warnOffset.txt');
}


$stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `status`=1 AND `notif` !=0 AND `notif` < ? LIMIT 50");
$stmt->bind_param("i", $time);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

if($orders){
    if($orders->num_rows>0){
        while ($order = $orders->fetch_assoc()){
            $send = false;
    	    $from_id = $order['userid'];
    	    $token = $order['token'];
            $remark = $order['remark'];
            $server_id = $order['server_id'];
            $inbound_id = $order['inbound_id'];
            $links_list = $order['link']; 
            $notif = $order['notif'];
            
            $response = getJson($server_id)->obj;  
            foreach($response as $row){
                if($inbound_id == 0) { 
                    if($row->remark == $remark) { 
                        $total = $row->total;
                        $up = $row->up;
                        $down = $row->down;
                        $expiryTime = $row->expiryTime;
                        break;
                    }
                }else{
                    if($row->id == $inbound_id) {
                        $settings = json_decode($row->settings, true); 
                        $clients = $settings['clients'];
                        foreach($clients as $key => $client) {
                            if($client['email'] == $remark) {
                                $total = $client['totalGB'];
                                break;
                            }
                        }
                        
                        $clientStats = $row->clientStats; 
                        foreach($clientStats as $key => $clientStat) {
                            if($clientStat->email == $remark) {
                                $up = $clientStat->up;
                                $down = $clientStat->down;
                                $expiryTime = $clientStat->expiryTime;
                                break;
                            }
                        }
                        break;
                    }
                }
            } 
            $leftgb = round( ($total - $up - $down) / 1073741824, 2);
            $now_microdate = floor(microtime(true) * 1000);
            if($expiryTime <= $now_microdate) $send = true; elseif($leftgb <= 0) $send = true;
            if($send){  
                if($inbound_id > 0) $res = deleteClient($server_id, $inbound_id, $remark); else $res = deleteInbound($server_id, $remark); 
        		if(!is_null($res)){
                    $msg = "💡 کاربر گرامی،
    اشتراک سرویس $remark منقضی شد و از لیست سفارش ها حذف گردید. لطفا از فروشگاه, سرویس جدید خریداری کنید.";
                    sendMessage( $msg, null, null, $from_id);
                    $stmt = $connection->prepare("DELETE FROM `orders_list` WHERE `remark`=?");
                    $stmt->bind_param("s", $remark);
                    $stmt->execute();
                    $stmt->close();
                    continue;
        		}
            }                
            else{
                $stmt = $connection->prepare("UPDATE `orders_list` SET `notif`= 0 WHERE `remark`=?");
                $stmt->bind_param("s", $remark);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
