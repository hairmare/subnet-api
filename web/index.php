<?php
/**
 * simple web app that returns info on a subnet through a JSON api
 *
 * @licence GPLv3
 * @author Lucas Bickel <hairmare@purplehaze.ch>
 */

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 

#$app['debug'] = true;
$app['cache_ttl'] = 86400;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../app/views',
));

$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__ . '/../app/cache/',
    'http_cache.options' => [
        'default_ttl' => $app['cache_ttl'],
    ],
));

$app->get('/', function() use($app) {
    return $app['twig']->render('index.html.twig');
});

$app->get('/swagger.json', function() use($app) {
    $response = new Symfony\Component\HttpFoundation\Response(
        '',
        200,
        ['Content-Type' => 'application/json']
    );
    return $app['twig']->render('swagger.json.twig', [], $response);
});

$app->get('/subnet/{ip}/{mask}', function($ip, $mask) use($app) {
    $subnet = $ip . '/' . $mask;

    try {
        $subnet_info = IPTools\Network::parse($subnet)->info;
    } catch (Exception $e) {
        $app->abort(400, $e->getMessage());
    }

    return $app->json(
        $subnet_info,
        200,
        [
            'Cache-Control' => 's-maxage=' . $app['cache_ttl'] . ', public',
            'ETag' => md5($subnet)
        ]
    );
})->assert('ip', '[\w\.\:]+')->assert('mask', '[0-9]+');

$app->error(function (\Exception $e, $code) use($app) {
    return $app->json(['error' => $e->getMessage()]);
});

if ($app['debug']) {
    $app->run();
} else {
    $app['http_cache']->run();
}
