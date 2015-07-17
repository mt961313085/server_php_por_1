<?php
//从读到数据（可能是多条指令）中获取指令
$str='gfha[012,w,54467]p,fs[033,s,13111],[024,w,98789]';
//记录所有设备对应的socket编号，例如$equipment[2]=5，代表二号设备的通信套接字编号为5
//一个1个服务器和25设备，$equipment[0]代表服务器通信套接字编号。
global $equipment;
$equipment[26]=array();
//记录没给设备的当前状态,例如$equipment[2]='0A2B0'
$equip_stat[26]=array();
//初始化设备全部为关闭状态
for($i=0;$i<=25;$i++){
	$equip_stat[$i]='00000';
}
//该函数将套接字读取到数据传入，提取出一条或多条指令，返回指令数组
function get_instruct($read_data,$read_socket,&$equipment,&$equip_stat){
echo "recive $read_data\r\n";
$z='/\[[^\]]*\]/';
$i=preg_match_all($z,$read_data,$instruct);
//$instruct是个二维数组，得把他变成一维数组
$instruct=$instruct[0];
//print_r($instruct);

 foreach ($instruct as $value)
{	
	//-----------------解析指令，获取设备ID，记录下该设备的套接字编号
	$id=substr($value, 1,3);
	//根据第一位判断该ID是否为服务器ID
	$fid=substr($id,0,1);
	//截取ID后两位
	$sid=substr($id, 1,2);
	//将字符串转换为数字
	$nid=intval($sid);
	if($fid=='F')
	{//记录服务器端口的socket编号
		$equipment[0]=$read_socket;
	}
	else {
		$equipment[$nid]=$read_socket;
	}
	
	//-----------------根据指令类型回复
	$type=substr($value,5,1);
	$num=substr($value,7,5);
	switch ($type){
			//0表示硬件，或服务器请求读,1代表回复类型
		case '0': 
			//echo '----------'.$equip_stat[$nid].'-------------';
			$response="[$id,1,$equip_stat[$nid]]";
			socket_write($read_socket,$response,strlen($response));
			echo 'send:'.$response."\r\n";
			break;
			
			//2表示开关类型控制类型，从服务器收到，转发给硬件;
		case '2':
			$response="[0$sid,2,$num]";
			//将控制消息通过套接字发给硬件
			socket_write($equipment[$nid], $response,strlen($response));
			echo "send:$response\r\n";
			break;
			
			//将硬件将控制信号硬件接收并处理后的确认信息发送给服务器
		case '3':
			//更新设备状态
			$equip_stat[$nid]=$num;
			//设置消息格式
			$response="[F$sid,3,$num]";
			//发送给服务器
			socket_write($equipment[0],$response,strlen($response));
			echo "send:$response\r\n";
			break;
			
			//设备异常开箱，记录日志，报告给服务器
		case '4':
			//更新箱子状态
			$equip_stat[$nid]=$num;
			//记录日志
			error_log("NO.$nid box were opened by violence ".date('Y-m-d H:i:s')."\r\n",3,'error_log.txt');			
			$response="[F$sid,4,$num]";
			socket_write($equipment[0], $response,strlen($response));
			echo "send:$response\r\n";
			break;
			
			//收到服务器异常开箱的确认，将确认转发给硬件
		case '5':
			$response="[0$sid,5,$num]";
			socket_write($equipment[$nid],$response,strlen($response));
			break;
			
			//心跳不回复，只记录设备状态
		case '6':
			//记录设备状态
			$equip_stat[$nid]=$num;
			break;
			
			//心跳时间间隔设置成功，记入日志
		case 'B':
			error_log("Heartbeat interval $num S".date('Y-m-d H:i:s')."\r\n",3,'error_log.txt');
			break;
			
	}
	//print_r($equipment);
}  

}
//get_instruct($str, 54);
?>