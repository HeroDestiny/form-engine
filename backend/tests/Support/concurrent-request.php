<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Worker for the PostgreSQL-only concurrency review; never boots the normal database profile.
require dirname(__DIR__, 2).'/vendor/autoload.php';

if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'form_engine_testing'
    || getenv('DB_USERNAME') !== 'form_engine_testing' || getenv('DB_HOST') !== 'postgres-test') {
    throw new LogicException('Worker restricted to the disposable PostgreSQL testing profile.');
}

class ReviewWorker extends TestCase
{
    public function test_request(): void {}
}

$_SERVER['TEST_DB_PROFILE'] = 'isolated-postgres';
$app = (new ReviewWorker('test_request'))->createApplication();
DB::statement("SET application_name = 'review-worker'");
$request = Request::create($argv[1], 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$argv[2],
], base64_decode($argv[3], true));
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
