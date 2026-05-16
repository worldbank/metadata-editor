<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * OIDC Client Library
 *
 * Generic OpenID Connect client for authentication with any OIDC-compliant provider
 * Supports auto-discovery, token exchange, and JWT validation
 *
 */

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class OidcClient
{
    protected $ci;
    protected $config;
    protected $discovery_cache_file;
    protected $jwks_cache_file;
    protected $discovery_cache_ttl = 3600; // 1 hour
    protected $jwks_cache_ttl = 3600; // 1 hour

    function __construct()
    {
        log_message('debug', "OidcClient class initialized");
        $this->ci =& get_instance();
        $this->config = $this->ci->config->item('oidc_auth');
        
        // Set cache file paths
        $cache_dir = APPPATH . '../datafiles/tmp/';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
        }
        $this->discovery_cache_file = $cache_dir . 'oidc_discovery_' . md5($this->config['issuer']) . '.json';
        $this->jwks_cache_file = $cache_dir . 'oidc_jwks_' . md5($this->config['issuer']) . '.json';
    }

    /**
     * Get OIDC discovery document
     * 
     * @return array Discovery document
     * @throws Exception
     */
    public function discover()
    {
        // Check cache first
        if (file_exists($this->discovery_cache_file)) {
            $cache_time = filemtime($this->discovery_cache_file);
            if ((time() - $cache_time) < $this->discovery_cache_ttl) {
                $cached = json_decode(file_get_contents($this->discovery_cache_file), true);
                if ($cached) {
                    return $cached;
                }
            }
        }

        if (empty($this->config['issuer'])) {
            throw new Exception('OIDC issuer not configured');
        }

        // Build discovery URL
        $issuer = rtrim($this->config['issuer'], '/');
        $discovery_url = $issuer . '/.well-known/openid-configuration';

        // Fetch discovery document
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Accept: application/json',
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($discovery_url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to fetch OIDC discovery document from: ' . $discovery_url);
        }

        $discovery = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in discovery document');
        }

        if (!isset($discovery['authorization_endpoint']) || !isset($discovery['token_endpoint'])) {
            throw new Exception('Invalid discovery document: missing required endpoints');
        }

        // Cache the discovery document
        @file_put_contents($this->discovery_cache_file, json_encode($discovery));

        return $discovery;
    }

    /**
     * Generate PKCE code verifier and challenge
     * 
     * @return array Array with 'code_verifier', 'code_challenge', and 'code_challenge_method'
     */
    public function generatePkceCodes()
    {
        // Generate random code verifier (43-128 characters, URL-safe)
        $length = 128; // Use maximum length for better security
        $code_verifier = $this->generateRandomString($length);
        
        // Generate code challenge: base64url(sha256(code_verifier))
        $code_challenge = $this->base64urlEncode(hash('sha256', $code_verifier, true));
        
        return array(
            'code_verifier' => $code_verifier,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256'
        );
    }
    
    /**
     * Generate random URL-safe string
     * 
     * @param int $length Length of string to generate
     * @return string Random URL-safe string
     */
    private function generateRandomString($length = 128)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Base64URL encode (RFC 4648)
     * 
     * @param string $data Data to encode
     * @return string Base64URL encoded string
     */
    private function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get authorization URL
     * 
     * @param string $state CSRF state parameter
     * @param string $nonce Nonce for replay protection
     * @param string|null $code_challenge PKCE code challenge (optional)
     * @param array $additional_params Additional query parameters to include
     * @param string|null $redirect_uri Override redirect URI (optional)
     * @return string Authorization URL
     * @throws Exception
     */
    public function getAuthorizationUrl($state, $nonce, $code_challenge = null, $additional_params = array(), $redirect_uri = null)
    {
        $discovery = $this->discover();
        $auth_endpoint = $discovery['authorization_endpoint'];

        if (empty($redirect_uri)) {
            $redirect_uri = !empty($this->config['redirect_uri']) 
                ? $this->config['redirect_uri'] 
                : site_url('auth/oidc_callback');
        }

        $params = array(
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirect_uri,
            'response_type' => $this->config['response_type'],
            'scope' => $this->config['scopes'],
            'state' => $state,
            'nonce' => $nonce
        );

        // Add PKCE code challenge if provided
        if (!empty($code_challenge)) {
            $params['code_challenge'] = $code_challenge;
            $params['code_challenge_method'] = 'S256';
        }

        // Add response_mode if specified
        if (!empty($this->config['response_mode'])) {
            $params['response_mode'] = $this->config['response_mode'];
        }

        // Add prompt parameter to force account selection every time
        // Default to 'select_account' if not specified in config
        $prompt = isset($this->config['prompt']) ? $this->config['prompt'] : 'select_account';
        if (!empty($prompt)) {
            $params['prompt'] = $prompt;
        }

        // Merge any additional parameters
        $params = array_merge($params, $additional_params);

        $url = $auth_endpoint . '?' . http_build_query($params);
        
        return $url;
    }

    /**
     * Exchange authorization code for tokens
     * 
     * @param string $code Authorization code
     * @param string $state State parameter (for validation)
     * @param string|null $code_verifier PKCE code verifier (for public clients)
     * @return array Tokens (id_token, access_token, etc.)
     * @throws Exception
     */
    public function exchangeCodeForTokens($code, $state, $code_verifier = null)
    {
        // Validate state
        // For public clients (PKCE flow), state validation happens on frontend
        // For confidential clients, validate state from session
        $client_type = isset($this->config['client_type']) ? $this->config['client_type'] : 'confidential';
        
        if ($this->config['validate_state'] && $client_type === 'confidential') {
            // Only validate state from session for confidential clients
            $stored_state = $this->ci->session->userdata('oidc_state');
            if (empty($stored_state) || $stored_state !== $state) {
                throw new Exception('Invalid state parameter');
            }
            $this->ci->session->unset_userdata('oidc_state');
        }
        // For public clients, state is validated by frontend before calling this endpoint

        $discovery = $this->discover();
        $token_endpoint = $discovery['token_endpoint'];

        $redirect_uri = !empty($this->config['redirect_uri']) 
            ? $this->config['redirect_uri'] 
            : site_url('auth/oidc_callback');

        // Prepare token request
        $data = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri,
            'client_id' => $this->config['client_id']
        );
        
        // Choose authentication method based on client type
        $client_type = isset($this->config['client_type']) ? $this->config['client_type'] : 'confidential';
        
        if ($client_type === 'confidential') {
            // Confidential client: use client_secret
            if (empty($this->config['client_secret'])) {
                throw new Exception('client_secret is required for confidential clients');
            }
            $data['client_secret'] = $this->config['client_secret'];
        } else if ($client_type === 'public') {
            // Public client: use PKCE
            if (empty($code_verifier)) {
                throw new Exception('code_verifier is required for public clients using PKCE');
            }
            $data['code_verifier'] = $code_verifier;
        } else {
            throw new Exception('Invalid client_type. Must be "confidential" or "public"');
        }

        // Make token request
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($token_endpoint, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to exchange code for tokens');
        }

        $tokens = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in token response');
        }

        if (isset($tokens['error'])) {
            throw new Exception('Token exchange error: ' . $tokens['error_description']);
        }

        if (!isset($tokens['id_token'])) {
            throw new Exception('No ID token in response');
        }

        return $tokens;
    }

    /**
     * Validate ID token
     * 
     * @param string $id_token JWT ID token
     * @param string $nonce Expected nonce value
     * @return array Decoded token claims
     * @throws Exception
     */
    public function validateIdToken($id_token, $nonce = null)
    {
        try {
            // Decode without verification first to get header
            $parts = explode('.', $id_token);
            if (count($parts) !== 3) {
                throw new Exception('Invalid JWT format');
            }

            $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
            if (!$header || !isset($header['kid'])) {
                throw new Exception('Invalid JWT header or missing kid');
            }

            $kid = $header['kid'];
            $algorithm = isset($header['alg']) ? $header['alg'] : 'RS256';

            // Try using Firebase JWT's JWK parser first (if available)
            if (class_exists('Firebase\JWT\JWK') && method_exists('Firebase\JWT\JWK', 'parseKeySet')) {
                try {
                    $jwks_raw = $this->getJwksRaw();
                    $key_set = JWK::parseKeySet($jwks_raw, $algorithm);
                    
                    if (!isset($key_set[$kid])) {
                        // Key not found - clear cache and retry once
                        log_message('debug', 'Key not found for kid: ' . $kid . ', clearing cache and retrying');
                        $this->clearJwksCache();
                        $jwks_raw = $this->getJwksRaw();
                        $key_set = JWK::parseKeySet($jwks_raw, $algorithm);
                        
                        if (!isset($key_set[$kid])) {
                            $available_kids = implode(', ', array_keys($key_set));
                            throw new Exception('Public key not found for kid: ' . $kid . '. Available kids: ' . $available_kids);
                        }
                    }
                    
                    // Pass the entire key_set array to JWT::decode
                    $decoded = JWT::decode($id_token, $key_set);
                    $claims = (array) $decoded;
                    
                } catch (\Firebase\JWT\Exception $e) {
                    // Re-throw Firebase JWT exceptions with more context
                    log_message('error', 'JWT decode failed: ' . $e->getMessage());
                    throw new Exception('JWT validation failed: ' . $e->getMessage());
                } catch (Exception $e) {
                    // Fall through to manual key lookup
                    log_message('debug', 'JWK::parseKeySet failed, using manual conversion: ' . $e->getMessage());
                }
            }
            
            // Fallback to manual key conversion if JWK parser not available or failed
            if (!isset($claims)) {
                // Get public key for this key ID
                $jwks = $this->getJwks();
                if (!isset($jwks[$kid])) {
                    // Clear cache and retry once
                    log_message('debug', 'Key not found in manual conversion for kid: ' . $kid . ', clearing cache and retrying');
                    $this->clearJwksCache();
                    $jwks = $this->getJwks();
                    if (!isset($jwks[$kid])) {
                        $available_kids = implode(', ', array_keys($jwks));
                        throw new Exception('Public key not found for kid: ' . $kid . '. Available kids: ' . $available_kids);
                    }
                }

                $public_key = $jwks[$kid];

                // Try using Key object if available (newer API)
                if (class_exists('Firebase\JWT\Key')) {
                    try {
                        $key_obj = new \Firebase\JWT\Key($public_key, $algorithm);
                        $decoded = JWT::decode($id_token, $key_obj);
                    } catch (Exception $e) {
                        // Fall back to old API
                        $decoded = JWT::decode($id_token, $public_key, array($algorithm));
                    }
                } else {
                    // Use old API format (compatible with older firebase/jwt versions)
                    $decoded = JWT::decode($id_token, $public_key, array($algorithm));
                }
                
                $claims = (array) $decoded;
            }

            // Validate issuer
            // Normalize both issuers by removing trailing slashes before comparison
            $expected_issuer = rtrim($this->config['issuer'], '/');
            $token_issuer = isset($claims['iss']) ? rtrim($claims['iss'], '/') : '';
            
            if (empty($token_issuer)) {
                throw new Exception('Missing issuer in token claims');
            }
            
            if ($token_issuer !== $expected_issuer) {
                throw new Exception('Invalid issuer. Expected: ' . $expected_issuer . ', Got: ' . $claims['iss']);
            }

            // Validate audience
            $aud = is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];
            if (!in_array($this->config['client_id'], $aud)) {
                throw new Exception('Invalid audience');
            }

            // Validate expiration
            if (isset($claims['exp']) && $claims['exp'] < time()) {
                throw new Exception('Token has expired');
            }

            // Validate nonce
            if ($this->config['validate_nonce'] && $nonce !== null) {
                if (!isset($claims['nonce']) || $claims['nonce'] !== $nonce) {
                    throw new Exception('Invalid nonce');
                }
            }

            return $claims;

        } catch (Exception $e) {
            log_message('error', 'OIDC token validation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get raw JWKS (JSON Web Key Set) from provider
     * 
     * @return array Raw JWKS data
     * @throws Exception
     */
    private function getJwksRaw()
    {
        $discovery = $this->discover();
        
        if (!isset($discovery['jwks_uri'])) {
            throw new Exception('JWKS URI not found in discovery document');
        }

        $jwks_uri = $discovery['jwks_uri'];

        // Fetch JWKS
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Accept: application/json',
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($jwks_uri, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to fetch JWKS from: ' . $jwks_uri);
        }

        $jwks_data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in JWKS response');
        }

        if (!isset($jwks_data['keys']) || !is_array($jwks_data['keys'])) {
            throw new Exception('Invalid JWKS format');
        }

        return $jwks_data;
    }

    /**
     * Clear JWKS cache
     */
    private function clearJwksCache()
    {
        if (file_exists($this->jwks_cache_file)) {
            @unlink($this->jwks_cache_file);
        }
    }

    /**
     * Get JWKS (JSON Web Key Set) - returns PEM keys indexed by kid
     * 
     * @return array Array of public keys (PEM format) indexed by kid
     * @throws Exception
     */
    public function getJwks()
    {
        // Check cache first
        if (file_exists($this->jwks_cache_file)) {
            $cache_time = filemtime($this->jwks_cache_file);
            if ((time() - $cache_time) < $this->jwks_cache_ttl) {
                $cached = json_decode(file_get_contents($this->jwks_cache_file), true);
                if ($cached && is_array($cached)) {
                    return $cached;
                }
            }
        }

        // Get raw JWKS data
        $jwks_data = $this->getJwksRaw();

        // Process keys and extract public keys
        $keys = array();
        foreach ($jwks_data['keys'] as $key_data) {
            if (!isset($key_data['kid'])) {
                log_message('debug', 'JWKS key missing kid, skipping');
                continue;
            }

            $kid = $key_data['kid'];
            
            try {
                // Handle RSA keys with x5c (certificate chain) - preferred method
                if (isset($key_data['x5c']) && is_array($key_data['x5c']) && !empty($key_data['x5c'])) {
                    $cert_text = "-----BEGIN CERTIFICATE-----\r\n" . 
                                chunk_split($key_data['x5c'][0], 64) . 
                                "-----END CERTIFICATE-----\r\n";
                    $cert = @openssl_x509_read($cert_text);
                    if ($cert !== false) {
                        $pubkey = @openssl_pkey_get_public($cert);
                        if ($pubkey !== false) {
                            $key_details = @openssl_pkey_get_details($pubkey);
                            if ($key_details !== false && isset($key_details['key'])) {
                                $keys[$kid] = $key_details['key'];
                                continue;
                            }
                        }
                    }
                }
                
                // Handle RSA keys with n/e (modulus/exponent)
                if (isset($key_data['n']) && isset($key_data['e']) && isset($key_data['kty']) && $key_data['kty'] === 'RSA') {
                    try {
                        $keys[$kid] = $this->jwkToPem($key_data);
                        continue;
                    } catch (Exception $e) {
                        log_message('debug', 'Failed to convert JWK n/e to PEM for kid ' . $kid . ': ' . $e->getMessage());
                    }
                }
                
                log_message('info', 'Could not process JWK for kid: ' . $kid . '. Key type: ' . (isset($key_data['kty']) ? $key_data['kty'] : 'unknown'));
            } catch (Exception $e) {
                log_message('error', 'Error processing JWK for kid ' . $kid . ': ' . $e->getMessage());
            }
        }

        if (empty($keys)) {
            throw new Exception('No valid keys found in JWKS');
        }

        // Cache the processed keys
        @file_put_contents($this->jwks_cache_file, json_encode($keys));

        return $keys;
    }

    /**
     * Convert JWK (n/e format) to PEM
     * 
     * @param array $jwk JWK data
     * @return string PEM formatted public key
     * @throws Exception
     */
    private function jwkToPem($jwk)
    {
        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            throw new Exception('JWK missing required n or e parameters');
        }

        // Decode base64url encoded values
        $n = $this->base64urlDecode($jwk['n']);
        $e = $this->base64urlDecode($jwk['e']);

        // Ensure exponent is properly formatted (should be 3 bytes for 65537)
        if (strlen($e) < 4) {
            $e = str_pad($e, 4, "\0", STR_PAD_LEFT);
        }

        // Build RSA public key in DER format
        $modulus_der = $this->derEncodeInteger($n);
        $exponent_der = $this->derEncodeInteger($e);
        $sequence = $this->derEncodeSequence($modulus_der . $exponent_der);
        $public_key_der = $this->derEncodeSequence($sequence);
        
        // Convert to PEM
        $public_key_base64 = base64_encode($public_key_der);
        $pem = "-----BEGIN PUBLIC KEY-----\r\n" . 
               chunk_split($public_key_base64, 64) . 
               "-----END PUBLIC KEY-----\r\n";

        // Verify the key is valid
        $pubkey = @openssl_pkey_get_public($pem);
        if ($pubkey === false) {
            throw new Exception('Failed to create valid public key from JWK');
        }
        
        $key_details = @openssl_pkey_get_details($pubkey);
        if ($key_details === false || !isset($key_details['key'])) {
            throw new Exception('Failed to extract key details');
        }
        
        return $key_details['key'];
    }

    /**
     * Base64URL decode
     */
    private function base64urlDecode($data)
    {
        // Add padding if needed
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * DER encode integer
     */
    private function derEncodeInteger($value)
    {
        // Remove leading zeros
        $value = ltrim($value, "\0");
        
        // If first byte has high bit set, prepend zero byte
        if (ord($value[0]) & 0x80) {
            $value = "\0" . $value;
        }
        
        return "\x02" . $this->derEncodeLength(strlen($value)) . $value;
    }


    /**
     * DER encode length
     */
    private function derEncodeLength($length)
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * DER encode sequence
     */
    private function derEncodeSequence($data)
    {
        return "\x30" . $this->derEncodeLength(strlen($data)) . $data;
    }

    /**
     * Get end session (logout) URL
     * 
     * @param string $post_logout_redirect_uri Optional redirect URI after logout
     * @return string|null Logout URL or null if not supported
     */
    public function getEndSessionUrl($post_logout_redirect_uri = null)
    {
        try {
            $discovery = $this->discover();
            
            if (!isset($discovery['end_session_endpoint'])) {
                return null;
            }

            $params = array(
                'id_token_hint' => $this->ci->session->userdata('oidc_id_token')
            );

            if ($post_logout_redirect_uri) {
                $params['post_logout_redirect_uri'] = $post_logout_redirect_uri;
            }

            return $discovery['end_session_endpoint'] . '?' . http_build_query($params);
        } catch (Exception $e) {
            log_message('error', 'Failed to get end session URL: ' . $e->getMessage());
            return null;
        }
    }
}

