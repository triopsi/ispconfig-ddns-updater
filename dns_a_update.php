<?php

################################################ Config ###############################################################
#Logger
$fileobj = fopen ("dyndnslog.log", 'a');
$jsonreturn= array();

#IDs of ID on ISPConfig A Records will be changed. E.g. 12
$dns_ids=[ '12' ];

#Username und Passwort vom Remote User - ISPConfig
$username_ispconfig = 'remote_dns';
$password_ispconfig = 'PASSWORD';

#Username und passwort des Fritzbox Users
$username_fritzbox= "fritzboxuser";
$password_fritzbox = "PASWORD";

#URL der API
$soap_location = 'https://example.com/remote/index.php'; //SOAP URL ISPConfig
$soap_uri = 'https://example.com/remote/'; //SOAP URL ISPConfig

####################################################### MAIN ############################################################
if(empty($dns_ids) || ! is_array($dns_ids) || count($dns_ids) == 0 ){
    die;
}
$user_name_fritzbox = filter_input(INPUT_GET, 'username'); //$_GET['username'];
$user_password_fritzbox = filter_input(INPUT_GET, 'password');//$_GET['password'];
$user_new_ip_fritzbox = filter_input(INPUT_GET, 'ipaddr', FILTER_VALIDATE_IP);//$_GET['ipaddr'];

$jsonreturn[]=[
    "HTTP_REFERER" => $_SERVER['HTTP_REFERER'],
    "REQUEST_URI" => $_SERVER['REQUEST_URI'],
    "user_name_fritzbox" => $user_name_fritzbox,
    "user_password_fritzbox"=> $user_password_fritzbox,
    "user_new_ip_fritzbox"=> $user_new_ip_fritzbox,
];

if ($user_name_fritzbox && $user_password_fritzbox && $user_new_ip_fritzbox){

    if ($user_name_fritzbox == $username_fritzbox && $user_password_fritzbox == $password_fritzbox){
        $context = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            )
        ));
        $client = new SoapClient(null, array('location' => $soap_location,
                'uri'      => $soap_uri,
                'trace' => 1,
                'exceptions' => 1,
                'stream_context' => $context));
        try {
            if(!$session_id = $client->login($username_ispconfig, $password_ispconfig)) {
                $jsonreturn[]=[
                    "IP" => $_SERVER['REMOTE_ADDR'],
                    "Server" => $_SERVER['HTTP_HOST'],
                    "Agent" => $_SERVER['HTTP_USER_AGENT'],
                    "nachricht"=>"Fail Login"
                ];
            }

            foreach($dns_ids as $dns_id){

                //get dns data
                $dns_record_1 = $client->dns_a_get($session_id, $dns_id);

                //change the IP Adress
                $dns_record_1['data'] = $user_new_ip_fritzbox;

                //update dns a record
                $affected_rows_1 = $client->dns_a_update($session_id, $client_id, $dns_id, $dns_record_1);

                if($affected_rows_1){
                    $jsonreturn[]=[
                        "IP" => $_SERVER['REMOTE_ADDR'],
                        "Server" => $_SERVER['HTTP_HOST'],
                        "Agent" => $_SERVER['HTTP_USER_AGENT'],
                        "Domain" => $dns_record_1['name'],
                        "nachricht"=>"Number of records that have been changed in the database: ".$affected_rows_1
                    ];
                }else{
                    $jsonreturn[]=[
                        "IP" => $_SERVER['REMOTE_ADDR'],
                        "Server" => $_SERVER['HTTP_HOST'],
                        "Agent" => $_SERVER['HTTP_USER_AGENT'],
                        "Domain" => $dns_record_1['name'],
                        "nachricht"=>"Intern Error - $affected_rows_1"
                    ];
                }
            }

            if(!$client->logout($session_id)) {
                $jsonreturn[]=[
                    "IP" => $_SERVER['REMOTE_ADDR'],
                    "Server" => $_SERVER['HTTP_HOST'],
                    "Agent" => $_SERVER['HTTP_USER_AGENT'],
                    "nachricht"=>"Fail Logout"
                ];
            }

        } catch (SoapFault $e) {
            echo $client->__getLastResponse();
            die('SOAP Error: '.$e->getMessage());
        }

    }else{
        $jsonreturn[]=[
            "IP" => $_SERVER['REMOTE_ADDR'],
            "Server" => $_SERVER['HTTP_HOST'],
            "Agent" => $_SERVER['HTTP_USER_AGENT'],
            "nachricht"=>"Error"
        ];
    }

}else{
    $jsonreturn[]=[
        "IP" => $_SERVER['REMOTE_ADDR'],
        "Server" => $_SERVER['HTTP_HOST'],
        "Agent" => $_SERVER['HTTP_USER_AGENT'],
        "nachricht"=>"Not Allow"
    ];
}
echo json_encode('OK');

//Loggen
fwrite ($fileobj, date("Y-m-d H:i:s").": ".json_encode($jsonreturn)."\n");
fclose ($fileobj);
