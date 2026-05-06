<?php

namespace Tests\Unit\Services\User;

use App\Contracts\Shared\OtpServiceInterface;
use App\Contracts\Shared\SmsServiceInterface;
use App\Exceptions\User\PhoneAlreadyTakenException;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;
    private OtpServiceInterface $otp;
    private SmsServiceInterface $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otp     = $this->createMock(OtpServiceInterface::class);
        $this->sms     = $this->createMock(SmsServiceInterface::class);
        $this->service = new UserService($this->otp, $this->sms);
    }

    public function test_update_profile_updates_name_and_bio(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->updateProfile($user, ['name' => 'New Name', 'bio' => 'My bio']);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('My bio', $updated->bio);
    }

    public function test_update_avatar_updates_avatar_key(): void
    {
        $user = User::factory()->create();

        $updated = $this->service->updateAvatar($user, 'avatars/new-uuid.jpg');

        $this->assertSame('avatars/new-uuid.jpg', $updated->avatar);
    }

    public function test_send_phone_otp_throws_when_phone_taken(): void
    {
        User::factory()->create(['phone' => '+6281234567890']);
        $user = User::factory()->create();

        $this->otp->expects($this->never())->method('generate');
        $this->sms->expects($this->never())->method('sendOtp');

        $this->expectException(PhoneAlreadyTakenException::class);

        $this->service->sendPhoneOtp($user, '+6281234567890', Request::create('/'));
    }

    public function test_verify_phone_updates_user_phone_and_verified_at(): void
    {
        $user = User::factory()->create();

        $this->otp->expects($this->once())->method('verify')->willReturn(true);

        $this->service->verifyPhone($user, '+6281234567890', '123456');

        $user->refresh();
        $this->assertSame('+6281234567890', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_normalizes_local_format_to_e164(): void
    {
        $user = User::factory()->create();

        $this->otp->expects($this->once())
            ->method('generate')
            ->with('phone:+6281234567890')
            ->willReturn('999999');

        $this->sms->expects($this->once())->method('sendOtp');

        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->service->sendPhoneOtp($user, '081234567890', $request);
    }
}
