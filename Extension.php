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

        $this->addTwigFunction('site_meta', 'get_site_meta');
        $this->addTwigFunction('route_meta', 'get_route_meta');
        $this->addTwigFunction('route_meta_contexted', 'get_route_meta_contexted');
    }

    public function get_site_meta($meta_or_sequence) {
        return $this->app['metapocket']->get_site_meta($meta_or_sequence);
    }
    public function get_route_meta($seq, $route = false) {
        return $this->app['metapocket']->get_route_meta($seq, $route);
    }
    public function get_route_meta_contexted($seq, $context, $route = false) {
        return $this->app['metapocket']->get_route_meta_contexted($seq, $context, $route);
    }
}
