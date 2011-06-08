<?php
require_once __DIR__.'/../silex.phar';

$app = new Silex\Application;
$app['autoloader']->registerNamespaces(array(
    'Facebook' => __DIR__.'/../vendor/facebook/src',
));

$app->register(new Silex\Extension\SessionExtension);
$app->register(new Silex\Extension\UrlGeneratorExtension);
$app->register(new Silex\Extension\TwigExtension, array(
    'twig.path'       => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$fbload = function() use ($app) {
    if ($app['session']->get('app')) {
        $config = $app['session']->get('app');
        $app->register(new Facebook\FacebookExtension, array(
            'facebook.app_id' => $config['appid'],
            'facebook.secret' => $config['secret'],
        ));

        return true;
    }

    return false;
};


$app->get('/', function () use ($app, $fbload) {
    $application = $test_user_list = array();
    if ($fbload()) {
        $appid = $app['facebook']->getAppId();
        $application = $app['facebook']->api("/{$appid}");
        $test_user_list = $app['facebook']->api("/{$appid}/accounts/test-users");
        $test_user_list = isset($test_user_list['data']) ? $test_user_list['data'] : array();
    }

    return $app['twig']->render('index.twig', array(
        'application' => $application,
        'test_user_list' => $test_user_list,
    ));
})->bind('homepage');

$app->get('/app/new', function() use ($app) {
    return $app['twig']->render('app/new.twig');
})->bind('app_new');

$app->post('/app/new', function() use ($app) {
    $app['session']->set('app', array(
        'appid' => $app['request']->get('appid'),
        'secret' => $app['request']->get('secret'),
    ));

    return $app->redirect($app['url_generator']->generate('homepage'));
});

$app->get('/user/new', function() use ($app) {
    $response = $app['session']->get('response');
    $app['session']->remove('response');

    return $app['twig']->render('user/new.twig', array(
        'response' => $response,
    ));
})->bind('user_new');

$app->post('/user/new', function() use ($app, $fbload) {
    if ($fbload()) {
        $appid = $app['facebook']->getAppId();
        $response = $app['facebook']->api("/{$appid}/accounts/test-users", 'POST', array(
            'installed' => $app['request']->get('installed', 'false'),
            'permissions' => implode(',', $app['request']->get('permissions') ? $app['request']->get('permissions') : array()),
            'method' => 'post',
        ));
        $app['session']->set('response', $response);
    }

    return $app->redirect($app['url_generator']->generate('user_new'));
});

$app->run();

