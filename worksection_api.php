<?php
//print '<pre>' . print_r($eventsArray, true) . '</pre>';

/*
 *  Класс реализует самописные методы api, созданные на основе crawling-сайта
 *  Но дополнительно через интерфейс getCommonApi предоставляет доступ к официальным (commonApi)
 *
 *  — isAuthed()
 *  — doHttpAuth()
 *
 *  — getCommonApi()
 *  — getEvents()
 *  — getTaskCommentsHtml($project_id, $task_id)
 *  — getTaskLogs($project_id, $task_id)
 */

class worksectionHandler extends worksectionUtilities {

    public $root = '';

    public $config = [
        'email' => '',
        'password' => '',
        'domain' => '',
    ];

    public $cookiesFilename = 'worksection_cookies.nfo';
    public $cookies = [];

    /*
     * @class worksectionCommonApi
     */
    public $commonApi = [];

    public function __construct($config){

        $this->root = dirname(realpath(__FILE__)) . '/';

        $this->config = $config ;

        $this->cookies = $this->getCookies();

    }

    public function getCommonApi(){

        if (empty($apiConnector))
            $this->commonApi = new worksectionCommonApi($this->config['apikey'], $this->config['domain']);

        return $this->commonApi ;

    }


    public function isAuthed(){

        $reply = $this->openUrlWithCookies('https://' . $this->config['domain'] . '/');

        if (strpos($reply, 'ajax/?action=my_search&') !== false)
            return true ;

        return false;

    }

    public function getProjectTasks($project_id, $params){

        return $this->getCommonApi()->getProjectTasks($project_id, $params);
    }

    /*
     * $task_page like «/project_id/task_id/»
     */
    public function subscribeToTask($task_page, $email_user){

        $url = $this->getCommonApi()->generateApiUrl($task_page, 'subscribe', ['email_user' => $email_user]);

        $reply = file_get_contents($url);

        if ($reply != '{"status":"ok"}')
            return false;

        return true ;

    }

    /*
     * Получает список последних уведомлений (notify) из панели пользователя
     */
    public function getLastEvents(){

        $this->config['mda'] = $this->getMyEventsMdaHash();

        $reply = $this->openUrlWithCookies('https://' . $this->config['domain'] . '/ajax/?action=my_events&mda=' . $this->config['mda']);

        $eventsArray = self::parseEventsHtml($reply);

        return $eventsArray;

    }

    public function getTaskCommentsHtml($project_id, $task_id){

        $url = 'https://' . $this->config['domain'] . '/project/' . $project_id . '/' . $task_id . '/';

        $reply = $this->openUrlWithCookies($url);

        return $reply ;
    }

    public function getTaskLogs($project_id, $task_id){

        $content = $this->getTaskCommentsHtml($project_id, $task_id);

        return self::parseCommentsLogsHtml($content);

    }

    public function getAllTasks(){

        return $tasks = $this->getCommonApi()->getAllTasks();

    }

}

/*
 * Used only by class worksectionHandler
 */
class worksectionUtilities {


    public function doHttpAuth(){

        $this->config['mda'] = $this->getLogonMdaHash();

        if (strlen($this->config['mda']) != 32){
            print 'Unable to get mda hash';
            exit();
        }

        $url = 'https://' . $this->config['domain'] . '/login/';

        list($headers, $reply) = self::post_query($url, [
            'email' => $this->config['email'],
            'password' => $this->config['password'],
            'save_login' => 1,
            'action' => 'logon',
            'mda' => $this->config['mda'],
            'chk' => 'frm_chk',
        ], [], ['withHeaders' => true]);

        $headers = self::http_parse_headers($headers);

        $this->saveCookies($headers['Set-Cookie']);

        return ;

    }

    public function getMyEventsMdaHash(){

        $url = 'https://' . $this->config['domain'];

        $reply = $this->openUrlWithCookies($url);

        $mda_hash = self::extract__surrounded($reply, '/ajax/?action=my_events&mda=', '\',');

        if (strlen($mda_hash) != 32){
            print 'Unable to get getMyEventsMdaHash';
            exit();
        }

        return $mda_hash;
    }

    public function getLogonMdaHash(){

        $url = 'https://' . $this->config['domain'] . '/login/';

        $reply = $this->openUrlWithCookies($url, false);

        $mda_hash = self::extract__surrounded($reply, '<input type="hidden" name="mda" value="', '" ');

        return $mda_hash;
    }


    /*
     * Open url using cookies and saves new one's if received
     */
    public function openUrlWithCookies($url, $cookie = true){

        $headers_provided = [
            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'accept-language: en,ru-RU;q=0.9,ru;q=0.8,es;q=0.7,de;q=0.6',
        ];

        if ($cookie)
            $headers_provided[] = self::getCookieHeaders();


        list($headers, $reply) = self::post_query($url, [], $headers_provided, ['withHeaders' => true]);

        if (isset($headers['Set-Cookie']))
            $this->saveCookies($headers['Set-Cookie']);

        //print $headers;

        return $reply ;

    }

    public function getCookieHeaders(){

        return 'cookie: ' . implode(';', $this->getCookies());

    }

    public function getCookies(){

        $cookies = @json_decode(file_get_contents($this->root . 'worksection_cookies.nfo'), true);

        if (!is_array($cookies) OR empty($cookies))
            return [];

        return $cookies;

    }
    public function saveCookies($cookiesArray){

        $cookiesFilename = $this->root . $this->cookiesFilename ;

        if (!file_exists($cookiesFilename)){
            @file_put_contents($cookiesFilename, '');

            if (!file_exists($cookiesFilename)){
                print 'Unable to create cookieFile «' . $cookiesFilename . '». Exit.';
                exit();
            }
        }

        @file_put_contents($cookiesFilename, json_encode($cookiesArray));

    }

    static function post_query($url, $params, $header = array(), $curlParams = []){

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POST, 1);

            if (isset($params['as_json'])){

                unset($params['as_json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
            else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }

        }

        // debug purpose
        //curl_setopt($ch, CURLOPT_HEADER, 1);
        //curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if (!empty($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if (isset($curlParams['withHeaders']) AND $curlParams['withHeaders'] == true){

            curl_setopt($ch, CURLOPT_HEADER, true);

        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        if (isset($curlParams['withHeaders']) AND $curlParams['withHeaders'] == true){

            return explode("\r\n\r\n", $server_output);

        }

        return $server_output ;

    }

    static function http_parse_headers($raw_headers) {
        $headers = array();
        $key = '';

        foreach(explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                }
                else {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                }

                $key = $h[0];
            }
            else {
                if (substr($h[0], 0, 1) == "\t")
                    $headers[$key] .= "\r\n\t".trim($h[0]);
                elseif (!$key)
                    $headers[0] = trim($h[0]);
            }
        }

        return $headers;
    }

    /*
     * Извлекает контент между string $before и string $after из $content
     */
    static function extract__surrounded($content, $before, $after){


        $string = explode($before, $content);

        $string = $string[1];

        $string = explode($after, $string);

        $reply = $string[0] ;

        return $reply ;


    }

    /*
     * convert string `5 ноября` to 2019-11-05
     */
    static function convertDateStringToYmd($date_string){

        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        foreach ($months as $_month_number => $_month_name){

            if (strpos($date_string, $_month_name) !== false){

                break ;
            }

        }

        $year = date('Y');

        if (strpos($date_string, ' 2019') !== false)
            $year = 2019 ;

        $day_number = explode(' ', $date_string);
        $day_number = $day_number[0];

        if (strlen($day_number) == 1)
            $day_number = '0' . $day_number;
        if (strlen($_month_number) == 1)
            $_month_number = '0' . $_month_number;

        $output_ymd = date($year . '-') . $_month_number . '-' . $day_number ;

        return $output_ymd;
    }

    public function parseCommentsLogsHtml($content){

        //$content = explode('comment_max_dt', $content);
        //$content = $content[0];

        $logs = explode('class="log"', $content);
        unset($logs[0]);

        foreach ($logs as $_key => $_log){

            $log_parts = explode('<div class="ind"></div>', $_log);

            $log_html = $log_parts[0];

            $date_string = explode('<div class="date">', $log_html);
            $date_string = explode('</div>', $date_string[1]);
            $date_string = $date_string[0] ;

            if (strpos($date_string, '<span class="print">') !== false){

                $date_string = explode('<span class="print">', $date_string);
                $date_string = explode('</span>', $date_string[1]);

                $date_string = $date_string[0];

            }

            $tags_added = [];

            $log_html_without_strike = $log_html;

            if (strpos($log_html_without_strike, '<strike>') !== false){

                $log_html_without_strike = preg_replace('/<strike>(.){0,1000}<\/strike>/', '', $log_html_without_strike);

                $tags_html = explode('<span class="tags">', $log_html_without_strike);

                if (isset($tags_html[1])) {
                    $tags_html = explode('</div>', $tags_html[1]);
                    $tags_html = $tags_html[0];

                    //$tags_html = strip_tags($tags_html);

                    $tags_html = explode('<span', $tags_html);

                    foreach ($tags_html as $_tag) {

                        if (strpos($_tag, 'class="tag') === false)
                            continue;

                        $tag_parts = explode('</span>', $_tag);

                        $tag_name = explode('">', $tag_parts[0]);

                        $tags_added[] = trim($tag_name[1]);

                    }

                }

            }


            $logs[$_key] = [
                'date' => $date_string,
                'date_ymd' => self::convertDateStringToYmd($date_string),
                'tags_added' => $tags_added,
                //'$tags_html' => $tags_html,
                //'$log_html_without_strike' => $log_html_without_strike,
                //'html' => $log_html,
            ];


        }


        return $logs ;

    }

    static function parseEventsHtml($content){

        $output = [];

        $junk = explode('<div class="also">', $content);
        $content_without_footer = $junk[0] ;

        $days = explode('<div class="mm_float">', $content_without_footer);
        unset($days[0]);

        foreach ($days as $_content){

            $date_string = explode('</i></span></div>', $_content);
            $date_string = $date_string[0];
            //$date_string = explode('<i>', $date_string[0]);

            if (strpos($date_string, 'date1') !== false) {
                $date_string = explode('<i>', $date_string)[1];
            }
            if (strpos($date_string, 'date2') !== false) {
                $date_string = explode('<i>', $date_string)[1];
            }
            if (strpos($date_string, 'date3') !== false){
                $date_string = explode('<i>', $date_string)[0];
                $date_string = strip_tags($date_string);
            }

            $date_string = trim($date_string);

            $item = [
                'date_string' => $date_string,
                'date_ymd' => self::convertDateStringToYmd($date_string),
                'events' => [],
                //'_content' => $_content,
            ];

            $events_raw = explode('l_click', $_content);

            foreach ($events_raw as $_event_html){

                if (strpos($_event_html, 'data-task=') === false)
                    continue ;

                if (strpos($_event_html, 'data-task="0"') !== false)
                    continue ;

                $time = self::extract__surrounded($_event_html, '<div class="time">', '</div>');

                $full_date = $item['date_ymd'] . ' ' . $time . ':00';

                $task_url = self::extract__surrounded($_event_html, '<a href="/project', '" class=');

                $_event_html_splited = explode('<span class="mline"></span>', $_event_html);

                $project_name = self::extract__surrounded($_event_html_splited[0], 'data-title="', '">');
                $project_name = explode('\n%', $project_name);
                $project_name = $project_name[0];
                $project_name = html_entity_decode($project_name);

                $data_id = self::extract__surrounded($_event_html, 'data-id="', '"');

                $event_type = self::extract__surrounded($_event_html_splited[1], 'data-title="', '"');

                $task_name = explode('">', $_event_html_splited[0]);
                $task_name = $task_name[count($task_name) - 1];
                $task_name = trim(strip_tags($task_name));

                $user_name = self::extract__surrounded($_event_html, 'ass="user"><b>', '</b>');

                $eventInfo = [
                    'data_id' => $data_id,
                    'time' => $time,
                    'full_date' => $full_date,
                    'full_date_ts' => strtotime($full_date),
                    'event_type' => $event_type,
                    'task_url' => '/project' . $task_url,
                    'task_name' => $task_name,
                    'project_name' => $project_name,
                    'user_name' => $user_name,
                    'it_vs' => '',
                    //'_event_html' => $_event_html,
                ];

                $item['events'][] = $eventInfo;

            }

            if (empty($item['events']))
                continue ;

            $output[] = $item ;

        }

        return $output ;

    }




}

class worksectionCommonApi {

    public $domain = '';
    public $apikey = '';

    public function __construct($apikey = '', $domain = ''){

        $this->apikey = $apikey ;
        $this->domain = $domain ;

    }

    /*
     * Params (optional):
     * — filter (active)
     * - text (1)
     */
    public function getProjectTasks($project_id, $params = []){

        $url = $this->generateApiUrl('/project/' . $project_id . '/', 'get_tasks', $params);

        //print $url . '<br/>';

        $projectsReply = file_get_contents($url);
        $projectsReply = json_decode($projectsReply, true);

        return $projectsReply ;

    }

    public function getTaskComments($project_id, $task_id){

        $url = $this->generateApiUrl('/project/' . $project_id . '/' . $task_id . '/', 'get_comments');

        print $url . '<br/>';

        $projectsReply = file_get_contents($url);
        $projectsReply = json_decode($projectsReply, true);

        return $projectsReply ;

    }

    public function getProjects(){

        $url = $this->generateApiUrl('', 'get_projects');

        //print $url . '<br/>';

        $projectsReply = file_get_contents($url);
        $projectsReply = json_decode($projectsReply, true);

        return $projectsReply ;

    }



    public function getAllTasks(){

        $url = $this->generateApiUrl('', 'get_all_tasks');

        //print $url . '<br/>';

        $projectsReply = file_get_contents($url);
        $projectsReply = json_decode($projectsReply, true);

        return $projectsReply ;

    }

    public function generateApiUrl($page, $action, $extra_url_params = []){

        $hash = md5 ($page.$action.$this->apikey);

        $url = ('https://' . $this->domain . '/api/admin/?action=' . $action . '&page=' . $page . '&hash=' . $hash . '&' . http_build_query($extra_url_params));

        return $url;

    }
}



?>