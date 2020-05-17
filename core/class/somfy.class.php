<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../php/somfy.inc.php';

class somfy extends eqLogic {
    /*     * *************************Attributs****************************** */



    /*     * ***********************Methode static*************************** */

    public static function dependancy_info($_refresh = false) {
		$return = array();
		$return['log'] = 'somfy_update';
		$return['progress_file'] = jeedom::getTmpFolder('somfy') . '/dependance';
		$return['state'] = (self::compilationOk()) ? 'ok' : 'nok';
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('somfy') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
	
	public static function compilationOk() {
		if (shell_exec('ls /usr/bin/node 2>/dev/null | wc -l') == 0) {
			return false;
		}
		return true;
	}
    
    public static function deamon_info() {
		$return = array();
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder('somfy') . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		$return['launchable'] = 'ok';
		return $return;
	}
	
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$gateway_path = dirname(__FILE__) . '/../../resources/somfy';
        $key = config::byKey('somfy::key', 'somfy');
        $secret = config::byKey('somfy::secret', 'somfy');

		$cmd = 'node ' . $gateway_path . '/index.js ';
		$cmd .= '8084 ';
        $cmd .= $key . ' ';
		$cmd .= $secret . ' ';
        $cmd .= $_SERVER['SERVER_ADDR'] . ' ';
		$cmd .= jeedom::getTmpFolder('somfy') . '/deamon.pid';
		
		log::add('somfy', 'info', 'Lancement démon somfy : ' . $cmd);
		exec($cmd . ' >> ' . log::getPathToLog('somfy') . ' 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('somfy', 'error', 'Impossible de lancer le démon somfy', 'unableStartDeamon');
			return false;
		}
		message::removeAll('somfy', 'unableStartDeamon');
		log::add('somfy', 'info', 'Démon somfy lancé');
	}
	
	public static function deamon_stop() {
		try {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				try {
					somfyRequest('/stop');
				} catch (Exception $e) {
					
				}
			}
			$pid_file = jeedom::getTmpFolder('somfy') . '/deamon.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
			sleep(1);
		} catch (\Exception $e) {
			
		}
	}
    
    public static function syncEqLogic() {
		log::add('somfy', 'debug', "syncEqLogic()");

		$sites = somfyRequest('allsites');
        foreach ($sites as $site) {
            $devices = somfyRequest('site/' . $site->id . '/device');
            foreach ($devices as $device) {
                log::add('somfy', 'debug', "id_device: " . $device->id);
                if ($device->available != true) {
                    log::add('somfy', 'debug', "Device " . $device->id . "not available");
                    continue;
                }
                $newEqLogic = eqLogic::byLogicalId($device->id, 'somfy');
                if (!is_object($newEqLogic)) {
                    $newEqLogic = new eqLogic();
                    $newEqLogic->setEqType_name('somfy');
                    $newEqLogic->setIsEnable(0);
                    $newEqLogic->setIsVisible(0);
                    $newEqLogic->setName($device->name);
                    $newEqLogic->setLogicalId($device->id);
                }
                if ($device->type == "roller_shutter_positionable_stateful_generic") {
                    $newEqLogic->setCategory('security', '1');
                    $newEqLogic->setCategory('automatism', '0');
                }
                else if ($device->type == "hub_tahoma_2") {
                    $newEqLogic->setCategory('automatism', '1');
                    $newEqLogic->setCategory('security', '0');
                }
                $newEqLogic->setConfiguration('site_id', $device->site_id);
                $newEqLogic->setConfiguration('parent_id', $device->parent_id);
                $newEqLogic->save();
                $refresh = $newEqLogic->getCmd(null, 'refresh');
                if (!is_object($refresh)) {
                    $refresh = new somfyCmd();
                }
                $refresh->setName('Rafraichir');
                $refresh->setEqLogic_id($newEqLogic->getId());
                $refresh->setLogicalId('refresh');
                $refresh->setType('action');
                $refresh->setSubType('other');
                $refresh->save();
                
                $refresh_last = $newEqLogic->getCmd(null, 'updatetime');
                if (!is_object($refresh_last)) {
                    $refresh_last = new somfyCmd();
                }
                $refresh_last->setName('Dernier refresh');
                $refresh_last->setEqLogic_id($newEqLogic->getId());
                $refresh_last->setLogicalId('updatetime');
                $refresh_last->setType('info');
                $refresh_last->setSubType('string');
                $refresh_last->save();

                /***********************************/
                //Infos
                foreach ($device->states as $state) {
                    $cmd = $newEqLogic->getCmd(null, $state->name);
                    if (!is_object($cmd)) {
                        $cmd = new somfyCmd();
                    }
                    $cmd->setName($state->name);
                    $cmd->setEqLogic_id($eqLogic->getId());
                    $cmd->setLogicalId($state->name);
                    $cmd->setType('info');
                    switch ($state->type) {
                        case "integer":
                            $cmd->setSubType('numeric');
                            break;
                        default:
                            $cmd->setSubType('other');
                            break;
                    }
                    switch ($state->name) {
                        case "position":
                            $cmd->setUnite('%');
                            $cmd->setDisplay('generic_type', 'FLAP_STATE');
                            break;
                        default:
                            break;
                    }
                    $cmd->save();
                    $cmd->setCollectDate('');
                    $value = $state->value;
                    if ($state->name == "position") {
                        $value = 100 - $value;
                    }
                    $cmd->event($value);
                }
                    
                /***********************************/
                //Actions
                foreach ($device->capabilities as $capacity) {
                    $cmd = $newEqLogic->getCmd(null, 'cmd' . $capacity->name);
                    if (!is_object($cmd)) {
                        $cmd = new somfyCmd();
                    }
                    $cmd->setName('cmd' . $capacity->name);
                    $cmd->setEqLogic_id($eqLogic->getId());
                    $cmd->setLogicalId('cmd' . $capacity->name);
                    $cmd->setType('action');
                    switch ($capacity->name) {
                        case "position":
                            $cmd->setConfiguration('minValue', '0');
                            $cmd->setConfiguration('maxValue', '100');
                            $cmd->setSubType('slider');
                            $cmd->setDisplay('generic_type', 'FLAP_SLIDER');
                            break;
                        case "close":
                            $cmd->setSubType('other');
                            $cmd->setDisplay('icon', '<i class="fa fa-arrow-down"></i>');
                            $cmd->setDisplay('generic_type', 'FLAP_DOWN');
                            break;
                        case "open":
                            $cmd->setSubType('other');
                            $cmd->setDisplay('icon', '<i class="fa fa-arrow-up"></i>');
                            $cmd->setDisplay('generic_type', 'FLAP_UP');
                            break;
                        case "stop":
                            $cmd->setSubType('other');
                            $cmd->setDisplay('icon', '<i class="fa fa-stop"></i>');
                            break;
                        case "open":
                            $cmd->setSubType('other');
                            $cmd->setDisplay('icon', '<i class="fa fa-arrow-up"></i>');
                            $cmd->setDisplay('generic_type', 'FLAP_UP');
                            break;
                        default:
                            $cmd->setSubType('other');
                            break;
                    }
                    $cmd->save();
                }
                    
                /***********************************/
                //Box
                if ($device->type == "hub_tahoma_2") {
                    $cmd = $newEqLogic->getCmd(null, "version");
                    if (!is_object($cmd)) {
                        $cmd = new somfyCmd();
                    }
                    $cmd->setName("version");
                    $cmd->setEqLogic_id($eqLogic->getId());
                    $cmd->setLogicalId("version");
                    $cmd->setType('info');
                    $cmd->setSubType('string');
                    $cmd->save();
                    $cmd->setCollectDate('');
                    $cmd->event($device->version);
                }
            }
        }
    }
    
    public static function pull() {
        $sites = somfyRequest('allsites');
        foreach ($sites as $site) {
            $devices = somfyRequest('site/' . $site->id . '/device');
            foreach ($devices as $device) {
                log::add('somfy', 'debug', "id_device: " . $device->id);
                if ($device->available != true) {
                    log::add('somfy', 'debug', "Device " . $device->id . "not available");
                    continue;
                }
                $eqLogic = eqLogic::byLogicalId($device->id, 'somfy');
                if (is_object($eqLogic)) {
                    log::add('somfy', 'debug', "Update data from device " . $device->id);
                    $eqLogic->updateData($device);
                }
            }
        }
	}

    /*     * *********************Méthodes d'instance************************* */

    public function refresh() {
        if ($this->getIsEnable()) {
            $device = somfyRequest('device/' . $this->getLogicalId());
            if ($device->available != true) {
                log::add('somfy', 'debug', "Device " . $device->id . "not available");
                return false;
            }
            log::add('somfy', 'debug', "Update data from device " . $device->id);
            $this->updateData($device);
            return true;
        }
    }
    
    public function updateData($device) {
        foreach ($device->states as $state) {
            $cmd = $this->getCmd(null, $state->name);
            if (is_object($cmd)) {
                $cmd->setCollectDate('');
                $value = $state->value;
                if ($state->name == "position") {
                    $value = 100 - $value;
                }
                $cmd->event($value);
                switch ($state->name) {
                    case 'position':
                        $cmdposition = $this->getCmd(null, "cmdposition");
                        if (is_object($cmdposition)) {
                            $cmdposition->setConfiguration('lastCmdValue', $value);
                            $cmdposition->save();
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        $refresh = $this->getCmd(null, 'updatetime');
        if (is_object($refresh)) {
            $refresh->event(date("d/m/Y H:i",(time())));
        }

        $mc = cache::byKey('somfyWidgetmobile' . $this->getId());
        $mc->remove();
        $mc = cache::byKey('somfyWidgetdashboard' . $this->getId());
        $mc->remove();
        $this->toHtml('mobile');
        $this->toHtml('dashboard');
        $this->refreshWidget();
    }
    
    public function cmdposition($value) {
        $newValue = 100 - $value;
        $device_id = $this->getLogicalId();
        $data = array("nameCommand" => "position", "nameParameter" => "position", "valueParameter" => $newValue);
        $device_data = somfyRequest('device/' . $device_id . '/exec', $data);
        if (property_exists($device_data, 'job_id')) {
            $cmd = $this->getCmd(null, "position");
            if (is_object($cmd)) {
                $cmd->setCollectDate('');
                $cmd->event($value);
            }
            $this->refreshWidget();
            return true;
        }
        return false;
    }
    
    public function cmdclose() {
        $device_id = $this->getLogicalId();
        $data = array("nameCommand" => "close");
        $device_data = somfyRequest('device/' . $device_id . '/exec', $data);
        if (property_exists($device_data, 'job_id')) {
            return true;
        }
        return false;
    }
    
    public function cmdopen() {
        $device_id = $this->getLogicalId();
        $data = array("nameCommand" => "open");
        $device_data = somfyRequest('device/' . $device_id . '/exec', $data);
        if (property_exists($device_data, 'job_id')) {
            return true;
        }
        return false;
    }
    
    public function cmdstop() {
        $device_id = $this->getLogicalId();
        $data = array("nameCommand" => "stop");
        $device_data = somfyRequest('device/' . $device_id . '/exec', $data);
        if (property_exists($device_data, 'job_id')) {
            return true;
        }
        return false;
    }
    
    public function cmdidentify() {
        $device_id = $this->getLogicalId();
        $data = array("nameCommand" => "identify");
        $device_data = somfyRequest('device/' . $device_id . '/exec', $data);
        if (property_exists($device_data, 'job_id')) {
            return true;
        }
        return false;
    }

    /*     * **********************Getteur Setteur*************************** */
}

class somfyCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
		log::add('somfy','debug','get '.$this->getLogicalId());
		switch ($this->getLogicalId()) {
            case "refresh":
                return $eqLogic->refresh();
            case "cmdposition":
                return $eqLogic->cmdposition($_options['slider']);
            case "cmdclose":
                return $eqLogic->cmdclose();
            case "cmdopen":
                return $eqLogic->cmdopen();
            case "cmdstop":
                return $eqLogic->cmdstop();
            case "cmdidentify":
                return $eqLogic->cmdidentify();
            default:
                return false;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}


