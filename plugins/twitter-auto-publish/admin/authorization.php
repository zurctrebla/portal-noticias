<?php
$clientId = get_option('xyz_twap_client_id');
$clientSecret = get_option('xyz_twap_client_secret');
$redirectUri = admin_url('admin.php?page=twitter-auto-publish-settings');
if (is_ssl() === false)
    $redirectUri = preg_replace("/^http:/i", "https:", $redirectUri);

if (isset($_REQUEST['code'])) {
    $code = $_REQUEST["code"];
}

if (isset($_POST['tw_auth'])) {
    	require_once (dirname(__FILE__) . '/../api/twitter.php');
// twitter_auth2_reauth();
    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'xyz_twap_tw_auth_form_nonce')) {
        wp_nonce_ays('xyz_twap_tw_auth_form_nonce');
        exit();
    }
    $twapp_session_state = md5(uniqid(rand(), TRUE));
    setcookie("xyz_twap_session_state", $twapp_session_state, "0", "/");

    // Generate code verifier (32-byte random string)
    $code_verifier = bin2hex(random_bytes(32));

    // Generate code challenge (base64url-encoded SHA-256 hash of code_verifier)
    $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');

    // Save code_verifier in session or cookie for later use
    setcookie("xyz_twap_code_verifier", $code_verifier, time() + 3600, "/");
  
    // Generate the authorization URL with sha256 challenge
    $authUrl = "https://x.com/i/oauth2/authorize?";
    $authUrl .= http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'scope' => 'tweet.read tweet.write media.write users.read offline.access',
        'state' => $twapp_session_state,
        'code_challenge' => $code_challenge,
        'code_challenge_method' => 'S256'

    ]);
    
    // Redirect the user to the authorization URL
    header('Location: ' . $authUrl);
    exit;
}
if (isset($_COOKIE['xyz_twap_session_state']) && isset($_REQUEST['state']) && ($_COOKIE['xyz_twap_session_state'] === $_REQUEST['state'])) {
    $token_url = XYZ_TWAP_API_OAUTH2_URL."oauth2/token";
    $current_time=time();
    // Retrieve code_verifier from the cookie
    $code_verifier = $_COOKIE['xyz_twap_code_verifier'] ?? '';

    $data = [
        "code" => $code,
        "grant_type" => "authorization_code",
        "code_verifier" => $code_verifier,
        "redirect_uri" => $redirectUri,
    ];

    $client_credentials = base64_encode("$clientId:$clientSecret");

    // Make the POST request
    $response = wp_remote_post($token_url, [
        'body' => http_build_query($data),
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . $client_credentials,
        ],
        'sslverify' => get_option('xyz_twap_peer_verification') == '1',
    ]);
    // Handle the response
    if (isset($response['body'])) {
        $params = json_decode($response['body']);
        if (isset($params->access_token)) {
            $access_token = $params->access_token;
            $refresh_token = $params->refresh_token;
            update_option('xyz_twap_tw_token', $access_token);
            update_option('xyz_twap_tw_refresh_token', $refresh_token);
            update_option('xyz_twap_last_auth_time', $current_time);
            update_option('xyz_twap_tw_af', 0);
            wp_safe_redirect( admin_url( 'admin.php?page=twitter-auto-publish-settings&auth=1&msg=2' ) );
            exit;
        } else {
            $error='Error:';
            if (isset($params->error)) {
                $error.= $params->error;
            }
            $error = isset( $error ) ? sanitize_text_field( $error ) : '';
            wp_safe_redirect( admin_url( 'admin.php?page=twitter-auto-publish-settings&error_msg=' . urlencode( $error ) ) );
            exit;
        }
    }
}
