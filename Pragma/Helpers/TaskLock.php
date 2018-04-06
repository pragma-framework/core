<?php
namespace Pragma\Helpers;

class TaskLock {
	public static function check_lock($path, $lock_name){
		if( ! file_exists($path) ){;
			$oldumask = umask(0);
			mkdir($path, 0775, true);
			umask($oldumask);
		}
		$file = $path . (substr($path, -1) == '/' ? $lock_name : '/'.$lock_name);

		if(file_exists($file)){
			$pid = file_get_contents($file);
			$pids = explode(PHP_EOL, `ps -e | awk '{print $1}'`);
			if(in_array($pid, $pids)){
				echo "\n Task `$lock_name` is still in progress... \n\n";@ob_flush();
				exit();
			}else{
				echo "\n Task `$lock_name` did not complete successfully. Task restart ! \n\n";@ob_flush();
				unlink($file);
			}
		}
		file_put_contents($file, getmypid());
	}

	public static function flush($path, $lock_name){
		$file = $path . (substr($path, -1) == '/' ? $lock_name : '/'.$lock_name);
		if(file_exists($file)){
			unlink($file);
		}
	}
}
