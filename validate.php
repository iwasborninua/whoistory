<?php
require 'vendor/autoload.php';

$domains = function () {
    $f = fopen('domains.txt', 'r');
    try {
        while ($line = fgets($f)) {
            yield trim($line, "\r\n\t");
        }
    } finally {
        fclose($f);
    }
};
$customConfigLoader = new class implements Amp\Dns\ConfigLoader {
    public function loadConfig(): Amp\Promise
    {
        return Amp\call(function () {
            $hosts = yield (new Amp\Dns\HostLoader)->loadHosts();
            return new Amp\Dns\Config([
                "8.8.8.8:53",
                "[2001:4860:4860::8888]:53",
            ], $hosts, $timeout = 5000, $attempts = 3);
        });
    }
};
Amp\Dns\resolver(new Amp\Dns\BasicResolver(null, $customConfigLoader));
Amp\Loop::run(function () use ($domains) {
    $semaphore = new Amp\Sync\LocalSemaphore(10);
    foreach ($domains() as $domain) {
        $lock = yield $semaphore->acquire();

        Amp\Dns\resolve($domain, Amp\Dns\Record::A)
            ->onResolve(function ($error, $result) use ($domain, $lock) {
                $lock->release();

                if ($error === null) {
                    echo $domain . PHP_EOL;
                } else {
                    echo $error->getMessage() . PHP_EOL;
                }
            });
    }
});