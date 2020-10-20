<?php

    define("MAILMAN_FILE_UPLOAD_DIRECTORY", "/root/");

    getMembers();
    
    function getMembers()
    {
        //url to api
        $url = "REDACTED";
        
        
        //Data array of post fields that includes the username and password that will be checked in the rest server for the feed
        //An error will be returned if any parameter is missing a value

        $data = "method=" . base64_encode("getAllMembers") . 
                "&username=" . base64_encode("REDACTED") . 
                "&password=" . base64_encode("REDACTED").
                "&encodeOutput=" . base64_encode(TRUE);
        
        // Set up curl options
        
        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
        //json array returned.
        $result = json_decode(curl_exec($ch), true);

        // Get the array of events
        $results = $result["Response"]["data"];

        //if data returned it is in the array with the following details
        $emails = array();
        
        if(is_array($results) && !empty($results))
        {
            foreach($results as $item)
            {
                //Process the results here
                if ($item["EmailMemberAllowed"])
                {
                        $emails[] = $item["Email"];
                }
            }
            // If the result from socs portal is an array and has got this far, we'll assume we have emails to sync.
            // Maybe just check theres a good few emails to sync (should be over 1000)
            if (count($emails) > 500) mailmanSync("compsoc-announce@lists.compsoc.ie", $emails);
        } else {
            var_dump("Gil: Mailman 3 sync in a bitta bother");
            var_dump($result);
        }


        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $emails;
    }

    function mailmanSync($listname, $emails)
    {
        $filePath = mailmanArrayToFile($emails);
        exec("cd /opt/mailman && /opt/mailman/venv/bin/mailman members -s " . $filePath . " " . $listname, $res);
        //exec("cd /opt/mailman && /opt/mailman/venv/bin/mailman syncmembers -W -G -n " . $filePath . " " . $listname, $res);
        unlink($filePath);
        return $res;
    }

    // Convert email array to file with email per line
    function mailmanArrayToFile($emails)
    {
        $filePath = MAILMAN_FILE_UPLOAD_DIRECTORY . "emails-" . date("His") . ".txt";
        $file = fopen($filePath, "w");
        $str = "";

        foreach ($emails as $email)
        {
            $str .= $email . "\n";
        }

        fwrite($file, $str);
        fclose($file);

        return $filePath;
    }
?>
