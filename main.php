<?php
//Your Variables go here: $GLOBALS['mysql']['YourVariableName'] = YourVariableValue
class mysql{
    public static function command($line):void{
        $lines = explode(" ",$line);
        if($lines[0] === "server"){
            if($lines[1] === "create"){
                self::new_server($lines[2],$lines[3]);
            }
        }
    }//Run when base command is class name, $line is anything after base command (string). e.g. > [base command] [$line]
    public static function start(int $serverNumber = 1){
        if(is_admin::check()){
            $serverInfo = settings::read("servers/" . $serverNumber);
            return service_manager::start_service($serverInfo['serviceName']);
        }
        return false;
    }
    public static function stop(int $serverNumber = 1){
        if(is_admin::check()){
            $serverInfo = settings::read("servers/" . $serverNumber);
            return service_manager::stop_service($serverInfo['serviceName']);
        }
        return false;
    }
    public static function new_server($mysqldExecutable,$name,$specifyIniFile = false):int|bool{
        if($specifyIniFile === false){
            $iniFile = files::getFileDir($mysqldExecutable) . "\\my.ini";
        }
        else{
            $iniFile = $specifyIniFile;
        }
        $mysqlExecutable = str_replace("/","\\",$mysqldExecutable);
        $iniFile = str_replace("/","\\",$iniFile);
        if(is_file($mysqlExecutable) && is_file($iniFile) && is_admin::check()){
            $serverNumber = 1;
            while(settings::isset("servers/" . $serverNumber)){
                $serverNumber ++;
            }
            $serverInfo = array(
                "serviceName"      => "php_cli_mysql_server_" . $serverNumber,
                "name"             => $name,
                "specifiedIniFile" => $specifyIniFile,
                "iniFile"          => $iniFile,
                "executable"       => $mysqlExecutable
            );
            settings::set("servers/" . $serverNumber,$serverInfo);
            cmd::run('"' . $mysqlExecutable . '" --install ' . $serverInfo['serviceName'] . ' --defaults-file="' . $iniFile . '"',false,false);
            return $serverNumber;
        }
        return false;
    }
    public static function delete_server(int $serverNumber):bool{
        if(is_admin::check()){
            $serverInfo = settings::read("servers/" . $serverNumber);
            service_manager::stop_service($serverInfo['serviceName']);
            settings::unset("servers/" . $serverNumber);
            return cmd::run("sc delete " . $serverInfo['serviceName'],true,false);
        }
        else{
            mklog('warning','Unable to delete mysql server ' . $serverNumber . ', administrator permissions required',false);
        }
        return false;
    }
    public static function init():void{
        if(settings::isset("servers")){
            $servers = settings::read("servers");
            foreach($servers as $serverNumber => $serverData){
                if(isset($serverData['autostart'])){
                    if($serverData['autostart'] === true){
                        self::start($serverNumber);
                    }
                }
                else{
                    settings::set("servers/" . $serverNumber . "/autostart",false);
                }
            }
        }
        extensions::ensure('mysqli');
    }//Run at startup
    public static function set_autostart(int $serverNumber, bool $autostart){
        if(settings::isset("servers/" . $serverNumber)){
            settings::set("servers/" . $serverNumber . "/autostart",$autostart);
        }
    }
    public static function newConnection(string $hostname, string $username, string|bool $password, int $port):int|bool{
        $connectionNumber = 1;
        while(mysql_client::connectionNumberExists($connectionNumber)){
            $connectionNumber ++;
        }
        $connData = array();
        $connData["hostname"] = $hostname;
        $connData["username"] = $username;
        if($password === false){
            $connData["password"] = false;
        }
        else{
            $connData["password"] = base64_encode($password);
        }
        $connData["port"] = $port;
        if(settings::set("connections/". $connectionNumber, $connData)){
            return $connectionNumber;
        }
        else{
            return false;
        }
    }
}