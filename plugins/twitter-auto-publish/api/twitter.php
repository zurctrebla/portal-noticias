<?php
///////////////////// Reauth/////////////////////
if(!function_exists('xyz_twap_twitter_auth2_reauth'))
{
 function xyz_twap_twitter_auth2_reauth() {
    $client_id = get_option('xyz_twap_client_id');
    $client_secret = get_option('xyz_twap_client_secret');
    $refresh_token = get_option('xyz_twap_tw_refresh_token');
    $current_time=time();
    $auth_header = base64_encode("$client_id:$client_secret");

    $response = wp_remote_post(XYZ_TWAP_API_OAUTH2_URL."oauth2/token", [
        'headers' => [
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . $auth_header,
        ],
        'body' => [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
        ],
        'timeout' => 15,
        'sslverify' => get_option('xyz_twap_peer_verification') == '1',
    ]);
    if (is_wp_error($response)) {

            return [
            'status'  => 'error',
            'message' => 'WP_Error: ' . $response->get_error_message(),
            'code'    => $response->get_error_code() ?: 0,
        ];
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code == 200 && isset($data['access_token'])) {
        $access_token = $data['access_token'];
        $refresh_token = $data['refresh_token'];
            update_option('xyz_twap_tw_token', $access_token);
            update_option('xyz_twap_tw_refresh_token', $refresh_token);
            update_option('xyz_twap_last_auth_time', $current_time);
            update_option('xyz_twap_tw_af', 0);
        }
    else {
        $error_msg = isset($data['error_description']) ? $data['error_description'] : "Unknown error occurred.";
        $error_msg.='Try reauthorize';
        return [
            'status'  => 'error',
            'message' => $error_msg,
            'code'    => $status_code,
        ];
    }
}
}
/////////////Text post///////////////
if(!function_exists('xyz_twap_post_to_twitter'))
{
    function xyz_twap_post_to_twitter($bearer_token,$message) {
        $url = XYZ_TWAP_API_OAUTH2_URL."tweets";
        $headers = array(
            'Authorization' => 'Bearer ' . $bearer_token,
            'Content-Type'  => 'application/json',
        );
        $body = json_encode(array(
            'text' => $message,
        ));
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body'    => $body,
            'method'  => 'POST',
            'timeout' => 20,
            'sslverify' => get_option('xyz_twap_peer_verification') == '1',
        ));
        if (is_wp_error($response)) {
            return [
                'status'  => 'error',
                'message' => 'WP_Error: ' . $response->get_error_message(),
                'code'    => 0,
            ];
        }
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        $http_code = $response['response']['code'];
        $http_message = $response['response']['message'];
        if ($http_code === 201 && !empty($response_data)) {
            $tweet_id = $response_data['data']['id'] ?? null;
            $tweet_text = $response_data['data']['text'] ?? '';  
        return [
            'status' => 'success',
            'data'   => [
                        'id'   => $tweet_id,
                    ],
            'code'   => $http_code,
        ];
        }
        // Handle all other cases as errors
        return [
        'status'  => 'error',
        'message' => $response_data['detail'] ?? $http_message ?? 'An unknown error occurred',
        'code'    => $http_code,
        ];
    } 
}
///////////////Upload media/////////////////
if(!function_exists('xyz_twap_upload_media'))
{
    function xyz_twap_upload_media($authToken,$filePath) {
    /////////////////////image upload///////////////////////
    $url = XYZ_TWAP_API_OAUTH2_URL."media/upload";

        // Open file and get contents
        $fileName = basename($filePath);
        $fileContent = file_get_contents($filePath);


                
                // Get file extension and map to MIME type
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $mime_types = [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'webp' => 'image/webp',
                ];
                if (!isset($mime_types[$ext])) {
                    return [
                        'status'  => 'error',
                        'message' => 'Unsupported file type',
                        'code'    => 400,
                    ];
                }
        
                $mimeType = $mime_types[$ext];

        // Prepare the form fields with multipart
        $boundary = wp_generate_password(24, false);
        $body = "--{$boundary}\r\n";
        // $body .= "Content-Disposition: form-data; name=\"total_bytes\"\r\n\r\n{$fileSize}\r\n";
        // $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media_type\"\r\n\r\n{$mimeType}\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media_category\"\r\n\r\ntweet_image\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Set headers
        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ];

        // Make the request
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
            'sslverify' => get_option('xyz_twap_peer_verification') == '1',
        ]);
        // Check the response
        if (is_wp_error($response)) {
                return [
                    'status'  => 'error',
                    'message' => 'WP_Error: ' . $response->get_error_message(),
                    'code'    => 0,
                ];
        }
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        $media_id = $response_data['data']['id'] ?? null;
        $http_code = $response['response']['code'];
        $http_message = $response['response']['message'];
        if ($http_code === 200 && !empty($response_data)) {
            return [
                'status' => 'success',
                'data'   => [
                        'id'   => $media_id,
                    ],
                'code'   => $http_code,
            ];
        }
        // Handle all other cases as errors
        return [
            'status'  => 'error',
            'message' => $response_data['detail'] ?? $http_message ?? 'An unknown error occurred',
            'code'    => $http_code,
        ];
    }
}
//////////create image tweet////////////////////
if(!function_exists('xyz_twap_create_post'))
{
    function xyz_twap_create_post($authToken,$mediaId,$message){
    $url = XYZ_TWAP_API_OAUTH2_URL."tweets";
        // Prepare the payload
        $payload = [
            'text' => $message,
            'media' => [
                'media_ids' => [$mediaId]
            ]
        ];
        // Prepare the headers
        $headers = [
            'Authorization' => 'Bearer ' . $authToken,
            'Content-Type'  => 'application/json',
        ];
        // Prepare the arguments for wp_remote_post
        $args = [
            'method'    => 'POST',
            'headers'   => $headers,
            'body'      => json_encode($payload),
            'timeout'   => 30,
            'sslverify' => get_option('xyz_twap_peer_verification') == '1',
        ];
        $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return [
            'status'  => 'error',
            'message' => 'WP_Error: ' . $response->get_error_message(),
            'code'    => 0,
        ];
    }
    $body = wp_remote_retrieve_body($response);
    $response_data = json_decode($body, true);
    $http_code = $response['response']['code'];
    $http_message = $response['response']['message'];
    if ($http_code === 201 && !empty($response_data)) {
        $response_data = json_decode($body, true);
        $tweet_id = $response_data['data']['id'] ?? null;
    return [
        'status' => 'success',
        'data'   => [
                    'id'   => $tweet_id,
                ],
        'code'   => $http_code,
    ];
    }
    // Handle all other cases as errors
    return [
    'status'  => 'error',
    'message' => $response_data['detail'] ?? $http_message ?? 'An unknown error occurred',
    'code'    => $http_code,
    ];
    }
}
