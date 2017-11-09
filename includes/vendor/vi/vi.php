<?php

namespace wpquads;

/*
 * vi integration for WP QUADS
 * @author René Hermenau
 * @email info@mashshare.net
 * 
 */

/**
 * Main class for wp quads vi integration
 *
 * @author René Hermenau
 */
class vi {

    /**
     * vi settings
     * @var type 
     */
    public $settings;

    /**
     * Debug mode
     * @var bool 
     */
    private $debug = true;

    /**
     * Base64 decoded jwt token
     * @var obj 
     */
    public $token;

    /**
     * Available ads
     * @var obj 
     */
    public $ads;

    /**
     * Ad Settings
     * @var obj 
     */
    public $adsSettings;

    /**
     * vi notices
     * @var array
     */
    //private $notices = array();

    public function __construct() {

        if ($this->debug) {
            // Test endpoints
            $this->urlSettings = 'https://dashboard-api-test.vidint.net/v1/api/widget/settings';
        } else {
            // Production endpoints
            $this->urlSettings = 'https://dashboard-api-test.vidint.net/v1/api/widget/settings';
        }

        $this->hooks();

        $this->settings = get_option('quads_vi_settings');
        $this->ads = get_option('quads_vi_ads');

        $this->getToken();

    }

    /**
     * Load hooks
     */
    public function hooks() {
        // Cron Check vi api settings daily
        add_action('quads_daily_event', array($this, 'setSettings'));
        add_action('quads_daily_event', array($this, 'setRevenue'));
        
        // Register the vi ad settings
        add_action('admin_init', array($this, 'registerSettings'));

        // Shortcodes
        add_shortcode('quadsvi', array($this, 'getShortcode'));
        
    }
    
    /**
     * Register the vi ad settings
     */
    public function registerSettings(){
        register_setting( 'quads_vi_ads', 'quads_vi_ads' );
    }
    
    
//    public function createAdSettings(){
//        add_settings_section(
//        'vi_ad_settings',         // ID used to identify this section and with which to register options
//        'VI Ad Settings',                  // Title to be displayed on the administration page
//        'vi_options_callback', // Callback used to render the description of the section
//        'general'                           // Page on which to add this section of options
//    );
//    }
    
    
//    private function parse($text) {
//    // Damn pesky carriage returns...
//    $text = str_replace("\r\n", "\n", $text);
//    $text = str_replace("\r", "\n", $text);
//
//    // JSON requires new line characters be escaped
//    $text = str_replace("\n", "\\n", $text);
//    return $text;
//}

    /**
     * Shortcode to include vi ad
     *
     * @param array $atts
     */
    public function getShortcode($atts) {
        global $quads_options;

        if (quads_check_meta_setting('NoAds') === '1') {
            return;
        }

        if (quads_is_amp_endpoint()) {
            return;
        }

        // The ad id
        $id = isset($atts['id']) ? (int) $atts['id'] : 1;

        $viad = $this->getAdCode();

        $style = 'min-width:363px;min-height:363px;';
        
        $code = "\n" . '<!-- WP QUADS v. ' . QUADS_VERSION . '  Shortcode vi ad -->' . "\n";
        $code .= '<div class="quads-location' . $id . '" id="quads-vi-ad' . $id . '" style="' . $style . '">' . "\n";
        $code .= "<script>";
        $code .= do_shortcode($viad['ads'][1]);
        $code .= '</script>' . "\n";
        $code .= '</div>' . "\n";

        return $code;
    }

    /**
     * vi Settings API
     * @return bool
     * See https://docs.vi.ai/
     * {
      "status": "ok",
      "error": null,
      "data": {
      "_id": [string],
      "signupURL": [string],
      "demoPageURL": [string],
      "loginAPI": [string],
      "directSellURL": [string],
      "dashboardURL": [string],
      "IABcategoriesUrl": [string],
      "revenueAPI": [string],
      "adstxtAPI": [string],
      “languages”: [ {“string” : “string”}, ... ]
      "jsTagAPI": [string]
      }
      }
     */
    public function setSettings() {
        
        $args = array(
            'method' => 'GET',
            'headers' => array(),
            'timeout' => 45
        );
        $response = wp_remote_post($this->urlSettings, $args);
        
        $response = json_decode($response['body']);
        
        if (isset($response->status) && $response->status == 'ok') {
            update_option('quads_vi_settings', $response);
            return true;
        }
        return false;
    }

    /**
     * Get vi settings
     * @return obj
     */
    public function getSettings() {
        return get_option('quads_vi_settings');
    }

    /**
     * Login to vi account
     * @param string $email
     * @param string $password
     * @return string json
     */
    public function login($email, $password) {
        $args = array(
            'method' => 'POST',
            'headers' => array(),
            'timeout' => 45,
            'body' => array('email' => $email, 'password' => $password)
        );

        $response = wp_remote_post($this->urlAuthenticate, $args);
        if (is_array($response)) {
            return $response['body'];
        }
        return json_encode('Unknown error: Can not retrive vi login information');
    }

    /**
     * 
     * @return string
     */
    public function getDashboard() {
        $response = wp_remote_get($this->urlDashboard);
        if (is_array($response)) {
            return $response['body']; // use the content
        }
        return '';
    }

    /**
     * Can write to the root of WordPress
     * @return boolean
     */
    private function canWriteRoot() {
        if (is_writable(get_home_path())) {
            return true;
        }
        return false;
    }

    /**
     * Create ads.txt
     * @return boolean
     */
    public function createAdsTxt() {

        if (!isset($this->token->publisherId))
            return false;

        $file = ABSPATH . 'ads.txt';
        // Default ads.txt content
        $vi = "vi.ai " . $this->token->publisherId . " DIRECT # 41b5eef6" . "\r\n";
        $vi .= "spotxchange.com, 74964, RESELLER, 7842df1d2fe2db34 # 41b5eef6" . "\r\n";
        $vi .= "spotx.tv, 74964, RESELLER, 7842df1d2fe2db34 # 41b5eef6" . "\r\n";
        $vi .= "spotx.tv, 104684, RESELLER, 7842df1d2fe2db34 # 41b5eef6" . "\r\n";
        $vi .= "spotx.tv, 122515, RESELLER, 7842df1d2fe2db34 # 41b5eef6" . "\r\n";
        $vi .= "freewheel.tv, 364193, RESELLER # 41b5eef6" . "\r\n";
        $vi .= "freewheel.tv, 369249, RESELLER # 41b5eef6" . "\r\n";
        $vi .= "freewheel.tv, 440657, RESELLER # 41b5eef6" . "\r\n";
        $vi .= "freewheel.tv, 440673, RESELLER # 41b5eef6" . "\r\n";

        // ads.txt does not exists
        if (!is_file($file)) {
            if (!file_put_contents($file, $vi))
                return false;
        } else {
            // Remove all vi related entries
            // get everything from ads.txt which already exists
            $content = file($file);

            // Remove any line that contains string # 41b5eef6 mark
            $pattern = "/41b5eef6/i";
            $remove = preg_grep($pattern, $content);
            $content = array_diff($content, $remove);

            // Add the cleaned content
            file_put_contents($file, $content);
            sleep(1);
            // Append the vi related content again
            if (!file_put_contents($file, $vi . PHP_EOL, FILE_APPEND))
                return false;
        }

        return true;
    }

    /**
     * Get the access token
     */
    private function getToken() {
        $token = get_option('quads_vi_token', '');

        if (empty($token)) {
            $this->token = '';
            return;
        }

        preg_match("/(\w*).(\w*)/", $token, $output);

        $this->token = json_decode(base64_decode($output[2]));
    }

    public function getAds() {
        $this->ads = get_option('quads_vi_ads');
    }

    public function setAds() {
        update_option('quads_vi_ads', $this->ads);
    }



    /**
     * Get revenue from API and store it in db
     * @return mixed string | bool 
     */
    public function setRevenue() {
        $vi_token = get_option('quads_vi_token');
        if (!$vi_token)
            return false;


        $args = array(
            'headers' => array(
                'Authorization' => $vi_token
            )
        );
        $response = wp_remote_request('https://dashboard-api-test.vidint.net/v1/api/publishers/report/revenue', $args);

        if (is_wp_error($response))
            return false;
        if (wp_remote_retrieve_response_code($response) == '404' || wp_remote_retrieve_response_code($response) == '401')
            return false;
        if (empty($response))
            return false;
        
        // convert into object
        $response = json_decode($response['body']);

        if (!isset($response->status) || $response->status !== 'ok') {
            return false;
        }

        // else
        //return $response->data;
        update_option('quads_vi_revenue', $response->data);
        return true;
        
    }
    
    /**
     * Get Revenue from db
     * @return object
     */
    public function getRevenue() {
        return get_option('quads_vi_revenue');
    }

    /**
     * Get ad code from api and store it in database
     * @return mixed string | bool 
     */
    public function setAdCode() {
        $vi_token = get_option('quads_vi_token');
        
        $ads = get_option('quads_vi_ads');

        if (!$vi_token)
            return false;

        $viParam = array(
            'domain' => $this->getDomain(),
            'adUnitType' => 'FLOATING_OUTSTREAM',
            'divId' => 'div_id',
            'language' => isset($ads['ads'][1]['language']) ? $ads['ads'][1]['language'] : 'en-en',
            'iabCategory' => isset($ads['ads'][1]['iab1']) ? $ads['ads'][1]['iab1'] : 'IAB2-16',
            'font' => isset($ads['ads'][1]['txt_font_family']) ? $ads['ads'][1]['txt_font_family'] : 'Courier New',
            'fontSize' => isset($ads['ads'][1]['font_size']) ? $ads['ads'][1]['font_size'] : 12,
            'keywords' => isset($ads['ads'][1]['keywords']) ? $ads['ads'][1]['keywords'] : 'key,words',
            'textColor' => isset($ads['ads'][1]['text_color']) ? $ads['ads'][1]['text_color'] : '#00ff00',
            'backgroundColor' => isset($ads['ads'][1]['bg_color']) ? $ads['ads'][1]['bg_color'] : '#00ff00',
            'vioptional1' => isset($ads['ads'][1]['optional1']) ? $ads['ads'][1]['optional1'] : 'optional1',
            'vioptional2' => isset($ads['ads'][1]['optional2']) ? $ads['ads'][1]['optional2'] : 'optional2',
            'vioptional3' => isset($ads['ads'][1]['optional3']) ? $ads['ads'][1]['optional3'] : 'optional3',
            'float' => true,
            'logoUrl' => 'http://url.com/logo.jpg',
            'dfpSupport' => true,
            'sponsoredText' => 'Sponsored text',
            'poweredByText' => 'Powered by VI'
        );

        $args = array(
            'method' => 'POST',
            'timeout' => 10,
            'headers' => array(
                'Authorization' => $vi_token,
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'body' => json_encode($viParam)
        );

        $response = wp_remote_post($this->settings->data->jsTagAPI, $args);

        if (is_wp_error($response))
            return false;
        if (wp_remote_retrieve_response_code($response) == '404' || wp_remote_retrieve_response_code($response) == '401')
            return false;
        if (empty($response))
            return false;

        // convert into object
        $response = json_decode($response['body']);
        

        // Die()
        if ($response->status !== 'ok' || empty($response->data)) {
            return false;
        }
        
        // Add ad code to key 1 as long as there are no more vi ad codes
        // Later we need to loop through the $ads array to store values
        $ads[1]['code'] = $response->data;

        //return $response->data;
        update_option('quads_vi_ads', $ads);
        
        return $response->data;
    }
    
    public function getAdCode(){
        return get_option('quads_vi_ads');
    }

    /**
     * Get Publisher ID
     * @return string
     */
    public function getPublisherId() {

        return isset($this->token->publisherId) ? $this->token->publisherId : '';
    }

    private function getDomain() {
        $domain = str_replace('www.', '', get_home_url());
        $domain = str_replace('https://', '', $domain);
        $domain = str_replace('http://', '', $domain);

        return $domain;
    }

}