<?php
class Updater {
	public $settings = array(
		'admin_menu_category' => 'Settings',
		'admin_menu_name' => 'Updater',
		'description' => 'Downloads and installs module updates.',
		'admin_menu_icon' => '<i class="icon-cloud-download"></i>',
	);
	function admin_area() {
		global $billic, $db;
		echo '<h1>' . $this->settings['admin_menu_icon'] . ' ' . $this->settings['admin_menu_name'] . '</h1>';
		if (isset($_GET['Install'])) {
			$id = urldecode($_GET['Install']);
			echo 'Installing module ' . $id . '...<br>';
			$module_data = $billic->fetch_module($id);
			$install = $billic->install_module($module_data);
			if ($install != 'OK') {
				echo '<b>Error:</b> ' . $install;
				exit;
			}
			echo '<br><font color="green">Success!</font><br><br>';
			$billic->regenerate_module_cache();
			$billic->regenerate_menu_cache();
			exit;
		}
		if (isset($_POST['modules']) && !empty($_POST['modules'])) {
			$total = count($_POST['modules']);
			$progress = floor((100 / $total) * ($_POST['update_module'] + 1));
			if ($progress > 100) {
				$progress = 100;
			}
			echo '<hr><div class="progress"><div class="progress-bar progress-bar-striped" role="progressbar" aria-valuenow="' . $progress . '" aria-valuemin="' . $progress . '" aria-valuemax="100" style="width: ' . $progress . '%;">' . $progress . '%</div></div><hr>';
			if ($_POST['update_module'] >= $total) {
				echo 'Updates complete!';
				$billic->regenerate_module_cache();
				$billic->regenerate_menu_cache();
				exit;
			}
			$id = $_POST['modules'][$_POST['update_module']];
			echo 'Updating module ' . $id . '...<br>';
			$install = $billic->install_module($billic->fetch_module($id));
			if ($install != 'OK') {
				echo '<b>Error:</b> ' . $install;
				exit;
			}
			echo '<br><font color="green">Success!</font><br><br>';
			echo '<form method="POST" id="update_form">';
			foreach ($_POST['modules'] as $id) {
				echo '<input type="hidden" name="modules[]" value="' . $id . '">';
			}
			echo '<input type="hidden" name="update_module" value="' . ($_POST['update_module'] + 1) . '">';
			echo '<input type="submit" value="Continue &raquo;" class="btn btn-default">';
			echo '</form>';
?><script>
var auto_submit_countdown = 4;
addLoadEvent(function() {
	setInterval(function() {
      var submit = $("form#update_form").find(':submit');
		auto_submit_countdown--;
		if (auto_submit_countdown > 0) {
			submit.val('Continue ('+auto_submit_countdown+') \xBB');
		} else
		if (auto_submit_countdown == 0) {
			$('form#update_form').submit();
			submit.val('Processing next module... please wait');
			submit.attr('disabled', true);
		}
}, 500);
});
</script><?php
			exit;
		}
		$send = array();
		$html = '<table class="table table-striped"><tr><th>Module</th><th>Installed</th>';
		$modules = $db->q('SELECT `id`, `version`, `timestamp` FROM `installed_modules` ORDER BY `id` ASC');
		foreach ($modules as $module) {
			$html.= '<tr><td>' . $module['id'] . '</td><td>' . date('Y-m-d H:i:s', $module['timestamp']) . '</td></tr>';
			$send[$module['id']] = array(
				'version' => $module['version'],
			);
		}
		$html.= '</table>';
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT => "Curl/Billic",
			CURLOPT_AUTOREFERER => true,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_SSL_VERIFYHOST => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_URL => 'https://www.billic.com/API/',
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => array(
				'module' => 'ModuleEditor',
				'request' => 'update_check',
				'modules' => json_encode($send) ,
			) ,
		));
		$data = curl_exec($ch);
		if (curl_errno($ch) > 0) {
			echo 'Curl error: ' . curl_error($ch);
			exit;
		}
		$rawdata = trim($data);
		$data = json_decode($rawdata, true);
		if ($data === null) {
			echo 'Unexpected: ' . $rawdata;
			exit;
		}
		if (isset($data['error'])) {
			echo 'Remote Error: ' . $data['error'];
			exit;
		}
		if ($data['status'] != 'OK') {
			echo 'Status Error: ' . $data['status'];
			exit;
		}
		if (empty($data['updates'])) {
			echo '<div class="alert alert-success" role="alert">All modules are currently up-to-date.</div>';
		} else {
			echo '<form method="POST"><div class="alert alert-danger" role="alert">Updates are available for: ';
			$out = '';
			$form = '';
			foreach ($data['updates'] as $update) {
				$out.= $update . ', ';
				$form.= '<input type="hidden" name="modules[]" value="' . $update . '">';
			}
			$out = substr($out, 0, -2);
			echo $out;
			echo '<br><br>';
			echo $form;
			echo '<input type="hidden" name="update_module" value="0">';
			echo '<input type="submit" value="Start Update &raquo;" class="btn btn-danger"></div></form>';
		}
		echo $html;
	}
	function dashboard_submodule() {
		global $billic, $db;
		$DashboardBillicUpdateCache = get_config('DashboardBillicUpdateCache');
		$DashboardBillicUpdateCache = json_decode($DashboardBillicUpdateCache, true);
		$error = false;
		$r = '<br>';
		if (isset($_POST['BillicUpdateCheck']) || $DashboardBillicUpdateCache['lastcheck'] < time() - 3600) {
			$lastcheck_text = 'Just Now';
			$modules = $db->q('SELECT `id`, `version` FROM `installed_modules` ORDER BY `id` ASC');
			$send = array();
			foreach ($modules as $module) {
				$send[$module['id']] = array(
					'version' => $module['version'],
				);
			}
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT => "Curl/Billic",
				CURLOPT_AUTOREFERER => true,
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_MAXREDIRS => 5,
				CURLOPT_SSL_VERIFYHOST => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_URL => 'https://www.billic.com/API/',
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => array(
					'module' => 'ModuleEditor',
					'request' => 'update_check',
					'modules' => json_encode($send) ,
				) ,
			));
			$data = curl_exec($ch);
			if (curl_errno($ch) > 0) {
				$error = 'Curl error: ' . curl_error($ch);
			} else {
				$rawdata = trim($data);
				$data = json_decode($rawdata, true);
				if ($data === null) {
					$error = 'Unexpected: ' . $rawdata;
				} else if (isset($data['error'])) {
					$error = 'Remote Error: ' . $data['error'];
				} else if ($data['status'] != 'OK') {
					$error = 'Status Error: ' . $data['status'];
				} else {
					$updates_available = count($data['updates']);
					set_config('DashboardBillicUpdateCache', json_encode(array()));
				}
			}
		} else {
			$lastcheck_text = $billic->time_ago($DashboardBillicUpdateCache['lastcheck']) . ' ago';
			$updates_available = $DashboardBillicUpdateCache['updates_available'];
		}
		if ($error !== false) {
			$r.= '<div class="alert alert-danger" role="alert">' . $error . '</div>';
		}
		if ($updates_available > 0) {
			$r.= '<div class="alert alert-danger" role="alert">There are ' . count($updates_available) . ' updates available!</div><a href="/Admin/Updater/" class="btn btn-default">Go to Updater &raquo;</a></form>';
		} else {
			$r.= '<div class="alert alert-success" role="alert">All modules are currently up-to-date.<br>Last checked: ' . $lastcheck_text . '</div>';
			if ($lastcheck_text != 'Just Now') {
				$r.= '<form method="POST"><input type="submit" class="btn btn-default" name="BillicUpdateCheck" value="Check Now &raquo;"></form>';
			}
		}
		return array(
			'header' => 'Module Updates',
			'html' => $r,
		);
	}
}
