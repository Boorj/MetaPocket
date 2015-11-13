<?php
/*
 * 10.11.2015 14:50  ! created by Klaus aka Boorj aka Mike.
 * */

namespace Bolt\Extension\Mapple\MetaPocketExtension;

use Bolt\Application;
use Bolt\BaseExtension;

require_once('MetaPocket.php');

class Extension extends BaseExtension
{

    public function getName()
    {
        return "MetaPocketExtension";
    }

    public function initialize()
    {
        $this->app['metapocket'] = $this->app->share(function ($app) { return new MetaPocket($app); });
        $this->app['metapocket']->initialize();

        $this->addTwigFunction('site_meta',            [$this->app['metapocket'], 'get_meta_for_site'       ]);
        $this->addTwigFunction('route_meta',           [$this->app['metapocket'], 'get_meta_for_route'      ]);
//        $this->addTwigFunction('route_meta_contexted', [$this->app['metapocket'], 'get_meta_for_route_contexted']);

        $this->addTwigFunction('print_site_meta',      'print_site_meta'  );
        $this->addTwigFunction('print_route_meta',     'print_route_meta' );
    }

//    public function get_meta_for_site($meta_or_sequence) {
//        return $this->app['metapocket']->get_meta_for_site($meta_or_sequence);
//    }
//    public function get_meta_for_route($seq, $route = false) {
//        return $this->app['metapocket']->get_meta_for_route($seq, $route);
//    }
//
    public function print_site_meta($meta_or_sequence) {
        $str = $this->app['metapocket']->print_site_meta($meta_or_sequence);
        return new \Twig_Markup($str, 'UTF-8');
    }
    public function print_route_meta($seq, $route = false) {
        $str = $this->app['metapocket']->print_route_meta($seq, $route);
        return new \Twig_Markup($str, 'UTF-8');
    }
//    public function get_meta_for_route_contexted($seq, $context, $route = false) {
//        return $this->app['metapocket']->get_meta_for_route_contexted($seq, $context, $route);
//    }
}
