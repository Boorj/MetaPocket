<?php
/*
 * 10.11.2015 14:50  ! created
 * */

namespace Bolt\Extension\Mapple\MetaPocketExtension;

use Bolt\Application;
use Bolt\BaseExtension;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Yaml\Parser;
use Bolt\Helpers\Arr;

class MetaPocket
{
    /** @var Application $app   */
    private $app = null;

    /** @var \Symfony\Component\Yaml\Parser */
    protected $yamlParser = false;

    protected $metas = null;

    const DELIMITER          = '.';
    const GLOBAL_PATH        = 'global';
    const VARS_PATH          = 'global.vars';
    const METAS_OVER_ROUTING = true;
    const INHERIT_METAS      = true;

    public $var_tags = [];
    public $default_settings = [];



    function __construct($app) {
        $this->app = $app;

        $this->var_tags = ['open'=>'%', 'close'=>'%'];

        $this->default_settings = [
            'global' => [
                'contacts'=>[],
                'vars'=>[
                    'page_title' => [
                        'default_pattern' => '%parts.sitelabel% | %label%',
                        'parts' => [
                            'sitelabel' => 'Site Name',
                        ],
                    ],
                ],
                'meta' => [
                    'description' => '',
                    'keywords' => '',
                    'share' => [
                        'heading' => '',
                        'comment' => '',
                        'comment_short' => '',
                        'image' => '',
                    ],
                ],
            ],
            'routes' => [
                'home' => [
                    'label' => '%parts.sitelabel%',
                ],
            ],
        ];
    }



    /**
     * replace_available_vars_in_metas
     */
    protected function replace_available_vars_in_metas () {

        $default_pattern = $this->replace_value(self::VARS_PATH.'page_title.default_pattern');


        foreach ($this->metas['routes'] as $route_name => $route) {
            if (empty($route['title'])) {
                $this->set_meta_value_by_seq("routes.{$route_name}.title", $default_pattern);
            }
            $add_vars = (!empty($route['label'])) ? [
                'label' => $route['label'],
//                    'context' => ['post'=>['title' => 'TUTLE']]
            ] : [];
            $this->replace_value("routes.{$route_name}.title", $add_vars);

        }

//        dump($this->metas);
    }


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

        $first_delimiter_pos = strpos($var_slug, $delimiter);
        if ($first_delimiter_pos !== false  &&  $first_delimiter_pos > 0) {
            $split_var_slug = explode($delimiter, $var_slug);
            $first_part = $split_var_slug[0];
            $rest_part = substr($var_slug, $first_delimiter_pos + 1);

            switch ($first_part) {
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
     * set_meta_value_by_seq
     * @param $str
     * @param $value
     */
    protected function set_meta_value_by_seq($str, $value) {
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
     * replace_value
     * @param       $seq
     * @param array $additional_vars
     * @return mixed
     */
    protected function replace_value($seq, $additional_vars = []) {
        $value = $this->get_meta_value_by_seq($seq);
        $new_value = $this->replace_vars_string($value, $additional_vars);
        if ($new_value !== $value)
            $this->set_meta_value_by_seq($seq, $new_value);

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


    private function get_all_route_metas ($route = false) {
        if (!$route) {
            $route = $this->app['request_stack']->getCurrentRequest()->get('_route');
        }
//        if (!isset($this->metas['routes'][$route]))
//            throw new LogicException('no such route - ' . $route);

        return $this->metas['routes'][$route];
    }


    protected function get_seq_by_meta ($meta) {
        $seq = '';
        switch ($meta) {
            case 'title' :
            case 'label' :
                $seq = $meta;
                break;

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
     * initialize
     */
    public function initialize()
    {
        $app    = &$this->app;

        $this->metas = Arr::mergeRecursiveDistinct(
            $this->default_settings,
            $this->parseConfigYaml('meta.yml')
        );

        $metas  = &$this->metas;
        $routes = &$metas['routes'];

        $site_meta = $metas['global']['meta'];

        // getting meta data from routing.yml
        $routings = $app['config']->get('routing');
        foreach ($routings as $route_name => $route_data) {
            if (!isset($routes[$route_name]))
                $routes[$route_name] = [];

            // @todo fill titles too

            // if not set meta data for route in meta.yml, taking from global metas
            if (!isset($routes[$route_name]['meta'])) {
                $routes[$route_name]['meta'] = $site_meta;
            }

            // if isset route metas in routing.yml - merging
            if (isset($route_data['meta'])) {
                if (self::METAS_OVER_ROUTING)
                    $routes[$route_name] = array_merge($route_data['meta'], $routes[$route_name]);
                else
                    $routes[$route_name] = array_merge($routes[$route_name], $route_data['meta']);
            }

            // getting label from routing.yml : if it's set as routing.<route_data>.label, not as routing.<route_data>.meta.label)
            if (isset($route_data['label'])) {
                if (!isset($routes[$route_name]['label']) || !self::METAS_OVER_ROUTING) {
                    $routes[$route_name]['label'] = $route_data['label'];
                }
            }
        }

        $this->replace_available_vars_in_metas();
    }


    /**
     * get_site_meta
     * @param $seq
     * @return null
     */
    public function get_site_meta ($seq) {
        // @todo reduce this function
        //if seq is not a sequence, but meta-name
        switch ($seq) {
            case 'description' :
            case 'keywords' :
                $seq = self::GLOBAL_PATH . '.meta.' . $seq;
                break;
            case 'heading' :
            case 'comment' :
            case 'comment_short' :
            case 'image' :
                $seq = self::GLOBAL_PATH . '.meta.share.' . $seq;
                break;

            default :
                // @todo thinking about this part.. should i restrict that only to globals or not
                if (strpos($seq, self::GLOBAL_PATH . '.') !== 0) {
                    $seq = self::GLOBAL_PATH . '.' . $seq;
                }
        }
        //getting data by sequence
        $data = $this->get_meta_value_by_seq($seq);
        return $data;
    }


    public function get_route_meta ($seq, $route = false) {
        $route_metas = $this->get_all_route_metas($route);
        $is_seq = (strpos($seq, self::DELIMITER) !== false);

        if (!$is_seq)
            $seq = $this->get_seq_by_meta($seq);

        $result = $this->_get_value($route_metas, $seq);
        return $result;
    }

    public function get_route_meta_contexted ($seq, $context, $route = false) {
        $meta = $this->get_route_meta($seq, $route);
        return $this->replace_vars_string($meta, ['context' => $context]);
    }

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

}
