<?php

/**
 * Watching Model Class
 * @package YetiForce.View
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Vtiger_Watchdog_Model extends Vtiger_Base_Model
{

	public $notificationDefaultType = 1;

	/**
	 * Function to get the instance by id
	 * @return <Home_Notification_Model>
	 */
	public static function getInstanceById($record, $moduleName)
	{
		if ($instance = Vtiger_Cache::get('WatchdogModel', $moduleName . $record)) {
			return $instance;
		}
		$modelClassName = Vtiger_Loader::getComponentClassName('Model', 'Watchdog', $moduleName);
		$instance = new $modelClassName();
		$instance->set('record', $record);
		$instance->set('module', $moduleName);

		Vtiger_Cache::set('WatchdogModel', $moduleName . $record, $instance);
		return $instance;
	}

	/**
	 * Function to get the instance by module
	 * @return <Home_Notification_Model>
	 */
	public static function getInstance($moduleName)
	{
		if ($instance = Vtiger_Cache::get('WatchdogModel', $moduleName)) {
			return $instance;
		}
		$modelClassName = Vtiger_Loader::getComponentClassName('Model', 'Watchdog', $moduleName);
		$instance = new $modelClassName();
		$instance->set('module', $moduleName);

		Vtiger_Cache::set('WatchdogModel', $moduleName, $instance);
		return $instance;
	}

	public function isWatchingModule($userId = false)
	{
		if ($this->has('isWatchingModule')) {
			return $this->get('isWatchingModule');
		}
		$return = false;
		
		$modules = self::getWatchingModules(false, $userId);
		if (in_array(Vtiger_Functions::getModuleId($this->get('module')), $modules)) {
			$return = true;
		}
		$this->set('isWatchingModule', $return);
		return $return;
	}

	public function isWatchingRecord($userId = false)
	{
		if ($this->has('isWatchingRecord')) {
			return $this->get('isWatchingRecord');
		}
		$return = $this->isWatchingModule($userId);
		$db = PearDatabase::getInstance();
		if ($userId === false) {
			$userId = Users_Privileges_Model::getCurrentUserPrivilegesModel()->getId();
		}
		$sql = 'SELECT state FROM u_yf_watchdog_record WHERE userid = ? AND record = ?';
		$result = $db->pquery($sql, [$userId, $this->get('record')]);
		$count = $db->getRowCount($result);
		$this->set('isRecord', $count);
		if ($count) {
			if ($db->getSingleValue($result) == 1) {
				$return = true;
			} else {
				$return = false;
			}
		}
		$this->set('isWatchingRecord', $return);
		return $return;
	}

	public static function getWatchingModules($reload = false, $ownerId = false)
	{
		if ($ownerId === false) {
			$ownerId = Users_Privileges_Model::getCurrentUserPrivilegesModel()->getId();
		}
		$modules = Vtiger_Cache::get('getWatchingModules', $ownerId);
		if (!$reload && $modules !== false) {
			return $modules;
		}
		$db = PearDatabase::getInstance();
		$sql = 'SELECT * FROM u_yf_watchdog_module WHERE userid = ?';
		$result = $db->pquery($sql, [$ownerId]);
		$modules = [];
		while ($row = $db->getRow($result)) {
			$modules[] = $row['module'];
		}
		Vtiger_Cache::set('getWatchingModules', $ownerId, $modules);
		return $modules;
	}

	public function changeRecordState($state, $ownerId = false)
	{
		$isWatchingRecord = $this->isWatchingRecord($ownerId);
		if ($isWatchingRecord && $state == 1) {
			return true;
		}
		if($ownerId == false){
			$ownerId = Users_Privileges_Model::getCurrentUserPrivilegesModel()->getId();
		}
		$db = PearDatabase::getInstance();
		
		$row = ['state' => $state];
		if ($this->get('isRecord') == 0) {
			$row['userid'] = $ownerId;
			$row['record'] = $this->get('record');
			$db->insert('u_yf_watchdog_record', $row);
		} else {
		
			$db->update('u_yf_watchdog_record', $row, 'userid = ? AND record = ?', [$ownerId, $this->get('record')]);
		}
	}

	public function changeModuleState($state, $ownerId = false)
	{
		$isWatchingRecord = $this->isWatchingModule($ownerId);
		if ($isWatchingRecord && $state == 1) {
			return true;
		}
		if ($ownerId === false) {
			$ownerId = Users_Privileges_Model::getCurrentUserPrivilegesModel()->getId();
		}
		$db = PearDatabase::getInstance();
		$moduleId = Vtiger_Functions::getModuleId($this->get('module'));
		if ($state == 1) {
			$db->insert('u_yf_watchdog_module', [
				'userid' => $ownerId,
				'module' => $moduleId
			]);
		} else {
			$db->delete('u_yf_watchdog_module', 'userid = ? AND module = ?', [$ownerId, $moduleId]);
		}
	}

	public function getWatchingUsers()
	{
		$users = [];
		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT userid FROM u_yf_watchdog_module WHERE module = ?', [Vtiger_Functions::getModuleId($this->get('module'))]);
		while (($userId = $db->getSingleValue($result)) !== false) {
			$users[$userId] = $userId;
		}
		if ($this->has('record')) {
			$result = $db->pquery('SELECT * FROM u_yf_watchdog_record WHERE record = ?', [$this->get('record')]);
			while ($row = $db->getRow($result)) {
				if ($row['state'] == 1) {
					$users[$row['userid']] = $row['userid'];
				} else {
					unset($users[$row['userid']]);
				}
			}
		}
		return $users;
	}
}
