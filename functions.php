<?php
function db($host,$user,$pass,$db_name) {
	$db = mysql_connect($host,$user,$pass);
	if(!$db) {
		exit(mysql_error());
	}

	if(!mysql_select_db($db_name,$db)) {
		exit(mysql_error());
	}

	mysql_query("SET NAMES UTF8");
}

function clear_str($str) {
	return trim(strip_tags($str));
}

function render($path,$param = array()) {
	extract($param);

	ob_start();

	if(!include($path.".php")) {
		exit("Нет такого шаблона");
	}

	return ob_get_clean();

}

function registration($post) {

	$login = clear_str($post['reg_login']);
	$password = trim($post['reg_password']);
	$conf_pass= trim($post['reg_password_confirm']);
	$email = clear_str($post['reg_email']);
	$name = clear_str($post['reg_name']);

	$msg = '';

	if(empty($login)) {
		$msg .= "Введите логин <br />";
	}
	if(empty($password)) {
		$msg .= "Введите пароль <br />";
	}
	if(empty($email)) {
		$msg .= "Введите адресс почтового ящика <br />";
	}
	if(empty($name)) {
		$msg .= "Введите имя <br />";
	}



	if($msg) {
		$_SESSION['reg']['login'] = $login;
		$_SESSION['reg']['email'] = $email;
		$_SESSION['reg']['name'] = $name;
		return $msg;
	}

	if($conf_pass == $password) {
		$sql = "SELECT user_id
					FROM ".PREF."users
					WHERE login='%s'";
		$sql = sprintf($sql,mysql_real_escape_string($login));

		$result = mysql_query($sql);

		if(mysql_num_rows($result) > 0) {
			$_SESSION['reg']['email'] = $email;
			$_SESSION['reg']['name'] = $name;

			return "Пользователь с таким логином уже существует";
		}

		$password = md5($password);
		$hash = md5(microtime());

		$query = "INSERT INTO ".PREF."users (
						name,
						email,
						password,
						login,
						hash
						)
					VALUES (
						'%s',
						'%s',
						'%s',
						'%s',
						'$hash'
					)";
		$query = sprintf($query,
			mysql_real_escape_string($name),
			mysql_real_escape_string($email),
			$password,
			mysql_real_escape_string($login)
		);
		$result2 = mysql_query($query);

		if(!$result2) {
			$_SESSION['reg']['login'] = $login;
			$_SESSION['reg']['email'] = $email;
			$_SESSION['reg']['name'] = $name;
			return "Ошибка при добавлении пользователя в базу данных".mysql_error();
		}
		else {
			$headers = '';
			$headers .= "From: Admin <admin@mail.ru> \r\n";
			$headers .= "Content-Type: text/plain; charset=utf8";

			$tema = "registration";

			$mail_body = "Спасибо за регистрацию на сайте. Ваша ссылка для подтверждения  учетной записи: http://board/index.php?action=registration&hash=".$hash;

			mail($email,$tema,$mail_body,$headers);

			return TRUE;

		}
	} else {
		$_SESSION['reg']['login'] = $login;
		$_SESSION['reg']['email'] = $email;
		$_SESSION['reg']['name'] = $name;
		return "Вы не правильно подтвердили пароль";
	}

}
	function confirm() {
	
	$new_hash = clear_str($_GET['hash']);
	
	$query = "UPDATE ".PREF."users
				SET confirm='1'
				WHERE hash = '%s'
				";
	$query = sprintf($query,mysql_real_escape_string($new_hash));	
	
	$resutl = mysql_query($query);
	
	if(mysql_affected_rows() == 1) {
		return TRUE;
	}
	else {
		return "Не верный код подтверждения регистрации";
	}
		
}
function login($post) {
	
	
	if(empty($post['login']) || empty($post['password'])) {
		return "Заполните поля";
	}
	
	$login = clear_str($post['login']);
	$password = md5(trim($post['password']));
	
	$sql = "SELECT user_id,confirm
			FROM ".PREF."users
			WHERE login = '%s'
			AND password = '%s'";
	$sql = sprintf($sql,mysql_real_escape_string($login),$password);
	
	$result = mysql_query($sql);
	
	if(!$result || mysql_num_rows($result) < 1) {
		return "Не правильный логи или пароль";
	}
	if(mysql_result($result,0,'confirm') == 0) {
		return "Пользователь с таким логином еще не продтвержден";
	}		
	
	$sess = md5(microtime());
	
	$sql_update = "UPDATE ".PREF."users SET sess='$sess' WHERE login='%s'";
	$sql_update = sprintf($sql_update,mysql_real_escape_string($login));
	
	if(!mysql_query($sql_update)) {
		return "Ошибка авторизации пользователя";
	}
	
	$_SESSION['sess'] = $sess;
	
	if($post['member'] == 1) {
		$time = time() + 10*24*3600;
		
		setcookie('login',$login,$time);
		setcookie('password',$password,$time);
		
	}
	
	return TRUE;
}
function logout() {
	unset($_SESSION['sess']);
	
	setcookie('login','',time()-3600);
	setcookie('password','',time()-3600);
	
	return TRUE;
}

function check_user() {

	if(isset($_SESSION['sess'])) {
		$sess = $_SESSION['sess'];

		$sql = "SELECT user_id,name,id_role
				FROM ".PREF."users
				WHERE sess='$sess'";
		$result = mysql_query($sql);

		if(!$result || mysql_num_rows($result) < 1) {
			return FALSE;
		}

		return mysql_fetch_assoc($result);
	}
	elseif(isset($_COOKIE['login']) && isset($_COOKIE['password'])) {
		$login = $_COOKIE['login'];
		$password = $_COOKIE['password'];

		$sql = "SELECT user_id,name,id_role
				FROM ".PREF."users
				WHERE login='$login'
				AND password='$password'
				AND confirm = '1'";
		$result2 = mysql_query($sql);

		if(!$result2 || mysql_num_rows($result2) < 1) {
			return FALSE;
		}

		$sess = md5(microtime());

		$sql_update = "UPDATE ".PREF."users SET sess='$sess' WHERE login='%s'";
		$sql_update = sprintf($sql_update,mysql_real_escape_string($login));

		if(!mysql_query($sql_update)) {
			return "Ошибка авторизации пользователя";
		}

		$_SESSION['sess'] = $sess;

		//return mysql_fetch_assoc($result);
	}
	else {
		return FALSE;
	}

}

function get_password($email)
{
	$email = clear_str($email);

	$sql = "SELECT user_id
			FROM " . PREF . "users
			WHERE email = '%s'";
	$sql = sprintf($sql, mysql_real_escape_string($email));

	$result = mysql_query($sql);

	if (!$result) {
		return "не возможно сгенерировать новый пароль";
	}

	if (mysql_num_rows($result) == 1) {
		$str = "234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM";

		$pass = '';

		for ($i = 0; $i < 6; $i++) {
			$x = mt_rand(0, (strlen($str) - 1));

			if ($i != 0) {
				if ($pass[strlen($str) - 1] == $str[$x]) {
					$i--;
					continue;
				}
			}
			$pass .= $str[$x];
		}

		$md5pass = md5($pass);

		$query = "UPDATE " . PREF . "users
					SET password='$md5pass'
					WHERE user_id = '" . mysql_result($result, 0, 'user_id') . "'";
		$result2 = mysql_query($query);

		if (!$result2) {
			return "Не возможно сгенерировать новый пароль";
		}

		$headers = '';
		$headers .= "From: Admin <admin@mail.ru> \r\n";
		$headers .= "Content-Type: text/plain; charset=utf8";

		$subject = 'new password';
		$mail_body = "Ваш новый пароль: " . $pass;

		mail($email, $subject, $mail_body, $headers);

		return TRUE;
	} else {
		return "Пользователя с таким потчтовым ящиком нет";
	}
}
	function can($id,$priv_adm) {
		$priv = getPriv($id);
		if(!$priv) {
			$priv = array();
		}

		$arr = array_intersect($priv_adm,$priv);

		if($arr === $priv_adm) {
			return TRUE;
		}

		return FALSE;


	}

	function getPriv($id) {
		$sql = "SELECT ".PREF."priv.name AS priv
				FROM ".PREF."priv
				LEFT JOIN ".PREF."role_priv
					ON ".PREF."role_priv.id_priv = ".PREF."priv.id
				WHERE ".PREF."role_priv.id_role = '$id'
				"
		;
		$result = mysql_query($sql);

		if(!$result) {
			return FALSE;
		}

		for($i = 0; $i < mysql_num_rows($result);$i++) {
			$row = mysql_fetch_array($result,MYSQL_NUM);

			$arr[] = $row[0];
		}

		return $arr;

}

	function get_razdel() {
		$sql = "SELECT id,name FROM ".PREF."razd";
		$result = mysql_query($sql);

		return get_result($result);
	}
	function get_result($result) {
		if(!$result) {
			exit(mysql_error());
		}
		if(mysql_num_rows($result)== 0) {
			return FALSE;
		}
		$row = array();
		for($i = 0; mysql_num_rows($result) > $i; $i++) {
			$row[] = mysql_fetch_array($result, MYSQL_ASSOC);
		}
		return $row;
	}
	function get_categories()
	{
		$sql = "SELECT * FROM ".PREF."categories";
		$result = mysql_query($sql);

		if (!$result) {
			exit(mysql_error());
		}

		if (mysql_num_rows($result) == 0) {
			return FALSE;
		}

		$categories = array();

		for ($i = 0; mysql_num_rows($result) > $i; $i++) {
			$row = mysql_fetch_array($result, MYSQL_ASSOC);
			if (!$row['parent_id']) {
				$categories[$row['id']][] = $row['name'];
			} else {
				$categories[$row['parent_id']]['next'][$row['id']] = $row['name'];
			}
		} return $categories;
	}
function get_img() {

	$width = 160;
	$height = 80;

	$r = mt_rand(133,255);
	$g = mt_rand(133,255);
	$b = mt_rand(133,255);

	$im = imagecreatetruecolor($width,$height);

	$background = imagecolorallocate($im,$r,$g,$b);

	imagefilledrectangle($im,0,0,$width,$height,$background);

	$black = imagecolorallocate($im,7,7,7);

	for($h = mt_rand(1,10);$h < $height; $h = $h + mt_rand(1,10)) {
		for($v = mt_rand(1,30);$v < $width; $v = $v + mt_rand(1,30)) {

			imagesetpixel($im,$v,$h,$black);
		}
	}

	$str = generate_str();
	$_SESSION['str_cap'] = $str;

	$fonts_p = "fonts/";

	$d = opendir($fonts_p);
	while(FALSE !=($file = readdir($d))) {
		if($file == "." || $file == "..") {
			continue;
		}
		$fonts[] = $file;
	}

	$x = 20;
	$color = imagecolorallocate($im,7,7,7);
	for($i = 0;$i < strlen($str);$i++) {

		$n = mt_rand(0,count($fonts)-1);
		$font = $fonts_p.$fonts[$n];

		$size = mt_rand(15,35);
		$angle = mt_rand(-30,30);
		$y = mt_rand(40,45);

		imagettftext($im,$size,$angle,$x,$y,$color,$font,$str[$i]);
		$x = $x + $size - 5;
	}

	for($c = 0; $c < 5; $c++) {

		$x1 = mt_rand(0,intval($width*0.1));
		$x2 = mt_rand(intval($width*0.8),$width);

		$y1 = mt_rand(0,intval($height*0.6));
		$y2 = mt_rand(intval($height*0.3),$height);

		imageline($im,$x1,$y1,$x2,$y2,$black);
	}



	header("Content-Type: image/png");
	imagepng($im);
	imagedestroy($im);
}


function generate_str() {

	$str = "23456789abcdegikpqsvxyz";
	$strLength = strlen($str) - 1;

	$str_g = "";

	for($i = 0; $i < 5; $i++) {

		$x = mt_rand(0,$strLength);

		if($i !== 0) {
			if($str_g[strlen($str_g) - 1] == $str[$x]) {
				$i--;
				continue;
			}
		}

		$str_g .= $str[$x];

	}

	return $str_g;

}

function add_mess($post,$user_id) {
	$title = clear_str($post['title']);
	$text = $post['text'];
	$id_categories =(int)($post['id_categories']);
	$id_razd =(int)($post['id_razd']);
	$price =(int)($post['price']);
	$town = clear_str($post['town']);
	$date = time();
	$a_time = (int)($post['time']);
	$time_over = $date + ($a_time*(60*60*24));

	$msg = '';

	if(empty($_SESSION['str_cap']) || $_SESSION['str_cap'] !== $post['capcha']) {
		$_SESSION['p']['title'] = $title;
		$_SESSION['p']['text'] = $text;
		$_SESSION['p']['town'] = $town;
		$_SESSION['p']['price'] = $price;
		$msg = "Ошибка ввода капчи";

	}

	unset($_SESSION['str_cap']);

	if(empty($title)) {
		$msg .= "Введите заголовок";
	}
	if(empty($text)) {
		$msg .= "Введите текст";
	}

	if(!empty($msg)) {
		$_SESSION['p']['title'] = $title;
		$_SESSION['p']['text'] = $text;
		$_SESSION['p']['town'] = $town;
		$_SESSION['p']['price'] = $price;
		echo $msg;

	}

	$img_types = array('jpeg'=>"image/jpeg",
		"pjpeg"=>"image/pjpeg",
		'png' => "image/png",
		'x-png' => "image/x-png",
		'gif' => "image/gif",
	);

	if(!empty($_FILES['img']['tmp_name'])) {

		if(!empty($_FILES['img']['error'])) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return "Erorr upload image";
		}

		$type_img = array_search($_FILES['img']['type'],$img_types);
		if(!$type_img) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return "Wrong type img";
		}

		if($_FILES['img']['size'] > (2*1024*1024)) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return "Very big img";
		}

		if(!move_uploaded_file($_FILES['img']['tmp_name'],FILES.$_FILES['img']['name'])) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return "Error copy image";
		}


		if(!img_resize($_FILES['img']['name'],$type_img)) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return "Error to resize image";
		}


		$img = $_FILES['img']['name'];

		$sql = "INSERT INTO ".PREF."post(
											title,text,img,date,id_user,id_categories,id_razd,town,time_over,price
											)
											VALUES (
												'$title','$text','$img','$date','$user_id','$id_categories','$id_razd','$town','$time_over','$price'
											)

										";


		$result = mysql_query($sql);

		if(!$result) {
			$_SESSION['p']['title'] = $title;
			$_SESSION['p']['text'] = $text;
			$_SESSION['p']['town'] = $town;
			$_SESSION['p']['price'] = $price;
			return mysql_error();
		}

	}
	else {
		$_SESSION['p']['title'] = $title;
		$_SESSION['p']['text'] = $text;
		$_SESSION['p']['town'] = $town;
		$_SESSION['p']['price'] = $price;
		return "Добавьте изображение";
	}

	if(!empty($_FILES['mini'])) {
		$id_mess = mysql_insert_id();

		$img_s = "";

		for($i = 0; $i < count($_FILES['mini']['tmp_name']); $i++) {
			if(empty($_FILES['mini']['tmp_name'][$i])) continue;

			if(!empty($_FILES['mini']['error'][$i])) {
				$_SESSION['p']['title'] = $title;
				$_SESSION['p']['text'] = $text;
				$_SESSION['p']['town'] = $town;
				$_SESSION['p']['price'] = $price;
				$msg .= "Erorr upload image";
				continue;
			}

			$type_img = array_search($_FILES['mini']['type'][$i],$img_types);
			if(!$type_img) {
				$_SESSION['p']['title'] = $title;
				$_SESSION['p']['text'] = $text;
				$_SESSION['p']['town'] = $town;
				$_SESSION['p']['price'] = $price;
				$msg .="Wrong type img";
				continue;
			}

			if($_FILES['mini']['size'][$i] > (2*1024*1024)) {
				$_SESSION['p']['title'] = $title;
				$_SESSION['p']['text'] = $text;
				$_SESSION['p']['town'] = $town;
				$_SESSION['p']['price'] = $price;
				$msg .="Very big img";
				continue;
			}

			$name_img = $id_mess."_".$i;
			$rash = substr($_FILES['mini']['name'][$i],strripos($_FILES['mini']['name'][$i],"."));
			$name_img .=$rash;

			if(!move_uploaded_file($_FILES['mini']['tmp_name'][$i],FILES.$name_img)) {
				$_SESSION['p']['title'] = $title;
				$_SESSION['p']['text'] = $text;
				$_SESSION['p']['town'] = $town;
				$_SESSION['p']['price'] = $price;
				$msg .= "Error copy image";
				continue;
			}


			if(!img_resize($name_img,$type_img)) {
				$_SESSION['p']['title'] = $title;
				$_SESSION['p']['text'] = $text;
				$_SESSION['p']['town'] = $town;
				$_SESSION['p']['price'] = $price;
				return "Error to resize image";
			}

			$img_s .= $name_img."|";
		}
		$img_s  = rtrim($img_s,"|");

		$sql = "UPDATE ".PREF."post SET img_s = '$img_s' WHERE id = '$id_mess'";

		$result2 = mysql_query($sql);
		if(mysql_affected_rows()) {
			if(!empty($msg)) {
				echo $msg;
			}
			return TRUE;
		}
	}
	else {
		return TRUE;
	}
}

function img_resize($file_name,$type) {
	switch($type) {

		case 'jpeg':
		case 'pjpeg':
			$img_id = imagecreatefromjpeg(FILES.$file_name);
			break;

		case 'png':
		case 'x-png':
			$img_id = imagecreatefrompng(FILES.$file_name);
			break;

		case 'gif':
			$img_id = imagecreatefromgif(FILES.$file_name);
			break;
	}

	$img_width = imageSX($img_id);
	$img_height = imageSY($img_id);


	$k = round($img_width/IMG_WIDTH,2);

	$img_mini_width = round($img_width/$k);
	$img_mini_height = round($img_height/$k);

	$img_dest_id = imagecreatetruecolor($img_mini_width,$img_mini_height);


	$result = imagecopyresampled($img_dest_id,
		$img_id,
		0,
		0,
		0,
		0,
		$img_mini_width,
		$img_mini_height,
		$img_width,
		$img_height
	);
	$img = imagejpeg($img_dest_id,MINI.$file_name,100);

	imagedestroy($img_id);
	imagedestroy($img_dest_id);

	if($img) {
		return TRUE;
	}
	else {
		return FALSE;
	}
}

function get_p_mess($user) {

	$sql = "SELECT
					".PREF."post.id,
					".PREF."post.title,
					img,
					text,
					date,
					town,
					price,
					".PREF."post.confirm,
					is_actual,
					time_over,
					".PREF."users.name AS uname,
					".PREF."users.email,
					".PREF."categories.name AS cat,
					".PREF."razd.name AS razd
				FROM ".PREF."post
				LEFT JOIN ".PREF."users ON ".PREF."users.user_id = '$user'
				LEFT JOIN ".PREF."categories ON ".PREF."categories.id = ".PREF."post.id_categories
				LEFT JOIN ".PREF."razd ON ".PREF."razd.id = ".PREF."post.id_razd
				WHERE ".PREF."post.id_user = '$user'
				ORDER by date DESC
						";
	$result = mysql_query($sql);
	return get_result($result);
}