# vk_api
Заготовка класса для работы с АПИ Вконтактика

## Получить токен
1. [Создать приложение](https://vk.com/editapp?act=create) типа Standalone 
2. В настройках выставить использование OpenAPI и домен localhost (опционально)
3. Скопировать ID приложения и "Сервисный ключ доступа". 
4. Продумать, какие [права](https://vk.com/dev/permissions) нужны токену. Пишем через запятую.<BR>
Советую всегда добавлять offline, это позволит токену жить дольше.
4. Сформировать урл, перейти и скопировать себе access_token.

```
https://oauth.vk.com/authorize?client_id=АЙДИ ПРИЛОЖЕНИЯ&display=page&redirect_uri=https://oauth.vk.com/blank.html&scope=ПРАВА&response_type=token&v=5.74
```
**Пример**
```
https://oauth.vk.com/authorize?client_id=6362076&scope=groups,wall,offline,photos,market&redirect_uri=https://oauth.vk.com/blank.html&display=page&v=5.95&response_type=token
```

> **Важно!** Ключ устаревает каждый раз, когда вы меняете пароль либо завершаете сеансы. <BR>
> Не рекомендую предоставлять полученный access_token третьим лицам.


## Работа с классом
**Пример: скачивание фотографий из альбома группы.**
```php
<?php 
//  Подключение.
require_once 'vk.php';
$access_token = 'ТОКЕН';
$app_service_key = 'СЕРВ. КЛЮЧ';

//  Вызов класса
$vk = new VK([
	'access_token' => $access_token,
	'app_service_key' => $app_service_key,
	'ver' => '5.95'
	]
);

//  Для примера скачаем все фото из группы
$g_id = '39552595'; //id группы
$album_id = '170962386';  //id альбома

//  Вызов апи. Передаём метод и массив параметров.
$photos = $vk->api('photos.get',[
	'owner_id' => '-'. $g_id,
	'album_id' => $album_id,
	'count' => 10,
	'photo_sizes' => 0,
	'rev' => 1
]);

//Проходимся циклом по полученным фото и стараемся найти наибольший размер
foreach($photos->items as $one_photo){
	$sizes = ['photo_2560','photo_1280','photo_807','photo_604', 'photo_130'];
	foreach($sizes as $one){
		if(property_exists($one_photo,$one)){
			$max_size = $one;
			break;
		}
	};
	$path = $one_photo->$max_size;
  //  Формируем массив из путей и имён
	$myphotos[] = [
		'id' => $one_photo->id,
		'name' => 'photo' . $one_photo->owner_id . '_' . $one_photo->id . '.jpg',
		'path' => $path
	];

}

//  Сохраняем файл на диск
foreach($myphotos as $one){
	 saveFile($one['path'],$one['name'], $vk);
}

// Функция сохранения файлов
function saveFile($filepath,$newname,$vk){
	$old_filename = basename($filepath);
	$context = $vk->curl($filepath);
	if($context){
		$res = @file_put_contents(__DIR__ . '\photos\\'. $newname, $context);
		if($res){
			echo "Файл $old_filename записан\n";
		}
		else{
			echo "Файл $old_filename не может записаться\n";
		}
	}
}
```

**Пример: удаление мёртвых участников.**

Вы должны быть владельцем группы.

```php
<?php 
//  Подключение.
require_once 'vk.php';
$access_token = 'ТОКЕН';
$app_service_key = 'СЕРВ. КЛЮЧ';

//  Вызов класса
$vk = new VK([
	'access_token' => $access_token,
	'app_service_key' => $app_service_key,
	'ver' => '5.95'
	]
);

//Получение всех  пользователей группы и доп. поля deactivated.
$g_id = '53125056'; //id группы для примера
$members = $vk->getAllGroupMembers($g_id,['deactivated']);

$banned_users = [];
foreach($members as $member){
	if(isset($member->deactivated)){
		$banned_users[] = $member->id;
	}
}

echo "Всего " . count($banned_users) . " заблокированных \n";
if(count($banned_users)){
	foreach($banned_users as $one_kill){
		$kill = $vk->api('groups.removeUser',[
			'group_id' => $g_id,
			'user_id' => $one_kill
			]
		);
		echo "Удалили $kill Айди $one_kill \n";
		sleep(1);
	}
}

```
В процессе класс будет допиливаться, а документация - дописываться.
