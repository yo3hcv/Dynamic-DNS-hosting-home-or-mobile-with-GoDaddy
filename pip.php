#!/usr/bin/php
<?php
//----------------------------------------------------------------------------------------------------------------------
// Dynamic DNS with GoDaddy
// Copyright (c)2018 YO3HCV, Edouard Gora
//
// License: WYL
// It means that you can do Whatever You Like with this script. 
// You can use as your school homework, tell your boss how smart you are and how hard you work for it, I don't care. 
// However, I am not responsible in any way for any stupid things you may do with it.
// If you like it and find it useful, please consider to donate in my PayPal account.   
//
// Make this script executable
// chmod +x pip.php
//
// CRONTAB
// -------------------------------------------------------------------------   
// crontab -e	edit
// crontab -l	view
//
//    
// +---------------- minute (0 - 59)
// |  +------------- hour (0 - 23)
// |  |  +---------- day of month (1 - 31)
// |  |  |  +------- month (1 - 12)
// |  |  |  |  +---- day of week (0 - 6) (Sunday=0 or 7)
// |  |  |  |  |
// *  *  *  *  *  command to be executed    
//
// Every 10 minutes    
// * /10 * * * * /root/pip.php           <-- no space between * and /
//
// Every minute (are you fucking crazy?)    
// * * * * * /root/pip.php
//
// View executed crons
// Apr  7 12:05:01  CRON[26475]: (root) CMD (/root/pip.php)
// Apr  7 12:06:01  CRON[26564]: (root) CMD (/root/pip.php)
//
// ~# grep -i CRON /var/log/syslog 
//
// Continuously monitor for debug
//
// tail -f /var/log/syslog | grep -i CRON   
//----------------------------------------------------------------------------------------------------------------------

require "/home/PHPMailer/PHPMailerAutoload.php";	// where is PHPMailer  

$reverse = true; // use reverse DNS lookup (better)

$mail = new PHPMailer;

$mail->SMTPDebug = 1; // Enable verbose debug output
$mail->isSMTP(); // Set mailer to use SMTP

$mail->Host = "smtp.gmail.com";
$mail->SMTPAuth = true;       
$mail->Username = "my_nice_email@gmail.com";
$mail->Password = "my_nice_password";

$mail->SMTPSecure = "tls"; // Enable TLS encryption, SSL is also accepted
$mail->Port = 587; // TCP port to connect to
$mail->setFrom("my_nice_email@gmail.com", "My Server");	// FROM

// TO (copy paste this line for multiple destinations)
$mail->addAddress("my_email_for_receiving_alerts@gmail.com");

$servers = array // , 
  (
    "http://checkip.dyndns.org",   // REMOTE_X_FORWARDED_FOR
    "http://checkip.feste-ip.net",   // REMOTE_ADDR
    "http://www.icanhazip.com",   // REMOTE_ADDR
    "http://checkip.amazonaws.com",  // REMOTE_X_FORWARDED_FOR
    "https://ipinfo.info", // unknown
	//"https://ipinfo.io/json", // unknown, JSON format
  );

// GoDaddy settings
// Create a PRODUCTION KEY here
// https://developer.godaddy.com/keys
//
// Verify DNS records like this
// https://api.godaddy.com/v1/domains/<DOMAIN>/records/A/<RECORD_NAME>
// 
// Issues:
// Call to undefined function curl_init()
// sudo apt-get install php-curl
  
$url = "https://api.godaddy.com/v1/domains/";
$domain="my_nice_domain.com";   // your domain name
$key="fddshsdkjhsKGcksauydgsadgfkcbvsadf";
$secret="lsakfdjgiuhdfgdshb";
  
//----------------------------------------------------------------------------------------------------------------------
// Functions
//----------------------------------------------------------------------------------------------------------------------
function getDNSRecord($type, $name, &$ret){
    global $url, $domain, $key, $secret;
    
    $headers = array(
        "Content-Type: application/json",
        "Authorization: sso-key $key:$secret"
        );
    $ch = curl_init();
	
    curl_setopt($ch, CURLOPT_URL, $url."$domain/records/$type/$name");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
	$ret = curl_exec($ch);          
    
	//var_dump($ret);
	
	if (!curl_errno($ch))
	  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	
	curl_close($ch);
	
	return $http_code;
}  
  
function updateDNSRecord($type, $name, $data, $ttl, &$ret, $add = false){
    global $url, $domain, $key, $secret;

    $headers = array(
        "Content-Type: application/json",
        "Authorization: sso-key $key:$secret"
        );
    $ch = curl_init();
    
	if($add)
	{
		$data = '[{"type":"'.$type.'","name":"'.$name.'","data":"'.$data.'","ttl":'.$ttl.'}]';
		curl_setopt($ch, CURLOPT_URL, $url."$domain/records");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');		// add
	}
	else
	{
		$data = '[{"data":"'.$data.'","ttl":'.$ttl.'}]';
		curl_setopt($ch, CURLOPT_URL, $url."$domain/records/$type/$name");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // update existing		
	}

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
	$ret = curl_exec($ch);          
    
	if (!curl_errno($ch))
	  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	curl_close($ch);
	
	return $http_code;
}    
  
function SendMail($subject, $body)
{
	global $mail;
	
	/* Send email */
	$mail->isHTML(false);
	$mail->Subject = $subject;
	$mail->Body = $body;

	if(!$mail->send())
		echo "Message could not be sent.\r\nMailer Error: " . $mail->ErrorInfo ."\r\n";
	else
		echo "Message has been sent\r\n";

}	
  
//----------------------------------------------------------------------------------------------------------------------
// Verify with all servers 
//----------------------------------------------------------------------------------------------------------------------
$dom = new domDocument; 
libxml_use_internal_errors(true); // suppress some warnings

$content = "\r\nChecking server IP at ". date("d-m-Y H:i:s"). " \r\n\r\nTrying all servers "
.($reverse? "with reverse DNS...":"...")."\r\n\r\n";

for($i=0; $i < sizeof($servers); $i++)
{
    $ip = "";
    
    switch($i)
    {
        // html style 1 :)
        case 0:
        case 1:
            
            @$dom->loadhtmlfile($servers[$i]);
        
            $obj = $dom->getElementsByTagName("body");
            if($obj->item(0))
            {
                $ip = $obj->item(0)->nodeValue;
                $ip = trim(str_replace("Current IP Address:","",$ip));
            }       
            break;
        
        // text
        case 2:
        case 3:
            @$ip = trim(file_get_contents($servers[$i])); 
            
            break;
        
        //https://ipinfo.info/
        case 4:
            @$dom->loadhtmlfile($servers[$i]);
            $objs = $dom->getElementsByTagName("a");

            $query = "http://www.dnsstuff.com/tools/whois.ch?ip=";
            foreach($objs as $obj)
            {
                    $attr = $obj->getAttribute("href");
                   
                    if(substr($attr, 0, strlen($query)) === $query)  // attr begins with query
                    {
                        @$ip = trim(str_replace($query,"",$attr));
                        break;   
                    }
            
            }
            
            break;
            
    }// sw

    $content .= "$servers[$i] ";
    
    //if($i==3) $ip = "gdsfgdsfgds"; // debug
    //$ip = "gdsfgdsfgds"; // debug
    
    if (filter_var($ip, FILTER_VALIDATE_IP)) // valid IP ?
    {
        //if($i==2) $ip = "1.2.3.4"; // debug
        //if($i==3) $ip = "1.2.3.4"; // debug
        //if($i==4) $ip = "1.2.3.4"; // debug
        
		if($reverse)
		{
			// let'd do a reverse DNS also, our provider format is XXX-XXX-XXX-XXX.home.provider
			$xip = str_replace(".","-",$ip);
			$local_domain = gethostbyaddr($ip); // do a reverse lookup
		
			if(strpos($local_domain, $xip) !== false) // if our IP is included in local domain string
			{
			
				$count = isset($data[$ip])? $data[$ip] : 0;
				$data[$ip] = ++$count;
				$content .= $ip;
			}
			else
				$content .= "-> $ip (invalid reverse DNS)";
		}
		else
		{
				$count = isset($data[$ip])? $data[$ip] : 0;
				$data[$ip] = ++$count;
				$content .= $ip;
		}
    }
    else
        $content .= "-> n/a";
    
    $content .= "\r\n";

}

$content .= "\r\n";

//----------------------------------------------------------------------------------------------------------------------
// Do we have consistent results?
//----------------------------------------------------------------------------------------------------------------------

if(!isset($data))
    die($content ."Ooopsss... do we have internet conection?\r\n");

arsort($data); // sort by value, descending

//print_r($data);
//echo "Count: ".sizeof($data).PHP_EOL;

foreach ($data as $ip => $count)
{
	$score = round($count/count($servers),1)*100;
	
    if($score == 100)
    {
        $content .= "Nice";

        break;
    }
    else if($score >= 0.8) //
    {
        $content .= "Acceptable";

        break;   
    }
    else if($score >= 0.6) //
    {
        $content .= "Possible";

        break;   
    }    
    else if($score >= 0.4) //
    {
        $content .= "Tiny";

        break;   
    }  	
    else //
    {
        $content .= "Not sure";

        break;   
    }  	
}

$content .= ", $score% identified, IP: $ip (http://$ip)".($reverse?"\r\nReverse DNS: $local_domain":"")."\r\n\r\n";

//----------------------------------------------------------------------------------------------------------------------
// Check against remote server
//----------------------------------------------------------------------------------------------------------------------

$ret='';
$http_code = getDNSRecord("A","@",$ret); // check A@ record at GoDaddy

//$http_code = 400;

if($http_code != 200) // API error
{
	$content .= "Cannot retrieve $domain IP from GoDaddy.\r\nhttps://account.godaddy.com\r\n\r\n";
	$content .= "Response: $http_code\r\n".$ret."\r\n";
	
	$subject = "IP status check report";
}
else if($http_code == 200)
{
    //print_r($ret);
    
	$json = str_replace("[","",$ret); // ugly...
    $json = str_replace("]","",$json);
    
    //print_r($json);
    
	$json = json_decode($json,true);
	
    
    print_r($json);
    
	if(!(isset($json["data"]) && filter_var($json["data"], FILTER_VALIDATE_IP)))
	{
		// we have 200 but empty or invalid IP
		$content .= "The domain $domain record A/@ is invalid.\r\nTrying to fix this with $ip... ";
		
		$ret = '';
		$http_code = updateDNSRecord("A","@",$ip, 600, $ret, true);
		
		//$http_code = 400;
		if($http_code != 200)
		{
			$content .= "Failed, please try manually.\r\nhttps://account.godaddy.com\r\n\r\n";
			$content .= "Response: $http_code\r\n".$ret."\r\n";
			
			$subject = "The domain $domain has invalid A/@ records!";
		}
		else
		{
			$content .= "OK\r\n\r\nThe domain $domain IP was changed to $ip";
			$subject = "The domain $domain IP was changed to $ip";
		}
	}
	else // valid JSON, valid IP
	{
		if($json["data"] == $ip) // already the same IP
		{
			$content .= "The domain $domain IP matches local sever IP.\r\n\r\n";
			echo $content ."\r\n";
			die();
		}
		else
		{
			// Different IP, Do something for God say!
			
			$content .= "The domain $domain IP ".$json["data"]." is different than local IP $ip.\r\n\r\n";
			
			if($score >= 0.4)
			{
			
				$ret = '';
				$http_code = updateDNSRecord("A","@",$ip, 600, $ret, false);
				
				if($http_code != 200)
				{
					$content .= "Failed to update $domain IP, please try manually.\r\nhttps://account.godaddy.com\r\n\r\n";
					$content .= $ret."\r\n";
					
					$subject = "Failed to update $domain IP!";
				}
				else
				{
					$content .= "The domain $domain IP was changed to $ip";
					$subject = "The domain $domain IP was changed to $ip";
				}
			}
			else
			{
				$content .= "The score is too low for automatic update, please try manually.\r\nhttps://account.godaddy.com\r\n";
				$subject = "The domain $domain IP require update!";
			}
		}
	} // valid JSON, valid IP
}// 200


echo $content ."\r\n";
SendMail($subject,$content);
 



?>