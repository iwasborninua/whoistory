<?php

require 'vendor/autoload.php';

$from = new DateTime('2017-06-01');
$to = new DateTime('2018-12-31');

$loop = React\EventLoop\Factory::create();
$browser = (new Clue\React\Buzz\Browser($loop))
    ->withOptions(['timeout' => 3]);

$handle = fopen('domains.txt', 'w');

$q = new Clue\React\Mq\Queue(5, null, function ($url) use ($handle, $browser) {
    return $browser->get($url)
        ->then(function ($response) use ($url, $handle) {
            $crawler = new Symfony\Component\DomCrawler\Crawler((string) $response->getBody());

            $links = $crawler->filter('div.left a');

            $links->each(function ($node) use ($handle) {
                if (substr($node->attr('href'), 0, 1) !== '/') {
                    fwrite($handle, $node->text() . "\n");
                }
            });

            echo $url . ' : ' . $links->count() . PHP_EOL;
        });
});
while (strtotime($from->format('Y-m-d')) < strtotime($to->format('Y-m-d'))) {
    $url = "http://www.whoistory.com" . $from->format('/Y/m/d/');
    $promises[] = $q($url)
        ->otherwise(function ($e) use ($q, $url) {
            echo $e->getMessage() . PHP_EOL;
            return $q($url);
        });

    $from->modify('+1 day');
}
React\Promise\all($promises)
    ->then(function () use ($handle) {
        fclose($handle);
    });
$loop->run();