<?php

namespace Tests\Unit\Services\Shared;

use App\Services\Shared\SmsService;
use Illuminate\Http\Client\Factory as HttpClient;
use PHPUnit\Framework\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_sms_service_instantiates_correctly(): void
    {
        $http    = $this->createMock(HttpClient::class);
        $service = new SmsService($http);

        $this->assertInstanceOf(SmsService::class, $service);
    }
}
