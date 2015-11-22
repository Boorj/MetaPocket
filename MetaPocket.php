<?php
/*
 * 10.11.2015 14:50  ! created
 *
 * @todo list of auto print metas for each route.
 * @todo join record data and other data (route, site).
 *       maybe do that from template like join_record_seo_data_to_current_route
 *       then in template you can use joined data. like records.current
 * @todo - normalize DELIMITERS - use commas or self::DELIMITER everywhere.
 * */

namespace Bolt\Extension\Mapple\MetaPocketExtension;

use Bolt\Application;
use Bolt\BaseExtension;
use Mapple\Site\Logic\Utils;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Parser;
use Bolt\Helpers\Arr;

class MetaPocket
{

    const DELIMITER          = '.';
    const GLOBAL_PATH        = 'global';
    const VARS_PATH          = 'global.vars';
    const METAS_OVER_ROUTING = true;
    const INHERIT_METAS      = true;

    /** @var Application $app   */
    private $app = null;

    /** @var \Symfony\Component\Yaml\Parser */
    private $yamlParser = false;

    private $metas = null;
    private $var_tags = [];
    private $default_settings = [];
    private $replacements_global = null;
    private $mappings = null;
    private $og_mapping = [];


    /**
     * __construct
     * @param $app
     */
    function __construct($app) {
        $this->app = $app;
    }

    public function get_all_metas() {
        return $this->metas;
    }


    /**
     * initialize
     */
    public function initialize()
    {
        //initialize default settings
        $this->default_setup();

        // get data from config.yml and merge
        $this->merge_with_meta_yml();

        $this->replace_available_vars_in_global();

        // for each route take some data from 'meta' and 'label' fields.
        $this->inherit_from_routing_yml();

        $this->fill_blank_fields_for_routes();

        //make replacing sequence for selected fields in global's and routes' metas
        $this->replace_available_vars_in_routes();

    //        dump($this->metas);
    }


    /**
     * default_setup
     */
    private function default_setup() {
        $this->var_tags = ['open'=>'%', 'close'=>'%'];

        $this->replacements_global = [
            'meta.share.image',
            'siteurl',
            'sitename',
            'vars.page_title.parts.sitelabel',
            'vars.page_title.default_pattern',
        ];

        $this->default_settings = [
            'global' => [
                'siteurl'  => '%bolt.general.canonical%',
                'sitename' => '%bolt.general.sitename%',
                'contacts' => [],
                'vars'     => [
                    'page_title' => [
                        'default_pattern' => '%parts.sitelabel% | %label%',
                        'parts'           => [
                            'sitelabel' => '%bolt.general.sitename%',
                        ],
                    ],
                ],
                'meta'     => [
                    'robots'      => '',
                    'description' => '%bolt.general.payoff%',
                    'keywords'    => '',
                    'share'       => [
                        'heading'       => '',
                        'comment'       => '',
                        'comment_short' => '',
                        'image'         => 'http://%global.siteurl%/i/screenshot.jpg',
                    ],
                    'og_type'    => 'website',
                ],
            ],
            'routes' => [
                'home' => [
                    'title' => '%parts.sitelabel%',
                ],
            ],
        ];

        $this->og_mapping = [
            'url'         => 'url',
            'image'       => 'share_image',
            'title'       => 'share_heading',
            'description' => 'meta_description',
            'site_name'   => 'share_sitename',
            'type'        => 'og_type',
        ];

        $this->mappings = [
            'site' => [
                'share_sitename'      => 'site:sitename'         ,
                'og_type'             => 'site:meta.og_type'        ,
            ],
            'route' => [
                'meta_description'    => 'route:meta.description',
                'meta_keywords'       => 'route:meta.keywords'   ,
                'share_heading'       => 'route:meta.share.heading'      ,
                'share_comment'       => 'route:meta.share.comment'      ,
                'share_comment_short' => 'route:meta.share.comment_short',
                'share_image'         => 'route:meta.share.image'        ,
                'share_sitename'      => 'site:sitename'         ,

                'og_type'             => 'route:meta.og_type'        ,
            ],
            'record' => [
                'meta_description'    => ['record:meta_description', 'record:teaser', 'record:share_comment', 'record:share_comment_short' ]    ,
                'meta_keywords'       => ['record:meta_keywords']         ,
                'share_heading'       => ['record:share_heading', 'record:title']       , // @todo : inheritance
                'share_comment'       => 'record:share_comment'       ,
                'share_comment_short' => ['record:share_comment_short', 'record:title'] ,
                'share_image'         => ['record:share_image', 'site:meta.share.image']         ,
                'share_sitename'      => 'site:sitename'         ,
                'og_type'             => 'record:og_type'             ,
            ],
        ];

        // @todo Make a list of what to convert - for globals and for each route.

        //        <meta name="robots" content="noindex, follow">
        //    — содержимое страницы индексировать нельзя, но следовать по ссылкам можно
        //        <meta name="robots" content="index, nofollow">
        //    — разрешается индексировать содержимое страницы, но не следовать по ссылкам, которые робот на ней обнаружит
        //        <meta name="robots" content="noindex, nofollow">
        //    — запрещается и индексировать содержимое страницы, и следовать по ссылкам
        //        <meta name="robots" content="index, follow">
        //    — разрешается и индексировать содержимое страницы, и следовать по ссылкам.
    }



    private function add_prefix_if_needed($prefix, $string) {
        $seq = $string;
        if (strpos($seq, $prefix) !== 0)
            $seq = $prefix . $seq;
        return $seq;
    }



    /**
     * merge_with_meta_yml
     */
    private function merge_with_meta_yml() {
        $this->metas = Arr::mergeRecursiveDistinct(
            $this->default_settings,
            $this->parseConfigYaml('meta.yml')
        );
    }


    /**
     * replace_available_vars_in_global
     */
    protected function replace_available_vars_in_global () {
        $replace_list = $this->replacements_global;
        foreach ($replace_list as $seq)
            $this->replace_value(self::GLOBAL_PATH . '.' . $seq);
    }


    /**
     * inherit_from_routing_yml
     */
    protected function inherit_from_routing_yml () {
        $global_meta = $this->metas['global']['meta'];
        $routes    = &$this->metas['routes'];


        // getting meta data from routing.yml
        $routings = $this->app['config']->get('routing');
        foreach ($routings as $yml_route_name => $yml_route_data) {
            if (!isset($routes[$yml_route_name]))
                $routes[$yml_route_name] = [];

            // @todo fill titles too

            // if NOT set <route>.meta in meta.yml, taking from global metas
            if (!isset($routes[$yml_route_name]['meta'])) {
                $routes[$yml_route_name]['meta'] = $global_meta;
            }

            // if ISset <route>.meta in routing.yml - merging with data for <route>.meta
            if (isset($yml_route_data['meta'])) {
                $routes[$yml_route_name] = (self::METAS_OVER_ROUTING)
                    ? array_merge($yml_route_data['meta'], $routes[$yml_route_name])
                    : array_merge($routes[$yml_route_name], $yml_route_data['meta']);
            }

            // if ISset <route>.label in routing.yml - if it's set as <route>.label, not as <route>.META.label)
            if (isset($yml_route_data['label'])) {
                if (!isset($routes[$yml_route_name]['label']) || !self::METAS_OVER_ROUTING) {
                    $routes[$yml_route_name]['label'] = $yml_route_data['label'];
                }
            }
        }
    }




    private function fill_blank_fields_for_routes() {
        $default_pattern = $this->get_meta_value_by_seq(self::VARS_PATH.'.page_title.default_pattern');
        $default_image   = $this->get_meta_value_by_seq(self::GLOBAL_PATH.'.meta.share.image');
        $default_og_type = $this->get_meta_value_by_seq(self::GLOBAL_PATH.'.meta.og_type');

        foreach ($this->metas['routes'] as $route_name => $route) {
            if (empty($route['title'])) {
                $this->set_meta_value("routes.{$route_name}.title", $default_pattern);
            }

            $image = $this->get_meta_value_by_seq("routes.{$route_name}.meta.share.image");
            if (empty($image)) {
                $this->set_meta_value("routes.{$route_name}.meta.share.image", $default_image);
            }

            $og_type = $this->get_meta_value_by_seq("routes.{$route_name}.meta.og_type");
            if (empty($og_type)) {
                $this->set_meta_value("routes.{$route_name}.meta.og_type", $default_og_type);
            }


            // checking <route>.parent for existence and correctness
            if (isset($route['parent'])) {
                $parent = $route['parent'];
                if (!is_string($parent)){
                    unset($this->metas['routes'][$route_name]['parent']);
                }
                else {
                    $parent = trim($route['parent']);
                    if (empty($parent) || !isset($this->metas['routes'][$parent]))
                        unset($this->metas['routes'][$route_name]['parent']);
                    else
                        $this->metas['routes'][$route_name]['parent'] = $parent;
                }
            }
        }
    }



    /**
     * replace_available_vars_in_routes
     */
    protected function replace_available_vars_in_routes () {

        foreach ($this->metas['routes'] as $route_name => $route) {
            $this->replace_value("routes.{$route_name}.title", $this->get_meta_value_by_seq("routes.{$route_name}"));

//            $add_vars = (!empty($route['label'])) ? [
//                'label' => $route['label'],
////                    'context' => ['post'=>['title' => 'TUTLE']]
//            ] : [];
//            $this->replace_value("routes.{$route_name}.title", $add_vars);

        }

//        dump($this->metas);
    }


    //——————————————————————————————————————————————————————————————————————————————————————————————————————————————————


    /**
     * replace_vars_string
     * @param       $str
     * @param array $additional_vars
     * @return mixed
     */
    protected function replace_vars_string($str, $additional_vars = []) {
        // finding all vars in string
        $t = $this->var_tags;
        $pattern = "/".$t['open']."([^".$t['close']."]*)".$t['close']."/";
        preg_match_all($pattern, $str, $matches);

        // making an array of unique var_slugs
        $vars_to_replace = [];
        if ($matches[1])
            $vars_to_replace = array_unique(array_values($matches[1]));

        //replacing var_slugs to values, if found
        $new_string = $str;
        foreach ($vars_to_replace as $var_slug) {
            $value = $this->find_special_var($var_slug, $additional_vars);
            if ($value !== null)
                $new_string = str_replace("{$t['open']}$var_slug{$t['close']}" , $value, $new_string);
        }

        return $new_string;
    }


    /**
     * find_special_var
     * @param       $var_slug
     * @param array $additional_vars
     * @return null
     */
    protected function find_special_var($var_slug, $additional_vars = []) {
        $delimiter=self::DELIMITER;
        $value = null;
        $seq = false;

        // if there's a delimiter ("." by default) in string
        $first_delimiter_pos = strpos($var_slug, $delimiter);
        if ($first_delimiter_pos !== false  &&  $first_delimiter_pos > 0) {
            $split_var_slug = explode($delimiter, $var_slug);
            $first_part = $split_var_slug[0];
            $rest_part = substr($var_slug, $first_delimiter_pos + 1);

            switch ($first_part) {
                case 'global'   :
                    $value = $this->get_meta_value_by_seq( self::GLOBAL_PATH . "." . $rest_part );
                    break;

                case 'vars'   :
                    $value = $this->get_meta_value_by_seq( self::VARS_PATH . "." . $rest_part );
                    break;

                case 'parts'  :
                    $value = $this->get_meta_value_by_seq( self::VARS_PATH . ".page_title.parts." . $rest_part );
                    break;

                case 'context':
                    if (!empty($additional_vars['context'])) {
                        $value = $this->_get_value($additional_vars['context'], $rest_part, null);
                    }
                    break;

                case 'bolt':
                    $rest_part = str_replace(self::DELIMITER, '/', $rest_part);
                    $value = $this->app['config']->get($rest_part);
                    break;
            }
        }

        if ($seq) {

        }

        if ($value===null)
            if (isset($additional_vars[$var_slug])) {
                $value = $additional_vars[$var_slug];
            }

        return $value;
    }


    /**
     * get_meta_value_by_seq
     * @param      $str
     * @param null $not_found_value
     * @return null
     */
    protected function get_meta_value_by_seq($str, $not_found_value = null) {
        return $this->_get_value($this->metas, $str, $not_found_value);
    }


    /**
     * _get_value
     * @param      $arr
     * @param      $str
     * @param null $not_found_value
     * @return null
     */
    protected function _get_value($arr, $str, $not_found_value = null) {
        $delimiter=self::DELIMITER;
        $breadcrumbs = explode($delimiter, $str);
        foreach ($breadcrumbs as $key) {
            if (!isset($arr[$key]))
                return $not_found_value;
            $arr = $arr[$key];
        }
        return $arr;
    }


    /**
     * set_meta_value
     * @param $str
     * @param $value
     */
    protected function set_meta_value($str, $value) {
        $this->_set_value($this->metas, $str, $value);
    }


    /**
     * _set_value
     * @param $arr
     * @param $str
     * @param $value
     */
    protected function _set_value(&$arr, $str, $value) {
        $delimiter=self::DELIMITER;
        $breadcrumbs = explode($delimiter, $str);
        $depth = count($breadcrumbs);
        foreach ($breadcrumbs as $level => $key) {
            if ($level+1 < $depth) {
                if (!isset($arr[$key]))
                    $arr[$key] = [];
                $arr = &$arr[$key];
            }
            else {
                $arr[$key] = $value;
            }
        }
    }


    /**
     * ### replace_value
     * gets value by sequence, replaces
     * @param       $seq
     * @param array $additional_vars
     * @return mixed
     */
    protected function replace_value($seq, $additional_vars = []) {
        $value = $this->get_meta_value_by_seq($seq);
        $new_value = $this->replace_vars_string($value, $additional_vars);
        if ($new_value !== $value)
            $this->set_meta_value($seq, $new_value);
        return $new_value;
    }


    /**
     * Read and parse a YAML configuration file
     *
     * @param string $filename The name of the YAML file to read
     * @param string $path     The (optional) path to the YAML file
     *
     * @return array
     */
    protected function parseConfigYaml($filename, $path = null)
    {
        // Initialise parser
        if ($this->yamlParser === false) {
            $this->yamlParser = new Parser();
        }

        // By default we assume that config files are located in app/config/
        $path = $path ?: $this->app['resources']->getPath('config');
        $filename = $path . '/' . $filename;

        if (!is_readable($filename)) {
            return [];
        }

        $yml = $this->yamlParser->parse(file_get_contents($filename) . "\n");

        // Unset the repeated nodes key after parse
        unset($yml['__nodes']);

        // Invalid, non-existing, or empty files return NULL
        return $yml ?: [];
    }


    private function get_all_metas_for_route ($route_name = false) {
        if (!$route_name) {
            $route_name = $this->app['request_stack']->getCurrentRequest()->get('_route');
        }
        if (isset($this->metas['routes'][$route_name])){
            $route_data = $this->metas['routes'][$route_name];
            $route_data['name'] = $route_name;
            return $route_data;
        }
        else
            throw new \LogicException("no such route: $route_name");
    }


    protected function get_seq_by_meta_name ($meta) {
//        $seq = '';
        $seq = $meta;
        switch ($meta) {
//            case 'title' :
//            case 'label' :
//                $seq = $meta;
//                break;

            case 'description' :
            case 'keywords' :
                $seq = 'meta.' . $meta;
                break;

            case 'heading' :
            case 'comment' :
            case 'comment_short' :
            case 'image' :
                $seq = 'meta.share.' . $meta;
                break;
        }
        return $seq;
    }




    //——————————————————————————————————————————————————————————————————————
    // PUBLIC
    //——————————————————————————————————————————————————————————————————————



    /**
     * get_meta_for_site
     * @param string       $seq - relative or absolute path
     * @param bool|false   $root  - ["global" by default] - "global" or "routes.post" for example
     * @return null
     */
    public function get_meta_for_site ($seq, $root = self::GLOBAL_PATH) {

        // @todo reduce this function
        //if seq is not a sequence, but meta-name
        switch ($seq) {
            case 'description' :
            case 'keywords' :
                $seq = $root . '.meta.' . $seq;
                break;

            case 'heading' :
            case 'comment' :
            case 'comment_short' :
            case 'image' :
                $seq = $root . '.meta.share.' . $seq;
                break;

            default :
                $seq = false;
        }

        if ($seq === false) {
            // @todo thinking about this part.. should i restrict that only to globals or not
            // if not found in other commands and starting with this "<root>." ("global." for example)
            // add "<root>."
            if (strpos($seq, "{$root}.") !== 0) {
                $seq = $root . '.' . $seq;
            }
        }

        //getting data by sequence
        $data = $this->get_meta_value_by_seq($seq);
        return $data;
    }



    /**
     * get_meta_for_route
     * @param            $seq
     * @param bool|false $context
     * @param bool|false $route
     * @return mixed|null
     */
    public function get_meta_for_route ($seq, $arg1 = false, $arg2 = false) {
        // detecting where's context, where's route slug
        $args = func_get_args();


        $context = false;
        $route   = false;
        if (count($args) > 1)
            for ($i=1; $i < count($args); $i++) {
                $arg = $args[$i];
                if (is_string($arg))
                    $route = $arg;
                else
                    $context = $arg;
            }


        // getting all route metas (with current route detection if it's false)
        $route_metas = $this->get_all_metas_for_route($route);

        $is_seq = (strpos($seq, self::DELIMITER) !== false);

        if (!$is_seq)
            $seq = $this->get_seq_by_meta_name($seq);

        $result = $this->_get_value($route_metas, $seq);

        if ($context !== false)
//            $result = $this->replace_contexted($seq, $result, $context);
            $result = $this->replace_vars_string($result, ['context' => $context]);
        return $result;

    }



    public function replace_contexted ($string, $context) {
        $result = $this->replace_vars_string($string, ['context' => $context]);
        return $result;
    }





//    public function get_meta_for_route_contexted ($seq, $context, $route = false) {
//        $meta = $this->get_meta_for_route($seq, $route);
//        return $this->replace_vars_string($meta, ['context' => $context]);
//    }

//    public function get_record_meta ($seq = false, $context = false) {
//        if ($seq === false  &&  $context === false) {
//            //getting from current route
//        }
//
//
//        $is_seq = (strpos($seq, self::DELIMITER) !== false);
//
//        if ($is_seq)
////        if ( ){
//            //it's
////        }
//        $result = null;
//
//        if ($context === false) {
//            // get from current route
//        }
//        elseif (is_string($context)) {
//            // it's for getcontent
//        }
//        elseif (is_array($context) or is_object($context)){
//            $data = $this->get_meta_value_by_seq($seq);
//            // it's object
//        }
//
//        return $result;
//    }




    /**
     * get_canonical_url
     * getting canonical url with diferent arguments.
     * @param string|bool    $route   - could be route name or false
     * @param object|bool    $record  - could be a record or false
     * @return string
     */
    public function get_canonical_url($route = false, $record = false) {
        $request = $this->app['request_stack']->getCurrentRequest();

        $canonical = $this->metas['global']['siteurl'] ?: $this->app['config']->get('general/canonical');

        $base_url = $canonical  ?:  $request->getBaseUrl();

        $canonical_base = $request->getScheme().'://' . $base_url;

        if ($route === false) { // for current route
            $path = $request->getPathInfo();
        }
        elseif ($route === '' || $route === '/') { //syntax sugar for for homepage route
            $path = '/';
        }
        else {
            if ($record === false) { // it's a route
                $url_opts = [];
                $path = $this->app['url_generator']->generate($route, $url_opts, UrlGeneratorInterface::ABSOLUTE_PATH);
            }
            else {  // it's a record
                if (method_exists($record, 'get') && $record->get('slug')) {
                    $url_opts = ['slug' => $record->getSlug()];
                    $path = $this->app['url_generator']->generate($route, $url_opts, UrlGeneratorInterface::ABSOLUTE_PATH);
                }
                else {
                    $path = '';
                }
            }
        }
        return $canonical_base . $path;
    }





    /**
     * get_hard_var
     * @param             $type
     * @param             $sequence
     * @param array|bool $route_data
     * @param object|bool $record_data
     * @return string
     */
    function get_hard_var ($type, $sequence, $route_data=false, $record_data=false) {

        // example 'route:meta.share.comment_short'
        $sequence = explode(':', trim($sequence));
        if (count($sequence) > 1) {
            $where = $sequence[0];
            $what  = $sequence[1];
        }
        else {
            $where = $type;
            $what  = $sequence[0];
        }

        $seq = $what;

        switch ($where ) {
            case 'site':
                $seq = $this->add_prefix_if_needed(self::GLOBAL_PATH.'.', $seq);
                $value = $this->get_meta_value_by_seq($seq);
                $value = $this->replace_vars_string($value);
                break;
            case 'route':
                $seq = $this->add_prefix_if_needed("routes.{$route_data['name']}.", $seq);
                $value = $this->get_meta_value_by_seq($seq);
                $value = $this->replace_vars_string($value);
                break;
            case 'record':
                $value = $record_data->get($what);
                break;

            default :
                throw new InvalidArgumentException('Wrong share type in generate_share_data : '. (is_string($where) ? $where : ' wrong type'));
        }

        return $value;

    }



    /**
     * generate_share_data
     * @param string|object|bool|false $route_name
     * @param string|object|bool|false $subject
     * @return array
     */
    public function generate_share_data ($route_name = false, $subject = false) {
        if ($route_name === false ) { // if ()
            $type = 'route';
            $route_name = $this->app['request_stack']->getCurrentRequest()->get('_route');
        }
        else {
            if ($subject === false) { // if only 1 arg.
                if ($route_name === 'site') { // if ('site')
                    $type = 'site';
                }
                elseif (is_string($route_name)) { // if (string)
                    $type = 'route';
                }
                else {// if (object)
                    $subject = $route_name;
                    $route_name = false;
                    $type = 'record';
                }
            }
            elseif (!is_string($subject)) { // if (string, object)
                $type = 'record';
            }
            else {  // if (string, string) - wtf?
                throw new InvalidArgumentException('Wrong share type in generate_share_data. Second argument == : '. $subject);
            }
        }

        if ($type === 'route' && !isset($this->metas['routes'][$route_name]))
            throw new InvalidArgumentException('There\'s no such route in generate_share_data : ' . $route_name . ' among [' . implode(array_keys($this->metas['routes']), ', ' ). ']' );

        $shares      = [];
        $route_data  = false;
        $record_data = false;
        switch ( $type ) {
            case 'site':
                $shares['url'] = $this->get_canonical_url('/');
                break;
            case 'route':
                $route_data  = $this->get_all_metas_for_route($route_name);
                $shares['url'] = $this->get_canonical_url($route_name);
                break;
            case 'record':
                $record_data = $subject;
                $route_data  = $this->get_all_metas_for_route();
                $shares['url'] = $this->get_canonical_url($route_name, $record_data);
                break;
        }

        $mapping = $this->mappings[$type];

        foreach ($mapping as $share_name => $sequences) {
            $sequences = $mapping[$share_name];

            if (is_string($sequences))
                $sequences = [$sequences];

            foreach ($sequences as $sequence) {
                $value = $this->get_hard_var($type, $sequence, $route_data, $record_data);

                if (!empty($value)) {
                    $shares[$share_name] = $value;
                    break;
                }
            }
        }
        return $shares;
    }


    public function print_all_ogs ($route_name = false, $subject = false) {
//        $ogs = $this->metapocket->generate_all_ogs($route_name, $subject);


        if (is_array($route_name))
            $data = $route_name;
        else
            $data = $this->generate_share_data($route_name, $subject);

        $ogs = [];
        foreach ($this->og_mapping as $og_name => $option_name) {
            if (isset($data[$option_name]))
                $ogs[$og_name] = $data[$option_name];
        }

        $metas = [];
        foreach ($ogs as $og_name => $og_value) {
            $metas []= "<meta name='og:{$og_name}' content='{$og_value}'/>";
        }
        return implode($metas, "\n");
    }


//    public function get_social($social, $route_name = false, $subject = false) {
//        $options = $this->metapocket->generate_share_data($route_name, $subject);
//        dump($options);
//    }


    /**
     * generate_og_url
     * @param $og
     * @param $options
     */
//    public function generate_one_og ($og, $options) {
//        $og_field_name = $this->og_mapping[$og];
//        return $options[$og_field_name];
//    }

    public function generate_all_ogs ($route_name = false, $subject = false) {
        if (is_array($route_name))
            $data = $route_name;
        else
            $data = $this->generate_share_data($route_name, $subject);

        $ogs = [];
        foreach ($this->og_mapping as $og_name => $option_name) {
            if (isset($data[$option_name]))
                $ogs[$og_name] = $data[$option_name];
        }
        return $ogs;
    }

    //    image
    //    title
    //    url
    //    description
    //    site_name
    //    type

}
