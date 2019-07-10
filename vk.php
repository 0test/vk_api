<?php
class VK{
	public function __construct(array $config){
		$this->access_token = $config['access_token'];
		$this->app_service_key = $config['app_service_key'];
		$this->ver = $config['ver'];
		
		if(!$this->checkUserToken()){
			die('Токен неверный');
			return false;
		}		
	}
	public function marketGet(array $params){
		//	получаем все товары из группы
		//	$params - параметры вызова, обязательный параметр gid - идентификатор группы со знаком "минус".
		//	Пример: marketGet(['gid' => '-33479582']);
		//	Отдаст $data['result']
		//	TODO: можно использовать execute
		if(!isset($params['offset'])){
			$first_query = $this->api('market.get',[
				'owner_id'=> $params['gid'],
				'offset' => 0,
				'count' => 200,
				]
			);

			$params['all_items_count'] = $first_query->count;
			$params['result'] = $first_query->items;
			
			if($params['all_items_count'] <= 200){
				return $params;
			}
			elseif($params['all_items_count'] > 200){
				return $this->marketGet([
						'gid'    => $params['gid'],
						'all_items_count'  => $params['all_items_count'],
						'offset' => 200,
						'result' => $first_query->items
				]);
			}
		}
		else{
			if( $params['all_items_count'] > $params['offset']){
				$res = $this->api('market.get',[
					'owner_id'=> $params['gid'],
					'offset' => $params['offset'] ,
					'count' => 200,
					]
				);
				foreach($res->items as $o){
					$params['result'][] = $o;
				}
				return $this->marketGet([
						'gid'    => $params['gid'],
						'all_items_count'  => $params['all_items_count'],
						'offset' => $params['offset'] + 200,
						'result' => $params['result'],
				]);
			}
			else{
				return $params;
			}
		}
	}	
	public function getAllGroupMembers($gid, array $fields = array()){
		//	gid - id группы. Обязательный.
		//	fields - массив доп. полей пользователя. Например ['city','deactivated']. Необязательный.
		//	Список доп. полей: https://vk.com/dev/objects/user
		//	Пример: getAllGroupMembers($g_id,['city','deactivated']); 
		//	Отдаст всех участников, у которых покажет город и статус аккаунта - бан или удалён.
		$parameters = [
			'group_id' => $gid,
			'sort' => 'id_asc',
			'offset' => 0,
			'count' => 1000
		];
		if(count($fields)){
			$parameters['fields'] = implode(",", $fields);
		}
		$first_result = $this->api('groups.getMembers',$parameters);
		if($first_result->count < 1000){
			return $first_result->items;
		}
		else{
			//TODO: Сделать то же, но с execute. Будет быстрее в 30 раз.
			$all_users = $first_result->items;
			for($a = 1; $a < ceil($first_result->count / 1000); $a++){
				$parameters['offset'] = $a * 1000;
				$other_results = $this->api('groups.getMembers',$parameters);
				echo "\n $a iteration \n";
				sleep(0.5);
				foreach($other_results->items as $res){
					$all_users[] = $res;
				}
			}
			return $all_users;
		}
		
	}
	public function checkUserToken(){
		//Проверка юзер. токена.
		$result = $this->curl('https://api.vk.com/method/secure.checkToken?token=' . $this->access_token . '&access_token=' . $this->app_service_key . '&v=' . $this->ver);
		$result = json_decode($result);
		if(isset($result->error)){
			$result = false;
		}else{
			$result = true;
		}
		return $result;
	}
	public function api($method, array $query = array()){
		//Вызов любого метода апи.
		//
		$parameters = array();
		foreach ($query as $param => $value){
			$q = $param . '=';
			if(is_array($value)){
				$q .= urlencode(implode(',', $value));
			}else{
				$q .= urlencode($value);
			}
			$parameters[] = $q;
		}
		$q = implode('&', $parameters);
		if(count($query) > 0){
			$q .= '&';
		}
		$url = 'https://api.vk.com/method/' . $method . '?' . $q . 'access_token=' . $this->access_token . '&v='.$this->ver;
		$result = json_decode($this->curl($url));
		if(isset($result->response)){
			return $result->response;
		}else{
			var_dump($result);
		}
		return $result;
	}
	public function curl($url,$file=''){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL,$url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		if($file){
			curl_setopt($ch, CURLOPT_POST, true);
			if(class_exists('\CURLFile')){
				$params = ['photo' => new \CURLFile($file)];
			}else{
				$params = ['photo' => '@' . $file];
			}			
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}
		$out = curl_exec($curl);
		if($out === false){
			var_dump($out);
			die('Ошибка в курл');
		};
		return $out;
	}
}
