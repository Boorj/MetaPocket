<?php
/*
 * 10.11.2015 14:50  ! created by Klaus aka Boorj aka Mike.
 * */

namespace Bolt\Extension\Mapple\MetaPocketExtension;

use Bolt\Application;
use Bolt\BaseExtension;

require_once('MetaPocket.php');
require_once('SocialShares.php');

class Extension extends BaseExtension
{

    /* @var MetaPocket $metapocket */
    private $metapocket = null;

    public function getName()
    {
        return "MetaPocketExtension";
    }

    public function initialize()
    {
        $this->app['metapocket'] = $this->app->share(function ($app) { return new MetaPocket($app); });
        $this->app['metapocket']->initialize();

        $this->metapocket = &$this->app['metapocket'];

        $this->addTwigFunction('get_share_data',   [$this->app['metapocket'], 'generate_share_data'          ]);

        $this->addTwigFunction('print_site_meta',  'print_site_meta'  );
        $this->addTwigFunction('print_route_meta', 'print_route_meta' );
        $this->addTwigFunction('print_record_meta', 'print_route_meta' );

        $this->addTwigFunction('print_social',     'print_social');
        $this->addTwigFilter(  'print_social',     'print_social');
        $this->addTwigFunction('social_url' ,      'social_url');
        $this->addTwigFilter  ('social_url' ,      'social_url');

        $this->addTwigFunction('print_all_ogs',    [$this->app['metapocket'], 'print_all_ogs' ]  );
        $this->addTwigFilter(  'print_all_ogs',    [$this->app['metapocket'], 'print_all_ogs' ]  );
        $this->addTwigFunction('canonical_url',    [$this->app['metapocket'], 'get_canonical_url' ]);
        $this->addTwigFilter(  'canonical_url',    'get_canonical_url_for_record' );

        $this->addTwigFunction('site_meta',        [$this->app['metapocket'], 'get_meta_for_site'            ]);
        $this->addTwigFunction('route_meta',       [$this->app['metapocket'], 'get_meta_for_route'           ]);
        $this->addTwigFunction('generate_all_ogs', [$this->app['metapocket'], 'generate_all_ogs'             ]);
    }

//    public function get_meta_for_site($meta_or_sequence) {
//        $str = $this->app['metapocket']->get_meta_for_site($meta_or_sequence);
//        return new \Twig_Markup($str, 'UTF-8');
//    }
//    public function get_meta_for_route($seq, $route = false) {
//        $str = $this->app['metapocket']->get_meta_for_route($seq, $route);
//        return new \Twig_Markup($str, 'UTF-8');
//    }



    public function print_site_meta($meta_or_sequence) {
        $meta_value = $this->metapocket->get_meta_for_route($meta_or_sequence);
        $str = $this->print_meta($meta_or_sequence, $meta_value);
        return new \Twig_Markup($str, 'UTF-8');
    }


    public function print_route_meta($seq, $route = false) {
        $meta_value = $this->metapocket->get_meta_for_route($seq, $route);
        $str = $this->print_meta($seq, $meta_value);
        return new \Twig_Markup($str, 'UTF-8');
    }


    public function print_record_meta($seq, $context = false, $route = false) {
        dump([$seq, $route, $context]);
        $meta_value = $this->metapocket->get_meta_for_route($seq, $route, $context);
//        $meta_value = $this->metapocket->replace_contexted($meta_value, $context);
        $str = $this->print_meta($seq, $meta_value);
        return new \Twig_Markup($str, 'UTF-8');
    }


    public function social_url($data, $social) {
        $str = SocialShares::generate_share_url($social, $data);
        return new \Twig_Markup($str, 'UTF-8');
    }


    public function print_social($data, $social, $attr=[], $inner='', $print_title=true) {
        $str = SocialShares::generate_share_link($social, $data, $attr, $inner, $print_title);
        return new \Twig_Markup($str, 'UTF-8');
    }


    public function get_canonical_url_for_record($record, $route) {
        return $this->metapocket->get_canonical_url($route, $record);
    }


    protected function print_meta ($meta, $value) {
        if (empty($value))
            return '';
        switch ($meta) {
            case 'title' :
                return '<title>' . trim($value).'</title>';

            case 'keywords' :
                if (is_array($value) || is_string($value)) {
                    $keywords = (is_string($value) ? trim($value) : implode(', ', $value));
                    return '<meta name="keywords" content="' . $keywords . '"/>';
                }
                break;

            case 'image' :
                return
                    '<meta itemprop="image" content="'.$value.'">'
                   .'<link rel="image_src" href="'    .$value.'">';

            case 'description' :
                return '<meta name="description" content="'.$value.'">';

        }
        return '';
    }

}
