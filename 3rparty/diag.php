<?php

function somfyRequest($url, $data = null) {
	log::add('somfy', 'debug', "url: " . $url);

    //Initialize cURL.
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8084/" . $url);

    //Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($data !== null) {
        log::add('somfy', 'debug', "data: " . json_encode($data));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    }

    //Execute the request.
    $response = curl_exec($ch);

    //Close the cURL handle.
    curl_close($ch);

    log::add('somfy', 'debug', "response: " . $response);
    return json_decode($response);
}

?>
